<?php
require_once __DIR__ . '/includes/auth.php';

$message = '';
$messageType = '';
$flash = get_flash();
if ($flash) { $message = $flash['message']; $messageType = $flash['type']; }
try {
    redirect_logged_in();
} catch (PDOException $exception) {
    error_log('Login database error: ' . $exception->getMessage());
    $message = 'Sign-in is temporarily unavailable. Please try again later.';
    $messageType = 'error';
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            if (login_user($email, $password, isset($_POST['remember_me']))) {
                header('Location: dashboard.php');
                exit;
            }
        } catch (PDOException $exception) {
            error_log('Login database error: ' . $exception->getMessage());
            $message = 'Sign-in is temporarily unavailable. Please try again later.';
            $messageType = 'error';
        }
    }
    if ($message === '') { $message = 'Invalid email or password.'; $messageType = 'error'; }
}
?>
<!doctype html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/head.php'; ?>
    <title>Welcome | agriSense</title>
</head>
<body class="login-page">
<main class="login-layout">
    <section class="login-visual">
        <a class="login-brand" href="index.php"><img src="assets/agrisense-logo.png" alt="agriSense"></a>
        <div class="login-story">
            <p class="login-eyebrow">Farmer field notes · Eswatini</p>
            <h1>Good decisions<br>start in the field.</h1>
            <p>Get a daily view of weather, crop fit, and the next practical step for your farm.</p>
            <div class="login-region-note"><span></span> Built around Hhohho, Manzini, Shiselweni, and Lubombo</div>
        </div>
        <p class="login-foot">Local conditions. Useful advice. A stronger season.</p>
    </section>
    <section class="login-content">
        <div class="login-card">
            <p class="section-eyebrow">Your farm, at a glance</p>
            <h2>Welcome back</h2>
            <p class="login-description">Sign in to open your farm briefing and saved profile.</p>
            <div class="login-benefits">
                <div><strong>Weather</strong><span>Regional conditions and rain outlook</span></div>
                <div><strong>Crop fit</strong><span>Ideas shaped by weather and farm details</span></div>
                <div><strong>Field actions</strong><span>Recommendations for your crop and stage</span></div>
            </div>
            <?php if ($message !== ''): ?><div class="message <?= e($messageType) ?>" role="alert"><?= e($message) ?></div><?php endif; ?>
            <form method="post" class="login-form">
                <label for="login-email">Email address<input id="login-email" name="email" type="email" autocomplete="email" required></label>
                <label for="login-password">Password<input id="login-password" name="password" type="password" autocomplete="current-password" required></label>
                <label class="login-remember"><input type="checkbox" name="remember_me"> Keep me signed in on this device</label>
                <button class="button" type="submit">Open my farm dashboard <span>→</span></button>
            </form>
            <p class="login-helper">New to agriSense? <a href="register.php">Create a farmer profile</a></p>
        </div>
    </section>
</main>
</body>
</html>
