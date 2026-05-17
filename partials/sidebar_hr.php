<?php
// Included from hr/*.php – links relative to hr/ directory
$cur = basename($_SERVER['PHP_SELF'], '.php');
?>
<nav class="sidebar">
  <div class="sidebar-logo">Mini <span>HR</span></div>
  <div class="sidebar-nav">
    <span class="nav-section">Dashboard</span>
    <a href="dashboard.php"     class="<?= $cur === 'dashboard'     ? 'active' : '' ?>">&#128202; Dashboard</a>
    <span class="nav-section">Gestione</span>
    <a href="employees.php"     class="<?= $cur === 'employees'     ? 'active' : '' ?>">&#128101; Dipendenti</a>
    <a href="attendance.php"    class="<?= $cur === 'attendance'    ? 'active' : '' ?>">&#128310; Presenze</a>
    <a href="requests.php"      class="<?= $cur === 'requests'      ? 'active' : '' ?>">&#128195; Richieste ferie</a>
    <a href="smartworking.php"  class="<?= $cur === 'smartworking'  ? 'active' : '' ?>">&#128071; Smartworking</a>
    <a href="sick-leave.php"    class="<?= $cur === 'sick-leave'    ? 'active' : '' ?>">&#129298; Malattie</a>
    <span class="nav-section">Report</span>
    <a href="reports.php"       class="<?= $cur === 'reports'       ? 'active' : '' ?>">&#128200; Report</a>
  </div>
  <div class="sidebar-footer">
    <button class="btn btn-sm btn-secondary" style="width:100%" onclick="openProfileModal()">&#128100; Profilo / Password</button>
  </div>
</nav>
