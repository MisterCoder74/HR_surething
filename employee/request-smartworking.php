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
  <title>Smartworking - Mini HR</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-layout">
  <?php include ROOT . '/partials/sidebar_emp.php'; ?>
  <header class="topbar">
    <span class="topbar-title">Smartworking</span>
    <div class="topbar-user">
      <span><?= htmlspecialchars(get_user_id()) ?></span>
      <div class="avatar">ME</div>
      <a href="../logout.php" class="btn btn-secondary btn-sm">Esci</a>
    </div>
  </header>
  <main class="main-content">
    <div class="page-header"><h1>Smartworking</h1></div>

    <!-- Info banner -->
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:.75rem 1rem;margin-bottom:1.25rem;font-size:.85rem;color:#1e40af;display:flex;gap:.5rem;align-items:flex-start">
      <span>&#8505;&#65039;</span>
      <span>Lo smartworking deve essere richiesto con almeno <strong>1 giorno lavorativo di preavviso</strong>. Le richieste vanno presentate entro le 23:59 del giorno precedente.</span>
    </div>

    <!-- Stats strip -->
    <div style="display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap">
      <div class="card" style="flex:1;min-width:130px;text-align:center;padding:.75rem 1rem">
        <div style="font-size:.75rem;color:var(--text-light);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.25rem">&#9200; In attesa</div>
        <div id="cnt-pending" style="font-size:1.8rem;font-weight:700;color:#f59e0b">—</div>
      </div>
      <div class="card" style="flex:1;min-width:130px;text-align:center;padding:.75rem 1rem">
        <div style="font-size:.75rem;color:var(--text-light);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.25rem">&#9989; Approvate</div>
        <div id="cnt-approved" style="font-size:1.8rem;font-weight:700;color:#22c55e">—</div>
      </div>
      <div class="card" style="flex:1;min-width:130px;text-align:center;padding:.75rem 1rem">
        <div style="font-size:.75rem;color:var(--text-light);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.25rem">&#128197; Giorni approvati</div>
        <div id="cnt-days" style="font-size:1.8rem;font-weight:700;color:#6366f1">—</div>
      </div>
    </div>

    <!-- New request form -->
    <div class="card" style="margin-bottom:1.5rem">
      <h2 style="font-size:1rem;margin:0 0 1rem">&#128195; Nuova richiesta smartworking</h2>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <div class="form-group">
          <label class="form-label">Data inizio</label>
          <input type="date" class="form-control" id="_sw-start">
        </div>
        <div class="form-group">
          <label class="form-label">Data fine <small style="font-weight:400;color:var(--text-light)">(lascia vuota per 1 giorno)</small></label>
          <input type="date" class="form-control" id="_sw-end">
        </div>
      </div>
      <div id="_sw-preview" style="font-size:.82rem;color:var(--text-light);margin-bottom:.75rem"></div>

      <div class="form-group">
        <label class="form-label">Motivo <small style="font-weight:400;color:var(--text-light)">(opzionale)</small></label>
        <input type="text" class="form-control" id="_sw-motivo" placeholder="es. Concentrazione progetto, Viaggio...">
      </div>

      <div id="_sw-err" style="color:var(--danger);font-size:.85rem;margin-bottom:.5rem;display:none"></div>
      <button class="btn btn-primary" id="_sw-submit">&#128228; Invia richiesta</button>
    </div>

    <!-- History -->
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem">
        <h2 style="font-size:1rem;margin:0">&#128203; Le mie richieste</h2>
        <select id="_sw-filter" class="form-control" style="width:auto;padding:.3rem .6rem;font-size:.85rem">
          <option value="">Tutti gli stati</option>
          <option value="pending">In attesa</option>
          <option value="approved">Approvate</option>
          <option value="rejected">Rifiutate</option>
          <option value="cancelled">Annullate</option>
        </select>
      </div>
      <div id="sw-table">
        <div style="text-align:center;padding:2rem;color:var(--text-light)">Caricamento…</div>
      </div>
    </div>
  </main>
</div>
<script src="../app.js"></script>
<script>
const API = '../api/smartworking.php';

async function init() {
  const d = await loadRequests();
  updateStats(d);
}

async function loadRequests() {
  const stato = document.getElementById('_sw-filter').value;
  const url   = API + '?action=list' + (stato ? '&stato=' + encodeURIComponent(stato) : '');
  const tbl   = document.getElementById('sw-table');
  try {
    const d    = await apiFetch(url);
    const reqs = d.requests ?? [];
    // Update stats using full list (ignore filter for counters)
    renderTable(reqs, tbl);
    return reqs;
  } catch(e) {
    tbl.innerHTML = `<div style="color:var(--danger);padding:1rem">Errore caricamento</div>`;
    return [];
  }
}

async function loadAllForStats() {
  try {
    const d = await apiFetch(API + '?action=list');
    updateStats(d.requests ?? []);
  } catch(_) {}
}

function updateStats(reqs) {
  const all = reqs; // may be filtered, but we reload all for counters via loadAllForStats
  document.getElementById('cnt-pending').textContent  = reqs.filter(r=>r.stato==='pending').length;
  document.getElementById('cnt-approved').textContent = reqs.filter(r=>r.stato==='approved').length;
  document.getElementById('cnt-days').textContent     = reqs.filter(r=>r.stato==='approved').reduce((s,r)=>s+(r.giorni||0),0);
}

function renderTable(reqs, tbl) {
  if (!reqs.length) {
    tbl.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--text-light)">Nessuna richiesta trovata</div>';
    return;
  }
  let html = `<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse">
    <thead><tr style="border-bottom:2px solid var(--border);font-size:.82rem;color:var(--text-light)">
      <th style="padding:.5rem .75rem;text-align:left">Dal</th>
      <th style="padding:.5rem .75rem;text-align:left">Al</th>
      <th style="padding:.5rem .75rem;text-align:left">Giorni</th>
      <th style="padding:.5rem .75rem;text-align:left">Motivo</th>
      <th style="padding:.5rem .75rem;text-align:left">Stato</th>
      <th style="padding:.5rem .75rem;text-align:left">Note HR</th>
      <th style="padding:.5rem .75rem;text-align:left"></th>
    </tr></thead><tbody>`;
  for (const r of reqs) {
    html += `<tr style="border-bottom:1px solid var(--border);font-size:.85rem">
      <td style="padding:.5rem .75rem">${formatDate(r.data_inizio)}</td>
      <td style="padding:.5rem .75rem">${r.data_fine !== r.data_inizio ? formatDate(r.data_fine) : '—'}</td>
      <td style="padding:.5rem .75rem;font-weight:500">${r.giorni} gg</td>
      <td style="padding:.5rem .75rem;color:var(--text-light);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escapeHtml(r.motivo||'—')}</td>
      <td style="padding:.5rem .75rem">${statusBadge(r.stato)}</td>
      <td style="padding:.5rem .75rem;color:var(--text-light);font-size:.8rem;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escapeHtml(r.note_hr||'—')}</td>
      <td style="padding:.5rem .75rem">
        ${r.stato==='pending' ? `<button class="btn btn-danger btn-sm" onclick="cancelReq('${r.id}')">&#x2715; Annulla</button>` : ''}
      </td>
    </tr>`;
  }
  html += '</tbody></table></div>';
  tbl.innerHTML = html;
}

/* ── Working days preview ────────────────────────────────────── */
function updatePreview() {
  const s = document.getElementById('_sw-start').value;
  const e = document.getElementById('_sw-end').value   || s;
  const p = document.getElementById('_sw-preview');
  if (s) {
    const n = countBusinessDays(s, e || s);
    p.textContent = n > 0 ? '📅 ' + n + ' giorno/i lavorativo/i' : '⚠️ Nessun giorno lavorativo nel periodo';
  } else { p.textContent = ''; }
}
document.getElementById('_sw-start').addEventListener('change', updatePreview);
document.getElementById('_sw-end').addEventListener('change', updatePreview);

/* ── Submit ──────────────────────────────────────────────────── */
document.getElementById('_sw-submit').addEventListener('click', async () => {
  const start  = document.getElementById('_sw-start').value;
  const end    = document.getElementById('_sw-end').value || start;
  const motivo = document.getElementById('_sw-motivo').value.trim();
  const errEl  = document.getElementById('_sw-err');
  const btn    = document.getElementById('_sw-submit');
  errEl.style.display = 'none';

  if (!start) { errEl.textContent = 'Seleziona la data di inizio'; errEl.style.display = ''; return; }

  setLoading(btn, true);
  try {
    await apiFetch(API + '?action=submit', 'POST', { data_inizio: start, data_fine: end, motivo });
    showToast('Richiesta inviata ✓', 'success');
    document.getElementById('_sw-start').value  = '';
    document.getElementById('_sw-end').value    = '';
    document.getElementById('_sw-motivo').value = '';
    document.getElementById('_sw-preview').textContent = '';
    await loadAllForStats();
    await loadRequests();
  } catch(e) {
    errEl.textContent = e.data?.error ?? 'Errore invio';
    errEl.style.display = '';
  } finally { setLoading(btn, false); }
});

/* ── Cancel ──────────────────────────────────────────────────── */
async function cancelReq(id) {
  confirmDialog('Annullare questa richiesta di smartworking?', async () => {
    try {
      await apiFetch(API + '?action=cancel', 'POST', { id });
      showToast('Richiesta annullata', 'info');
      await loadAllForStats();
      await loadRequests();
    } catch(e) { showToast(e.data?.error ?? 'Errore', 'error'); }
  });
}

document.getElementById('_sw-filter').addEventListener('change', loadRequests);
loadAllForStats();
init();
</script>
</body>
</html>
