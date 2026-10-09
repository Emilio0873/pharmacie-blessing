<?php
/**
 * Global Utility Functions
 */

session_start();

/**
 * Application base URL path (empty on Railway root, local XAMPP subfolder by default).
 */
function app_base_url($path = '') {
    static $root = null;
    if ($root === null) {
        $configured = getenv('APP_BASE_URL');
        if ($configured !== false) {
            $root = rtrim(str_replace('\\', '/', $configured), '/');
        } elseif (getenv('RAILWAY_ENVIRONMENT') || getenv('RAILWAY_PROJECT_ID') || getenv('MYSQLHOST')) {
            $root = '';
        } else {
            $root = '/PHARMACIE%20BLESSING';
        }
    }

    if ($path === '' || $path === '/') {
        return $root === '' ? '/' : $root . '/';
    }

    return $root . '/' . ltrim($path, '/');
}

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

function home_path_for_role($role = null) {
    $role = $role ?? ($_SESSION['role'] ?? '');
    return match ($role) {
        'Caissier' => 'modules/caisse/index.php',
        'Gérant' => 'modules/products/index.php',
        'Facturier' => 'modules/facturation/index.php',
        'Livreur' => 'modules/livraisons/index.php',
        default => 'dashboard.php',
    };
}

/**
 * Authorize access for specific roles
 * @param array $allowed_roles
 */
function authorize($allowed_roles) {
    if (!is_logged_in()) {
        redirect(app_base_url('index.php'));
    }
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        header('Location: ' . app_base_url('dashboard.php?error=unauthorized'));
        exit();
    }
}

/**
 * Sanitize input
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function csrf_valid() {
    $sent = $_POST['csrf_token'] ?? '';
    $known = $_SESSION['csrf_token'] ?? '';
    return is_string($sent) && $known !== '' && hash_equals($known, $sent);
}

function csrf_delete_form($id, $confirm, $buttonClass = 'btn btn-sm btn-light text-danger') {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars(json_encode($confirm, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    $class = htmlspecialchars($buttonClass, ENT_QUOTES, 'UTF-8');
    return '<form method="post" class="d-inline" onsubmit="return confirm(' . $message . ')">'
        . '<input type="hidden" name="csrf_token" value="' . $token . '">'
        . '<input type="hidden" name="delete_id" value="' . (int)$id . '">'
        . '<button type="submit" class="' . $class . '" title="Supprimer"><i class="fas fa-trash"></i></button>'
        . '</form>';
}

/**
 * Turn a database exception into a short message for the user.
 */
function db_user_message($e, $duplicate = 'Cette valeur existe déjà.', $missing = 'Un champ obligatoire est manquant.') {
    $msg = $e instanceof Throwable ? $e->getMessage() : (string)$e;
    if (str_contains($msg, '1062') || stripos($msg, 'Duplicate') !== false) {
        return $duplicate;
    }
    if (str_contains($msg, '1048') || stripos($msg, 'cannot be null') !== false) {
        return $missing;
    }
    if (str_contains($msg, '1452')) {
        return 'La valeur liée (catégorie, client ou fournisseur) n\'existe pas.';
    }
    if (str_contains($msg, '1451')) {
        return 'Impossible : cet élément est encore utilisé ailleurs.';
    }
    return 'Une erreur est survenue. Vérifiez les champs et réessayez.';
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
    try {
        $stmt = $pdo->query("SELECT * FROM notifications WHERE status = 'unread' ORDER BY created_at DESC LIMIT 5");
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
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
        'address' => get_app_setting($pdo, 'business_address', 'Kinshasa, République démocratique du Congo'),
        'phone' => get_app_setting($pdo, 'business_phone', '+243 965 431 594'),
        'email' => get_app_setting($pdo, 'business_email', 'contact@blessingpharmacie.com'),
        'subtitle' => get_app_setting($pdo, 'business_subtitle', 'Dépôt pharmaceutique'),
        'legal_ids' => get_app_setting($pdo, 'business_legal_ids', 'RCCM: CD/KNG/RCCM/20-B-00123 | NIF: A2203947T')
    ];
}

function format_reservation_number($id) {
    return 'RES-' . str_pad((int)$id, 6, '0', STR_PAD_LEFT);
}

function format_counter_order_number($id) {
    return 'CMD-' . str_pad((int)$id, 6, '0', STR_PAD_LEFT);
}

function reservation_phone_key($phone) {
    return preg_replace('/\D+/', '', (string)$phone);
}

function find_or_create_client($pdo, $name, $phone, $email = null, $address = null) {
    $find = $pdo->prepare("SELECT id FROM clients WHERE phone = ? LIMIT 1");
    $find->execute([$phone]);
    $id = $find->fetchColumn();
    if ($id) {
        return (int)$id;
    }
    $pdo->prepare("INSERT INTO clients (name, phone, email, address) VALUES (?, ?, ?, ?)")
        ->execute([$name, $phone, $email ?: null, $address ?: null]);
    return (int)$pdo->lastInsertId();
}

/**
 * Create a paid sale + invoice + stock movements from cart lines.
 * Expects to run inside an open transaction.
 */
function create_paid_sale_from_lines(PDO $pdo, array $lines, array $meta) {
    ensure_invoicing_tables($pdo);
    ensure_delivery_status_column($pdo);
    ensure_sale_fulfillment_columns($pdo);

    if (empty($lines)) {
        throw new Exception('Aucun produit à encaisser.');
    }

    foreach ($lines as $item) {
        $productId = (int)$item['product_id'];
        $qty = (int)$item['qty'];
        $check = $pdo->prepare("SELECT name, qty FROM products WHERE id = ? FOR UPDATE");
        $check->execute([$productId]);
        $product = $check->fetch();
        if (!$product) {
            throw new Exception('Produit introuvable : ' . ($item['product_name'] ?? "#$productId"));
        }
        if ((int)$product['qty'] < $qty) {
            throw new Exception("Stock insuffisant pour « {$product['name']} » (dispo: {$product['qty']}).");
        }
    }

    $clientId = !empty($meta['client_id']) ? (int)$meta['client_id'] : null;
    $userId = (int)$meta['user_id'];
    $subtotal = (float)$meta['subtotal'];
    $discount = (float)($meta['discount'] ?? 0);
    $final = max(0, $subtotal - $discount);
    $fulfillment = ($meta['fulfillment_type'] ?? 'retrait_depot') === 'livraison_domicile' ? 'livraison_domicile' : 'retrait_depot';
    $geoLat = isset($meta['geo_lat']) && $meta['geo_lat'] !== '' && $meta['geo_lat'] !== null ? (float)$meta['geo_lat'] : null;
    $geoLng = isset($meta['geo_lng']) && $meta['geo_lng'] !== '' && $meta['geo_lng'] !== null ? (float)$meta['geo_lng'] : null;
    $pickupDate = !empty($meta['pickup_date']) ? $meta['pickup_date'] : null;
    $commune = $meta['location_commune'] ?? null;
    $avenue = $meta['location_avenue'] ?? null;
    $landmark = $meta['location_landmark'] ?? null;
    $deliveryNote = $meta['stock_note'] ?? 'Vente';
    $initialDelivery = 'a_preparer';

    $pdo->prepare("INSERT INTO sales
        (client_id, user_id, total_amount, discount, final_amount, delivery_status, fulfillment_type, geo_lat, geo_lng, pickup_date, location_commune, location_avenue, location_landmark)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$clientId, $userId, $subtotal, $discount, $final, $initialDelivery, $fulfillment, $geoLat, $geoLng, $pickupDate, $commune, $avenue, $landmark]);
    $saleId = (int)$pdo->lastInsertId();

    foreach ($lines as $item) {
        $productId = (int)$item['product_id'];
        $qty = (int)$item['qty'];
        $price = (float)$item['unit_price'];
        $pdo->prepare("INSERT INTO sale_details (sale_id, product_id, qty, unit_price) VALUES (?, ?, ?, ?)")
            ->execute([$saleId, $productId, $qty, $price]);
        $stock = $pdo->prepare("UPDATE products SET qty = qty - ? WHERE id = ? AND qty >= ?");
        $stock->execute([$qty, $productId, $qty]);
        if ($stock->rowCount() === 0) {
            throw new Exception('Stock insuffisant pour « ' . ($item['product_name'] ?? "#$productId") . ' ».');
        }
        $pdo->prepare("INSERT INTO stock_movements (product_id, type, qty, user_id, reference_id, notes)
            VALUES (?, 'OUT', ?, ?, ?, ?)")
            ->execute([$productId, $qty, $userId, $saleId, $deliveryNote]);
    }

    $invoiceNumber = format_invoice_number($saleId);
    $pdo->prepare("INSERT INTO invoices
        (sale_id, invoice_number, client_id, user_id, subtotal, discount, tax_rate, tax_amount, total_amount, payment_mode, legal_note)
        VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, 'Espèces', ?)")
        ->execute([
            $saleId,
            $invoiceNumber,
            $clientId,
            $userId,
            $subtotal,
            $discount,
            $final,
            'Les médicaments vendus ne sont ni repris ni échangés.',
        ]);
    $invoiceId = (int)$pdo->lastInsertId();

    $lineStmt = $pdo->prepare("INSERT INTO invoice_items
        (invoice_id, product_id, product_name, product_code, qty, unit_price, line_discount, line_tax, line_total)
        VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?)");
    foreach ($lines as $item) {
        $lineStmt->execute([
            $invoiceId,
            (int)$item['product_id'],
            $item['product_name'],
            $item['product_code'] ?? null,
            (int)$item['qty'],
            (float)$item['unit_price'],
            (float)$item['line_total'],
        ]);
    }

    return [
        'sale_id' => $saleId,
        'invoice_number' => $invoiceNumber,
        'final_amount' => $final,
    ];
}

function maps_url($lat, $lng) {
    if ($lat === null || $lng === null || $lat === '' || $lng === '') {
        return null;
    }
    return 'https://www.google.com/maps?q=' . rawurlencode((float)$lat . ',' . (float)$lng);
}

function build_location_address($commune, $avenue, $landmark, $extra = '') {
    $parts = array_filter([
        trim((string)$commune),
        trim((string)$avenue),
        trim((string)$landmark) !== '' ? 'Repère: ' . trim((string)$landmark) : '',
        trim((string)$extra),
    ]);
    return implode(' — ', $parts);
}

function app_absolute_url($path = '') {
    $forwarded = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $https = $forwarded === 'https'
        || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(app_base_url(), '/');
    if ($base === '/') {
        $base = '';
    }
    return ($https ? 'https' : 'http') . '://' . $host . $base . '/' . ltrim((string)$path, '/');
}

function phone_to_whatsapp($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '0')) {
        $digits = '243' . substr($digits, 1);
    }
    return $digits;
}

function ensure_invoice_share_token(PDO $pdo, $invoiceId) {
    ensure_invoice_share_column($pdo);
    $invoiceId = (int)$invoiceId;
    $stmt = $pdo->prepare("SELECT share_token FROM invoices WHERE id = ?");
    $stmt->execute([$invoiceId]);
    $token = $stmt->fetchColumn();
    if (is_string($token) && $token !== '') {
        return $token;
    }
    $token = bin2hex(random_bytes(16));
    $pdo->prepare("UPDATE invoices SET share_token = ? WHERE id = ?")->execute([$token, $invoiceId]);
    return $token;
}

function invoice_share_links(PDO $pdo, $saleId, $clientPhone = '', $clientEmail = '') {
    ensure_invoicing_tables($pdo);
    $saleId = (int)$saleId;
    $inv = $pdo->prepare("SELECT id, invoice_number FROM invoices WHERE sale_id = ? LIMIT 1");
    $inv->execute([$saleId]);
    $row = $inv->fetch();
    if (!$row) {
        return null;
    }
    $token = ensure_invoice_share_token($pdo, (int)$row['id']);
    $url = app_absolute_url('facture.php?t=' . urlencode($token));
    $label = $row['invoice_number'] ?: format_invoice_number($saleId);
    $text = "Pharmacie Blessing — Votre facture $label : $url";
    $waPhone = phone_to_whatsapp($clientPhone);
    return [
        'url' => $url,
        'invoice_number' => $label,
        'message' => $text,
        'email' => $clientEmail !== ''
            ? 'mailto:' . rawurlencode($clientEmail) . '?subject=' . rawurlencode("Facture $label - Pharmacie Blessing") . '&body=' . rawurlencode($text)
            : 'mailto:?subject=' . rawurlencode("Facture $label - Pharmacie Blessing") . '&body=' . rawurlencode($text),
        'whatsapp' => $waPhone !== ''
            ? 'https://wa.me/' . $waPhone . '?text=' . rawurlencode($text)
            : 'https://wa.me/?text=' . rawurlencode($text),
        'sms' => 'sms:' . rawurlencode($clientPhone) . '?body=' . rawurlencode($text),
    ];
}

function is_client_logged_in() {
    return !empty($_SESSION['client_account_id']);
}

function require_client_login() {
    if (!is_client_logged_in()) {
        redirect(app_base_url('client_login.php'));
    }
}

/**
 * Send invoice link to client by email (and prepare WhatsApp/SMS channels).
 */
function deliver_invoice_to_client(PDO $pdo, $saleId) {
    $saleId = (int)$saleId;
    $stmt = $pdo->prepare("SELECT s.id, c.name as client_name, c.phone as client_phone, c.email as client_email,
                                  inv.invoice_number, inv.total_amount
                           FROM sales s
                           LEFT JOIN clients c ON c.id = s.client_id
                           LEFT JOIN invoices inv ON inv.sale_id = s.id
                           WHERE s.id = ? LIMIT 1");
    $stmt->execute([$saleId]);
    $sale = $stmt->fetch();
    if (!$sale) {
        return ['ok' => false, 'email_sent' => false, 'channels' => []];
    }

    $phone = (string)($sale['client_phone'] ?? '');
    $email = trim((string)($sale['client_email'] ?? ''));
    $share = invoice_share_links($pdo, $saleId, $phone, $email);
    if (!$share) {
        return ['ok' => false, 'email_sent' => false, 'channels' => []];
    }

    $emailSent = false;
    $channels = [];
    $business = get_business_profile($pdo);
    $from = $business['email'] ?: 'noreply@blessingpharmacie.com';
    $label = $share['invoice_number'];
    $amount = number_format((float)($sale['total_amount'] ?? 0), 0, ',', ' ');

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $subject = "Facture $label — Pharmacie Blessing";
        $body = "Bonjour " . ($sale['client_name'] ?: 'Client') . ",\n\n"
            . "Votre paiement a été confirmé.\n"
            . "Facture : $label\n"
            . "Montant : $amount FC\n\n"
            . "Consultez votre facture ici :\n{$share['url']}\n\n"
            . "Pharmacie Blessing — Dépôt pharmaceutique\n"
            . $business['phone'] . "\n";
        $headers = "From: Pharmacie Blessing <$from>\r\n"
            . "Reply-To: $from\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n";
        $emailSent = @mail($email, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
        if ($emailSent) {
            $channels[] = 'email';
        }
    }

    if ($phone !== '') {
        $channels[] = 'whatsapp';
        $channels[] = 'sms';
    }

    $_SESSION['invoice_delivery'] = [
        'sale_id' => $saleId,
        'email_sent' => $emailSent,
        'email' => $email,
        'phone' => $phone,
        'whatsapp' => $share['whatsapp'],
        'sms' => $share['sms'],
        'url' => $share['url'],
        'auto_whatsapp' => $phone !== '',
        'auto_sms' => $phone !== '' && !$emailSent,
    ];

    return [
        'ok' => true,
        'email_sent' => $emailSent,
        'channels' => $channels,
        'share' => $share,
    ];
}
?>
