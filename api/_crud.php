<?php
/**
 * CRUD generik berbasis konfigurasi (JSON). Dipakai supplier, satuan, item, kebutuhan.
 *
 * cfg:
 *  table, label, from, select, fields[name => [type,required,max,min,enum,fk,nullable,default]],
 *  search[], sortable[alias => expr], default_sort, filters[param => expr],
 *  options[sql], before_save(callable &$data, ?int $id, PDO), after_create(callable int $id, array $input, PDO)
 */
require_once __DIR__ . '/_bootstrap.php';

function crud_bersihkan(PDO $pdo, array $cfg, array $in, bool $create, ?int $id): array
{
    $out = [];
    foreach ($cfg['fields'] as $name => $f) {
        $ada = array_key_exists($name, $in);
        if (!$ada) {
            if ($create) {
                if (array_key_exists('default', $f)) {
                    $out[$name] = $f['default'];
                    continue;
                }
                if (!empty($f['required'])) {
                    fail("Field $name wajib diisi.");
                }
                $out[$name] = null;
            }
            continue;
        }
        $v = $in[$name];
        $label = $f['label'] ?? $name;
        $kosong = $v === null || (is_string($v) && trim($v) === '');

        if ($kosong) {
            if (!empty($f['required'])) {
                fail("$label wajib diisi.");
            }
            $out[$name] = array_key_exists('default', $f) && $f['type'] !== 'string' && empty($f['nullable']) ? $f['default'] : null;
            continue;
        }

        switch ($f['type']) {
            case 'string':
                $v = trim((string) $v);
                if (mb_strlen($v) > ($f['max'] ?? 255)) {
                    fail("$label maksimal " . ($f['max'] ?? 255) . ' karakter.');
                }
                if (!empty($f['email']) && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    fail("Format $label tidak valid.");
                }
                break;
            case 'int':
                if (!is_numeric($v) || (int) $v != $v) {
                    fail("$label harus berupa bilangan bulat.");
                }
                $v = (int) $v;
                if (isset($f['min']) && $v < $f['min']) fail("$label minimal {$f['min']}.");
                if (isset($f['max']) && $v > $f['max']) fail("$label maksimal {$f['max']}.");
                break;
            case 'decimal':
                $v = angka($v, $label, $f['min'] ?? null, $f['max'] ?? null);
                break;
            case 'date':
                $v = tanggal_valid($v, $label);
                break;
            case 'enum':
                if (!in_array($v, $f['enum'], true)) {
                    fail("$label tidak valid.");
                }
                break;
            case 'bool':
                $v = in_array($v, [1, '1', true, 'true', 'on'], true) ? 1 : 0;
                break;
            case 'fk':
                $v = (int) $v;
                $s = $pdo->prepare("SELECT 1 FROM {$f['fk']} WHERE id = :id");
                $s->execute([':id' => $v]);
                if (!$s->fetchColumn()) {
                    fail("$label tidak ditemukan.");
                }
                break;
        }
        $out[$name] = $v;
    }
    return $out;
}

function crud_run(PDO $pdo, array $cfg): void
{
    run_api($pdo, function () use ($pdo, $cfg) {
        $method = $_SERVER['REQUEST_METHOD'];
        $select = $cfg['select'] ?? 'x.*';
        $from = $cfg['from'];

        if ($method === 'GET' && ($_GET['action'] ?? '') === 'options') {
            response(true, '', $pdo->query($cfg['options'])->fetchAll());
        }

        if ($method === 'GET' && isset($_GET['id'])) {
            $s = $pdo->prepare("SELECT $select FROM $from WHERE x.id = :id");
            $s->execute([':id' => id_param()]);
            $row = $s->fetch();
            if (!$row) fail($cfg['label'] . ' tidak ditemukan.', 404);
            response(true, '', $row);
        }

        if ($method === 'GET') {
            [$page, $limit, $offset] = paging();
            $sortable = $cfg['sortable'];
            $sortKey = isset($sortable[$_GET['sort'] ?? '']) ? $_GET['sort'] : $cfg['default_sort'];
            $sortExpr = $sortable[$sortKey];
            $dir = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

            $where = [];
            $params = [];
            $search = trim($_GET['search'] ?? '');
            if ($search !== '' && !empty($cfg['search'])) {
                $parts = [];
                foreach ($cfg['search'] as $i => $col) {
                    $parts[] = "$col LIKE :s$i";
                    $params[":s$i"] = "%$search%";
                }
                $where[] = '(' . implode(' OR ', $parts) . ')';
            }
            foreach ($cfg['filters'] ?? [] as $param => $expr) {
                $val = trim((string) ($_GET[$param] ?? ''));
                if ($val !== '') {
                    $where[] = "$expr = :f_$param";
                    $params[":f_$param"] = $val;
                }
            }
            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $c = $pdo->prepare("SELECT COUNT(*) FROM $from $whereSql");
            $c->execute($params);
            $total = (int) $c->fetchColumn();

            $stmt = $pdo->prepare("SELECT $select FROM $from $whereSql ORDER BY $sortExpr $dir, x.id DESC LIMIT :limit OFFSET :offset");
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            response(true, 'Data berhasil diambil.', $stmt->fetchAll(), 200, ['meta' => meta($page, $limit, $total)]);
        }

        if ($method === 'POST') {
            $in = json_body();
            $data = crud_bersihkan($pdo, $cfg, $in, true, null);
            if (!empty($cfg['before_save'])) ($cfg['before_save'])($data, null, $pdo);

            $pdo->beginTransaction();
            $cols = array_keys($data);
            $sql = "INSERT INTO {$cfg['table']} (" . implode(',', $cols) . ') VALUES (:' . implode(',:', $cols) . ')';
            $pdo->prepare($sql)->execute($data);
            $newId = (int) $pdo->lastInsertId();
            if (!empty($cfg['after_create'])) ($cfg['after_create'])($newId, $in, $pdo);
            $pdo->commit();
            response(true, $cfg['label'] . ' berhasil ditambahkan.', ['id' => $newId], 201);
        }

        if ($method === 'PUT') {
            $id = id_param();
            $s = $pdo->prepare("SELECT 1 FROM {$cfg['table']} WHERE id = :id");
            $s->execute([':id' => $id]);
            if (!$s->fetchColumn()) fail($cfg['label'] . ' tidak ditemukan.', 404);

            $data = crud_bersihkan($pdo, $cfg, json_body(), false, $id);
            if (!empty($cfg['before_save'])) ($cfg['before_save'])($data, $id, $pdo);
            if (!$data) fail('Tidak ada perubahan.');

            $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($data)));
            $data['__id'] = $id;
            $pdo->prepare("UPDATE {$cfg['table']} SET " . str_replace(':__id', ':__id', $set) . ' WHERE id = :__id')->execute($data);
            response(true, $cfg['label'] . ' berhasil diperbarui.');
        }

        if ($method === 'DELETE') {
            $id = id_param();
            $st = $pdo->prepare("DELETE FROM {$cfg['table']} WHERE id = :id");
            $st->execute([':id' => $id]);
            if (!$st->rowCount()) fail($cfg['label'] . ' tidak ditemukan.', 404);
            response(true, $cfg['label'] . ' berhasil dihapus.');
        }

        fail('Method tidak didukung.', 405);
    });
}
