<?php
/**
 * api/dashboard.php — Dashboard data API. Phase 8 (fixed in Phase 9).
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

    $employees = read_json(data_path('employees', 'employees.json'));
    $total     = count($employees);
    $active    = count(array_filter($employees, fn($e) => ($e['status'] ?? '') === 'attivo'));

    $empMap = [];
    foreach ($employees as $e) {
        $empMap[$e['employee_id']] = $e['first_name'] . ' ' . $e['last_name'];
    }

    // Leave requests — uses Italian field names: stato, tipo, data_inizio, data_fine, creato_il
    $leaveReqs = read_json(data_path('leave_requests', 'requests.json'));
    $pendingL  = count(array_filter($leaveReqs, fn($r) => ($r['stato'] ?? '') === 'pending'));

    // Smartworking — uses: stato, data_inizio, data_fine, creato_il
    $swReqs    = read_json(data_path('smartworking', 'requests.json'));
    $pendingSW = count(array_filter($swReqs, fn($r) => ($r['stato'] ?? '') === 'pending'));
    $swToday   = count(array_filter($swReqs, fn($r) =>
        ($r['stato'] ?? '') === 'approved'
        && ($r['data_inizio'] ?? '') <= $today
        && ($r['data_fine']   ?? '') >= $today
    ));

    // Sick leaves — uses: stato, data_inizio, creato_il
    $sickRecs   = read_json(data_path('sick_leave', 'records.json'));
    $activeSick = count(array_filter($sickRecs, fn($r) => ($r['stato'] ?? '') === 'active'));

    // Recent activity: merge last 8 items, sorted DESC by creato_il
    $activity = [];
    foreach ($leaveReqs as $r) {
        $t = $r['tipo'] ?? 'ferie';
        $activity[] = [
            'type'       => $t,
            'label'      => $t === 'permesso' ? 'Permesso' : 'Ferie',
            'badge_type' => $t === 'permesso' ? 'permesso' : 'ferie',
            'employee'   => $empMap[$r['employee_id'] ?? ''] ?? ($r['employee_id'] ?? '—'),
            'status'     => $r['stato'] ?? 'pending',
            'info'       => ($r['data_inizio'] ?? '—') . ' → ' . ($r['data_fine'] ?? '—'),
            'created_at' => $r['creato_il'] ?? '2000-01-01T00:00:00',
        ];
    }
    foreach ($swReqs as $r) {
        $di = $r['data_inizio'] ?? '—';
        $df = $r['data_fine']   ?? $di;
        $activity[] = [
            'type'       => 'smartworking',
            'label'      => 'Smartworking',
            'badge_type' => 'smartworking',
            'employee'   => $empMap[$r['employee_id'] ?? ''] ?? ($r['employee_id'] ?? '—'),
            'status'     => $r['stato'] ?? 'pending',
            'info'       => $di === $df ? $di : $di . ' → ' . $df,
            'created_at' => $r['creato_il'] ?? '2000-01-01T00:00:00',
        ];
    }
    foreach ($sickRecs as $r) {
        $activity[] = [
            'type'       => 'sick_leave',
            'label'      => 'Malattia',
            'badge_type' => 'malattia',
            'employee'   => $empMap[$r['employee_id'] ?? ''] ?? ($r['employee_id'] ?? '—'),
            'status'     => $r['stato'] ?? 'active',
            'info'       => $r['data_inizio'] ?? '—',
            'created_at' => $r['creato_il'] ?? '2000-01-01T00:00:00',
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
    $anno  = date('Y');
    $mese  = date('Y-m');

    $employees = read_json(data_path('employees', 'employees.json'));
    $empData   = null;
    foreach ($employees as $e) { if ($e['employee_id'] === $empId) { $empData = $e; break; } }
    $name = $empData ? ($empData['first_name'] . ' ' . $empData['last_name']) : $empId;

    $balances = read_json(data_path('leave_balance', $anno . '.json'));
    $bal      = $balances[$empId] ?? [
        'ferie_totali'         => 0, 'ferie_usate'          => 0, 'ferie_residue'        => 0,
        'permessi_totali_ore'  => 0, 'permessi_usati_ore'   => 0, 'permessi_residui_ore' => 0,
    ];

    $leaveReqs    = read_json(data_path('leave_requests', 'requests.json'));
    $pendingFerie = count(array_filter($leaveReqs,
        fn($r) => $r['employee_id'] === $empId && ($r['stato'] ?? '') === 'pending'));

    $swReqs    = read_json(data_path('smartworking', 'requests.json'));
    $pendingSW = count(array_filter($swReqs,
        fn($r) => $r['employee_id'] === $empId && ($r['stato'] ?? '') === 'pending'));

    $sickRecs   = read_json(data_path('sick_leave', 'records.json'));
    $sickActive = count(array_filter($sickRecs,
        fn($r) => $r['employee_id'] === $empId && ($r['stato'] ?? '') === 'active')) > 0;

    $attFile      = data_path('attendance', $empId . '.json');
    $attRecs      = file_exists($attFile) ? read_json($attFile) : [];
    $presenzeMese = count(array_filter($attRecs, fn($r) => str_starts_with($r['date'] ?? '', $mese)));

    json_response([
        'name'             => $name,
        'leave_balance'    => $bal,
        'pending_requests' => $pendingFerie + $pendingSW,
        'sick_active'      => $sickActive,
        'presenze_mese'    => $presenzeMese,
    ]);
}

json_response(['error' => 'Azione non valida'], 400);
