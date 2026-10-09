<?php
$page_title = "Historique Mouvements de Stock - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Gérant']);

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$type = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$users = $pdo->query("SELECT u.id, u.full_name, r.name as role_name
                      FROM users u
                      LEFT JOIN roles r ON r.id = u.role_id
                      WHERE u.status = 'active'
                      ORDER BY u.full_name ASC")->fetchAll();

$query = "SELECT sm.*, p.name as product_name, u.full_name as user_name, r.name as role_name
          FROM stock_movements sm
          JOIN products p ON sm.product_id = p.id
          LEFT JOIN users u ON sm.user_id = u.id
          LEFT JOIN roles r ON r.id = u.role_id
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
if ($user_id > 0) {
    $query .= " AND sm.user_id = ?";
    $params[] = $user_id;
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

$summarySql = "SELECT u.full_name, r.name as role_name,
    SUM(CASE WHEN sm.type = 'IN' THEN 1 ELSE 0 END) as nb_in,
    SUM(CASE WHEN sm.type = 'OUT' THEN 1 ELSE 0 END) as nb_out,
    SUM(CASE WHEN sm.type = 'IN' THEN sm.qty ELSE 0 END) as qty_in,
    SUM(CASE WHEN sm.type = 'OUT' THEN sm.qty ELSE 0 END) as qty_out
    FROM stock_movements sm
    JOIN users u ON u.id = sm.user_id
    LEFT JOIN roles r ON r.id = u.role_id
    WHERE 1=1";
$sumParams = [];
if ($date_from) {
    $summarySql .= " AND DATE(sm.created_at) >= ?";
    $sumParams[] = $date_from;
}
if ($date_to) {
    $summarySql .= " AND DATE(sm.created_at) <= ?";
    $sumParams[] = $date_to;
}
if ($user_id > 0) {
    $summarySql .= " AND sm.user_id = ?";
    $sumParams[] = $user_id;
}
$summarySql .= " GROUP BY u.id, u.full_name, r.name ORDER BY (nb_in + nb_out) DESC";
$sumStmt = $pdo->prepare($summarySql);
$sumStmt->execute($sumParams);
$userSummary = $sumStmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="fas fa-exchange-alt text-primary me-2"></i> Mouvements de Stock</h3>
        <p class="text-muted mb-0">Inventaire détaillé avec actions de chaque utilisateur.</p>
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

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header border-0 pt-3 px-4">
        <h5 class="fw-bold mb-0"><i class="fas fa-user-check text-info me-2"></i>Actions inventaire par utilisateur</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Utilisateur</th>
                        <th>Rôle</th>
                        <th class="text-center">Entrées</th>
                        <th class="text-center">Sorties</th>
                        <th class="text-end pe-4">Total actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($userSummary): foreach ($userSummary as $row): ?>
                        <tr>
                            <td class="ps-4 fw-bold"><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($row['role_name'] ?? ''); ?></span></td>
                            <td class="text-center text-success"><?php echo (int)$row['nb_in']; ?> <small>(<?php echo (int)$row['qty_in']; ?> u.)</small></td>
                            <td class="text-center text-danger"><?php echo (int)$row['nb_out']; ?> <small>(<?php echo (int)$row['qty_out']; ?> u.)</small></td>
                            <td class="text-end pe-4 fw-bold"><?php echo (int)$row['nb_in'] + (int)$row['nb_out']; ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucune action inventaire pour ces filtres.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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
                <label class="form-label small fw-bold text-muted">Utilisateur</label>
                <select class="form-select" name="user_id">
                    <option value="0">Tous</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo (int)$u['id']; ?>" <?php echo $user_id === (int)$u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['full_name'] . ' (' . ($u['role_name'] ?? '') . ')'); ?>
                        </option>
                    <?php endforeach; ?>
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
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100 fw-bold" title="Filtrer">
                    <i class="fas fa-filter"></i>
                </button>
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
                        <th>Rôle</th>
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
                                <td><small><?php echo htmlspecialchars($m['role_name'] ?? ''); ?></small></td>
                                <td><small><?php echo htmlspecialchars($m['notes']); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
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
