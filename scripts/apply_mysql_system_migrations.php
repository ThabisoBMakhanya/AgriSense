<?php
require_once __DIR__ . '/../includes/database.php';

if (db_driver() !== 'mysql') {
    fwrite(STDERR, "Use this script with a configured MySQL or MariaDB connection.\n");
    exit(1);
}

try {
    foreach (['upgrade_account_safety_mysql.sql', 'upgrade_farm_journal_mysql.sql', 'upgrade_farm_enterprises_mysql.sql'] as $migration) {
        $sql = file_get_contents(__DIR__ . '/../database/' . $migration);
        if ($sql === false) throw new RuntimeException('Could not read a migration file.');
        foreach (explode(';', $sql) as $statement) {
            $statement = trim(preg_replace('/^\s*--.*$/m', '', $statement));
            if ($statement !== '') db()->exec($statement);
        }
    }
    $tables = db()->query("SHOW TABLES LIKE 'auth_attempts'")->fetchColumn();
    if (!$tables) throw new RuntimeException('Authentication attempt table was not created.');
    $sessions = db()->query("SHOW TABLES LIKE 'auth_sessions'")->fetchColumn();
    if (!$sessions) throw new RuntimeException('Shared authentication session table was not created.');
    $columns = db()->query('SHOW COLUMNS FROM farm_enterprises LIKE "weather_location"')->fetchColumn();
    if (!$columns) throw new RuntimeException('Farm site weather location was not created.');
    echo "MySQL account safety and farm site migrations applied.\n";
} catch (Throwable $exception) {
    error_log('MySQL system migration failed: ' . $exception->getMessage());
    fwrite(STDERR, "Could not apply the migrations. Check the MySQL connection and database role.\n");
    exit(1);
}
