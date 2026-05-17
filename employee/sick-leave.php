<?php
define('ROOT', __DIR__ . '/..');
define('DATA_DIR', ROOT . '/data');
require_once ROOT . '/auth.php';
require_employee();
?><!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  <title>Malattia - Mini HR</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-layout">
  <?php include ROOT . '/partials/sidebar_emp.php'; ?>
  <header class="topbar">
    <span class="topbar-title">Malattia</span>
    <div class="topbar-user">
      <span><?= htmlspecialchars(get_user_id()) ?></span>
      <div class="avatar">ME</div>
      <a href="../logout.php" class="btn btn-secondary btn-sm">Esci</a>
    </div>
  </header>
  <main class="main-content">
    <div class="page-header"><h1>Malattia</h1></div>

    <!-- Info banner -->
    <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:.75rem 1rem;margin-bottom:1.25rem;font-size:.85rem;color:#92400e;display:flex;gap:.5rem;align-items:flex-start">
      <span>&#8505;&#65039;</span>
      <span>Registra il periodo di assenza per malattia. Carica il <strong>certificato medico</strong> (PDF, JPG o PNG, max 5 MB) il prima possibile — è richiesto dall'HR.</span>
    </div>

    <!-- Stats strip -->
    <div style="display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap">
      <div class="card" style="flex:1;min-width:130px;text-align:center;padding:.75rem 1rem">
        <div style="font-size:.75rem;color:var(--text-light);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.25rem">&#128308; Periodi attivi</div>
        <div id="cnt-active" style="font-size:1.8rem;font-weight:700;color:#ef4444">—</div>
      </div>
      <div class="card" style="flex:1;min-width:130px;text-align:center;padding:.75rem 1rem">
        <div style="font-size:.75rem;color:var(--text-light);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.25rem">&#128197; Giorni totali (anno)</div>
        <div id="cnt-days" style="font-size:1.8rem;font-weight:700;color:#6366f1">—</div>
      </div>
      <div class="card" style="flex:1;min-width:130px;text-align:center;padding:.75rem 1rem">
        <div style="font-size:.75rem;color:var(--text-light);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.25rem">&#128196; Certificati mancanti</div>
        <div id="cnt-missing" style="font-size:1.8rem;font-weight:700;color:#f59e0b">—</div>
      </div>
    </div>

    <!-- New sick leave form -->
    <div class="card" style="margin-bottom:1.5rem">
      <h2 style="font-size:1rem;margin:0 0 1rem">&#129298; Dichiara malattia</h2>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <div class="form-group">
          <label class="form-label">Data inizio</label>
          <input type="date" class="form-control" id="_sl-start">
        </div>
        <div class="form-group">
          <label class="form-label">Data fine <small style="font-weight:400;color:var(--text-light)">(prevista, aggiornabile)</small></label>
          <input type="date" class="form-control" id="_sl-end">
        </div>
      </div>

      <div id="_sl-preview" style="font-size:.82rem;color:var(--text-light);margin-bottom:.75rem"></div>

      <div class="form-group">
        <label class="form-label">Medico <small style="font-weight:400;color:var(--text-light)">(opzionale)</small></label>
        <input type="text" class="form-control" id="_sl-medico" placeholder="es. Dr. Bianchi">
      </div>

      <div class="form-group">
        <label class="form-label">Certificato medico <small style="font-weight:400;color:var(--text-light)">(opzionale — puoi caricarlo anche in seguito)</small></label>
        <input type="file" class="form-control" id="_sl-file" accept=".pdf,.jpg,.jpeg,.png" style="padding:.35rem .5rem">
        <div id="_sl-file-info" style="font-size:.8rem;color:var(--text-light);margin-top:.25rem"></div>
      </div>

      <div id="_sl-err" style="color:var(--danger);font-size:.85rem;margin-bottom:.5rem;display:none"></div>
      <button class="btn btn-primary" id="_sl-submit">&#128228; Registra malattia</button>
    </div>

    <!-- History -->
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem">
        <h2 style="font-size:1rem;margin:0">&#128203; I miei periodi di malattia</h2>
        <select id="_sl-filter" class="form-control" style="width:auto;padding:.3rem .6rem;font-size:.85rem">
          <option value="">Tutti</option>
          <option value="active">Attivi</option>
          <option value="closed">Chiusi</option>
          <option value="cancelled">Annullati</option>
        </select>
      </div>
      <div id="sl-table">
        <div style="text-align:center;padding:2rem;color:var(--text-light)">Caricamento…</div>
      </div>
    </div>
  </main>
</div>

<!-- Upload cert modal (inline) -->
<script src="../app.js"></script>
<script>
const API = '../api/sick-leave.php';
const today = new Date().toISOString().slice(0,10);

// Max start date = today
document.getElementById('_sl-start').max = today;
document.getElementById('_sl-end').max   = today;

/* ── Preview ─────────────────────────────────────────────────── */
function updatePreview() {
  const s = document.getElementById('_sl-start').value;
  const e = document.getElementById('_sl-end').value || s;
  const p = document.getElementById('_sl-preview');
  if (s) {
    const n = countBusinessDays(s, e);
    p.textContent = n > 0 ? '📅 ' + n + ' giorno/i lavorativo/i' : '⚠️ Nessun giorno lavorativo nel periodo';
  } else { p.textContent = ''; }
}
document.getElementById('_sl-start').addEventListener('change', updatePreview);
document.getElementById('_sl-end').addEventListener('change', updatePreview);

/* ── File info ───────────────────────────────────────────────── */
document.getElementById('_sl-file').addEventListener('change', e => {
  const f = e.target.files[0];
  if (f) {
    document.getElementById('_sl-file-info').textContent = f.name + ' (' + (f.size/1024).toFixed(0) + ' KB)';
    if (f.size > 5*1024*1024) {
      document.getElementById('_sl-err').textContent = 'File troppo grande (max 5 MB)';
      document.getElementById('_sl-err').style.display = '';
    } else { document.getElementById('_sl-err').style.display = 'none'; }
  } else { document.getElementById('_sl-file-info').textContent = ''; }
});

/* ── Stats ───────────────────────────────────────────────────── */
async function loadStats() {
  try {
    const d   = await apiFetch(API + '?action=list');
    const all = d.records ?? [];
    const cur = new Date().getFullYear().toString();
    document.getElementById('cnt-active').textContent  = all.filter(r=>r.stato==='active').length;
    document.getElementById('cnt-days').textContent    = all.filter(r=>r.stato!=='cancelled'&&r.data_inizio.startsWith(cur)).reduce((s,r)=>s+(r.giorni||0),0);
    document.getElementById('cnt-missing').textContent = all.filter(r=>r.stato==='active'&&r.doc_status==='missing').length;
  } catch(_) {}
}

/* ── Load & render ───────────────────────────────────────────── */
async function load() {
  const stato = document.getElementById('_sl-filter').value;
  const url   = API + '?action=list' + (stato ? '&stato=' + encodeURIComponent(stato) : '');
  const tbl   = document.getElementById('sl-table');
  try {
    const d = await apiFetch(url);
    renderTable(d.records ?? [], tbl);
  } catch(e) {
    tbl.innerHTML = `<div style="color:var(--danger);padding:1rem">Errore caricamento</div>`;
  }
}

function renderTable(recs, tbl) {
  if (!recs.length) {
    tbl.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--text-light)">Nessun record trovato</div>';
    return;
  }
  let html = `<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse">
    <thead><tr style="border-bottom:2px solid var(--border);font-size:.82rem;color:var(--text-light)">
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
    const docBadge = docStatusBadge(r.doc_status);
    const canCancel = r.stato === 'active' && r.data_inizio >= today;
    const canUpload = r.stato === 'active' && r.doc_status === 'missing';
    html += `<tr style="border-bottom:1px solid var(--border);font-size:.85rem">
      <td style="padding:.5rem .75rem">${formatDate(r.data_inizio)}</td>
      <td style="padding:.5rem .75rem">${r.data_fine !== r.data_inizio ? formatDate(r.data_fine) : '—'}</td>
      <td style="padding:.5rem .75rem;font-weight:500">${r.giorni} gg</td>
      <td style="padding:.5rem .75rem;color:var(--text-light)">${escapeHtml(r.medico||'—')}</td>
      <td style="padding:.5rem .75rem">${docBadge}</td>
      <td style="padding:.5rem .75rem">${statusBadge(r.stato)}</td>
      <td style="padding:.5rem .75rem;color:var(--text-light);font-size:.8rem;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escapeHtml(r.note_hr||'—')}</td>
      <td style="padding:.5rem .75rem;white-space:nowrap;display:flex;gap:.3rem;flex-wrap:wrap">
        ${canUpload ? `<button class="btn btn-secondary btn-sm" onclick="openUpload('${r.id}')">&#128196; Allega cert.</button>` : ''}
        ${canCancel ? `<button class="btn btn-danger btn-sm" onclick="cancelRec('${r.id}')">&#x2715; Annulla</button>` : ''}
      </td>
    </tr>`;
  }
  html += '</tbody></table></div>';
  tbl.innerHTML = html;
}

/* ── Submit ──────────────────────────────────────────────────── */
document.getElementById('_sl-submit').addEventListener('click', async () => {
  const start  = document.getElementById('_sl-start').value;
  const end    = document.getElementById('_sl-end').value || start;
  const medico = document.getElementById('_sl-medico').value.trim();
  const file   = document.getElementById('_sl-file').files[0];
  const errEl  = document.getElementById('_sl-err');
  const btn    = document.getElementById('_sl-submit');
  errEl.style.display = 'none';

  if (!start) { errEl.textContent = 'Seleziona la data di inizio'; errEl.style.display = ''; return; }
  if (file && file.size > 5*1024*1024) { errEl.textContent = 'File troppo grande (max 5 MB)'; errEl.style.display = ''; return; }

  const fd = new FormData();
  fd.append('data_inizio', start);
  fd.append('data_fine',   end);
  fd.append('medico',      medico);
  if (file) fd.append('certificato', file);

  setLoading(btn, true);
  try {
    await apiFetch(API + '?action=submit', 'POST', fd);
    showToast('Malattia registrata ✓', 'success');
    document.getElementById('_sl-start').value  = '';
    document.getElementById('_sl-end').value    = '';
    document.getElementById('_sl-medico').value = '';
    document.getElementById('_sl-file').value   = '';
    document.getElementById('_sl-file-info').textContent = '';
    document.getElementById('_sl-preview').textContent   = '';
    await loadStats();
    await load();
  } catch(e) {
    errEl.textContent = e.data?.error ?? 'Errore invio';
    errEl.style.display = '';
  } finally { setLoading(btn, false); }
});

/* ── Cancel ──────────────────────────────────────────────────── */
async function cancelRec(id) {
  confirmDialog('Annullare questo periodo di malattia?', async () => {
    try {
      await apiFetch(API + '?action=cancel', 'POST', { id });
      showToast('Periodo annullato', 'info');
      await loadStats(); await load();
    } catch(e) { showToast(e.data?.error ?? 'Errore', 'error'); }
  }, true);
}

/* ── Upload cert modal ───────────────────────────────────────── */
function openUpload(id) {
  showModal(`
    <div class="modal-header">
      <span class="modal-title">&#128196; Allega certificato</span>
      <button class="modal-close">&#x2715;</button>
    </div>
    <div class="modal-body">
      <p style="margin-bottom:1rem;font-size:.9rem;color:var(--text-light)">PDF, JPG o PNG — max 5 MB</p>
      <input type="file" class="form-control" id="_uc-file" accept=".pdf,.jpg,.jpeg,.png">
      <div id="_uc-info" style="font-size:.8rem;color:var(--text-light);margin-top:.25rem"></div>
      <div id="_uc-err" style="color:var(--danger);font-size:.85rem;margin-top:.5rem;display:none"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-dismiss="modal">Annulla</button>
      <button class="btn btn-primary" id="_uc-btn">&#128228; Carica</button>
    </div>
  `);
  document.getElementById('_uc-file').addEventListener('change', e => {
    const f = e.target.files[0];
    document.getElementById('_uc-info').textContent = f ? f.name + ' (' + (f.size/1024).toFixed(0) + ' KB)' : '';
  });
  document.getElementById('_uc-btn').addEventListener('click', async () => {
    const file = document.getElementById('_uc-file').files[0];
    const btn  = document.getElementById('_uc-btn');
    const err  = document.getElementById('_uc-err');
    err.style.display = 'none';
    if (!file)             { err.textContent = 'Seleziona un file'; err.style.display = ''; return; }
    if (file.size > 5*1024*1024) { err.textContent = 'File troppo grande (max 5 MB)'; err.style.display = ''; return; }

    const fd = new FormData();
    fd.append('id', id);
    fd.append('certificato', file);
    setLoading(btn, true);
    try {
      await apiFetch(API + '?action=upload_cert', 'POST', fd);
      closeModal();
      showToast('Certificato caricato ✓', 'success');
      await loadStats(); await load();
    } catch(e) { err.textContent = e.data?.error ?? 'Errore'; err.style.display = ''; setLoading(btn, false); }
  });
}

document.getElementById('_sl-filter').addEventListener('change', load);
loadStats();
load();

/* ── Utility: countBusinessDays (fallback if not in app.js) ─── */
function countBusinessDays(start, end) {
  let n = 0, c = new Date(start), e = new Date(end);
  while (c <= e) { const d = c.getDay(); if (d !== 0 && d !== 6) n++; c.setDate(c.getDate()+1); }
  return n;
}
</script>
</body>
</html>
