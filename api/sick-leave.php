<?php
define('ROOT', __DIR__.'/..');
define('DATA_DIR', ROOT.'/data');
require_once ROOT.'/auth.php';
require_once __DIR__.'/json_helper.php';
api_require_login();
header('Content-Type: application/json; charset=utf-8');
json_response(['error'=>'Not implemented yet — sick-leave API (Phase coming soon)'], 501);
