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

    $needsImport = false;
    try {
        $pdo->query("SELECT 1 FROM roles LIMIT 1");
    } catch (Throwable $e) {
        $needsImport = true;
    }

    if (!$needsImport) {
        seed_default_categories($pdo);
        sync_business_phone($pdo);
        return;
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

    seed_default_categories($pdo);
    sync_business_phone($pdo);
}

function sync_business_phone(PDO $pdo): void {
    try {
        $pdo->prepare("UPDATE app_settings SET setting_value = ? WHERE setting_key = 'business_phone' AND setting_value = ?")
            ->execute(['0965431594', '+243 972 573 971']);
    } catch (Throwable $e) {
        // settings table may not exist yet
    }
}

/**
 * Pharmacy categories used when the table is still empty.
 */
function seed_default_categories(PDO $pdo): void {
    try {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    } catch (Throwable $e) {
        return;
    }
    if ($count > 0) {
        return;
    }

    $categories = [
        ['Antalgiques', 'Douleurs et fièvre'],
        ['Antibiotiques', 'Infections bactériennes'],
        ['Antihypertenseurs', 'Tension artérielle'],
        ['Antipaludéens', 'Traitement et prévention du paludisme'],
        ['Vitamines', 'Compléments et vitamines'],
        ['Soins et pansements', 'Hygiène, pansements et premiers soins'],
        ['Divers', 'Autres produits'],
    ];

    $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
    foreach ($categories as $category) {
        $stmt->execute($category);
    }
}
