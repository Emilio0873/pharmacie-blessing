<?php
$page_title = "Gestion des Utilisateurs - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin']);

$error = '';
$success = '';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!csrf_valid()) {
        $error = "Action refusée. Rechargez la page puis réessayez.";
    } else {
    $del_id = (int)$_POST['delete_id'];
    if ($del_id == $_SESSION['user_id']) {
        $error = "Vous ne pouvez pas supprimer votre propre compte.";
    } else {
        try {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$del_id]);
            log_activity($pdo, $_SESSION['user_id'], 'Suppression Utilisateur', "ID: $del_id");
            $success = "Utilisateur supprimé avec succès.";
        } catch (Exception $e) {
            $error = "Erreur lors de la suppression. Cet utilisateur a peut-être des données liées.";
        }
    }
    }
}

// Handle toggle status
if (isset($_GET['toggle'])) {
    $tog_id = (int)$_GET['toggle'];
    if ($tog_id != $_SESSION['user_id']) {
        $current = $pdo->prepare("SELECT status FROM users WHERE id = ?");
        $current->execute([$tog_id]);
        $u_status = $current->fetchColumn();
        $new_status = ($u_status === 'active') ? 'inactive' : 'active';
        $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new_status, $tog_id]);
        log_activity($pdo, $_SESSION['user_id'], 'Modif Statut Utilisateur', "ID: $tog_id → " . ucfirst($new_status));
        $success = "Statut mis à jour.";
    }
}

$users = $pdo->query("SELECT u.*, r.name as role_name,
    (SELECT COUNT(*) FROM sales WHERE user_id = u.id) as total_sales,
    (SELECT COUNT(*) FROM audit_logs WHERE user_id = u.id) as total_actions
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.id
    ORDER BY u.created_at DESC")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="fas fa-user-shield text-primary me-2"></i> Gestion des Utilisateurs</h3>
        <p class="text-muted mb-0">Gérez les accès et les rôles du personnel.</p>
    </div>
    <a href="add.php" class="btn btn-primary shadow-sm"><i class="fas fa-user-plus me-2"></i> Nouvel Utilisateur</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-times-circle me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- Stats Row -->
<?php
$total_u   = count($users);
$active_u  = count(array_filter($users, fn($u) => $u['status'] === 'active'));
$admins_u  = count(array_filter($users, fn($u) => $u['role_name'] === 'Super Admin'));
?>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-3">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-light-primary text-primary me-3"><i class="fas fa-users"></i></div>
                <div><small class="text-muted fw-bold">TOTAL UTILISATEURS</small><h4 class="mb-0 fw-bold"><?php echo $total_u; ?></h4></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-3">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-light-success text-success me-3"><i class="fas fa-user-check"></i></div>
                <div><small class="text-muted fw-bold">COMPTES ACTIFS</small><h4 class="mb-0 fw-bold text-success"><?php echo $active_u; ?></h4></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-3">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-light-warning text-warning me-3"><i class="fas fa-shield-alt"></i></div>
                <div><small class="text-muted fw-bold">SUPER ADMINS</small><h4 class="mb-0 fw-bold text-warning"><?php echo $admins_u; ?></h4></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Utilisateur</th>
                        <th>Rôle</th>
                        <th class="text-center">Ventes</th>
                        <th class="text-center">Actions Log</th>
                        <th class="text-center">Statut</th>
                        <th class="text-center">Inscrit le</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3 fw-bold" style="width:40px;height:40px;font-size:16px;">
                                    <?php echo strtoupper(substr($u['full_name'] ?? 'U', 0, 1)); ?>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($u['full_name']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($u['username']); ?></small>
                                </div>
                                <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                    <span class="badge bg-info ms-2">Vous</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php
                            $role_n = $u['role_name'] ?? 'N/A';
                            $role_class = match($role_n) {
                                'Super Admin' => 'bg-danger',
                                'Admin'       => 'bg-warning text-dark',
                                'Magasinier'  => 'bg-info text-dark',
                                default       => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?php echo $role_class; ?> rounded-pill px-3">
                                <?php if ($role_n === 'Super Admin'): ?><i class="fas fa-crown me-1"></i><?php endif; ?>
                                <?php echo htmlspecialchars($role_n); ?>
                            </span>
                        </td>
                        <td class="text-center"><span class="badge bg-success rounded-pill"><?php echo $u['total_sales']; ?></span></td>
                        <td class="text-center"><span class="badge bg-info rounded-pill"><?php echo $u['total_actions']; ?></span></td>
                        <td class="text-center">
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <a href="?toggle=<?php echo $u['id']; ?>" class="text-decoration-none">
                            <?php endif; ?>
                                <?php if ($u['status'] === 'active'): ?>
                                    <span class="badge bg-success px-3 py-2"><i class="fas fa-circle me-1" style="font-size:8px;"></i>Actif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary px-3 py-2"><i class="fas fa-circle me-1" style="font-size:8px;"></i>Inactif</span>
                                <?php endif; ?>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            </a>
                            <?php endif; ?>
                        </td>
                        <td class="text-center text-muted small"><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                        <td class="text-end pe-4">
                            <a href="edit.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-light text-primary" title="Modifier"><i class="fas fa-edit"></i></a>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <?php echo csrf_delete_form($u['id'], 'Supprimer cet utilisateur ? Cette action est irréversible.', 'btn btn-sm btn-light text-danger ms-1'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($users) === 0): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">Aucun utilisateur trouvé.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
