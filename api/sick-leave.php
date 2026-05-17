<?php
define('ROOT', __DIR__ . '/..');
define('DATA_DIR', ROOT . '/data');
require_once ROOT . '/auth.php';
require_once __DIR__ . '/json_helper.php';
api_require_login();

define('SL_FILE',    DATA_DIR . '/sick_leave/records.json');
define('CERTS_DIR',  DATA_DIR . '/sick_certs');

// Allowed MIME types for certificates
const ALLOWED_MIME = ['application/pdf','image/jpeg','image/png'];
const ALLOWED_EXT  = ['pdf','jpg','jpeg','png'];
const MAX_SIZE     = 5 * 1024 * 1024; // 5 MB

/* ── helpers ─────────────────────────────────────────────────── */
function sl_load(): array {
    if (!file_exists(SL_FILE)) return [];
    $d = json_decode(file_get_contents(SL_FILE), true);
    return is_array($d) ? $d : [];
}

function sl_save(array $data): void {
    $dir = dirname(SL_FILE);
    if (!is_dir($dir)) mkdir($dir, 0770, true);
    file_put_contents(SL_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function sl_wd(string $start, string $end): int {
    $n = 0; $c = new DateTime($start); $e = new DateTime($end);
    while ($c <= $e) { if ((int)$c->format('N') < 6) $n++; $c->modify('+1 day'); }
    return $n;
}

function sl_json(int $code, array $body): void {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function sl_handle_upload(array $file, string $empId, string $recId): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > MAX_SIZE) sl_json(400, ['error' => 'File troppo grande (max 5 MB)']);

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ALLOWED_MIME)) sl_json(415, ['error' => 'Tipo file non supportato (PDF, JPG, PNG)']);

    $ext = match($mime) {
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        default           => 'bin'
    };

    if (!is_dir(CERTS_DIR)) mkdir(CERTS_DIR, 0770, true);
    $filename = preg_replace('/[^a-z0-9_]/', '', strtolower($empId)) . '_' . $recId . '.' . $ext;
    $dest     = CERTS_DIR . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) sl_json(500, ['error' => 'Errore salvataggio file']);
    return $filename;
}

if (!file_exists(SL_FILE)) sl_save([]);
if (!is_dir(CERTS_DIR)) { mkdir(CERTS_DIR, 0770, true); }

// Write .htaccess to protect certs dir
$htaccess = CERTS_DIR . '/.htaccess';
if (!file_exists($htaccess)) file_put_contents($htaccess, "Require all denied\n");

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

/* ── routing ─────────────────────────────────────────────────── */
switch ($action) {

  /* LIST ─────────────────────────────────────────────────────── */
  case 'list': {
    $all = sl_load();
    if (is_employee()) {
        $all = array_values(array_filter($all, fn($r) => $r['employee_id'] === get_employee_id()));
    } else {
        api_require_hr();
        if ($f = ($_GET['stato']       ?? '')) $all = array_values(array_filter($all, fn($r) => $r['stato']      === $f));
        if ($f = ($_GET['employee_id'] ?? '')) $all = array_values(array_filter($all, fn($r) => $r['employee_id']=== $f));
        if ($f = ($_GET['doc_status']  ?? '')) $all = array_values(array_filter($all, fn($r) => $r['doc_status'] === $f));
    }
    usort($all, fn($a,$b) => strcmp($b['data_inizio'], $a['data_inizio']));
    sl_json(200, ['records' => $all]);
  }

  /* SUBMIT (employee) ─────────────────────────────────────────── */
  case 'submit': {
    api_require_employee();
    $empId      = get_employee_id();
    $dataInizio = trim($_POST['data_inizio'] ?? '');
    $dataFine   = trim($_POST['data_fine']   ?? '') ?: $dataInizio;
    $medico     = trim($_POST['medico']      ?? '');

    if (!$dataInizio)              sl_json(400, ['error' => 'Data inizio obbligatoria']);
    if ($dataFine < $dataInizio)   sl_json(400, ['error' => 'Data fine non può precedere la data inizio']);

    // Future dates not allowed (sick leave is declared when already ill)
    $today = (new DateTime())->setTime(0,0,0);
    $start = (new DateTime($dataInizio))->setTime(0,0,0);
    if ($start > $today) sl_json(400, ['error' => 'La data inizio non può essere futura']);

    $giorni = sl_wd($dataInizio, $dataFine);
    if ($giorni < 1) sl_json(400, ['error' => 'Nessun giorno lavorativo nel periodo']);

    // Overlap check
    $all = sl_load();
    foreach ($all as $r) {
        if ($r['employee_id'] !== $empId || $r['stato'] !== 'active') continue;
        if ($r['data_inizio'] <= $dataFine && $r['data_fine'] >= $dataInizio) {
            sl_json(409, ['error' => 'Hai già un periodo di malattia che si sovrappone']);
        }
    }

    $recId = 'sl_' . time() . '_' . $empId;
    $cert  = null;

    if (!empty($_FILES['certificato']['name'])) {
        $cert = sl_handle_upload($_FILES['certificato'], $empId, $recId);
    }

    $rec = [
        'id'          => $recId,
        'employee_id' => $empId,
        'data_inizio' => $dataInizio,
        'data_fine'   => $dataFine,
        'giorni'      => $giorni,
        'medico'      => $medico,
        'certificato' => $cert,
        'stato'       => 'active',
        'doc_status'  => $cert ? 'uploaded' : 'missing',
        'note_hr'     => '',
        'creato_il'   => (new DateTime())->format('Y-m-d\TH:i:s'),
        'aggiornato_il' => (new DateTime())->format('Y-m-d\TH:i:s'),
    ];
    $all[] = $rec;
    sl_save($all);
    sl_json(201, ['success' => true, 'id' => $recId, 'doc_status' => $rec['doc_status']]);
  }

  /* CANCEL (employee — only if start date is today or future) ── */
  case 'cancel': {
    api_require_employee();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = trim($body['id'] ?? '');
    if (!$id) sl_json(400, ['error' => 'ID mancante']);

    $all = sl_load();
    $idx = array_search($id, array_column($all, 'id'));
    if ($idx === false)                                sl_json(404, ['error' => 'Record non trovato']);
    if ($all[$idx]['employee_id'] !== get_employee_id()) sl_json(403, ['error' => 'Non autorizzato']);
    if ($all[$idx]['stato'] !== 'active')              sl_json(409, ['error' => 'Il record non è attivo']);

    $today = (new DateTime())->setTime(0,0,0);
    $start = (new DateTime($all[$idx]['data_inizio']))->setTime(0,0,0);
    if ($start < $today) sl_json(409, ['error' => 'Non puoi annullare una malattia già iniziata']);

    // Delete cert if present
    if ($all[$idx]['certificato']) {
        $cf = CERTS_DIR . '/' . $all[$idx]['certificato'];
        if (file_exists($cf)) @unlink($cf);
    }
    $all[$idx]['stato']         = 'cancelled';
    $all[$idx]['aggiornato_il'] = (new DateTime())->format('Y-m-d\TH:i:s');
    sl_save($all);
    sl_json(200, ['success' => true]);
  }

  /* UPLOAD_CERT (employee attaches/replaces certificate) ──────── */
  case 'upload_cert': {
    api_require_employee();
    $id = trim($_POST['id'] ?? '');
    if (!$id) sl_json(400, ['error' => 'ID mancante']);
    if (empty($_FILES['certificato']['name'])) sl_json(400, ['error' => 'Nessun file ricevuto']);

    $all = sl_load();
    $idx = array_search($id, array_column($all, 'id'));
    if ($idx === false)                                sl_json(404, ['error' => 'Record non trovato']);
    if ($all[$idx]['employee_id'] !== get_employee_id()) sl_json(403, ['error' => 'Non autorizzato']);
    if ($all[$idx]['stato'] !== 'active')              sl_json(409, ['error' => 'Il record non è attivo']);

    // Remove old cert
    if ($all[$idx]['certificato']) {
        $old = CERTS_DIR . '/' . $all[$idx]['certificato'];
        if (file_exists($old)) @unlink($old);
    }

    $cert = sl_handle_upload($_FILES['certificato'], $all[$idx]['employee_id'], $id);
    $all[$idx]['certificato']   = $cert;
    $all[$idx]['doc_status']    = 'uploaded';
    $all[$idx]['aggiornato_il'] = (new DateTime())->format('Y-m-d\TH:i:s');
    sl_save($all);
    sl_json(200, ['success' => true, 'doc_status' => 'uploaded']);
  }

  /* MARK_RECEIVED (HR confirms physical/digital receipt) ──────── */
  case 'mark_received': {
    api_require_hr();
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $id      = trim($body['id']      ?? '');
    $note_hr = trim($body['note_hr'] ?? '');
    if (!$id) sl_json(400, ['error' => 'ID mancante']);

    $all = sl_load();
    $idx = array_search($id, array_column($all, 'id'));
    if ($idx === false) sl_json(404, ['error' => 'Record non trovato']);

    $all[$idx]['doc_status']    = 'received';
    $all[$idx]['note_hr']       = $note_hr;
    $all[$idx]['aggiornato_il'] = (new DateTime())->format('Y-m-d\TH:i:s');
    sl_save($all);
    sl_json(200, ['success' => true]);
  }

  /* CLOSE (HR closes sick leave — employee is back) ────────────── */
  case 'close': {
    api_require_hr();
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = trim($body['id']  ?? '');
    $note = trim($body['note_hr'] ?? '');
    if (!$id) sl_json(400, ['error' => 'ID mancante']);

    $all = sl_load();
    $idx = array_search($id, array_column($all, 'id'));
    if ($idx === false) sl_json(404, ['error' => 'Record non trovato']);
    if ($all[$idx]['stato'] !== 'active') sl_json(409, ['error' => 'Il record non è attivo']);

    $all[$idx]['stato']         = 'closed';
    $all[$idx]['note_hr']       = $note ?: $all[$idx]['note_hr'];
    $all[$idx]['aggiornato_il'] = (new DateTime())->format('Y-m-d\TH:i:s');
    sl_save($all);
    sl_json(200, ['success' => true]);
  }

  /* DOWNLOAD_CERT (HR only — serves the file) ──────────────────── */
  case 'download_cert': {
    api_require_hr();
    $id = trim($_GET['id'] ?? '');
    if (!$id) sl_json(400, ['error' => 'ID mancante']);

    $all = sl_load();
    $idx = array_search($id, array_column($all, 'id'));
    if ($idx === false || !$all[$idx]['certificato']) sl_json(404, ['error' => 'Certificato non trovato']);

    $filename = $all[$idx]['certificato'];
    $path     = CERTS_DIR . '/' . $filename;
    if (!file_exists($path)) sl_json(404, ['error' => 'File non trovato sul server']);

    $ext  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mime = match($ext) {
        'pdf'  => 'application/pdf',
        'jpg', 'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        default => 'application/octet-stream'
    };
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="certificato_' . basename($filename) . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    // Remove JSON header set above
    while (ob_get_level()) ob_end_clean();
    readfile($path);
    exit;
  }

  default:
    sl_json(400, ['error' => "Azione non riconosciuta: $action"]);
}
