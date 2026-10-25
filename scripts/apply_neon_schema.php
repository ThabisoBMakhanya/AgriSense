<?php

$connectionUri = getenv('NEON_DATABASE_URL') ?: '';
$parts = parse_url($connectionUri);
$databaseName = trim((string)($parts['path'] ?? ''), '/');

if (!$parts || ($parts['scheme'] ?? '') !== 'postgresql' || $databaseName !== 'agrisense') {
    fwrite(STDERR, "Set NEON_DATABASE_URL to the PostgreSQL connection URI for the agrisense database.\n");
    exit(2);
}

if (!extension_loaded('pdo_pgsql')) {
    fwrite(STDERR, "The PHP pdo_pgsql extension is required.\n");
    exit(2);
}

$host = $parts['host'] ?? '';
$port = $parts['port'] ?? 5432;
$username = rawurldecode($parts['user'] ?? '');
$password = rawurldecode($parts['pass'] ?? '');
$dsn = "pgsql:host={$host};port={$port};dbname={$databaseName};sslmode=require";

try {
    $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $schemaPath = __DIR__ . '/../database/neon_schema.sql';
    $schema = file_get_contents($schemaPath);
    if ($schema === false) throw new RuntimeException('Could not read the Neon schema file.');

    foreach (explode(';', $schema) as $statement) {
        if (trim($statement) !== '') $pdo->exec($statement);
    }

    $expectedTables = ['users', 'weather_cache', 'ml_models', 'ml_predictions', 'training_data', 'sms_logs'];
    $check = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    $createdTables = $check->fetchAll(PDO::FETCH_COLUMN);
    $missingTables = array_diff($expectedTables, $createdTables);
    if ($missingTables) throw new RuntimeException('Schema verification found missing tables.');

    echo "Neon schema applied and verified for database agrisense.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Could not apply the Neon schema. Check the database connection and schema file.\n");
    exit(1);
}
