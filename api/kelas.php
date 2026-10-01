<?php
require_once __DIR__ . '/_bootstrap.php';

run_api($pdo, function () use ($pdo) {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET' && ($_GET['action'] ?? '') === 'options') {
        response(true, '', $pdo->query("SELECT id AS value, CONCAT('Kelas ', kode) AS label FROM kelas ORDER BY kode")->fetchAll());
    }

    if ($method === 'GET') {
        $rows = $pdo->query('SELECT * FROM v_rekap_kelas ORDER BY kode')->fetchAll();
        $tanpa = (int) $pdo->query('SELECT COUNT(*) FROM mahasiswa WHERE kelas_id IS NULL')->fetchColumn();
        response(true, '', $rows, 200, ['meta' => ['tanpa_kelas' => $tanpa]]);
    }

    if ($method === 'POST' && ($_GET['action'] ?? '') === 'sync') {
        $n = sinkron_kelas_mahasiswa($pdo);
        response(true, $n ? "$n mahasiswa disesuaikan kelasnya berdasarkan rentang NBI." : 'Semua mahasiswa sudah sesuai rentang NBI.', ['diubah' => $n]);
    }

    if ($method === 'PUT') {
        $id = id_param();
        $in = json_body();
        $nama = trim((string) ($in['nama'] ?? ''));
        if ($nama === '') fail('Nama kelas wajib diisi.');
        foreach (['nbi_awal', 'nbi_akhir'] as $f) {
            if (!preg_match('/^[0-9]{6,20}$/', (string) ($in[$f] ?? ''))) fail("$f harus berupa angka 6-20 digit.");
        }
        $awal = (string) $in['nbi_awal'];
        $akhir = (string) $in['nbi_akhir'];
        if (((strlen($awal) <=> strlen($akhir)) ?: strcmp($awal, $akhir)) > 0) fail('NBI awal tidak boleh lebih besar dari NBI akhir.');
        $kap = (int) angka($in['kapasitas'] ?? 30, 'Kapasitas', 1, 500);

        $cek = $pdo->prepare('SELECT kode FROM kelas WHERE id <> :id AND nbi_awal <= :akhir AND nbi_akhir >= :awal LIMIT 1');
        $cek->execute([':id' => $id, ':akhir' => $akhir, ':awal' => $awal]);
        if ($bentrok = $cek->fetchColumn()) fail("Rentang NBI bertabrakan dengan Kelas $bentrok.");

        $st = $pdo->prepare('UPDATE kelas SET nama = :n, nbi_awal = :a, nbi_akhir = :b, kapasitas = :k WHERE id = :id');
        $st->execute([':n' => $nama, ':a' => $awal, ':b' => $akhir, ':k' => $kap, ':id' => $id]);
        $diubah = sinkron_kelas_mahasiswa($pdo);
        response(true, 'Kelas diperbarui.' . ($diubah ? " $diubah mahasiswa dipindahkan sesuai rentang baru." : ''));
    }

    fail('Method tidak didukung.', 405);
});
