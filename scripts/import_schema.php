<?php
/**
 * One-shot schema import for Railway / production.
 * Usage: php scripts/import_schema.php
 */
require_once __DIR__ . '/../config/db.php';

$schemaFile = __DIR__ . '/railway_schema.sql';
if (!is_file($schemaFile)) {
    fwrite(STDERR, "Schema file not found: $schemaFile\n");
    exit(1);
}

$sql = file_get_contents($schemaFile);
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "Schema file is empty.\n");
    exit(1);
}

// Strip UTF-8 BOM if present
$sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);

// Remove line comments
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

try {
    $count = 0;
    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
        $count++;
    }

    echo "OK: executed $count statements.\n";
    $roles = (int)$pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
    $users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "roles=$roles users=$users\n";
    echo "Default login: admin / admin123\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
