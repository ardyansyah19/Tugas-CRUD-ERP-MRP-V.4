<?php
require_once __DIR__ . '/_bootstrap.php';

const WO_SELECT = 'SELECT w.*, i.kode AS item_kode, i.nama AS item_nama, i.pengadaan, sa.kode AS satuan,
                          k.kode AS kelas_kode, m.nama AS pic_nama, m.nbi AS pic_nbi
                   FROM work_order w JOIN item i ON i.id = w.item_id JOIN satuan sa ON sa.id = i.satuan_id
                   LEFT JOIN kelas k ON k.id = w.kelas_id LEFT JOIN mahasiswa m ON m.id = w.pic_mahasiswa_id';

function wo_ambil(PDO $pdo, int $id, bool $lock = false): array
{
    $s = $pdo->prepare('SELECT * FROM work_order WHERE id = :id' . ($lock ? ' FOR UPDATE' : ''));
    $s->execute([':id' => $id]);
    $wo = $s->fetch();
    if (!$wo) fail('Work order tidak ditemukan.', 404);
    return $wo;
}

function wo_validasi_input(PDO $pdo, array $in): array
{
    $mulai = tanggal_valid($in['tanggal_mulai'] ?? '', 'Tanggal mulai');
    $selesai = tanggal_valid($in['tanggal_selesai'] ?? '', 'Tanggal selesai');
    if ($selesai < $mulai) fail('Tanggal selesai tidak boleh sebelum tanggal mulai.');
    [$kelasId, $picId] = validasi_kelas_pic($pdo, $in['kelas_id'] ?? null, $in['pic_mahasiswa_id'] ?? null);
    return [
        'qty' => angka($in['qty'] ?? null, 'Jumlah', 0.01),
        'tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai,
        'kelas_id' => $kelasId, 'pic_mahasiswa_id' => $picId,
        'catatan' => mb_substr(trim((string) ($in['catatan'] ?? '')), 0, 255) ?: null,
    ];
}

run_api($pdo, function () use ($pdo) {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    if ($method === 'GET' && isset($_GET['id'])) {
        $s = $pdo->prepare(WO_SELECT . ' WHERE w.id = :id');
        $s->execute([':id' => id_param()]);
        $wo = $s->fetch();
        if (!$wo) fail('Work order tidak ditemukan.', 404);
        $s = $pdo->prepare(
            'SELECT c.kode, c.nama, sa.kode AS satuan, c.stok, ROUND(:q * b.qty_per * (1 + b.scrap_persen / 100), 4) AS dibutuhkan
             FROM bom b JOIN item c ON c.id = b.child_item_id JOIN satuan sa ON sa.id = c.satuan_id
             WHERE b.parent_item_id = :p ORDER BY c.kode'
        );
        $s->execute([':q' => $wo['qty'], ':p' => $wo['item_id']]);
        $wo['komponen'] = $s->fetchAll();
        response(true, '', $wo);
    }

    if ($method === 'GET') {
        [$page, $limit, $offset] = paging();
        $where = [];
        $params = [];
        if (in_array($_GET['status'] ?? '', ['planned', 'released', 'selesai', 'batal'], true)) {
            $where[] = 'w.status = :st';
            $params[':st'] = $_GET['status'];
        }
        if (!empty($_GET['kelas_id'])) {
            $where[] = 'w.kelas_id = :kl';
            $params[':kl'] = (int) $_GET['kelas_id'];
        }
        $search = trim($_GET['search'] ?? '');
        if ($search !== '') {
            $where[] = '(w.no_wo LIKE :s0 OR i.nama LIKE :s1 OR m.nama LIKE :s2)';
            $params[':s0'] = $params[':s1'] = $params[':s2'] = "%$search%";
        }
        $w = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $c = $pdo->prepare('SELECT COUNT(*) FROM work_order w JOIN item i ON i.id = w.item_id LEFT JOIN mahasiswa m ON m.id = w.pic_mahasiswa_id' . $w);
        $c->execute($params);
        $total = (int) $c->fetchColumn();

        $stmt = $pdo->prepare(WO_SELECT . $w . ' ORDER BY w.id DESC LIMIT :l OFFSET :o');
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
        $stmt->execute();
        response(true, '', $stmt->fetchAll(), 200, ['meta' => meta($page, $limit, $total)]);
    }

    // ---- Buat WO manual ----
    if ($method === 'POST' && $action === '') {
        $in = json_body();
        $itemId = (int) ($in['item_id'] ?? 0);
        $s = $pdo->prepare('SELECT pengadaan FROM item WHERE id = :id');
        $s->execute([':id' => $itemId]);
        $peng = $s->fetchColumn();
        if ($peng === false) fail('Item tidak ditemukan.');
        if ($peng !== 'produksi') fail('Work order hanya untuk item yang diproduksi (FG/SFG).');
        $d = wo_validasi_input($pdo, $in);

        $pdo->beginTransaction();
        $no = nomor_berikut($pdo, 'WO', 'work_order', 'no_wo');
        $pdo->prepare(
            "INSERT INTO work_order (no_wo, item_id, qty, tanggal_mulai, tanggal_selesai, status, kelas_id, pic_mahasiswa_id, catatan)
             VALUES (:n, :i, :q, :m, :s, 'planned', :k, :p, :c)"
        )->execute([':n' => $no, ':i' => $itemId, ':q' => $d['qty'], ':m' => $d['tanggal_mulai'], ':s' => $d['tanggal_selesai'],
                    ':k' => $d['kelas_id'], ':p' => $d['pic_mahasiswa_id'], ':c' => $d['catatan']]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        response(true, "Work order $no dibuat.", ['id' => $id], 201);
    }

    // ---- Ubah WO (hanya planned/released) ----
    if ($method === 'PUT') {
        $id = id_param();
        $wo = wo_ambil($pdo, $id);
        if (!in_array($wo['status'], ['planned', 'released'], true)) fail('Work order yang sudah selesai/batal tidak dapat diubah.', 409);
        $d = wo_validasi_input($pdo, json_body());
        $pdo->prepare(
            'UPDATE work_order SET qty = :q, tanggal_mulai = :m, tanggal_selesai = :s, kelas_id = :k, pic_mahasiswa_id = :p, catatan = :c WHERE id = :id'
        )->execute([':q' => $d['qty'], ':m' => $d['tanggal_mulai'], ':s' => $d['tanggal_selesai'], ':k' => $d['kelas_id'],
                    ':p' => $d['pic_mahasiswa_id'], ':c' => $d['catatan'], ':id' => $id]);
        response(true, 'Work order diperbarui.');
    }

    // ---- Perubahan status ----
    if ($method === 'POST') {
        $id = id_param();
        $pdo->beginTransaction();
        $wo = wo_ambil($pdo, $id, true);

        if ($action === 'release') {
            if ($wo['status'] !== 'planned') fail('Hanya work order planned yang dapat di-release.', 409);
            $pdo->prepare("UPDATE work_order SET status = 'released' WHERE id = :id")->execute([':id' => $id]);
            $pdo->commit();
            response(true, "{$wo['no_wo']} di-release.");
        }

        if ($action === 'selesai') {
            if (!in_array($wo['status'], ['planned', 'released'], true)) fail('Work order ini tidak dapat diselesaikan.', 409);
            // Backflush: komponen dipotong sesuai BOM, hasil produksi masuk stok.
            foreach (bom_komponen($pdo, (int) $wo['item_id']) as $child => $faktor) {
                stok_ubah($pdo, $child, -round((float) $wo['qty'] * $faktor, 2), 'keluar', 'WO', $id, "Pemakaian {$wo['no_wo']}");
            }
            stok_ubah($pdo, (int) $wo['item_id'], (float) $wo['qty'], 'masuk', 'WO', $id, "Hasil produksi {$wo['no_wo']}");
            $pdo->prepare("UPDATE work_order SET status = 'selesai' WHERE id = :id")->execute([':id' => $id]);
            $pdo->commit();
            response(true, "{$wo['no_wo']} selesai: komponen terpakai & hasil produksi masuk stok.");
        }

        if ($action === 'batal') {
            if (!in_array($wo['status'], ['planned', 'released'], true)) fail('Work order ini tidak dapat dibatalkan.', 409);
            $pdo->prepare("UPDATE work_order SET status = 'batal' WHERE id = :id")->execute([':id' => $id]);
            $pdo->prepare("UPDATE mrp_planned_order SET status = 'planned', wo_id = NULL WHERE wo_id = :id")->execute([':id' => $id]);
            $pdo->commit();
            response(true, "{$wo['no_wo']} dibatalkan.");
        }
        fail('Aksi tidak dikenali.', 400);
    }

    if ($method === 'DELETE') {
        $id = id_param();
        $wo = wo_ambil($pdo, $id);
        if (!in_array($wo['status'], ['planned', 'batal'], true)) fail('Hanya work order planned atau batal yang dapat dihapus.', 409);
        $pdo->prepare('DELETE FROM work_order WHERE id = :id')->execute([':id' => $id]);
        response(true, 'Work order dihapus.');
    }

    fail('Method tidak didukung.', 405);
});
