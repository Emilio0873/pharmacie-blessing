<?php
$page_title = "Historique Mouvements de Stock - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Magasinier']);

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$type = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$query = "SELECT sm.*, p.name as product_name, u.full_name as user_name 
          FROM stock_movements sm 
          JOIN products p ON sm.product_id = p.id 
          LEFT JOIN users u ON sm.user_id = u.id 
          WHERE 1=1";

$params = [];

if ($search) {
    $query .= " AND p.name LIKE ?";
    $params[] = "%$search%";
}
if ($type) {
    $query .= " AND sm.type = ?";
    $params[] = $type;
}
if ($date_from) {
    $query .= " AND DATE(sm.created_at) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $query .= " AND DATE(sm.created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY sm.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$movements = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="fas fa-exchange-alt text-primary me-2"></i> Mouvements de Stock</h3>
    </div>
    <div class="col-md-6 text-end">
        <a href="in.php" class="btn btn-success shadow-sm me-2">
            <i class="fas fa-plus-circle me-1"></i> Entrée de stock
        </a>
        <a href="out.php" class="btn btn-danger shadow-sm">
            <i class="fas fa-minus-circle me-1"></i> Sortie de stock
        </a>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 overflow-hidden d-print-none">
    <div class="card-body bg-light-subtle">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">Recherche Produit</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" name="search" placeholder="Nom du produit..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Type</label>
                <select class="form-select" name="type">
                    <option value="">Tous</option>
                    <option value="IN" <?php echo $type === 'IN' ? 'selected' : ''; ?>>Entrée (IN)</option>
                    <option value="OUT" <?php echo $type === 'OUT' ? 'selected' : ''; ?>>Sortie (OUT)</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Du</label>
                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Au</label>
                <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-md-3">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1 fw-bold">
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

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Date</th>
                        <th>Produit</th>
                        <th>Type</th>
                        <th>Quantité</th>
                        <th>Utilisateur</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($movements) > 0): ?>
                        <?php foreach ($movements as $m): ?>
                            <tr>
                                <td class="ps-4"><?php echo date('d/m/Y H:i', strtotime($m['created_at'])); ?></td>
                                <td class="fw-600"><?php echo htmlspecialchars($m['product_name']); ?></td>
                                <td>
                                    <?php if ($m['type'] === 'IN'): ?>
                                        <span class="badge bg-success"><i class="fas fa-arrow-down me-1"></i> ENTRÉE</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="fas fa-arrow-up me-1"></i> SORTIE</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="fw-bold <?php echo $m['type'] === 'IN' ? 'text-success' : 'text-danger'; ?>"><?php echo $m['qty']; ?></span></td>
                                <td><small class="text-muted"><i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($m['user_name'] ?? 'N/A'); ?></small></td>
                                <td><small><?php echo htmlspecialchars($m['notes']); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-3x mb-3 opacity-25 d-block"></i> Aucun mouvement de stock trouvé.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
