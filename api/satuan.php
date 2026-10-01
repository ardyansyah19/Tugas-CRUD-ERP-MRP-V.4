<?php
require_once __DIR__ . '/_crud.php';
crud_run($pdo, [
    'table' => 'satuan', 'label' => 'Satuan', 'from' => 'satuan x',
    'fields' => [
        'kode' => ['type' => 'string', 'required' => true, 'max' => 10, 'label' => 'Kode'],
        'nama' => ['type' => 'string', 'required' => true, 'max' => 50, 'label' => 'Nama'],
    ],
    'search' => ['x.kode', 'x.nama'],
    'sortable' => ['kode' => 'x.kode', 'nama' => 'x.nama'], 'default_sort' => 'kode',
    'options' => "SELECT id AS value, CONCAT(nama, ' (', kode, ')') AS label FROM satuan ORDER BY nama",
]);
