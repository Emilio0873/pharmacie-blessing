<?php
$page_title = "Ajouter un Client - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Caissier', 'Facturier']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = sanitize($_POST['name']);
    $phone   = sanitize($_POST['phone']);
    $email   = sanitize($_POST['email']);
    $address = sanitize($_POST['address']);
    $photo   = null;

    // Gestion upload photo
    if (!empty($_FILES['photo']['name'])) {
        $allowed = ['image/jpeg','image/jpg','image/png','image/webp','image/gif'];
        $ftype   = mime_content_type($_FILES['photo']['tmp_name']);
        if (in_array($ftype, $allowed) && $_FILES['photo']['size'] < 3*1024*1024) {
            $ext    = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $fname  = 'client_' . time() . '_' . rand(100,999) . '.' . $ext;
            $dest   = __DIR__ . '/../../uploads/clients/' . $fname;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                $photo = $fname;
            }
        } else {
            $error = "Photo invalide. Format JPG/PNG/WEBP, max 3 Mo.";
        }
    }

    if (empty($error) && !empty($name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO clients (name, phone, email, address, photo) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $email, $address, $photo]);
            log_activity($pdo, $_SESSION['user_id'], 'Ajout Client', "Nom: $name");
            $success = "Client <strong>" . htmlspecialchars($name) . "</strong> ajouté avec succès !";
        } catch (Exception $e) {
            $error = "Erreur lors de l'ajout : " . $e->getMessage();
        }
    } elseif (empty($error)) {
        $error = "Le nom du client est obligatoire.";
    }
}

require_once '../../includes/header.php';
?>

<style>
.client-form-hero{background:linear-gradient(135deg,#0d6efd 0%,#0a58ca 100%);border-radius:20px;padding:2.5rem;color:#fff;margin-bottom:2rem;position:relative;overflow:hidden;}
.client-form-hero::before{content:'';position:absolute;right:-60px;top:-60px;width:220px;height:220px;background:rgba(255,255,255,0.07);border-radius:50%;}
.client-form-hero::after{content:'';position:absolute;right:40px;top:50px;width:110px;height:110px;background:rgba(255,255,255,0.05);border-radius:50%;}
.photo-upload-zone{border:2.5px dashed #3b82f6;border-radius:18px;background:linear-gradient(135deg,#0b1426,#111b2f);transition:all 0.3s cubic-bezier(.4,0,.2,1);cursor:pointer;position:relative;overflow:hidden;min-height:180px;display:flex;flex-direction:column;align-items:center;justify-content:center;}
.photo-upload-zone:hover{border-color:#60a5fa;background:linear-gradient(135deg,#111b2f,#0b1426);transform:translateY(-2px);box-shadow:0 8px 25px rgba(29,78,216,0.2);}
.photo-upload-zone.drag-over{border-color:#60a5fa;background:#0b1426;transform:scale(1.01);}
.photo-preview-img{width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0;border-radius:16px;}
.photo-overlay{position:absolute;inset:0;background:rgba(13,110,253,0.55);border-radius:16px;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity 0.3s;}
.photo-upload-zone:hover .photo-overlay{opacity:1;}
.form-card{background:linear-gradient(180deg, rgba(17, 27, 47, 0.98) 0%, rgba(11, 18, 32, 0.98) 100%);border-radius:20px;box-shadow:0 4px 30px rgba(2,6,23,0.35);border:1px solid #223253;padding:2rem;animation:slideUp 0.4s cubic-bezier(.4,0,.2,1);color:#e2e8f0;}
@media (max-width:575.98px){.form-card{padding:1.2rem;}}
@keyframes slideUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
.form-control,.form-select{border-radius:12px;border:1.5px solid #334155;padding:0.65rem 1rem;transition:all 0.25s;font-size:0.95rem;background:#0b1426;color:#e2e8f0;}
.form-control:focus,.form-select:focus{border-color:#60a5fa;box-shadow:0 0 0 3px rgba(59,130,246,0.18);transform:translateY(-1px);background:#111b2f;color:#f8fafc;}
.btn-save{background:linear-gradient(135deg,#0d6efd,#0a58ca);border:none;border-radius:14px;padding:0.75rem 2.5rem;font-weight:700;font-size:1rem;color:#fff;transition:all 0.3s;box-shadow:0 4px 15px rgba(13,110,253,0.28);}
.btn-save:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(13,110,253,0.38);color:#fff;}
.upload-icon{font-size:2.5rem;color:#60a5fa;margin-bottom:8px;transition:transform 0.3s;}
.photo-upload-zone:hover .upload-icon{transform:scale(1.15);}
.field-label{font-weight:600;font-size:0.85rem;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;}
.alert-custom{border-radius:14px;border:none;font-weight:600;animation:slideUp 0.3s ease;}
</style>

<!-- Hero Header -->
<div class="client-form-hero">
    <div class="position-relative" style="z-index:1;">
        <nav aria-label="breadcrumb" class="mb-2">
            <ol class="breadcrumb mb-0" style="--bs-breadcrumb-divider-color:rgba(255,255,255,0.5);">
                <li class="breadcrumb-item"><a href="index.php" class="text-white-50 text-decoration-none">Clients</a></li>
                <li class="breadcrumb-item active text-white">Nouveau client</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center gap-3">
            <div style="width:52px;height:52px;background:rgba(255,255,255,0.15);border-radius:14px;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(10px);">
                <i class="fas fa-user-plus fa-lg"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0">Nouveau Client</h2>
                <small class="text-white-50">Remplissez les informations du client</small>
            </div>
        </div>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success alert-custom d-flex align-items-center gap-2 mb-3">
    <i class="fas fa-check-circle fa-lg"></i>
    <span><?php echo $success; ?></span>
    <a href="index.php" class="btn btn-sm btn-success ms-auto">Voir la liste</a>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-custom d-flex align-items-center gap-2 mb-3">
    <i class="fas fa-exclamation-circle fa-lg"></i>
    <span><?php echo $error; ?></span>
</div>
<?php endif; ?>

<div class="form-card">
    <form method="POST" action="" enctype="multipart/form-data" id="clientForm">
        <div class="row g-4">

            <!-- Photo upload -->
            <div class="col-12 col-md-4">
                <div class="field-label mb-2">Photo du client</div>
                <div class="photo-upload-zone" id="uploadZone" onclick="document.getElementById('photoInput').click()">
                    <img id="photoPreview" class="photo-preview-img d-none" src="" alt="Aperçu">
                    <div class="photo-overlay" id="changeOverlay" style="display:none!important;">
                        <span class="text-white fw-bold"><i class="fas fa-camera me-2"></i>Changer</span>
                    </div>
                    <div id="uploadPlaceholder">
                        <div class="upload-icon"><i class="fas fa-camera-retro"></i></div>
                        <div class="fw-bold text-primary mb-1">Ajouter une photo</div>
                        <small class="text-muted">JPG, PNG, WEBP · max 3 Mo</small>
                    </div>
                    <input type="file" id="photoInput" name="photo" accept="image/*" class="d-none">
                </div>
            </div>

            <!-- Champs texte -->
            <div class="col-12 col-md-8">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="field-label">Nom complet <span class="text-danger">*</span></div>
                        <input type="text" class="form-control" name="name" required placeholder="Ex: Marie Kabongo" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="field-label">Téléphone</div>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="fas fa-phone text-muted small"></i></span>
                            <input type="text" class="form-control border-start-0" name="phone" placeholder="+243 000 000 000" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" style="border-radius:0 12px 12px 0;">
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="field-label">Email</div>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="fas fa-envelope text-muted small"></i></span>
                            <input type="email" class="form-control border-start-0" name="email" placeholder="client@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" style="border-radius:0 12px 12px 0;">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="field-label">Adresse</div>
                        <textarea class="form-control" name="address" rows="3" placeholder="Adresse complète du client"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="col-12">
                <hr class="my-1">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-2 pt-2">
                    <a href="index.php" class="btn btn-light border fw-bold px-4" style="border-radius:12px;">
                        <i class="fas fa-arrow-left me-2"></i> Retour
                    </a>
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save me-2"></i> Enregistrer le client
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Photo preview
const photoInput = document.getElementById('photoInput');
const photoPreview = document.getElementById('photoPreview');
const uploadPlaceholder = document.getElementById('uploadPlaceholder');
const uploadZone = document.getElementById('uploadZone');
const changeOverlay = document.getElementById('changeOverlay');

photoInput.addEventListener('change', function() {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            photoPreview.src = e.target.result;
            photoPreview.classList.remove('d-none');
            uploadPlaceholder.style.display = 'none';
            changeOverlay.style.display = 'flex !important';
            changeOverlay.style.cssText = 'position:absolute;inset:0;background:rgba(13,110,253,0.55);border-radius:16px;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity 0.3s;';
        };
        reader.readAsDataURL(this.files[0]);
    }
});

// Drag & Drop
uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('drag-over'); });
uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('drag-over'));
uploadZone.addEventListener('drop', e => {
    e.preventDefault();
    uploadZone.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
        const dt = new DataTransfer();
        dt.items.add(file);
        photoInput.files = dt.files;
        photoInput.dispatchEvent(new Event('change'));
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
