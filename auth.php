<?php
/**
 * auth.php — Shared authentication include.
 * Always at app root. Computes ROOT_PREFIX so redirects work from any depth.
 */
if (!defined('DATA_DIR')) define('DATA_DIR', __DIR__ . '/data');
define('SESSION_TIMEOUT_HOURS', 8);

// Compute relative path back to app root from the currently executing script
$_auth_dir    = dirname(realpath(__FILE__));
$_script_file = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
$_script_dir  = $_script_file ? dirname($_script_file) : $_auth_dir;
$_depth = 0;
$_tmp   = $_script_dir;
while ($_tmp !== $_auth_dir && strlen($_tmp) > strlen($_auth_dir)) {
    $_depth++;
    $_tmp = dirname($_tmp);
    if (dirname($_tmp) === $_tmp) break; // filesystem root guard
}
if (!defined('ROOT_PREFIX')) define('ROOT_PREFIX', str_repeat('../', $_depth));

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true, 'samesite' => 'Strict',
    ]);
    session_start();
}

// Session timeout
if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT_HOURS * 3600) {
        session_unset(); session_destroy();
        $is_api = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
        if ($is_api) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Sessione scaduta']); exit;
        }
        header('Location: ' . ROOT_PREFIX . 'login.php?reason=timeout'); exit;
    }
}
$_SESSION['last_activity'] = time();

/* ── CSRF ──────────────────────────────────────────────────────────────── */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token']))
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}
function validate_csrf(string $t): bool {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $t);
}

/* ── Role helpers ──────────────────────────────────────────────────────── */
function is_logged_in(): bool  { return !empty($_SESSION['user_id']); }
function is_hr(): bool         { return is_logged_in() && ($_SESSION['role'] ?? '') === 'hr'; }
function is_employee(): bool   { return is_logged_in() && ($_SESSION['role'] ?? '') === 'employee'; }
function get_user_id(): ?string     { return $_SESSION['user_id']     ?? null; }
function get_role(): ?string        { return $_SESSION['role']        ?? null; }
function get_employee_id(): ?string { return $_SESSION['employee_id'] ?? null; }

/* ── Page guards (redirect) ────────────────────────────────────────────── */
function require_login(): void {
    if (!is_logged_in()) { header('Location: ' . ROOT_PREFIX . 'login.php'); exit; }
}
function require_hr(): void {
    require_login();
    if (!is_hr()) {
        http_response_code(403);
        include __DIR__ . '/error_403.php'; exit;
    }
}
function require_employee(): void {
    require_login();
    if (!is_employee()) {
        http_response_code(403);
        include __DIR__ . '/error_403.php'; exit;
    }
}

/* ── API guards (JSON) ─────────────────────────────────────────────────── */
function api_require_login(): void {
    if (!is_logged_in()) {
        http_response_code(401); header('Content-Type: application/json');
        echo json_encode(['error' => 'Non autenticato']); exit;
    }
}
function api_require_hr(): void {
    api_require_login();
    if (!is_hr()) {
        http_response_code(403); header('Content-Type: application/json');
        echo json_encode(['error' => 'Accesso non autorizzato']); exit;
    }
}
function api_require_employee(): void {
    api_require_login();
    if (!is_employee()) {
        http_response_code(403); header('Content-Type: application/json');
        echo json_encode(['error' => 'Accesso non autorizzato']); exit;
    }
}
function api_validate_csrf(): void {
    $t = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!validate_csrf($t)) {
        http_response_code(403); header('Content-Type: application/json');
        echo json_encode(['error' => 'Token CSRF non valido']); exit;
    }
}
