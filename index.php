<?php
require_once __DIR__ . '/includes/auth.php';
redirect_logged_in();

$message = '';
$messageType = '';
$flash = get_flash();
if ($flash) { $message = $flash['message']; $messageType = $flash['type']; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && login_user($email, $password, isset($_POST['remember_me']))) {
        header('Location: dashboard.php');
        exit;
    }
    $message = 'Invalid Email or Password.';
    $messageType = 'error';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sign in or register for Kudla kwesive agricultural market and advisory services.">
    <title>Kudla kwesive | Welcome</title>
    <style>
        :root { --green: #176b45; --deep-green: #0b432d; --yellow: #f5c542; --ink: #18352a; --muted: #63766c; --paper: #f8f6ee; --line: #d8e1d9; --danger: #a43d32; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; color: var(--ink); background: var(--paper); font-family: Georgia, 'Times New Roman', serif; }
        .page { height: 100vh; display: grid; grid-template-columns: minmax(280px, .9fr) minmax(320px, 1.1fr); }
        .intro { position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; padding: clamp(28px, 6vw, 76px); color: white; background: linear-gradient(rgba(11,67,45,.68), rgba(11,67,45,.78)), url('Images/farm_image.jpeg') center / cover no-repeat; }
        .intro:after { content: ''; position: absolute; width: 380px; height: 380px; right: -150px; bottom: -130px; border: 70px solid var(--yellow); border-radius: 50%; opacity: .9; }
        .brand { position: relative; z-index: 1; display: flex; align-items: center; gap: 12px; font-size: 1.1rem; font-weight: bold; letter-spacing: .04em; }
        .intro-copy { position: relative; z-index: 1; max-width: 480px; margin: auto 0; }
        .eyebrow { color: var(--yellow); font: 700 .76rem/1.2 Arial, sans-serif; letter-spacing: .15em; text-transform: uppercase; }
        h1 { max-width: 550px; margin: 16px 0; font-size: clamp(2.5rem, 6vw, 5.2rem); line-height: .94; font-weight: 500; }
        .intro p { max-width: 420px; color: #d7e8d8; font: 1.05rem/1.6 Arial, sans-serif; }
        .intro-foot { position: relative; z-index: 1; color: #b8bfba; font: .78rem Arial, sans-serif; }
        .content { display: flex; align-items: center; justify-content: center; padding: clamp(20px, 4vw, 48px); }
        .auth-shell { width: min(100%, 500px); }
        .auth-header { margin-bottom: 18px; }
        h2 { margin: 0 0 8px; font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 500; }
        .auth-header p { margin: 0; color: var(--muted); font: .95rem/1.5 Arial, sans-serif; }
        .field { margin-bottom: 10px; } label { display: block; margin-bottom: 5px; font: 700 .78rem Arial, sans-serif; letter-spacing: .03em; }
        input[type='email'], input[type='password'] { width: 100%; border: 1px solid var(--line); border-radius: 5px; padding: 11px 15px; color: var(--ink); background: white; font: 1rem Arial, sans-serif; outline: none; }
        input:focus { border-color: var(--green); box-shadow: 0 0 0 3px rgba(23,107,69,.12); }
        .check { display: flex; align-items: flex-start; gap: 9px; margin: 7px 0 14px; color: var(--muted); font: .84rem/1.4 Arial, sans-serif; } .check input { accent-color: var(--green); margin-top: 3px; }
        .submit { width: 100%; border: 0; border-radius: 5px; padding: 12px; color: white; background: var(--green); font: 700 .94rem Arial, sans-serif; cursor: pointer; } .submit:hover { background: var(--deep-green); }
        .message { border-left: 4px solid; border-radius: 4px; padding: 12px 14px; margin-bottom: 20px; font: .88rem/1.4 Arial, sans-serif; } .message.error { color: var(--danger); background: #fcecea; border-color: var(--danger); } .message.success { color: var(--green); background: #e6f3e6; border-color: var(--green); }
        .helper { margin-top: 22px; color: var(--muted); text-align: center; font: .76rem Arial, sans-serif; }
        .helper a { color: var(--green); font-weight: bold; }
        @media (max-width: 760px) { .page { min-height: 100vh; height: auto; display: block; } .intro { min-height: 310px; padding: 27px 24px; } .intro-copy { margin: 48px 0 0; } h1 { font-size: 3.2rem; } .intro p { margin-bottom: 0; font-size: .92rem; } .intro-foot { display: none; } .content { padding: 38px 24px 50px; } }
    </style>
</head>
<body>
    <main class="page">
        <section class="intro" aria-label="About Kudla kwesive">
            <div class="brand">KUDLA KWESIVE</div>
            <div class="intro-copy"><div class="eyebrow">Grow together</div><h1>Better markets. Stronger harvests.</h1><p>A simple home for Eswatini's farmers, buyers, and agricultural support network.</p></div>
            <div class="intro-foot">Agricultural Market &amp; Advisory System · Eswatini</div>
        </section>
        <section class="content">
            <div class="auth-shell">
                <div class="auth-header"><h2>Welcome back</h2><p>Access market prices, weather alerts, and your farming network.</p></div>
                <?php if ($message !== ''): ?><div class="message <?= e($messageType) ?>" role="alert"><?= e($message) ?></div><?php endif; ?>
                <form method="post"><div class="field"><label for="login-email">Email address</label><input id="login-email" name="email" type="email" autocomplete="email" required></div><div class="field"><label for="login-password">Password</label><input id="login-password" name="password" type="password" autocomplete="current-password" required></div><label class="check"><input type="checkbox" name="remember_me"> Remember me on this device</label><button class="submit" type="submit">Login </button></form>
                <p class="helper">Need an account? <a href="register.php">Register here</a></p>
            </div>
        </section>
    </main>
</body>
</html>