<?php
define('ROOT', __DIR__.'/..');
define('DATA_DIR', ROOT.'/data');
require_once ROOT.'/auth.php';
require_employee();
?><!DOCTYPE html>
<html lang="it">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<title>La Mia Dashboard - Mini HR</title><link rel="stylesheet" href="/style.css"></head>
<body><div class="app-layout">
<?php include ROOT.'/partials/sidebar_emp.php'; ?>
<header class="topbar"><span class="topbar-title">La Mia Dashboard</span>
<div class="topbar-user"><span><?= htmlspecialchars(get_employee_id()) ?></span><div class="avatar">ME</div><a href="/logout.php" class="btn btn-secondary btn-sm">Esci</a></div></header>
<main class="main-content"><div class="page-header"><h1>La Mia Dashboard</h1><p>Implementazione: Fase successiva</p></div>
<div class="card"><div class="empty-state"><div class="empty-state-icon">🚧</div><div class="empty-state-text">In costruzione</div></div></div>
</main></div><script src="/app.js"></script></body></html>
