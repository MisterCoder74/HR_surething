<?php
/**
 * api/employees.php – Employee Registry CRUD.
 * Phase 2
 */
define('ROOT',     __DIR__ . '/..');
define('DATA_DIR', ROOT . '/data');
require_once ROOT . '/auth.php';
require_once __DIR__ . '/json_helper.php';

header('Content-Type: application/json; charset=utf-8');
api_require_login();

$method   = $_SERVER['REQUEST_METHOD'];
$emp_file = data_path('employees', 'employees.json');
if (!is_dir(dirname($emp_file))) mkdir(dirname($emp_file), 0755, true);
if (!file_exists($emp_file))     write_json($emp_file, []);

function load_emps(): array  { global $emp_file; return read_json($emp_file); }
function save_emps(array $l): void { global $emp_file; write_json($emp_file, array_values($l)); }
function find_emp_idx(string $id, array $l): int|false {
    foreach ($l as $i => $e) if ($e['employee_id'] === $id) return $i;
    return false;
}

// ── GET ──────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $id   = $_GET['id'] ?? '';
    $list = load_emps();

    if ($id) {
        if (is_employee() && get_employee_id() !== $id)
            json_response(['error' => 'Accesso non autorizzato'], 403);
        $idx = find_emp_idx($id, $list);
        if ($idx === false) json_response(['error' => 'Dipendente non trovato'], 404);
        json_response($list[$idx]);
    }

    // Employee gets only own record; HR gets all
    if (is_employee()) {
        $eid = get_employee_id();
        foreach ($list as $e) if ($e['employee_id'] === $eid) json_response([$e]);
        json_response([]);
    }
    json_response($list);
}

// ── POST ─────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    api_validate_csrf();
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? '';

    if ($action === 'create') {
        api_require_hr();
        $req = ['first_name','last_name','email','role','department','contract_type','hire_date'];
        foreach ($req as $f)
            if (empty(trim($input[$f] ?? ''))) json_response(['error' => "Campo obbligatorio: $f"], 422);

        $list = load_emps();
        foreach ($list as $e)
            if (strtolower($e['email']) === strtolower(trim($input['email'])))
                json_response(['error' => 'Email già presente'], 422);

        $maxN = 0;
        foreach ($list as $e)
            if (preg_match('/^e(\d+)$/', $e['employee_id'], $m)) $maxN = max($maxN, (int)$m[1]);
        $newId = 'e' . str_pad($maxN + 1, 3, '0', STR_PAD_LEFT);

        $emp = [
            'employee_id'   => $newId,
            'user_id'       => null,
            'first_name'    => trim($input['first_name']),
            'last_name'     => trim($input['last_name']),
            'email'         => trim($input['email']),
            'role'          => trim($input['role']),
            'department'    => trim($input['department']),
            'contract_type' => trim($input['contract_type']),
            'hire_date'     => trim($input['hire_date']),
            'status'        => 'attivo',
            'phone'         => trim($input['phone'] ?? ''),
            'notes'         => trim($input['notes'] ?? ''),
        ];
        $list[] = $emp;
        save_emps($list);
        json_response(['success' => true, 'employee' => $emp]);
    }

    if ($action === 'update') {
        api_require_hr();
        $id   = $input['employee_id'] ?? '';
        $list = load_emps();
        $idx  = find_emp_idx($id, $list);
        if ($idx === false) json_response(['error' => 'Dipendente non trovato'], 404);
        foreach (['first_name','last_name','email','role','department','contract_type','hire_date','phone','notes','status'] as $f)
            if (isset($input[$f])) $list[$idx][$f] = trim($input[$f]);
        save_emps($list);
        json_response(['success' => true, 'employee' => $list[$idx]]);
    }

    if ($action === 'deactivate') {
        api_require_hr();
        $list = load_emps();
        $idx  = find_emp_idx($input['employee_id'] ?? '', $list);
        if ($idx === false) json_response(['error' => 'Dipendente non trovato'], 404);
        $list[$idx]['status'] = 'cessato';
        save_emps($list);
        json_response(['success' => true]);
    }

    if ($action === 'reactivate') {
        api_require_hr();
        $list = load_emps();
        $idx  = find_emp_idx($input['employee_id'] ?? '', $list);
        if ($idx === false) json_response(['error' => 'Dipendente non trovato'], 404);
        $list[$idx]['status'] = 'attivo';
        save_emps($list);
        json_response(['success' => true]);
    }

    json_response(['error' => 'Azione non riconosciuta'], 400);
}

json_response(['error' => 'Metodo non supportato'], 405);
