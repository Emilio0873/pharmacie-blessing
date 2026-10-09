<?php
$page_title = "Commande comptoir - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Facturier', 'Caissier']);

ensure_counter_order_tables($pdo);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM counter_orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) redirect('index.php');

$items = $pdo->prepare("SELECT * FROM counter_order_items WHERE order_id = ? ORDER BY id");
$items->execute([$id]);
$lines = $items->fetchAll();
$map = maps_url($order['geo_lat'] ?? null, $order['geo_lng'] ?? null);

require_once '../../includes/header.php';
?>

<div class="mb-4">
    <a href="<?php echo has_role('Caissier') ? '../caisse/index.php?view=pending' : 'index.php'; ?>" class="btn btn-light btn-sm mb-3">Retour</a>
    <h3 class="fw-bold"><?php echo htmlspecialchars($order['reference']); ?></h3>
    <?php if (!empty($_GET['sent'])): ?>
        <div class="alert alert-success">Commande transférée à la caisse. Le client peut régulariser son paiement.</div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="mb-1"><strong><?php echo htmlspecialchars($order['client_name']); ?></strong></p>
                <p class="mb-1">Tél : <?php echo htmlspecialchars($order['phone']); ?></p>
                <?php if ($order['address']): ?><p class="mb-1"><?php echo htmlspecialchars($order['address']); ?></p><?php endif; ?>
                <p class="mb-1">Mode : <strong><?php echo $order['fulfillment_type'] === 'livraison_domicile' ? 'Livraison' : 'Retrait'; ?></strong></p>
                <?php if ($order['pickup_date']): ?>
                    <p class="mb-1">Date : <?php echo date('d/m/Y', strtotime($order['pickup_date'])); ?></p>
                <?php endif; ?>
                <?php if ($map): ?>
                    <a class="btn btn-sm btn-outline-primary mb-2" target="_blank" href="<?php echo htmlspecialchars($map); ?>">Carte</a>
                <?php endif; ?>
                <p class="mb-0">
                    <?php if ($order['status'] === 'en_caisse'): ?>
                        <span class="badge bg-warning text-dark">En attente de paiement (caisse)</span>
                    <?php elseif ($order['status'] === 'payee'): ?>
                        <span class="badge bg-success">Payée</span>
                        <?php if ($order['sale_id']): ?>
                            <a class="btn btn-sm btn-primary mt-2 d-block" href="../sales/invoice.php?id=<?php echo (int)$order['sale_id']; ?>">Facture</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead class="bg-light"><tr><th class="ps-4">Produit</th><th>Qté</th><th>Prix</th><th class="pe-4 text-end">Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($lines as $line): ?>
                            <tr>
                                <td class="ps-4"><?php echo htmlspecialchars($line['product_name']); ?></td>
                                <td><?php echo (int)$line['qty']; ?></td>
                                <td><?php echo format_currency($line['unit_price']); ?></td>
                                <td class="pe-4 text-end"><?php echo format_currency($line['line_total']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between p-4">
                <span>Total</span>
                <strong><?php echo format_currency($order['subtotal']); ?></strong>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
