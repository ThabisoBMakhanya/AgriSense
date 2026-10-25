<?php
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
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
