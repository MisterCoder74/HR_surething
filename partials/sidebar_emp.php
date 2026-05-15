<?php
$cur = basename($_SERVER['PHP_SELF'],'.php');
?>
<nav class="sidebar">
  <div class="sidebar-logo">Mini <span>HR</span></div>
  <div class="sidebar-nav">
    <span class="nav-section">La mia area</span>
    <a href="/employee/dashboard.php"            class="<?=$cur==='dashboard'            ?'active':''?>">&#127968; Dashboard</a>
    <a href="/employee/attendance.php"           class="<?=$cur==='attendance'           ?'active':''?>">&#128336; Le mie presenze</a>
    <a href="/employee/request-leave.php"        class="<?=$cur==='request-leave'        ?'active':''?>">&#127958;&#65039; Richiedi ferie</a>
    <a href="/employee/request-smartworking.php" class="<?=$cur==='request-smartworking' ?'active':''?>">&#128187; Smartworking</a>
    <a href="/employee/sick-leave.php"           class="<?=$cur==='sick-leave'           ?'active':''?>">&#129298; Malattia</a>
  </div>
  <div class="sidebar-footer">Area Dipendente</div>
</nav>
