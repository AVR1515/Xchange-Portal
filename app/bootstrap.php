<?php
declare(strict_types=1);
$configFile = dirname(__DIR__) . '/config/config.php';
$config = require (is_file($configFile) ? $configFile : dirname(__DIR__) . '/config/config.example.php');
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('xchange_session');
    session_set_cookie_params(['httponly'=>true,'secure'=>$https,'samesite'=>'Lax','path'=>'/']);
    session_start();
}
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");
function db(): PDO {
    global $config;
    static $db = null;
    if (!$db) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_port'], $config['db_name']);
        $db = new PDO($dsn, $config['db_user'], $config['db_pass'], [
          PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
          PDO::ATTR_EMULATE_PREPARES=>false
        ]);
    }
    return $db;
}
function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function checkCsrf(): void {
    if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('CSRF inválido'); }
}
function me(): ?array {
    if (empty($_SESSION['uid'])) return null;
    $s=db()->prepare('SELECT id,name,email,password_hash FROM users WHERE id=? AND is_active=1');
    $s->execute([$_SESSION['uid']]);
    $user=$s->fetch();
    if (!$user || !hash_equals(hash('sha256',$user['password_hash']),$_SESSION['auth_version']??'')) {
        $_SESSION=[];
        return null;
    }
    unset($user['password_hash']);
    return $user;
}
function requireUser(): array {
    $u=me();
    if (!$u) { header('Location: index.php'); exit; }
    return $u;
}
function membership(int $projectId, int $userId): ?array {
    $s=db()->prepare('SELECT role FROM project_members WHERE project_id=? AND user_id=?');
    $s->execute([$projectId,$userId]); return $s->fetch() ?: null;
}
function canCreateProject(int $userId): bool {
    $s=db()->prepare("SELECT 1 FROM project_members WHERE user_id=? AND role='admin' LIMIT 1");
    $s->execute([$userId]);
    return (bool)$s->fetchColumn();
}
function getRequest(int $requestId, int $uid): array {
    $s=db()->prepare('SELECT r.*,p.name AS project_name,u.name AS assigned_name FROM requests r JOIN project_members pm ON pm.project_id=r.project_id AND pm.user_id=? JOIN projects p ON p.id=r.project_id LEFT JOIN users u ON u.id=r.assigned_to WHERE r.id=?');
    $s->execute([$uid,$requestId]);
    $r=$s->fetch();
    if (!$r) { http_response_code(404); exit('Solicitud no encontrada'); }
    return $r;
}
function go(string $extra=''): never { header('Location: index.php'.$extra); exit; }
function recordEvent(int $projectId, ?int $requestId, int $uid, string $action, string $details=''): void {
    $s=db()->prepare('INSERT INTO audit_events(project_id,request_id,user_id,action,details) VALUES(?,?,?,?,?)');
    $s->execute([$projectId,$requestId,$uid,$action,$details]);
}
