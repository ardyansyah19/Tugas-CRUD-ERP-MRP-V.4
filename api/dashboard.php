<?php
require_once __DIR__ . '/_bootstrap.php';

run_api($pdo, function () use ($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail('Method tidak didukung.', 405);

    $one = fn(string $sql) => (int) $pdo->query($sql)->fetchColumn();
    $terakhir = $pdo->query(
        'SELECT r.id, r.kode, r.tanggal_mulai, r.horizon_minggu, r.total_planned_order, r.created_at, k.kode AS kelas_kode
         FROM mrp_run r LEFT JOIN kelas k ON k.id = r.kelas_id ORDER BY r.id DESC LIMIT 1'
    )->fetch() ?: null;

    response(true, '', [
        'mahasiswa' => $one('SELECT COUNT(*) FROM mahasiswa'),
        'per_kelas' => $pdo->query('SELECT kode, nama, kapasitas, jumlah_mahasiswa FROM v_rekap_kelas ORDER BY kode')->fetchAll(),
        'item' => $one('SELECT COUNT(*) FROM item WHERE aktif = 1'),
        'item_per_tipe' => $pdo->query('SELECT tipe, COUNT(*) AS jumlah FROM item WHERE aktif = 1 GROUP BY tipe')->fetchAll(),
        'stok_kritis' => $one('SELECT COUNT(*) FROM item WHERE aktif = 1 AND stok < stok_pengaman'),
        'nilai_stok' => (float) $pdo->query('SELECT COALESCE(SUM(stok * harga_standar), 0) FROM item WHERE aktif = 1')->fetchColumn(),
        'po_aktif' => $one("SELECT COUNT(*) FROM purchase_order WHERE status IN ('draft','open')"),
        'wo_aktif' => $one("SELECT COUNT(*) FROM work_order WHERE status IN ('planned','released')"),
        'planned_belum_konversi' => $one("SELECT COUNT(*) FROM mrp_planned_order WHERE status = 'planned'"),
        'mrp_terakhir' => $terakhir,
        'item_kritis' => $pdo->query(
            "SELECT i.kode, i.nama, i.stok, i.stok_pengaman, s.kode AS satuan
             FROM item i JOIN satuan s ON s.id = i.satuan_id
             WHERE i.aktif = 1 AND i.stok < i.stok_pengaman ORDER BY (i.stok - i.stok_pengaman) LIMIT 8"
        )->fetchAll(),
    ]);
});
