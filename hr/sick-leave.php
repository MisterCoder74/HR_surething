<?php
define('ROOT', __DIR__ . '/..');
define('DATA_DIR', ROOT . '/data');
require_once ROOT . '/auth.php';
require_hr();

$emp_file = DATA_DIR . '/employees/employees.json';
$emp_map  = [];
if (file_exists($emp_file)) {
    $emps = json_decode(file_get_contents($emp_file), true) ?? [];
    foreach ($emps as $e) {
        $emp_map[$e['employee_id']] = ($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '');
    }
}
?><!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  <title>Malattie - Mini HR</title>
  <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="app-layout">
  <?php include ROOT . '/partials/sidebar_hr.php'; ?>
  <header class="topbar">
    <span class="topbar-title">Malattie</span>
    <div class="topbar-user">
      <span><?= htmlspecialchars(get_user_id()) ?></span>
      <div class="avatar">HR</div>
      <a href="../logout.php" class="btn btn-secondary btn-sm">Esci</a>
    </div>
  </header>
  <main class="main-content">
    <div class="page-header"><h1>Gestione Malattie</h1></div>

    <!-- Counters -->
    <div style="display:flex;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap">
      <div class="card" style="flex:1;min-width:120px;text-align:center;padding:.75rem 1rem">
        <div style="font-size:.75rem;color:var(--text-light);text-transform:uppercase;letter-spacing:.04em">Attivi</div>
        <div id="cnt-active" style="font-size:1.6rem;font-weight:700;color:#ef4444">—</div>
      </div>
      <div class="card" style="flex:1;min-width:120px;text-align:center;padding:.75rem 1rem">
        <div style="font-size:.75rem;color:var(--text-light);text-transform:uppercase;letter-spacing:.04em">Giorni (anno)</div>
        <div id="cnt-days" style="font-size:1.6rem;font-weight:700;color:#6366f1">—</div>
      </div>
      <div class="card" style="flex:1;min-width:120px;text-align:center;padding:.75rem 1rem">
        <div style="font-size:.75rem;color:var(--text-light);text-transform:uppercase;letter-spacing:.04em">Cert. mancanti</div>
        <div id="cnt-missing" style="font-size:1.6rem;font-weight:700;color:#f59e0b">—</div>
      </div>
      <div class="card" style="flex:1;min-width:120px;text-align:center;padding:.75rem 1rem">
        <div style="font-size:.75rem;color:var(--text-light);text-transform:uppercase;letter-spacing:.04em">Da ricevere</div>
        <div id="cnt-uploaded" style="font-size:1.6rem;font-weight:700;color:#3b82f6">—</div>
      </div>
    </div>

    <!-- Filters + table -->
    <div class="card">
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;flex-wrap:wrap">
        <select id="_fil-stato" class="form-control" style="width:auto;padding:.3rem .6rem;font-size:.85rem">
          <option value="">Tutti gli stati</option>
          <option value="active">Attivi</option>
          <option value="closed">Chiusi</option>
          <option value="cancelled">Annullati</option>
        </select>
        <select id="_fil-emp" class="form-control" style="width:auto;padding:.3rem .6rem;font-size:.85rem">
          <option value="">Tutti i dipendenti</option>
          <?php foreach ($emp_map as $eid => $name): ?>
            <option value="<?= htmlspecialchars($eid) ?>"><?= htmlspecialchars(trim($name)) ?></option>
          <?php endforeach; ?>
        </select>
        <select id="_fil-doc" class="form-control" style="width:auto;padding:.3rem .6rem;font-size:.85rem">
          <option value="">Tutti i cert.</option>
          <option value="missing">Mancanti</option>
          <option value="uploaded">Caricati</option>
          <option value="received">Ricevuti</option>
        </select>
      </div>
      <div id="sl-table">
        <div style="text-align:center;padding:2rem;color:var(--text-light)">Caricamento…</div>
      </div>
    </div>
  </main>
</div>
<script src="../app.js?v=<?php echo time(); ?>"></script>
<script>
const API  = '../api/sick-leave.php';
const EMAP = <?= json_encode($emp_map, JSON_UNESCAPED_UNICODE) ?>;

async function loadCounters() {
  try {
    const d   = await apiFetch(API + '?action=list');
    const all = d.records ?? [];
    const cur = new Date().getFullYear().toString();
    document.getElementById('cnt-active').textContent   = all.filter(r=>r.stato==='active').length;
    document.getElementById('cnt-days').textContent     = all.filter(r=>r.stato!=='cancelled'&&r.data_inizio.startsWith(cur)).reduce((s,r)=>s+(r.giorni||0),0);
    document.getElementById('cnt-missing').textContent  = all.filter(r=>r.stato==='active'&&r.doc_status==='missing').length;
    document.getElementById('cnt-uploaded').textContent = all.filter(r=>r.stato==='active'&&r.doc_status==='uploaded').length;
  } catch(_) {}
}

async function load() {
  const stato = document.getElementById('_fil-stato').value;
  const emp   = document.getElementById('_fil-emp').value;
  const doc   = document.getElementById('_fil-doc').value;
  let url = API + '?action=list';
  if (stato) url += '&stato='       + encodeURIComponent(stato);
  if (emp)   url += '&employee_id=' + encodeURIComponent(emp);
  if (doc)   url += '&doc_status='  + encodeURIComponent(doc);
  const tbl = document.getElementById('sl-table');
  try {
    const d = await apiFetch(url);
    renderTable(d.records ?? [], tbl);
  } catch(e) {
    tbl.innerHTML = `<div style="color:var(--danger);padding:1rem">Errore: ${escapeHtml(e.data?.error??'')}</div>`;
  }
}

function renderTable(recs, tbl) {
  if (!recs.length) {
    tbl.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--text-light)">Nessun record trovato</div>';
    return;
  }
  let html = `<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse">
    <thead><tr style="border-bottom:2px solid var(--border);font-size:.82rem;color:var(--text-light)">
      <th style="padding:.5rem .75rem;text-align:left">Dipendente</th>
      <th style="padding:.5rem .75rem;text-align:left">Dal</th>
      <th style="padding:.5rem .75rem;text-align:left">Al</th>
      <th style="padding:.5rem .75rem;text-align:left">Giorni</th>
      <th style="padding:.5rem .75rem;text-align:left">Medico</th>
      <th style="padding:.5rem .75rem;text-align:left">Certificato</th>
      <th style="padding:.5rem .75rem;text-align:left">Stato</th>
      <th style="padding:.5rem .75rem;text-align:left">Note HR</th>
      <th style="padding:.5rem .75rem;text-align:left">Azioni</th>
    </tr></thead><tbody>`;
  for (const r of recs) {
    const emp     = escapeHtml((EMAP[r.employee_id] ?? r.employee_id).trim());
    const docBadge = docStatusBadge(r.doc_status);
    html += `<tr style="border-bottom:1px solid var(--border);font-size:.85rem">
      <td style="padding:.5rem .75rem;font-weight:500">${emp}</td>
      <td style="padding:.5rem .75rem">${formatDate(r.data_inizio)}</td>
      <td style="padding:.5rem .75rem">${r.data_fine !== r.data_inizio ? formatDate(r.data_fine) : '—'}</td>
      <td style="padding:.5rem .75rem">${r.giorni} gg</td>
      <td style="padding:.5rem .75rem;color:var(--text-light)">${escapeHtml(r.medico||'—')}</td>
      <td style="padding:.5rem .75rem">${docBadge}</td>
      <td style="padding:.5rem .75rem">${statusBadge(r.stato)}</td>
      <td style="padding:.5rem .75rem;color:var(--text-light);font-size:.8rem;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escapeHtml(r.note_hr||'—')}</td>
      <td style="padding:.5rem .75rem;white-space:nowrap;display:flex;gap:.3rem;flex-wrap:wrap">
        ${r.certificato ? `<a class="btn btn-secondary btn-sm" href="${API}?action=download_cert&id=${encodeURIComponent(r.id)}" target="_blank">&#128196; Cert.</a>` : ''}
        ${r.stato==='active'&&r.doc_status==='uploaded' ? `<button class="btn btn-success btn-sm" onclick="markReceived('${r.id}','${emp}')">&#10003; Ricevuto</button>` : ''}
        ${r.stato==='active' ? `<button class="btn btn-secondary btn-sm" onclick="openClose('${r.id}','${emp}')">&#9744; Chiudi</button>` : ''}
      </td>
    </tr>`;
  }
  html += '</tbody></table></div>';
  tbl.innerHTML = html;
}

/* ── Mark received ───────────────────────────────────────────── */
function markReceived(id, empName) {
  showModal(`
    <div class="modal-header">
      <span class="modal-title">&#10003; Conferma ricezione certificato</span>
      <button class="modal-close">&#x2715;</button>
    </div>
    <div class="modal-body">
      <p style="margin-bottom:1rem">Confermare la ricezione del certificato di <strong>${empName}</strong>?</p>
      <div class="form-group">
        <label class="form-label">Nota HR <small style="font-weight:400;color:var(--text-light)">(opzionale)</small></label>
        <input type="text" class="form-control" id="_mr-note" placeholder="es. Certificato INPS ricevuto">
      </div>
      <div id="_mr-err" style="color:var(--danger);font-size:.85rem;display:none"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-dismiss="modal">Annulla</button>
      <button class="btn btn-success" id="_mr-btn">Conferma</button>
    </div>
  `);
  document.getElementById('_mr-btn').addEventListener('click', async () => {
    const note = document.getElementById('_mr-note').value.trim();
    const btn  = document.getElementById('_mr-btn');
    const err  = document.getElementById('_mr-err');
    err.style.display = 'none';
    setLoading(btn, true);
    try {
      await apiFetch(API + '?action=mark_received', 'POST', { id, note_hr: note });
      closeModal();
      showToast('Certificato segnato come ricevuto ✓', 'success');
      loadCounters(); load();
    } catch(e) { err.textContent = e.data?.error ?? 'Errore'; err.style.display = ''; setLoading(btn, false); }
  });
}

/* ── Close sick leave ────────────────────────────────────────── */
function openClose(id, empName) {
  showModal(`
    <div class="modal-header">
      <span class="modal-title">&#9744; Chiudi periodo malattia</span>
      <button class="modal-close">&#x2715;</button>
    </div>
    <div class="modal-body">
      <p style="margin-bottom:1rem">Chiudere il periodo di malattia di <strong>${empName}</strong>?<br><small style="color:var(--text-light)">Il dipendente è rientrato al lavoro.</small></p>
      <div class="form-group">
        <label class="form-label">Nota HR <small style="font-weight:400;color:var(--text-light)">(opzionale)</small></label>
        <input type="text" class="form-control" id="_cl-note" placeholder="es. Rientro il 19/05">
      </div>
      <div id="_cl-err" style="color:var(--danger);font-size:.85rem;display:none"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-dismiss="modal">Annulla</button>
      <button class="btn btn-primary" id="_cl-btn">Chiudi periodo</button>
    </div>
  `);
  document.getElementById('_cl-btn').addEventListener('click', async () => {
    const note = document.getElementById('_cl-note').value.trim();
    const btn  = document.getElementById('_cl-btn');
    const err  = document.getElementById('_cl-err');
    err.style.display = 'none';
    setLoading(btn, true);
    try {
      await apiFetch(API + '?action=close', 'POST', { id, note_hr: note });
      closeModal();
      showToast('Periodo malattia chiuso', 'info');
      loadCounters(); load();
    } catch(e) { err.textContent = e.data?.error ?? 'Errore'; err.style.display = ''; setLoading(btn, false); }
  });
}

['_fil-stato','_fil-emp','_fil-doc'].forEach(id =>
  document.getElementById(id).addEventListener('change', load)
);

loadCounters();
load();
</script>
</body>
</html>
