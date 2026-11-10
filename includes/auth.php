<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/database.php';

final class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        $statement = db()->prepare('SELECT payload FROM auth_sessions WHERE session_id = ? AND expires_at > CURRENT_TIMESTAMP');
        $statement->execute([$id]);
        $payload = $statement->fetchColumn();
        if ($payload === false) return '';
        $decoded = base64_decode((string)$payload, true);
        return $decoded === false ? '' : $decoded;
    }

    public function write(string $id, string $data): bool
    {
        $expiresAt = date('Y-m-d H:i:s', time() + max(300, (int)ini_get('session.gc_maxlifetime')));
        $payload = base64_encode($data);
        if (db_driver() === 'pgsql') {
            $sql = 'INSERT INTO auth_sessions (session_id, payload, expires_at, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP) ON CONFLICT (session_id) DO UPDATE SET payload = EXCLUDED.payload, expires_at = EXCLUDED.expires_at, updated_at = CURRENT_TIMESTAMP';
        } else {
            $sql = 'INSERT INTO auth_sessions (session_id, payload, expires_at, updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE payload = VALUES(payload), expires_at = VALUES(expires_at), updated_at = CURRENT_TIMESTAMP';
        }
        $statement = db()->prepare($sql);
        return $statement->execute([$id, $payload, $expiresAt]);
    }

    public function destroy(string $id): bool
    {
        $statement = db()->prepare('DELETE FROM auth_sessions WHERE session_id = ?');
        return $statement->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $statement = db()->prepare('DELETE FROM auth_sessions WHERE expires_at <= CURRENT_TIMESTAMP');
        return $statement->execute() ? $statement->rowCount() : false;
    }

    public function validateId(string $id): bool
    {
        $statement = db()->prepare('SELECT 1 FROM auth_sessions WHERE session_id = ? AND expires_at > CURRENT_TIMESTAMP');
        $statement->execute([$id]);
        return (bool)$statement->fetchColumn();
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $expiresAt = date('Y-m-d H:i:s', time() + max(300, (int)ini_get('session.gc_maxlifetime')));
        $statement = db()->prepare('UPDATE auth_sessions SET expires_at = ?, updated_at = CURRENT_TIMESTAMP WHERE session_id = ?');
        $statement->execute([$expiresAt, $id]);
        return $statement->rowCount() > 0 || $this->write($id, $data);
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || getenv('VERCEL')) ini_set('session.cookie_secure', '1');
    if (getenv('VERCEL') || getenv('VERCEL_URL')) {
        session_set_save_handler(new DatabaseSessionHandler(), true);
    }
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['csrf_token'];
}

function csrf_is_valid(): bool
{
    $provided = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return $provided !== '' && isset($_SESSION['csrf_token']) && hash_equals((string)$_SESSION['csrf_token'], $provided);
}

function auth_throttle_keys(string $email): array
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return [hash('sha256', strtolower(trim($email))), hash('sha256', $ip)];
}

function auth_is_throttled(string $email): bool
{
    [$emailKey, $ipKey] = auth_throttle_keys($email);
    $since = date('Y-m-d H:i:s', time() - 900);
    $db = db();
    $failedValue = db_driver() === 'pgsql' ? 'FALSE' : '0';
    $byEmail = $db->prepare("SELECT COUNT(*) FROM auth_attempts WHERE email_key = ? AND created_at >= ? AND succeeded = {$failedValue}");
    $byEmail->execute([$emailKey, $since]);
    $byIp = $db->prepare("SELECT COUNT(*) FROM auth_attempts WHERE ip_key = ? AND created_at >= ? AND succeeded = {$failedValue}");
    $byIp->execute([$ipKey, $since]);
    return (int)$byEmail->fetchColumn() >= 5 || (int)$byIp->fetchColumn() >= 30;
}

function auth_record_attempt(string $email, bool $succeeded): void
{
    [$emailKey, $ipKey] = auth_throttle_keys($email);
    $db = db();
    $isPostgres = db_driver() === 'pgsql';
    $sql = $isPostgres
        ? 'INSERT INTO auth_attempts (email_key, ip_key, succeeded, created_at) VALUES (?, ?, CAST(? AS BOOLEAN), ?)'
        : 'INSERT INTO auth_attempts (email_key, ip_key, succeeded, created_at) VALUES (?, ?, ?, ?)';
    $insert = $db->prepare($sql);
    $insert->execute([$emailKey, $ipKey, $isPostgres ? ($succeeded ? 'true' : 'false') : (int)$succeeded, date('Y-m-d H:i:s')]);
    $db->exec("DELETE FROM auth_attempts WHERE created_at < " . $db->quote(date('Y-m-d H:i:s', time() - 2592000)));
    if ($succeeded) {
        $failedValue = $isPostgres ? 'FALSE' : '0';
        $clear = $db->prepare("DELETE FROM auth_attempts WHERE email_key = ? AND ip_key = ? AND succeeded = {$failedValue}");
        $clear->execute([$emailKey, $ipKey]);
    }
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
    $connection = db();
    $connection->beginTransaction();
    try {
    $statement = $connection->prepare(
        'INSERT INTO users (email, name, phone, password_hash, recovery_code_hash, region, weather_location, farm_size, crop_type, soil_quality, farmer_type, growth_stage, soil_type, sms_opt_in, registered_at, last_login)
         VALUES (:email, :name, :phone, :password_hash, :recovery_code_hash, :region, :weather_location, :farm_size, :crop_type, :soil_quality, :farmer_type, :growth_stage, :soil_type, :sms_opt_in, :registered_at, :last_login)'
    );
    $statement->execute([
        'email' => $user['email'],
        'name' => $user['name'],
        'phone' => $user['phone'],
        'password_hash' => $user['password_hash'],
        'recovery_code_hash' => $user['recovery_code_hash'] ?? null,
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
    save_user_enterprises($user['email'], $user['farm_enterprises'] ?? []);
    $connection->commit();
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
}

function get_user_enterprises(string $email): array
{
    $statement = db()->prepare('SELECT id, enterprise_type, name, quantity, unit, growth_stage, notes, weather_location FROM farm_enterprises WHERE farmer_email = ? ORDER BY CASE enterprise_type WHEN \'crop\' THEN 1 WHEN \'animal\' THEN 2 ELSE 3 END, name, id');
    $statement->execute([$email]);
    return $statement->fetchAll();
}

function save_user_enterprises(string $email, array $enterprises): void
{
    $connection = db();
    $ownsTransaction = !$connection->inTransaction();
    if ($ownsTransaction) $connection->beginTransaction();
    try {
        $delete = $connection->prepare('DELETE FROM farm_enterprises WHERE farmer_email = ?');
        $delete->execute([$email]);
        $insert = $connection->prepare('INSERT INTO farm_enterprises (farmer_email, enterprise_type, name, quantity, unit, growth_stage, notes, weather_location) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($enterprises as $enterprise) {
            $type = (string)($enterprise['enterprise_type'] ?? '');
            $name = trim((string)($enterprise['name'] ?? ''));
            if (!in_array($type, ['crop', 'animal', 'other'], true) || $name === '') continue;
            $insert->execute([
                $email,
                $type,
                substr($name, 0, 150),
                max(0, (float)($enterprise['quantity'] ?? 0)),
                substr(trim((string)($enterprise['unit'] ?? '')), 0, 30),
                $type === 'crop' ? substr((string)($enterprise['growth_stage'] ?? ''), 0, 20) : '',
                substr(trim((string)($enterprise['notes'] ?? '')), 0, 2000),
                substr(trim((string)($enterprise['weather_location'] ?? '')), 0, 100),
            ]);
        }
        if ($ownsTransaction) $connection->commit();
    } catch (Throwable $exception) {
        if ($ownsTransaction && $connection->inTransaction()) $connection->rollBack();
        throw $exception;
    }
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
    if (auth_is_throttled($email)) return false;
    $user = find_user_by_email($email);
    if ($user === null) {
        auth_record_attempt($email, false);
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
        auth_record_attempt($email, false);
        return false;
    }

    auth_record_attempt($email, true);

    session_regenerate_id(true);
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name'] = $user['name'];

    if (empty($user['recovery_code_hash'])) {
        $recoveryCode = create_recovery_code();
        $recovery = db()->prepare("UPDATE users SET recovery_code_hash = ? WHERE email = ? AND (recovery_code_hash IS NULL OR recovery_code_hash = '')");
        $recovery->execute([password_hash($recoveryCode, PASSWORD_DEFAULT), $email]);
        if ($recovery->rowCount() > 0) $_SESSION['new_recovery_code'] = $recoveryCode;
    }

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

function create_recovery_code(): string
{
    return strtoupper(bin2hex(random_bytes(8)));
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
