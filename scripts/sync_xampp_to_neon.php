<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

$connectionUri = getenv('NEON_DATABASE_URL') ?: '';
$parts = parse_url($connectionUri);
$databaseName = trim((string)($parts['path'] ?? ''), '/');
if (!$parts || ($parts['scheme'] ?? '') !== 'postgresql' || $databaseName !== 'agrisense') {
    fwrite(STDERR, "Set NEON_DATABASE_URL to the PostgreSQL connection URI for the agrisense database.\n");
    exit(2);
}
if (!extension_loaded('pdo_mysql') || !extension_loaded('pdo_pgsql')) {
    fwrite(STDERR, "The PHP pdo_mysql and pdo_pgsql extensions are required.\n");
    exit(2);
}

$sourceHost = getenv('DB_HOST') ?: DB_HOST;
$sourcePort = getenv('DB_PORT') ?: DB_PORT;
$sourceName = getenv('DB_NAME') ?: DB_NAME;
$sourceUser = getenv('DB_USER') ?: DB_USER;
$sourcePassword = getenv('DB_PASSWORD');
if ($sourcePassword === false) $sourcePassword = DB_PASSWORD;

$source = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $sourceHost, $sourcePort, $sourceName),
    $sourceUser,
    $sourcePassword,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$username = rawurldecode($parts['user'] ?? '');
$password = rawurldecode($parts['pass'] ?? '');
$query = [];
parse_str($parts['query'] ?? '', $query);
$channelBinding = preg_replace('/[^a-z]/', '', (string)($query['channel_binding'] ?? 'prefer'));
putenv('PGCHANNELBINDING=' . ($channelBinding !== '' ? $channelBinding : 'prefer'));
$destination = new PDO(
    sprintf(
        'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
        $parts['host'],
        (int)($parts['port'] ?? 5432),
        $databaseName,
        preg_replace('/[^a-z]/', '', (string)($query['sslmode'] ?? 'require')) ?: 'require'
    ),
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$tables = [
    'users' => [
        'key' => 'email',
        'columns' => ['email','name','phone','password_hash','region','weather_location','farm_size','crop_type','soil_quality','farmer_type','growth_stage','soil_type','sms_opt_in','registered_at','last_login'],
    ],
    'weather_cache' => [
        'key' => 'location',
        'columns' => ['location','temp_c','humidity_pct','rain_probability','wind_kmh','cloud_pct','condition_text','raw_json','fetched_at'],
    ],
    'ml_models' => [
        'key' => 'id',
        'columns' => ['id','model_name','model_path','accuracy_score','trained_at','is_active'],
    ],
    'ml_predictions' => [
        'key' => 'id',
        'columns' => ['id','farmer_id','weather_snapshot_json','input_features_json','predicted_class','confidence','recommendation_text','created_at'],
    ],
    'training_data' => [
        'key' => 'id',
        'columns' => ['id','temp_c','humidity_pct','rain_probability','wind_kmh','cloud_pct','condition_encoded','farmer_type_encoded','growth_stage','soil_type_encoded','season_encoded','label_class','source','created_at'],
    ],
    'sms_logs' => [
        'key' => 'id',
        'columns' => ['id','farmer_email','recipient','message','status','provider','provider_response_json','created_at'],
    ],
];

$counts = [];
foreach ($tables as $table => $_definition) {
    $counts[$table] = [
        'xampp' => (int)$source->query("SELECT COUNT(*) FROM {$table}")->fetchColumn(),
        'neon' => (int)$destination->query("SELECT COUNT(*) FROM {$table}")->fetchColumn(),
    ];
}

echo "Source database: {$sourceName}\nDestination database: agrisense\n";
foreach ($counts as $table => $count) {
    echo sprintf("%-18s XAMPP %8d | Neon %8d\n", $table, $count['xampp'], $count['neon']);
}

if (!in_array('--apply', $argv, true)) {
    echo "Preview only. Run with --apply to sync; Neon-only rows will be kept.\n";
    exit(0);
}

try {
    foreach ($tables as $table => $definition) {
        $columns = $definition['columns'];
        $key = $definition['key'];
        $quotedColumns = implode(',', array_map(static fn(string $column): string => '"' . $column . '"', $columns));
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $updates = array_values(array_filter($columns, static fn(string $column): bool => $column !== $key));
        $updateClause = implode(',', array_map(static fn(string $column): string => '"' . $column . '"=EXCLUDED."' . $column . '"', $updates));
        $conflictAction = $updateClause === '' ? 'DO NOTHING' : 'DO UPDATE SET ' . $updateClause;
        $upsert = $destination->prepare(
            "INSERT INTO {$table} ({$quotedColumns}) VALUES ({$placeholders}) ON CONFLICT (\"{$key}\") {$conflictAction}"
        );
        $sourceColumns = implode(',', array_map(static fn(string $column): string => '`' . $column . '`', $columns));
        $rows = $source->query("SELECT {$sourceColumns} FROM {$table}");
        $copied = 0;
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            try {
                $upsert->execute(array_values($row));
            } catch (Throwable $rowError) {
                $details = preg_replace('/(postgres(?:ql)?:\/\/[^:\s]+:)[^@\s]+@/i', '$1[redacted]@', $rowError->getMessage());
                fwrite(STDERR, "Failed on {$table} source row {$copied}: {$details}\n");
                throw $rowError;
            }
            $copied++;
        }
        echo "Synced {$table}: {$copied} source row(s).\n";

        if ($key === 'id') {
            $destination->query(
                "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE(MAX(id), 1), MAX(id) IS NOT NULL) FROM {$table}"
            );
        }
    }
    echo "Sync complete. Existing Neon-only rows were retained.\n";
} catch (Throwable $error) {
    $details = preg_replace('/(postgres(?:ql)?:\/\/[^:\s]+:)[^@\s]+@/i', '$1[redacted]@', $error->getMessage());
    fwrite(STDERR, "Sync stopped; previously written rows may already be committed. Error type: " . get_class($error) . ". Details: {$details}\n");
    exit(1);
}
