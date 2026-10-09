<?php
$page_title = "Historique des Achats - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Magasinier']);

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

$query = "SELECT p.*, s.name as supplier_name, u.full_name as user_name 
          FROM purchases p 
          LEFT JOIN suppliers s ON p.supplier_id = s.id 
          LEFT JOIN users u ON p.created_by = u.id 
          WHERE 1=1";

$params = [];

if ($search) {
    $query .= " AND (s.name LIKE ? OR p.id = ?)";
    $params[] = "%$search%";
    $params[] = (int)$search;
}
if ($date_from) {
    $query .= " AND DATE(p.purchase_date) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $query .= " AND DATE(p.purchase_date) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY p.purchase_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$purchases = $stmt->fetchAll();

$message = '';
$error = '';

// Handle Delete Purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!csrf_valid()) {
        $error = "Action refusée. Rechargez la page puis réessayez.";
    } else {
    $id = (int)$_POST['delete_id'];
    try {
        $pdo->beginTransaction();

        // 1. Get purchase details to reverse stock
        $stmt_items = $pdo->prepare("SELECT product_id, qty FROM purchase_details WHERE purchase_id = ?");
        $stmt_items->execute([$id]);
        $items = $stmt_items->fetchAll();

        foreach ($items as $item) {
            $stmt_check = $pdo->prepare("SELECT name, qty FROM products WHERE id = ? FOR UPDATE");
            $stmt_check->execute([$item['product_id']]);
            $product = $stmt_check->fetch();
            if ($product && (int)$product['qty'] < (int)$item['qty']) {
                throw new Exception("Stock insuffisant pour annuler cet achat (« {$product['name']} »).");
            }
            $stmt_update = $pdo->prepare("UPDATE products SET qty = qty - ? WHERE id = ?");
            $stmt_update->execute([$item['qty'], $item['product_id']]);
        }

        // 3. Delete related movements
        $stmt_del_mv = $pdo->prepare("DELETE FROM stock_movements WHERE reference_id = ? AND notes LIKE '%Achat%'");
        $stmt_del_mv->execute([$id]);

        // 4. Delete purchase details
        $stmt_del_det = $pdo->prepare("DELETE FROM purchase_details WHERE purchase_id = ?");
        $stmt_del_det->execute([$id]);

        // 5. Delete the purchase record
        $stmt_del_pur = $pdo->prepare("DELETE FROM purchases WHERE id = ?");
        $stmt_del_pur->execute([$id]);

        $pdo->commit();
        $message = "Achat supprimé avec succès. Le stock a été ajusté.";
        log_activity($pdo, $_SESSION['user_id'], 'Suppression Achat', "ID: $id (Stock inversé)");
        
        // Refresh list
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $purchases = $stmt->fetchAll();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage() !== '' && !str_contains($e->getMessage(), 'SQLSTATE')
            ? $e->getMessage()
            : db_user_message($e, "Impossible de supprimer cet achat.");
    }
    }
}

$total_purchases = 0;
foreach ($purchases as $p) {
    $total_purchases += $p['total_amount'];
}

require_once '../../includes/header.php';
?>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-4">
    <h3 class="fw-bold"><i class="fas fa-truck-loading text-primary me-2"></i> Achats Fournisseurs</h3>
    <a href="add.php" class="btn btn-primary d-print-none shadow-sm"><i class="fas fa-plus-circle me-1"></i> Nouvel Achat</a>
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

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow bg-success text-white border-0">
            <div class="card-body p-4 text-center">
                <h5 class="text-white-50 fw-bold mb-2">TOTAL DES ACHATS (Période)</h5>
                <h1 class="display-4 fw-bold mb-0"><?php echo format_currency($total_purchases); ?></h1>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 overflow-hidden d-print-none">
    <div class="card-body bg-light-subtle">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">Recherche</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" name="search" placeholder="Fournisseur ou N° Achat..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Du</label>
                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Au</label>
                <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success flex-grow-1 fw-bold text-white">
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
                        <th class="ps-4">N° Achat</th>
                        <th>Date & Heure</th>
                        <th>Fournisseur</th>
                        <th>Utilisateur</th>
                        <th>Statut</th>
                        <th>Montant Total</th>
                        <th class="text-center d-print-none">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($purchases) > 0): ?>
                        <?php foreach ($purchases as $p): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-success">ACH-<?php echo str_pad($p['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($p['purchase_date'])); ?></td>
                                <td class="fw-600"><?php echo htmlspecialchars($p['supplier_name'] ?? 'N/A'); ?></td>
                                <td><small class="text-muted"><i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($p['user_name'] ?? 'N/A'); ?></small></td>
                                <td>
                                    <?php if ($p['status'] == 'received'): ?>
                                        <span class="badge bg-success">Reçu au stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning"><?php echo ucfirst($p['status']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold text-danger"><?php echo format_currency($p['total_amount']); ?></td>
                                <td class="text-center d-print-none">
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-light text-info" onclick="viewPurchase(<?php echo $p['id']; ?>)" title="Détails">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php echo csrf_delete_form($p['id'], 'Attention: cela va supprimer l\'achat et retirer les quantités du stock. Confirmer ?', 'btn btn-sm btn-light text-danger ms-1'); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">Aucun achat trouvé.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Détails -->
<div class="modal fade d-print-none" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-receipt me-2"></i> Détails d'Achat <span id="modalId"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4 text-muted">
                    <i class="fas fa-spinner fa-spin fa-2x" id="modalLoader"></i>
                </div>
                <div id="modalContent" style="display:none;">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="bg-light">
                                <tr>
                                    <th>Produit</th>
                                    <th class="text-center">Quantité</th>
                                    <th class="text-end">Prix d'Achat</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody id="modalItemsList"></tbody>
                        </table>
                    </div>
                    <div class="text-end mt-3">
                        <h3 class="fw-bold text-success">TOTAL: <span id="modalTotal"></span></h3>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="printInvoiceBtn" target="_blank" class="btn btn-secondary"><i class="fas fa-print me-1"></i> Imprimer Facture</a>
            </div>
        </div>
    </div>
</div>

<script>
function viewPurchase(id) {
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    document.getElementById('modalId').innerText = 'ACH-' + String(id).padStart(5, '0');
    document.getElementById('modalLoader').style.display = 'inline-block';
    document.getElementById('modalContent').style.display = 'none';
    document.getElementById('printInvoiceBtn').href = 'invoice.php?id=' + id;
    modal.show();

    fetch('view.php?id=' + id)
        .then(res => res.json())
        .then(data => {
            document.getElementById('modalLoader').style.display = 'none';
            if (data.success) {
                let html = '';
                data.items.forEach(item => {
                    const totalLine = item.buy_price * item.qty;
                    html += `<tr>
                        <td class="fw-600">${item.product_name}</td>
                        <td class="text-center">${item.qty}</td>
                        <td class="text-end">${Number(item.buy_price).toLocaleString()} FC</td>
                        <td class="text-end fw-bold">${totalLine.toLocaleString()} FC</td>
                    </tr>`;
                });
                document.getElementById('modalItemsList').innerHTML = html;
                document.getElementById('modalTotal').innerText = Number(data.purchase.total_amount).toLocaleString() + ' FC';
                document.getElementById('modalContent').style.display = 'block';
            } else {
                alert('Erreur: ' + data.message);
                modal.hide();
            }
        });
}
</script>

<?php require_once '../../includes/footer.php'; ?>
