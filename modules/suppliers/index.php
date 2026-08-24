<?php
$page_title = "Gestion des Fournisseurs - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Magasinier']);

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM suppliers WHERE id = ?")->execute([$id]);
        $success = "Fournisseur supprimé avec succès.";
    } catch (Exception $e) {
        $error = "Erreur lors de la suppression (ce fournisseur est lié à des achats).";
    }
}

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$query = "SELECT s.*, 
          (SELECT COUNT(*) FROM purchases WHERE supplier_id = s.id) as total_purchases_count,
          (SELECT SUM(total_amount) FROM purchases WHERE supplier_id = s.id) as total_purchases_amount
          FROM suppliers s WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (s.name LIKE ? OR s.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY s.name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$suppliers = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="fas fa-handshake text-primary me-2"></i> Fournisseurs</h3>
    </div>
    <div class="col-md-6 text-end">
        <a href="add.php" class="btn btn-primary shadow-sm">
            <i class="fas fa-plus-circle me-1"></i> Nouveau Fournisseur
        </a>
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success mt-3"><?php echo $success; ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger mt-3"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card shadow-sm border-0 mb-4 overflow-hidden d-print-none">
    <div class="card-body bg-light-subtle">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-8">
                <label class="form-label small fw-bold text-muted">Recherche</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" name="search" placeholder="Nom ou téléphone du fournisseur..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-4">
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
                        <th class="ps-4">Nom du fournisseur</th>
                        <th>Contact</th>
                        <th>Achats effectués</th>
                        <th>Volume d'achats</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($suppliers) > 0): ?>
                        <?php foreach ($suppliers as $s): ?>
                            <tr>
                                <td class="ps-4 fw-600"><?php echo htmlspecialchars($s['name']); ?></td>
                                <td>
                                    <?php if ($s['phone']): ?><div><i class="fas fa-phone small text-muted"></i> <?php echo htmlspecialchars($s['phone']); ?></div><?php endif; ?>
                                    <?php if ($s['email']): ?><div><i class="fas fa-envelope small text-muted"></i> <?php echo htmlspecialchars($s['email']); ?></div><?php endif; ?>
                                </td>
                                <td><span class="badge bg-secondary rounded-pill px-3"><?php echo $s['total_purchases_count']; ?> achats</span></td>
                                <td class="fw-bold text-danger"><?php echo format_currency($s['total_purchases_amount'] ?? 0); ?></td>
                                <td class="text-end pe-4">
                                    <a href="edit.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-light text-primary"><i class="fas fa-edit"></i></a>
                                    <a href="?delete=<?php echo $s['id']; ?>" class="btn btn-sm btn-light text-danger" onclick="return confirm('Supprimer ce fournisseur ?');"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">Aucun fournisseur trouvé.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
