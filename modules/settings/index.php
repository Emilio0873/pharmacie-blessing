<?php
$page_title = "Paramètres du Système - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in() || !has_role('Super Admin')) redirect('../../index.php');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'backup') {
        $candidates = [
            dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'mysql' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'mysqldump.exe',
            'E:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        ];
        $dump = null;
        foreach ($candidates as $path) {
            if (is_file($path)) {
                $dump = $path;
                break;
            }
        }

        $backupDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'backups';
        if (!$dump) {
            $error = "mysqldump introuvable. Vérifiez que MySQL est installé avec XAMPP.";
        } else {
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0775, true);
            }
            $backupName = 'backup_' . date('Ymd_His') . '.sql';
            $backupPath = $backupDir . DIRECTORY_SEPARATOR . $backupName;
            $command = escapeshellarg($dump)
                . ' --host=' . escapeshellarg($host)
                . ' --port=' . escapeshellarg((string)$port)
                . ' --user=' . escapeshellarg($user)
                . ($pass !== '' ? ' --password=' . escapeshellarg($pass) : '')
                . ' --single-transaction --databases ' . escapeshellarg($db)
                . ' > ' . escapeshellarg($backupPath) . ' 2>&1';
            exec($command, $output, $result);

            if ($result === 0 && is_file($backupPath) && filesize($backupPath) > 0) {
                $success = "Sauvegarde réussie. Fichier : $backupName";
                log_activity($pdo, $_SESSION['user_id'], 'Sauvegarde BDD', "Fichier: $backupName");
            } else {
                $error = "Erreur lors de la sauvegarde.";
            }
        }
    } elseif ($_POST['action'] === 'update_business') {
        try {
            $business_name = sanitize($_POST['business_name'] ?? 'PHARMACIE BLESSING');
            $business_subtitle = sanitize($_POST['business_subtitle'] ?? 'Dépôt Pharmaceutique de Référence');
            $business_address = sanitize($_POST['business_address'] ?? '');
            $business_phone = sanitize($_POST['business_phone'] ?? '');
            $business_email = sanitize($_POST['business_email'] ?? '');
            $business_legal_ids = sanitize($_POST['business_legal_ids'] ?? '');

            set_app_setting($pdo, 'business_name', $business_name);
            set_app_setting($pdo, 'business_subtitle', $business_subtitle);
            set_app_setting($pdo, 'business_address', $business_address);
            set_app_setting($pdo, 'business_phone', $business_phone);
            set_app_setting($pdo, 'business_email', $business_email);
            set_app_setting($pdo, 'business_legal_ids', $business_legal_ids);

            $success = "Informations de la pharmacie mises à jour avec succès.";
            log_activity($pdo, $_SESSION['user_id'], 'Mise à jour paramètres', 'Coordonnées pharmacie modifiées (adresse/téléphone/email).');
        } catch (Exception $e) {
            $error = "Erreur lors de la mise à jour des paramètres.";
        }
    }
}

$business = get_business_profile($pdo);

require_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h3 class="fw-bold"><i class="fas fa-cogs text-primary me-2"></i> Paramètres du Système</h3>
    </div>
</div>

<?php if (isset($success) && $success): ?>
    <div class="alert alert-success mt-3"><?php echo $success; ?></div>
<?php endif; ?>
<?php if (isset($error) && $error): ?>
    <div class="alert alert-danger mt-3"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row justify-content-center">
    <!-- Informations de l'entreprise -->
    <div class="col-md-8 col-lg-7">
        <div class="card shadow-sm border-0 p-4">
            <h5 class="fw-bold mb-4"><i class="fas fa-building text-secondary me-2"></i> Informations de la Pharmacie</h5>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_business">
                <div class="mb-3">
                    <label class="form-label fw-600">Nom de l'entreprise</label>
                    <input type="text" class="form-control" name="business_name" value="<?php echo htmlspecialchars($business['name']); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-600">Slogan / Sous-titre</label>
                    <input type="text" class="form-control" name="business_subtitle" value="<?php echo htmlspecialchars($business['subtitle']); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-600">Devise Principale</label>
                    <input type="text" class="form-control" value="Franc Congolais (FC)" readonly>
                    <small class="text-muted">La devise a été formatée et standardisée partout dans le système.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-600">Adresse Globale</label>
                    <input type="text" class="form-control" name="business_address" value="<?php echo htmlspecialchars($business['address']); ?>" placeholder="Adresse de la pharmacie">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-600">Numéro de téléphone principal</label>
                    <input type="text" class="form-control" name="business_phone" value="<?php echo htmlspecialchars($business['phone']); ?>" placeholder="+243 ...">
                    <small class="text-muted">Ce numéro sera automatiquement utilisé sur les nouvelles factures.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-600">Email principal</label>
                    <input type="email" class="form-control" name="business_email" value="<?php echo htmlspecialchars($business['email']); ?>" placeholder="contact@...">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-600">Mentions légales</label>
                    <input type="text" class="form-control" name="business_legal_ids" value="<?php echo htmlspecialchars($business['legal_ids']); ?>" placeholder="RCCM / NIF ...">
                </div>
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i> Mettre à jour</button>
            </form>
        </div>
    </div>
</div>

<div class="row justify-content-center mt-4">
    <div class="col-md-8 col-lg-7">
        <div class="card shadow-sm border-0 p-4">
            <h5 class="fw-bold mb-3"><i class="fas fa-database text-primary me-2"></i> Sauvegarde de la Base</h5>
            <form method="POST" action="">
                <input type="hidden" name="action" value="backup">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="fas fa-download me-2"></i> Lancer une sauvegarde maintenant
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
