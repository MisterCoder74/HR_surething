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
  <title>Presenze HR - Mini HR</title>
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
          <select class="form-control" id="emp-sel" style="min-width:220px">
            <option value="">— Tutti —</option>
          </select>
        </div>
        <button class="btn btn-primary" id="load-btn">Carica</button>
        <button class="btn btn-secondary" id="csv-btn" style="display:none">&#8595; Esporta CSV</button>
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
const ATT = {
  presenza:                {label:'Presenza',     color:'#22c55e'},
  smartworking:            {label:'Smartworking', color:'#3b82f6'},
  ferie:                   {label:'Ferie',        color:'#f59e0b'},
  permesso:                {label:'Permesso',     color:'#8b5cf6'},
  malattia:                {label:'Malattia',     color:'#ef4444'},
  assente_non_giustificato:{label:'Assente',      color:'#6b7280'},
};

document.getElementById('month-sel').value = currentMonthISO();

// ── State ─────────────────────────────────────────────────────────────────────
let _currentMonth  = '';
let _currentEid    = '';
let _summaryData   = [];
let _detailRecords = {};
let _emps          = [];

// ── Bootstrap ─────────────────────────────────────────────────────────────────
async function loadEmpFilter() {
  try {
    _emps = await apiFetch('../api/employees.php');
    const sel = document.getElementById('emp-sel');
    _emps.filter(e => e.status === 'attivo').forEach(e => {
      const o = document.createElement('option');
      o.value = e.employee_id;
      o.textContent = `${e.last_name} ${e.first_name}`;
      sel.appendChild(o);
    });
  } catch(_){}
}

document.getElementById('load-btn').addEventListener('click', loadAtt);

// ── Load ──────────────────────────────────────────────────────────────────────
async function loadAtt() {
  _currentMonth = document.getElementById('month-sel').value;
  _currentEid   = document.getElementById('emp-sel').value;
  const res = document.getElementById('att-result');
  if (!_currentMonth) { showToast('Seleziona un mese','warning'); return; }

  res.innerHTML = '<div style="padding:2rem;text-align:center;color:var(--text-light)">Caricamento…</div>';
  document.getElementById('csv-btn').style.display = 'none';

  try {
    if (_currentEid) {
      const data = await apiFetch(`../api/attendance.php?employee_id=${_currentEid}&month=${_currentMonth}`);
      _detailRecords = {};
      data.forEach(r => _detailRecords[r.date] = r);
      renderDetail(res);
    } else {
      _summaryData = await apiFetch('../api/attendance.php','POST',{action:'bulk_summary',month:_currentMonth});
      renderSummary(res);
    }
    document.getElementById('csv-btn').style.display = '';
  } catch(e) {
    res.innerHTML = `<div class="text-danger" style="padding:1rem">Errore: ${escapeHtml(e.data?.error??'')}</div>`;
  }
}

// ── Summary view (all employees) ──────────────────────────────────────────────
function renderSummary(el) {
  const active = _summaryData.filter(e => e.status === 'attivo');
  el.innerHTML = `
    <div class="card-header"><strong>Riepilogo ${_currentMonth}</strong> — ${active.length} dipendenti attivi</div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr>
          <th>Dipendente</th><th>Reparto</th>
          ${Object.values(ATT).map(v=>`<th style="text-align:center;font-size:.8rem">${v.label}</th>`).join('')}
          <th style="text-align:center">Totale</th>
          <th style="width:60px">Azioni</th>
        </tr></thead>
        <tbody>${active.map(e=>{
          const tot = Object.values(e.counts).reduce((a,b)=>a+b,0);
          return `<tr>
            <td><a href="#" onclick="drillDown('${e.employee_id}');return false">${escapeHtml(e.name)}</a></td>
            <td>${escapeHtml(e.department)}</td>
            ${Object.entries(ATT).map(([k,v])=>`<td style="text-align:center">
              ${e.counts[k]>0
                ?`<span class="badge" style="background:${v.color}20;color:${v.color};font-weight:700">${e.counts[k]}</span>`
                :`<span style="color:var(--text-light)">—</span>`}
            </td>`).join('')}
            <td style="text-align:center"><strong>${tot}</strong></td>
            <td><button class="btn btn-sm btn-secondary" title="Dettaglio" onclick="drillDown('${e.employee_id}')">&#128269;</button></td>
          </tr>`;
        }).join('')}</tbody>
      </table>
    </div>`;
}

async function drillDown(eid) {
  document.getElementById('emp-sel').value = eid;
  _currentEid = eid;
  const data = await apiFetch(`../api/attendance.php?employee_id=${eid}&month=${_currentMonth}`);
  _detailRecords = {};
  data.forEach(r => _detailRecords[r.date] = r);
  renderDetail(document.getElementById('att-result'));
}

// ── Detail view (single employee) ─────────────────────────────────────────────
function renderDetail(el) {
  const [y,m] = _currentMonth.split('-').map(Number);
  const dim   = new Date(y,m,0).getDate();
  const emp   = _emps.find(e => e.employee_id === _currentEid);
  const name  = emp ? `${emp.last_name} ${emp.first_name}` : _currentEid;
  const counts = Object.fromEntries(Object.keys(ATT).map(k=>[k,0]));
  Object.values(_detailRecords).forEach(r=>{ if(counts[r.type]!==undefined) counts[r.type]++; });

  el.innerHTML = `
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
      <div>
        <strong>${escapeHtml(name)}</strong>
        <span style="color:var(--text-light);font-size:.85rem;margin-left:.5rem">— ${_currentMonth}</span>
      </div>
      <div style="display:flex;gap:.5rem">
        <button class="btn btn-sm btn-secondary" onclick="backToSummary()">&#8592; Tutti</button>
        <button class="btn btn-sm btn-primary" onclick="openEditModal(null)">+ Aggiungi</button>
      </div>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:.75rem;padding:.65rem 1.25rem;border-bottom:1px solid var(--border)">
      ${Object.entries(ATT).map(([k,v])=>`
        <span style="font-size:.82rem;display:flex;align-items:center;gap:.35rem">
          <span style="width:8px;height:8px;border-radius:50%;background:${v.color};display:inline-block"></span>
          <strong>${counts[k]}</strong> ${v.label}
        </span>`).join('')}
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Data</th><th>Giorno</th><th>Tipo</th><th>Entrata</th><th>Uscita</th><th>Note</th><th style="width:80px">Azioni</th></tr></thead>
        <tbody id="detail-body">${buildDetailRows(y,m,dim)}</tbody>
      </table>
    </div>`;
}

function buildDetailRows(y, m, dim) {
  const DAYS = ['Dom','Lun','Mar','Mer','Gio','Ven','Sab'];
  return Array.from({length:dim},(_,i)=>i+1).map(day => {
    const ds  = `${_currentMonth}-${String(day).padStart(2,'0')}`;
    const dow = new Date(y,m-1,day).getDay();
    const we  = dow===0||dow===6;
    const r   = _detailRecords[ds];
    if (we && !r) return '';
    return `<tr style="${we?'background:var(--bg)':''}">
      <td>${formatDate(ds)}</td>
      <td style="${we?'color:var(--text-light)':''}">${DAYS[dow]}</td>
      <td>${r ? presenceBadge(r.type) : '<span style="color:var(--text-light);font-size:.8rem">—</span>'}</td>
      <td>${r?.check_in  ? formatTime(r.check_in)  : '—'}</td>
      <td>${r?.check_out ? formatTime(r.check_out) : '—'}</td>
      <td style="font-size:.82rem;color:var(--text-light)">${escapeHtml(r?.notes??'')}</td>
      <td>
        <button class="btn btn-sm btn-secondary" title="Modifica" onclick="openEditModal('${ds}')">&#9998;</button>
        ${r ? `<button class="btn btn-sm btn-danger" title="Elimina" onclick="deleteRecord('${ds}')">&#215;</button>` : ''}
      </td>
    </tr>`;
  }).join('');
}

function backToSummary() {
  document.getElementById('emp-sel').value = '';
  _currentEid = '';
  renderSummary(document.getElementById('att-result'));
}

// ── Edit / Add modal (HR version – no date restrictions) ──────────────────────
function openEditModal(date) {
  const [y,m] = _currentMonth.split('-').map(Number);
  const dim   = new Date(y,m,0).getDate();
  const rec   = date ? _detailRecords[date] : null;

  // Build date options for "add" mode
  let dateField = '';
  if (!date) {
    const opts = Array.from({length:dim},(_,i)=>{
      const d = String(i+1).padStart(2,'0');
      const ds = `${_currentMonth}-${d}`;
      return `<option value="${ds}" ${_detailRecords[ds]?'disabled style="color:#999"':''}>${formatDate(ds)}</option>`;
    }).join('');
    dateField = `<div class="form-group">
      <label class="form-label">Data *</label>
      <select class="form-control" id="_edate">${opts}</select>
    </div>`;
  }

  showModal(`
    <div class="modal-header">
      <span class="modal-title">${rec ? '&#9998; Modifica' : '&#43; Aggiungi'} presenza</span>
      <button class="modal-close">&#x2715;</button>
    </div>
    <div class="modal-body">
      ${date ? `<p style="font-size:.88rem;color:var(--text-light);margin:0 0 .75rem"><strong>Data:</strong> ${formatDate(date)}</p>` : dateField}
      <div class="form-group">
        <label class="form-label">Tipo *</label>
        <div style="display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.35rem">
          ${Object.entries(ATT).map(([k,v])=>`
            <label id="_lbl-${k}" style="display:flex;align-items:center;gap:.3rem;cursor:pointer;
              padding:.3rem .55rem;border-radius:6px;border:2px solid ${v.color}40;font-size:.85rem;
              background:${rec?.type===k?v.color+'20':'transparent'};transition:background .15s">
              <input type="radio" name="_etype" value="${k}" ${rec?.type===k?'checked':''} style="display:none">
              ${v.label}
            </label>`).join('')}
        </div>
      </div>
      <div id="_etimes" style="${rec?.type==='presenza'||rec?.type==='smartworking'?'':'display:none'}">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
          <div class="form-group">
            <label class="form-label">Entrata</label>
            <input type="time" class="form-control" id="_ein"  value="${rec?.check_in??'09:00'}">
          </div>
          <div class="form-group">
            <label class="form-label">Uscita</label>
            <input type="time" class="form-control" id="_eout" value="${rec?.check_out??'18:00'}">
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Note</label>
        <input class="form-control" id="_enotes" value="${escapeHtml(rec?.notes??'')}">
      </div>
      <div id="_eerr" style="color:var(--danger);font-size:.85rem;display:none"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-dismiss="modal">Annulla</button>
      <button class="btn btn-primary" id="_esave">Salva</button>
    </div>`);

  document.querySelectorAll('input[name="_etype"]').forEach(r => {
    r.addEventListener('change', () => {
      document.getElementById('_etimes').style.display =
        ['presenza','smartworking'].includes(r.value) ? '' : 'none';
      document.querySelectorAll('input[name="_etype"]').forEach(x => {
        document.getElementById(`_lbl-${x.value}`).style.background =
          x.checked ? ATT[x.value].color+'20' : 'transparent';
      });
    });
  });

  document.getElementById('_esave').addEventListener('click', async () => {
    const t    = document.querySelector('input[name="_etype"]:checked');
    const err  = document.getElementById('_eerr');
    const btn  = document.getElementById('_esave');
    const d    = date || document.getElementById('_edate')?.value;
    err.style.display = 'none';
    if (!t) { err.textContent = 'Seleziona un tipo'; err.style.display = ''; return; }
    if (!d) { err.textContent = 'Seleziona una data'; err.style.display = ''; return; }
    setLoading(btn, true);
    try {
      await apiFetch('../api/attendance.php', 'POST', {
        action:       'save',
        employee_id:  _currentEid,
        date:         d,
        type:         t.value,
        check_in:     document.getElementById('_ein')?.value  ?? '',
        check_out:    document.getElementById('_eout')?.value ?? '',
        notes:        document.getElementById('_enotes').value,
      });
      closeModal();
      showToast('Presenza salvata', 'success');
      // Refresh detail
      const data = await apiFetch(`../api/attendance.php?employee_id=${_currentEid}&month=${_currentMonth}`);
      _detailRecords = {};
      data.forEach(r => _detailRecords[r.date] = r);
      renderDetail(document.getElementById('att-result'));
    } catch(e) {
      err.textContent = e.data?.error ?? 'Errore';
      err.style.display = '';
    } finally { setLoading(btn, false); }
  });
}

async function deleteRecord(date) {
  confirmDialog(`Eliminare il record del ${formatDate(date)}?`, async () => {
    try {
      await apiFetch('../api/attendance.php', 'POST', {
        action: 'delete', employee_id: _currentEid, date,
      });
      showToast('Record eliminato', 'success');
      delete _detailRecords[date];
      const [y,m] = _currentMonth.split('-').map(Number);
      document.getElementById('detail-body').innerHTML =
        buildDetailRows(y, m, new Date(y,m,0).getDate());
    } catch(e) { showToast(e.data?.error ?? 'Errore', 'error'); }
  }, true);
}

// ── CSV export ────────────────────────────────────────────────────────────────
document.getElementById('csv-btn').addEventListener('click', exportCSV);

async function exportCSV() {
  const btn = document.getElementById('csv-btn');
  setLoading(btn, true);
  try {
    let rows = [];
    const header = ['Dipendente','ID','Reparto','Data','Giorno','Tipo','Entrata','Uscita','Note'];
    const DAYS   = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];

    if (_currentEid) {
      // Single employee – use already-loaded records
      const emp  = _emps.find(e => e.employee_id === _currentEid);
      const name = emp ? `${emp.last_name} ${emp.first_name}` : _currentEid;
      const dept = emp?.department ?? '';
      Object.values(_detailRecords).sort((a,b)=>a.date.localeCompare(b.date)).forEach(r => {
        const dow = new Date(r.date).getDay();
        rows.push([name, _currentEid, dept, r.date, DAYS[dow],
          ATT[r.type]?.label ?? r.type, r.check_in||'', r.check_out||'', r.notes||'']);
      });
    } else {
      // All employees – fetch detail per employee
      const active = _summaryData.filter(e => e.status === 'attivo');
      for (const e of active) {
        const data = await apiFetch(`../api/attendance.php?employee_id=${e.employee_id}&month=${_currentMonth}`);
        data.forEach(r => {
          const dow = new Date(r.date).getDay();
          rows.push([e.name, e.employee_id, e.department, r.date, DAYS[dow],
            ATT[r.type]?.label ?? r.type, r.check_in||'', r.check_out||'', r.notes||'']);
        });
      }
      rows.sort((a,b) => a[3].localeCompare(b[3]) || a[0].localeCompare(b[0]));
    }

    const csv = [header, ...rows].map(r =>
      r.map(cell => '"' + String(cell).replace(/"/g,'""') + '"').join(',')
    ).join('\r\n');

    const blob = new Blob(['\uFEFF' + csv], {type:'text/csv;charset=utf-8;'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = `presenze_${_currentMonth}${_currentEid?'_'+_currentEid:''}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    showToast('CSV esportato', 'success');
  } catch(e) {
    showToast('Errore durante l\'export', 'error');
  } finally { setLoading(btn, false); }
}

loadEmpFilter();
</script>
</body>
</html>
