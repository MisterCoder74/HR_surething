<?php
define('ROOT', __DIR__ . '/..');
define('DATA_DIR', ROOT . '/data');
require_once ROOT . '/auth.php';
require_hr();
$cur_year  = date('Y');
$cur_month = date('Y-m');
?><!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  <title>Report HR - Mini HR</title>
  <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="app-layout">
  <?php include ROOT . '/partials/sidebar_hr.php'; ?>
  <header class="topbar">
    <span class="topbar-title">Report</span>
    <div class="topbar-user">
      <span><?= htmlspecialchars(get_user_id()) ?></span>
      <div class="avatar">HR</div>
      <a href="../logout.php" class="btn btn-secondary btn-sm">Esci</a>
    </div>
  </header>
  <main class="main-content">

    <div class="page-header">
      <h1>&#128200; Report HR</h1>
      <p>Esporta e analizza i dati del personale</p>
    </div>

    <div class="tabs" id="report-tabs">
      <div class="tab-bar">
        <button class="tab-btn active" data-tab="tab-presenze">&#128310; Presenze</button>
        <button class="tab-btn" data-tab="tab-ferie">&#127958;&#65039; Ferie &amp; Permessi</button>
        <button class="tab-btn" data-tab="tab-malattie">&#129298; Malattie</button>
        <button class="tab-btn" data-tab="tab-sw">&#128071; Smartworking</button>
      </div>

      <!-- TAB: Presenze mensili -->
      <div class="tab-panel active" id="tab-presenze">
        <div class="card">
          <div class="card-header" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
            <h3 style="margin:0">Presenze mensili</h3>
            <div style="display:flex;gap:.5rem;align-items:center;margin-left:auto;flex-wrap:wrap">
              <input type="month" id="p-mese" class="form-control" style="width:auto" value="<?= $cur_month ?>">
              <button class="btn btn-primary btn-sm" id="p-load-btn">Carica</button>
              <button class="btn btn-secondary btn-sm" id="p-csv-btn">&#11015; CSV</button>
            </div>
          </div>
          <div id="p-loading" class="empty-state" style="display:none"><div class="spinner"></div></div>
          <div id="p-empty" class="empty-state" style="display:none">
            <div class="empty-state-icon">&#128310;</div>
            <div class="empty-state-text">Nessun dipendente o dato per il periodo selezionato</div>
          </div>
          <div id="p-table-wrap" class="table-responsive" style="display:none">
            <table class="table">
              <thead><tr>
                <th>Dipendente</th><th>Reparto</th>
                <th title="Presenza">Pres.</th><th title="Smartworking">SW</th><th>Ferie</th>
                <th>Permesso</th><th title="Malattia">Malatt.</th><th title="Assente non giustificato">Assente</th><th>Totale</th>
              </tr></thead>
              <tbody id="p-tbody"></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- TAB: Ferie & Permessi -->
      <div class="tab-panel" id="tab-ferie">
        <div class="card">
          <div class="card-header" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
            <h3 style="margin:0">Ferie &amp; Permessi</h3>
            <div style="display:flex;gap:.5rem;align-items:center;margin-left:auto;flex-wrap:wrap">
              <select id="f-anno" class="form-control" style="width:auto">
                <?php for($y=$cur_year;$y>=$cur_year-3;$y--): ?>
                <option value="<?= $y ?>"><?= $y ?></option>
                <?php endfor; ?>
              </select>
              <button class="btn btn-primary btn-sm" id="f-load-btn">Carica</button>
              <button class="btn btn-secondary btn-sm" id="f-csv-btn">&#11015; CSV</button>
            </div>
          </div>
          <div id="f-loading" class="empty-state" style="display:none"><div class="spinner"></div></div>
          <div id="f-empty" class="empty-state" style="display:none">
            <div class="empty-state-icon">&#127958;&#65039;</div>
            <div class="empty-state-text">Nessun dato per l'anno selezionato</div>
          </div>
          <div id="f-table-wrap" class="table-responsive" style="display:none">
            <table class="table">
              <thead><tr>
                <th>Dipendente</th><th>Reparto</th>
                <th>Ferie Tot.</th><th>Usate</th><th>Residue</th>
                <th>Perm. Tot.(h)</th><th>Usati(h)</th><th>Residui(h)</th>
                <th>Richieste anno</th>
              </tr></thead>
              <tbody id="f-tbody"></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- TAB: Malattie -->
      <div class="tab-panel" id="tab-malattie">
        <div class="card">
          <div class="card-header" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
            <h3 style="margin:0">Malattie</h3>
            <div style="display:flex;gap:.5rem;align-items:center;margin-left:auto;flex-wrap:wrap">
              <select id="m-anno" class="form-control" style="width:auto">
                <?php for($y=$cur_year;$y>=$cur_year-3;$y--): ?>
                <option value="<?= $y ?>"><?= $y ?></option>
                <?php endfor; ?>
              </select>
              <button class="btn btn-primary btn-sm" id="m-load-btn">Carica</button>
              <button class="btn btn-secondary btn-sm" id="m-csv-btn">&#11015; CSV</button>
            </div>
          </div>
          <div id="m-loading" class="empty-state" style="display:none"><div class="spinner"></div></div>
          <div id="m-empty" class="empty-state" style="display:none">
            <div class="empty-state-icon">&#129298;</div>
            <div class="empty-state-text">Nessun episodio di malattia per l'anno selezionato</div>
          </div>
          <div id="m-table-wrap" class="table-responsive" style="display:none">
            <table class="table">
              <thead><tr>
                <th>Dipendente</th><th>Reparto</th>
                <th>Data Inizio</th><th>Data Fine</th><th>GG Lav.</th>
                <th>Stato</th><th>Certificato</th><th>Medico</th>
              </tr></thead>
              <tbody id="m-tbody"></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- TAB: Smartworking -->
      <div class="tab-panel" id="tab-sw">
        <div class="card">
          <div class="card-header" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
            <h3 style="margin:0">Smartworking</h3>
            <div style="display:flex;gap:.5rem;align-items:center;margin-left:auto;flex-wrap:wrap">
              <select id="sw-tipo" class="form-control" style="width:auto">
                <option value="anno">Per anno</option>
                <option value="mese">Per mese</option>
              </select>
              <select id="sw-anno" class="form-control" style="width:auto">
                <?php for($y=$cur_year;$y>=$cur_year-3;$y--): ?>
                <option value="<?= $y ?>"><?= $y ?></option>
                <?php endfor; ?>
              </select>
              <input type="month" id="sw-mese" class="form-control" style="width:auto;display:none" value="<?= $cur_month ?>">
              <button class="btn btn-primary btn-sm" id="sw-load-btn">Carica</button>
              <button class="btn btn-secondary btn-sm" id="sw-csv-btn">&#11015; CSV</button>
            </div>
          </div>
          <div id="sw-loading" class="empty-state" style="display:none"><div class="spinner"></div></div>
          <div id="sw-empty" class="empty-state" style="display:none">
            <div class="empty-state-icon">&#128071;</div>
            <div class="empty-state-text">Nessuna richiesta per il periodo selezionato</div>
          </div>
          <div id="sw-table-wrap" class="table-responsive" style="display:none">
            <table class="table">
              <thead><tr>
                <th>Dipendente</th><th>Reparto</th>
                <th>GG Approvati</th><th>N. Richieste</th><th>Approvate</th><th>In attesa</th>
              </tr></thead>
              <tbody id="sw-tbody"></tbody>
            </table>
          </div>
        </div>
      </div>

    </div><!-- /tabs -->
  </main>
</div>

<script src="../app.js?v=<?php echo time(); ?>"></script>
<script>
/* ── Tabs (use global initTabs if available, otherwise local) ─────────────── */
if (typeof initTabs === 'function') {
  initTabs('#report-tabs');
} else {
  document.querySelectorAll('#report-tabs .tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#report-tabs .tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('#report-tabs .tab-panel').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById(btn.dataset.tab)?.classList.add('active');
    });
  });
}

function showState(prefix, state) {
  document.getElementById(prefix+'-loading').style.display    = state === 'loading' ? '' : 'none';
  document.getElementById(prefix+'-empty').style.display      = state === 'empty'   ? '' : 'none';
  document.getElementById(prefix+'-table-wrap').style.display = state === 'table'   ? '' : 'none';
}

/* ── PRESENZE ─────────────────────────────────────────────────────────────── */
async function loadPresenze() {
  const mese = document.getElementById('p-mese').value;
  if (!mese) return;
  showState('p', 'loading');
  try {
    const d = await apiFetch('../api/reports.php?action=presenze&mese='+encodeURIComponent(mese));
    const rows = d.data || [];
    if (!rows.length) { showState('p','empty'); return; }
    document.getElementById('p-tbody').innerHTML = rows.map(s => `
      <tr>
        <td>${escapeHtml(s.name)}</td>
        <td>${escapeHtml(s.department)}</td>
        <td>${s.counts.presenza}</td>
        <td>${s.counts.smartworking}</td>
        <td>${s.counts.ferie}</td>
        <td>${s.counts.permesso}</td>
        <td>${s.counts.malattia}</td>
        <td>${s.counts.assente_non_giustificato}</td>
        <td><strong>${s.total}</strong></td>
      </tr>`).join('');
    showState('p','table');
  } catch(e) { showState('p','empty'); showToast((e.data?.error)||'Errore caricamento','error'); }
}
document.getElementById('p-load-btn').addEventListener('click', loadPresenze);
document.getElementById('p-csv-btn').addEventListener('click', () => {
  const mese = document.getElementById('p-mese').value;
  if (mese) window.open('../api/reports.php?action=presenze&mese='+encodeURIComponent(mese)+'&format=csv','_self');
});
loadPresenze();

/* ── FERIE & PERMESSI ─────────────────────────────────────────────────────── */
async function loadFerie() {
  const anno = document.getElementById('f-anno').value;
  showState('f','loading');
  try {
    const d = await apiFetch('../api/reports.php?action=ferie_permessi&anno='+anno);
    const rows = d.data || [];
    if (!rows.length) { showState('f','empty'); return; }
    document.getElementById('f-tbody').innerHTML = rows.map(s => {
      const reqBadges = s.requests && s.requests.length
        ? s.requests.map(r => `<span class="badge badge-${r.stato||'pending'}" style="font-size:.7rem;margin:1px">${r.tipo==='permesso'?'P':'F'} ${r.data_inizio}</span>`).join('')
        : '<span style="color:var(--color-muted);font-size:.85rem">—</span>';
      return `<tr>
        <td>${escapeHtml(s.name)}</td>
        <td>${escapeHtml(s.department)}</td>
        <td>${s.ferie_totali}</td>
        <td>${s.ferie_usate}</td>
        <td><strong>${s.ferie_residue}</strong></td>
        <td>${s.permessi_totali_ore}h</td>
        <td>${s.permessi_usati_ore}h</td>
        <td><strong>${s.permessi_residui_ore}h</strong></td>
        <td style="max-width:200px;white-space:normal">${reqBadges}</td>
      </tr>`;
    }).join('');
    showState('f','table');
  } catch(e) { showState('f','empty'); showToast((e.data?.error)||'Errore caricamento','error'); }
}
document.getElementById('f-load-btn').addEventListener('click', loadFerie);
document.getElementById('f-csv-btn').addEventListener('click', () => {
  window.open('../api/reports.php?action=ferie_permessi&anno='+document.getElementById('f-anno').value+'&format=csv','_self');
});
loadFerie();

/* ── MALATTIE ─────────────────────────────────────────────────────────────── */
async function loadMalattie() {
  const anno = document.getElementById('m-anno').value;
  showState('m','loading');
  try {
    const d = await apiFetch('../api/reports.php?action=malattie&anno='+anno);
    const rows = d.data || [];
    if (!rows.length) { showState('m','empty'); return; }
    document.getElementById('m-tbody').innerHTML = rows.map(s => `
      <tr>
        <td>${escapeHtml(s.name)}</td>
        <td>${escapeHtml(s.department)}</td>
        <td>${formatDate(s.data_inizio)}</td>
        <td>${s.data_fine ? formatDate(s.data_fine) : '<span style="color:var(--color-warning)">In corso</span>'}</td>
        <td>${s.giorni_wd}</td>
        <td>${statusBadge(s.stato)}</td>
        <td>${docStatusBadge(s.doc_status)}</td>
        <td>${escapeHtml(s.medico||'—')}</td>
      </tr>`).join('');
    showState('m','table');
  } catch(e) { showState('m','empty'); showToast((e.data?.error)||'Errore caricamento','error'); }
}
document.getElementById('m-load-btn').addEventListener('click', loadMalattie);
document.getElementById('m-csv-btn').addEventListener('click', () => {
  window.open('../api/reports.php?action=malattie&anno='+document.getElementById('m-anno').value+'&format=csv','_self');
});
loadMalattie();

/* ── SMARTWORKING ─────────────────────────────────────────────────────────── */
document.getElementById('sw-tipo').addEventListener('change', function() {
  document.getElementById('sw-anno').style.display = this.value === 'anno' ? '' : 'none';
  document.getElementById('sw-mese').style.display = this.value === 'mese' ? '' : 'none';
});
async function loadSW() {
  const tipo = document.getElementById('sw-tipo').value;
  const qs   = tipo === 'mese'
    ? 'mese=' + encodeURIComponent(document.getElementById('sw-mese').value)
    : 'anno=' + document.getElementById('sw-anno').value;
  showState('sw','loading');
  try {
    const d = await apiFetch('../api/reports.php?action=smartworking&'+qs);
    const rows = d.data || [];
    if (!rows.length) { showState('sw','empty'); return; }
    document.getElementById('sw-tbody').innerHTML = rows.map(s => `
      <tr>
        <td>${escapeHtml(s.name)}</td>
        <td>${escapeHtml(s.department)}</td>
        <td><strong>${s.totale_giorni}</strong></td>
        <td>${s.richieste}</td>
        <td>${s.approvate}</td>
        <td>${s.richieste - s.approvate}</td>
      </tr>`).join('');
    showState('sw','table');
  } catch(e) { showState('sw','empty'); showToast((e.data?.error)||'Errore caricamento','error'); }
}
document.getElementById('sw-load-btn').addEventListener('click', loadSW);
document.getElementById('sw-csv-btn').addEventListener('click', () => {
  const tipo = document.getElementById('sw-tipo').value;
  const qs   = tipo === 'mese'
    ? 'mese=' + encodeURIComponent(document.getElementById('sw-mese').value)
    : 'anno=' + document.getElementById('sw-anno').value;
  window.open('../api/reports.php?action=smartworking&format=csv&'+qs,'_self');
});
loadSW();
</script>
</body>
</html>
