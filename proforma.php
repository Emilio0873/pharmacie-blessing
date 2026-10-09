<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/ui_shell.php';
require_once 'includes/public_chrome.php';

$ref = trim($_GET['ref'] ?? '');
$reservation = null;
$items = [];
$phoneError = '';

if ($ref !== '') {
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE reference = ?");
    $stmt->execute([$ref]);
    $reservation = $stmt->fetch();
}

if ($reservation && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = reservation_phone_key($_POST['phone'] ?? '');
    if ($posted !== '' && hash_equals(reservation_phone_key($reservation['phone']), $posted)) {
        $_SESSION['reservation_phone'] = $posted;
    } else {
        $phoneError = 'Ce téléphone ne correspond pas à la réservation.';
    }
}

$allowed = $reservation && isset($_SESSION['reservation_phone'])
    && hash_equals(reservation_phone_key($reservation['phone']), $_SESSION['reservation_phone']);

if ($allowed) {
    $stmt = $pdo->prepare("SELECT * FROM reservation_items WHERE reservation_id = ? ORDER BY id ASC");
    $stmt->execute([(int)$reservation['id']]);
    $items = $stmt->fetchAll();
}

$business = get_business_profile($pdo);
render_public_chrome_start('Facture pro forma — Pharmacie Blessing');
?>

<section class="section section-products">
    <div class="container" style="max-width: 860px;">
        <?php if (!$reservation): ?>
            <div class="reserve-panel">
                <h2 class="section-title">Réservation introuvable</h2>
                <p>Vérifiez le numéro de réservation, ou ouvrez <a href="mes_reservations.php">Mes réservations</a>.</p>
            </div>
        <?php elseif (!$allowed): ?>
            <div class="reserve-panel">
                <h2 class="section-title">Voir la facture pro forma</h2>
                <p class="section-text">Indiquez le téléphone utilisé lors de la réservation <?php echo htmlspecialchars($reservation['reference']); ?>.</p>
                <?php if ($phoneError): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($phoneError); ?></div>
                <?php endif; ?>
                <form method="post" class="row g-2">
                    <div class="col-12 col-sm-8">
                        <input type="tel" name="phone" class="form-control" required placeholder="Téléphone">
                    </div>
                    <div class="col-12 col-sm-4">
                        <button type="submit" class="btn-hero btn-hero-primary w-100">Afficher</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <a href="mes_reservations.php" class="btn-hero btn-hero-ghost">Mes réservations</a>
                <button type="button" class="btn-hero btn-hero-primary" onclick="window.print()">Imprimer</button>
            </div>
            <article class="proforma-sheet" id="proformaSheet">
                <div class="proforma-banner">Facture pro forma — non payée</div>
                <header class="proforma-head">
                    <div>
                        <h1><?php echo htmlspecialchars($business['name']); ?></h1>
                        <p><?php echo htmlspecialchars($business['address']); ?><br>Tél : <?php echo htmlspecialchars($business['phone']); ?></p>
                    </div>
                    <div class="text-md-end">
                        <div class="proforma-ref"><?php echo htmlspecialchars($reservation['reference']); ?></div>
                        <div>Émise le <?php echo date('d/m/Y H:i', strtotime($reservation['created_at'])); ?></div>
                        <?php $isDelivery = ($reservation['fulfillment_type'] ?? '') === 'livraison_domicile'; ?>
                        <div><?php echo $isDelivery ? 'Livraison' : 'Retrait'; ?> prévu le <?php echo date('d/m/Y', strtotime($reservation['pickup_date'])); ?></div>
                    </div>
                </header>
                <section class="proforma-client">
                    <h2>Client</h2>
                    <p>
                        <?php echo htmlspecialchars($reservation['last_name'] . ' ' . $reservation['first_name']); ?><br>
                        Tél : <?php echo htmlspecialchars($reservation['phone']); ?>
                        <?php if (!empty($reservation['email'])): ?><br>E-mail : <?php echo htmlspecialchars($reservation['email']); ?><?php endif; ?>
                        <?php if (!empty($reservation['address'])): ?><br><?php echo htmlspecialchars($reservation['address']); ?><?php endif; ?>
                        <br>Mode : <?php echo $isDelivery ? 'Livraison à domicile' : 'Retrait au dépôt'; ?>
                        <?php
                        $mapLink = maps_url($reservation['geo_lat'] ?? null, $reservation['geo_lng'] ?? null);
                        if ($mapLink): ?>
                            <br><a href="<?php echo htmlspecialchars($mapLink); ?>" target="_blank" rel="noopener">Position GPS</a>
                        <?php endif; ?>
                    </p>
                </section>
                <table class="proforma-table">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Qté</th>
                            <th>Prix</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td><?php echo (int)$item['qty']; ?></td>
                                <td><?php echo number_format((float)$item['unit_price'], 0, ',', ' '); ?> FC</td>
                                <td><?php echo number_format((float)$item['line_total'], 0, ',', ' '); ?> FC</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="proforma-sum">
                    <span>Montant prévisionnel</span>
                    <strong><?php echo number_format((float)$reservation['subtotal'], 0, ',', ' '); ?> FC</strong>
                </div>
                <p class="proforma-legal">Document prévisionnel, sans valeur de reçu de caisse. Le paiement et la remise des produits se font au dépôt, après traitement par le facturier. Cette réservation n’ouvre pas la livraison.</p>
            </article>
        <?php endif; ?>
    </div>
</section>

<?php render_public_chrome_end(); ?>
