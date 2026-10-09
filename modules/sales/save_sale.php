<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}
if (!in_array($_SESSION['role'] ?? '', ['Super Admin', 'Admin', 'Caissier'])) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['cart'])) {
    echo json_encode(['success' => false, 'message' => 'Panier vide']);
    exit();
}

try {
    // Must run BEFORE the transaction: CREATE TABLE causes an implicit commit in MySQL.
    ensure_invoicing_tables($pdo);
    ensure_delivery_status_column($pdo);
    ensure_sale_fulfillment_columns($pdo);

    $pdo->beginTransaction();

    $client_id = !empty($data['client_id']) ? (int)$data['client_id'] : null;
    $subtotal = (float)$data['total_amount'];
    $discount = (float)$data['discount'];
    $tax_rate = isset($data['tax_rate']) ? max(0, (float)$data['tax_rate']) : 0;
    $taxable_base = max(0, $subtotal - $discount);
    $tax_amount = round(($taxable_base * $tax_rate) / 100, 2);
    $final_amount = $taxable_base + $tax_amount;
    $payment_mode = !empty($data['payment_mode']) ? sanitize($data['payment_mode']) : 'Espèces';
    $legal_note = !empty($data['legal_note']) ? sanitize($data['legal_note']) : 'Les médicaments vendus ne sont ni repris ni échangés.';
    $user_id = (int)$_SESSION['user_id'];

    // Validate stock before writing
    foreach ($data['cart'] as $item) {
        $product_id = (int)$item['id'];
        $qty = (int)$item['qty'];
        if ($product_id <= 0 || $qty <= 0) {
            throw new Exception('Article invalide dans le panier.');
        }
        $stmt_check = $pdo->prepare("SELECT name, qty FROM products WHERE id = ? FOR UPDATE");
        $stmt_check->execute([$product_id]);
        $product = $stmt_check->fetch();
        if (!$product) {
            throw new Exception("Produit introuvable (#$product_id).");
        }
        if ((int)$product['qty'] < $qty) {
            throw new Exception("Stock insuffisant pour « {$product['name']} » (dispo: {$product['qty']}).");
        }
    }

    $stmt = $pdo->prepare("INSERT INTO sales (client_id, user_id, total_amount, discount, final_amount, delivery_status, fulfillment_type)
        VALUES (?, ?, ?, ?, ?, 'a_preparer', 'retrait_depot')");
    $stmt->execute([$client_id, $user_id, $subtotal, $discount, $final_amount]);
    $sale_id = (int)$pdo->lastInsertId();

    $productIds = array_map(function ($item) {
        return (int)$item['id'];
    }, $data['cart']);

    $productMeta = [];
    if (!empty($productIds)) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmt_prod = $pdo->prepare("SELECT id, name, code FROM products WHERE id IN ($placeholders)");
        $stmt_prod->execute($productIds);
        foreach ($stmt_prod->fetchAll() as $p) {
            $productMeta[(int)$p['id']] = $p;
        }
    }

    $invoiceLines = [];
    foreach ($data['cart'] as $item) {
        $product_id = (int)$item['id'];
        $qty = (int)$item['qty'];
        $price = (float)$item['price'];

        $stmt_det = $pdo->prepare("INSERT INTO sale_details (sale_id, product_id, qty, unit_price) VALUES (?, ?, ?, ?)");
        $stmt_det->execute([$sale_id, $product_id, $qty, $price]);

        $stmt_stock = $pdo->prepare("UPDATE products SET qty = qty - ? WHERE id = ? AND qty >= ?");
        $stmt_stock->execute([$qty, $product_id, $qty]);
        if ($stmt_stock->rowCount() === 0) {
            $pname = $productMeta[$product_id]['name'] ?? "#$product_id";
            throw new Exception("Stock insuffisant pour « $pname ».");
        }

        $stmt_mv = $pdo->prepare("INSERT INTO stock_movements (product_id, type, qty, user_id, reference_id, notes) VALUES (?, 'OUT', ?, ?, ?, 'Vente POS')");
        $stmt_mv->execute([$product_id, $qty, $user_id, $sale_id]);

        $invoiceLines[] = [
            'product_id' => $product_id,
            'product_name' => $productMeta[$product_id]['name'] ?? ('Produit #' . $product_id),
            'product_code' => $productMeta[$product_id]['code'] ?? null,
            'qty' => $qty,
            'unit_price' => $price,
            'line_total' => $qty * $price
        ];
    }

    $invoice_number = format_invoice_number($sale_id);

    $stmt_invoice = $pdo->prepare("INSERT INTO invoices
        (sale_id, invoice_number, client_id, user_id, subtotal, discount, tax_rate, tax_amount, total_amount, payment_mode, legal_note)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_invoice->execute([
        $sale_id,
        $invoice_number,
        $client_id,
        $user_id,
        $subtotal,
        $discount,
        $tax_rate,
        $tax_amount,
        $final_amount,
        $payment_mode,
        $legal_note
    ]);
    $invoice_id = (int)$pdo->lastInsertId();

    if (!empty($invoiceLines)) {
        $stmt_invoice_line = $pdo->prepare("INSERT INTO invoice_items
            (invoice_id, product_id, product_name, product_code, qty, unit_price, line_discount, line_tax, line_total)
            VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?)");

        foreach ($invoiceLines as $line) {
            $stmt_invoice_line->execute([
                $invoice_id,
                $line['product_id'],
                $line['product_name'],
                $line['product_code'],
                $line['qty'],
                $line['unit_price'],
                $line['line_total']
            ]);
        }
    }

    log_activity($pdo, $user_id, 'Vente effectuée', "Vente ID: $sale_id, Facture: $invoice_number, Montant TTC: $final_amount");

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }

    deliver_invoice_to_client($pdo, (int)$sale_id);

    echo json_encode([
        'success' => true,
        'sale_id' => $sale_id,
        'invoice_number' => $invoice_number,
        'auto_send' => 1
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
