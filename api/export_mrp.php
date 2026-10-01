<?php
// Ekspor rencana order (planned order) sebuah MRP run ke CSV.
require_once __DIR__ . '/../config/database.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo 'Sesi tidak valid. Silakan login kembali.';
    exit;
}
$runId = filter_input(INPUT_GET, 'run_id', FILTER_VALIDATE_INT);
if (!$runId) {
    http_response_code(400);
    echo 'run_id tidak valid.';
    exit;
}
$run = $pdo->prepare('SELECT kode FROM mrp_run WHERE id = :id');
$run->execute([':id' => $runId]);
$kode = $run->fetchColumn();
if (!$kode) {
    http_response_code(404);
    echo 'MRP run tidak ditemukan.';
    exit;
}

$stmt = $pdo->prepare(
    "SELECT i.kode, i.nama, po.jenis, po.qty, sa.kode AS satuan, po.tanggal_release, po.tanggal_butuh,
            IF(po.terlambat = 1, 'Ya', 'Tidak') AS terlambat, po.status, sp.nama AS supplier,
            ROUND(po.qty * i.harga_standar, 2) AS estimasi_nilai
     FROM mrp_planned_order po JOIN item i ON i.id = po.item_id JOIN satuan sa ON sa.id = i.satuan_id
     LEFT JOIN supplier sp ON sp.id = i.supplier_id
     WHERE po.run_id = :r ORDER BY po.tanggal_release, po.jenis DESC, i.kode"
);
$stmt->execute([':r' => $runId]);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="rencana_order_' . $kode . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Kode Item', 'Nama Item', 'Jenis', 'Jumlah', 'Satuan', 'Tanggal Release', 'Tanggal Dibutuhkan', 'Terlambat', 'Status', 'Supplier', 'Estimasi Nilai']);
foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) {
    fputcsv($out, $row);
}
fclose($out);
exit;
