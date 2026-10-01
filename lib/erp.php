<?php
/**
 * Fungsi bantu bersama untuk modul akademik & ERP.
 */

/** Kelas (id) berdasarkan rentang NBI di tabel `kelas`. NULL bila di luar rentang. */
function kelas_id_dari_nbi(PDO $pdo, string $nbi): ?int
{
    if (!ctype_digit($nbi)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id FROM kelas WHERE :n BETWEEN nbi_awal AND nbi_akhir LIMIT 1');
    $stmt->execute([':n' => $nbi]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

/** Tanggal Senin pada minggu dari tanggal tertentu (format Y-m-d). */
function senin_dari(string $tanggal): string
{
    $d = new DateTimeImmutable($tanggal);
    $selisih = (int) $d->format('N') - 1; // Senin = 1
    return $d->modify("-$selisih days")->format('Y-m-d');
}

/**
 * Nomor dokumen berurutan per bulan, mis. PO-202609-0001.
 * $table/$col hanya boleh berupa konstanta dari kode (bukan input user).
 */
function nomor_berikut(PDO $pdo, string $prefix, string $table, string $col): string
{
    $pola = $prefix . '-' . date('Ym') . '-';
    $stmt = $pdo->prepare(
        "SELECT MAX(CAST(SUBSTRING_INDEX($col, '-', -1) AS UNSIGNED)) FROM $table WHERE $col LIKE :p"
    );
    $stmt->execute([':p' => $pola . '%']);
    $n = (int) $stmt->fetchColumn() + 1;
    return $pola . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
}

/**
 * Ubah saldo stok item + catat ke stok_mutasi. Harus dipanggil di dalam transaksi.
 * $delta bertanda (+ masuk, - keluar). Melempar RuntimeException bila stok jadi negatif.
 * Mengembalikan saldo baru.
 */
function stok_ubah(PDO $pdo, int $itemId, float $delta, string $tipe, ?string $refTipe, ?int $refId, ?string $ket): float
{
    $stmt = $pdo->prepare('SELECT kode, nama, stok FROM item WHERE id = :id FOR UPDATE');
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch();
    if (!$item) {
        throw new RuntimeException('Item tidak ditemukan.');
    }
    $baru = round((float) $item['stok'] + $delta, 2);
    if ($baru < 0) {
        throw new RuntimeException(sprintf(
            'Stok %s (%s) tidak mencukupi: tersedia %s, dibutuhkan %s.',
            $item['nama'], $item['kode'], rtrim(rtrim(number_format((float) $item['stok'], 2, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format(abs($delta), 2, '.', ''), '0'), '.')
        ));
    }
    $pdo->prepare('UPDATE item SET stok = :s WHERE id = :id')->execute([':s' => $baru, ':id' => $itemId]);
    $pdo->prepare(
        'INSERT INTO stok_mutasi (item_id, tipe, qty, stok_setelah, ref_tipe, ref_id, keterangan)
         VALUES (:i, :t, :q, :s, :rt, :ri, :k)'
    )->execute([
        ':i' => $itemId, ':t' => $tipe, ':q' => $delta, ':s' => $baru,
        ':rt' => $refTipe, ':ri' => $refId, ':k' => $ket,
    ]);
    return $baru;
}

/** Sinkronkan kelas_id semua mahasiswa dengan rentang NBI kelas. Mengembalikan jumlah baris berubah. */
function sinkron_kelas_mahasiswa(PDO $pdo): int
{
    $a = $pdo->exec(
        'UPDATE mahasiswa m JOIN kelas k ON CAST(m.nbi AS UNSIGNED) BETWEEN k.nbi_awal AND k.nbi_akhir
         SET m.kelas_id = k.id WHERE m.kelas_id IS NULL OR m.kelas_id <> k.id'
    );
    $b = $pdo->exec(
        'UPDATE mahasiswa m SET m.kelas_id = NULL
         WHERE m.kelas_id IS NOT NULL
           AND NOT EXISTS (SELECT 1 FROM kelas k WHERE CAST(m.nbi AS UNSIGNED) BETWEEN k.nbi_awal AND k.nbi_akhir)'
    );
    return (int) $a + (int) $b;
}

/** Faktor kebutuhan komponen (qty_per x (1 + scrap%)) untuk 1 unit induk. [child_id => faktor] */
function bom_komponen(PDO $pdo, int $parentId): array
{
    $stmt = $pdo->prepare('SELECT child_item_id, qty_per, scrap_persen FROM bom WHERE parent_item_id = :p');
    $stmt->execute([':p' => $parentId]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[(int) $r['child_item_id']] = (float) $r['qty_per'] * (1 + (float) $r['scrap_persen'] / 100);
    }
    return $out;
}
