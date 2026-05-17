<?php
define('ROOT', __DIR__.'/..');
define('DATA_DIR', ROOT.'/data');
require_once ROOT.'/auth.php';
require_once __DIR__.'/json_helper.php';
api_require_login();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

define('SW_FILE', DATA_DIR . '/smartworking/requests.json');

/* ── helpers ─────────────────────────────────────────────────── */
function sw_load(): array {
    if (!file_exists(SW_FILE)) return [];
    $d = json_decode(file_get_contents(SW_FILE), true);
    return is_array($d) ? $d : [];
}

function sw_save(array $data): void {
    $dir = dirname(SW_FILE);
    if (!is_dir($dir)) mkdir($dir, 0770, true);
    file_put_contents(SW_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function sw_wd(string $start, string $end): int {
    $n = 0; $c = new DateTime($start); $e = new DateTime($end);
    while ($c <= $e) { if ((int)$c->format('N') < 6) $n++; $c->modify('+1 day'); }
    return $n;
}

function sw_json(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!file_exists(SW_FILE)) sw_save([]);

/* ── routing ─────────────────────────────────────────────────── */
switch ($action) {

  /* LIST ─────────────────────────────────────────────────────── */
  case 'list': {
    $all = sw_load();
    if (is_employee()) {
        $all = array_values(array_filter($all, fn($r) => $r['employee_id'] === get_employee_id()));
    } else {
        api_require_hr();
        if ($f = ($_GET['stato']       ?? '')) $all = array_values(array_filter($all, fn($r) => $r['stato']       === $f));
        if ($f = ($_GET['employee_id'] ?? '')) $all = array_values(array_filter($all, fn($r) => $r['employee_id'] === $f));
    }
    // newest first
    usort($all, fn($a,$b) => strcmp($b['creato_il'], $a['creato_il']));
    sw_json(200, ['requests' => $all]);
  }

  /* SUBMIT (employee) ─────────────────────────────────────────── */
  case 'submit': {
    api_require_employee();
    $body       = json_decode(file_get_contents('php://input'), true) ?? [];
    $data_inizio = trim($body['data_inizio'] ?? '');
    $data_fine   = trim($body['data_fine']   ?? '') ?: $data_inizio;
    $motivo      = trim($body['motivo']      ?? '');

    // Basic validation
    if (!$data_inizio)          sw_json(400, ['error' => 'Data inizio obbligatoria']);
    if ($data_fine < $data_inizio) sw_json(400, ['error' => 'Data fine non può precedere la data inizio']);

    // Min 1 working day notice
    $today   = (new DateTime())->setTime(0,0,0);
    $reqDate = new DateTime($data_inizio);
    $diff    = (int)$today->diff($reqDate)->format('%r%a');
    if ($diff < 1) sw_json(400, ['error' => 'Lo smartworking deve essere richiesto con almeno 1 giorno lavorativo di preavviso']);

    $giorni = sw_wd($data_inizio, $data_fine);
    if ($giorni < 1) sw_json(400, ['error' => 'Nessun giorno lavorativo nel periodo selezionato']);

    $all  = sw_load();
    $empId = get_employee_id();

    // Overlap check (pending or approved)
    foreach ($all as $r) {
        if ($r['employee_id'] !== $empId) continue;
        if (!in_array($r['stato'], ['pending','approved'])) continue;
        if ($r['data_inizio'] <= $data_fine && $r['data_fine'] >= $data_inizio) {
            sw_json(409, ['error' => 'Hai già una richiesta di smartworking che copre questo periodo']);
        }
    }

    $req = [
        'id'          => 'sw_' . time() . '_' . $empId,
        'employee_id' => $empId,
        'data_inizio' => $data_inizio,
        'data_fine'   => $data_fine,
        'giorni'      => $giorni,
        'motivo'      => $motivo,
        'stato'       => 'pending',
        'note_hr'     => '',
        'creato_il'   => (new DateTime())->format('Y-m-d\TH:i:s'),
        'aggiornato_il' => (new DateTime())->format('Y-m-d\TH:i:s'),
    ];
    $all[] = $req;
    sw_save($all);
    sw_json(201, ['success' => true, 'id' => $req['id']]);
  }

  /* CANCEL (employee own pending) ─────────────────────────────── */
  case 'cancel': {
    api_require_employee();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = trim($body['id'] ?? '');
    if (!$id) sw_json(400, ['error' => 'ID richiesta mancante']);

    $all = sw_load();
    $idx = array_search($id, array_column($all, 'id'));
    if ($idx === false) sw_json(404, ['error' => 'Richiesta non trovata']);
    if ($all[$idx]['employee_id'] !== get_employee_id()) sw_json(403, ['error' => 'Non autorizzato']);
    if ($all[$idx]['stato'] !== 'pending') sw_json(409, ['error' => 'Solo le richieste in attesa possono essere annullate']);

    $all[$idx]['stato']         = 'cancelled';
    $all[$idx]['aggiornato_il'] = (new DateTime())->format('Y-m-d\TH:i:s');
    sw_save($all);
    sw_json(200, ['success' => true]);
  }

  /* APPROVE (HR) ──────────────────────────────────────────────── */
  case 'approve': {
    api_require_hr();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = trim($body['id']      ?? '');
    $note = trim($body['note_hr'] ?? '');
    if (!$id) sw_json(400, ['error' => 'ID richiesta mancante']);

    $all = sw_load();
    $idx = array_search($id, array_column($all, 'id'));
    if ($idx === false) sw_json(404, ['error' => 'Richiesta non trovata']);
    if ($all[$idx]['stato'] !== 'pending') sw_json(409, ['error' => 'Solo le richieste in attesa possono essere approvate']);

    $all[$idx]['stato']         = 'approved';
    $all[$idx]['note_hr']       = $note;
    $all[$idx]['aggiornato_il'] = (new DateTime())->format('Y-m-d\TH:i:s');
    sw_save($all);
    sw_json(200, ['success' => true]);
  }

  /* REJECT (HR) ───────────────────────────────────────────────── */
  case 'reject': {
    api_require_hr();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = trim($body['id']      ?? '');
    $note = trim($body['note_hr'] ?? '');
    if (!$id)   sw_json(400, ['error' => 'ID richiesta mancante']);
    if (!$note) sw_json(400, ['error' => 'Motivazione rifiuto obbligatoria']);

    $all = sw_load();
    $idx = array_search($id, array_column($all, 'id'));
    if ($idx === false) sw_json(404, ['error' => 'Richiesta non trovata']);
    if ($all[$idx]['stato'] !== 'pending') sw_json(409, ['error' => 'Solo le richieste in attesa possono essere rifiutate']);

    $all[$idx]['stato']         = 'rejected';
    $all[$idx]['note_hr']       = $note;
    $all[$idx]['aggiornato_il'] = (new DateTime())->format('Y-m-d\TH:i:s');
    sw_save($all);
    sw_json(200, ['success' => true]);
  }

  default:
    sw_json(400, ['error' => "Azione non riconosciuta: $action"]);
}
