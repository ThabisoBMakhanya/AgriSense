<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !csrf_is_valid()) {
    http_response_code(400);
    exit('Invalid recovery code request.');
}
$code = create_recovery_code();
$statement = db()->prepare('UPDATE users SET recovery_code_hash = ? WHERE email = ?');
$statement->execute([password_hash($code, PASSWORD_DEFAULT), (string)$_SESSION['user_email']]);
$_SESSION['new_recovery_code'] = $code;
flash('success', 'New recovery code generated. Save it now; the previous code no longer works.');
header('Location: profile.php#recovery-code');
exit;
