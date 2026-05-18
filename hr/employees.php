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
  <title>Dipendenti - Mini HR</title>
  <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="app-layout">
  <?php include ROOT . '/partials/sidebar_hr.php'; ?>
  <header class="topbar">
    <span class="topbar-title">Dipendenti</span>
    <div class="topbar-user">
      <span><?= htmlspecialchars(get_user_id()) ?></span>
      <div class="avatar">HR</div>
      <a href="../logout.php" class="btn btn-secondary btn-sm">Esci</a>
    </div>
  </header>
  <main class="main-content">
    <div class="page-header">
      <h1>Registro Dipendenti</h1>
      <button class="btn btn-primary" id="add-btn">+ Nuovo Dipendente</button>
    </div>
    <div class="card">
      <div class="card-header" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
        <input type="text" class="form-control" id="q" placeholder="&#128269; Cerca nome, email, reparto…" style="max-width:300px">
        <label style="display:flex;align-items:center;gap:.4rem;font-size:.85rem;color:var(--text-light)">
          <input type="checkbox" id="show-cess"> Mostra cessati
        </label>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>ID</th><th>Cognome / Nome</th><th>Email</th>
              <th>Reparto</th><th>Ruolo</th><th>Contratto</th>
              <th>Assunzione</th><th>Stato</th><th style="width:90px">Azioni</th>
            </tr>
          </thead>
          <tbody id="tbody"><tr><td colspan="9" class="text-center">Caricamento…</td></tr></tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<script src="../app.js?v=<?php echo time(); ?>"></script>
<script>
let _emps = [];

async function load() {
  try {
    _emps = await apiFetch('../api/employees.php');
    render();
  } catch(e) {
    document.getElementById('tbody').innerHTML =
      `<tr><td colspan="9" class="text-danger text-center">Errore: ${escapeHtml(e.data?.error??'')}</td></tr>`;
  }
}

function render() {
  const q  = document.getElementById('q').value.toLowerCase();
  const sc = document.getElementById('show-cess').checked;
  const rows = _emps
    .filter(e => sc || e.status === 'attivo')
    .filter(e => !q || `${e.first_name} ${e.last_name} ${e.email} ${e.department} ${e.role}`.toLowerCase().includes(q));
  const tb = document.getElementById('tbody');
  if (!rows.length) {
    tb.innerHTML = '<tr><td colspan="9" class="text-center" style="color:var(--text-light)">Nessun risultato</td></tr>';
    return;
  }
  tb.innerHTML = rows.map(e => `
    <tr style="${e.status==='cessato'?'opacity:.55':''}">
      <td><code>${escapeHtml(e.employee_id)}</code></td>
      <td><strong>${escapeHtml(e.last_name)}</strong> ${escapeHtml(e.first_name)}</td>
      <td>${escapeHtml(e.email)}</td>
      <td>${escapeHtml(e.department)}</td>
      <td>${escapeHtml(e.role)}</td>
      <td style="text-transform:capitalize">${escapeHtml(e.contract_type)}</td>
      <td>${formatDate(e.hire_date)}</td>
      <td>${statusBadge(e.status)}</td>
      <td>
        <button class="btn btn-sm btn-secondary" title="Modifica" onclick="openForm('${e.employee_id}')">&#9998;</button>
        ${e.status==='attivo'
          ? `<button class="btn btn-sm btn-danger" title="Cessa" onclick="toggleStatus('${e.employee_id}','deactivate')">&#8856;</button>`
          : `<button class="btn btn-sm btn-secondary" title="Riattiva" onclick="toggleStatus('${e.employee_id}','reactivate')">&#10003;</button>`}
      </td>
    </tr>`).join('');
}

document.getElementById('q').addEventListener('input', render);
document.getElementById('show-cess').addEventListener('change', render);

// ── Modal form ────────────────────────────────────────────────────────────────
function formHtml(e) {
  const v = k => escapeHtml(e?.[k] ?? '');
  const sel = (k, opts) => opts.map(o => `<option value="${o}"${e?.[k]===o?' selected':''}>${o}</option>`).join('');
  return `
    <div class="modal-header">
      <span class="modal-title">${e ? '&#9998; Modifica' : '&#43; Nuovo Dipendente'}</span>
      <button class="modal-close">&#x2715;</button>
    </div>
    <div class="modal-body">
      ${e ? `<input type="hidden" id="_eid" value="${v('employee_id')}">` : ''}
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <div class="form-group">
          <label class="form-label">Nome *</label>
          <input class="form-control" id="_fn" value="${v('first_name')}">
        </div>
        <div class="form-group">
          <label class="form-label">Cognome *</label>
          <input class="form-control" id="_ln" value="${v('last_name')}">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Email *</label>
        <input class="form-control" id="_em" type="email" value="${v('email')}">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <div class="form-group">
          <label class="form-label">Reparto *</label>
          <input class="form-control" id="_dpt" value="${v('department')}">
        </div>
        <div class="form-group">
          <label class="form-label">Ruolo *</label>
          <input class="form-control" id="_rl" value="${v('role')}">
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <div class="form-group">
          <label class="form-label">Contratto *</label>
          <select class="form-control" id="_ct">
            ${sel('contract_type',['indeterminato','determinato','apprendistato','consulenza','stage','part-time'])}
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Data assunzione *</label>
          <input class="form-control" id="_hd" type="date" value="${v('hire_date')}">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Telefono</label>
        <input class="form-control" id="_ph" value="${v('phone')}">
      </div>
      <div class="form-group">
        <label class="form-label">Note</label>
        <textarea class="form-control" id="_nt" rows="2">${v('notes')}</textarea>
      </div>
      <div id="_err" style="color:var(--danger);font-size:.85rem;display:none"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-dismiss="modal">Annulla</button>
      <button class="btn btn-primary" id="_sb">${e ? 'Salva' : 'Crea'}</button>
    </div>`;
}

function openForm(id) {
  const emp = id ? _emps.find(e => e.employee_id === id) : null;
  showModal(formHtml(emp));
  document.getElementById('_sb').addEventListener('click', async () => {
    const err = document.getElementById('_err');
    const btn = document.getElementById('_sb');
    err.style.display = 'none';
    const p = {
      first_name:    document.getElementById('_fn').value.trim(),
      last_name:     document.getElementById('_ln').value.trim(),
      email:         document.getElementById('_em').value.trim(),
      department:    document.getElementById('_dpt').value.trim(),
      role:          document.getElementById('_rl').value.trim(),
      contract_type: document.getElementById('_ct').value,
      hire_date:     document.getElementById('_hd').value,
      phone:         document.getElementById('_ph').value.trim(),
      notes:         document.getElementById('_nt').value.trim(),
    };
    if (!p.first_name||!p.last_name||!p.email||!p.department||!p.role||!p.hire_date) {
      err.textContent = 'Compila tutti i campi obbligatori (*)'; err.style.display = ''; return;
    }
    setLoading(btn, true);
    try {
      p.action = emp ? 'update' : 'create';
      if (emp) p.employee_id = document.getElementById('_eid').value;
      await apiFetch('../api/employees.php', 'POST', p);
      closeModal();
      showToast(emp ? 'Dipendente aggiornato' : 'Dipendente creato', 'success');
      await load();
    } catch(e) {
      err.textContent = e.data?.error ?? 'Errore'; err.style.display = '';
    } finally { setLoading(btn, false); }
  });
}

document.getElementById('add-btn').addEventListener('click', () => openForm(null));

async function toggleStatus(id, action) {
  const msg = action === 'deactivate' ? 'Cessare questo dipendente?' : 'Riattivare questo dipendente?';
  confirmDialog(msg, async () => {
    try {
      await apiFetch('../api/employees.php', 'POST', {action, employee_id: id});
      showToast(action === 'deactivate' ? 'Dipendente cessato' : 'Riattivato', 'success');
      await load();
    } catch(e) { showToast(e.data?.error ?? 'Errore', 'error'); }
  }, action === 'deactivate');
}

load();
</script>
</body>
</html>
