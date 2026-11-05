<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('UTC');
set_exception_handler(function (Throwable $e): void {
    // Do not log submitted input, credentials, or full database exceptions.
    error_log('ParcelTrack failure: '.get_class($e).' code '.$e->getCode());
    http_response_code(503);
    echo 'Service temporarily unavailable. Please try again later.';
});
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start(['use_strict_mode'=>1, 'cookie_httponly'=>true,
          'cookie_samesite'=>'Lax', 'cookie_secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off']);
    }
}
function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $path = 'C:/xampp/parceltrack-private/config.ini';
    if (!is_file($path)) throw new RuntimeException('Missing private configuration', 1001);
    $c = parse_ini_file($path, true, INI_SCANNER_RAW)['database'];
    $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['password'], [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}
function esc($v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function field(array $a, string $k): string { return isset($a[$k]) && is_string($a[$k]) ? trim($a[$k]) : ''; }
function audit(string $event, ?int $uid=null, ?int $pid=null): void {
    $s=db()->prepare('INSERT INTO audit_logs(user_id,event_type,parcel_id,ip_address) VALUES(?,?,?,?)');
    $s->execute([$uid,$event,$pid,$_SERVER['REMOTE_ADDR']??null]);
}
function stop(int $status, string $message): never { http_response_code($status); exit(esc($message)); }
function seller(): array {
    $id=$_SESSION['user_id']??null;
    if (!is_scalar($id) || !ctype_digit((string)$id) || (int)$id<1) stop(401,'Please log in through Module 1 first.');
    $s=db()->prepare('SELECT user_id,full_name,role,is_active,must_change_pwd FROM users WHERE user_id=?');
    $s->execute([(int)$id]); $u=$s->fetch(PDO::FETCH_ASSOC);
    if (!$u) stop(401,'Please log in again.');
    if ($u['role']!=='seller' || !$u['is_active'] || $u['must_change_pwd']) {
        audit('ACCESS_DENIED',(int)$u['user_id']); stop(403,'Access denied.');
    }
    return $u;
}
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function check_csrf(): void {
    if (!hash_equals(csrf(), field($_POST,'csrf'))) stop(403,'Form expired. Reload and try again.');
}
function local_time(string $t): string {
    return (new DateTimeImmutable($t,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'))->format('d M Y H:i:s');
}
function top(string $title): void {
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc($title).'</title><link rel="stylesheet" href="style.css"><body><main><nav><a href="index.php">ParcelTrack</a><a href="register_parcel.php">Register parcel</a><a href="my_parcels.php">My parcels</a><a href="track.php">Public tracking</a></nav><h1>'.esc($title).'</h1>';
}
function bottom(): void { echo '</main></body></html>'; }
function tracking_limit(): bool {
    $pdo=db(); $ip=$_SERVER['REMOTE_ADDR']??'unknown';
    $lock='pt:'.substr(hash('sha256',$ip),0,50);
    $s=$pdo->prepare('SELECT GET_LOCK(?, 3)'); $s->execute([$lock]);
    if ((int)$s->fetchColumn()!==1) throw new RuntimeException('Limiter busy', 1002);
    try {
        $s=$pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE scope='tracking' AND identifier=? AND attempted_at > UTC_TIMESTAMP() - INTERVAL 60 SECOND");
        $s->execute([$ip]);
        if ((int)$s->fetchColumn()>=20) return false;
        $s=$pdo->prepare("INSERT INTO rate_limits(scope,identifier) VALUES('tracking',?)");$s->execute([$ip]);
        return true;
    } finally { $s=$pdo->prepare('SELECT RELEASE_LOCK(?)');$s->execute([$lock]); }
}
