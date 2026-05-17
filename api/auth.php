<?php
/**
 * api/auth.php — Login / Logout API. Phase 8: added login rate limiting.
 * Rate limit: 5 failed attempts per username → lock 15 min.
 * Attempts stored in data/config/login_attempts.json.
 */
define('ROOT', __DIR__ . '/..');
define('DATA_DIR', ROOT . '/data');
require_once ROOT . '/auth.php';
require_once __DIR__ . '/json_helper.php';
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'login';

/* ── Rate-limit helpers ────────────────────────────────────────────────── */
const RL_MAX     = 5;        // max failed attempts
const RL_WINDOW  = 900;      // 15 minutes in seconds
const RL_LOCK    = 900;      // lock duration in seconds

function rl_file(): string {
    return DATA_DIR . '/config/login_attempts.json';
}
function rl_load(): array {
    $f = rl_file();
    if (!file_exists($f)) return [];
    $d = json_decode(file_get_contents($f), true);
    return is_array($d) ? $d : [];
}
function rl_save(array $data): void {
    file_put_contents(rl_file(), json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}
function rl_check(string $username): void {
    $data = rl_load();
    $now  = time();
    $rec  = $data[$username] ?? null;
    if (!$rec) return;

    // If locked
    if (!empty($rec['locked_until']) && $rec['locked_until'] > $now) {
        $wait = (int)ceil(($rec['locked_until'] - $now) / 60);
        json_response(['error' => "Account temporaneamente bloccato. Riprova tra {$wait} minuto/i."], 429);
    }

    // Reset if window expired
    if (!empty($rec['first_attempt']) && ($now - $rec['first_attempt']) >= RL_WINDOW) {
        unset($data[$username]);
        rl_save($data);
    }
}
function rl_fail(string $username): void {
    $data = rl_load();
    $now  = time();
    $rec  = $data[$username] ?? ['count' => 0, 'first_attempt' => $now, 'locked_until' => null];

    // Reset window if expired
    if (($now - ($rec['first_attempt'] ?? $now)) >= RL_WINDOW) {
        $rec = ['count' => 0, 'first_attempt' => $now, 'locked_until' => null];
    }

    $rec['count']++;
    if ($rec['count'] >= RL_MAX) {
        $rec['locked_until'] = $now + RL_LOCK;
    }
    $data[$username] = $rec;
    rl_save($data);
}
function rl_clear(string $username): void {
    $data = rl_load();
    unset($data[$username]);
    rl_save($data);
}

/* ── Login ─────────────────────────────────────────────────────────────── */
if ($method === 'POST' && $action === 'login') {
    $input    = json_decode(file_get_contents('php://input'), true) ?? [];
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    if (!$username || !$password) json_response(['error' => 'Username e password obbligatori'], 422);

    // Rate limit check (before DB lookup — avoids timing oracle)
    rl_check($username);

    $creds = read_json(data_path('users', 'credentials.json'));
    $user  = null;
    foreach ($creds as $c) { if ($c['username'] === $username) { $user = $c; break; } }

    if (!$user || !password_verify($password, $user['password_hash'])) {
        rl_fail($username);
        // Generic message — don't reveal which field is wrong
        json_response(['error' => 'Credenziali non valide'], 401);
    }

    // Success — clear rate limit counter
    rl_clear($username);

    session_regenerate_id(true);
    $_SESSION['user_id']       = $user['user_id'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['employee_id']   = $user['employee_id'];
    $_SESSION['last_activity'] = time();

    $dest     = $user['role'] === 'hr' ? 'hr/dashboard.php' : 'employee/dashboard.php';
    $redirect = APP_URL . $dest;
    json_response(['success' => true, 'redirect' => $redirect]);
}

/* ── Logout ─────────────────────────────────────────────────────────────── */
if ($action === 'logout') {
    session_unset(); session_destroy();
    json_response(['success' => true, 'redirect' => APP_URL . 'login.php']);
}

json_response(['error' => 'Azione non valida'], 400);
