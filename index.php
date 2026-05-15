<?php
define('DATA_DIR', __DIR__ . '/data');
require_once __DIR__ . '/auth.php';
if (!is_logged_in()) { header('Location: login.php'); exit; }
if (is_hr()) { header('Location: hr/dashboard.php'); }
else         { header('Location: employee/dashboard.php'); }
exit;
