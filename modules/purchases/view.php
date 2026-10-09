<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Accès refusé ou ID manquant.']);
    exit;
}
if (!in_array($_SESSION['role'] ?? '', ['Super Admin', 'Admin', 'Gérant'])) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

$purchase_id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT p.*, s.name as supplier_name, u.full_name as user_name 
                           FROM purchases p 
                           LEFT JOIN suppliers s ON p.supplier_id = s.id 
                           LEFT JOIN users u ON p.created_by = u.id 
                           WHERE p.id = ?");
    $stmt->execute([$purchase_id]);
    $purchase = $stmt->fetch();

    if (!$purchase) {
        echo json_encode(['success' => false, 'message' => 'Achat introuvable.']);
        exit;
    }

    $stmt_items = $pdo->prepare("SELECT pd.*, pr.name as product_name 
                                 FROM purchase_details pd 
                                 JOIN products pr ON pd.product_id = pr.id 
                                 WHERE pd.purchase_id = ?");
    $stmt_items->execute([$purchase_id]);
    $items = $stmt_items->fetchAll();

    echo json_encode(['success' => true, 'purchase' => $purchase, 'items' => $items]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
