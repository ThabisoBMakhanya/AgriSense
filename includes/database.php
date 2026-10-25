<?php
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $databaseUrl = getenv('DATABASE_URL');
    if ($databaseUrl !== false && $databaseUrl !== '') {
        $parts = parse_url($databaseUrl);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'postgresql' || empty($parts['host']) || empty($parts['path'])) {
            throw new PDOException('DATABASE_URL must be a valid PostgreSQL connection URL.');
        }

        parse_str($parts['query'] ?? '', $query);
        $sslMode = preg_replace('/[^a-z]/', '', (string)($query['sslmode'] ?? 'require'));
        $channelBinding = preg_replace('/[^a-z]/', '', (string)($query['channel_binding'] ?? 'prefer'));
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s;channel_binding=%s',
            $parts['host'],
            (int)($parts['port'] ?? 5432),
            ltrim($parts['path'], '/'),
            $sslMode !== '' ? $sslMode : 'require',
            $channelBinding !== '' ? $channelBinding : 'prefer'
        );
        $username = rawurldecode($parts['user'] ?? '');
        $password = rawurldecode($parts['pass'] ?? '');
        if ($username === '') {
            throw new PDOException('DATABASE_URL is missing its username.');
        }

        $connection = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $connection;
    }

    $host = getenv('DB_HOST') ?: DB_HOST;
    $port = getenv('DB_PORT') ?: DB_PORT;
    $database = getenv('DB_NAME') ?: DB_NAME;
    $username = getenv('DB_USER') ?: DB_USER;
    $password = getenv('DB_PASSWORD');
    if ($password === false) {
        $password = DB_PASSWORD;
    }

    if ($host === '' || $database === '' || $username === '') {
        throw new PDOException('Database settings are missing. Configure DB_HOST, DB_NAME, and DB_USER.');
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
    $connection = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $connection;
}

function db_driver(): string
{
    return (getenv('DATABASE_URL') ?: '') !== '' ? 'pgsql' : 'mysql';
}
