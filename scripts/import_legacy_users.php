<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

$legacyFile = __DIR__ . '/../data/users.json';
$legacyUsers = json_decode(file_get_contents($legacyFile), true);
if (!is_array($legacyUsers)) {
    fwrite(STDERR, "Could not read legacy accounts from data/users.json.\n");
    exit(1);
}

$insert = db()->prepare(
    'INSERT IGNORE INTO users (email, name, phone, password_hash, region, farm_size, crop_type, soil_quality, registered_at, last_login)
     VALUES (:email, :name, :phone, :password_hash, :region, :farm_size, :crop_type, :soil_quality, :registered_at, :last_login)'
);
$imported = 0;
db()->beginTransaction();
try {
    foreach ($legacyUsers as $email => $user) {
        if (!is_array($user) || empty($user['name']) || empty($user['password'])) {
            continue;
        }
        $insert->execute([
            'email' => strtolower(trim($user['email'] ?? $email)),
            'name' => $user['name'],
            'phone' => $user['phone'] ?? null,
            'password_hash' => $user['password'],
            'region' => $user['region'] ?? null,
            'farm_size' => $user['farm_size'] ?? null,
            'crop_type' => $user['crop_type'] ?? null,
            'soil_quality' => isset($user['soil_quality']) ? soil_value($user['soil_quality']) : null,
            'registered_at' => $user['registered_at'] ?? null,
            'last_login' => $user['last_login'] ?? null,
        ]);
        $imported += $insert->rowCount();
    }
    db()->commit();
} catch (PDOException $exception) {
    db()->rollBack();
    fwrite(STDERR, "Import failed; no accounts were imported. Check the database connection and schema.\n");
    error_log('Legacy account import error: ' . $exception->getMessage());
    exit(1);
}

fwrite(STDOUT, sprintf("Imported %d account(s); existing email addresses were left unchanged.\n", $imported));
