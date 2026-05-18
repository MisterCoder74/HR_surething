<?php
/**
 * hr/dashboard.php — HR Dashboard. Phase 8.
 */
define('ROOT', __DIR__ . '/..');
define('DATA_DIR', ROOT . '/data');
require_once ROOT . '/auth.php';
require_hr();
?><!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  <title>Dashboard HR — Mini HR</title>
  <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="app-layout">
  <?php include ROOT . '/partials/sidebar_hr.php'; ?>
  <header class="topbar">
    <span class="topbar-title">Dashboard HR</span>
    <div class="topbar-user">
      <span><?= htmlspecialchars(get_user_id()) ?></span>
      <div class="avatar">HR</div>
      <a href="../logout.php" class="btn btn-secondary btn-sm">Esci</a>
    </div>
  </header>
  <main class="main-content">
    <div class="page-header">
      <h1>Dashboard HR</h1>
      <p id="dash-date" class="text-muted"></p>
    </div>

    <!-- KPI Cards -->
    <div class="stats-grid" id="kpi-grid">
      <div class="stat-card"><div class="stat-value" id="kpi-emp">—</div><div class="stat-label">Dipendenti attivi</div></div>
      <div class="stat-card"><div class="stat-value" id="kpi-pending">—</div><div class="stat-label">Richieste in attesa</div></div>
      <div class="stat-card"><div class="stat-value" id="kpi-sick">—</div><div class="stat-label">Malattie attive</div></div>
      <div class="stat-card"><div class="stat-value" id="kpi-sw">—</div><div class="stat-label">Smartworking oggi</div></div>
    </div>

    <!-- Quick Actions -->
    <div class="card" style="margin-bottom:1.25rem">
      <div class="card-header"><h3 class="card-title">Azioni rapide</h3></div>
      <div style="display:flex;gap:.75rem;flex-wrap:wrap;padding:.25rem 0">
        <a href="requests.php"     class="btn btn-secondary">&#128195; Richieste ferie</a>
        <a href="smartworking.php" class="btn btn-secondary">&#128071; Smartworking</a>
        <a href="sick-leave.php"   class="btn btn-secondary">&#129298; Malattie</a>
        <a href="attendance.php"   class="btn btn-secondary">&#128310; Presenze</a>
        <a href="employees.php"    class="btn btn-secondary">&#128101; Dipendenti</a>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="card">
      <div class="card-header"><h3 class="card-title">Attività recenti</h3></div>
      <div id="activity-wrap">
        <div class="empty-state"><div class="empty-state-icon">&#128230;</div><div class="empty-state-text">Caricamento...</div></div>
      </div>
    </div>

    <!-- Error state -->
    <div id="dash-error" style="display:none"></div>
  </main>
</div>
<script src="../app.js?v=<?php echo time(); ?>"></script>
<script>
(async () => {
  // Date header
  document.getElementById('dash-date').textContent =
    new Date().toLocaleDateString('it-IT', {weekday:'long',day:'numeric',month:'long',year:'numeric'});

  try {
    const d = await apiFetch('../api/dashboard.php?action=hr');

    // KPI
    document.getElementById('kpi-emp').textContent     = d.kpi.employees_active + ' / ' + d.kpi.employees_total;
    document.getElementById('kpi-pending').textContent = d.kpi.pending_requests;
    document.getElementById('kpi-sick').textContent    = d.kpi.active_sick;
    document.getElementById('kpi-sw').textContent      = d.kpi.smartworking_today;

    // Color KPIs
    if (d.kpi.pending_requests > 0) document.getElementById('kpi-pending').style.color = 'var(--warning, #f59e0b)';
    if (d.kpi.active_sick > 0)      document.getElementById('kpi-sick').style.color    = 'var(--danger,  #dc3545)';

    // Recent activity table
    const wrap = document.getElementById('activity-wrap');
    if (!d.recent_activity || d.recent_activity.length === 0) {
      wrap.innerHTML = '<div class="empty-state"><div class="empty-state-icon">&#128230;</div><div class="empty-state-text">Nessuna attività recente</div></div>';
      return;
    }
    const rows = d.recent_activity.map(r => `
      <tr>
        <td><span class="badge badge-${r.badge_type}">${escapeHtml(r.label)}</span></td>
        <td>${escapeHtml(r.employee)}</td>
        <td>${statusBadge(r.status)}</td>
        <td>${escapeHtml(r.info)}</td>
        <td class="text-muted" style="font-size:.82rem">${formatDateTime(r.created_at)}</td>
      </tr>`).join('');
    wrap.innerHTML = `
      <table class="table">
        <thead><tr>
          <th>Tipo</th><th>Dipendente</th><th>Stato</th><th>Periodo / Data</th><th>Inviato</th>
        </tr></thead>
        <tbody>${rows}</tbody>
      </table>`;
  } catch (e) {
    document.getElementById('dash-error').style.display = '';
    document.getElementById('dash-error').innerHTML =
      `<div class="alert alert-danger">Errore nel caricamento della dashboard: ${escapeHtml(e.data?.error ?? 'Errore di connessione')}</div>`;
  }
})();
</script>
</body>
</html>
