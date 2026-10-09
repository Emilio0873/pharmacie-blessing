<?php
$page_title = "Liste des Ventes - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Caissier', 'Facturier']);
ensure_invoicing_tables($pdo);

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

$query = "SELECT s.*, c.name as client_name, u.full_name as user_name,
                 inv.invoice_number, inv.tax_rate, inv.tax_amount, inv.total_amount AS invoice_total
          FROM sales s 
          LEFT JOIN clients c ON s.client_id = c.id 
          LEFT JOIN users u ON s.user_id = u.id
          LEFT JOIN invoices inv ON inv.sale_id = s.id
          WHERE 1=1";

$params = [];

if ($search) {
    $query .= " AND (
        c.name LIKE ?
        OR CAST(s.id AS CHAR) = ?
        OR inv.invoice_number LIKE ?
        OR REPLACE(UPPER(inv.invoice_number), '-', '') = REPLACE(UPPER(?), '-', '')
    )";
    $params[] = "%$search%";
    $params[] = (int)$search;
    $params[] = "%$search%";
    $params[] = $search;
}
if ($date_from) {
    $query .= " AND DATE(s.sale_date) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $query .= " AND DATE(s.sale_date) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY s.sale_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll();

$message = '';
$error = '';

// Handle Delete Sale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!csrf_valid()) {
        $error = "Action refusée. Rechargez la page puis réessayez.";
    } else {
    $id = (int)$_POST['delete_id'];
    try {
        $pdo->beginTransaction();

        // 1. Get sale details to restore stock
        $stmt_items = $pdo->prepare("SELECT product_id, qty FROM sale_details WHERE sale_id = ?");
        $stmt_items->execute([$id]);
        $items = $stmt_items->fetchAll();

        foreach ($items as $item) {
            // 2. Add qty back to products
            $stmt_update = $pdo->prepare("UPDATE products SET qty = qty + ? WHERE id = ?");
            $stmt_update->execute([$item['qty'], $item['product_id']]);
        }

        // 3. Delete related movements
        $stmt_del_mv = $pdo->prepare("DELETE FROM stock_movements WHERE reference_id = ? AND notes LIKE '%Vente%'");
        $stmt_del_mv->execute([$id]);

        // 4. Delete sale details
        $stmt_del_det = $pdo->prepare("DELETE FROM sale_details WHERE sale_id = ?");
        $stmt_del_det->execute([$id]);

        // 4-bis. Delete invoice details if present
        $stmt_get_invoice = $pdo->prepare("SELECT id FROM invoices WHERE sale_id = ?");
        $stmt_get_invoice->execute([$id]);
        $invoice_id = $stmt_get_invoice->fetchColumn();
        if ($invoice_id) {
            $stmt_del_invoice_items = $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
            $stmt_del_invoice_items->execute([$invoice_id]);

            $stmt_del_invoice = $pdo->prepare("DELETE FROM invoices WHERE id = ?");
            $stmt_del_invoice->execute([$invoice_id]);
        }

        // 5. Delete the sale record
        $stmt_del_sale = $pdo->prepare("DELETE FROM sales WHERE id = ?");
        $stmt_del_sale->execute([$id]);

        $pdo->commit();
        $message = "Vente supprimée avec succès. Le stock a été restitué.";
        log_activity($pdo, $_SESSION['user_id'], 'Suppression Vente', "ID: $id (Stock restitué)");
        
        // Refresh list
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $sales = $stmt->fetchAll();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = db_user_message($e, "Impossible de supprimer cette vente.");
    }
    }
}

$total_ca = 0;
foreach ($sales as $sale) {
    $total_ca += $sale['final_amount'];
}

$business = get_business_profile($pdo);

require_once '../../includes/header.php';
?>

<style>
@media print {
    body { background: #fff !important; color: #0f172a !important; }
    .wrapper #sidebar, .navbar, .sidebar-overlay, .d-print-none { display: none !important; }
    #content, .container-fluid { padding: 0 !important; margin: 0 !important; width: 100% !important; }
    .sales-print-header { display: block !important; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #1d4ed8; }
    .card { background: #fff !important; border: 1px solid #e2e8f0 !important; box-shadow: none !important; color: #0f172a !important; }
    .card.bg-primary { background: #1d4ed8 !important; color: #fff !important; }
    .table, .table td, .table th { color: #0f172a !important; background: #fff !important; }
    .table thead th { background: #f1f5f9 !important; color: #334155 !important; }
    a { color: #0f172a !important; text-decoration: none !important; }
}
.sales-print-header { display: none; }
</style>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm d-print-none" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm d-print-none" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="sales-print-header">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h2 class="fw-bold mb-1" style="color:#1e3a8a;"><?php echo htmlspecialchars($business['name']); ?></h2>
            <div class="text-muted small">
                <?php echo htmlspecialchars($business['address']); ?><br>
                Tél: <?php echo htmlspecialchars($business['phone']); ?>
            </div>
        </div>
        <div class="text-end small text-muted">
            <strong>Rapport des ventes</strong><br>
            Période : <?php echo htmlspecialchars(date('d/m/Y', strtotime($date_from))); ?> — <?php echo htmlspecialchars(date('d/m/Y', strtotime($date_to))); ?><br>
            Édité le <?php echo date('d/m/Y H:i'); ?>
        </div>
    </div>
</div>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="fas fa-list-alt text-primary me-2"></i> Liste des Ventes</h3>
        <p class="text-muted mb-0">Historique complet des ventes et chiffre d'affaires.</p>
    </div>
    <div class="col-md-6 text-md-end d-print-none">
        <button type="button" onclick="window.print()" class="btn btn-primary shadow-sm">
            <i class="fas fa-print me-1"></i> Imprimer le rapport
        </button>
        <a href="pos.php" class="btn btn-success shadow-sm ms-2">
            <i class="fas fa-shopping-cart me-1"></i> Vente Rapide (POS)
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow bg-primary text-white border-0">
            <div class="card-body p-4 text-center">
                <h5 class="text-white-50 fw-bold mb-2">CHIFFRE D'AFFAIRES (Période sélectionnée)</h5>
                <h1 class="display-4 fw-bold mb-0"><?php echo format_currency($total_ca); ?></h1>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 overflow-hidden d-print-none">
    <div class="card-body bg-light-subtle">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-12 col-lg-4">
                <label class="form-label small fw-bold text-muted">Recherche</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0" name="search" placeholder="Client, FAC-000001..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small fw-bold text-muted">Du</label>
                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small fw-bold text-muted">Au</label>
                <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-12 col-md-6 col-lg-4">
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
                        <th class="ps-4">N° Vente</th>
                        <th>Date & Heure</th>
                        <th>Client</th>
                        <th>Caissier</th>
                        <th>Montant TTC</th>
                        <th class="text-center d-print-none">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($sales) > 0): ?>
                        <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?php echo htmlspecialchars($sale['invoice_number'] ?? format_invoice_number($sale['id'])); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($sale['sale_date'])); ?></td>
                                <td class="fw-600"><?php echo htmlspecialchars($sale['client_name'] ?? 'Client de passage'); ?></td>
                                <td><small class="text-muted"><i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($sale['user_name'] ?? 'N/A'); ?></small></td>
                                <td class="fw-bold text-success"><?php echo format_currency($sale['final_amount']); ?></td>
                                <td class="text-center d-print-none">
                                    <div class="btn-group action-labels">
                                        <button class="btn btn-sm btn-light text-info" onclick="viewSale(<?php echo $sale['id']; ?>)" title="Détails">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="invoice.php?id=<?php echo $sale['id']; ?>" target="_blank" class="btn btn-sm btn-primary ms-1" title="Facture A4 Professionnelle">
                                            <i class="fas fa-print"></i><span class="d-none d-xl-inline ms-1">Réimprimer</span>
                                        </a>
                                        <a href="invoice.php?id=<?php echo $sale['id']; ?>&download=pdf" target="_blank" class="btn btn-sm btn-outline-primary ms-1" title="Télécharger la facture PDF">
                                            <i class="fas fa-download"></i><span class="d-none d-xl-inline ms-1">Télécharger</span>
                                        </a>
                                        <a href="receipt.php?id=<?php echo $sale['id']; ?>" target="_blank" class="btn btn-sm btn-light text-secondary ms-1" title="Ticket de caisse">
                                            <i class="fas fa-receipt"></i>
                                        </a>
                                        <?php echo csrf_delete_form($sale['id'], 'Attention: cela va supprimer la vente et réintégrer les quantités dans le stock. Confirmer ?', 'btn btn-sm btn-light text-danger ms-1'); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-receipt fa-3x mb-3 opacity-25 d-block"></i> Aucune vente trouvée pour cette période.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Détails Vente -->
<div class="modal fade d-print-none" id="saleDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-list-alt me-2"></i> Détails de la Vente <span id="modalSaleId"></span></h5>
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
                                    <th class="text-end">Prix Unitaire</th>
                                    <th class="text-end">Total Ligne</th>
                                </tr>
                            </thead>
                            <tbody id="modalItemsList"></tbody>
                        </table>
                    </div>
                    <div class="text-end mt-3">
                        <h5 class="fw-bold">Remise: <span id="modalDiscount" class="text-danger"></span></h5>
                        <h3 class="fw-bold text-success">TOTAL PAYÉ: <span id="modalTotal"></span></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewSale(id) {
    const modal = new bootstrap.Modal(document.getElementById('saleDetailsModal'));
    document.getElementById('modalSaleId').innerText = '#' + String(id).padStart(5, '0');
    document.getElementById('modalLoader').style.display = 'inline-block';
    document.getElementById('modalContent').style.display = 'none';
    modal.show();

    fetch('view.php?id=' + id)
        .then(res => res.json())
        .then(data => {
            document.getElementById('modalLoader').style.display = 'none';
            if (data.success) {
                let html = '';
                data.items.forEach(item => {
                    const unitPrice = Number(item.unit_price ?? item.price ?? 0);
                    const totalLine = unitPrice * item.qty;
                    html += `<tr>
                        <td class="fw-600">${item.product_name}</td>
                        <td class="text-center">${item.qty}</td>
                        <td class="text-end">${unitPrice.toLocaleString()} FC</td>
                        <td class="text-end fw-bold">${totalLine.toLocaleString()} FC</td>
                    </tr>`;
                });
                document.getElementById('modalItemsList').innerHTML = html;
                document.getElementById('modalDiscount').innerText = Number(data.sale.discount).toLocaleString() + ' FC';
                document.getElementById('modalTotal').innerText = Number(data.sale.final_amount).toLocaleString() + ' FC';
                document.getElementById('modalContent').style.display = 'block';
            } else {
                alert('Erreur: ' + data.message);
                modal.hide();
            }
        })
        .catch(err => {
            console.error(err);
            alert('Erreur lors de la récupération des détails.');
            modal.hide();
        });
}
</script>

<?php require_once '../../includes/footer.php'; ?>
