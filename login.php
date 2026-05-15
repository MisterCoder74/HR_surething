<?php
define('DATA_DIR', __DIR__.'/data');
require_once __DIR__.'/auth.php';
if (is_logged_in()) { header('Location: /index.php'); exit; }
$reason = $_GET['reason'] ?? '';
?><!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  <title>Login — Mini HR</title>
  <link rel="stylesheet" href="/style.css">
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-logo"><h1>Mini <span>HR</span></h1><p>Gestione HR</p></div>
    <?php if($reason==='timeout'):?><div class="alert alert-warning">Sessione scaduta. Effettua nuovamente il login.</div><?php endif;?>
    <div id="err" class="alert alert-danger" style="display:none"></div>
    <form id="lf">
      <div class="form-group">
        <label class="form-label">Username <span class="required">*</span></label>
        <input type="text" id="u" class="form-control" required autofocus autocomplete="username">
      </div>
      <div class="form-group">
        <label class="form-label">Password <span class="required">*</span></label>
        <input type="password" id="p" class="form-control" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary btn-block btn-lg" id="lb">Accedi</button>
    </form>
    <p class="text-center text-sm text-muted mt-16">Mini HR Vanilla v1.0</p>
  </div>
</div>
<script src="/app.js"></script>
<script>
document.getElementById('lf').addEventListener('submit', async e => {
  e.preventDefault();
  const btn=document.getElementById('lb'), err=document.getElementById('err');
  err.style.display='none'; setLoading(btn,true);
  try {
    const d = await apiFetch('/api/auth.php','POST',{username:document.getElementById('u').value.trim(),password:document.getElementById('p').value});
    if(d.redirect) window.location.href=d.redirect;
  } catch(ex) { err.textContent=ex.data?.error??'Errore di connessione'; err.style.display='flex'; setLoading(btn,false); }
});
</script>
</body>
</html>
