<?php
$page_title = "Clients - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Caissier']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!csrf_valid()) {
        $error = "Action refusée. Rechargez la page puis réessayez.";
    } else {
    $id = (int)$_POST['delete_id'];
    try {
        // Remove photo file if exists
        $r = $pdo->prepare("SELECT photo FROM clients WHERE id=?"); $r->execute([$id]);
        $row = $r->fetch();
        if ($row && $row['photo'] && file_exists(__DIR__ . '/../../uploads/clients/' . $row['photo'])) {
            unlink(__DIR__ . '/../../uploads/clients/' . $row['photo']);
        }
        $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$id]);
        $success = "Client supprimé avec succès.";
    } catch (Exception $e) {
        $error = "Erreur lors de la suppression (client lié à des ventes).";
    }
    }
}

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$query  = "SELECT c.*, 
           (SELECT COUNT(*) FROM sales WHERE client_id = c.id) as total_sales_count,
           (SELECT SUM(final_amount) FROM sales WHERE client_id = c.id) as total_sales_amount
           FROM clients c WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (c.name LIKE ? OR c.phone LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
$query .= " ORDER BY c.name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<style>
/* === CLIENTS INDEX PREMIUM === */
.page-hero {
    background: linear-gradient(135deg, #0d6efd 0%, #0a3d8f 100%);
    border-radius: 22px;
    padding: 2.2rem 2.5rem;
    color: #fff;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}
.page-hero::before { content:''; position:absolute; right:-80px; top:-80px; width:250px; height:250px; background:rgba(255,255,255,0.06); border-radius:50%; }
.page-hero::after  { content:''; position:absolute; right:60px; top:60px; width:120px; height:120px; background:rgba(255,255,255,0.04); border-radius:50%; }
.search-bar-wrap   { background:#0b1426; border-radius:18px; box-shadow:0 8px 24px rgba(2,6,23,0.38); padding:1.2rem 1.5rem; margin-bottom:1.8rem; border:1px solid #223253; }
.search-bar-wrap .form-label { color:#94a3b8 !important; }
.search-bar-wrap .input-group-text {
    background:#111b2f !important;
    border-color:#334155 !important;
    color:#94a3b8 !important;
}
.search-bar-wrap .form-control {
    background:#111b2f !important;
    border-color:#334155 !important;
    color:#e2e8f0 !important;
}
.search-bar-wrap .form-control::placeholder { color:#64748b; }
.search-bar-wrap .form-control:focus {
    border-color:#60a5fa !important;
    box-shadow:0 0 0 .2rem rgba(59,130,246,.18) !important;
}
.clients-grid      { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:1.3rem; }

/* Client Card */
.client-card {
    background: linear-gradient(180deg, rgba(17, 27, 47, 0.98) 0%, rgba(11, 18, 32, 0.98) 100%);
    border-radius:20px;
    overflow:hidden;
    box-shadow:0 4px 22px rgba(2,6,23,0.35);
    border:1px solid #223253;
    transition:all 0.35s cubic-bezier(.4,0,.2,1);
    animation:fadeInUp 0.4s ease both;
    position:relative;
    cursor:pointer;
}
.client-card:hover { transform:translateY(-6px); box-shadow:0 16px 40px rgba(29,78,216,0.22); border-color:rgba(147,197,253,0.35); }
@keyframes fadeInUp {
    from { opacity:0; transform:translateY(20px); }
    to   { opacity:1; transform:translateY(0); }
}
.client-card:nth-child(1)  { animation-delay:0.03s }
.client-card:nth-child(2)  { animation-delay:0.07s }
.client-card:nth-child(3)  { animation-delay:0.11s }
.client-card:nth-child(4)  { animation-delay:0.15s }
.client-card:nth-child(5)  { animation-delay:0.19s }
.client-card:nth-child(6)  { animation-delay:0.23s }
.client-card:nth-child(7)  { animation-delay:0.27s }
.client-card:nth-child(8)  { animation-delay:0.31s }

.card-photo-zone { position:relative; height:120px; overflow:hidden; }
.card-photo-zone::after {
    content:''; position:absolute; inset:0;
    background:linear-gradient(to bottom, transparent 40%, rgba(0,0,0,0.55) 100%);
}
.card-photo-img { width:100%; height:100%; object-fit:cover; transition:transform 0.5s ease; }
.client-card:hover .card-photo-img { transform:scale(1.07); }

/* Avatar initiales */
.card-avatar-init {
    width:100%; height:120px;
    display:flex; align-items:center; justify-content:center;
    font-size:2.2rem; font-weight:800; color:#fff; letter-spacing:-1px;
}

/* Mini-badge actions en hover sur la photo */
.card-quick-actions {
    position:absolute; top:10px; right:10px; z-index:5;
    display:flex; gap:5px;
    opacity:0; transition:opacity 0.25s;
}
.client-card:hover .card-quick-actions { opacity:1; }
@media (hover: none) {
    .card-quick-actions { opacity:1; }
}
.card-quick-btn {
    width:30px; height:30px; border-radius:8px; border:none;
    display:flex; align-items:center; justify-content:center;
    font-size:0.75rem; cursor:pointer; transition:transform 0.15s;
    backdrop-filter:blur(6px);
}
.card-quick-btn:hover { transform:scale(1.18); }
.btn-q-edit   { background:rgba(255,255,255,0.9); color:#0d6efd; }
.btn-q-delete { background:rgba(220,53,69,0.85); color:#fff; }
.btn-q-view   { background:rgba(255,255,255,0.9); color:#198754; }

/* Avatar circle sur la card body */
.card-avatar-circle {
    width:54px; height:54px; border-radius:50%;
    object-fit:cover; border:3px solid #111b2f;
    box-shadow:0 4px 12px rgba(0,0,0,0.35);
    margin-top:-27px; position:relative; z-index:3;
    transition:transform 0.3s;
}
.client-card:hover .card-avatar-circle { transform:scale(1.08); }
.card-avatar-circle-init {
    width:54px; height:54px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-size:1.3rem; font-weight:800; color:#fff;
    border:3px solid #111b2f; box-shadow:0 4px 12px rgba(0,0,0,0.35);
    margin-top:-27px; position:relative; z-index:3;
    transition:transform 0.3s;
}
.client-card:hover .card-avatar-circle-init { transform:scale(1.08); }

.card-body-area { padding:0.5rem 1.2rem 1.2rem; }
.client-name    { font-weight:700; font-size:1rem; color:#e2e8f0; margin-bottom:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.client-contact { font-size:0.8rem; color:#94a3b8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.card-stats     { display:flex; gap:8px; margin-top:10px; padding-top:10px; border-top:1px solid #1e293b; }
.stat-pill      { flex:1; text-align:center; background:#0b1426; border-radius:10px; padding:6px 4px; border:1px solid #223253; }
.stat-pill-val  { font-weight:700; font-size:0.85rem; color:#93c5fd; }
.stat-pill-lbl  { font-size:0.65rem; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em; }

/* Empty state */
.empty-state { text-align:center; padding:4rem 2rem; }
.empty-icon  { font-size:4rem; color:#cbd5e1; margin-bottom:1rem; animation:pulse 2s ease-in-out infinite; }
@keyframes pulse { 0%,100%{opacity:0.5} 50%{opacity:1} }

/* Avatar palette colors */
.bg-grad-0 { background:linear-gradient(135deg,#0d6efd,#0a58ca); }
.bg-grad-1 { background:linear-gradient(135deg,#7c3aed,#4f46e5); }
.bg-grad-2 { background:linear-gradient(135deg,#0891b2,#0e7490); }
.bg-grad-3 { background:linear-gradient(135deg,#059669,#047857); }
.bg-grad-4 { background:linear-gradient(135deg,#dc2626,#b91c1c); }
.bg-grad-5 { background:linear-gradient(135deg,#d97706,#b45309); }
.bg-grad-6 { background:linear-gradient(135deg,#db2777,#be185d); }
.bg-grad-7 { background:linear-gradient(135deg,#16a34a,#15803d); }

.total-badge { background:rgba(255,255,255,0.18); border-radius:10px; padding:4px 14px; font-weight:700; font-size:1.1rem; backdrop-filter:blur(8px); }

@media (max-width: 575.98px) {
    .page-hero { padding: 1.4rem 1.2rem; }
    .search-bar-wrap { padding: 1rem; }
    .search-bar-wrap form { flex-direction: column; align-items: stretch !important; }
    .clients-grid { grid-template-columns: 1fr; }
}
</style>

<!-- Hero -->
<div class="page-hero">
    <div class="position-relative" style="z-index:1;">
        <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3">
            <div>
                <h2 class="fw-bold mb-1">Clients</h2>
                <p class="mb-0 text-white-50 small">Gérez votre portefeuille clients</p>
            </div>
            <div class="d-flex align-items-center gap-2 gap-sm-3 flex-wrap">
                <span class="total-badge"><?php echo count($clients); ?> clients</span>
                <a href="add.php" class="btn btn-light fw-bold shadow-sm" style="border-radius:12px;">
                    <i class="fas fa-plus me-2"></i>Nouveau
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (isset($success)): ?>
<div class="alert alert-success border-0 rounded-3 mb-3 d-flex align-items-center gap-2" style="animation:fadeInUp 0.3s ease;">
    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
</div>
<?php endif; ?>
<?php if (isset($error)): ?>
<div class="alert alert-danger border-0 rounded-3 mb-3"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?></div>
<?php endif; ?>

<!-- Search -->
<div class="search-bar-wrap">
    <form method="GET" action="" class="d-flex flex-column flex-sm-row gap-3 align-items-stretch align-items-sm-end">
        <div class="flex-grow-1">
            <label class="form-label small fw-bold text-muted mb-1">Recherche rapide</label>
            <div class="input-group">
                <span class="input-group-text border-end-0" style="border-radius:12px 0 0 12px;"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control border-start-0" name="search" placeholder="Nom ou téléphone du client…" 
                       value="<?php echo htmlspecialchars($search); ?>" style="border-radius:0 12px 12px 0;">
            </div>
        </div>
        <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary fw-bold px-4" style="border-radius:12px;white-space:nowrap;"><i class="fas fa-filter me-2"></i>Filtrer</button>
        <?php if ($search): ?>
        <a href="index.php" class="btn btn-outline-secondary" style="border-radius:12px;" title="Réinitialiser"><i class="fas fa-times"></i></a>
        <?php endif; ?>
        </div>
    </form>
</div>

<!-- Clients Grid -->
<?php if (count($clients) > 0): ?>
<div class="clients-grid">
    <?php foreach ($clients as $i => $c):
        $grad = 'bg-grad-' . ($i % 8);
        $initial = strtoupper(substr($c['name'], 0, 1));
        $total_amount = format_currency($c['total_sales_amount'] ?? 0);
        $has_photo = !empty($c['photo']);
    ?>
    <div class="client-card">
        <!-- Quick action buttons -->
        <div class="card-quick-actions">
            <a href="view.php?id=<?php echo $c['id']; ?>" class="card-quick-btn btn-q-view" title="Voir profil"><i class="fas fa-eye"></i></a>
            <a href="edit.php?id=<?php echo $c['id']; ?>" class="card-quick-btn btn-q-edit" title="Modifier"><i class="fas fa-pen"></i></a>
            <?php echo csrf_delete_form($c['id'], 'Supprimer ce client ?', 'card-quick-btn btn-q-delete'); ?>
        </div>
        <!-- Photo zone (banner) -->
        <div class="card-photo-zone <?php echo $has_photo ? '' : $grad; ?>">
            <?php if ($has_photo): ?>
                <img class="card-photo-img" src="<?php echo app_base_url('uploads/clients/' . rawurlencode($c['photo'])); ?>" alt="<?php echo htmlspecialchars($c['name']); ?>">
            <?php else: ?>
                <div class="card-avatar-init"><?php echo $initial; ?></div>
            <?php endif; ?>
        </div>
        <!-- Body -->
        <div class="card-body-area">
            <div class="d-flex align-items-start gap-2 mb-2" style="margin-top:-27px;">
                <?php if ($has_photo): ?>
                    <img class="card-avatar-circle" src="<?php echo app_base_url('uploads/clients/' . rawurlencode($c['photo'])); ?>" alt="<?php echo htmlspecialchars($c['name']); ?>">
                <?php else: ?>
                    <div class="card-avatar-circle-init <?php echo $grad; ?>"><?php echo $initial; ?></div>
                <?php endif; ?>
            </div>
            <a href="view.php?id=<?php echo $c['id']; ?>" class="text-decoration-none">
                <div class="client-name"><?php echo htmlspecialchars($c['name']); ?></div>
            </a>
            <div class="client-contact">
                <?php if ($c['phone']): ?><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($c['phone']); ?><?php endif; ?>
                <?php if ($c['email']): ?>&nbsp;·&nbsp;<i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($c['email']); ?><?php endif; ?>
            </div>
            <div class="card-stats">
                <div class="stat-pill">
                    <div class="stat-pill-val"><?php echo $c['total_sales_count']; ?></div>
                    <div class="stat-pill-lbl">Achats</div>
                </div>
                <div class="stat-pill">
                    <div class="stat-pill-val" style="font-size:0.72rem;"><?php echo $total_amount; ?></div>
                    <div class="stat-pill-lbl">Dépensé</div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-state">
    <div class="empty-icon"><i class="fas fa-users"></i></div>
    <h5 class="fw-bold text-muted mb-2">Aucun client trouvé</h5>
    <p class="text-muted small mb-3">
        <?php echo $search ? "Aucun résultat pour \"$search\"" : "Commencez par ajouter votre premier client."; ?>
    </p>
    <?php if ($search): ?>
        <a href="index.php" class="btn btn-outline-primary btn-sm rounded-3">Réinitialiser</a>
    <?php else: ?>
        <a href="add.php" class="btn btn-primary rounded-3"><i class="fas fa-plus me-2"></i>Ajouter un client</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
