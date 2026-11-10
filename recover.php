<?php
require_once __DIR__ . '/includes/auth.php';
redirect_logged_in();

$message = '';
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_is_valid()) {
        $errors[] = 'This recovery form expired. Reload the page and try again.';
    } else {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $code = strtoupper(preg_replace('/[^a-f0-9]/i', '', (string)($_POST['recovery_code'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter the email address on your farm account.';
        if (strlen($code) !== 16) $errors[] = 'Enter the 16-character recovery code.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';
        if (!$errors) {
            try {
                if (auth_is_throttled($email)) {
                    $errors[] = 'Too many attempts. Wait 15 minutes before trying again.';
                } else {
                    $user = find_user_by_email($email);
                    $stored = (string)($user['recovery_code_hash'] ?? '');
                    if ($user && $stored !== '' && password_verify($code, $stored)) {
                        $statement = db()->prepare('UPDATE users SET password_hash = ?, recovery_code_hash = NULL WHERE email = ?');
                        $statement->execute([password_hash($password, PASSWORD_DEFAULT), $email]);
                        auth_record_attempt($email, true);
                        flash('success', 'Password reset. Sign in with your new password. Generate and save a new recovery code from your profile.');
                        header('Location: index.php');
                        exit;
                    }
                    auth_record_attempt($email, false);
                    $errors[] = 'Email or recovery code is not valid.';
                }
            } catch (PDOException $exception) {
                error_log('Password recovery database error: ' . $exception->getMessage());
                $errors[] = 'Password recovery is temporarily unavailable. Please try again later.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head><?php include __DIR__ . '/includes/head.php'; ?><title>Recover account | agriSense</title></head>
<body class="auth-page">
<main class="auth-layout">
  <section class="auth-hero compact"><p class="kicker">Account recovery</p><h1>Get back to your farm.</h1><p>Use the recovery code you saved when you created your account. It works without email or SMS.</p></section>
  <section class="auth-panel">
    <a class="brand auth-brand" href="index.php"><img class="brand-logo" src="assets/agrisense-logo.png" alt="agriSense"></a>
    <p class="kicker">Reset password</p><h2>Recover your account</h2>
    <?php if ($errors): ?><div class="alert error" id="form-errors" tabindex="-1" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" class="registration-form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label>Email address<input type="email" name="email" autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>" required></label>
      <label>16-character recovery code<input name="recovery_code" autocomplete="off" autocapitalize="characters" minlength="16" maxlength="19" required></label>
      <label>New password<input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
      <label>Confirm new password<input type="password" name="confirm_password" autocomplete="new-password" minlength="8" required></label>
      <button class="button" type="submit">Reset password</button>
    </form>
    <p class="auth-switch"><a href="index.php">Back to sign in</a></p>
  </section>
</main>
<script>document.addEventListener('DOMContentLoaded', () => document.getElementById('form-errors')?.focus());</script>
</body></html>
