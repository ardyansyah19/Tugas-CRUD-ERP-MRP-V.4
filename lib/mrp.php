<?php
/**
 * Mesin MRP (gross-to-net, time-phased, bucket mingguan).
 *
 * Alur per item, diurutkan menurut low-level code (level 0 = barang jadi):
 *   Gross Requirement  = kebutuhan independen + kebutuhan dependen (dari planned release induk
 *                        dan alokasi komponen work order yang sudah berjalan)
 *   Scheduled Receipt  = PO (draft/open) yang belum diterima + WO (planned/released)
 *   Proyeksi On Hand   = OH sebelumnya + SR + Planned Receipt - Gross
 *   Net Requirement    = jika (OH sebelumnya + SR - Gross) < Safety Stock -> selisihnya
 *   Planned Receipt    = Net Req yang dibulatkan menurut lot sizing (L4L / FOQ / POQ)
 *   Planned Release    = Planned Receipt digeser mundur sebesar lead time
 * Planned release item induk otomatis menjadi gross requirement komponennya.
 */

/**
 * @return array{items: array<int,array>, orders: array<int,array>}
 */
function mrp_hitung(PDO $pdo, string $mulai, int $horizon): array
{
    $awal = new DateTimeImmutable($mulai);

    $mingguKe = function (string $tgl) use ($awal, $horizon): ?int {
        $hari = (int) $awal->diff(new DateTimeImmutable($tgl))->format('%r%a');
        $w = $hari < 0 ? 1 : intdiv($hari, 7) + 1;   // yang sudah lewat masuk minggu 1
        return $w > $horizon ? null : $w;
    };
    $tglMinggu = fn(int $w): string => $awal->modify('+' . (7 * ($w - 1)) . ' days')->format('Y-m-d');

    // ---- Master item & BOM ----
    $items = [];
    foreach ($pdo->query('SELECT * FROM item WHERE aktif = 1 ORDER BY id') as $r) {
        $items[(int) $r['id']] = $r;
    }
    if (!$items) {
        throw new RuntimeException('Belum ada item aktif.');
    }

    $anak = []; // parent_id => [[child_id, faktor], ...]
    foreach ($pdo->query('SELECT parent_item_id p, child_item_id c, qty_per, scrap_persen FROM bom') as $b) {
        $p = (int) $b['p'];
        $c = (int) $b['c'];
        if (!isset($items[$p], $items[$c])) {
            continue;
        }
        $anak[$p][] = [$c, (float) $b['qty_per'] * (1 + (float) $b['scrap_persen'] / 100)];
    }

    // ---- Low-level code (jalur terpanjang dari barang jadi) ----
    $level = array_fill_keys(array_keys($items), 0);
    $n = count($items);
    for ($iter = 0; ; $iter++) {
        $berubah = false;
        foreach ($anak as $p => $daftar) {
            foreach ($daftar as [$c]) {
                if ($level[$c] < $level[$p] + 1) {
                    $level[$c] = $level[$p] + 1;
                    $berubah = true;
                }
            }
        }
        if (!$berubah) {
            break;
        }
        if ($iter > $n) {
            throw new RuntimeException('BOM mengandung siklus (item menjadi komponen dirinya sendiri).');
        }
    }

    // ---- Kebutuhan independen ----
    $ind = $dep = $sr = [];
    foreach ($pdo->query('SELECT item_id, tanggal_dibutuhkan, qty FROM kebutuhan_independen') as $r) {
        $id = (int) $r['item_id'];
        $w = $mingguKe($r['tanggal_dibutuhkan']);
        if ($w !== null && isset($items[$id])) {
            $ind[$id][$w] = ($ind[$id][$w] ?? 0) + (float) $r['qty'];
        }
    }

    // ---- Scheduled receipts: PO belum diterima & WO berjalan ----
    $po = $pdo->query(
        "SELECT d.item_id, d.tanggal_terima, (d.qty - d.qty_diterima) AS sisa
         FROM purchase_order_detail d JOIN purchase_order p ON p.id = d.po_id
         WHERE p.status IN ('draft','open') AND d.qty > d.qty_diterima"
    );
    foreach ($po as $r) {
        $id = (int) $r['item_id'];
        $w = $mingguKe($r['tanggal_terima']);
        if ($w !== null && isset($items[$id])) {
            $sr[$id][$w] = ($sr[$id][$w] ?? 0) + (float) $r['sisa'];
        }
    }
    $wo = $pdo->query("SELECT item_id, qty, tanggal_mulai, tanggal_selesai FROM work_order WHERE status IN ('planned','released')");
    foreach ($wo as $r) {
        $id = (int) $r['item_id'];
        if (!isset($items[$id])) {
            continue;
        }
        $w = $mingguKe($r['tanggal_selesai']);
        if ($w !== null) {
            $sr[$id][$w] = ($sr[$id][$w] ?? 0) + (float) $r['qty'];
        }
        // Komponen WO yang berjalan sudah teralokasi -> jadi kebutuhan dependen pada minggu mulai.
        $wm = $mingguKe($r['tanggal_mulai']);
        if ($wm !== null) {
            foreach ($anak[$id] ?? [] as [$c, $f]) {
                $dep[$c][$wm] = ($dep[$c][$wm] ?? 0) + (float) $r['qty'] * $f;
            }
        }
    }

    // ---- Proses item menurut level ----
    $urutan = array_keys($items);
    usort($urutan, fn($a, $b) => [$level[$a], $a] <=> [$level[$b], $b]);

    $hasil = [];
    $orders = [];
    foreach ($urutan as $id) {
        $it = $items[$id];
        $lt = (int) $it['lead_time_minggu'];
        $ss = (float) $it['stok_pengaman'];
        $oh = (float) $it['stok'];
        $jenis = $it['pengadaan'] === 'beli' ? 'PO' : 'WO';

        $gross = $srr = $net = $prc = $prl = $poh = array_fill(1, $horizon, 0.0);
        for ($w = 1; $w <= $horizon; $w++) {
            $gross[$w] = round(($ind[$id][$w] ?? 0) + ($dep[$id][$w] ?? 0), 4);
            $srr[$w] = round($sr[$id][$w] ?? 0, 4);
        }

        for ($t = 1; $t <= $horizon; $t++) {
            $tersedia = round($oh + $srr[$t] - $gross[$t], 4);
            $netReq = $tersedia < $ss ? round($ss - $tersedia, 4) : 0.0;
            $q = 0.0;

            if ($netReq > 0) {
                switch ($it['lot_sizing']) {
                    case 'FOQ':
                        $lot = (float) $it['ukuran_lot'];
                        $q = $lot > 0 ? ceil($netReq / $lot - 1e-9) * $lot : $netReq;
                        break;
                    case 'POQ':
                        $periode = max(1, (int) $it['periode_poq']);
                        $q = $netReq;
                        for ($k = $t + 1; $k <= min($horizon, $t + $periode - 1); $k++) {
                            $q += max(0.0, $gross[$k] - $srr[$k]);
                        }
                        break;
                    default: // L4L
                        $q = $netReq;
                }
                $q = round($q, 4);
            }

            $net[$t] = $netReq;
            $prc[$t] = $q;
            $oh = round($tersedia + $q, 4);
            $poh[$t] = $oh;

            if ($q > 0) {
                $rw = $t - $lt;
                $terlambat = 0;
                if ($rw < 1) {
                    $rw = 1;
                    $terlambat = 1;
                }
                $prl[$rw] = round($prl[$rw] + $q, 4);
                // Item produksi meledak ke komponen; item beli tidak punya komponen.
                if ($it['pengadaan'] === 'produksi') {
                    foreach ($anak[$id] ?? [] as [$c, $f]) {
                        $dep[$c][$rw] = ($dep[$c][$rw] ?? 0) + $q * $f;
                    }
                }
                $orders[] = [
                    'item_id' => $id, 'jenis' => $jenis, 'qty' => $q,
                    'minggu_release' => $rw, 'tanggal_release' => $tglMinggu($rw),
                    'tanggal_butuh' => $tglMinggu($t), 'terlambat' => $terlambat,
                ];
            }
        }

        $baris = [];
        for ($w = 1; $w <= $horizon; $w++) {
            $baris[$w] = [
                'minggu' => $w, 'tanggal_awal' => $tglMinggu($w),
                'gross' => $gross[$w], 'scheduled_receipt' => $srr[$w], 'proj_on_hand' => $poh[$w],
                'net_req' => $net[$w], 'planned_receipt' => $prc[$w], 'planned_release' => $prl[$w],
            ];
        }
        $hasil[$id] = ['item' => $it, 'level' => $level[$id], 'minggu' => $baris];
    }

    return ['items' => $hasil, 'orders' => $orders];
}
