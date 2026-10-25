<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function find_user_by_email(string $email): ?array
{
    $statement = db()->prepare('SELECT * FROM users WHERE email = :email');
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();
    return $user ?: null;
}

function current_user(): ?array
{
    $email = $_SESSION['user_email'] ?? '';
    if (!$email) {
        return null;
    }

    $user = find_user_by_email($email);
    if ($user === null) {
        return null;
    }

    unset($user['password_hash']);
    return $user;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: index.php');
        exit;
    }
}

function redirect_logged_in(): void
{
    if (current_user()) {
        header('Location: dashboard.php');
        exit;
    }
}

function normalise_phone(string $phone): string
{
    $digits = preg_replace('/\D/', '', $phone);
    return '+268 ' . substr($digits, -8, 4) . ' ' . substr($digits, -4);
}

function create_user(array $user): void
{
    $statement = db()->prepare(
        'INSERT INTO users (email, name, phone, password_hash, region, weather_location, farm_size, crop_type, soil_quality, farmer_type, growth_stage, soil_type, sms_opt_in, registered_at, last_login)
         VALUES (:email, :name, :phone, :password_hash, :region, :weather_location, :farm_size, :crop_type, :soil_quality, :farmer_type, :growth_stage, :soil_type, :sms_opt_in, :registered_at, :last_login)'
    );
    $statement->execute([
        'email' => $user['email'],
        'name' => $user['name'],
        'phone' => $user['phone'],
        'password_hash' => $user['password_hash'],
        'region' => $user['region'],
        'weather_location' => $user['weather_location'] ?? null,
        'farm_size' => $user['farm_size'],
        'crop_type' => $user['crop_type'],
        'soil_quality' => $user['soil_quality'],
        'farmer_type' => $user['farmer_type'] ?? 'crop',
        'growth_stage' => $user['growth_stage'] ?? 'vegetative',
        'soil_type' => $user['soil_type'] ?? 'loamy',
        'sms_opt_in' => !empty($user['sms_opt_in']) ? 1 : 0,
        'registered_at' => $user['registered_at'],
        'last_login' => $user['last_login'],
    ]);
}

function update_user_profile(string $email, float $farmSize, string $cropType, int $soilQuality, string $farmerType = 'crop', string $growthStage = 'vegetative', string $soilType = 'loamy', bool $smsOptIn = false, ?string $weatherLocation = null): void
{
    $statement = db()->prepare(
        'UPDATE users SET farm_size = :farm_size, crop_type = :crop_type, soil_quality = :soil_quality, farmer_type = :farmer_type, growth_stage = :growth_stage, soil_type = :soil_type, sms_opt_in = :sms_opt_in, weather_location = :weather_location WHERE email = :email'
    );
    $statement->execute([
        'farm_size' => $farmSize,
        'crop_type' => $cropType,
        'soil_quality' => $soilQuality,
        'farmer_type' => $farmerType,
        'growth_stage' => $growthStage,
        'soil_type' => $soilType,
        'sms_opt_in' => $smsOptIn ? 1 : 0,
        'weather_location' => $weatherLocation,
        'email' => $email,
    ]);
}

function login_user(string $email, string $password, bool $remember): bool
{
    $user = find_user_by_email($email);
    if ($user === null) {
        return false;
    }

    $storedPassword = $user['password_hash'] ?? '';
    $valid = password_verify($password, $storedPassword);
    $needsRehash = false;
    if (!$valid && $storedPassword !== '' && hash_equals($storedPassword, $password)) {
        $valid = true;
        $needsRehash = true;
    } elseif ($valid && password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
        $needsRehash = true;
    }
    if (!$valid) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name'] = $user['name'];

    if ($needsRehash) {
        $statement = db()->prepare('UPDATE users SET password_hash = :password_hash, last_login = NOW() WHERE email = :email');
        $statement->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'email' => $email,
        ]);
    } else {
        $statement = db()->prepare('UPDATE users SET last_login = NOW() WHERE email = :email');
        $statement->execute(['email' => $email]);
    }

    if ($remember) {
        setcookie('kk_remember', hash('sha256', $email), [
            'expires' => time() + 2592000,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    return true;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $parameters = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'], $parameters['secure'], $parameters['httponly']);
    }
    session_destroy();
}
