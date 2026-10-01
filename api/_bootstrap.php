<?php
/**
 * Bootstrap bersama untuk endpoint ERP (JSON). Menyediakan:
 * response(), fail(), guard() [login + CSRF + rate limit], json_body(), run_api().
 * File ini diawali "_" agar jelas bukan endpoint publik.
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/erp.php';

class ApiError extends Exception
{
    public int $status;
    public function __construct(string $message, int $status = 422)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

function response($success, $message = '', $data = [], $code = 200, $extra = [])
{
    http_response_code($code);
    echo json_encode(array_merge(['success' => $success, 'message' => $message, 'data' => $data], $extra));
    exit;
}

function fail(string $message, int $status = 422): void
{
    throw new ApiError($message, $status);
}

function json_body(): array
{
    static $body = null;
    if ($body === null) {
        $raw = file_get_contents('php://input');
        $decoded = $raw !== '' ? json_decode($raw, true) : null;
        $body = is_array($decoded) ? $decoded : $_POST;
    }
    return $body;
}

/** Wajib login; untuk method selain GET juga rate limit + CSRF. */
function guard(): void
{
    if (!is_logged_in()) {
        response(false, 'Sesi tidak valid. Silakan login kembali.', [], 401);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        if (!rate_limit_check()) {
            response(false, 'Terlalu banyak permintaan, silakan coba lagi sesaat lagi.', [], 429);
        }
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? (json_body()['csrf_token'] ?? null);
        if (!csrf_verify($token)) {
            response(false, 'Token keamanan (CSRF) tidak valid. Muat ulang halaman lalu coba lagi.', [], 403);
        }
    }
}

function id_param(string $name = 'id'): int
{
    $id = filter_input(INPUT_GET, $name, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        fail('ID tidak valid.', 400);
    }
    return $id;
}

/**
 * Jalankan endpoint dengan penanganan error seragam.
 * Urutan catch penting: PDOException adalah turunan RuntimeException, jadi harus ditangkap lebih dulu.
 */
function run_api(PDO $pdo, callable $fn): void
{
    guard();
    try {
        $fn();
        response(false, 'Permintaan tidak dikenali.', [], 400);
    } catch (ApiError $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        response(false, $e->getMessage(), [], $e->status);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $kode = $e->errorInfo[1] ?? 0;
        if ($kode === 1062) {
            response(false, 'Data duplikat: kode/nilai unik tersebut sudah digunakan.', [], 409);
        }
        if ($kode === 1451) {
            response(false, 'Data masih dipakai oleh data lain (mis. PO, work order, atau BOM) sehingga tidak dapat dihapus.', [], 409);
        }
        if ($kode === 1452) {
            response(false, 'Referensi data tidak valid.', [], 422);
        }
        error_log('[api] ' . $e->getMessage());
        response(false, 'Terjadi kesalahan database.', [], 500);
    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        response(false, $e->getMessage(), [], 409);
    }
}

/** Ambil paging standar dari query string. */
function paging(): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = (int) ($_GET['limit'] ?? 10);
    $limit = in_array($limit, [10, 25, 50, 100], true) ? $limit : 10;
    return [$page, $limit, ($page - 1) * $limit];
}

function meta(int $page, int $limit, int $total): array
{
    return ['page' => $page, 'limit' => $limit, 'total' => $total, 'total_pages' => (int) ceil($total / $limit) ?: 1];
}

/** Validasi angka desimal dari input. */
function angka($v, string $label, ?float $min = null, ?float $max = null): float
{
    if (!is_numeric($v)) {
        fail("$label harus berupa angka.");
    }
    $v = (float) $v;
    if ($min !== null && $v < $min) {
        fail("$label minimal $min.");
    }
    if ($max !== null && $v > $max) {
        fail("$label maksimal $max.");
    }
    return $v;
}

function tanggal_valid($v, string $label): string
{
    $v = trim((string) $v);
    $d = DateTime::createFromFormat('Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) {
        fail("$label harus berformat tanggal YYYY-MM-DD.");
    }
    return $v;
}

/** Pastikan pasangan kelas & mahasiswa konsisten (mahasiswa harus anggota kelas tsb). */
function validasi_kelas_pic(PDO $pdo, $kelasId, $picId): array
{
    $kelasId = ($kelasId === '' || $kelasId === null) ? null : (int) $kelasId;
    $picId = ($picId === '' || $picId === null) ? null : (int) $picId;

    if ($kelasId !== null) {
        $s = $pdo->prepare('SELECT 1 FROM kelas WHERE id = :id');
        $s->execute([':id' => $kelasId]);
        if (!$s->fetchColumn()) {
            fail('Kelas tidak ditemukan.');
        }
    }
    if ($picId !== null) {
        $s = $pdo->prepare('SELECT kelas_id FROM mahasiswa WHERE id = :id');
        $s->execute([':id' => $picId]);
        $row = $s->fetch();
        if (!$row) {
            fail('Mahasiswa PIC tidak ditemukan.');
        }
        if ($kelasId === null) {
            $kelasId = $row['kelas_id'] !== null ? (int) $row['kelas_id'] : null;   // kelas otomatis dari PIC
        } elseif ((int) $row['kelas_id'] !== $kelasId) {
            fail('Mahasiswa PIC bukan anggota kelas yang dipilih.');
        }
    }
    return [$kelasId, $picId];
}
