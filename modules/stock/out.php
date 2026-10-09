<?php
$page_title = "Sortie de Stock - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Magasinier']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = (int)$_POST['product_id'];
    $qty = (int)$_POST['qty'];
    $notes = sanitize($_POST['notes']);

    if ($product_id > 0 && $qty > 0) {
        try {
            $pdo->beginTransaction();

            $stmt_check = $pdo->prepare("SELECT qty, name FROM products WHERE id = ?");
            $stmt_check->execute([$product_id]);
            $product = $stmt_check->fetch();

            if ($product && $product['qty'] >= $qty) {
                $stmt = $pdo->prepare("UPDATE products SET qty = qty - ? WHERE id = ?");
                $stmt->execute([$qty, $product_id]);

                $stmt_mv = $pdo->prepare("INSERT INTO stock_movements (product_id, type, qty, user_id, notes) VALUES (?, 'OUT', ?, ?, ?)");
                $stmt_mv->execute([$product_id, $qty, $_SESSION['user_id'], $notes]);

                log_activity($pdo, $_SESSION['user_id'], 'Sortie Stock', "Product ID: $product_id, Qty: $qty");

                $pdo->commit();
                $success = "La sortie de stock a été enregistrée avec succès.";
            } else {
                $pdo->rollBack();
                $error = "Erreur: Le stock est insuffisant pour retirer {$qty} unités de " . htmlspecialchars($product['name'] ?? 'ce produit') . ".";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    } else {
        $error = "Veuillez sélectionner un produit et entrer une quantité valide.";
    }
}

$products = $pdo->query("SELECT id, name, qty FROM products WHERE qty > 0 ORDER BY name ASC")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="fas fa-minus-circle text-danger me-2"></i> Nouvelle Sortie de Stock</h3>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php" class="btn btn-light shadow-sm border">
            <i class="fas fa-arrow-left me-2"></i> Retour à l'historique
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success mt-3"><?php echo $success; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger mt-3"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card shadow-sm border-0 p-4">
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label fw-bold">Sélectionner un Produit *</label>
                    <select class="form-select" name="product_id" required>
                        <option value="">-- Choisir le produit --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>">
                                <?php echo htmlspecialchars($p['name']); ?> (Actuel: <?php echo $p['qty']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Quantité Sortante *</label>
                    <input type="number" class="form-control" name="qty" required min="1" placeholder="Ex: 5">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold">Motif / Notes *</label>
                    <textarea class="form-control" name="notes" rows="3" required placeholder="Ex: Produit expiré ou endommagé"></textarea>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-danger px-4 fw-bold">
                        <i class="fas fa-save me-2"></i> Enregistrer la sortie
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
