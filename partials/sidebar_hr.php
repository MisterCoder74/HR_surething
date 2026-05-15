<?php
$cur = basename($_SERVER['PHP_SELF'],'.php');
?>
<nav class="sidebar">
  <div class="sidebar-logo">Mini <span>HR</span></div>
  <div class="sidebar-nav">
    <span class="nav-section">Dashboard</span>
    <a href="/hr/dashboard.php"  class="<?=$cur==='dashboard' ?'active':''?>">&#128202; Dashboard</a>
    <span class="nav-section">Gestione</span>
    <a href="/hr/employees.php"  class="<?=$cur==='employees' ?'active':''?>">&#128101; Dipendenti</a>
    <a href="/hr/attendance.php" class="<?=$cur==='attendance'?'active':''?>">&#128336; Presenze</a>
    <a href="/hr/requests.php"   class="<?=$cur==='requests'  ?'active':''?>">&#128203; Richieste</a>
    <a href="/hr/sick-leave.php" class="<?=$cur==='sick-leave'?'active':''?>">&#129298; Malattie</a>
    <span class="nav-section">Report</span>
    <a href="/hr/reports.php"    class="<?=$cur==='reports'   ?'active':''?>">&#128200; Report</a>
  </div>
  <div class="sidebar-footer">HR Consultant</div>
</nav>
