<?php
require_once __DIR__ . '/_bootstrap.php';

/** Apakah $target dapat dicapai dari $dari lewat rantai BOM (turun ke komponen)? Dipakai deteksi siklus. */
function bom_terjangkau(PDO $pdo, int $dari, int $target): bool
{
    $antri = [$dari];
    $sudah = [];
    $stmt = $pdo->prepare('SELECT child_item_id FROM bom WHERE parent_item_id = :p');
    while ($antri) {
        $x = array_pop($antri);
        if ($x === $target) {
            return true;
        }
        if (isset($sudah[$x])) {
            continue;
        }
        $sudah[$x] = true;
        $stmt->execute([':p' => $x]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $c) {
            $antri[] = (int) $c;
        }
    }
    return false;
}

/** Ledakan BOM (indented): kebutuhan total tiap komponen untuk $qty unit induk. */
function bom_pohon(PDO $pdo, int $itemId, float $qty, int $depth = 0, array &$rows = [], array $jalur = []): array
{
    if ($depth > 15 || in_array($itemId, $jalur, true)) {
        return $rows;
    }
    $stmt = $pdo->prepare(
        'SELECT b.child_item_id, b.qty_per, b.scrap_persen, i.kode, i.nama, i.tipe, s.kode AS satuan
         FROM bom b JOIN item i ON i.id = b.child_item_id JOIN satuan s ON s.id = i.satuan_id
         WHERE b.parent_item_id = :p ORDER BY i.tipe = \'RM\', i.kode'
    );
    $stmt->execute([':p' => $itemId]);
    foreach ($stmt->fetchAll() as $r) {
        $total = round($qty * (float) $r['qty_per'] * (1 + (float) $r['scrap_persen'] / 100), 4);
        $rows[] = ['level' => $depth + 1, 'kode' => $r['kode'], 'nama' => $r['nama'], 'tipe' => $r['tipe'],
                   'satuan' => $r['satuan'], 'qty_total' => $total];
        bom_pohon($pdo, (int) $r['child_item_id'], $total, $depth + 1, $rows, array_merge($jalur, [$itemId]));
    }
    return $rows;
}

run_api($pdo, function () use ($pdo) {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $parent = id_param('parent_id');
        $item = $pdo->prepare('SELECT i.id, i.kode, i.nama, i.tipe, s.kode AS satuan FROM item i JOIN satuan s ON s.id = i.satuan_id WHERE i.id = :id');
        $item->execute([':id' => $parent]);
        $induk = $item->fetch();
        if (!$induk) fail('Item tidak ditemukan.', 404);

        $stmt = $pdo->prepare(
            'SELECT b.id, b.child_item_id, i.kode, i.nama, i.tipe, s.kode AS satuan, b.qty_per, b.scrap_persen, b.catatan
             FROM bom b JOIN item i ON i.id = b.child_item_id JOIN satuan s ON s.id = i.satuan_id
             WHERE b.parent_item_id = :p ORDER BY i.kode'
        );
        $stmt->execute([':p' => $parent]);
        $pohon = bom_pohon($pdo, $parent, 1.0);
        response(true, '', ['induk' => $induk, 'komponen' => $stmt->fetchAll(), 'pohon' => $pohon]);
    }

    if ($method === 'POST') {
        $in = json_body();
        $parent = (int) ($in['parent_item_id'] ?? 0);
        $child = (int) ($in['child_item_id'] ?? 0);
        $qtyPer = angka($in['qty_per'] ?? null, 'Qty per', 0.0001);
        $scrap = angka($in['scrap_persen'] ?? 0, 'Scrap (%)', 0, 100);

        if ($parent === $child) fail('Item tidak boleh menjadi komponen dirinya sendiri.');
        $s = $pdo->prepare('SELECT tipe FROM item WHERE id = :id');
        $s->execute([':id' => $parent]);
        $tp = $s->fetchColumn();
        $s->execute([':id' => $child]);
        $tc = $s->fetchColumn();
        if ($tp === false || $tc === false) fail('Item induk/komponen tidak ditemukan.');
        if ($tp === 'RM') fail('Bahan baku (RM) tidak boleh memiliki komponen BOM.');
        if ($tc === 'FG') fail('Barang jadi (FG) tidak boleh menjadi komponen item lain.');
        if (bom_terjangkau($pdo, $child, $parent)) {
            fail('Komponen ini akan membentuk siklus BOM (induk sudah menjadi komponen di dalam struktur tersebut).');
        }

        $pdo->prepare(
            'INSERT INTO bom (parent_item_id, child_item_id, qty_per, scrap_persen, catatan) VALUES (:p, :c, :q, :s, :n)'
        )->execute([':p' => $parent, ':c' => $child, ':q' => $qtyPer, ':s' => $scrap, ':n' => trim((string) ($in['catatan'] ?? '')) ?: null]);
        response(true, 'Komponen BOM ditambahkan.', ['id' => (int) $pdo->lastInsertId()], 201);
    }

    if ($method === 'PUT') {
        $id = id_param();
        $in = json_body();
        $st = $pdo->prepare('UPDATE bom SET qty_per = :q, scrap_persen = :s, catatan = :n WHERE id = :id');
        $st->execute([
            ':q' => angka($in['qty_per'] ?? null, 'Qty per', 0.0001),
            ':s' => angka($in['scrap_persen'] ?? 0, 'Scrap (%)', 0, 100),
            ':n' => trim((string) ($in['catatan'] ?? '')) ?: null, ':id' => $id,
        ]);
        response(true, 'Komponen BOM diperbarui.');
    }

    if ($method === 'DELETE') {
        $st = $pdo->prepare('DELETE FROM bom WHERE id = :id');
        $st->execute([':id' => id_param()]);
        if (!$st->rowCount()) fail('Baris BOM tidak ditemukan.', 404);
        response(true, 'Komponen BOM dihapus.');
    }

    fail('Method tidak didukung.', 405);
});
