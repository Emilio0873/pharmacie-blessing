<?php
$page_title = "Ajouter un Utilisateur - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
if (!has_role('Super Admin')) redirect('../../dashboard.php');

// Load roles
$roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $username  = sanitize($_POST['username']);
    $password  = $_POST['password'];
    $role_id   = (int)$_POST['role_id'];
    $status    = isset($_POST['is_active']) ? 'active' : 'inactive';

    if (empty($full_name) || empty($username) || empty($password)) {
        $error = "Le nom complet, le nom d'utilisateur et le mot de passe sont obligatoires.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit comporter au moins 6 caractères.";
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            $error = "Ce nom d'utilisateur est déjà pris.";
        } else {
            try {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (full_name, username, password, role_id, status) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$full_name, $username, $hashed, $role_id, $status]);
                log_activity($pdo, $_SESSION['user_id'], 'Création Utilisateur', "Nom: $full_name, Role ID: $role_id");
                $success = "Utilisateur <strong>$full_name</strong> créé avec succès.";
            } catch (Exception $e) {
                $error = "Erreur : " . $e->getMessage();
            }
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="fas fa-user-plus text-primary me-2"></i> Nouvel Utilisateur</h3>
    </div>
    <div class="col-md-6 text-end">
        <a href="index.php" class="btn btn-light shadow-sm border"><i class="fas fa-arrow-left me-2"></i> Retour à la liste</a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-times-circle me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 p-4">
            <form method="POST" action="">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Nom Complet *</label>
                        <input type="text" class="form-control" name="full_name" required placeholder="Ex: Marie Kabongo"
                            value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Nom d'utilisateur (Login) *</label>
                        <input type="text" class="form-control" name="username" required placeholder="Ex: jdupont"
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        <small class="text-muted">Unique. Utilisé pour se connecter.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Mot de passe *</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="password" id="passwordField" required placeholder="Min. 6 caractères">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePwd()"><i class="fas fa-eye" id="pwdEyeIcon"></i></button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Rôle *</label>
                        <select class="form-select" name="role_id">
                            <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>" <?php if(isset($_POST['role_id']) && (int)$_POST['role_id'] === $role['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($role['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="isActive" checked>
                            <label class="form-check-label fw-bold" for="isActive">Compte actif dès la création</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i><strong>Rôles :</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($roles as $role): ?>
                                <li><strong><?php echo htmlspecialchars($role['name']); ?></strong> — <?php echo htmlspecialchars($role['permissions'] ?? 'N/A'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <div class="col-12 text-end pt-2">
                        <button type="submit" class="btn btn-primary px-5 fw-bold">
                            <i class="fas fa-save me-2"></i> Créer l'utilisateur
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function togglePwd() {
    const f = document.getElementById('passwordField');
    const i = document.getElementById('pwdEyeIcon');
    if (f.type === 'password') { f.type = 'text'; i.className = 'fas fa-eye-slash'; }
    else { f.type = 'password'; i.className = 'fas fa-eye'; }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
