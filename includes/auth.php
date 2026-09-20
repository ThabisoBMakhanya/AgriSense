<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
function users(): array { return read_json(USERS_FILE, []); }
function current_user(): ?array { $email = $_SESSION['user_email'] ?? ''; $all = users(); if (!$email || !isset($all[$email])) return null; $all[$email]['email'] = $all[$email]['email'] ?? $email; return $all[$email]; }
function require_login(): void { if (!current_user()) { header('Location: index.php'); exit; } }
function redirect_logged_in(): void { if (current_user()) { header('Location: dashboard.php'); exit; } }
function normalise_phone(string $phone): string { return '+268 ' . substr(preg_replace('/\D/', '', $phone), -8, 4) . ' ' . substr(preg_replace('/\D/', '', $phone), -4); }
function login_user(string $email, string $password, bool $remember): bool {
    $all=users(); if (!isset($all[$email])) return false;
    $storedPassword=$all[$email]['password'] ?? '';
    $valid=password_verify($password, $storedPassword);
    if (!$valid && hash_equals($storedPassword, $password)) { $valid=true; $all[$email]['password']=password_hash($password,PASSWORD_DEFAULT); }
    if (!$valid) return false;
    session_regenerate_id(true); $_SESSION['user_email']=$email; $_SESSION['user_name']=$all[$email]['name'];
    $all[$email]['last_login']=date('Y-m-d H:i:s'); write_json(USERS_FILE,$all);
    if ($remember) setcookie('kk_remember', hash('sha256', $email), ['expires'=>time()+2592000,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
    return true;
}
function logout_user(): void { $_SESSION=[]; if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']); } session_destroy(); }
