<?php
/**
 * employee/dashboard.php — Employee Dashboard. Phase 8.
 */
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  <title>La Mia Dashboard — Mini HR</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="app-layout">
  <?php include ROOT . '/partials/sidebar_emp.php'; ?>
  <header class="topbar">
    <span class="topbar-title">La Mia Dashboard</span>
        <div class="topbar-user">
      <span><?= htmlspecialchars(get_employee_id()) ?><?= $_emp_display ? ' – ' . htmlspecialchars($_emp_display) : '' ?></span>
      <div class="avatar"><?= htmlspecialchars($_emp_initials) ?></div>
      <a href="../logout.php" class="btn btn-secondary btn-sm">Esci</a>
    </div>
  </header>
  <main class="main-content">
    <!-- Welcome banner -->
    <div class="page-header">
      <h1 id="welcome-name">Ciao!</h1>
      <p id="dash-date" class="text-muted"></p>
    </div>

    <!-- Sick leave alert (shown only if active) -->
    <div id="sick-alert" style="display:none" class="alert alert-warning" style="margin-bottom:1rem">
      &#129298; Hai una malattia attiva in corso.
      <a href="sick-leave.php" style="font-weight:600;text-decoration:underline">Gestisci &rarr;</a>
    </div>

    <!-- KPI Cards -->
    <div class="stats-grid" style="margin-bottom:1.25rem">
      <div class="stat-card">
        <div class="stat-value" id="kpi-ferie">—</div>
        <div class="stat-label">Ferie residue (gg)</div>
        <div class="stat-sub" id="kpi-ferie-sub" style="font-size:.78rem;color:var(--text-light);margin-top:.25rem"></div>
      </div>
      <div class="stat-card">
        <div class="stat-value" id="kpi-permessi">—</div>
        <div class="stat-label">Permessi residui (ore)</div>
        <div class="stat-sub" id="kpi-permessi-sub" style="font-size:.78rem;color:var(--text-light);margin-top:.25rem"></div>
      </div>
      <div class="stat-card">
        <div class="stat-value" id="kpi-pending">—</div>
        <div class="stat-label">Richieste in attesa</div>
      </div>
      <div class="stat-card">
        <div class="stat-value" id="kpi-presence">—</div>
        <div class="stat-label">Presenze questo mese</div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
      <div class="card-header"><h3 class="card-title">Azioni rapide</h3></div>
      <div style="display:flex;gap:.75rem;flex-wrap:wrap;padding:.25rem 0">
        <a href="request-leave.php"        class="btn btn-primary">&#127958;&#65039; Richiedi ferie / permesso</a>
        <a href="request-smartworking.php" class="btn btn-secondary">&#128071; Richiedi smartworking</a>
        <a href="sick-leave.php"           class="btn btn-secondary">&#129298; Gestisci malattia</a>
        <a href="attendance.php"           class="btn btn-secondary">&#128310; Le mie presenze</a>
      </div>
    </div>

    <!-- Error state -->
    <div id="dash-error" style="display:none"></div>
  </main>
</div>
<script src="../app.js"></script>
<script>
(async () => {
  document.getElementById('dash-date').textContent =
    new Date().toLocaleDateString('it-IT', {weekday:'long',day:'numeric',month:'long',year:'numeric'});

  try {
    const d = await apiFetch('../api/dashboard.php?action=employee');

    // Welcome
    document.getElementById('welcome-name').textContent = 'Ciao, ' + (d.employee?.name ?? '') + '!';

    // Sick alert
    if (d.sick_active) document.getElementById('sick-alert').style.display = '';

    // Leave balance
    const b = d.leave_balance;
    document.getElementById('kpi-ferie').textContent         = b.ferie_residue ?? '—';
    document.getElementById('kpi-ferie-sub').textContent     = `usate: ${b.ferie_usate ?? 0} / ${b.ferie_totali ?? 0}`;
    document.getElementById('kpi-permessi').textContent      = b.permessi_residui_ore ?? '—';
    document.getElementById('kpi-permessi-sub').textContent  = `usate: ${b.permessi_usati_ore ?? 0} / ${b.permessi_totali_ore ?? 0}`;

    // Pending
    document.getElementById('kpi-pending').textContent  = d.pending?.total ?? 0;
    if ((d.pending?.total ?? 0) > 0) document.getElementById('kpi-pending').style.color = 'var(--warning, #f59e0b)';

    // Presence
    document.getElementById('kpi-presence').textContent = d.presence_days_this_month ?? 0;

    // Color warnings on low balance
    if ((b.ferie_residue ?? 0) <= 3)          document.getElementById('kpi-ferie').style.color    = 'var(--warning, #f59e0b)';
    if ((b.permessi_residui_ore ?? 0) <= 4)   document.getElementById('kpi-permessi').style.color = 'var(--warning, #f59e0b)';

  } catch (e) {
    document.getElementById('dash-error').style.display = '';
    document.getElementById('dash-error').innerHTML =
      `<div class="alert alert-danger">Errore nel caricamento della dashboard: ${escapeHtml(e.data?.error ?? 'Errore di connessione')}</div>`;
  }
})();
</script>
</body>
</html>
