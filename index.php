<?php
define('DATA_DIR', __DIR__ . '/data');
require_once __DIR__ . '/auth.php';   // APP_URL defined here
if (!is_logged_in()) { header('Location: ' . APP_URL . 'login.php'); exit; }
header('Location: ' . APP_URL . (is_hr() ? 'hr/dashboard.php' : 'employee/dashboard.php'));
exit;
