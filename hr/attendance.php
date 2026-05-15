<?php
define('ROOT', __DIR__ . '/..');
define('DATA_DIR', ROOT . '/data');
require_once ROOT . '/auth.php';
require_hr();
?><!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  <title>Presenze - Mini HR</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-layout">
  <?php include ROOT . '/partials/sidebar_hr.php'; ?>
  <header class="topbar">
    <span class="topbar-title">Presenze</span>
    <div class="topbar-user">
      <span><?= htmlspecialchars(get_user_id()) ?></span>
      <div class="avatar">HR</div>
      <a href="../logout.php" class="btn btn-secondary btn-sm">Esci</a>
    </div>
  </header>
  <main class="main-content">
    <div class="page-header"><h1>Gestione Presenze</h1></div>

    <div class="card" style="margin-bottom:1.25rem">
      <div style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="margin:0">
          <label class="form-label">Mese</label>
          <input type="month" class="form-control" id="month-sel">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">Dipendente</label>
          <select class="form-control" id="emp-sel" style="min-width:200px">
            <option value="">— Tutti —</option>
          </select>
        </div>
        <button class="btn btn-primary" id="load-btn">Carica</button>
      </div>
    </div>

    <div class="card" id="att-result">
      <div class="empty-state">
        <div class="empty-state-icon">&#128197;</div>
        <div class="empty-state-text">Seleziona mese e clicca Carica</div>
      </div>
    </div>
  </main>
</div>
<script src="../app.js"></script>
<script>
const ATT_HR = {
  presenza:               {label:'Presenza',    color:'#22c55e'},
  smartworking:           {label:'Smartworking',color:'#3b82f6'},
  ferie:                  {label:'Ferie',       color:'#f59e0b'},
  permesso:               {label:'Permesso',    color:'#8b5cf6'},
  malattia:               {label:'Malattia',    color:'#ef4444'},
  assente_non_giustificato:{label:'Assente',    color:'#6b7280'},
};

document.getElementById('month-sel').value = currentMonthISO();

async function loadEmpFilter() {
  try {
    const emps = await apiFetch('../api/employees.php');
    const sel = document.getElementById('emp-sel');
    emps.filter(e => e.status==='attivo').forEach(e => {
      const o = document.createElement('option');
      o.value = e.employee_id;
      o.textContent = `${e.last_name} ${e.first_name}`;
      sel.appendChild(o);
    });
  } catch(_){}
}

document.getElementById('load-btn').addEventListener('click', loadAtt);

async function loadAtt() {
  const month = document.getElementById('month-sel').value;
  const eid   = document.getElementById('emp-sel').value;
  const res   = document.getElementById('att-result');
  if (!month) { showToast('Seleziona un mese','warning'); return; }
  res.innerHTML = '<div style="padding:2rem;text-align:center;color:var(--text-light)">Caricamento…</div>';
  try {
    if (eid) {
      const data = await apiFetch(`../api/attendance.php?employee_id=${eid}&month=${month}`);
      renderDetail(res, data, month);
    } else {
      const data = await apiFetch('../api/attendance.php','POST',{action:'bulk_summary',month});
      renderSummary(res, data, month);
    }
  } catch(e) {
    res.innerHTML = `<div class="text-danger" style="padding:1rem">Errore: ${escapeHtml(e.data?.error??'')}</div>`;
  }
}

function renderSummary(el, data, month) {
  const [y,m] = month.split('-').map(Number);
  const dim = new Date(y,m,0).getDate();
  const active = data.filter(e=>e.status==='attivo');
  el.innerHTML = `
    <div class="card-header"><strong>Riepilogo ${month}</strong> — ${active.length} dipendenti attivi</div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr>
          <th>Dipendente</th><th>Reparto</th>
          ${Object.values(ATT_HR).map(v=>`<th style="text-align:center;font-size:.8rem">${v.label}</th>`).join('')}
          <th style="text-align:center">Totale</th>
        </tr></thead>
        <tbody>${active.map(e=>{
          const tot = Object.values(e.counts).reduce((a,b)=>a+b,0);
          return `<tr>
            <td><a href="#" onclick="selectEmp('${e.employee_id}');return false">${escapeHtml(e.name)}</a></td>
            <td>${escapeHtml(e.department)}</td>
            ${Object.entries(ATT_HR).map(([k,v])=>`<td style="text-align:center">
              ${e.counts[k]>0
                ?`<span class="badge" style="background:${v.color}20;color:${v.color};font-weight:700">${e.counts[k]}</span>`
                :`<span style="color:var(--text-light)">—</span>`}
            </td>`).join('')}
            <td style="text-align:center"><strong>${tot}</strong>/${dim}</td>
          </tr>`;
        }).join('')}</tbody>
      </table>
    </div>`;
}

function selectEmp(eid) {
  document.getElementById('emp-sel').value = eid;
  loadAtt();
}

function renderDetail(el, records, month) {
  const [y,m] = month.split('-').map(Number);
  const dim = new Date(y,m,0).getDate();
  const map = {};
  records.forEach(r => map[r.date] = r);
  const counts = Object.fromEntries(Object.keys(ATT_HR).map(k=>[k,0]));
  records.forEach(r=>{ if(counts[r.type]!==undefined) counts[r.type]++; });

  el.innerHTML = `
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
      <strong>Dettaglio — ${month}</strong>
      <button class="btn btn-sm btn-secondary" onclick="document.getElementById('emp-sel').value='';loadAtt()">&#8592; Tutti</button>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:.75rem;padding:.65rem 1.25rem;border-bottom:1px solid var(--border)">
      ${Object.entries(ATT_HR).map(([k,v])=>`
        <span style="font-size:.82rem;display:flex;align-items:center;gap:.35rem">
          <span style="width:8px;height:8px;border-radius:50%;background:${v.color};display:inline-block"></span>
          <strong>${counts[k]}</strong> ${v.label}
        </span>`).join('')}
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Data</th><th>Giorno</th><th>Tipo</th><th>Entrata</th><th>Uscita</th><th>Note</th></tr></thead>
        <tbody>${Array.from({length:dim},(_,i)=>i+1).map(day=>{
          const ds = `${month}-${String(day).padStart(2,'0')}`;
          const dow = new Date(y,m-1,day).getDay();
          const dn = ['Dom','Lun','Mar','Mer','Gio','Ven','Sab'][dow];
          const we = dow===0||dow===6;
          const r = map[ds];
          if (we && !r) return '';
          return `<tr style="${we?'background:var(--bg)':''}">
            <td>${formatDate(ds)}</td>
            <td style="${we?'color:var(--text-light)':''}">${dn}</td>
            <td>${r ? presenceBadge(r.type) : '<span style="color:var(--text-light);font-size:.8rem">—</span>'}</td>
            <td>${r?.check_in ? formatTime(r.check_in) : '—'}</td>
            <td>${r?.check_out ? formatTime(r.check_out) : '—'}</td>
            <td style="font-size:.82rem;color:var(--text-light)">${escapeHtml(r?.notes??'')}</td>
          </tr>`;
        }).join('')}</tbody>
      </table>
    </div>`;
}

loadEmpFilter();
</script>
</body>
</html>
