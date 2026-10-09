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
        ensure_reservation_tables($pdo);
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
    ensure_reservation_tables($pdo);
}

function ensure_reservation_tables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reference VARCHAR(30) NOT NULL UNIQUE,
        last_name VARCHAR(100) NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        phone VARCHAR(30) NOT NULL,
        email VARCHAR(150) NULL,
        address VARCHAR(255) NULL,
        pickup_date DATE NOT NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'en_attente',
        subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_reservations_phone (phone),
        INDEX idx_reservations_pickup (pickup_date),
        INDEX idx_reservations_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS reservation_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reservation_id INT NOT NULL,
        product_id INT NOT NULL,
        product_name VARCHAR(255) NOT NULL,
        product_code VARCHAR(100) NULL,
        qty INT NOT NULL,
        unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
        INDEX idx_reservation_items_reservation (reservation_id),
        CONSTRAINT fk_reservation_items_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id)
            ON UPDATE CASCADE ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function sync_business_phone(PDO $pdo): void {
    try {
        $stmt = $pdo->prepare("UPDATE app_settings SET setting_value = ? WHERE setting_key = 'business_phone' AND setting_value IN (?, ?, ?)");
        $stmt->execute(['0965431594', '+243 972 573 971', '+243 965 431 594', '0965 431 594']);
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
