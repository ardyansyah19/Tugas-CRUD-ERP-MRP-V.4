<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';

function response($success, $message = '', $data = [], $code = 200)
{
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? ($input['action'] ?? '');

if ($method === 'GET' && $action === 'check') {
    response(true, '', [
        'logged_in' => is_logged_in(),
        'username'  => $_SESSION['username'] ?? null,
        'csrf_token' => csrf_token(),
    ]);
}

if ($method === 'POST' && $action === 'login') {
    // Batasi percobaan login untuk mengurangi risiko brute force sederhana.
    $attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'start' => time()];
    if (time() - $attempts['start'] > 300) {
        $attempts = ['count' => 0, 'start' => time()];
    }
    if ($attempts['count'] >= 10) {
        response(false, 'Terlalu banyak percobaan login. Coba lagi beberapa menit lagi.', [], 429);
    }

    $username = trim($input['username'] ?? '');
    $password = (string) ($input['password'] ?? '');

    if ($username === '' || $password === '') {
        response(false, 'Username dan password wajib diisi.', [], 422);
    }

    $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    $attempts['count']++;
    $_SESSION['login_attempts'] = $attempts;

    if (!$user || !password_verify($password, $user['password'])) {
        response(false, 'Username atau password salah.', [], 401);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    unset($_SESSION['login_attempts']);

    response(true, 'Login berhasil.', [
        'username' => $user['username'],
        'csrf_token' => csrf_token(),
    ]);
}

if ($method === 'POST' && $action === 'logout') {
    $_SESSION = [];
    session_destroy();
    response(true, 'Logout berhasil.');
}

response(false, 'Aksi tidak dikenali.', [], 400);
