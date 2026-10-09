<?php
$page_title = "Détails du Produit - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Gérant']);

ensure_product_lot_column($pdo);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) redirect('index.php');

// Fetch product with category name
$stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) redirect('index.php');

// Fetch recent movements for this product
$stmt_mv = $pdo->prepare("SELECT m.*, u.full_name as user_name 
                          FROM stock_movements m 
                          LEFT JOIN users u ON m.user_id = u.id 
                          WHERE m.product_id = ? 
                          ORDER BY m.created_at DESC LIMIT 5");
$stmt_mv->execute([$id]);
$movements = $stmt_mv->fetchAll();

require_once '../../includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="fas fa-search text-primary me-2"></i> Consultation Produit</h3>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php" class="btn btn-light shadow-sm border me-2">
            <i class="fas fa-arrow-left me-2"></i> Retour
        </a>
        <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-primary shadow-sm">
            <i class="fas fa-edit me-2"></i> Modifier
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Main Info -->
    <div class="col-md-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-4">
                    <div class="flex-shrink-0 me-3">
                        <?php if ($product['image']): ?>
                            <img src="../../uploads/products/<?php echo $product['image']; ?>" class="rounded shadow-sm" style="width: 100px; height: 100px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-light rounded d-flex align-items-center justify-content-center shadow-sm" style="width: 100px; height: 100px;">
                                <i class="fas fa-image fa-3x text-muted border-0"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="badge bg-primary mb-2"><?php echo htmlspecialchars($product['category_name'] ?: 'Sans catégorie'); ?></span>
                        <h2 class="fw-bold mb-1"><?php echo htmlspecialchars($product['name']); ?></h2>
                        <p class="text-muted mb-0"><i class="fas fa-barcode me-1"></i> Code: <span class="fw-bold text-dark"><?php echo htmlspecialchars($product['code']); ?></span></p>
                    </div>
                </div>

                <hr class="my-4">

                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="text-muted small fw-bold text-uppercase d-block mb-1">Marque / Laboratoire</label>
                        <p class="fw-600 fs-5"><?php echo htmlspecialchars($product['brand'] ?: 'Non spécifié'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small fw-bold text-uppercase d-block mb-1">Description</label>
                        <p><?php echo nl2br(htmlspecialchars($product['description'] ?: 'Aucune description disponible.')); ?></p>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded">
                            <label class="text-muted small fw-bold text-uppercase d-block mb-1">Prix d'Achat</label>
                            <h4 class="fw-bold text-danger mb-0"><?php echo format_currency($product['buy_price']); ?></h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded">
                            <label class="text-muted small fw-bold text-uppercase d-block mb-1">Prix de Vente</label>
                            <h4 class="fw-bold text-success mb-0"><?php echo format_currency($product['sell_price']); ?></h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded text-center">
                            <label class="text-muted small fw-bold text-uppercase d-block mb-1">Marge</label>
                            <?php $marge = $product['sell_price'] - $product['buy_price']; ?>
                            <h4 class="fw-bold mb-0"><?php echo format_currency($marge); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory & Dates -->
    <div class="col-md-4">
        <div class="card shadow-sm border-0 mb-4 text-white <?php echo $product['qty'] <= $product['alert_threshold'] ? 'bg-danger' : 'bg-primary'; ?>">
            <div class="card-body p-4 text-center">
                <label class="opacity-75 small fw-bold text-uppercase d-block mb-2">Quantité en Stock</label>
                <h1 class="display-3 fw-bold mb-0"><?php echo $product['qty']; ?></h1>
                <p class="mb-0 opacity-75">Seuil d'alerte: <?php echo $product['alert_threshold']; ?></p>
            </div>
        </div>

        <div class="card shadow-sm border-0 p-4">
            <h5 class="fw-bold mb-3"><i class="fas fa-calendar-alt text-primary me-2"></i> Lot & Dates</h5>
            <div class="mb-3">
                <label class="text-muted small fw-bold text-uppercase d-block mb-1">N° de Lot</label>
                <p class="fw-600 mb-0"><?php echo !empty($product['lot_number']) ? htmlspecialchars($product['lot_number']) : '-'; ?></p>
            </div>
            <div class="mb-3">
                <label class="text-muted small fw-bold text-uppercase d-block mb-1">Date de Fabrication</label>
                <p class="fw-600 mb-0"><?php echo $product['mfg_date'] ? date('d/m/Y', strtotime($product['mfg_date'])) : '-'; ?></p>
            </div>
            <div>
                <label class="text-muted small fw-bold text-uppercase d-block mb-1">Date d'Expiration</label>
                <?php 
                $exp_class = '';
                if ($product['exp_date']) {
                    $exp = strtotime($product['exp_date']);
                    if ($exp < time()) $exp_class = 'text-danger fw-bold';
                    else if ($exp < time() + (60 * 24 * 3600)) $exp_class = 'text-warning fw-bold';
                }
                ?>
                <p class="fw-600 mb-0 <?php echo $exp_class; ?>">
                    <?php echo $product['exp_date'] ? date('d/m/Y', strtotime($product['exp_date'])) : '-'; ?>
                    <?php if ($exp_class): ?> <i class="fas fa-exclamation-triangle ms-1"></i> <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- History -->
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="fw-bold mb-0"><i class="fas fa-history text-primary me-2"></i> Derniers Mouvements</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Type</th>
                                <th class="text-center">Quantité</th>
                                <th>Utilisateur</th>
                                <th>Date</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movements as $m): ?>
                            <tr>
                                <td class="ps-4">
                                    <?php if ($m['type'] == 'IN'): ?>
                                        <span class="badge bg-success-subtle text-success">ENTRÉE</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger">SORTIE</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold"><?php echo $m['qty']; ?></td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($m['user_name']); ?></small></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($m['created_at'])); ?></td>
                                <td><small><?php echo htmlspecialchars($m['notes']); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($movements)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Aucun mouvement récent.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
