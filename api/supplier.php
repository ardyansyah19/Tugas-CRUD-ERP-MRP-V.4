<?php
require_once __DIR__ . '/_crud.php';
crud_run($pdo, [
    'table' => 'supplier', 'label' => 'Supplier', 'from' => 'supplier x',
    'fields' => [
        'kode'    => ['type' => 'string', 'required' => true, 'max' => 20, 'label' => 'Kode'],
        'nama'    => ['type' => 'string', 'required' => true, 'max' => 100, 'label' => 'Nama'],
        'kontak'  => ['type' => 'string', 'max' => 100, 'label' => 'Kontak'],
        'telepon' => ['type' => 'string', 'max' => 20, 'label' => 'Telepon'],
        'email'   => ['type' => 'string', 'max' => 100, 'email' => true, 'label' => 'Email'],
        'alamat'  => ['type' => 'string', 'max' => 255, 'label' => 'Alamat'],
        'aktif'   => ['type' => 'bool', 'default' => 1, 'label' => 'Aktif'],
    ],
    'search' => ['x.kode', 'x.nama', 'x.kontak'],
    'sortable' => ['kode' => 'x.kode', 'nama' => 'x.nama', 'aktif' => 'x.aktif'], 'default_sort' => 'kode',
    'filters' => ['aktif' => 'x.aktif'],
    'options' => "SELECT id AS value, CONCAT(kode, ' - ', nama) AS label FROM supplier WHERE aktif = 1 ORDER BY kode",
]);
