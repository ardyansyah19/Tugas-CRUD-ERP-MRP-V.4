<?php
/**
 * Konfigurasi umum aplikasi.
 * File ini di-load paling awal (session, timezone, konstanta path upload, dsb).
 */

date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Nama aplikasi (bebas diganti)
define('APP_NAME', 'Sistem Akademik & MRP-ERP');

// Folder & batasan upload foto profil mahasiswa
define('UPLOAD_DIR', __DIR__ . '/../uploads/foto');
define('UPLOAD_URL_PREFIX', 'uploads/foto');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2 MB
define('ALLOWED_MIME', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']);

// Batas sederhana anti-spam (rate limit) untuk endpoint yang mengubah data.
// Maksimal N permintaan tulis (POST/PUT/DELETE) per pengguna dalam jendela waktu tertentu.
define('RATE_LIMIT_MAX_REQUEST', 30);
define('RATE_LIMIT_WINDOW', 60); // detik

/**
 * Menghasilkan / mengambil CSRF token yang tersimpan di session.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Memverifikasi CSRF token yang dikirim client terhadap yang ada di session.
 */
function csrf_verify(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Cek status login. Mengembalikan true jika sudah login sebagai admin.
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Rate limiting sederhana berbasis session per pengguna (bukan pengganti
 * rate limiting di level server/proxy, hanya lapisan tambahan untuk tugas ini).
 */
function rate_limit_check(): bool
{
    $now = time();
    $bucket = $_SESSION['rate_bucket'] ?? ['start' => $now, 'count' => 0];

    if ($now - $bucket['start'] > RATE_LIMIT_WINDOW) {
        $bucket = ['start' => $now, 'count' => 0];
    }

    $bucket['count']++;
    $_SESSION['rate_bucket'] = $bucket;

    return $bucket['count'] <= RATE_LIMIT_MAX_REQUEST;
}
