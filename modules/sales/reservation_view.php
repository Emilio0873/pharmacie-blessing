<?php
$page_title = "Réservation en ligne - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Caissier', 'Facturier']);

ensure_reservation_tables($pdo);
ensure_delivery_status_column($pdo);
ensure_invoicing_tables($pdo);

$id = (int)($_GET['id'] ?? 0);
$message = '';
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

            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $qty = (int)$item['qty'];
                $check = $pdo->prepare("SELECT name, qty FROM products WHERE id = ? FOR UPDATE");
                $check->execute([$productId]);
                $product = $check->fetch();
                if (!$product) {
                    throw new Exception('Produit introuvable : ' . $item['product_name']);
                }
                if ((int)$product['qty'] < $qty) {
                    throw new Exception("Stock insuffisant pour « {$product['name']} » (dispo: {$product['qty']}).");
                }
            }

            $clientName = trim($reservation['last_name'] . ' ' . $reservation['first_name']);
            $clientPhone = $reservation['phone'];
            $clientId = null;
            $findClient = $pdo->prepare("SELECT id FROM clients WHERE phone = ? LIMIT 1");
            $findClient->execute([$clientPhone]);
            $clientId = $findClient->fetchColumn();
            if (!$clientId) {
                $pdo->prepare("INSERT INTO clients (name, phone, email, address) VALUES (?, ?, ?, ?)")
                    ->execute([
                        $clientName,
                        $clientPhone,
                        $reservation['email'] ?: null,
                        $reservation['address'] ?: null,
                    ]);
                $clientId = (int)$pdo->lastInsertId();
            } else {
                $clientId = (int)$clientId;
            }

            $subtotal = (float)$reservation['subtotal'];
            $userId = (int)$_SESSION['user_id'];
            $pdo->prepare("INSERT INTO sales (client_id, user_id, total_amount, discount, final_amount, delivery_status)
                VALUES (?, ?, ?, 0, ?, 'a_preparer')")
                ->execute([$clientId, $userId, $subtotal, $subtotal]);
            $saleId = (int)$pdo->lastInsertId();

            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $qty = (int)$item['qty'];
                $price = (float)$item['unit_price'];

                $pdo->prepare("INSERT INTO sale_details (sale_id, product_id, qty, unit_price) VALUES (?, ?, ?, ?)")
                    ->execute([$saleId, $productId, $qty, $price]);

                $stock = $pdo->prepare("UPDATE products SET qty = qty - ? WHERE id = ? AND qty >= ?");
                $stock->execute([$qty, $productId, $qty]);
                if ($stock->rowCount() === 0) {
                    throw new Exception('Stock insuffisant pour « ' . $item['product_name'] . ' ».');
                }

                $pdo->prepare("INSERT INTO stock_movements (product_id, type, qty, user_id, reference_id, notes)
                    VALUES (?, 'OUT', ?, ?, ?, ?)")
                    ->execute([$productId, $qty, $userId, $saleId, 'Réservation ' . $reservation['reference']]);
            }

            $invoiceNumber = format_invoice_number($saleId);
            $pdo->prepare("INSERT INTO invoices
                (sale_id, invoice_number, client_id, user_id, subtotal, discount, tax_rate, tax_amount, total_amount, payment_mode, legal_note)
                VALUES (?, ?, ?, ?, ?, 0, 0, 0, ?, 'Espèces', ?)")
                ->execute([
                    $saleId,
                    $invoiceNumber,
                    $clientId,
                    $userId,
                    $subtotal,
                    $subtotal,
                    'Les médicaments vendus ne sont ni repris ni échangés.',
                ]);
            $invoiceId = (int)$pdo->lastInsertId();

            $lineStmt = $pdo->prepare("INSERT INTO invoice_items
                (invoice_id, product_id, product_name, product_code, qty, unit_price, line_discount, line_tax, line_total)
                VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?)");
            foreach ($items as $item) {
                $lineStmt->execute([
                    $invoiceId,
                    (int)$item['product_id'],
                    $item['product_name'],
                    $item['product_code'],
                    (int)$item['qty'],
                    (float)$item['unit_price'],
                    (float)$item['line_total'],
                ]);
            }

            $mark = $pdo->prepare("UPDATE reservations SET status = 'payee', sale_id = ? WHERE id = ? AND status = 'en_attente'");
            $mark->execute([$saleId, $id]);
            if ($mark->rowCount() === 0) {
                throw new Exception('Cette réservation a déjà été traitée.');
            }

            log_activity($pdo, $userId, 'Réservation encaissée', "Réservation {$reservation['reference']} → vente #$saleId / $invoiceNumber");
            $pdo->commit();

            redirect('invoice.php?id=' . $saleId . '&paid=1');
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

require_once '../../includes/header.php';
?>

<div class="mb-4">
    <a href="reservations.php" class="btn btn-light btn-sm mb-3">Retour aux réservations</a>
    <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($reservation['reference']); ?></h3>
    <?php if ($isPaid): ?>
        <p class="text-success mb-0">Paiement validé. La vente est enregistrée en caisse et transmise au livreur.</p>
    <?php else: ?>
        <p class="text-muted mb-0">Pro forma en attente. Quand le client vient payer au dépôt, validez le paiement ici : la vente passe en caisse et chez le livreur.</p>
    <?php endif; ?>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
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
                <p class="mb-3">Retrait prévu : <strong><?php echo date('d/m/Y', strtotime($reservation['pickup_date'])); ?></strong></p>

                <?php if ($isPaid): ?>
                    <span class="badge bg-success mb-3">Payée</span>
                    <?php if (!empty($reservation['sale_id'])): ?>
                        <div class="d-grid gap-2">
                            <a class="btn btn-primary" href="invoice.php?id=<?php echo (int)$reservation['sale_id']; ?>">Voir la facture</a>
                            <a class="btn btn-outline-secondary" href="../caisse/index.php?view=sales">Voir en caisse</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <form method="post" onsubmit="return confirm('Confirmer le paiement de cette réservation ? Une vente sera créée, le stock diminué, et la caisse / le livreur seront mis à jour.');">
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
                    <span class="text-muted"><?php echo $isPaid ? 'Montant encaissé' : 'Montant prévisionnel'; ?></span>
                    <strong class="fs-5"><?php echo format_currency($reservation['subtotal']); ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
