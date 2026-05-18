<?php
define('ROOT', __DIR__ . '/..');
define('DATA_DIR', ROOT . '/data');
require_once ROOT . '/auth.php';
require_employee();
require_once ROOT . '/api/json_helper.php';
// Load current employee record for topbar display
$_emp_rec = [];
foreach (read_json(data_path('employees', 'employees.json')) as $_e) {
    if (($_e['employee_id'] ?? '') === get_employee_id()) { $_emp_rec = $_e; break; }
}
$_emp_fn = $_emp_rec['first_name'] ?? '';
$_emp_ln = $_emp_rec['last_name']  ?? '';
$_emp_display  = trim("$_emp_fn $_emp_ln");
$_emp_initials = strtoupper(substr($_emp_fn, 0, 1) . substr($_emp_ln, 0, 1)) ?: 'ME';

?><!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  <title>Le mie presenze - Mini HR</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-layout">
  <?php include ROOT . '/partials/sidebar_emp.php'; ?>
  <header class="topbar">
    <span class="topbar-title">Le mie presenze</span>
        <div class="topbar-user">
      <span><?= htmlspecialchars(get_employee_id()) ?><?= $_emp_display ? ' – ' . htmlspecialchars($_emp_display) : '' ?></span>
      <div class="avatar"><?= htmlspecialchars($_emp_initials) ?></div>
      <a href="../logout.php" class="btn btn-secondary btn-sm">Esci</a>
    </div>
  </header>
  <main class="main-content">
    <div class="page-header"><h1>Le mie presenze</h1></div>

    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap">
      <button class="btn btn-secondary btn-sm" id="prev-m">&#8592;</button>
      <span id="month-lbl" style="font-size:1.05rem;font-weight:600;min-width:160px;text-align:center"></span>
      <button class="btn btn-secondary btn-sm" id="next-m">&#8594;</button>
      <button class="btn btn-primary btn-sm" id="today-btn">Oggi</button>
    </div>

    <div class="card" style="margin-bottom:1.25rem">
      <div id="summary" style="display:flex;flex-wrap:wrap;gap:1rem;padding:.4rem 0">
        <span style="color:var(--text-light);font-size:.85rem">Caricamento…</span>
      </div>
    </div>

    <div class="card">
      <div id="calendar"></div>
    </div>

    <p style="font-size:.78rem;color:var(--text-light);margin-top:.75rem">
      &#9432; Puoi registrare e modificare le presenze degli ultimi 7 giorni. Clicca su un giorno per aggiungere o modificare.
    </p>
  </main>
</div>
<script src="../app.js"></script>
<script>
const TYPES = {
  presenza:               {label:'Presenza',    color:'#22c55e',emoji:'&#127970;'},
  smartworking:           {label:'Smartworking',color:'#3b82f6',emoji:'&#127968;'},
  ferie:                  {label:'Ferie',       color:'#f59e0b',emoji:'&#127796;'},
  permesso:               {label:'Permesso',    color:'#8b5cf6',emoji:'&#128336;'},
  malattia:               {label:'Malattia',    color:'#ef4444',emoji:'&#129298;'},
  assente_non_giustificato:{label:'Assente',    color:'#6b7280',emoji:'&#10060;'},
};

let _month = currentMonthISO();
let _recs  = {};

document.getElementById('prev-m').addEventListener('click',()=>shift(-1));
document.getElementById('next-m').addEventListener('click',()=>shift(+1));
document.getElementById('today-btn').addEventListener('click',()=>{ _month=currentMonthISO(); renderAll(); });

function shift(d) {
  const [y,m] = _month.split('-').map(Number);
  const dt = new Date(y,m-1+d,1);
  _month = `${dt.getFullYear()}-${String(dt.getMonth()+1).padStart(2,'0')}`;
  renderAll();
}

async function renderAll() {
  const [y,m] = _month.split('-').map(Number);
  document.getElementById('month-lbl').textContent =
    new Date(y,m-1,1).toLocaleDateString('it-IT',{month:'long',year:'numeric'})
      .replace(/^\w/,c=>c.toUpperCase());
  document.getElementById('summary').innerHTML = '<span style="color:var(--text-light);font-size:.85rem">Caricamento…</span>';
  document.getElementById('calendar').innerHTML = '<div style="padding:2rem;text-align:center;color:var(--text-light)">Caricamento…</div>';
  try {
    const data = await apiFetch(`../api/attendance.php?month=${_month}`);
    _recs = {};
    data.forEach(r => _recs[r.date] = r);
    renderSummary();
    renderCal();
  } catch(e) {
    document.getElementById('calendar').innerHTML =
      `<div class="text-danger" style="padding:1rem">Errore: ${escapeHtml(e.data?.error??'')}</div>`;
  }
}

function renderSummary() {
  const c = Object.fromEntries(Object.keys(TYPES).map(k=>[k,0]));
  Object.values(_recs).forEach(r=>{ if(c[r.type]!==undefined) c[r.type]++; });
  document.getElementById('summary').innerHTML = Object.entries(TYPES).map(([k,v])=>
    `<span style="display:flex;align-items:center;gap:.35rem;font-size:.83rem">
      <span style="width:9px;height:9px;border-radius:50%;background:${v.color};display:inline-block"></span>
      <strong>${c[k]}</strong> ${v.label}
    </span>`).join('');
}

function renderCal() {
  const [y,m] = _month.split('-').map(Number);
  const today = todayISO();
  const dim   = new Date(y,m,0).getDate();
  const fdow  = (new Date(y,m-1,1).getDay()+6)%7; // Mon=0

  let html = `<div style="padding:.25rem">
    <div class="att-cal-head">
      ${['Lun','Mar','Mer','Gio','Ven','Sab','Dom'].map(d=>`<div class="att-cal-dh">${d}</div>`).join('')}
    </div>
    <div class="att-cal-grid">`;

  for(let i=0;i<fdow;i++) html += '<div class="att-cal-cell empty"></div>';

  for(let day=1;day<=dim;day++){
    const ds  = `${_month}-${String(day).padStart(2,'0')}`;
    const dow = (new Date(y,m-1,day).getDay()+6)%7;
    const we  = dow>=5;
    const fut = ds > today;
    const rec = _recs[ds];
    const daysAgo = Math.round((new Date(today)-new Date(ds))/86400000);
    const canEdit = !fut && daysAgo<=7;
    let cls = 'att-cal-cell';
    if(we) cls+=' weekend';
    if(ds===today) cls+=' today';
    if(fut) cls+=' future';
    if(rec) cls+=' has-record';
    const bg = rec ? TYPES[rec.type]?.color??'#888' : '';
    const click = (canEdit||rec) ? `onclick="openDayModal('${ds}')"` : '';
    html += `<div class="${cls}" style="${rec?`background:${bg}15;border-color:${bg}`:''}" ${click}>
      <span class="att-cal-day">${day}</span>
      ${rec
        ? `<div class="att-cal-type" style="background:${bg}">${TYPES[rec.type]?.emoji??''} ${TYPES[rec.type]?.label??rec.type}</div>
           ${rec.check_in?`<div class="att-cal-time">${formatTime(rec.check_in)}-${formatTime(rec.check_out)}</div>`:''}`
        : (canEdit&&!we ? '<div class="att-cal-add">&#43;</div>' : '')}
    </div>`;
  }
  html += '</div></div>';
  document.getElementById('calendar').innerHTML = html;
}

function openDayModal(date) {
  const rec = _recs[date];
  const lbl = new Date(date).toLocaleDateString('it-IT',{weekday:'long',day:'numeric',month:'long'})
    .replace(/^\w/,c=>c.toUpperCase());
  showModal(`
    <div class="modal-header">
      <span class="modal-title">&#128197; ${lbl}</span>
      <button class="modal-close">&#x2715;</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Tipo presenza</label>
        <div style="display:flex;flex-wrap:wrap;gap:.45rem;margin-top:.4rem">
          ${Object.entries(TYPES).map(([k,v])=>`
            <label id="_lbl-${k}" style="display:flex;align-items:center;gap:.35rem;cursor:pointer;
              padding:.35rem .6rem;border-radius:6px;border:2px solid ${v.color}40;
              background:${rec?.type===k?v.color+'20':'transparent'};transition:background .15s">
              <input type="radio" name="_type" value="${k}" ${rec?.type===k?'checked':''} style="display:none">
              ${v.emoji} ${v.label}
            </label>`).join('')}
        </div>
      </div>
      <div id="_times" style="${rec?.type==='presenza'||rec?.type==='smartworking'?'':'display:none'}">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
          <div class="form-group">
            <label class="form-label">Entrata</label>
            <input type="time" class="form-control" id="_in" value="${rec?.check_in??'09:00'}">
          </div>
          <div class="form-group">
            <label class="form-label">Uscita</label>
            <input type="time" class="form-control" id="_out" value="${rec?.check_out??'18:00'}">
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Note</label>
        <input class="form-control" id="_notes" value="${escapeHtml(rec?.notes??'')}">
      </div>
      <div id="_err" style="color:var(--danger);font-size:.85rem;display:none"></div>
    </div>
    <div class="modal-footer">
      ${rec?'<button class="btn btn-danger" id="_del">Elimina</button>':''}
      <button class="btn btn-secondary" data-dismiss="modal">Annulla</button>
      <button class="btn btn-primary" id="_save">Salva</button>
    </div>`);

  document.querySelectorAll('input[name="_type"]').forEach(r=>{
    r.addEventListener('change',()=>{
      document.getElementById('_times').style.display =
        ['presenza','smartworking'].includes(r.value)?'':'none';
      document.querySelectorAll('input[name="_type"]').forEach(x=>{
        const col = TYPES[x.value]?.color??'#888';
        document.getElementById(`_lbl-${x.value}`).style.background =
          x.checked ? col+'20' : 'transparent';
      });
    });
  });

  document.getElementById('_save').addEventListener('click', async()=>{
    const t = document.querySelector('input[name="_type"]:checked');
    const err = document.getElementById('_err');
    const btn = document.getElementById('_save');
    err.style.display='none';
    if(!t){ err.textContent='Seleziona un tipo'; err.style.display=''; return; }
    setLoading(btn,true);
    try {
      await apiFetch('../api/attendance.php','POST',{
        action:'save', date, type:t.value,
        check_in:  document.getElementById('_in')?.value??'',
        check_out: document.getElementById('_out')?.value??'',
        notes:     document.getElementById('_notes').value,
      });
      closeModal(); showToast('Presenza salvata','success'); await renderAll();
    } catch(e){ err.textContent=e.data?.error??'Errore'; err.style.display=''; }
    finally{ setLoading(btn,false); }
  });

  document.getElementById('_del')?.addEventListener('click',()=>{
    confirmDialog('Eliminare questo record?', async()=>{
      try{
        await apiFetch('../api/attendance.php','POST',{action:'delete',date});
        closeModal(); showToast('Eliminato','success'); await renderAll();
      }catch(e){ showToast(e.data?.error??'Errore','error'); }
    }, true);
  });
}

renderAll();
</script>
</body>
</html>
