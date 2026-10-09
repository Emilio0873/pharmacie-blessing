<?php
$page_title = "Commandes à remettre - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Livreur']);

ensure_delivery_status_column($pdo);
ensure_sale_fulfillment_columns($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    $saleId = (int)($_POST['sale_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $next = match ($action) {
        'preparer' => 'preparee',
        'a_retirer' => 'a_retirer',
        'livrer' => 'livree',
        default => null,
    };
    if ($saleId > 0 && $next) {
        try {
            $allowedFrom = ['a_preparer', 'preparee', 'a_retirer'];
            $stmt = $pdo->prepare("UPDATE sales SET delivery_status = ? WHERE id = ? AND delivery_status IN ('a_preparer','preparee','a_retirer')");
            $stmt->execute([$next, $saleId]);
            if ($stmt->rowCount() > 0) {
                $labels = [
                    'preparee' => 'préparée',
                    'a_retirer' => 'à retirer au dépôt',
                    'livree' => 'livrée / remise',
                ];
                log_activity($pdo, $_SESSION['user_id'], 'Livraison', "Vente #$saleId : " . ($labels[$next] ?? $next));
                $message = "Commande #$saleId mise à jour (" . ($labels[$next] ?? $next) . ").";
            } else {
                $error = "Cette commande n’est plus disponible.";
            }
        } catch (Exception $e) {
            $error = db_user_message($e, 'Impossible de mettre à jour la commande.');
        }
    }
}

$pending = $pdo->query(
    "SELECT s.id, s.sale_date, s.final_amount, s.delivery_status, s.fulfillment_type, s.geo_lat, s.geo_lng,
            s.pickup_date, s.location_commune, s.location_avenue, s.location_landmark,
            c.name AS client_name, c.phone AS client_phone, c.address AS client_address,
            inv.invoice_number
     FROM sales s
     LEFT JOIN clients c ON c.id = s.client_id
     LEFT JOIN invoices inv ON inv.sale_id = s.id
     WHERE s.delivery_status IN ('a_preparer', 'preparee', 'a_retirer')
     ORDER BY FIELD(s.delivery_status, 'a_preparer', 'preparee', 'a_retirer'), s.sale_date ASC"
)->fetchAll();

$history = $pdo->query(
    "SELECT s.id, s.sale_date, s.final_amount, s.delivery_status, s.fulfillment_type,
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
        <p class="text-muted mb-0">Après paiement caisse uniquement. Validez soit la <strong>livraison</strong>, soit <strong>À retirer</strong> (récupération au dépôt, avec le jour indiqué). Puis <strong>Remise effectuée</strong> quand le client prend sa marchandise.</p>
    </div>
</div>

<?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Facture</th>
                        <th>Client</th>
                        <th>Mode</th>
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
                            $map = maps_url($row['geo_lat'] ?? null, $row['geo_lng'] ?? null);
                            $isDelivery = ($row['fulfillment_type'] ?? '') === 'livraison_domicile';
                            ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?php echo htmlspecialchars($row['invoice_number'] ?? format_invoice_number($row['id'])); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($row['client_name'] ?? 'Client de passage'); ?>
                                    <?php if (!empty($row['client_phone'])): ?>
                                        <div class="small text-muted"><?php echo htmlspecialchars($row['client_phone']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row['location_commune']) || !empty($row['location_avenue'])): ?>
                                        <div class="small text-muted">
                                            <?php echo htmlspecialchars(build_location_address($row['location_commune'] ?? '', $row['location_avenue'] ?? '', $row['location_landmark'] ?? '', $row['client_address'] ?? '')); ?>
                                        </div>
                                    <?php elseif (!empty($row['client_address'])): ?>
                                        <div class="small text-muted"><?php echo htmlspecialchars($row['client_address']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row['pickup_date'])): ?>
                                        <div class="small fw-bold text-primary mt-1">
                                            Jour prévu : <?php echo date('d/m/Y', strtotime($row['pickup_date'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="small text-muted mt-1">
                                        <?php foreach ($lines as $line): ?>
                                            <?php echo (int)$line['qty']; ?> × <?php echo htmlspecialchars($line['name']); ?><br>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if ($map): ?>
                                        <a class="btn btn-sm btn-outline-primary mt-1" href="<?php echo htmlspecialchars($map); ?>" target="_blank" rel="noopener">Localiser</a>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $isDelivery ? 'Livraison' : 'À récupérer'; ?></td>
                                <td><?php echo format_currency($row['final_amount']); ?></td>
                                <td>
                                    <?php
                                    echo match ($row['delivery_status']) {
                                        'preparee' => '<span class="badge bg-info text-dark">Préparée</span>',
                                        'a_retirer' => '<span class="badge bg-warning text-dark">À retirer</span>',
                                        default => '<span class="badge bg-secondary">À préparer</span>',
                                    };
                                    ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($row['delivery_status'] === 'a_preparer'): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                            <input type="hidden" name="sale_id" value="<?php echo (int)$row['id']; ?>">
                                            <input type="hidden" name="action" value="preparer">
                                            <button class="btn btn-sm btn-outline-primary">Préparée</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($row['delivery_status'] !== 'a_retirer'): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                            <input type="hidden" name="sale_id" value="<?php echo (int)$row['id']; ?>">
                                            <input type="hidden" name="action" value="a_retirer">
                                            <button class="btn btn-sm btn-warning">À retirer</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                        <input type="hidden" name="sale_id" value="<?php echo (int)$row['id']; ?>">
                                        <input type="hidden" name="action" value="livrer">
                                        <button class="btn btn-sm btn-success"><?php echo $isDelivery ? 'Livrée' : 'Remise effectuée'; ?></button>
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
    <div class="card-header bg-transparent"><h5 class="mb-0 fw-bold">Historique des remises</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="bg-light">
                    <tr><th class="ps-4">Facture</th><th>Date</th><th>Client</th><th>Mode</th><th class="pe-4">Montant</th></tr>
                </thead>
                <tbody>
                    <?php if (!$history): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">Aucun historique.</td></tr>
                    <?php else: foreach ($history as $row): ?>
                        <tr>
                            <td class="ps-4"><?php echo htmlspecialchars($row['invoice_number'] ?? format_invoice_number($row['id'])); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($row['sale_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['client_name'] ?? 'Client de passage'); ?></td>
                            <td><?php echo ($row['fulfillment_type'] ?? '') === 'livraison_domicile' ? 'Livraison' : 'Retrait'; ?></td>
                            <td class="pe-4"><?php echo format_currency($row['final_amount']); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
