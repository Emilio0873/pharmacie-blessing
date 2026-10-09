<?php
$page_title = "Modifier un Fournisseur - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Gérant']);

if (!isset($_GET['id'])) {
    redirect('index.php');
}

$id = (int)$_GET['id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $email = sanitize($_POST['email']);
    $address = sanitize($_POST['address']);

    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("UPDATE suppliers SET name=?, phone=?, email=?, address=? WHERE id=?");
            $stmt->execute([$name, $phone, $email, $address, $id]);
            
            log_activity($pdo, $_SESSION['user_id'], 'Modif Fournisseur', "ID: $id");
            $success = "Fournisseur mis à jour avec succès.";
        } catch (Exception $e) {
            $error = "Erreur lors de la modification : " . $e->getMessage();
        }
    } else {
        $error = "Le nom du fournisseur est obligatoire.";
    }
}

$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->execute([$id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    redirect('index.php');
}

require_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="fas fa-edit text-primary me-2"></i> Modifier Fournisseur</h3>
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
    <form method="POST" action="">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-600">Nom du Fournisseur / Entreprise *</label>
                <input type="text" class="form-control" name="name" required value="<?php echo htmlspecialchars($supplier['name']); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-600">Téléphone</label>
                <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($supplier['phone']); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-600">Email</label>
                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($supplier['email']); ?>">
            </div>
            <div class="col-md-12">
                <label class="form-label fw-600">Adresse Complète</label>
                <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($supplier['address']); ?></textarea>
            </div>
            <div class="col-12 text-end pt-3">
                <hr>
                <button type="submit" class="btn btn-primary px-4 fw-bold">
                    <i class="fas fa-save me-2"></i> Enregistrer les modifications
                </button>
            </div>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
