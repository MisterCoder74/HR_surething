<?php
/**
 * api/users.php – User profile & password management.
 * Phase 1 – Authentication
 */
define('ROOT',     __DIR__ . '/..');
define('DATA_DIR', ROOT . '/data');
require_once ROOT . '/auth.php';
require_once __DIR__ . '/json_helper.php';

header('Content-Type: application/json; charset=utf-8');
api_require_login();

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: return current user profile ────────────────────────────────────────
if ($method === 'GET') {
    $creds = read_json(data_path('users', 'credentials.json'));
    $uid   = get_user_id();
    foreach ($creds as $u) {
        if ($u['user_id'] === $uid) {
            json_response([
                'user_id'     => $u['user_id'],
                'username'    => $u['username'],
                'role'        => $u['role'],
                'employee_id' => $u['employee_id'] ?? null,
            ]);
        }
    }
    json_response(['error' => 'Utente non trovato'], 404);
}

// ── POST actions ─────────────────────────────────────────────────────────────
if ($method === 'POST') {
    api_validate_csrf();
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? '';

    // change_password – authenticated user changes own password
    if ($action === 'change_password') {
        $old = $input['old_password'] ?? '';
        $new = $input['new_password'] ?? '';
        if (strlen($new) < 8)
            json_response(['error' => 'La nuova password deve contenere almeno 8 caratteri'], 422);

        $creds = read_json(data_path('users', 'credentials.json'));
        $uid   = get_user_id();
        $idx   = null;
        foreach ($creds as $i => $u) { if ($u['user_id'] === $uid) { $idx = $i; break; } }
        if ($idx === null) json_response(['error' => 'Utente non trovato'], 404);
        if (!password_verify($old, $creds[$idx]['password_hash']))
            json_response(['error' => 'Password attuale non corretta'], 422);

        $creds[$idx]['password_hash'] = password_hash($new, PASSWORD_BCRYPT);
        write_json(data_path('users', 'credentials.json'), $creds);
        json_response(['success' => true, 'message' => 'Password aggiornata con successo']);
    }

    // reset_password – HR resets any user's password (no old-password check)
    if ($action === 'reset_password') {
        api_require_hr();
        $target = $input['user_id']      ?? '';
        $new    = $input['new_password'] ?? '';
        if (strlen($new) < 8)
            json_response(['error' => 'La nuova password deve contenere almeno 8 caratteri'], 422);

        $creds = read_json(data_path('users', 'credentials.json'));
        $idx   = null;
        foreach ($creds as $i => $u) { if ($u['user_id'] === $target) { $idx = $i; break; } }
        if ($idx === null) json_response(['error' => 'Utente non trovato'], 404);

        $creds[$idx]['password_hash'] = password_hash($new, PASSWORD_BCRYPT);
        write_json(data_path('users', 'credentials.json'), $creds);
        json_response(['success' => true, 'message' => 'Password resettata con successo']);
    }

    // list – HR only, returns sanitised user list (used from Phase 2)
    if ($action === 'list') {
        api_require_hr();
        $creds = read_json(data_path('users', 'credentials.json'));
        json_response(array_map(fn($u) => [
            'user_id'  => $u['user_id'],
            'username' => $u['username'],
            'role'     => $u['role'],
        ], $creds));
    }

    json_response(['error' => 'Azione non riconosciuta'], 400);
}

json_response(['error' => 'Metodo non supportato'], 405);
