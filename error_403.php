<?php
// error_403.php — always included after auth.php, so ROOT_PREFIX is defined
?><!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>403 - Accesso Negato</title>
  <link rel="stylesheet" href="<?= ROOT_PREFIX ?>style.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="login-page">
  <div class="login-card" style="text-align:center">
    <h1 style="font-size:3rem;color:var(--color-danger)">403</h1>
    <h2>Accesso Negato</h2>
    <p class="text-muted mt-8">Non hai i permessi per accedere a questa pagina.</p>
    <a href="<?= ROOT_PREFIX ?>index.php" class="btn btn-primary mt-16">Torna alla home</a>
  </div>
</div>
</body>
</html>
