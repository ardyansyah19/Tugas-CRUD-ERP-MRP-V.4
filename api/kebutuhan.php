<?php
require_once __DIR__ . '/_crud.php';
crud_run($pdo, [
    'table' => 'kebutuhan_independen', 'label' => 'Kebutuhan', 'from' => 'kebutuhan_independen x JOIN item i ON i.id = x.item_id JOIN satuan s ON s.id = i.satuan_id',
    'select' => 'x.*, i.kode AS item_kode, i.nama AS item_nama, s.kode AS satuan_kode',
    'fields' => [
        'item_id' => ['type' => 'fk', 'fk' => 'item', 'required' => true, 'label' => 'Item'],
        'tanggal_dibutuhkan' => ['type' => 'date', 'required' => true, 'label' => 'Tanggal dibutuhkan'],
        'qty' => ['type' => 'decimal', 'required' => true, 'min' => 0.01, 'label' => 'Jumlah'],
        'jenis' => ['type' => 'enum', 'enum' => ['pesanan', 'forecast'], 'default' => 'pesanan', 'label' => 'Jenis'],
        'no_referensi' => ['type' => 'string', 'max' => 50, 'label' => 'No. referensi'],
        'keterangan' => ['type' => 'string', 'max' => 255, 'label' => 'Keterangan'],
    ],
    'search' => ['i.kode', 'i.nama', 'x.no_referensi', 'x.keterangan'],
    'sortable' => ['tanggal_dibutuhkan' => 'x.tanggal_dibutuhkan', 'item' => 'i.kode', 'qty' => 'x.qty', 'jenis' => 'x.jenis'],
    'default_sort' => 'tanggal_dibutuhkan',
    'filters' => ['jenis' => 'x.jenis', 'item_id' => 'x.item_id'],
]);
