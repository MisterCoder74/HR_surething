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
  <title>Ferie &amp; Permessi - Mini HR</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-layout">
  <?php include ROOT . '/partials/sidebar_emp.php'; ?>
  <header class="topbar">
    <span class="topbar-title">Ferie &amp; Permessi</span>
    <div class="topbar-user">
      <span><?= htmlspecialchars(get_user_id()) ?></span>
      <div class="avatar">ME</div>
      <a href="../logout.php" class="btn btn-secondary btn-sm">Esci</a>
    </div>
  </header>
  <main class="main-content">
    <div class="page-header"><h1>Ferie &amp; Permessi</h1></div>

    <!-- Balance cards -->
    <div style="display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap">
      <div class="card" style="flex:1;min-width:180px;text-align:center">
        <div style="font-size:.8rem;color:var(--text-light);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.35rem">&#9728;&#65039; Ferie residue</div>
        <div id="bal-ferie" style="font-size:2rem;font-weight:700;color:var(--primary)">—</div>
        <div id="bal-ferie-sub" style="font-size:.78rem;color:var(--text-light);margin-top:.2rem">giorni</div>
      </div>
      <div class="card" style="flex:1;min-width:180px;text-align:center">
        <div style="font-size:.8rem;color:var(--text-light);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.35rem">&#128336; Permessi residui</div>
        <div id="bal-permessi" style="font-size:2rem;font-weight:700;color:#8b5cf6">—</div>
        <div id="bal-permessi-sub" style="font-size:.78rem;color:var(--text-light);margin-top:.2rem">ore</div>
      </div>
    </div>

    <!-- New request form -->
    <div class="card" style="margin-bottom:1.5rem">
      <h2 style="font-size:1rem;margin:0 0 1rem">&#128195; Nuova richiesta</h2>

      <div class="form-group">
        <label class="form-label">Tipo</label>
        <div style="display:flex;gap:.75rem;flex-wrap:wrap">
          <label id="_lbl-ferie" style="display:flex;align-items:center;gap:.4rem;cursor:pointer;padding:.45rem .9rem;border-radius:6px;border:2px solid #f59e0b60;background:#f59e0b15;transition:background .15s">
            <input type="radio" name="_req-tipo" value="ferie" checked style="display:none"> &#9728;&#65039; Ferie
          </label>
          <label id="_lbl-permesso" style="display:flex;align-items:center;gap:.4rem;cursor:pointer;padding:.45rem .9rem;border-radius:6px;border:2px solid #8b5cf640;background:transparent;transition:background .15s">
            <input type="radio" name="_req-tipo" value="permesso" style="display:none"> &#128336; Permesso
          </label>
        </div>
      </div>

      <div id="_ferie-fields">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
          <div class="form-group">
            <label class="form-label">Data inizio</label>
            <input type="date" class="form-control" id="_req-start">
          </div>
          <div class="form-group">
            <label class="form-label">Data fine</label>
            <input type="date" class="form-control" id="_req-end">
          </div>
        </div>
        <div id="_giorni-preview" style="font-size:.82rem;color:var(--text-light);margin-bottom:.75rem"></div>
      </div>

      <div id="_permesso-fields" style="display:none">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
          <div class="form-group">
            <label class="form-label">Data</label>
            <input type="date" class="form-control" id="_req-date">
          </div>
          <div class="form-group">
            <label class="form-label">Ore (incrementi da 0.5h)</label>
            <input type="number" class="form-control" id="_req-ore" min="0.5" max="8" step="0.5" placeholder="es. 4">
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Motivo <small style="font-weight:400;color:var(--text-light)">(opzionale)</small></label>
        <input type="text" class="form-control" id="_req-motivo" placeholder="es. Vacanze, Visita medica…">
      </div>

      <div id="_req-err" style="color:var(--danger);font-size:.85rem;margin-bottom:.5rem;display:none"></div>
      <button class="btn btn-primary" id="_req-submit">&#128228; Invia richiesta</button>
    </div>

    <!-- History table -->
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem">
        <h2 style="font-size:1rem;margin:0">&#128203; Le mie richieste</h2>
        <select id="_hist-filter" class="form-control" style="width:auto;padding:.3rem .6rem;font-size:.85rem">
          <option value="">Tutti gli stati</option>
          <option value="pending">In attesa</option>
          <option value="approved">Approvate</option>
          <option value="rejected">Rifiutate</option>
          <option value="cancelled">Annullate</option>
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
const API = '../api/leave-requests.php';

async function init() {
  await Promise.all([loadBalance(), loadRequests()]);
}

async function loadBalance() {
  try {
    const d = await apiFetch(API + '?action=balance');
    const b = d.balance;
    if (!b) return;
    document.getElementById('bal-ferie').textContent     = b.ferie_residue ?? '—';
    document.getElementById('bal-ferie-sub').textContent  = (b.ferie_residue ?? 0) + ' di ' + (b.ferie_totali ?? 0) + ' giorni';
    document.getElementById('bal-permessi').textContent   = (b.permessi_residui_ore ?? '—') + 'h';
    document.getElementById('bal-permessi-sub').textContent = (b.permessi_residui_ore ?? 0) + 'h di ' + (b.permessi_totali_ore ?? 0) + 'h totali';
  } catch(e) {
    document.getElementById('bal-ferie').textContent   = '?';
    document.getElementById('bal-permessi').textContent = '?';
  }
}

async function loadRequests() {
  const stato = document.getElementById('_hist-filter').value;
  const url   = API + '?action=list' + (stato ? '&stato=' + encodeURIComponent(stato) : '');
  const tbl   = document.getElementById('requests-table');
  try {
    const d = await apiFetch(url);
    const reqs = d.requests ?? [];
    if (!reqs.length) {
      tbl.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--text-light)">Nessuna richiesta trovata</div>';
      return;
    }
    let html = `<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse">
      <thead><tr style="border-bottom:2px solid var(--border);font-size:.82rem;color:var(--text-light)">
        <th style="padding:.5rem .75rem;text-align:left">Tipo</th>
        <th style="padding:.5rem .75rem;text-align:left">Dal</th>
        <th style="padding:.5rem .75rem;text-align:left">Al</th>
        <th style="padding:.5rem .75rem;text-align:left">Gg/Ore</th>
        <th style="padding:.5rem .75rem;text-align:left">Motivo</th>
        <th style="padding:.5rem .75rem;text-align:left">Stato</th>
        <th style="padding:.5rem .75rem;text-align:left">Note HR</th>
        <th style="padding:.5rem .75rem;text-align:left"></th>
      </tr></thead><tbody>`;
    for (const r of reqs) {
      const tl   = r.tipo === 'ferie' ? '&#9728;&#65039; Ferie' : '&#128336; Permesso';
      const qty  = r.tipo === 'ferie' ? r.giorni + ' gg' : r.ore + 'h';
      const fine = r.tipo === 'ferie' ? formatDate(r.data_fine) : '—';
      html += `<tr style="border-bottom:1px solid var(--border);font-size:.85rem">
        <td style="padding:.5rem .75rem;font-weight:500">${tl}</td>
        <td style="padding:.5rem .75rem">${formatDate(r.data_inizio)}</td>
        <td style="padding:.5rem .75rem">${fine}</td>
        <td style="padding:.5rem .75rem">${qty}</td>
        <td style="padding:.5rem .75rem;color:var(--text-light);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escapeHtml(r.motivo||'—')}</td>
        <td style="padding:.5rem .75rem">${statusBadge(r.stato)}</td>
        <td style="padding:.5rem .75rem;color:var(--text-light);font-size:.8rem;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escapeHtml(r.note_hr||'—')}</td>
        <td style="padding:.5rem .75rem">
          ${r.stato==='pending' ? `<button class="btn btn-danger btn-sm" onclick="cancelRequest('${r.id}')">&#x2715; Annulla</button>` : ''}
        </td>
      </tr>`;
    }
    html += '</tbody></table></div>';
    tbl.innerHTML = html;
  } catch(e) {
    tbl.innerHTML = `<div style="color:var(--danger);padding:1rem">Errore nel caricamento</div>`;
  }
}

/* ── Tipo toggle ─────────────────────────────────────────────── */
document.querySelectorAll('input[name="_req-tipo"]').forEach(radio => {
  radio.addEventListener('change', () => {
    const isFerie = radio.value === 'ferie';
    document.getElementById('_ferie-fields').style.display    = isFerie ? '' : 'none';
    document.getElementById('_permesso-fields').style.display = isFerie ? 'none' : '';
    document.getElementById('_lbl-ferie').style.background    = isFerie ? '#f59e0b15' : 'transparent';
    document.getElementById('_lbl-permesso').style.background = isFerie ? 'transparent' : '#8b5cf615';
  });
  if (radio.checked) radio.dispatchEvent(new Event('change'));
});

/* ── Working days preview ────────────────────────────────────── */
function updateGiorniPreview() {
  const s = document.getElementById('_req-start').value;
  const e = document.getElementById('_req-end').value;
  const p = document.getElementById('_giorni-preview');
  if (s && e && e >= s) {
    const n = countBusinessDays(s, e);
    p.textContent = n > 0 ? '&#128197; ' + n + ' giorno/i lavorativo/i' : '&#9888;&#65039; Nessun giorno lavorativo nel periodo';
  } else { p.textContent = ''; }
}
document.getElementById('_req-start').addEventListener('change', updateGiorniPreview);
document.getElementById('_req-end').addEventListener('change', updateGiorniPreview);

/* ── Submit ──────────────────────────────────────────────────── */
document.getElementById('_req-submit').addEventListener('click', async () => {
  const tipo   = document.querySelector('input[name="_req-tipo"]:checked').value;
  const motivo = document.getElementById('_req-motivo').value.trim();
  const errEl  = document.getElementById('_req-err');
  const btn    = document.getElementById('_req-submit');
  errEl.style.display = 'none';

  let body = { tipo, motivo };
  if (tipo === 'ferie') {
    body.data_inizio = document.getElementById('_req-start').value;
    body.data_fine   = document.getElementById('_req-end').value;
    if (!body.data_inizio || !body.data_fine) { errEl.textContent = 'Seleziona le date'; errEl.style.display = ''; return; }
  } else {
    body.data_inizio = document.getElementById('_req-date').value;
    body.ore = parseFloat(document.getElementById('_req-ore').value);
    if (!body.data_inizio)             { errEl.textContent = 'Seleziona la data'; errEl.style.display = ''; return; }
    if (!body.ore || body.ore <= 0)    { errEl.textContent = 'Inserisci le ore';  errEl.style.display = ''; return; }
  }

  setLoading(btn, true);
  try {
    await apiFetch(API + '?action=submit', 'POST', body);
    showToast('Richiesta inviata con successo', 'success');
    document.getElementById('_req-start').value  = '';
    document.getElementById('_req-end').value    = '';
    document.getElementById('_req-date').value   = '';
    document.getElementById('_req-ore').value    = '';
    document.getElementById('_req-motivo').value = '';
    document.getElementById('_giorni-preview').textContent = '';
    await loadBalance();
    await loadRequests();
  } catch(e) {
    errEl.textContent = e.data?.error ?? 'Errore invio richiesta';
    errEl.style.display = '';
  } finally { setLoading(btn, false); }
});

/* ── Cancel ──────────────────────────────────────────────────── */
async function cancelRequest(id) {
  confirmDialog('Annullare questa richiesta?', async () => {
    try {
      await apiFetch(API + '?action=cancel', 'POST', { id });
      showToast('Richiesta annullata', 'info');
      await loadRequests();
    } catch(e) { showToast(e.data?.error ?? 'Errore annullamento', 'error'); }
  });
}

document.getElementById('_hist-filter').addEventListener('change', loadRequests);
init();
</script>
</body>
</html>
