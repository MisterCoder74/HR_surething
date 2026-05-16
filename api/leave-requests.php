<?php
define('ROOT', __DIR__.'/..');
define('DATA_DIR', ROOT.'/data');
require_once ROOT.'/auth.php';
require_once __DIR__.'/json_helper.php';
api_require_login();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

define('REQUESTS_FILE', DATA_DIR . '/leave_requests/requests.json');
define('BALANCE_FILE',  DATA_DIR . '/leave_balance/2026.json');
define('RULES_FILE',    DATA_DIR . '/config/rules.json');

/* ── helpers ─────────────────────────────────────────────────── */
function lr_load(string $path, $default = []) {
    if (!file_exists($path)) return $default;
    $d = json_decode(file_get_contents($path), true);
    return $d !== null ? $d : $default;
}

function lr_save(string $path, $data): void {
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function count_wd(string $start, string $end): int {
    $n = 0;
    $c = new DateTime($start);
    $e = new DateTime($end);
    while ($c <= $e) {
        if ((int)$c->format('N') < 6) $n++;
        $c->modify('+1 day');
    }
    return $n;
}

// Ensure unified requests file exists
if (!file_exists(REQUESTS_FILE)) {
    file_put_contents(REQUESTS_FILE, '[]');
}

/* ── routing ─────────────────────────────────────────────────── */
switch ($action) {

  /* LIST — employee sees own, HR sees all with optional filters */
  case 'list': {
    $all = lr_load(REQUESTS_FILE, []);
    if (is_employee()) {
        $all = array_values(array_filter($all, fn($r) => $r['employee_id'] === get_employee_id()));
    } else {
        api_require_hr();
        if ($f = ($_GET['stato'] ?? ''))       $all = array_values(array_filter($all, fn($r) => $r['stato'] === $f));
        if ($f = ($_GET['employee_id'] ?? '')) $all = array_values(array_filter($all, fn($r) => $r['employee_id'] === $f));
        if ($f = ($_GET['tipo'] ?? ''))        $all = array_values(array_filter($all, fn($r) => $r['tipo'] === $f));
    }
    usort($all, fn($a,$b) => strcmp($b['creato_il'], $a['creato_il']));
    json_response(['requests' => $all]);
    break;
  }

  /* BALANCE */
  case 'balance': {
    $bal = lr_load(BALANCE_FILE, []);
    if (is_employee()) {
        $emp = get_employee_id();
        json_response(['balance' => $bal[$emp] ?? null]);
    } else {
        api_require_hr();
        $emp = $_GET['employee_id'] ?? '';
        json_response(['balance' => $emp ? ($bal[$emp] ?? null) : $bal]);
    }
    break;
  }

  /* SUBMIT — employee only */
  case 'submit': {
    api_require_employee();
    api_validate_csrf();
    $b = json_decode(file_get_contents('php://input'), true) ?: [];

    $tipo        = trim($b['tipo'] ?? '');
    $data_inizio = trim($b['data_inizio'] ?? '');
    $data_fine   = trim($b['data_fine'] ?? $data_inizio);
    $motivo      = trim($b['motivo'] ?? '');
    $ore         = isset($b['ore']) ? (float)$b['ore'] : null;

    if (!in_array($tipo, ['ferie', 'permesso'])) {
        json_response(['error' => 'Tipo non valido'], 400); break;
    }
    if (!$data_inizio || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_inizio)) {
        json_response(['error' => 'Data inizio non valida'], 400); break;
    }

    $rules   = lr_load(RULES_FILE, []);
    $emp_id  = get_employee_id();
    $balance = lr_load(BALANCE_FILE, []);
    $bal     = $balance[$emp_id] ?? [];
    $oggi    = new DateTime('today');

    if ($tipo === 'ferie') {
        if (!$data_fine || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_fine)) {
            json_response(['error' => 'Data fine non valida'], 400); break;
        }
        if ($data_fine < $data_inizio) {
            json_response(['error' => 'Data fine precedente a data inizio'], 400); break;
        }
        $preavviso  = (int)($rules['ferie']['preavviso_minimo_giorni'] ?? 7);
        $dt_inizio  = new DateTime($data_inizio);
        if ($dt_inizio <= $oggi) {
            json_response(['error' => 'La data di inizio deve essere futura'], 400); break;
        }
        $diff = (int)$oggi->diff($dt_inizio)->days;
        if ($diff < $preavviso) {
            json_response(['error' => "Preavviso minimo di {$preavviso} giorni richiesto"], 400); break;
        }
        $giorni = count_wd($data_inizio, $data_fine);
        if ($giorni <= 0) {
            json_response(['error' => 'Nessun giorno lavorativo nel periodo selezionato'], 400); break;
        }
        if (!empty($bal) && $giorni > ($bal['ferie_residue'] ?? 0)) {
            json_response(['error' => 'Giorni ferie insufficienti (disponibili: '.($bal['ferie_residue'] ?? 0).')'], 400); break;
        }
        $req = [
            'id'            => 'lr_'.time().'_'.$emp_id,
            'employee_id'   => $emp_id,
            'tipo'          => 'ferie',
            'data_inizio'   => $data_inizio,
            'data_fine'     => $data_fine,
            'giorni'        => $giorni,
            'ore'           => null,
            'motivo'        => $motivo,
            'stato'         => 'pending',
            'note_hr'       => null,
            'creato_il'     => date('c'),
            'aggiornato_il' => null,
            'aggiornato_da' => null,
        ];
    } else { // permesso
        if ($ore === null || $ore <= 0 || fmod($ore * 2, 1) !== 0.0) {
            json_response(['error' => 'Ore non valide (incrementi da 0.5h)'], 400); break;
        }
        $ore_max = (float)($rules['ore_giornaliere'] ?? 8);
        if ($ore > $ore_max) {
            json_response(['error' => "Ore permesso max {$ore_max}h per giorno"], 400); break;
        }
        if (!empty($bal) && $ore > ($bal['permessi_residui_ore'] ?? 0)) {
            json_response(['error' => 'Ore permesso insufficienti (disponibili: '.($bal['permessi_residui_ore'] ?? 0).'h)'], 400); break;
        }
        $req = [
            'id'            => 'lr_'.time().'_'.$emp_id,
            'employee_id'   => $emp_id,
            'tipo'          => 'permesso',
            'data_inizio'   => $data_inizio,
            'data_fine'     => $data_inizio,
            'giorni'        => null,
            'ore'           => $ore,
            'motivo'        => $motivo,
            'stato'         => 'pending',
            'note_hr'       => null,
            'creato_il'     => date('c'),
            'aggiornato_il' => null,
            'aggiornato_da' => null,
        ];
    }

    $all   = lr_load(REQUESTS_FILE, []);
    $all[] = $req;
    lr_save(REQUESTS_FILE, array_values($all));
    json_response(['ok' => true, 'request' => $req]);
    break;
  }

  /* CANCEL — employee cancels own pending request */
  case 'cancel': {
    api_require_employee();
    api_validate_csrf();
    $b  = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = trim($b['id'] ?? '');
    if (!$id) { json_response(['error' => 'ID mancante'], 400); break; }

    $all    = lr_load(REQUESTS_FILE, []);
    $emp_id = get_employee_id();
    $found  = false;
    foreach ($all as &$r) {
        if ($r['id'] !== $id) continue;
        if ($r['employee_id'] !== $emp_id) { json_response(['error' => 'Non autorizzato'], 403); break 2; }
        if ($r['stato'] !== 'pending')     { json_response(['error' => 'Solo richieste in attesa possono essere annullate'], 400); break 2; }
        $r['stato']         = 'cancelled';
        $r['aggiornato_il'] = date('c');
        $found = true; break;
    }
    unset($r);
    if (!$found) { json_response(['error' => 'Richiesta non trovata'], 404); break; }
    lr_save(REQUESTS_FILE, array_values($all));
    json_response(['ok' => true]);
    break;
  }

  /* APPROVE — HR only */
  case 'approve': {
    api_require_hr();
    api_validate_csrf();
    $b       = json_decode(file_get_contents('php://input'), true) ?: [];
    $id      = trim($b['id'] ?? '');
    $note_hr = trim($b['note_hr'] ?? '');
    if (!$id) { json_response(['error' => 'ID mancante'], 400); break; }

    $all     = lr_load(REQUESTS_FILE, []);
    $balance = lr_load(BALANCE_FILE, []);
    $found   = false;
    foreach ($all as &$r) {
        if ($r['id'] !== $id) continue;
        if ($r['stato'] !== 'pending') { json_response(['error' => 'Solo richieste in attesa possono essere approvate'], 400); break 2; }
        $eid = $r['employee_id'];
        if (isset($balance[$eid])) {
            if ($r['tipo'] === 'ferie') {
                $balance[$eid]['ferie_usate']   = ($balance[$eid]['ferie_usate'] ?? 0) + $r['giorni'];
                $balance[$eid]['ferie_residue'] = max(0, ($balance[$eid]['ferie_residue'] ?? 0) - $r['giorni']);
            } else {
                $balance[$eid]['permessi_usati_ore']   = ($balance[$eid]['permessi_usati_ore'] ?? 0) + $r['ore'];
                $balance[$eid]['permessi_residui_ore'] = max(0, ($balance[$eid]['permessi_residui_ore'] ?? 0) - $r['ore']);
            }
            $balance[$eid]['ultimo_aggiornamento'] = date('c');
        }
        $r['stato']         = 'approved';
        $r['note_hr']       = $note_hr ?: null;
        $r['aggiornato_il'] = date('c');
        $r['aggiornato_da'] = get_user_id();
        $found = true; break;
    }
    unset($r);
    if (!$found) { json_response(['error' => 'Richiesta non trovata'], 404); break; }
    lr_save(REQUESTS_FILE, array_values($all));
    lr_save(BALANCE_FILE, $balance);
    json_response(['ok' => true]);
    break;
  }

  /* REJECT — HR only, note obbligatoria */
  case 'reject': {
    api_require_hr();
    api_validate_csrf();
    $b       = json_decode(file_get_contents('php://input'), true) ?: [];
    $id      = trim($b['id'] ?? '');
    $note_hr = trim($b['note_hr'] ?? '');
    if (!$id)      { json_response(['error' => 'ID mancante'], 400); break; }
    if (!$note_hr) { json_response(['error' => 'Motivazione rifiuto obbligatoria'], 400); break; }

    $all   = lr_load(REQUESTS_FILE, []);
    $found = false;
    foreach ($all as &$r) {
        if ($r['id'] !== $id) continue;
        if ($r['stato'] !== 'pending') { json_response(['error' => 'Solo richieste in attesa possono essere rifiutate'], 400); break 2; }
        $r['stato']         = 'rejected';
        $r['note_hr']       = $note_hr;
        $r['aggiornato_il'] = date('c');
        $r['aggiornato_da'] = get_user_id();
        $found = true; break;
    }
    unset($r);
    if (!$found) { json_response(['error' => 'Richiesta non trovata'], 404); break; }
    lr_save(REQUESTS_FILE, array_values($all));
    json_response(['ok' => true]);
    break;
  }

  default:
    json_response(['error' => 'Azione non valida'], 400);
}
