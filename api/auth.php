<?php
define('ROOT', __DIR__ . '/..');
define('DATA_DIR', ROOT . '/data');
require_once ROOT . '/auth.php';
require_once __DIR__ . '/json_helper.php';
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'login';

if ($method === 'POST' && $action === 'login') {
    $input    = json_decode(file_get_contents('php://input'), true) ?? [];
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    if (!$username || !$password) json_response(['error' => 'Username e password obbligatori'], 422);

    $creds = read_json(data_path('users', 'credentials.json'));
    $user  = null;
    foreach ($creds as $c) { if ($c['username'] === $username) { $user = $c; break; } }
    if (!$user || !password_verify($password, $user['password_hash']))
        json_response(['error' => 'Credenziali non valide'], 401);

    session_regenerate_id(true);
    $_SESSION['user_id']     = $user['user_id'];
    $_SESSION['role']        = $user['role'];
    $_SESSION['employee_id'] = $user['employee_id'];
    $_SESSION['last_activity'] = time();

    // Paths relative to login.php (at app root) — no leading slash
    $redirect = $user['role'] === 'hr' ? 'hr/dashboard.php' : 'employee/dashboard.php';
    json_response(['success' => true, 'redirect' => $redirect]);
}

if ($action === 'logout') {
    session_unset(); session_destroy();
    json_response(['success' => true, 'redirect' => 'login.php']);
}

json_response(['error' => 'Azione non valida'], 400);
