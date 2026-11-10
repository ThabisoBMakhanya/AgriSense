<?php
declare(strict_types=1);

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

$params = [];
parse_str($parts['query'] ?? '', $params);
$channelBinding = preg_replace('/[^a-z]/', '', (string)($params['channel_binding'] ?? 'prefer'));
putenv('PGCHANNELBINDING=' . ($channelBinding !== '' ? $channelBinding : 'prefer'));
$dsn = sprintf(
    'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
    $parts['host'],
    (int)($parts['port'] ?? 5432),
    $databaseName,
    preg_replace('/[^a-z]/', '', (string)($params['sslmode'] ?? 'require')) ?: 'require'
);

try {
    $db = new PDO($dsn, rawurldecode($parts['user'] ?? ''), rawurldecode($parts['pass'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $exports = [
        'ml_predictions.csv' => [
            'SELECT id, weather_snapshot_json::text AS weather_snapshot_json, input_features_json::text AS input_features_json, predicted_class, confidence, recommendation_text, created_at FROM ml_predictions ORDER BY id',
            'id','weather_snapshot_json','input_features_json','predicted_class','confidence','recommendation_text','created_at',
        ],
        'training_data.csv' => [
            'SELECT id, temp_c, humidity_pct, rain_probability, wind_kmh, cloud_pct, condition_encoded, farmer_type_encoded, growth_stage, soil_type_encoded, season_encoded, label_class, source, created_at FROM training_data ORDER BY id',
            'id','temp_c','humidity_pct','rain_probability','wind_kmh','cloud_pct','condition_encoded','farmer_type_encoded','growth_stage','soil_type_encoded','season_encoded','label_class','source','created_at',
        ],
        'weather_cache.csv' => [
            'SELECT location, temp_c, humidity_pct, rain_probability, wind_kmh, cloud_pct, condition_text, raw_json::text AS raw_json, fetched_at FROM weather_cache ORDER BY location',
            'location','temp_c','humidity_pct','rain_probability','wind_kmh','cloud_pct','condition_text','raw_json','fetched_at',
        ],
    ];

    $outputDir = __DIR__ . '/../exports';
    if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
        throw new RuntimeException('Could not create the export directory.');
    }

    foreach ($exports as $fileName => $export) {
        $sql = $export[0];
        $columns = array_slice($export, 1);
        $path = $outputDir . '/' . $fileName;
        $file = fopen($path, 'wb');
        if ($file === false) throw new RuntimeException('Could not create an export file.');
        fputcsv($file, $columns);
        $statement = $db->query($sql);
        $count = 0;
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $values = [];
            foreach ($columns as $column) $values[] = $row[$column] ?? '';
            fputcsv($file, $values);
            $count++;
        }
        fclose($file);
        echo "Exported {$count} row(s) to exports/{$fileName}.\n";
    }

    file_put_contents(
        $outputDir . '/README.md',
        "# agriSense ML data export\n\nThe Neon database exports omit farmer email addresses. `training_data.csv` contains labeled examples stored in Neon. The `synthetic_*_training.csv` files contain the generated examples used by the current models; these are synthetic, not recorded farm outcomes.\n"
    );
} catch (Throwable $error) {
    $details = preg_replace('/(postgres(?:ql)?:\/\/[^:\s]+:)[^@\s]+@/i', '$1[redacted]@', $error->getMessage());
    fwrite(STDERR, "Could not export ML data. Error type: " . get_class($error) . ". Details: {$details}\n");
    exit(1);
}
