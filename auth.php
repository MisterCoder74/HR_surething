<?php
/**
 * auth.php — Shared authentication include. Always at app root.
 *
 * Uses __DIR__ as the app root filesystem anchor.
 * APP_URL is computed from __DIR__ vs DOCUMENT_ROOT, so Location headers
 * always point to the correct URL regardless of install subfolder.
 */
define('APP_ROOT', __DIR__);
if (!defined('DATA_DIR')) define('DATA_DIR', APP_ROOT . '/data');
define('SESSION_TIMEOUT_HOURS', 8);

// Compute URL base for Location redirects (e.g. '/' or '/myapp/')
if (!defined('APP_URL')) {
    $_doc = rtrim(realpath($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
    $_app = rtrim(APP_ROOT, '/\\');
    if ($_doc !== '' && str_starts_with($_app, $_doc)) {
        $_rel = substr($_app, strlen($_doc));
        $_rel = '/' . ltrim(str_replace('\\', '/', $_rel), '/');
    } else {
        $_rel = '/'; // fallback: assume app is at doc root
    }
    define('APP_URL', rtrim($_rel, '/') . '/'); // always ends with /
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true, 'samesite' => 'Strict',
    ]);
    session_start();
}

// Session timeout
if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT_HOURS * 3600) {
        session_unset(); session_destroy();
        $is_api = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');
        if ($is_api) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Sessione scaduta']); exit;
        }
        header('Location: ' . APP_URL . 'login.php?reason=timeout'); exit;
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
    return '<input type="hidden" name="csrf_token" value="'
         . htmlspecialchars(csrf_token()) . '">';
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

/* ── Page guards (redirect via APP_URL) ────────────────────────────────── */
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . APP_URL . 'login.php'); exit;
    }
}
function require_hr(): void {
    require_login();
    if (!is_hr()) {
        http_response_code(403);
        include APP_ROOT . '/error_403.php'; exit;
    }
}
function require_employee(): void {
    require_login();
    if (!is_employee()) {
        http_response_code(403);
        include APP_ROOT . '/error_403.php'; exit;
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
