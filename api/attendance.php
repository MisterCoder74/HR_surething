<?php
/**
 * api/attendance.php – Attendance records.
 * Phase 3 – Employee self-service + HR view
 */
define('ROOT',     __DIR__ . '/..');
define('DATA_DIR', ROOT . '/data');
require_once ROOT . '/auth.php';
require_once __DIR__ . '/json_helper.php';

header('Content-Type: application/json; charset=utf-8');
api_require_login();

$method  = $_SERVER['REQUEST_METHOD'];
$att_dir = data_path('attendance');
if (!is_dir($att_dir)) mkdir($att_dir, 0755, true);

$VALID_TYPES = ['presenza','smartworking','ferie','permesso','malattia','assente_non_giustificato'];

function att_file(string $eid): string {
    global $att_dir;
    return $att_dir . '/' . preg_replace('/[^a-z0-9_-]/i', '', $eid) . '.json';
}
function load_att(string $eid): array {
    $f = att_file($eid);
    return file_exists($f) ? read_json($f) : [];
}
function save_att(string $eid, array $records): void {
    write_json(att_file($eid), array_values($records));
}

// ── GET: ?employee_id=e001&month=2026-05 ────────────────────────────────────
if ($method === 'GET') {
    if (is_employee()) {
        $eid = get_employee_id();
    } else {
        api_require_hr();
        $eid = $_GET['employee_id'] ?? '';
        if (!$eid) json_response(['error' => 'employee_id obbligatorio'], 422);
    }

    $month   = $_GET['month'] ?? '';
    $records = load_att($eid);
    if ($month) $records = array_values(array_filter($records, fn($r) => str_starts_with($r['date'], $month)));
    usort($records, fn($a, $b) => strcmp($a['date'], $b['date']));
    json_response($records);
}

// ── POST ─────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    api_validate_csrf();
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? '';
    global $VALID_TYPES;

    if ($action === 'save') {
        $date = $input['date'] ?? '';
        $type = $input['type'] ?? '';

        if (is_employee()) {
            $eid = get_employee_id();
        } else {
            api_require_hr();
            $eid = $input['employee_id'] ?? '';
        }
        if (!$eid) json_response(['error' => 'employee_id mancante'], 422);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json_response(['error' => 'Data non valida'], 422);
        if (!in_array($type, $VALID_TYPES)) json_response(['error' => 'Tipo non valido'], 422);

        // Employees can only edit own records within last 7 days
        if (is_employee()) {
            $now = new DateTime(); $dt = new DateTime($date);
            if ($dt > $now) json_response(['error' => 'Non puoi registrare presenze future'], 422);
            if ($now->diff($dt)->days > 7) json_response(['error' => 'Puoi modificare solo gli ultimi 7 giorni'], 422);
        }

        $records = load_att($eid);
        $idx = null;
        foreach ($records as $i => $r) if ($r['date'] === $date) { $idx = $i; break; }
        $record = [
            'date'      => $date,
            'type'      => $type,
            'check_in'  => trim($input['check_in']  ?? ''),
            'check_out' => trim($input['check_out'] ?? ''),
            'notes'     => trim($input['notes']     ?? ''),
        ];
        if ($idx !== null) $records[$idx] = $record;
        else               $records[]     = $record;
        save_att($eid, $records);
        json_response(['success' => true, 'record' => $record]);
    }

    if ($action === 'delete') {
        $date = $input['date'] ?? '';
        if (is_employee()) $eid = get_employee_id();
        else { api_require_hr(); $eid = $input['employee_id'] ?? ''; }
        if (!$eid || !$date) json_response(['error' => 'Parametri mancanti'], 422);
        $records = array_values(array_filter(load_att($eid), fn($r) => $r['date'] !== $date));
        save_att($eid, $records);
        json_response(['success' => true]);
    }

    // bulk_summary: HR – counts per employee for a month
    if ($action === 'bulk_summary') {
        api_require_hr();
        $month    = $input['month'] ?? date('Y-m');
        $emp_file = data_path('employees', 'employees.json');
        $emp_list = file_exists($emp_file) ? read_json($emp_file) : [];
        $summary  = [];
        foreach ($emp_list as $emp) {
            $records = load_att($emp['employee_id']);
            $month_r = array_filter($records, fn($r) => str_starts_with($r['date'], $month));
            $counts  = array_fill_keys($VALID_TYPES, 0);
            foreach ($month_r as $r) if (isset($counts[$r['type']])) $counts[$r['type']]++;
            $summary[] = [
                'employee_id' => $emp['employee_id'],
                'name'        => $emp['first_name'] . ' ' . $emp['last_name'],
                'department'  => $emp['department'],
                'status'      => $emp['status'],
                'counts'      => $counts,
            ];
        }
        json_response($summary);
    }

    json_response(['error' => 'Azione non riconosciuta'], 400);
}

json_response(['error' => 'Metodo non supportato'], 405);
