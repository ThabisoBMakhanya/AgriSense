<?php
require_once __DIR__ . '/../includes/database.php';

if (db_driver() !== 'pgsql') {
    fwrite(STDERR, "Set DATABASE_URL to the Neon PostgreSQL database before running this migration.\n");
    exit(1);
}

try {
    foreach (['upgrade_account_safety.sql', 'upgrade_farm_journal.sql', 'upgrade_journal_domains.sql', 'upgrade_farm_enterprises.sql'] as $migration) {
        $sql = file_get_contents(__DIR__ . '/../database/' . $migration);
        if ($sql === false) throw new RuntimeException('Could not read a farm journal migration.');
        foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
            $statement = trim(preg_replace('/^\s*--.*$/m', '', $statement));
            if ($statement !== '') db()->exec($statement);
        }
    }
    $tables = db()->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_name IN ('auth_attempts','auth_sessions','farm_tasks','farm_records','field_cases','animal_records','farm_inventory','farm_stock_movements','farm_journal_preferences','field_harvests','farm_enterprises') ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
    $columns = db()->query("SELECT table_name || '.' || column_name FROM information_schema.columns WHERE table_schema='public' AND (table_name, column_name) IN (('users','recovery_code_hash'),('farm_enterprises','weather_location'),('farm_tasks','record_scope'),('farm_records','record_scope'),('field_cases','subject_type')) ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
    echo 'Neon journal tables present: ' . implode(', ', $tables) . PHP_EOL;
    echo 'Journal category columns present: ' . implode(', ', $columns) . PHP_EOL;
} catch (Throwable $exception) {
    error_log('Farm journal schema migration failed: ' . $exception->getMessage());
    fwrite(STDERR, "Could not apply the farm journal migration. Check the Neon connection and database role.\n");
    exit(1);
}
