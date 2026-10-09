<?php
$page_title = "Gestion des Catégories - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Gérant']);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    if (!csrf_valid()) {
        $error = "Action refusée. Rechargez la page puis réessayez.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Catégorie supprimée avec succès.";
            log_activity($pdo, $_SESSION['user_id'], 'Suppression Catégorie', "ID: $id");
        } catch (Exception $e) {
            $error = "Impossible de supprimer cette catégorie car elle est utilisée par des produits.";
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if (!empty($name)) {
        try {
            if ($id > 0) {
                $dup = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id <> ?");
                $dup->execute([$name, $id]);
                if ($dup->fetch()) {
                    $error = "Une catégorie portant ce nom existe déjà.";
                } else {
                    $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $id]);
                    $message = "Catégorie mise à jour.";
                    log_activity($pdo, $_SESSION['user_id'], 'Modification Catégorie', $name);
                }
            } else {
                $dup = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
                $dup->execute([$name]);
                if ($dup->fetch()) {
                    $error = "La catégorie « " . $name . " » existe déjà. Choisissez-la dans la liste des produits.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                    $stmt->execute([$name, $description]);
                    $message = "Catégorie ajoutée.";
                    log_activity($pdo, $_SESSION['user_id'], 'Ajout Catégorie', $name);
                }
            }
        } catch (Exception $e) {
            $error = db_user_message($e, "Une catégorie portant ce nom existe déjà.");
        }
    } else {
        $error = "Le nom de la catégorie est obligatoire.";
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY id DESC")->fetchAll();

require_once '../../includes/header.php';
?>

<style>
.cat-night-wrap{
    background:linear-gradient(160deg,#050b1a 0%,#0a1631 45%,#122955 100%);
    border:1px solid rgba(96,165,250,0.2);
    border-radius:20px;
    padding:1.2rem;
    box-shadow:0 10px 30px rgba(2,6,23,0.35);
}
.cat-night-wrap .table-custom,
.cat-night-wrap .table-custom thead,
.cat-night-wrap .table-custom tbody,
.cat-night-wrap .table-custom tr,
.cat-night-wrap .table-custom td,
.cat-night-wrap .table-custom th{
    background:transparent !important;
}
.cat-night-wrap .table-custom thead th{
    color:#93c5fd;
    border-color:rgba(148,163,184,0.25);
}
.cat-night-wrap .table-custom td{
    color:#e2e8f0;
    border-color:rgba(148,163,184,0.2);
}
.cat-night-wrap .text-muted{color:#94a3b8 !important;}

#addCategoryModal .modal-content{
    background:linear-gradient(155deg,#060d1d 0%,#0d1d3f 100%);
    color:#e2e8f0;
    border:1px solid rgba(96,165,250,0.25);
}
#addCategoryModal .modal-header{
    background:linear-gradient(135deg,#1d4ed8,#1e40af) !important;
    border-bottom:1px solid rgba(147,197,253,0.35);
}
#addCategoryModal .form-label{color:#bfdbfe;}
#addCategoryModal .form-control,
#addCategoryModal textarea{
    background:#0b1530;
    border:1px solid #28406b;
    color:#e2e8f0;
}
#addCategoryModal .form-control::placeholder,
#addCategoryModal textarea::placeholder{color:#64748b;}
#addCategoryModal .form-control:focus,
#addCategoryModal textarea:focus{
    border-color:#60a5fa;
    box-shadow:0 0 0 .2rem rgba(59,130,246,.2);
}
#addCategoryModal .modal-footer{border-top:1px solid rgba(148,163,184,0.25);}
#addCategoryModal .btn-light{
    background:#0b1530;
    color:#cbd5e1;
    border:1px solid #334155;
}
#addCategoryModal .btn-light:hover{background:#132245;color:#e2e8f0;}
</style>

<div class="row mb-4">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="fas fa-tags text-primary me-2"></i> Gestion des Catégories</h3>
    </div>
    <div class="col-md-6 text-end">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="fas fa-plus me-2"></i> Nouvelle Catégorie
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="cat-night-wrap">
    <div class="table-responsive">
        <table class="table table-hover table-custom mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th>Nom de la catégorie</th>
                    <th>Description</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td><?php echo $cat['id']; ?></td>
                    <td class="fw-bold"><?php echo $cat['name']; ?></td>
                    <td><?php echo $cat['description'] ?: '-'; ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-light text-primary edit-cat" 
                                data-id="<?php echo $cat['id']; ?>" 
                                data-name="<?php echo $cat['name']; ?>" 
                                data-description="<?php echo $cat['description']; ?>"
                                data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php echo csrf_delete_form($cat['id'], 'Supprimer cette catégorie ?', 'btn btn-sm btn-light text-danger ms-1'); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($categories)): ?>
                <tr>
                    <td colspan="4" class="text-center py-4 text-muted">Aucune catégorie trouvée.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Add/Edit -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">Nouvelle Catégorie</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="id" id="cat_id" value="0">
                    <div class="mb-3">
                        <label class="form-label">Nom de la catégorie</label>
                        <input type="text" class="form-control" name="name" id="cat_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="cat_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editBtns = document.querySelectorAll('.edit-cat');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('modalTitle').innerText = 'Modifier la Catégorie';
            document.getElementById('cat_id').value = this.dataset.id;
            document.getElementById('cat_name').value = this.dataset.name;
            document.getElementById('cat_description').value = this.dataset.description;
        });
    });

    const addBtn = document.querySelector('[data-bs-target="#addCategoryModal"]');
    addBtn.addEventListener('click', function() {
        if (!this.classList.contains('edit-cat')) {
            document.getElementById('modalTitle').innerText = 'Nouvelle Catégorie';
            document.getElementById('cat_id').value = '0';
            document.getElementById('cat_name').value = '';
            document.getElementById('cat_description').value = '';
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
