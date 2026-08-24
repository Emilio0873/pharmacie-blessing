<?php
/**
 * Global Utility Functions
 */

session_start();

/**
 * Redirect to a specific URL
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user has a specific role
 */
function has_role($role_name) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role_name;
}

/**
 * Authorize access for specific roles
 * @param array $allowed_roles
 */
function authorize($allowed_roles) {
    if (!is_logged_in()) {
        redirect('/PHARMACIE BLESSING/index.php');
    }
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        // Redirect to dashboard with error or just block
        header("Location: /PHARMACIE BLESSING/dashboard.php?error=unauthorized");
        exit();
    }
}

/**
 * Sanitize input
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Format currency
 */
function format_currency($amount) {
    return number_format($amount, 0, ',', ' ') . ' FC';
}

/**
 * Log activity
 */
function log_activity($pdo, $user_id, $action, $details = null) {
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $action, $details]);
}

/**
 * Get dynamic notifications
 */
function get_notifications($pdo) {
    $stmt = $pdo->query("SELECT * FROM notifications WHERE status = 'unread' ORDER BY created_at DESC LIMIT 5");
    return $stmt->fetchAll();
}

/**
 * Build invoice number from a numeric sale/invoice id.
 */
function format_invoice_number($id) {
    return 'FAC-' . str_pad((int)$id, 6, '0', STR_PAD_LEFT);
}

/**
 * Ensure invoicing tables exist before writing invoice records.
 */
function ensure_invoicing_tables($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL UNIQUE,
        invoice_number VARCHAR(50) NOT NULL UNIQUE,
        client_id INT NULL,
        user_id INT NOT NULL,
        subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
        discount DECIMAL(12,2) NOT NULL DEFAULT 0,
        tax_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
        tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        payment_mode VARCHAR(50) NOT NULL DEFAULT 'Espèces',
        legal_note VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_invoice_number (invoice_number),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS invoice_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        product_id INT NOT NULL,
        product_name VARCHAR(255) NOT NULL,
        product_code VARCHAR(100) NULL,
        qty INT NOT NULL,
        unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        line_discount DECIMAL(12,2) NOT NULL DEFAULT 0,
        line_tax DECIMAL(12,2) NOT NULL DEFAULT 0,
        line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_invoice_id (invoice_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Convert integer amount into french words (short scale, practical coverage).
 */
function amount_to_words_fr($amount) {
    $n = (int)round($amount);
    if ($n === 0) {
        return 'zero franc congolais';
    }

    $units = [
        0 => 'zero', 1 => 'un', 2 => 'deux', 3 => 'trois', 4 => 'quatre', 5 => 'cinq',
        6 => 'six', 7 => 'sept', 8 => 'huit', 9 => 'neuf', 10 => 'dix',
        11 => 'onze', 12 => 'douze', 13 => 'treize', 14 => 'quatorze', 15 => 'quinze',
        16 => 'seize'
    ];

    $tens = [
        20 => 'vingt', 30 => 'trente', 40 => 'quarante', 50 => 'cinquante',
        60 => 'soixante', 80 => 'quatre-vingt'
    ];

    $under100 = function($num) use (&$under100, $units, $tens) {
        if ($num <= 16) {
            return $units[$num];
        }
        if ($num < 20) {
            return 'dix-' . $units[$num - 10];
        }
        if ($num < 70) {
            $ten = (int)(floor($num / 10) * 10);
            $unit = $num % 10;
            if ($unit === 0) {
                return $tens[$ten];
            }
            if ($unit === 1) {
                return $tens[$ten] . '-et-un';
            }
            return $tens[$ten] . '-' . $units[$unit];
        }
        if ($num < 80) {
            if ($num === 71) {
                return 'soixante-et-onze';
            }
            return 'soixante-' . $under100($num - 60);
        }
        if ($num === 80) {
            return 'quatre-vingts';
        }
        if ($num < 100) {
            return 'quatre-vingt-' . $under100($num - 80);
        }
        return '';
    };

    $under1000 = function($num) use (&$under1000, $under100, $units) {
        if ($num < 100) {
            return $under100($num);
        }
        $hundreds = (int)floor($num / 100);
        $rest = $num % 100;

        if ($hundreds === 1) {
            $prefix = 'cent';
        } else {
            $prefix = $units[$hundreds] . ' cent';
        }

        if ($rest === 0 && $hundreds > 1) {
            return $prefix . 's';
        }

        if ($rest > 0) {
            return $prefix . ' ' . $under100($rest);
        }

        return $prefix;
    };

    $chunks = [
        1000000000 => 'milliard',
        1000000 => 'million',
        1000 => 'mille',
        1 => ''
    ];

    $words = [];
    $remaining = $n;

    foreach ($chunks as $value => $label) {
        if ($remaining >= $value) {
            $chunk = (int)floor($remaining / $value);
            $remaining = $remaining % $value;

            if ($value === 1000) {
                if ($chunk === 1) {
                    $words[] = 'mille';
                } else {
                    $words[] = $under1000($chunk) . ' mille';
                }
            } elseif ($value >= 1000000) {
                $part = ($chunk === 1) ? 'un ' . $label : $under1000($chunk) . ' ' . $label . 's';
                $words[] = $part;
            } else {
                if ($chunk > 0) {
                    $words[] = $under1000($chunk);
                }
            }
        }
    }

    return trim(implode(' ', $words)) . ' francs congolais';
}

/**
 * Ensure products.lot_number exists (batch identification for stock entries).
 */
function ensure_product_lot_column($pdo) {
    static $done = false;
    if ($done) {
        return;
    }
    $cols = $pdo->query("SHOW COLUMNS FROM products LIKE 'lot_number'")->fetch();
    if (!$cols) {
        $pdo->exec("ALTER TABLE products ADD COLUMN lot_number VARCHAR(100) NULL AFTER brand");
        $pdo->exec("ALTER TABLE products ADD INDEX idx_products_lot (lot_number)");
    }
    $done = true;
}

/**
 * Ensure generic application settings table exists.
 */
function ensure_app_settings_table($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(120) NOT NULL UNIQUE,
        setting_value TEXT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Read an application setting value.
 */
function get_app_setting($pdo, $key, $default = '') {
    ensure_app_settings_table($pdo);
    $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value !== false && $value !== null && $value !== '' ? $value : $default;
}

/**
 * Create or update an application setting.
 */
function set_app_setting($pdo, $key, $value) {
    ensure_app_settings_table($pdo);
    $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP");
    $stmt->execute([$key, $value]);
}

/**
 * Get pharmacy business profile used on generated invoices.
 */
function get_business_profile($pdo) {
    return [
        'name' => get_app_setting($pdo, 'business_name', 'PHARMACIE BLESSING'),
        'address' => get_app_setting($pdo, 'business_address', 'Kinshasa, République Démocratique du Congo'),
        'phone' => get_app_setting($pdo, 'business_phone', '+243 972 573 971'),
        'email' => get_app_setting($pdo, 'business_email', 'contact@blessingpharmacie.com'),
        'subtitle' => get_app_setting($pdo, 'business_subtitle', 'Dépôt Pharmaceutique de Référence'),
        'legal_ids' => get_app_setting($pdo, 'business_legal_ids', 'RCCM: CD/KNG/RCCM/20-B-00123 | NIF: A2203947T')
    ];
}
?>
