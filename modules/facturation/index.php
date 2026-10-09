<?php
$page_title = "Commandes comptoir - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Facturier']);

ensure_counter_order_tables($pdo);

$orders = $pdo->query(
    "SELECT o.*,
            (SELECT COUNT(*) FROM counter_order_items i WHERE i.order_id = o.id) AS line_count
     FROM counter_orders o
     ORDER BY FIELD(o.status, 'en_caisse', 'payee', 'annulee'), o.created_at DESC
     LIMIT 100"
)->fetchAll();

require_once '../../includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-8">
        <h3 class="fw-bold mb-1">Commandes au comptoir</h3>
        <p class="text-muted mb-0">Schéma physique : facturier (commande) → caisse (paiement) → livreur (livraison ou à retirer).</p>
    </div>
    <div class="col-md-4 text-md-end">
        <a href="create.php" class="btn btn-primary fw-bold"><i class="fas fa-plus me-1"></i> Nouvelle commande</a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Référence</th>
                        <th>Client</th>
                        <th>Mode</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$orders): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">Aucune commande comptoir.</td></tr>
                    <?php else: foreach ($orders as $order): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-primary"><?php echo htmlspecialchars($order['reference']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($order['client_name']); ?>
                                <div class="small text-muted"><?php echo htmlspecialchars($order['phone']); ?></div>
                            </td>
                            <td><?php echo ($order['fulfillment_type'] === 'livraison_domicile') ? 'Livraison' : 'Retrait'; ?></td>
                            <td><?php echo format_currency($order['subtotal']); ?></td>
                            <td>
                                <?php if ($order['status'] === 'payee'): ?>
                                    <span class="badge bg-success">Payée</span>
                                <?php elseif ($order['status'] === 'en_caisse'): ?>
                                    <span class="badge bg-warning text-dark">Chez la caisse</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($order['status']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <a class="btn btn-sm btn-primary" href="view.php?id=<?php echo (int)$order['id']; ?>">Voir</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
