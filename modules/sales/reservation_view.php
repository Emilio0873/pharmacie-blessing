<?php
$page_title = "Réservation en ligne - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Caissier']);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
$stmt->execute([$id]);
$reservation = $stmt->fetch();

if (!$reservation) {
    redirect('reservations.php');
}

$stmt = $pdo->prepare("SELECT * FROM reservation_items WHERE reservation_id = ? ORDER BY id ASC");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="mb-4">
    <a href="reservations.php" class="btn btn-light btn-sm mb-3">Retour aux réservations</a>
    <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($reservation['reference']); ?></h3>
    <p class="text-muted mb-0">Pro forma en attente du facturier. Cette fiche ne confirme pas un paiement et ne prépare pas une livraison.</p>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="fw-bold">Client</h5>
                <p class="mb-1"><?php echo htmlspecialchars($reservation['last_name'] . ' ' . $reservation['first_name']); ?></p>
                <p class="mb-1">Tél : <?php echo htmlspecialchars($reservation['phone']); ?></p>
                <?php if (!empty($reservation['email'])): ?>
                    <p class="mb-1">E-mail : <?php echo htmlspecialchars($reservation['email']); ?></p>
                <?php endif; ?>
                <?php if (!empty($reservation['address'])): ?>
                    <p class="mb-1"><?php echo htmlspecialchars($reservation['address']); ?></p>
                <?php endif; ?>
                <p class="mb-0">Retrait prévu : <strong><?php echo date('d/m/Y', strtotime($reservation['pickup_date'])); ?></strong></p>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Produit</th>
                                <th>Qté</th>
                                <th>Prix</th>
                                <th class="text-end pe-4">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="ps-4">
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                        <?php if (!empty($item['product_code'])): ?>
                                            <div class="small text-muted"><?php echo htmlspecialchars($item['product_code']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo (int)$item['qty']; ?></td>
                                    <td><?php echo format_currency($item['unit_price']); ?></td>
                                    <td class="text-end pe-4"><?php echo format_currency($item['line_total']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between p-4">
                    <span class="text-muted">Montant prévisionnel</span>
                    <strong class="fs-5"><?php echo format_currency($reservation['subtotal']); ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
