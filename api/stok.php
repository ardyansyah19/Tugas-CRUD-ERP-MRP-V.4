<?php
require_once __DIR__ . '/_bootstrap.php';

run_api($pdo, function () use ($pdo) {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET' && ($_GET['action'] ?? '') === 'mutasi') {
        $itemId = id_param('item_id');
        [$page, $limit, $offset] = paging();
        $c = $pdo->prepare('SELECT COUNT(*) FROM stok_mutasi WHERE item_id = :i');
        $c->execute([':i' => $itemId]);
        $total = (int) $c->fetchColumn();
        $stmt = $pdo->prepare('SELECT * FROM stok_mutasi WHERE item_id = :i ORDER BY id DESC LIMIT :l OFFSET :o');
        $stmt->bindValue(':i', $itemId, PDO::PARAM_INT);
        $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
        $stmt->execute();
        response(true, '', $stmt->fetchAll(), 200, ['meta' => meta($page, $limit, $total)]);
    }

    if ($method === 'GET') {
        $where = ['i.aktif = 1'];
        $params = [];
        $search = trim($_GET['search'] ?? '');
        if ($search !== '') {
            $where[] = '(i.kode LIKE :s0 OR i.nama LIKE :s1)';
            $params[':s0'] = $params[':s1'] = "%$search%";
        }
        if (in_array($_GET['tipe'] ?? '', ['FG', 'SFG', 'RM'], true)) {
            $where[] = 'i.tipe = :tipe';
            $params[':tipe'] = $_GET['tipe'];
        }
        $status = $_GET['status'] ?? '';
        if ($status === 'kritis') $where[] = 'i.stok < i.stok_pengaman AND i.stok > 0';
        if ($status === 'habis') $where[] = 'i.stok <= 0';
        if ($status === 'aman') $where[] = 'i.stok >= i.stok_pengaman AND i.stok > 0';

        $stmt = $pdo->prepare(
            "SELECT i.id, i.kode, i.nama, i.tipe, s.kode AS satuan, i.stok, i.stok_pengaman,
                    CASE WHEN i.stok <= 0 THEN 'habis' WHEN i.stok < i.stok_pengaman THEN 'kritis' ELSE 'aman' END AS status_stok,
                    ROUND(i.stok * i.harga_standar, 2) AS nilai_stok
             FROM item i JOIN satuan s ON s.id = i.satuan_id WHERE " . implode(' AND ', $where) . ' ORDER BY i.tipe DESC, i.kode'
        );
        $stmt->execute($params);
        response(true, '', $stmt->fetchAll());
    }

    if ($method === 'POST') {
        $in = json_body();
        $itemId = (int) ($in['item_id'] ?? 0);
        $delta = angka($in['qty'] ?? null, 'Jumlah');
        if ($delta == 0.0) fail('Jumlah penyesuaian tidak boleh 0.');
        $ket = trim((string) ($in['keterangan'] ?? ''));
        if ($ket === '') fail('Keterangan penyesuaian wajib diisi.');

        $pdo->beginTransaction();
        $baru = stok_ubah($pdo, $itemId, $delta, 'penyesuaian', 'MANUAL', null, mb_substr($ket, 0, 255));
        $pdo->commit();
        response(true, 'Stok disesuaikan.', ['stok' => $baru]);
    }

    fail('Method tidak didukung.', 405);
});
