<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}
if (!in_array($_SESSION['role'] ?? '', ['Super Admin', 'Admin', 'Magasinier'])) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['cart'])) {
    echo json_encode(['success' => false, 'message' => 'Commande vide']);
    exit();
}

try {
    $pdo->beginTransaction();

    $supplier_id = !empty($data['supplier_id']) ? (int)$data['supplier_id'] : null;
    $total_amount = (float)$data['total_amount'];
    $user_id = $_SESSION['user_id'];
    // Status is 'received' meaning it directly enters stock
    $status = 'received';

    // Insert Purchase
    $stmt = $pdo->prepare("INSERT INTO purchases (supplier_id, total_amount, status, created_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$supplier_id, $total_amount, $status, $user_id]);
    $purchase_id = $pdo->lastInsertId();

    foreach ($data['cart'] as $item) {
        $product_id = (int)$item['id'];
        $qty = (int)$item['qty'];
        $buy_price = (float)$item['price'];

        // Insert Purchase Details
        $stmt_det = $pdo->prepare("INSERT INTO purchase_details (purchase_id, product_id, qty, buy_price) VALUES (?, ?, ?, ?)");
        $stmt_det->execute([$purchase_id, $product_id, $qty, $buy_price]);

        // Update Stock and Update Buy Price for Product
        $stmt_stock = $pdo->prepare("UPDATE products SET qty = qty + ?, buy_price = ? WHERE id = ?");
        $stmt_stock->execute([$qty, $buy_price, $product_id]);

        // Log Stock Movement
        $stmt_mv = $pdo->prepare("INSERT INTO stock_movements (product_id, type, qty, user_id, reference_id, notes) VALUES (?, 'IN', ?, ?, ?, 'Achat Fournisseur')");
        $stmt_mv->execute([$product_id, $qty, $user_id, $purchase_id]);
    }

    log_activity($pdo, $user_id, 'Achat effectué', "Achat ID: $purchase_id, Montant: $total_amount");
    
    $pdo->commit();
    echo json_encode(['success' => true, 'purchase_id' => $purchase_id]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>
