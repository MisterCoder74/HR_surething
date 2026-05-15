<?php
/**
 * api/json_helper.php - JSON storage helpers.
 * Include in every API endpoint.
 */
function read_json(string $path): array {
    if (!file_exists($path)) return [];
    $c = file_get_contents($path);
    if ($c === false || trim($c) === '') return [];
    $d = json_decode($c, true);
    return is_array($d) ? $d : [];
}
function write_json(string $path, array $data): bool {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0770, true);
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
}
function data_path(string ...$parts): string { return DATA_DIR.'/'.implode('/', $parts); }
function attendance_file(string $ym): string { return data_path('attendance', $ym.'.json'); }
function safe_path(string $base, string $file): string {
    $b = realpath($base); $full = realpath($b.'/'.$file);
    if ($full===false || strpos($full,$b)!==0) { http_response_code(400); echo json_encode(['error'=>'Path non valido']); exit; }
    return $full;
}
function generate_id(string $prefix='id'): string { return $prefix.'_'.date('Ymd').'_'.str_pad(rand(1,999),3,'0',STR_PAD_LEFT); }
function next_employee_id(): string {
    $idx = read_json(data_path('employees','index.json'));
    if (empty($idx)) return 'e001';
    $nums = array_map(fn($e) => (int)ltrim($e['id'],'e'), $idx);
    return 'e'.str_pad(max($nums)+1,3,'0',STR_PAD_LEFT);
}
function validate_date(string $d): bool { $dt = DateTime::createFromFormat('Y-m-d',$d); return $dt && $dt->format('Y-m-d')===$d; }
function validate_time(string $t): bool { return (bool)preg_match('/^\d{2}:\d{2}$/',$t); }
function sanitize(string $s): string { return htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8'); }
function load_holidays(int $year): array { $d=read_json(data_path('config','holidays.json')); return array_column($d[(string)$year]??[],'date'); }
function count_business_days(string $from, string $to, array $hol=[]): int {
    $s=new DateTime($from); $e=new DateTime($to); $n=0; $c=clone $s;
    while($c<=$e){$dow=(int)$c->format('N'); if($dow<6&&!in_array($c->format('Y-m-d'),$hol))$n++; $c->modify('+1 day');}
    return $n;
}
function iso_week(string $date): string { return (new DateTime($date))->format('o-W'); }
function load_config(): array { static $c=null; if($c===null)$c=read_json(data_path('config','rules.json')); return $c; }
function json_response(array $data, int $status=200): void {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE); exit;
}
