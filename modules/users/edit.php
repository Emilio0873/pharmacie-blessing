<?php
$page_title = "Modifier Utilisateur - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
if (!has_role('Super Admin')) redirect('../../dashboard.php');
if (!isset($_GET['id'])) redirect('index.php');

$id = (int)$_GET['id'];
$error = '';
$success = '';

$roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();

// Fetch user
$stmt = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) redirect('index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name    = sanitize($_POST['full_name']);
    $username     = sanitize($_POST['username']);
    $role_id      = (int)$_POST['role_id'];
    $status       = isset($_POST['is_active']) ? 'active' : 'inactive';
    $new_password = $_POST['new_password'];

    if (empty($full_name) || empty($username)) {
        $error = "Le nom complet et le nom d'utilisateur sont obligatoires.";
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->execute([$username, $id]);
        if ($check->fetch()) {
            $error = "Ce nom d'utilisateur est déjà pris.";
        } else {
            try {
                // Protect own role/status
                if ($id == $_SESSION['user_id']) {
                    $role_id = $user['role_id'];
                    $status  = 'active';
                }
                if (!empty($new_password)) {
                    if (strlen($new_password) < 6) throw new Exception("Le nouveau mot de passe doit comporter au moins 6 caractères.");
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET full_name=?, username=?, password=?, role_id=?, status=? WHERE id=?")
                        ->execute([$full_name, $username, $hashed, $role_id, $status, $id]);
                } else {
                    $pdo->prepare("UPDATE users SET full_name=?, username=?, role_id=?, status=? WHERE id=?")
                        ->execute([$full_name, $username, $role_id, $status, $id]);
                }
                log_activity($pdo, $_SESSION['user_id'], 'Modif Utilisateur', "ID: $id, Nom: $full_name");
                $success = "Utilisateur mis à jour avec succès.";
                // Refresh
                $stmt->execute([$id]);
                $user = $stmt->fetch();
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="fas fa-user-edit text-primary me-2"></i> Modifier Utilisateur</h3>
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
                        <input type="text" class="form-control" name="full_name" required value="<?php echo htmlspecialchars($user['full_name']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Nom d'utilisateur (Login) *</label>
                        <input type="text" class="form-control" name="username" required value="<?php echo htmlspecialchars($user['username']); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Nouveau mot de passe</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="new_password" id="passwordField" placeholder="Laisser vide pour conserver">
                            <button type="button" class="btn btn-outline-secondary" onclick="togglePwd()"><i class="fas fa-eye" id="pwdEyeIcon"></i></button>
                        </div>
                        <small class="text-muted">Laissez vide pour ne pas changer.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Rôle *</label>
                        <?php if ($id == $_SESSION['user_id']): ?>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['role_name'] ?? 'N/A'); ?>" readonly>
                            <input type="hidden" name="role_id" value="<?php echo $user['role_id']; ?>">
                            <small class="text-warning"><i class="fas fa-lock me-1"></i>Vous ne pouvez pas changer votre propre rôle.</small>
                        <?php else: ?>
                            <select class="form-select" name="role_id">
                                <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>" <?php if((int)$user['role_id'] === $role['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($role['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="col-12">
                        <?php if ($id == $_SESSION['user_id']): ?>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" checked disabled>
                                <label class="form-check-label fw-bold">Compte actif <span class="text-warning">(votre propre compte ne peut pas être désactivé)</span></label>
                            </div>
                            <input type="hidden" name="is_active" value="1">
                        <?php else: ?>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?php if($user['status'] === 'active') echo 'checked'; ?>>
                                <label class="form-check-label fw-bold" for="isActive">Compte actif</label>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 text-end pt-2">
                        <hr>
                        <button type="submit" class="btn btn-primary px-5 fw-bold">
                            <i class="fas fa-save me-2"></i> Enregistrer les modifications
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
