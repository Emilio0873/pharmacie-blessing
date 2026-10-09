<?php
$page_title = "Commandes à remettre - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Livreur']);

ensure_delivery_status_column($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    $saleId = (int)($_POST['sale_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $next = match ($action) {
        'preparer' => 'preparee',
        'livrer' => 'livree',
        default => null,
    };
    if ($saleId > 0 && $next) {
        try {
            $stmt = $pdo->prepare("UPDATE sales SET delivery_status = ? WHERE id = ? AND delivery_status IN ('a_preparer', 'preparee')");
            $stmt->execute([$next, $saleId]);
            if ($stmt->rowCount() > 0) {
                $label = $next === 'preparee' ? 'préparée' : 'remise au client';
                log_activity($pdo, $_SESSION['user_id'], 'Livraison', "Vente #$saleId marquée $label");
                $message = "Commande #$saleId mise à jour.";
            } else {
                $error = "Cette commande n’est plus disponible.";
            }
        } catch (Exception $e) {
            $error = db_user_message($e, 'Impossible de mettre à jour la commande.');
        }
    }
}

$pending = $pdo->query(
    "SELECT s.id, s.sale_date, s.final_amount, s.delivery_status,
            c.name AS client_name, c.phone AS client_phone,
            u.full_name AS cashier_name,
            inv.invoice_number
     FROM sales s
     LEFT JOIN clients c ON c.id = s.client_id
     LEFT JOIN users u ON u.id = s.user_id
     LEFT JOIN invoices inv ON inv.sale_id = s.id
     WHERE s.delivery_status IN ('a_preparer', 'preparee')
     ORDER BY FIELD(s.delivery_status, 'a_preparer', 'preparee'), s.sale_date ASC"
)->fetchAll();

$history = $pdo->query(
    "SELECT s.id, s.sale_date, s.final_amount, s.delivery_status,
            c.name AS client_name, inv.invoice_number
     FROM sales s
     LEFT JOIN clients c ON c.id = s.client_id
     LEFT JOIN invoices inv ON inv.sale_id = s.id
     WHERE s.delivery_status = 'livree'
     ORDER BY s.sale_date DESC
     LIMIT 20"
)->fetchAll();

require_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h3 class="fw-bold mb-1">Commandes à remettre</h3>
        <p class="text-muted mb-0">Préparez et remettez uniquement les ventes déjà payées. Les réservations en ligne n’apparaissent ici qu’après facturation et paiement.</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Facture</th>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($pending) === 0): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">Aucune commande à préparer.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pending as $row): ?>
                            <?php
                            $items = $pdo->prepare("SELECT p.name, sd.qty FROM sale_details sd JOIN products p ON p.id = sd.product_id WHERE sd.sale_id = ?");
                            $items->execute([(int)$row['id']]);
                            $lines = $items->fetchAll();
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?php echo htmlspecialchars($row['invoice_number'] ?? format_invoice_number($row['id'])); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['sale_date'])); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($row['client_name'] ?? 'Client de passage'); ?>
                                    <?php if (!empty($row['client_phone'])): ?>
                                        <div class="small text-muted"><?php echo htmlspecialchars($row['client_phone']); ?></div>
                                    <?php endif; ?>
                                    <div class="small text-muted mt-1">
                                        <?php foreach ($lines as $line): ?>
                                            <?php echo (int)$line['qty']; ?> × <?php echo htmlspecialchars($line['name']); ?><br>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td><?php echo format_currency($row['final_amount']); ?></td>
                                <td>
                                    <?php if ($row['delivery_status'] === 'preparee'): ?>
                                        <span class="badge bg-info text-dark">Préparée</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">À préparer</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($row['delivery_status'] === 'a_preparer'): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                            <input type="hidden" name="sale_id" value="<?php echo (int)$row['id']; ?>">
                                            <input type="hidden" name="action" value="preparer">
                                            <button class="btn btn-sm btn-outline-primary">Marquer préparée</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                        <input type="hidden" name="sale_id" value="<?php echo (int)$row['id']; ?>">
                                        <input type="hidden" name="action" value="livrer">
                                        <button class="btn btn-sm btn-primary">Remise effectuée</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent">
        <h5 class="mb-0 fw-bold">Dernières remises</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Facture</th>
                        <th>Date</th>
                        <th>Client</th>
                        <th class="pe-4">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($history) === 0): ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">Aucun historique pour le moment.</td></tr>
                    <?php else: ?>
                        <?php foreach ($history as $row): ?>
                            <tr>
                                <td class="ps-4"><?php echo htmlspecialchars($row['invoice_number'] ?? format_invoice_number($row['id'])); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['sale_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['client_name'] ?? 'Client de passage'); ?></td>
                                <td class="pe-4"><?php echo format_currency($row['final_amount']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
