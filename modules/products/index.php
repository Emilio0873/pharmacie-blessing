<?php
$page_title = "Gestion des Produits - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Magasinier']);

ensure_product_lot_column($pdo);

$message = '';
$error = '';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();
        
        // Delete related records first
        $pdo->prepare("DELETE FROM stock_movements WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM sale_details WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM purchase_details WHERE product_id = ?")->execute([$id]);
        
        // Delete the product
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        
        $message = "Produit et tout son historique ont été supprimés avec succès.";
        log_activity($pdo, $_SESSION['user_id'], 'Suppression Produit (Cascade)', "ID: $id");
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

// Search & Filter
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$cat_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$brand_filter = isset($_GET['brand']) ? sanitize($_GET['brand']) : '';
$stock_alert = isset($_GET['filter']) && $_GET['filter'] === 'low_stock';

$query = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.code LIKE ? OR p.lot_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($cat_filter > 0) {
    $query .= " AND p.category_id = ?";
    $params[] = $cat_filter;
}

if (!empty($brand_filter)) {
    $query .= " AND p.brand = ?";
    $params[] = $brand_filter;
}

if ($stock_alert) {
    $query .= " AND p.qty <= p.alert_threshold";
}

$query .= " ORDER BY p.id DESC";
$products = $pdo->prepare($query);
$products->execute($params);
$products = $products->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$brands = $pdo->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand != '' ORDER BY brand ASC")->fetchAll(PDO::FETCH_COLUMN);

require_once '../../includes/header.php';
?>

<div class="row mb-4 align-items-center g-2">
    <div class="col-12 col-md-6">
        <h3 class="fw-bold"><i class="fas fa-pills text-primary me-2"></i> Gestion des Produits</h3>
    </div>
    <div class="col-12 col-md-6 text-md-end">
        <a href="add.php" class="btn btn-primary shadow-sm">
            <i class="fas fa-plus me-2"></i> Ajouter un Produit
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm border-0 mb-4 overflow-hidden">
    <div class="card-body bg-light-subtle">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small fw-bold text-muted">Recherche</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nom, code ou n° de lot...">
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted">Catégorie</label>
                <select class="form-select" name="category">
                    <option value="0">Toutes</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $cat_filter == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo $cat['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted">Marque / Labo</label>
                <select class="form-select" name="brand">
                    <option value="">Toutes</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?php echo $b; ?>" <?php echo $brand_filter == $b ? 'selected' : ''; ?>>
                            <?php echo $b; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <div class="form-check pb-2">
                    <input class="form-check-input" type="checkbox" name="filter" value="low_stock" id="low_stock" <?php echo $stock_alert ? 'checked' : ''; ?>>
                    <label class="form-check-label fw-600" for="low_stock">Stock faible</label>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fas fa-filter me-1"></i> Filtrer
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary" title="Réinitialiser">
                        <i class="fas fa-sync-alt"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm border-0 p-3">
    <div class="table-responsive">
        <table class="table table-hover table-custom mb-0">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Code</th>
                    <th>Nom du Produit</th>
                    <th>Lot</th>
                    <th>Catégorie</th>
                    <th>Prix Vente</th>
                    <th>Quantité</th>
                    <th>Exp.</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td>
                        <?php if ($p['image']): ?>
                            <img src="../../uploads/products/<?php echo $p['image']; ?>" class="rounded" style="width: 40px; height: 40px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="fas fa-image text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="fw-bold"><?php echo $p['code']; ?></td>
                    <td><?php echo $p['name']; ?></td>
                    <td>
                        <?php if (!empty($p['lot_number'])): ?>
                            <span class="badge bg-light text-primary border"><?php echo htmlspecialchars($p['lot_number']); ?></span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-light text-dark"><?php echo $p['category_name']; ?></span></td>
                    <td class="fw-bold"><?php echo format_currency($p['sell_price']); ?></td>
                    <td class="fw-bold <?php echo $p['qty'] <= $p['alert_threshold'] ? 'text-danger' : ''; ?>">
                        <?php echo $p['qty']; ?>
                    </td>
                    <td>
                        <?php 
                        if ($p['exp_date']):
                            $exp = strtotime($p['exp_date']);
                            $today = time();
                            $class = $exp < $today ? 'text-danger fw-bold' : ($exp < $today + (30*24*60*60) ? 'text-warning fw-bold' : '');
                        ?>
                            <span class="<?php echo $class; ?>"><?php echo date('d/m/Y', $exp); ?></span>
                        <?php else: echo '-'; endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="btn-group">
                            <a href="view.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-light text-info" title="Voir les détails">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="edit.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-light text-primary" title="Modifier">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="?delete=<?php echo $p['id']; ?>" class="btn btn-sm btn-light text-danger" onclick="return confirm('Supprimer ce produit ?')" title="Supprimer">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">Aucun produit trouvé.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
