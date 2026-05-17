<?php
/**
 * api/dashboard.php — Dashboard data API. Phase 8.
 * GET ?action=hr       → HR dashboard summary (requires hr role)
 * GET ?action=employee → Employee dashboard summary (requires employee role)
 */
define('ROOT', __DIR__ . '/..');
define('DATA_DIR', ROOT . '/data');
require_once ROOT . '/auth.php';
require_once __DIR__ . '/json_helper.php';
header('Content-Type: application/json; charset=utf-8');

api_require_login();

$action = $_GET['action'] ?? (is_hr() ? 'hr' : 'employee');

/* ── HR Dashboard ─────────────────────────────────────────────────────────── */
if ($action === 'hr') {
    api_require_hr();

    $today = date('Y-m-d');

    // Employees
    $employees = read_json(data_path('employees', 'employees.json'));
    $total     = count($employees);
    $active    = count(array_filter($employees, fn($e) => ($e['status'] ?? '') === 'attivo'));

    // Employee name map
    $empMap = [];
    foreach ($employees as $e) {
        $empMap[$e['employee_id']] = $e['first_name'] . ' ' . $e['last_name'];
    }

    // Leave requests — pending count
    $leaveReqs   = read_json(data_path('leave_requests', 'requests.json'));
    $pendingL    = count(array_filter($leaveReqs, fn($r) => ($r['status'] ?? '') === 'pending'));

    // Smartworking — pending count + approved today
    $swReqs  = read_json(data_path('smartworking', 'requests.json'));
    $pendingSW = count(array_filter($swReqs, fn($r) => ($r['status'] ?? '') === 'pending'));
    $swToday   = count(array_filter($swReqs,  fn($r) => ($r['status'] ?? '') === 'approved'
                                                     && ($r['date']   ?? '') === $today));

    // Sick leaves — active
    $sickReqs  = read_json(data_path('sick_leave', 'records.json'));
    $activeSick = count(array_filter($sickReqs, fn($r) => ($r['status'] ?? '') === 'active'));

    // Recent activity: merge last 8 items from all stores, sorted DESC
    $activity = [];
    foreach ($leaveReqs as $r) {
        $t = $r['type'] ?? 'ferie';
        $activity[] = [
            'type'       => $t,
            'label'      => $t === 'permesso' ? 'Permesso' : 'Ferie',
            'badge_type' => $t === 'permesso' ? 'permesso' : 'ferie',
            'employee'   => $empMap[$r['employee_id'] ?? ''] ?? ($r['employee_id'] ?? '—'),
            'status'     => $r['status'] ?? 'pending',
            'info'       => ($r['date_from'] ?? '—') . ' → ' . ($r['date_to'] ?? '—'),
            'created_at' => $r['created_at'] ?? '2000-01-01T00:00:00Z',
        ];
    }
    foreach ($swReqs as $r) {
        $activity[] = [
            'type'       => 'smartworking',
            'label'      => 'Smartworking',
            'badge_type' => 'smartworking',
            'employee'   => $empMap[$r['employee_id'] ?? ''] ?? ($r['employee_id'] ?? '—'),
            'status'     => $r['status'] ?? 'pending',
            'info'       => $r['date'] ?? '—',
            'created_at' => $r['created_at'] ?? '2000-01-01T00:00:00Z',
        ];
    }
    foreach ($sickReqs as $r) {
        $activity[] = [
            'type'       => 'sick_leave',
            'label'      => 'Malattia',
            'badge_type' => 'malattia',
            'employee'   => $empMap[$r['employee_id'] ?? ''] ?? ($r['employee_id'] ?? '—'),
            'status'     => $r['status'] ?? 'active',
            'info'       => $r['start_date'] ?? '—',
            'created_at' => $r['created_at'] ?? '2000-01-01T00:00:00Z',
        ];
    }
    usort($activity, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
    $activity = array_slice($activity, 0, 8);

    json_response([
        'kpi' => [
            'employees_total'    => $total,
            'employees_active'   => $active,
            'pending_requests'   => $pendingL + $pendingSW,
            'active_sick'        => $activeSick,
            'smartworking_today' => $swToday,
        ],
        'recent_activity' => $activity,
    ]);
}

/* ── Employee Dashboard ───────────────────────────────────────────────────── */
if ($action === 'employee') {
    api_require_employee();
    $empId = get_employee_id();
    $year  = (int)date('Y');
    $ym    = date('Y-m');

    // Leave balance
    $balances = read_json(data_path('leave_balance', $year . '.json'));
    $bal = $balances[$empId] ?? [
        'ferie_totali' => 0, 'ferie_usate' => 0, 'ferie_residue' => 0,
        'permessi_totali_ore' => 0, 'permessi_usati_ore' => 0, 'permessi_residui_ore' => 0,
    ];

    // Pending leave requests
    $leaveReqs = read_json(data_path('leave_requests', 'requests.json'));
    $pendingL  = count(array_filter($leaveReqs,
        fn($r) => ($r['employee_id'] ?? '') === $empId && ($r['status'] ?? '') === 'pending'));

    // Pending smartworking
    $swReqs   = read_json(data_path('smartworking', 'requests.json'));
    $pendingSW = count(array_filter($swReqs,
        fn($r) => ($r['employee_id'] ?? '') === $empId && ($r['status'] ?? '') === 'pending'));

    // Active sick leave
    $sickReqs = read_json(data_path('sick_leave', 'records.json'));
    $activeSickArr = array_values(array_filter($sickReqs,
        fn($r) => ($r['employee_id'] ?? '') === $empId && ($r['status'] ?? '') === 'active'));
    $hasSick = count($activeSickArr) > 0;

    // Employee info
    $employees = read_json(data_path('employees', 'employees.json'));
    $emp = null;
    foreach ($employees as $e) {
        if (($e['employee_id'] ?? '') === $empId) { $emp = $e; break; }
    }
    $fullName = $emp ? $emp['first_name'] . ' ' . $emp['last_name'] : $empId;

    // Presence days this month
    $att = read_json(data_path('attendance', $ym . '.json'));
    $presenceDays = count(array_filter($att,
        fn($r) => ($r['employee_id'] ?? '') === $empId
               && in_array($r['type'] ?? '', ['presenza', 'smartworking'])));

    json_response([
        'employee'   => [
            'id'         => $empId,
            'name'       => $fullName,
            'role'       => $emp['role']       ?? '',
            'department' => $emp['department'] ?? '',
        ],
        'leave_balance' => $bal,
        'pending'    => [
            'ferie'       => $pendingL,
            'smartworking' => $pendingSW,
            'total'        => $pendingL + $pendingSW,
        ],
        'sick_active'             => $hasSick,
        'presence_days_this_month' => $presenceDays,
    ]);
}

json_response(['error' => 'Azione non valida'], 400);
