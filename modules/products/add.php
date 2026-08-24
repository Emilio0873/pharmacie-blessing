<?php
$page_title = "Ajouter un Produit - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');

ensure_product_lot_column($pdo);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = sanitize($_POST['code']);
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $brand = sanitize($_POST['brand']);
    $lot_number = sanitize($_POST['lot_number'] ?? '');
    $buy_price = (float)$_POST['buy_price'];
    $sell_price = (float)$_POST['sell_price'];
    $qty = (int)$_POST['qty'];
    $alert_threshold = (int)$_POST['alert_threshold'];
    $mfg_date = !empty($_POST['mfg_date']) ? $_POST['mfg_date'] : null;
    $exp_date = !empty($_POST['exp_date']) ? $_POST['exp_date'] : null;

    // Handle Image Upload
    $image_name = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $image_name = time() . '.' . $ext;
        move_uploaded_file($_FILES['image']['tmp_name'], "../../uploads/products/" . $image_name);
    }

    if (!empty($code) && !empty($name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO products (code, name, description, category_id, brand, lot_number, buy_price, sell_price, qty, alert_threshold, image, mfg_date, exp_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$code, $name, $description, $category_id, $brand, $lot_number ?: null, $buy_price, $sell_price, $qty, $alert_threshold, $image_name, $mfg_date, $exp_date]);
            
            // Log initial stock movement
            $product_id = $pdo->lastInsertId();
            if ($qty > 0) {
                $lot_note = $lot_number ? " — Lot: $lot_number" : '';
                $stmt_mv = $pdo->prepare("INSERT INTO stock_movements (product_id, type, qty, user_id, notes) VALUES (?, 'IN', ?, ?, ?)");
                $stmt_mv->execute([$product_id, $qty, $_SESSION['user_id'], 'Stock initial lors de la création' . $lot_note]);
            }

            log_activity($pdo, $_SESSION['user_id'], 'Ajout Produit', $name . ($lot_number ? " (Lot: $lot_number)" : ''));
            $success = "Produit ajouté avec succès.";
        } catch (Exception $e) {
            $error = "Erreur lors de l'ajout : " . $e->getMessage();
        }
    } else {
        $error = "Veuillez remplir les champs obligatoires (Code et Nom).";
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="fas fa-plus-circle text-primary me-2"></i> Ajouter un Produit</h3>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php" class="btn btn-light shadow-sm border">
            <i class="fas fa-arrow-left me-2"></i> Retour à la liste
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success mt-3"><?php echo $success; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger mt-3"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card shadow-sm border-0 p-4">
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="row g-4">
            <!-- Informations de base -->
            <div class="col-md-8">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-600">Code Produit / Code-barres *</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                            <input type="text" class="form-control" name="code" required placeholder="SKU-XXXX">
                        </div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-600">Nom du Produit *</label>
                        <input type="text" class="form-control" name="name" required placeholder="Ex: Paracétamol 500mg">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-600">Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Détails du produit..."></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-600">Catégorie</label>
                        <select class="form-select" name="category_id">
                            <option value="">Sélectionner une catégorie</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-600">Marque / Laboratoire</label>
                        <input type="text" class="form-control" name="brand" placeholder="Ex: Sanofi">
                    </div>
                </div>
            </div>

            <!-- Image -->
            <div class="col-md-4">
                <div class="border rounded p-3 text-center bg-light">
                    <label class="form-label d-block fw-600 mb-3">Image du Produit</label>
                    <div id="image-preview" class="mb-3 d-flex align-items-center justify-content-center bg-white rounded border" style="height: 150px;">
                        <i class="fas fa-camera fa-3x text-muted"></i>
                    </div>
                    <input type="file" class="form-control" name="image" id="image-input" accept="image/*">
                </div>
            </div>

            <!-- Prix et Stock -->
            <div class="col-12 py-3 border-top mt-4">
                <h5 class="fw-bold mb-3"><i class="fas fa-dollar-sign text-primary me-2"></i> Prix & Inventaire</h5>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-600">Prix d'Achat (PV)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" class="form-control" name="buy_price" value="0">
                            <span class="input-group-text bg-light">FC</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-600">Prix de Vente (PV)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" class="form-control" name="sell_price" value="0">
                            <span class="input-group-text bg-light">FC</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-600">Quantité Initiale</label>
                        <input type="number" min="0" class="form-control" name="qty" value="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-600">Seuil d'Alerte</label>
                        <input type="number" min="0" class="form-control" name="alert_threshold" value="5">
                    </div>
                </div>
            </div>

            <!-- Dates -->
            <div class="col-12 py-3 border-top mt-4">
                <h5 class="fw-bold mb-3"><i class="fas fa-calendar-alt text-primary me-2"></i> Lot, Dates & Expiration</h5>
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-600">N° de Lot</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-layer-group"></i></span>
                            <input type="text" class="form-control" name="lot_number" placeholder="Ex: LOT-2026-A01" maxlength="100" value="<?php echo htmlspecialchars($_POST['lot_number'] ?? ''); ?>">
                        </div>
                        <small class="text-muted">Permet de distinguer les différentes entrées du même produit.</small>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-600">Date de Fabrication</label>
                        <input type="date" class="form-control" name="mfg_date">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-600">Date d'Expiration</label>
                        <input type="date" class="form-control" name="exp_date">
                    </div>
                </div>
            </div>

            <div class="col-12 pt-4 text-end">
                <hr>
                <button type="submit" class="btn btn-primary px-5 py-2 fw-bold shadow">
                    <i class="fas fa-save me-2"></i> Enregistrer le Produit
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.getElementById('image-input').addEventListener('change', function(e) {
    const preview = document.getElementById('image-preview');
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" class="img-fluid rounded" style="max-height: 100%;">`;
        }
        reader.readAsDataURL(file);
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
