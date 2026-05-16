<?php
define('ROOT', __DIR__ . '/..');
define('DATA_DIR', ROOT . '/data');
require_once ROOT . '/auth.php';
require_hr();

// Load employee name map for display
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
  <title>Richieste - Mini HR</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-layout">
  <?php include ROOT . '/partials/sidebar_hr.php'; ?>
  <header class="topbar">
    <span class="topbar-title">Richieste ferie &amp; permessi</span>
    <div class="topbar-user">
      <span><?= htmlspecialchars(get_user_id()) ?></span>
      <div class="avatar">HR</div>
      <a href="../logout.php" class="btn btn-secondary btn-sm">Esci</a>
    </div>
  </header>
  <main class="main-content">
    <div class="page-header"><h1>Richieste ferie &amp; permessi</h1></div>

    <!-- Summary counters -->
    <div style="display:flex;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap">
      <div class="card" style="flex:1;min-width:120px;text-align:center;padding:.75rem 1rem">
        <div style="font-size:.75rem;color:var(--text-light);text-transform:uppercase;letter-spacing:.04em">In attesa</div>
        <div id="cnt-pending" style="font-size:1.6rem;font-weight:700;color:#f59e0b">—</div>
      </div>
      <div class="card" style="flex:1;min-width:120px;text-align:center;padding:.75rem 1rem">
        <div style="font-size:.75rem;color:var(--text-light);text-transform:uppercase;letter-spacing:.04em">Approvate</div>
        <div id="cnt-approved" style="font-size:1.6rem;font-weight:700;color:#22c55e">—</div>
      </div>
      <div class="card" style="flex:1;min-width:120px;text-align:center;padding:.75rem 1rem">
        <div style="font-size:.75rem;color:var(--text-light);text-transform:uppercase;letter-spacing:.04em">Rifiutate</div>
        <div id="cnt-rejected" style="font-size:1.6rem;font-weight:700;color:#ef4444">—</div>
      </div>
    </div>

    <!-- Filters + table -->
    <div class="card">
      <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;flex-wrap:wrap">
        <select id="_fil-stato" class="form-control" style="width:auto;padding:.3rem .6rem;font-size:.85rem">
          <option value="">Tutti gli stati</option>
          <option value="pending">In attesa</option>
          <option value="approved">Approvate</option>
          <option value="rejected">Rifiutate</option>
          <option value="cancelled">Annullate</option>
        </select>
        <select id="_fil-tipo" class="form-control" style="width:auto;padding:.3rem .6rem;font-size:.85rem">
          <option value="">Tutti i tipi</option>
          <option value="ferie">Ferie</option>
          <option value="permesso">Permesso</option>
        </select>
        <select id="_fil-emp" class="form-control" style="width:auto;padding:.3rem .6rem;font-size:.85rem">
          <option value="">Tutti i dipendenti</option>
          <?php foreach ($emp_map as $eid => $name): ?>
            <option value="<?= htmlspecialchars($eid) ?>"><?= htmlspecialchars(trim($name)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div id="requests-table">
        <div style="text-align:center;padding:2rem;color:var(--text-light)">Caricamento…</div>
      </div>
    </div>
  </main>
</div>
<script src="../app.js"></script>
<script>
const API  = '../api/leave-requests.php';
const EMAP = <?= json_encode($emp_map, JSON_UNESCAPED_UNICODE) ?>;

async function load() {
  const stato = document.getElementById('_fil-stato').value;
  const tipo  = document.getElementById('_fil-tipo').value;
  const emp   = document.getElementById('_fil-emp').value;
  let url = API + '?action=list';
  if (stato) url += '&stato='       + encodeURIComponent(stato);
  if (tipo)  url += '&tipo='        + encodeURIComponent(tipo);
  if (emp)   url += '&employee_id=' + encodeURIComponent(emp);
  try {
    const d = await apiFetch(url);
    renderTable(d.requests ?? []);
  } catch(e) {
    document.getElementById('requests-table').innerHTML =
      `<div style="color:var(--danger);padding:1rem">Errore: ${escapeHtml(e.data?.error??'')}</div>`;
  }
}

async function loadCounters() {
  try {
    const d   = await apiFetch(API + '?action=list');
    const all = d.requests ?? [];
    document.getElementById('cnt-pending').textContent  = all.filter(r=>r.stato==='pending').length;
    document.getElementById('cnt-approved').textContent = all.filter(r=>r.stato==='approved').length;
    document.getElementById('cnt-rejected').textContent = all.filter(r=>r.stato==='rejected').length;
  } catch(_) {}
}

function renderTable(reqs) {
  const tbl = document.getElementById('requests-table');
  if (!reqs.length) {
    tbl.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--text-light)">Nessuna richiesta trovata</div>';
    return;
  }
  let html = `<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse">
    <thead><tr style="border-bottom:2px solid var(--border);font-size:.82rem;color:var(--text-light)">
      <th style="padding:.5rem .75rem;text-align:left">Dipendente</th>
      <th style="padding:.5rem .75rem;text-align:left">Tipo</th>
      <th style="padding:.5rem .75rem;text-align:left">Dal</th>
      <th style="padding:.5rem .75rem;text-align:left">Al</th>
      <th style="padding:.5rem .75rem;text-align:left">Gg/Ore</th>
      <th style="padding:.5rem .75rem;text-align:left">Motivo</th>
      <th style="padding:.5rem .75rem;text-align:left">Inviata il</th>
      <th style="padding:.5rem .75rem;text-align:left">Stato</th>
      <th style="padding:.5rem .75rem;text-align:left">Note HR</th>
      <th style="padding:.5rem .75rem;text-align:left">Azioni</th>
    </tr></thead><tbody>`;
  for (const r of reqs) {
    const emp  = escapeHtml((EMAP[r.employee_id] ?? r.employee_id).trim());
    const tl   = r.tipo === 'ferie' ? '&#9728;&#65039; Ferie' : '&#128336; Permesso';
    const qty  = r.tipo === 'ferie' ? r.giorni + ' gg' : r.ore + 'h';
    const fine = r.tipo === 'ferie' ? formatDate(r.data_fine) : '—';
    html += `<tr style="border-bottom:1px solid var(--border);font-size:.85rem">
      <td style="padding:.5rem .75rem;font-weight:500">${emp}</td>
      <td style="padding:.5rem .75rem">${tl}</td>
      <td style="padding:.5rem .75rem">${formatDate(r.data_inizio)}</td>
      <td style="padding:.5rem .75rem">${fine}</td>
      <td style="padding:.5rem .75rem">${qty}</td>
      <td style="padding:.5rem .75rem;color:var(--text-light);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escapeHtml(r.motivo||'—')}</td>
      <td style="padding:.5rem .75rem;color:var(--text-light);font-size:.8rem">${formatDate(r.creato_il)}</td>
      <td style="padding:.5rem .75rem">${statusBadge(r.stato)}</td>
      <td style="padding:.5rem .75rem;color:var(--text-light);font-size:.8rem;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escapeHtml(r.note_hr||'—')}</td>
      <td style="padding:.5rem .75rem;white-space:nowrap">
        ${r.stato==='pending' ? `
          <button class="btn btn-success btn-sm" style="margin-right:.3rem" onclick="openApprove('${r.id}','${emp}')">&#10003; Approva</button>
          <button class="btn btn-danger btn-sm" onclick="openReject('${r.id}','${emp}')">&#x2715; Rifiuta</button>
        ` : ''}
      </td>
    </tr>`;
  }
  html += '</tbody></table></div>';
  tbl.innerHTML = html;
}

/* ── Approve modal ───────────────────────────────────────────── */
function openApprove(id, empName) {
  showModal(`
    <div class="modal-header">
      <span class="modal-title">&#10003; Approva richiesta</span>
      <button class="modal-close">&#x2715;</button>
    </div>
    <div class="modal-body">
      <p style="margin-bottom:1rem">Approvare la richiesta di <strong>${empName}</strong>?</p>
      <div class="form-group">
        <label class="form-label">Nota HR <small style="font-weight:400;color:var(--text-light)">(opzionale)</small></label>
        <input type="text" class="form-control" id="_app-note" placeholder="es. Approvato, buone vacanze!">
      </div>
      <div id="_app-err" style="color:var(--danger);font-size:.85rem;display:none"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-dismiss="modal">Annulla</button>
      <button class="btn btn-success" id="_app-btn">Conferma approvazione</button>
    </div>
  `);
  document.getElementById('_app-btn').addEventListener('click', async () => {
    const note = document.getElementById('_app-note').value.trim();
    const btn  = document.getElementById('_app-btn');
    const err  = document.getElementById('_app-err');
    err.style.display = 'none';
    setLoading(btn, true);
    try {
      await apiFetch(API + '?action=approve', 'POST', { id, note_hr: note });
      closeModal();
      showToast('Richiesta approvata ✓', 'success');
      loadCounters(); load();
    } catch(e) { err.textContent = e.data?.error ?? 'Errore'; err.style.display = ''; setLoading(btn, false); }
  });
}

/* ── Reject modal ────────────────────────────────────────────── */
function openReject(id, empName) {
  showModal(`
    <div class="modal-header">
      <span class="modal-title">&#x2715; Rifiuta richiesta</span>
      <button class="modal-close">&#x2715;</button>
    </div>
    <div class="modal-body">
      <p style="margin-bottom:1rem">Rifiutare la richiesta di <strong>${empName}</strong>?</p>
      <div class="form-group">
        <label class="form-label">Motivazione rifiuto <span style="color:var(--danger)">*</span></label>
        <input type="text" class="form-control" id="_rej-note" placeholder="es. Periodo già occupato, ripianifica...">
      </div>
      <div id="_rej-err" style="color:var(--danger);font-size:.85rem;display:none"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-dismiss="modal">Annulla</button>
      <button class="btn btn-danger" id="_rej-btn">Conferma rifiuto</button>
    </div>
  `);
  document.getElementById('_rej-btn').addEventListener('click', async () => {
    const note = document.getElementById('_rej-note').value.trim();
    const btn  = document.getElementById('_rej-btn');
    const err  = document.getElementById('_rej-err');
    err.style.display = 'none';
    if (!note) { err.textContent = 'Motivazione obbligatoria'; err.style.display = ''; return; }
    setLoading(btn, true);
    try {
      await apiFetch(API + '?action=reject', 'POST', { id, note_hr: note });
      closeModal();
      showToast('Richiesta rifiutata', 'info');
      loadCounters(); load();
    } catch(e) { err.textContent = e.data?.error ?? 'Errore'; err.style.display = ''; setLoading(btn, false); }
  });
}

['_fil-stato','_fil-tipo','_fil-emp'].forEach(id =>
  document.getElementById(id).addEventListener('change', load)
);

loadCounters();
load();
</script>
</body>
</html>
