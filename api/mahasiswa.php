<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/erp.php';

function response($success, $message = '', $data = [], $code = 200, $extra = [])
{
    http_response_code($code);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], $extra));
    exit;
}

if (!is_logged_in()) {
    response(false, 'Sesi tidak valid. Silakan login kembali.', [], 401);
}

$method = $_SERVER['REQUEST_METHOD'];

// Method override: form-data (dibutuhkan untuk upload foto) tidak mendukung
// body PUT secara native di PHP, jadi client mengirim POST + field _method.
if ($method === 'POST' && isset($_POST['_method']) && strtoupper($_POST['_method']) === 'PUT') {
    $method = 'PUT';
}

// Semua method selain GET dianggap "menulis" -> perlu CSRF token & rate limit.
if ($method !== 'GET') {
    if (!rate_limit_check()) {
        response(false, 'Terlalu banyak permintaan, silakan coba lagi sesaat lagi.', [], 429);
    }
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!csrf_verify($token)) {
        response(false, 'Token keamanan (CSRF) tidak valid. Muat ulang halaman lalu coba lagi.', [], 403);
    }
}

/**
 * Validasi & simpan file foto profil. Mengembalikan nama file baru (relatif)
 * atau null jika tidak ada file yang diunggah.
 */
function handle_upload(?string $oldFoto): ?string
{
    if (empty($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
        return $oldFoto;
    }

    $file = $_FILES['foto'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        response(false, 'Gagal mengunggah foto (kode error: ' . $file['error'] . ').', [], 422);
    }
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        response(false, 'Ukuran foto maksimal 2MB.', [], 422);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset(ALLOWED_MIME[$mime])) {
        response(false, 'Format foto harus JPG, PNG, atau WEBP.', [], 422);
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $newName = bin2hex(random_bytes(16)) . '.' . ALLOWED_MIME[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $newName)) {
        response(false, 'Gagal menyimpan file foto ke server.', [], 500);
    }

    // Hapus foto lama jika ada penggantian.
    if ($oldFoto && is_file(UPLOAD_DIR . '/' . $oldFoto)) {
        @unlink(UPLOAD_DIR . '/' . $oldFoto);
    }

    return $newName;
}

function validate_input(array $input, PDO $pdo, ?int $excludeId = null): void
{
    foreach (['nbi', 'nama', 'jurusan', 'email'] as $field) {
        if (trim((string) ($input[$field] ?? '')) === '') {
            response(false, "Field $field wajib diisi.", [], 422);
        }
    }

    if (!preg_match('/^[0-9]{6,20}$/', trim($input['nbi']))) {
        response(false, 'NBI harus berupa angka, 6-20 digit.', [], 422);
    }

    if (!filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL)) {
        response(false, 'Format email tidak valid.', [], 422);
    }

    $noHp = trim($input['no_hp'] ?? '');
    if ($noHp !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $noHp)) {
        response(false, 'Format nomor HP tidak valid.', [], 422);
    }

    $angkatan = trim((string) ($input['angkatan'] ?? ''));
    if ($angkatan !== '' && (!ctype_digit($angkatan) || $angkatan < 2000 || $angkatan > (int) date('Y') + 1)) {
        response(false, 'Angkatan tidak valid.', [], 422);
    }

    // Cek duplikasi NBI / email di luar baris yang sedang diedit.
    $sql = 'SELECT id FROM mahasiswa WHERE (nbi = :nbi OR email = :email)';
    $params = [':nbi' => trim($input['nbi']), ':email' => trim($input['email'])];
    if ($excludeId) {
        $sql .= ' AND id != :id';
        $params[':id'] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetch()) {
        response(false, 'NBI atau email sudah digunakan mahasiswa lain.', [], 409);
    }
}

try {
    $action = $_GET['action'] ?? '';

    // ---- Statistik ringkas untuk dashboard ----
    if ($method === 'GET' && $action === 'stats') {
        $total = (int) $pdo->query('SELECT COUNT(*) FROM mahasiswa')->fetchColumn();
        $perJurusan = $pdo->query(
            'SELECT jurusan, COUNT(*) AS jumlah FROM mahasiswa GROUP BY jurusan ORDER BY jumlah DESC'
        )->fetchAll();
        $perKelas = $pdo->query('SELECT kode, nama, kapasitas, jumlah_mahasiswa FROM v_rekap_kelas ORDER BY kode')->fetchAll();
        $tanpaKelas = (int) $pdo->query('SELECT COUNT(*) FROM mahasiswa WHERE kelas_id IS NULL')->fetchColumn();
        response(true, '', ['total' => $total, 'per_jurusan' => $perJurusan, 'per_kelas' => $perKelas, 'tanpa_kelas' => $tanpaKelas]);
    }

    // ---- Daftar jurusan unik (untuk dropdown filter) ----
    if ($method === 'GET' && $action === 'jurusan_list') {
        $list = $pdo->query('SELECT DISTINCT jurusan FROM mahasiswa ORDER BY jurusan ASC')
            ->fetchAll(PDO::FETCH_COLUMN);
        response(true, '', $list);
    }

    // ---- List + pagination + search + filter + sort ----
    if ($method === 'GET') {
        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = (int) ($_GET['limit'] ?? 10);
        $limit = in_array($limit, [10, 25, 50, 100], true) ? $limit : 10;
        $offset = ($page - 1) * $limit;

        // Kolom sort yang diizinkan -> ekspresi SQL (whitelist, aman dari injection).
        $sortable = [
            'id' => 'm.id', 'nbi' => 'm.nbi', 'nama' => 'm.nama', 'jurusan' => 'm.jurusan',
            'angkatan' => 'm.angkatan', 'email' => 'm.email', 'kelas' => 'k.kode',
        ];
        $sortKey = isset($sortable[$_GET['sort'] ?? '']) ? $_GET['sort'] : 'id';
        $sort = $sortable[$sortKey];
        $dir  = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $where = [];
        $params = [];

        // Catatan: PDO dengan prepared statement native (EMULATE_PREPARES=false) tidak
        // mengizinkan nama placeholder yang sama dipakai berulang, jadi tiap kolom punya placeholder sendiri.
        $search = trim($_GET['search'] ?? '');
        if ($search !== '') {
            $where[] = '(m.nbi LIKE :s0 OR m.nama LIKE :s1 OR m.jurusan LIKE :s2 OR m.email LIKE :s3)';
            foreach ([':s0', ':s1', ':s2', ':s3'] as $ph) {
                $params[$ph] = "%$search%";
            }
        }

        $jurusan = trim($_GET['jurusan'] ?? '');
        if ($jurusan !== '') {
            $where[] = 'm.jurusan = :jurusan';
            $params[':jurusan'] = $jurusan;
        }

        $kelas = strtoupper(trim($_GET['kelas'] ?? ''));
        if ($kelas === '-') {
            $where[] = 'm.kelas_id IS NULL';
        } elseif ($kelas !== '') {
            $where[] = 'k.kode = :kelas';
            $params[':kelas'] = $kelas;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $fromSql = 'FROM mahasiswa m LEFT JOIN kelas k ON k.id = m.kelas_id';

        $countStmt = $pdo->prepare("SELECT COUNT(*) $fromSql $whereSql");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT m.id, m.nbi, m.nama, m.jurusan, m.angkatan, m.email, m.no_hp, m.alamat, m.foto,
                       m.kelas_id, k.kode AS kelas, m.created_at, m.updated_at
                $fromSql $whereSql
                ORDER BY $sort $dir, m.id DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['foto_url'] = $row['foto'] ? UPLOAD_URL_PREFIX . '/' . $row['foto'] : null;
        }

        response(true, 'Data berhasil diambil.', $rows, 200, [
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => (int) ceil($total / $limit) ?: 1,
            ],
        ]);
    }

    // ---- CREATE ----
    if ($method === 'POST') {
        $input = $_POST;
        validate_input($input, $pdo);
        $foto = handle_upload(null);
        // Kelas ditentukan otomatis dari rentang NBI (lihat tabel `kelas`).
        $kelasId = kelas_id_dari_nbi($pdo, trim($input['nbi']));

        $stmt = $pdo->prepare(
            'INSERT INTO mahasiswa (nbi, nama, jurusan, angkatan, email, no_hp, alamat, foto, kelas_id)
             VALUES (:nbi, :nama, :jurusan, :angkatan, :email, :no_hp, :alamat, :foto, :kelas_id)'
        );
        $stmt->execute([
            ':nbi' => trim($input['nbi']),
            ':nama' => trim($input['nama']),
            ':jurusan' => trim($input['jurusan']),
            ':angkatan' => trim($input['angkatan']) !== '' ? (int) $input['angkatan'] : null,
            ':email' => trim($input['email']),
            ':no_hp' => trim($input['no_hp'] ?? ''),
            ':alamat' => trim($input['alamat'] ?? ''),
            ':foto' => $foto,
            ':kelas_id' => $kelasId,
        ]);

        response(true, 'Data berhasil ditambahkan.' . ($kelasId ? '' : ' (NBI di luar rentang kelas A-D, kelas dikosongkan.)'),
            ['id' => $pdo->lastInsertId(), 'kelas_id' => $kelasId], 201);
    }

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        response(false, 'ID tidak valid.', [], 400);
    }

    // ---- UPDATE ----
    if ($method === 'PUT') {
        $input = $_POST;

        $existing = $pdo->prepare('SELECT foto FROM mahasiswa WHERE id = :id');
        $existing->execute([':id' => $id]);
        $current = $existing->fetch();
        if (!$current) {
            response(false, 'Data tidak ditemukan.', [], 404);
        }

        validate_input($input, $pdo, $id);
        $foto = handle_upload($current['foto']);

        // Dukungan hapus foto tanpa mengganti dengan yang baru (checkbox "hapus foto").
        if (!empty($input['hapus_foto']) && empty($_FILES['foto']['name'])) {
            if ($current['foto'] && is_file(UPLOAD_DIR . '/' . $current['foto'])) {
                @unlink(UPLOAD_DIR . '/' . $current['foto']);
            }
            $foto = null;
        }

        $stmt = $pdo->prepare(
            'UPDATE mahasiswa
             SET nbi=:nbi, nama=:nama, jurusan=:jurusan, angkatan=:angkatan,
                 email=:email, no_hp=:no_hp, alamat=:alamat, foto=:foto, kelas_id=:kelas_id
             WHERE id=:id'
        );
        $stmt->execute([
            ':nbi' => trim($input['nbi']),
            ':nama' => trim($input['nama']),
            ':jurusan' => trim($input['jurusan']),
            ':angkatan' => trim($input['angkatan']) !== '' ? (int) $input['angkatan'] : null,
            ':email' => trim($input['email']),
            ':no_hp' => trim($input['no_hp'] ?? ''),
            ':alamat' => trim($input['alamat'] ?? ''),
            ':foto' => $foto,
            ':kelas_id' => kelas_id_dari_nbi($pdo, trim($input['nbi'])),
            ':id' => $id,
        ]);

        response(true, 'Data berhasil diperbarui.');
    }

    // ---- DELETE ----
    if ($method === 'DELETE') {
        $existing = $pdo->prepare('SELECT foto FROM mahasiswa WHERE id = :id');
        $existing->execute([':id' => $id]);
        $current = $existing->fetch();
        if (!$current) {
            response(false, 'Data tidak ditemukan.', [], 404);
        }

        $stmt = $pdo->prepare('DELETE FROM mahasiswa WHERE id=:id');
        $stmt->execute([':id' => $id]);

        if ($current['foto'] && is_file(UPLOAD_DIR . '/' . $current['foto'])) {
            @unlink(UPLOAD_DIR . '/' . $current['foto']);
        }

        response(true, 'Data berhasil dihapus.');
    }

    response(false, 'Method tidak didukung.', [], 405);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        response(false, 'NBI atau email sudah digunakan.', [], 409);
    }
    response(false, 'Terjadi kesalahan database.', [], 500);
}
