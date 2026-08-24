<?php
$page_title = "Journal d'Activités - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in() || !has_role('Super Admin')) redirect('../../index.php');

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$query = "SELECT a.*, u.full_name FROM audit_logs a JOIN users u ON a.user_id = u.id WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (a.action LIKE ? OR u.full_name LIKE ? OR a.details LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY a.created_at DESC LIMIT 500";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h3 class="fw-bold"><i class="fas fa-list-alt text-primary me-2"></i> Journal d'Activités (Audit)</h3>
        <p class="text-muted">Traçabilité des 500 dernières actions dans le système.</p>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" action="">
            <div class="input-group">
                <input type="text" class="form-control" name="search" placeholder="Chercher une action, un utilisateur, des détails..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrer</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-custom align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Date & Heure</th>
                        <th>Utilisateur</th>
                        <th>Action Initée</th>
                        <th>Détails Techniques</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td class="ps-4 text-nowrap"><small class="text-muted"><i class="far fa-clock me-1"></i> <?php echo date('d/m/Y H:i:s', strtotime($l['created_at'])); ?></small></td>
                                <td class="fw-bold"><i class="fas fa-user-circle text-primary me-1"></i> <?php echo htmlspecialchars($l['full_name']); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($l['action']); ?></span></td>
                                <td><small class="text-muted text-wrap" style="max-width: 300px; display: block;"><?php echo htmlspecialchars($l['details']); ?></small></td>
                                <td><small class="font-monospace"><?php echo htmlspecialchars($l['ip_address'] ?? 'Local'); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">Aucune activité trouvée.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
