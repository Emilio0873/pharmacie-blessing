<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Accès refusé ou ID manquant.']);
    exit;
}
if (!in_array($_SESSION['role'] ?? '', ['Super Admin', 'Admin', 'Caissier', 'Facturier'])) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
    exit;
}

$sale_id = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT s.*, c.name as client_name, u.full_name as user_name 
                           FROM sales s 
                           LEFT JOIN clients c ON s.client_id = c.id 
                           LEFT JOIN users u ON s.user_id = u.id 
                           WHERE s.id = ?");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch();

    if (!$sale) {
        echo json_encode(['success' => false, 'message' => 'Vente introuvable.']);
        exit;
    }

    $stmt_items = $pdo->prepare("SELECT sd.*, p.name as product_name 
                                 FROM sale_details sd 
                                 JOIN products p ON sd.product_id = p.id 
                                 WHERE sd.sale_id = ?");
    $stmt_items->execute([$sale_id]);
    $items = $stmt_items->fetchAll();

    echo json_encode(['success' => true, 'sale' => $sale, 'items' => $items]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
