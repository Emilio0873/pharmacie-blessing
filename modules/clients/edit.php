<?php
$page_title = "Modifier un Client - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Caissier', 'Facturier']);
if (!isset($_GET['id'])) redirect('index.php');

$id    = (int)$_GET['id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = sanitize($_POST['name']);
    $phone   = sanitize($_POST['phone']);
    $email   = sanitize($_POST['email']);
    $address = sanitize($_POST['address']);

    // Récupérer la photo actuelle
    $stmt_cur = $pdo->prepare("SELECT photo FROM clients WHERE id=?");
    $stmt_cur->execute([$id]);
    $cur = $stmt_cur->fetch();
    $photo = $cur['photo'];

    // Gérer le nouvel upload
    if (!empty($_FILES['photo']['name'])) {
        $allowed = ['image/jpeg','image/jpg','image/png','image/webp','image/gif'];
        $ftype   = mime_content_type($_FILES['photo']['tmp_name']);
        if (in_array($ftype, $allowed) && $_FILES['photo']['size'] < 3*1024*1024) {
            $ext   = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $fname = 'client_' . time() . '_' . rand(100,999) . '.' . $ext;
            $dest  = __DIR__ . '/../../uploads/clients/' . $fname;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                // Supprimer l'ancienne photo
                if ($photo && file_exists(__DIR__ . '/../../uploads/clients/' . $photo)) {
                    unlink(__DIR__ . '/../../uploads/clients/' . $photo);
                }
                $photo = $fname;
            }
        } else {
            $error = "Photo invalide. Format JPG/PNG/WEBP, max 3 Mo.";
        }
    }

    if (empty($error) && !empty($name)) {
        try {
            $stmt = $pdo->prepare("UPDATE clients SET name=?, phone=?, email=?, address=?, photo=? WHERE id=?");
            $stmt->execute([$name, $phone, $email, $address, $photo, $id]);
            log_activity($pdo, $_SESSION['user_id'], 'Modif Client', "Client ID: $id");
            $success = "Client mis à jour avec succès !";
        } catch (Exception $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    } elseif (empty($error)) {
        $error = "Le nom du client est obligatoire.";
    }
}

$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) redirect('index.php');

require_once '../../includes/header.php';
?>

<style>
.client-form-hero{background:linear-gradient(135deg,#7c3aed 0%,#4f46e5 100%);border-radius:20px;padding:2.5rem;color:#fff;margin-bottom:2rem;position:relative;overflow:hidden;}
.client-form-hero::before{content:'';position:absolute;right:-60px;top:-60px;width:220px;height:220px;background:rgba(255,255,255,0.07);border-radius:50%;}
.photo-upload-zone{border:2.5px dashed #818cf8;border-radius:18px;background:linear-gradient(135deg,#0b1426,#111b2f);transition:all 0.3s cubic-bezier(.4,0,.2,1);cursor:pointer;position:relative;overflow:hidden;min-height:200px;display:flex;flex-direction:column;align-items:center;justify-content:center;}
.photo-upload-zone:hover{border-color:#a5b4fc;background:linear-gradient(135deg,#111b2f,#0b1426);transform:translateY(-2px);box-shadow:0 8px 25px rgba(124,58,237,0.2);}
.photo-preview-img{width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0;border-radius:16px;}
.photo-overlay{position:absolute;inset:0;background:rgba(124,58,237,0.55);border-radius:16px;display:none;align-items:center;justify-content:center;transition:opacity 0.3s;}
.photo-upload-zone:hover .photo-overlay{display:flex;}
.form-card{background:linear-gradient(180deg, rgba(17, 27, 47, 0.98) 0%, rgba(11, 18, 32, 0.98) 100%);border-radius:20px;box-shadow:0 4px 30px rgba(2,6,23,0.35);border:1px solid #223253;padding:2rem;animation:slideUp 0.4s cubic-bezier(.4,0,.2,1);color:#e2e8f0;}
@media (max-width:575.98px){.form-card{padding:1.2rem;}.client-form-hero{padding:1.4rem;}}
@keyframes slideUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
.form-control,.form-select{border-radius:12px;border:1.5px solid #334155;padding:0.65rem 1rem;transition:all 0.25s;background:#0b1426;color:#e2e8f0;}
.form-control:focus,.form-select:focus{border-color:#818cf8;box-shadow:0 0 0 3px rgba(124,58,237,0.18);transform:translateY(-1px);background:#111b2f;color:#f8fafc;}
.btn-save{background:linear-gradient(135deg,#7c3aed,#4f46e5);border:none;border-radius:14px;padding:0.75rem 2.5rem;font-weight:700;font-size:1rem;color:#fff;transition:all 0.3s;box-shadow:0 4px 15px rgba(124,58,237,0.28);}
.btn-save:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(124,58,237,0.38);color:#fff;}
.field-label{font-weight:600;font-size:0.85rem;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;}
.alert-custom{border-radius:14px;border:none;font-weight:600;}
.current-photo-badge{position:absolute;top:10px;right:10px;background:rgba(124,58,237,0.85);color:#fff;font-size:0.7rem;padding:3px 8px;border-radius:20px;backdrop-filter:blur(4px);}
</style>

<div class="client-form-hero">
    <div class="position-relative" style="z-index:1;">
        <nav aria-label="breadcrumb" class="mb-2">
            <ol class="breadcrumb mb-0" style="--bs-breadcrumb-divider-color:rgba(255,255,255,0.5);">
                <li class="breadcrumb-item"><a href="index.php" class="text-white-50 text-decoration-none">Clients</a></li>
                <li class="breadcrumb-item"><a href="view.php?id=<?php echo $id; ?>" class="text-white-50 text-decoration-none"><?php echo htmlspecialchars($client['name']); ?></a></li>
                <li class="breadcrumb-item active text-white">Modifier</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center gap-3">
            <?php if ($client['photo']): ?>
            <img src="<?php echo app_base_url('uploads/clients/' . rawurlencode($client['photo'])); ?>" 
                 style="width:52px;height:52px;object-fit:cover;border-radius:50%;border:3px solid rgba(255,255,255,0.4);">
            <?php else: ?>
            <div style="width:52px;height:52px;background:rgba(255,255,255,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:700;">
                <?php echo strtoupper(substr($client['name'],0,1)); ?>
            </div>
            <?php endif; ?>
            <div>
                <h2 class="fw-bold mb-0">Modifier Client</h2>
                <small class="text-white-50"><?php echo htmlspecialchars($client['name']); ?></small>
            </div>
        </div>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success alert-custom d-flex align-items-center gap-2 mb-3">
    <i class="fas fa-check-circle fa-lg"></i><span><?php echo $success; ?></span>
    <a href="view.php?id=<?php echo $id; ?>" class="btn btn-sm btn-success ms-auto">Voir le profil</a>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-custom d-flex align-items-center gap-2 mb-3">
    <i class="fas fa-exclamation-circle fa-lg"></i><span><?php echo $error; ?></span>
</div>
<?php endif; ?>

<div class="form-card">
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="row g-4">

            <!-- Photo -->
            <div class="col-12 col-md-4">
                <div class="field-label mb-2">Photo du client</div>
                <div class="photo-upload-zone" id="uploadZone" onclick="document.getElementById('photoInput').click()">
                    <?php if ($client['photo']): ?>
                        <img id="photoPreview" class="photo-preview-img" 
                             src="<?php echo app_base_url('uploads/clients/' . rawurlencode($client['photo'])); ?>" alt="Photo">
                        <span class="current-photo-badge"><i class="fas fa-check me-1"></i>Photo actuelle</span>
                    <?php else: ?>
                        <img id="photoPreview" class="photo-preview-img d-none" src="" alt="Aperçu">
                    <?php endif; ?>
                    <div class="photo-overlay">
                        <span class="text-white fw-bold"><i class="fas fa-camera me-2"></i>Changer la photo</span>
                    </div>
                    <div id="uploadPlaceholder" <?php echo $client['photo'] ? 'style="display:none"' : ''; ?>>
                        <div style="font-size:2.5rem;color:#818cf8;margin-bottom:8px;"><i class="fas fa-camera-retro"></i></div>
                        <div class="fw-bold mb-1 text-primary">Ajouter une photo</div>
                        <small class="text-muted">JPG, PNG, WEBP · max 3 Mo</small>
                    </div>
                    <input type="file" id="photoInput" name="photo" accept="image/*" class="d-none">
                </div>
            </div>

            <!-- Champs -->
            <div class="col-12 col-md-8">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="field-label">Nom complet <span class="text-danger">*</span></div>
                        <input type="text" class="form-control" name="name" required value="<?php echo htmlspecialchars($client['name']); ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="field-label">Téléphone</div>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="fas fa-phone text-muted small"></i></span>
                            <input type="text" class="form-control border-start-0" name="phone" value="<?php echo htmlspecialchars($client['phone']); ?>" style="border-radius:0 12px 12px 0;">
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="field-label">Email</div>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="fas fa-envelope text-muted small"></i></span>
                            <input type="email" class="form-control border-start-0" name="email" value="<?php echo htmlspecialchars($client['email']); ?>" style="border-radius:0 12px 12px 0;">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="field-label">Adresse</div>
                        <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($client['address']); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <hr class="my-1">
                <div class="d-flex justify-content-between align-items-center pt-2">
                    <a href="view.php?id=<?php echo $id; ?>" class="btn btn-light border fw-bold px-4" style="border-radius:12px;">
                        <i class="fas fa-arrow-left me-2"></i> Retour
                    </a>
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save me-2"></i> Enregistrer les modifications
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
const photoInput = document.getElementById('photoInput');
const photoPreview = document.getElementById('photoPreview');
const uploadPlaceholder = document.getElementById('uploadPlaceholder');

photoInput.addEventListener('change', function() {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            photoPreview.src = e.target.result;
            photoPreview.classList.remove('d-none');
            if (uploadPlaceholder) uploadPlaceholder.style.display = 'none';
        };
        reader.readAsDataURL(this.files[0]);
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
