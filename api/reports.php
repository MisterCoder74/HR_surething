<?php
/**
 * api/reports.php — Reports & CSV Export. Phase 9 + Phase 10 bugfix.
 * HR only.
 *
 * GET ?action=presenze&mese=2026-05[&format=csv]
 * GET ?action=ferie_permessi&anno=2026[&format=csv]
 * GET ?action=malattie&anno=2026[&format=csv]
 * GET ?action=smartworking&anno=2026[&mese=2026-05][&format=csv]
 */
ob_start(); // capture any stray PHP notices so CSV headers can still be sent

define('ROOT', __DIR__ . '/..');
define('DATA_DIR', ROOT . '/data');
require_once ROOT . '/auth.php';
require_once __DIR__ . '/json_helper.php';
api_require_hr();

$action = $_GET['action'] ?? '';
$format = $_GET['format'] ?? 'json';
$mese   = preg_replace('/[^0-9-]/', '', $_GET['mese'] ?? date('Y-m'));
$anno   = preg_replace('/[^0-9]/',  '', $_GET['anno'] ?? date('Y'));

/* ── helpers ─────────────────────────────────────────────────────────────── */

/** Build employee_id → record map from employees.json */
function rpt_emp_map(): array {
    $m = [];
    foreach (read_json(data_path('employees', 'employees.json')) as $e) {
        $eid = $e['employee_id'] ?? '';
        if ($eid) $m[$eid] = $e;
    }
    return $m;
}

/** emp name from map (fallback to eid) */
function rpt_name(string $eid, array $empMap): string {
    $e = $empMap[$eid] ?? [];
    return trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')) ?: $eid;
}
function rpt_dept(string $eid, array $empMap): string {
    return $empMap[$eid]['department'] ?? '';
}

/** CSV response — clears ALL output buffers before sending headers */
function rpt_csv(array $headers, array $rows, string $filename): void {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    fputcsv($out, $headers, ';');
    foreach ($rows as $row) fputcsv($out, array_values((array)$row), ';');
    fclose($out);
    exit;
}

/** JSON response — clears ALL output buffers before sending */
function rpt_json(array $payload): void {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Business days between two dates */
function rpt_wd(string $start, ?string $end): int {
    if (!$start) return 0;
    try {
        $s = new DateTime($start);
        $e = $end ? new DateTime($end) : new DateTime();
        $n = 0; $c = clone $s;
        while ($c <= $e) { if ((int)$c->format('N') < 6) $n++; $c->modify('+1 day'); }
        return $n;
    } catch (\Exception $ex) { return 0; }
}

/* ══════════════════════════════════════════════════════════════════════════
   PRESENZE MENSILI
   Primary source: data/attendance/{eid}.json  (one file per employee)
   Falls back gracefully if employees.json missing.
   ══════════════════════════════════════════════════════════════════════════ */
if ($action === 'presenze') {
    $empMap = rpt_emp_map();
    $types  = ['presenza','smartworking','ferie','permesso','malattia','assente_non_giustificato'];
    $rows   = [];

    // Collect all employee IDs: from employees list + from attendance files
    $eids = array_keys($empMap);
    $att_dir = data_path('attendance');
    if (is_dir($att_dir)) {
        foreach (glob($att_dir . '/*.json') ?: [] as $f) {
            $eid = basename($f, '.json');
            if (!in_array($eid, $eids, true)) $eids[] = $eid;
        }
    }
    sort($eids);

    foreach ($eids as $eid) {
        $att_file = data_path('attendance', preg_replace('/[^a-z0-9_-]/i', '', $eid) . '.json');
        $recs = file_exists($att_file)
            ? array_filter(read_json($att_file), fn($r) => str_starts_with($r['date'] ?? '', $mese))
            : [];
        $cnt = array_fill_keys($types, 0);
        foreach ($recs as $r) { $t = $r['type'] ?? ''; if (isset($cnt[$t])) $cnt[$t]++; }
        $rows[] = [
            'employee_id' => $eid,
            'name'        => rpt_name($eid, $empMap),
            'department'  => rpt_dept($eid, $empMap),
            'counts'      => $cnt,
            'total'       => array_sum($cnt),
        ];
    }

    if ($format === 'csv') {
        $hdr = ['ID','Dipendente','Reparto','Presenza','Smartworking','Ferie','Permesso','Malattia','Assente','Totale'];
        $csv = array_map(fn($s) => [
            $s['employee_id'], $s['name'], $s['department'],
            $s['counts']['presenza'], $s['counts']['smartworking'], $s['counts']['ferie'],
            $s['counts']['permesso'], $s['counts']['malattia'], $s['counts']['assente_non_giustificato'],
            $s['total'],
        ], $rows);
        rpt_csv($hdr, $csv, 'presenze_' . $mese . '.csv');
    }
    rpt_json(['mese' => $mese, 'data' => $rows]);
}

/* ══════════════════════════════════════════════════════════════════════════
   FERIE & PERMESSI
   Primary source: data/leave_requests/requests.json + leave_balance/{anno}.json
   Iterates over unique employee IDs found in requests + balances (not employees.json).
   ══════════════════════════════════════════════════════════════════════════ */
if ($action === 'ferie_permessi') {
    $empMap   = rpt_emp_map();
    $allReqs  = read_json(data_path('leave_requests', 'requests.json'));
    $balances = read_json(data_path('leave_balance', $anno . '.json'));

    // Collect all employee IDs from requests + balances + employees
    $eids = array_unique(array_merge(
        array_keys($empMap),
        array_keys($balances),
        array_column(array_filter($allReqs, fn($r) => str_starts_with($r['data_inizio'] ?? '', $anno)), 'employee_id')
    ));
    sort($eids);

    $rows = [];
    foreach ($eids as $eid) {
        if (!$eid) continue;
        $bal      = is_array($balances[$eid] ?? null) ? $balances[$eid] : [];
        $empReqs  = array_values(array_filter($allReqs,
            fn($r) => ($r['employee_id'] ?? '') === $eid && str_starts_with($r['data_inizio'] ?? '', $anno)));
        usort($empReqs, fn($a,$b) => strcmp($a['data_inizio'] ?? '', $b['data_inizio'] ?? ''));
        $rows[] = [
            'employee_id'          => $eid,
            'name'                 => rpt_name($eid, $empMap),
            'department'           => rpt_dept($eid, $empMap),
            'ferie_totali'         => $bal['ferie_totali']         ?? 0,
            'ferie_usate'          => $bal['ferie_usate']          ?? 0,
            'ferie_residue'        => $bal['ferie_residue']        ?? 0,
            'permessi_totali_ore'  => $bal['permessi_totali_ore']  ?? 0,
            'permessi_usati_ore'   => $bal['permessi_usati_ore']   ?? 0,
            'permessi_residui_ore' => $bal['permessi_residui_ore'] ?? 0,
            'requests'             => $empReqs,
        ];
    }

    if ($format === 'csv') {
        $hdr = ['ID','Dipendente','Reparto','Ferie Tot.','Ferie Usate','Ferie Residue','Permessi Tot.(h)','Permessi Usati(h)','Permessi Residui(h)'];
        $csv = array_map(fn($s) => [
            $s['employee_id'], $s['name'], $s['department'],
            $s['ferie_totali'], $s['ferie_usate'], $s['ferie_residue'],
            $s['permessi_totali_ore'], $s['permessi_usati_ore'], $s['permessi_residui_ore'],
        ], $rows);
        rpt_csv($hdr, $csv, 'ferie_permessi_' . $anno . '.csv');
    }
    rpt_json(['anno' => $anno, 'data' => $rows]);
}

/* ══════════════════════════════════════════════════════════════════════════
   MALATTIE
   Primary source: data/sick_leave/records.json
   ══════════════════════════════════════════════════════════════════════════ */
if ($action === 'malattie') {
    $empMap  = rpt_emp_map();
    $records = array_values(array_filter(
        read_json(data_path('sick_leave', 'records.json')),
        fn($r) => str_starts_with($r['data_inizio'] ?? '', $anno)
    ));
    usort($records, fn($a,$b) => strcmp($b['data_inizio'] ?? '', $a['data_inizio'] ?? ''));

    $rows = array_map(function($r) use ($empMap) {
        $eid = $r['employee_id'] ?? '';
        return [
            'id'          => $r['id']          ?? '',
            'employee_id' => $eid,
            'name'        => rpt_name($eid, $empMap),
            'department'  => rpt_dept($eid, $empMap),
            'data_inizio' => $r['data_inizio']  ?? '',
            'data_fine'   => $r['data_fine']    ?? null,
            'giorni_wd'   => rpt_wd($r['data_inizio'] ?? '', $r['data_fine'] ?? null),
            'stato'       => $r['stato']        ?? '',
            'doc_status'  => $r['doc_status']   ?? '',
            'medico'      => $r['medico']       ?? '',
        ];
    }, $records);

    if ($format === 'csv') {
        $hdr = ['ID Dip.','Dipendente','Reparto','Data Inizio','Data Fine','GG Lav.','Stato','Certificato','Medico'];
        $csv = array_map(fn($s) => [
            $s['employee_id'], $s['name'], $s['department'],
            $s['data_inizio'], $s['data_fine'] ?? '—', $s['giorni_wd'],
            $s['stato'], $s['doc_status'], $s['medico'],
        ], $rows);
        rpt_csv($hdr, $csv, 'malattie_' . $anno . '.csv');
    }
    rpt_json(['anno' => $anno, 'data' => $rows]);
}

/* ══════════════════════════════════════════════════════════════════════════
   SMARTWORKING
   Primary source: data/smartworking/requests.json
   ══════════════════════════════════════════════════════════════════════════ */
if ($action === 'smartworking') {
    $empMap  = rpt_emp_map();
    $allReqs = read_json(data_path('smartworking', 'requests.json'));
    $useMese = !empty($_GET['mese']);
    $period  = $useMese ? $mese : $anno;
    $filtered = array_filter($allReqs, fn($r) => str_starts_with($r['data_inizio'] ?? '', $period));

    // Group by employee
    $byEmp = [];
    foreach ($filtered as $r) {
        $eid = $r['employee_id'] ?? '';
        if (!$eid) continue;
        $byEmp[$eid][] = $r;
    }
    // Also include employees with no requests (from empMap)
    foreach (array_keys($empMap) as $eid) {
        if (!isset($byEmp[$eid])) $byEmp[$eid] = [];
    }

    $rows = [];
    foreach ($byEmp as $eid => $empReqs) {
        $approved = array_filter($empReqs, fn($r) => ($r['stato'] ?? '') === 'approved');
        $ggTot    = array_sum(array_map(fn($r) => (int)($r['giorni'] ?? 0), $approved));
        $rows[] = [
            'employee_id'   => $eid,
            'name'          => rpt_name($eid, $empMap),
            'department'    => rpt_dept($eid, $empMap),
            'totale_giorni' => $ggTot,
            'richieste'     => count($empReqs),
            'approvate'     => count($approved),
            'requests'      => array_values($empReqs),
        ];
    }
    usort($rows, fn($a,$b) => $b['totale_giorni'] - $a['totale_giorni']);

    if ($format === 'csv') {
        $hdr = ['ID','Dipendente','Reparto','GG Approvati','N. Richieste','Approvate'];
        $csv = array_map(fn($s) => [
            $s['employee_id'], $s['name'], $s['department'],
            $s['totale_giorni'], $s['richieste'], $s['approvate'],
        ], $rows);
        rpt_csv($hdr, $csv, 'smartworking_' . $period . '.csv');
    }
    rpt_json(['periodo' => $period, 'data' => $rows]);
}

rpt_json(['error' => 'Azione non valida']);
