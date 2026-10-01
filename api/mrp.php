<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../lib/mrp.php';

/** Ambil detail 1 run: header, item (dengan grid mingguan) dan planned order. */
function mrp_detail(PDO $pdo, int $runId): array
{
    $s = $pdo->prepare(
        'SELECT r.*, k.kode AS kelas_kode, m.nama AS pic_nama, m.nbi AS pic_nbi
         FROM mrp_run r LEFT JOIN kelas k ON k.id = r.kelas_id LEFT JOIN mahasiswa m ON m.id = r.pic_mahasiswa_id
         WHERE r.id = :id'
    );
    $s->execute([':id' => $runId]);
    $run = $s->fetch();
    if (!$run) fail('MRP run tidak ditemukan.', 404);

    $s = $pdo->prepare(
        'SELECT mi.*, i.kode, i.nama, i.tipe, i.pengadaan, sa.kode AS satuan
         FROM mrp_item mi JOIN item i ON i.id = mi.item_id JOIN satuan sa ON sa.id = i.satuan_id
         WHERE mi.run_id = :r ORDER BY mi.level_bom, i.kode'
    );
    $s->execute([':r' => $runId]);
    $items = $s->fetchAll();

    $s = $pdo->prepare('SELECT * FROM mrp_hasil WHERE run_id = :r ORDER BY item_id, minggu');
    $s->execute([':r' => $runId]);
    $grid = [];
    foreach ($s->fetchAll() as $h) {
        $grid[(int) $h['item_id']][] = $h;
    }
    foreach ($items as &$it) {
        $it['minggu'] = $grid[(int) $it['item_id']] ?? [];
    }
    unset($it);

    $s = $pdo->prepare(
        'SELECT po.*, i.kode, i.nama, i.harga_standar, sa.kode AS satuan, sp.nama AS supplier_nama,
                ROUND(po.qty * i.harga_standar, 2) AS estimasi_nilai,
                p.no_po, w.no_wo
         FROM mrp_planned_order po
         JOIN item i ON i.id = po.item_id JOIN satuan sa ON sa.id = i.satuan_id
         LEFT JOIN supplier sp ON sp.id = i.supplier_id
         LEFT JOIN purchase_order p ON p.id = po.po_id
         LEFT JOIN work_order w ON w.id = po.wo_id
         WHERE po.run_id = :r ORDER BY po.tanggal_release, po.jenis DESC, i.kode'
    );
    $s->execute([':r' => $runId]);
    return ['run' => $run, 'items' => $items, 'orders' => $s->fetchAll()];
}

run_api($pdo, function () use ($pdo) {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    // ---- Daftar run ----
    if ($method === 'GET' && !isset($_GET['id'])) {
        $rows = $pdo->query(
            'SELECT r.id, r.kode, r.tanggal_mulai, r.horizon_minggu, r.catatan, r.total_planned_order, r.created_at,
                    k.kode AS kelas_kode, m.nama AS pic_nama,
                    (SELECT COUNT(*) FROM mrp_planned_order x WHERE x.run_id = r.id AND x.status = \'planned\') AS belum_dikonversi
             FROM mrp_run r LEFT JOIN kelas k ON k.id = r.kelas_id LEFT JOIN mahasiswa m ON m.id = r.pic_mahasiswa_id
             ORDER BY r.id DESC LIMIT 100'
        )->fetchAll();
        response(true, '', $rows);
    }

    // ---- Detail run ----
    if ($method === 'GET') {
        response(true, '', mrp_detail($pdo, id_param()));
    }

    // ---- Jalankan MRP ----
    if ($method === 'POST' && $action === 'run') {
        $in = json_body();
        $mulai = senin_dari(!empty($in['tanggal_mulai']) ? tanggal_valid($in['tanggal_mulai'], 'Tanggal mulai') : date('Y-m-d'));
        $horizon = (int) angka($in['horizon_minggu'] ?? 8, 'Horizon', 4, 26);
        [$kelasId, $picId] = validasi_kelas_pic($pdo, $in['kelas_id'] ?? null, $in['pic_mahasiswa_id'] ?? null);
        $catatan = mb_substr(trim((string) ($in['catatan'] ?? '')), 0, 255) ?: null;

        $hasil = mrp_hitung($pdo, $mulai, $horizon);   // perhitungan di memori (baca-saja)

        $pdo->beginTransaction();
        $kode = nomor_berikut($pdo, 'MRP', 'mrp_run', 'kode');
        $pdo->prepare(
            'INSERT INTO mrp_run (kode, tanggal_mulai, horizon_minggu, kelas_id, pic_mahasiswa_id, catatan, total_planned_order)
             VALUES (:k, :t, :h, :kl, :p, :c, :n)'
        )->execute([':k' => $kode, ':t' => $mulai, ':h' => $horizon, ':kl' => $kelasId, ':p' => $picId,
                    ':c' => $catatan, ':n' => count($hasil['orders'])]);
        $runId = (int) $pdo->lastInsertId();

        $insItem = $pdo->prepare(
            'INSERT INTO mrp_item (run_id, item_id, level_bom, stok_awal, stok_pengaman, lead_time_minggu, lot_sizing, ukuran_lot, periode_poq)
             VALUES (:r, :i, :l, :so, :ss, :lt, :ls, :ul, :pq)'
        );
        $insHasil = $pdo->prepare(
            'INSERT INTO mrp_hasil (run_id, item_id, minggu, tanggal_awal, gross, scheduled_receipt, proj_on_hand, net_req, planned_receipt, planned_release)
             VALUES (:r, :i, :w, :t, :g, :sr, :oh, :n, :pr, :rl)'
        );
        foreach ($hasil['items'] as $id => $d) {
            $it = $d['item'];
            $insItem->execute([':r' => $runId, ':i' => $id, ':l' => $d['level'], ':so' => $it['stok'], ':ss' => $it['stok_pengaman'],
                               ':lt' => $it['lead_time_minggu'], ':ls' => $it['lot_sizing'], ':ul' => $it['ukuran_lot'], ':pq' => $it['periode_poq']]);
            foreach ($d['minggu'] as $b) {
                $insHasil->execute([':r' => $runId, ':i' => $id, ':w' => $b['minggu'], ':t' => $b['tanggal_awal'], ':g' => $b['gross'],
                                    ':sr' => $b['scheduled_receipt'], ':oh' => $b['proj_on_hand'], ':n' => $b['net_req'],
                                    ':pr' => $b['planned_receipt'], ':rl' => $b['planned_release']]);
            }
        }
        $insOrder = $pdo->prepare(
            'INSERT INTO mrp_planned_order (run_id, item_id, jenis, qty, minggu_release, tanggal_release, tanggal_butuh, terlambat)
             VALUES (:r, :i, :j, :q, :mr, :tr, :tb, :tl)'
        );
        foreach ($hasil['orders'] as $o) {
            $insOrder->execute([':r' => $runId, ':i' => $o['item_id'], ':j' => $o['jenis'], ':q' => $o['qty'], ':mr' => $o['minggu_release'],
                                ':tr' => $o['tanggal_release'], ':tb' => $o['tanggal_butuh'], ':tl' => $o['terlambat']]);
        }
        $pdo->commit();
        response(true, "MRP $kode selesai: " . count($hasil['orders']) . ' rencana order dihasilkan.', ['id' => $runId, 'kode' => $kode], 201);
    }

    // ---- Konversi planned order -> PO (draft) / WO (planned) ----
    if ($method === 'POST' && $action === 'convert') {
        $in = json_body();
        $runId = (int) ($in['run_id'] ?? 0);
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($in['order_ids'] ?? [])))));
        if (!$ids) fail('Pilih minimal satu rencana order.');

        $pdo->beginTransaction();
        $rs = $pdo->prepare('SELECT * FROM mrp_run WHERE id = :id FOR UPDATE');
        $rs->execute([':id' => $runId]);
        $run = $rs->fetch();
        if (!$run) fail('MRP run tidak ditemukan.', 404);

        $ph = implode(',', array_fill(0, count($ids), '?'));
        $q = $pdo->prepare(
            "SELECT po.*, i.kode, i.nama, i.supplier_id, i.harga_standar
             FROM mrp_planned_order po JOIN item i ON i.id = po.item_id
             WHERE po.run_id = ? AND po.status = 'planned' AND po.id IN ($ph) FOR UPDATE"
        );
        $q->execute(array_merge([$runId], $ids));
        $orders = $q->fetchAll();
        if (!$orders) fail('Tidak ada rencana order berstatus "planned" yang dipilih.');

        $perSupplier = [];
        $wos = [];
        $tanpaSupplier = [];
        foreach ($orders as $o) {
            if ($o['jenis'] === 'PO') {
                if (!$o['supplier_id']) {
                    $tanpaSupplier[$o['kode']] = true;
                    continue;
                }
                $perSupplier[(int) $o['supplier_id']][] = $o;
            } else {
                $wos[] = $o;
            }
        }
        if ($tanpaSupplier) {
            fail('Item berikut belum punya supplier: ' . implode(', ', array_keys($tanpaSupplier)) . '. Lengkapi dulu di menu Item.');
        }

        $upd = $pdo->prepare("UPDATE mrp_planned_order SET status = 'dikonversi', po_id = :po, wo_id = :wo WHERE id = :id");
        $jmlPo = 0;
        foreach ($perSupplier as $supId => $baris) {
            $noPo = nomor_berikut($pdo, 'PO', 'purchase_order', 'no_po');
            $pdo->prepare(
                "INSERT INTO purchase_order (no_po, supplier_id, tanggal_po, status, catatan, kelas_id) VALUES (:n, :s, CURDATE(), 'draft', :c, :k)"
            )->execute([':n' => $noPo, ':s' => $supId, ':c' => 'Dari ' . $run['kode'], ':k' => $run['kelas_id']]);
            $poId = (int) $pdo->lastInsertId();
            $jmlPo++;
            foreach ($baris as $o) {
                $pdo->prepare(
                    'INSERT INTO purchase_order_detail (po_id, item_id, qty, harga, tanggal_terima) VALUES (:p, :i, :q, :h, :t)'
                )->execute([':p' => $poId, ':i' => $o['item_id'], ':q' => $o['qty'], ':h' => $o['harga_standar'], ':t' => $o['tanggal_butuh']]);
                $upd->execute([':po' => $poId, ':wo' => null, ':id' => $o['id']]);
            }
        }
        $jmlWo = 0;
        foreach ($wos as $o) {
            $noWo = nomor_berikut($pdo, 'WO', 'work_order', 'no_wo');
            $pdo->prepare(
                "INSERT INTO work_order (no_wo, item_id, qty, tanggal_mulai, tanggal_selesai, status, kelas_id, pic_mahasiswa_id, catatan)
                 VALUES (:n, :i, :q, :m, :s, 'planned', :k, :pic, :c)"
            )->execute([':n' => $noWo, ':i' => $o['item_id'], ':q' => $o['qty'], ':m' => $o['tanggal_release'], ':s' => $o['tanggal_butuh'],
                        ':k' => $run['kelas_id'], ':pic' => $run['pic_mahasiswa_id'], ':c' => 'Dari ' . $run['kode']]);
            $upd->execute([':po' => null, ':wo' => (int) $pdo->lastInsertId(), ':id' => $o['id']]);
            $jmlWo++;
        }
        $pdo->commit();
        response(true, "Dikonversi: $jmlPo PO (draft) dan $jmlWo work order (planned).", ['po' => $jmlPo, 'wo' => $jmlWo]);
    }

    if ($method === 'DELETE') {
        $st = $pdo->prepare('DELETE FROM mrp_run WHERE id = :id');
        $st->execute([':id' => id_param()]);
        if (!$st->rowCount()) fail('MRP run tidak ditemukan.', 404);
        response(true, 'Hasil MRP dihapus (PO/WO yang sudah dibuat tetap ada).');
    }

    fail('Aksi tidak dikenali.', 400);
});
