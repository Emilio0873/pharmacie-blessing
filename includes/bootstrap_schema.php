<?php
/**
 * Ensure core tables exist (first boot on Railway / empty MySQL).
 */
function bootstrap_schema_if_needed(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo->query("SELECT 1 FROM roles LIMIT 1");
        return; // already initialized
    } catch (Throwable $e) {
        // continue to import
    }

    $schemaFile = __DIR__ . '/../scripts/railway_schema.sql';
    if (!is_file($schemaFile)) {
        return;
    }

    $sql = file_get_contents($schemaFile);
    if ($sql === false) {
        return;
    }

    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
    $lines = preg_split("/\r\n|\n|\r/", $sql);
    $cleaned = [];
    foreach ($lines as $line) {
        $trim = ltrim($line);
        if ($trim === '' || str_starts_with($trim, '--')) {
            continue;
        }
        $cleaned[] = $line;
    }
    $sql = implode("\n", $cleaned);
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }
}
