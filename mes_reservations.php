<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/ui_shell.php';
require_once 'includes/public_chrome.php';

$error = '';
$reservations = [];
$phoneLabel = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phoneLabel = trim(strip_tags($_POST['phone'] ?? ''));
    $key = reservation_phone_key($phoneLabel);
    if (strlen($key) < 8) {
        $error = 'Indiquez le téléphone utilisé pour la réservation.';
    } else {
        $_SESSION['reservation_phone'] = $key;
    }
}

if (!empty($_SESSION['reservation_phone'])) {
    $stmt = $pdo->query("SELECT id, reference, last_name, first_name, phone, pickup_date, subtotal, status, created_at FROM reservations ORDER BY created_at DESC");
    foreach ($stmt->fetchAll() as $row) {
        if (hash_equals(reservation_phone_key($row['phone']), $_SESSION['reservation_phone'])) {
            $reservations[] = $row;
        }
    }
}

render_public_chrome_start('Mes réservations — Pharmacie Blessing');
?>

<section class="section section-products">
    <div class="container" style="max-width: 860px;">
        <div class="section-head">
            <span class="section-kicker">Espace personnel</span>
            <h2 class="section-title">Mes réservations</h2>
            <p class="section-text">Retrouvez vos factures pro forma avec le téléphone indiqué lors de la réservation.</p>
        </div>

        <form method="post" class="reserve-panel mb-4">
            <label class="form-label" for="phone">Téléphone</label>
            <div class="row g-2">
                <div class="col-12 col-sm-8">
                    <input type="tel" class="form-control" id="phone" name="phone" required value="<?php echo htmlspecialchars($phoneLabel); ?>" placeholder="Numéro utilisé à la réservation">
                </div>
                <div class="col-12 col-sm-4">
                    <button type="submit" class="btn-hero btn-hero-primary w-100">Afficher</button>
                </div>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger mt-3 mb-0"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
        </form>

        <?php if (!empty($_SESSION['reservation_phone'])): ?>
            <div class="reserve-panel">
                <?php if (empty($reservations)): ?>
                    <p class="mb-0">Aucune réservation pour ce téléphone. <a href="reservation.php">Réserver une commande</a>.</p>
                <?php else: ?>
                    <?php foreach ($reservations as $reservation): ?>
                        <a class="reserve-line text-decoration-none" href="proforma.php?ref=<?php echo urlencode($reservation['reference']); ?>">
                            <div>
                                <strong><?php echo htmlspecialchars($reservation['reference']); ?></strong>
                                <div class="reserve-meta">
                                    Retrait le <?php echo date('d/m/Y', strtotime($reservation['pickup_date'])); ?>
                                    · En attente du facturier
                                </div>
                            </div>
                            <div><?php echo number_format((float)$reservation['subtotal'], 0, ',', ' '); ?> FC</div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php render_public_chrome_end(); ?>
