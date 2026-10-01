<?php
require_once __DIR__ . '/../config/database.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo 'Sesi tidak valid. Silakan login kembali.';
    exit;
}

$search = trim($_GET['search'] ?? '');
$jurusan = trim($_GET['jurusan'] ?? '');
$kelas = strtoupper(trim($_GET['kelas'] ?? ''));

$where = [];
$params = [];
if ($search !== '') {
    // Placeholder harus unik per kemunculan (prepared statement native).
    $where[] = '(m.nbi LIKE :s0 OR m.nama LIKE :s1 OR m.jurusan LIKE :s2 OR m.email LIKE :s3)';
    foreach ([':s0', ':s1', ':s2', ':s3'] as $ph) {
        $params[$ph] = "%$search%";
    }
}
if ($jurusan !== '') {
    $where[] = 'm.jurusan = :jurusan';
    $params[':jurusan'] = $jurusan;
}
if ($kelas === '-') {
    $where[] = 'm.kelas_id IS NULL';
} elseif ($kelas !== '') {
    $where[] = 'k.kode = :kelas';
    $params[':kelas'] = $kelas;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare(
    "SELECT m.nbi, m.nama, COALESCE(k.kode, '-') AS kelas, m.jurusan, m.angkatan, m.email, m.no_hp, m.alamat
     FROM mahasiswa m LEFT JOIN kelas k ON k.id = m.kelas_id $whereSql ORDER BY k.kode, m.nbi ASC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="data_mahasiswa_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
// BOM agar Excel membaca karakter UTF-8 (misal nama dengan aksen) dengan benar.
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['NBI', 'Nama', 'Kelas', 'Jurusan', 'Angkatan', 'Email', 'No. HP', 'Alamat']);
foreach ($rows as $row) {
    fputcsv($out, $row);
}
fclose($out);
exit;
