<?php
$page_title = "Réservation en ligne - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Caissier', 'Facturier']);

ensure_reservation_tables($pdo);
ensure_delivery_status_column($pdo);
ensure_sale_fulfillment_columns($pdo);
ensure_invoicing_tables($pdo);

$id = (int)($_GET['id'] ?? 0);
$error = '';

$stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
$stmt->execute([$id]);
$reservation = $stmt->fetch();

if (!$reservation) {
    redirect('reservations.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_payment'])) {
    if (!csrf_valid()) {
        $error = 'La page a expiré. Rechargez-la puis validez à nouveau.';
    } elseif (($reservation['status'] ?? '') === 'payee' || !empty($reservation['sale_id'])) {
        $error = 'Cette réservation est déjà validée et encaissée.';
    } else {
        try {
            $itemsStmt = $pdo->prepare("SELECT * FROM reservation_items WHERE reservation_id = ? ORDER BY id ASC");
            $itemsStmt->execute([$id]);
            $items = $itemsStmt->fetchAll();
            if (empty($items)) {
                throw new Exception('Aucun produit dans cette réservation.');
            }

            $pdo->beginTransaction();
            $clientName = trim($reservation['last_name'] . ' ' . $reservation['first_name']);
            $clientId = find_or_create_client(
                $pdo,
                $clientName,
                $reservation['phone'],
                $reservation['email'] ?? null,
                $reservation['address'] ?? null
            );
            $result = create_paid_sale_from_lines($pdo, $items, [
                'client_id' => $clientId,
                'user_id' => (int)$_SESSION['user_id'],
                'subtotal' => (float)$reservation['subtotal'],
                'fulfillment_type' => $reservation['fulfillment_type'] ?? 'retrait_depot',
                'geo_lat' => $reservation['geo_lat'] ?? null,
                'geo_lng' => $reservation['geo_lng'] ?? null,
                'pickup_date' => $reservation['pickup_date'] ?? null,
                'location_commune' => $reservation['location_commune'] ?? null,
                'location_avenue' => $reservation['location_avenue'] ?? null,
                'location_landmark' => $reservation['location_landmark'] ?? null,
                'stock_note' => 'Réservation ' . $reservation['reference'],
            ]);

            $mark = $pdo->prepare("UPDATE reservations SET status = 'payee', sale_id = ? WHERE id = ? AND status = 'en_attente'");
            $mark->execute([$result['sale_id'], $id]);
            if ($mark->rowCount() === 0) {
                throw new Exception('Cette réservation a déjà été traitée.');
            }

            log_activity($pdo, (int)$_SESSION['user_id'], 'Réservation encaissée', "{$reservation['reference']} → vente #{$result['sale_id']} / {$result['invoice_number']}");
            $pdo->commit();
            redirect('invoice.php?id=' . $result['sale_id'] . '&paid=1&share=1');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
            $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
            $stmt->execute([$id]);
            $reservation = $stmt->fetch();
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM reservation_items WHERE reservation_id = ? ORDER BY id ASC");
$stmt->execute([$id]);
$items = $stmt->fetchAll();
$isPaid = ($reservation['status'] ?? '') === 'payee' || !empty($reservation['sale_id']);
$isDelivery = ($reservation['fulfillment_type'] ?? '') === 'livraison_domicile';
$map = maps_url($reservation['geo_lat'] ?? null, $reservation['geo_lng'] ?? null);

require_once '../../includes/header.php';
?>

<div class="mb-4">
    <a href="reservations.php" class="btn btn-light btn-sm mb-3">Retour aux réservations</a>
    <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($reservation['reference']); ?></h3>
    <?php if ($isPaid): ?>
        <p class="text-success mb-0">Paiement validé. La vente est en caisse et chez le livreur.</p>
    <?php else: ?>
        <p class="text-muted mb-0">Quand le client vient régulariser, validez le paiement : caisse et livreur sont mis à jour automatiquement.</p>
    <?php endif; ?>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

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
                <p class="mb-1">
                    Mode :
                    <strong><?php echo $isDelivery ? 'Livraison à domicile' : 'À récupérer au dépôt'; ?></strong>
                </p>
                <p class="mb-1">
                    <?php echo $isDelivery ? 'Jour de livraison' : 'Jour de récupération'; ?> :
                    <strong><?php echo date('d/m/Y', strtotime($reservation['pickup_date'])); ?></strong>
                </p>
                <?php if ($isDelivery): ?>
                    <p class="mb-3 small">
                        <?php echo htmlspecialchars(build_location_address(
                            $reservation['location_commune'] ?? '',
                            $reservation['location_avenue'] ?? '',
                            $reservation['location_landmark'] ?? '',
                            ''
                        )); ?>
                    </p>
                <?php else: ?>
                    <div class="mb-3"></div>
                <?php endif; ?>
                <?php if ($map): ?>
                    <a class="btn btn-outline-primary btn-sm mb-3" href="<?php echo htmlspecialchars($map); ?>" target="_blank" rel="noopener">Voir sur la carte</a>
                <?php endif; ?>

                <?php if ($isPaid): ?>
                    <span class="badge bg-success mb-3 d-block">Payée</span>
                    <?php if (!empty($reservation['sale_id'])): ?>
                        <div class="d-grid gap-2">
                            <a class="btn btn-primary" href="invoice.php?id=<?php echo (int)$reservation['sale_id']; ?>">Voir la facture</a>
                            <a class="btn btn-outline-secondary" href="../caisse/index.php?view=pending">Voir en caisse</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <form method="post" onsubmit="return confirm('Confirmer le paiement ? Stock, caisse et livreur seront mis à jour.');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <input type="hidden" name="validate_payment" value="1">
                        <button type="submit" class="btn btn-success w-100 fw-bold">
                            <i class="fas fa-check-circle me-1"></i> Valider le paiement
                        </button>
                    </form>
                <?php endif; ?>
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
                                    <td class="ps-4"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td><?php echo (int)$item['qty']; ?></td>
                                    <td><?php echo format_currency($item['unit_price']); ?></td>
                                    <td class="text-end pe-4"><?php echo format_currency($item['line_total']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between p-4">
                    <span class="text-muted"><?php echo $isPaid ? 'Montant encaissé' : 'Montant prévisionnel'; ?></span>
                    <strong class="fs-5"><?php echo format_currency($reservation['subtotal']); ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
