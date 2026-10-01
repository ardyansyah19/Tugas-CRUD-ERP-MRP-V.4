<?php
require_once __DIR__ . '/_bootstrap.php';

function po_ambil(PDO $pdo, int $id, bool $lock = false): array
{
    $s = $pdo->prepare('SELECT * FROM purchase_order WHERE id = :id' . ($lock ? ' FOR UPDATE' : ''));
    $s->execute([':id' => $id]);
    $po = $s->fetch();
    if (!$po) fail('Purchase order tidak ditemukan.', 404);
    return $po;
}

run_api($pdo, function () use ($pdo) {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    if ($method === 'GET' && isset($_GET['id'])) {
        $po = po_ambil($pdo, id_param());
        $s = $pdo->prepare('SELECT s.kode, s.nama, s.telepon FROM supplier s WHERE s.id = :id');
        $s->execute([':id' => $po['supplier_id']]);
        $po['supplier'] = $s->fetch();
        $s = $pdo->prepare(
            'SELECT d.*, i.kode, i.nama, sa.kode AS satuan, ROUND(d.qty * d.harga, 2) AS subtotal
             FROM purchase_order_detail d JOIN item i ON i.id = d.item_id JOIN satuan sa ON sa.id = i.satuan_id
             WHERE d.po_id = :id ORDER BY d.id'
        );
        $s->execute([':id' => $po['id']]);
        $po['detail'] = $s->fetchAll();
        $po['total'] = array_sum(array_column($po['detail'], 'subtotal'));
        response(true, '', $po);
    }

    if ($method === 'GET') {
        [$page, $limit, $offset] = paging();
        $where = [];
        $params = [];
        if (in_array($_GET['status'] ?? '', ['draft', 'open', 'diterima', 'batal'], true)) {
            $where[] = 'p.status = :st';
            $params[':st'] = $_GET['status'];
        }
        $search = trim($_GET['search'] ?? '');
        if ($search !== '') {
            $where[] = '(p.no_po LIKE :s0 OR sp.nama LIKE :s1)';
            $params[':s0'] = $params[':s1'] = "%$search%";
        }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $c = $pdo->prepare("SELECT COUNT(*) FROM purchase_order p JOIN supplier sp ON sp.id = p.supplier_id $w");
        $c->execute($params);
        $total = (int) $c->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT p.*, sp.nama AS supplier_nama, k.kode AS kelas_kode,
                    (SELECT COUNT(*) FROM purchase_order_detail d WHERE d.po_id = p.id) AS jumlah_item,
                    (SELECT COALESCE(SUM(d.qty * d.harga), 0) FROM purchase_order_detail d WHERE d.po_id = p.id) AS total
             FROM purchase_order p JOIN supplier sp ON sp.id = p.supplier_id LEFT JOIN kelas k ON k.id = p.kelas_id
             $w ORDER BY p.id DESC LIMIT :l OFFSET :o"
        );
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
        $stmt->execute();
        response(true, '', $stmt->fetchAll(), 200, ['meta' => meta($page, $limit, $total)]);
    }

    if ($method === 'POST') {
        $id = id_param();
        $pdo->beginTransaction();
        $po = po_ambil($pdo, $id, true);

        if ($action === 'terbitkan') {
            if ($po['status'] !== 'draft') fail('Hanya PO berstatus draft yang dapat diterbitkan.', 409);
            $pdo->prepare("UPDATE purchase_order SET status = 'open' WHERE id = :id")->execute([':id' => $id]);
            $pdo->commit();
            response(true, "PO {$po['no_po']} diterbitkan.");
        }

        if ($action === 'terima') {
            if ($po['status'] !== 'open') fail('Hanya PO berstatus open yang dapat diterima barangnya.', 409);
            $s = $pdo->prepare('SELECT id, item_id, qty, qty_diterima FROM purchase_order_detail WHERE po_id = :id');
            $s->execute([':id' => $id]);
            foreach ($s->fetchAll() as $d) {
                $sisa = round((float) $d['qty'] - (float) $d['qty_diterima'], 2);
                if ($sisa > 0) {
                    stok_ubah($pdo, (int) $d['item_id'], $sisa, 'masuk', 'PO', $id, "Penerimaan {$po['no_po']}");
                    $pdo->prepare('UPDATE purchase_order_detail SET qty_diterima = qty WHERE id = :id')->execute([':id' => $d['id']]);
                }
            }
            $pdo->prepare("UPDATE purchase_order SET status = 'diterima' WHERE id = :id")->execute([':id' => $id]);
            $pdo->commit();
            response(true, "Barang PO {$po['no_po']} diterima, stok bertambah.");
        }

        if ($action === 'batal') {
            if (!in_array($po['status'], ['draft', 'open'], true)) fail('PO ini tidak dapat dibatalkan.', 409);
            $pdo->prepare("UPDATE purchase_order SET status = 'batal' WHERE id = :id")->execute([':id' => $id]);
            $pdo->prepare("UPDATE mrp_planned_order SET status = 'planned', po_id = NULL WHERE po_id = :id")->execute([':id' => $id]);
            $pdo->commit();
            response(true, "PO {$po['no_po']} dibatalkan.");
        }
        fail('Aksi tidak dikenali.', 400);
    }

    if ($method === 'DELETE') {
        $id = id_param();
        $po = po_ambil($pdo, $id);
        if (!in_array($po['status'], ['draft', 'batal'], true)) fail('Hanya PO draft atau batal yang dapat dihapus.', 409);
        $pdo->prepare('DELETE FROM purchase_order WHERE id = :id')->execute([':id' => $id]);
        response(true, 'Purchase order dihapus.');
    }

    fail('Method tidak didukung.', 405);
});
