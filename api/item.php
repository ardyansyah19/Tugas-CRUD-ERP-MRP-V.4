<?php
require_once __DIR__ . '/_crud.php';
crud_run($pdo, [
    'table' => 'item', 'label' => 'Item', 'from' => 'item x JOIN satuan s ON s.id = x.satuan_id LEFT JOIN supplier sp ON sp.id = x.supplier_id',
    'select' => 'x.*, s.kode AS satuan_kode, sp.nama AS supplier_nama,
                 (SELECT COUNT(*) FROM bom b WHERE b.parent_item_id = x.id) AS jumlah_komponen',
    'fields' => [
        'kode'      => ['type' => 'string', 'required' => true, 'max' => 30, 'label' => 'Kode'],
        'nama'      => ['type' => 'string', 'required' => true, 'max' => 120, 'label' => 'Nama'],
        'tipe'      => ['type' => 'enum', 'required' => true, 'enum' => ['FG', 'SFG', 'RM'], 'label' => 'Tipe'],
        'satuan_id' => ['type' => 'fk', 'fk' => 'satuan', 'required' => true, 'label' => 'Satuan'],
        'supplier_id' => ['type' => 'fk', 'fk' => 'supplier', 'label' => 'Supplier'],
        'lead_time_minggu' => ['type' => 'int', 'min' => 0, 'max' => 52, 'default' => 1, 'label' => 'Lead time'],
        'lot_sizing' => ['type' => 'enum', 'enum' => ['L4L', 'FOQ', 'POQ'], 'default' => 'L4L', 'label' => 'Lot sizing'],
        'ukuran_lot' => ['type' => 'decimal', 'min' => 0, 'default' => 0, 'label' => 'Ukuran lot'],
        'periode_poq' => ['type' => 'int', 'min' => 1, 'max' => 52, 'default' => 1, 'label' => 'Periode POQ'],
        'stok_pengaman' => ['type' => 'decimal', 'min' => 0, 'default' => 0, 'label' => 'Stok pengaman'],
        'harga_standar' => ['type' => 'decimal', 'min' => 0, 'default' => 0, 'label' => 'Harga standar'],
        'aktif'     => ['type' => 'bool', 'default' => 1, 'label' => 'Aktif'],
    ],
    'search' => ['x.kode', 'x.nama'],
    'sortable' => ['kode' => 'x.kode', 'nama' => 'x.nama', 'tipe' => 'x.tipe', 'stok' => 'x.stok', 'lead_time_minggu' => 'x.lead_time_minggu'],
    'default_sort' => 'kode',
    'filters' => ['tipe' => 'x.tipe', 'aktif' => 'x.aktif'],
    'options' => "SELECT id AS value, CONCAT(kode, ' - ', nama) AS label, tipe FROM item WHERE aktif = 1 ORDER BY kode",
    'before_save' => function (array &$d, ?int $id, PDO $pdo) {
        if (isset($d['tipe'])) {
            // RM dibeli dari supplier; FG/SFG diproduksi lewat work order.
            $d['pengadaan'] = $d['tipe'] === 'RM' ? 'beli' : 'produksi';
            if ($d['tipe'] === 'RM' && $id) {
                $s = $pdo->prepare('SELECT COUNT(*) FROM bom WHERE parent_item_id = :id');
                $s->execute([':id' => $id]);
                if ($s->fetchColumn() > 0) {
                    fail('Item ini punya komponen BOM, tidak bisa dijadikan bahan baku (RM). Hapus komponennya dulu.');
                }
            }
        }
        if (($d['lot_sizing'] ?? null) === 'FOQ' && (float) ($d['ukuran_lot'] ?? 0) <= 0) {
            fail('Lot sizing FOQ membutuhkan ukuran lot lebih dari 0.');
        }
    },
    'after_create' => function (int $id, array $in, PDO $pdo) {
        $awal = isset($in['stok_awal']) && $in['stok_awal'] !== '' ? angka($in['stok_awal'], 'Stok awal', 0) : 0.0;
        if ($awal > 0) {
            stok_ubah($pdo, $id, $awal, 'penyesuaian', 'MANUAL', null, 'Stok awal');
        }
    },
]);
