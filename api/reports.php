<?php
/**
 * api/reports.php — Reports & CSV Export. Phase 9. HR only.
 *
 * GET ?action=presenze&mese=2026-05[&format=csv]
 * GET ?action=ferie_permessi&anno=2026[&format=csv]
 * GET ?action=malattie&anno=2026[&format=csv]
 * GET ?action=smartworking&anno=2026[&mese=2026-05][&format=csv]
 */
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
function rpt_employees(): array {
    return read_json(data_path('employees', 'employees.json'));
}
function rpt_emp_map(): array {
    $m = [];
    foreach (rpt_employees() as $e) $m[$e['employee_id']] = $e;
    return $m;
}
function rpt_load_att(string $eid): array {
    $f = data_path('attendance', $eid . '.json');
    return file_exists($f) ? read_json($f) : [];
}
function rpt_csv(array $headers, array $rows, string $filename): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    fputcsv($out, $headers, ';');
    foreach ($rows as $row) fputcsv($out, array_values($row), ';');
    fclose($out);
    exit;
}
function rpt_wd(string $start, ?string $end): int {
    $s = new DateTime($start);
    $e = $end ? new DateTime($end) : new DateTime();
    $n = 0; $c = clone $s;
    while ($c <= $e) { if ((int)$c->format('N') < 6) $n++; $c->modify('+1 day'); }
    return $n;
}

/* ── PRESENZE MENSILI ────────────────────────────────────────────────────── */
if ($action === 'presenze') {
    $types = ['presenza','smartworking','ferie','permesso','malattia','assente_non_giustificato'];
    $rows  = [];
    foreach (rpt_employees() as $emp) {
        $recs = array_filter(rpt_load_att($emp['employee_id']),
            fn($r) => str_starts_with($r['date'] ?? '', $mese));
        $cnt  = array_fill_keys($types, 0);
        foreach ($recs as $r) if (isset($cnt[$r['type'] ?? ''])) $cnt[$r['type']]++;
        $rows[] = [
            'employee_id' => $emp['employee_id'],
            'name'        => $emp['first_name'] . ' ' . $emp['last_name'],
            'department'  => $emp['department'],
            'status'      => $emp['status'],
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
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['mese' => $mese, 'data' => $rows], JSON_UNESCAPED_UNICODE); exit;
}

/* ── FERIE & PERMESSI ────────────────────────────────────────────────────── */
if ($action === 'ferie_permessi') {
    $empMap   = rpt_emp_map();
    $reqs     = array_filter(
        read_json(data_path('leave_requests', 'requests.json')),
        fn($r) => str_starts_with($r['data_inizio'] ?? '', $anno)
    );
    $balances = read_json(data_path('leave_balance', $anno . '.json'));

    $rows = [];
    foreach ($empMap as $eid => $emp) {
        $bal     = $balances[$eid] ?? [];
        $empReqs = array_values(array_filter($reqs, fn($r) => $r['employee_id'] === $eid));
        usort($empReqs, fn($a,$b) => strcmp($a['data_inizio'] ?? '', $b['data_inizio'] ?? ''));
        $rows[] = [
            'employee_id'          => $eid,
            'name'                 => $emp['first_name'] . ' ' . $emp['last_name'],
            'department'           => $emp['department'],
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
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['anno' => $anno, 'data' => $rows], JSON_UNESCAPED_UNICODE); exit;
}

/* ── MALATTIE ────────────────────────────────────────────────────────────── */
if ($action === 'malattie') {
    $empMap  = rpt_emp_map();
    $records = array_values(array_filter(
        read_json(data_path('sick_leave', 'records.json')),
        fn($r) => str_starts_with($r['data_inizio'] ?? '', $anno)
    ));
    usort($records, fn($a,$b) => strcmp($b['data_inizio'] ?? '', $a['data_inizio'] ?? ''));

    $rows = array_map(function($r) use ($empMap) {
        $emp = $empMap[$r['employee_id']] ?? [];
        return [
            'id'          => $r['id']          ?? '',
            'employee_id' => $r['employee_id'] ?? '',
            'name'        => ($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''),
            'department'  => $emp['department']  ?? '',
            'data_inizio' => $r['data_inizio']  ?? '',
            'data_fine'   => $r['data_fine']    ?? null,
            'giorni_wd'   => rpt_wd($r['data_inizio'] ?? date('Y-m-d'), $r['data_fine'] ?? null),
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
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['anno' => $anno, 'data' => $rows], JSON_UNESCAPED_UNICODE); exit;
}

/* ── SMARTWORKING ────────────────────────────────────────────────────────── */
if ($action === 'smartworking') {
    $empMap   = rpt_emp_map();
    $allReqs  = read_json(data_path('smartworking', 'requests.json'));
    $useMese  = !empty($_GET['mese']);
    $period   = $useMese ? $mese : $anno;
    $filtered = array_filter($allReqs, fn($r) => str_starts_with($r['data_inizio'] ?? '', $period));

    $rows = [];
    foreach ($empMap as $eid => $emp) {
        $empReqs  = array_values(array_filter($filtered, fn($r) => $r['employee_id'] === $eid));
        $approved = array_filter($empReqs, fn($r) => ($r['stato'] ?? '') === 'approved');
        $ggTot    = array_sum(array_map(fn($r) => (int)($r['giorni'] ?? 0), $approved));
        $rows[] = [
            'employee_id'   => $eid,
            'name'          => $emp['first_name'] . ' ' . $emp['last_name'],
            'department'    => $emp['department'],
            'totale_giorni' => $ggTot,
            'richieste'     => count($empReqs),
            'approvate'     => count($approved),
            'requests'      => $empReqs,
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
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['periodo' => $period, 'data' => $rows], JSON_UNESCAPED_UNICODE); exit;
}

header('Content-Type: application/json; charset=utf-8');
http_response_code(400);
echo json_encode(['error' => 'Azione non valida'], JSON_UNESCAPED_UNICODE);
