<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/ui_shell.php';
require_once 'includes/public_chrome.php';

ensure_client_accounts_table($pdo);
ensure_invoice_share_column($pdo);
require_client_login();

$welcome = isset($_GET['welcome']);
$phoneKey = reservation_phone_key($_SESSION['client_phone'] ?? '');
$clientId = (int)($_SESSION['client_id'] ?? 0);
$reservations = [];
$invoices = [];

if ($phoneKey !== '') {
    $all = $pdo->query("SELECT id, reference, phone, pickup_date, subtotal, status, created_at, fulfillment_type, sale_id
                        FROM reservations ORDER BY created_at DESC LIMIT 300")->fetchAll();
    foreach ($all as $row) {
        if (hash_equals(reservation_phone_key($row['phone']), $phoneKey)) {
            $reservations[] = $row;
        }
    }
}

if ($clientId > 0) {
    $inv = $pdo->prepare("SELECT s.id as sale_id, inv.id as invoice_id, inv.invoice_number, inv.total_amount,
                                 inv.created_at, inv.share_token, s.fulfillment_type, s.delivery_status
                          FROM invoices inv
                          JOIN sales s ON s.id = inv.sale_id
                          WHERE s.client_id = ?
                          ORDER BY inv.created_at DESC
                          LIMIT 50");
    $inv->execute([$clientId]);
    $invoices = $inv->fetchAll();
}

render_public_chrome_start('Mon espace — Pharmacie Blessing');
?>

<section class="section section-products">
    <div class="container" style="max-width: 920px;">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div class="section-head mb-0">
                <span class="section-kicker">Espace client</span>
                <h2 class="section-title">Bonjour, <?php echo htmlspecialchars($_SESSION['client_name'] ?? ''); ?></h2>
                <p class="section-text mb-0">Suivez vos réservations et factures Pharmacie Blessing.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="reservation.php" class="btn-hero btn-hero-primary">Nouvelle réservation</a>
                <a href="client_logout.php" class="btn-hero btn-hero-ghost">Déconnexion</a>
            </div>
        </div>

        <?php if ($welcome): ?>
            <div class="alert alert-success">Compte créé avec succès. Bienvenue !</div>
        <?php endif; ?>

        <div class="reserve-panel mb-4">
            <h3 class="h5 fw-bold mb-3">Mes réservations</h3>
            <?php if (empty($reservations)): ?>
                <p class="mb-0">Aucune réservation pour le moment. <a href="reservation.php">Réserver une commande</a>.</p>
            <?php else: ?>
                <?php foreach ($reservations as $reservation): ?>
                    <a class="reserve-line text-decoration-none" href="proforma.php?ref=<?php echo urlencode($reservation['reference']); ?>">
                        <div>
                            <strong><?php echo htmlspecialchars($reservation['reference']); ?></strong>
                            <div class="reserve-meta">
                                <?php echo (($reservation['fulfillment_type'] ?? '') === 'livraison_domicile') ? 'Livraison' : 'Retrait'; ?>
                                le <?php echo date('d/m/Y', strtotime($reservation['pickup_date'])); ?>
                                · <?php echo (($reservation['status'] ?? '') === 'payee') ? 'Payée' : 'En attente'; ?>
                            </div>
                        </div>
                        <div><?php echo number_format((float)$reservation['subtotal'], 0, ',', ' '); ?> FC</div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="reserve-panel">
            <h3 class="h5 fw-bold mb-3">Mes factures</h3>
            <?php if (empty($invoices)): ?>
                <p class="mb-0">Aucune facture liée à votre compte pour le moment.</p>
            <?php else: ?>
                <?php foreach ($invoices as $invoice):
                    $token = (string)($invoice['share_token'] ?? '');
                    if ($token === '' && !empty($invoice['invoice_id'])) {
                        $token = ensure_invoice_share_token($pdo, (int)$invoice['invoice_id']);
                    }
                    $href = $token !== '' ? ('facture.php?t=' . urlencode($token)) : '#';
                    ?>
                    <a class="reserve-line text-decoration-none" href="<?php echo htmlspecialchars($href); ?>">
                        <div>
                            <strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong>
                            <div class="reserve-meta">
                                <?php echo date('d/m/Y H:i', strtotime($invoice['created_at'])); ?>
                                · <?php echo htmlspecialchars($invoice['delivery_status'] ?? ''); ?>
                            </div>
                        </div>
                        <div><?php echo number_format((float)$invoice['total_amount'], 0, ',', ' '); ?> FC</div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php render_public_chrome_end(); ?>
