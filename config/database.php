<?php
require_once __DIR__ . '/config.php';

/**
 * Konfigurasi database.
 * Nilai bisa dioverride lewat environment variable (DB_HOST, DB_NAME, dst),
 * sehingga kredensial tidak perlu ditulis langsung di source code saat deployment.
 * Untuk pemakaian lokal (XAMPP/Laragon), nilai default di bawah ini sudah cukup.
 */
$host    = getenv('DB_HOST') ?: 'localhost';
$db      = getenv('DB_NAME') ?: 'crud_mahasiswa';
$user    = getenv('DB_USER') ?: 'root';
$pass    = getenv('DB_PASS') ?: '';
$port    = getenv('DB_PORT') ?: '3306';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'message' => 'Koneksi database gagal. Periksa config/database.php dan pastikan MySQL berjalan.',
    ]);
    exit;
}
