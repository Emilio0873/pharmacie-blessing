<?php
$page_title = "Profil Client - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Caissier']);
if (!isset($_GET['id'])) redirect('index.php');

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) redirect('index.php');

// Ventes
$stmt_sales = $pdo->prepare("SELECT s.*, u.full_name as user_name FROM sales s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.client_id = ? ORDER BY s.sale_date DESC");
$stmt_sales->execute([$id]);
$sales = $stmt_sales->fetchAll();

$total_spent    = array_sum(array_column($sales, 'final_amount'));
$total_discount = array_sum(array_column($sales, 'discount'));
$avg_sale       = count($sales) > 0 ? $total_spent / count($sales) : 0;

// Statistiques mensuelles (6 mois)
$monthly_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end   = date('Y-m-t',  strtotime("-$i months"));
    $label       = date('M Y',    strtotime("-$i months"));
    $stmt_m = $pdo->prepare("SELECT SUM(final_amount) FROM sales WHERE client_id=? AND DATE(sale_date) BETWEEN ? AND ?");
    $stmt_m->execute([$id, $month_start, $month_end]);
    $monthly_data[] = ['label' => $label, 'total' => $stmt_m->fetchColumn() ?: 0];
}

// Produits les plus achetés
$top_products = $pdo->prepare("SELECT p.name, SUM(sd.qty) as qty_bought, SUM(sd.qty * sd.unit_price) as total_spent
    FROM sale_details sd
    JOIN sales s ON sd.sale_id = s.id
    JOIN products p ON sd.product_id = p.id
    WHERE s.client_id = ?
    GROUP BY p.id ORDER BY qty_bought DESC LIMIT 5");
$top_products->execute([$id]);
$top_prods = $top_products->fetchAll();

// Palette de couleurs
$palettes = [
    ['from'=>'#0d6efd','to'=>'#0a3d8f'],
    ['from'=>'#7c3aed','to'=>'#4f46e5'],
    ['from'=>'#0891b2','to'=>'#0e7490'],
    ['from'=>'#059669','to'=>'#047857'],
    ['from'=>'#dc2626','to'=>'#b91c1c'],
    ['from'=>'#d97706','to'=>'#b45309'],
    ['from'=>'#db2777','to'=>'#be185d'],
];
$pal  = $palettes[$id % count($palettes)];
$init = strtoupper(substr($client['name'], 0, 2));

require_once '../../includes/header.php';
?>

<style>
/* PROFILE PREMIUM */
.profile-cover {
    height: 220px;
    border-radius: 22px 22px 0 0;
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, <?php echo $pal['from']; ?> 0%, <?php echo $pal['to']; ?> 100%);
}
.cover-photo-img { width:100%; height:100%; object-fit:cover; }
.cover-overlay   { position:absolute; inset:0; background:linear-gradient(to bottom, transparent 30%, rgba(0,0,0,0.45) 100%); }
.cover-pattern   {
    position:absolute; inset:0; opacity:0.08;
    background-image: radial-gradient(circle, rgba(255,255,255,0.8) 1px, transparent 1px);
    background-size: 28px 28px;
}
.profile-card-wrap {
    background: linear-gradient(180deg, rgba(17, 27, 47, 0.98) 0%, rgba(11, 18, 32, 0.98) 100%);
    border-radius:0 0 22px 22px;
    box-shadow: 0 12px 40px rgba(2,6,23,0.35);
    margin-bottom: 1.5rem;
    border: 1px solid #223253;
    border-top: none;
}
.profile-avatar {
    width: 100px; height: 100px;
    border-radius: 50%; border: 5px solid #111b2f;
    box-shadow: 0 8px 24px rgba(0,0,0,0.35);
    object-fit: cover;
    margin-top: -50px; position: relative; z-index: 2;
    transition: transform 0.35s cubic-bezier(.4,0,.2,1), box-shadow 0.35s;
}
.profile-avatar:hover { transform: scale(1.07); box-shadow: 0 14px 35px rgba(0,0,0,0.45); }
.profile-avatar-init {
    width: 100px; height: 100px;
    border-radius: 50%; border: 5px solid #111b2f;
    box-shadow: 0 8px 24px rgba(0,0,0,0.35);
    background: linear-gradient(135deg, <?php echo $pal['from']; ?>, <?php echo $pal['to']; ?>);
    display: flex; align-items: center; justify-content: center;
    font-size: 2.2rem; font-weight: 800; color: #fff;
    margin-top: -50px; position: relative; z-index: 2;
    transition: transform 0.35s;
}
.profile-avatar-init:hover { transform: scale(1.07); }
.profile-info-zone { padding: 0.5rem 2rem 1.5rem; }
.profile-name-badge { display:inline-flex; align-items:center; gap:8px; }
.profile-cover-name {
    position: absolute;
    bottom: 18px;
    left: 130px;
    right: 16px;
    z-index: 3;
}
.client-since-badge {
    background: linear-gradient(135deg, <?php echo $pal['from']; ?>, <?php echo $pal['to']; ?>);
    font-size: 0.75rem; color:#fff; padding: 3px 12px; border-radius: 20px; font-weight: 600;
}

/* KPI Cards */
.kpi-row     { display: flex; gap: 1rem; padding: 1rem 2rem 0; flex-wrap: wrap; }
.kpi-card    {
    flex: 1; min-width: 130px;
    background: #0b1426; border-radius: 16px; padding: 1rem 1.2rem;
    border: 1px solid #223253;
    transition: all 0.3s; animation: fadeUp 0.5s ease both;
}
.kpi-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(29,78,216,0.18); }
@keyframes fadeUp {
    from { opacity:0; transform:translateY(14px); }
    to   { opacity:1; transform:translateY(0); }
}
.kpi-card:nth-child(1) { animation-delay:0.1s }
.kpi-card:nth-child(2) { animation-delay:0.18s }
.kpi-card:nth-child(3) { animation-delay:0.26s }
.kpi-card:nth-child(4) { animation-delay:0.34s }
.kpi-icon    { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:8px; }
.kpi-val     { font-size:1.3rem; font-weight:800; color:#e2e8f0; line-height:1; }
.kpi-label   { font-size:0.7rem; text-transform:uppercase; letter-spacing:0.06em; color:#94a3b8; font-weight:600; margin-top:2px; }

/* Content Cards */
.content-card {
    background: linear-gradient(180deg, rgba(17, 27, 47, 0.98) 0%, rgba(11, 18, 32, 0.98) 100%);
    border-radius:18px;
    box-shadow: 0 4px 22px rgba(2,6,23,0.3);
    border: 1px solid #223253;
    overflow:hidden; animation: fadeUp 0.45s ease both;
}
.cc-header {
    padding: 1.2rem 1.5rem; border-bottom: 1px solid #1e293b;
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.5rem;
}
.cc-title { font-weight:700; font-size:0.95rem; color:#e2e8f0; }
.cc-body  { padding:1.3rem 1.5rem; }

/* Timeline for purchases */
.purchase-row { border-radius:12px; transition:background 0.2s; padding:10px 12px; margin-bottom:4px; }
.purchase-row:hover { background:#0b1426; }
.sale-num { font-weight:700; color:<?php echo $pal['from']; ?>; font-size:0.9rem; }
.sale-amt { font-weight:700; color:#34d399; }

/* Progress bar products */
.prod-bar { height:5px; border-radius:100px; background:#1e293b; overflow:hidden; margin-top:3px; }
.prod-bar-fill { height:100%; border-radius:100px; background:linear-gradient(to right, <?php echo $pal['from']; ?>, <?php echo $pal['to']; ?>); transition:width 1s cubic-bezier(.4,0,.2,1); }

/* Chart section */
.chart-wrap { position:relative; }

@media (max-width: 767.98px) {
    .profile-cover { height: 160px; border-radius: 16px 16px 0 0; }
    .profile-cover-name {
        left: 16px;
        bottom: 14px;
    }
    .profile-cover-name h3 { font-size: 1.15rem; }
    .profile-avatar,
    .profile-avatar-init {
        width: 78px; height: 78px; margin-top: -40px;
        font-size: 1.6rem;
    }
    .profile-info-zone,
    .kpi-row { padding-left: 1rem; padding-right: 1rem; }
    .kpi-card { min-width: calc(50% - 0.5rem); }
}
@media (max-width: 575.98px) {
    .kpi-card { min-width: 100%; }
}
</style>

<!-- Breadcrumb + Actions -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Clients</a></li>
            <li class="breadcrumb-item active fw-600"><?php echo htmlspecialchars($client['name']); ?></li>
        </ol>
    </nav>
    <div class="d-flex gap-2 flex-wrap">
        <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-primary btn-sm fw-bold px-3" style="border-radius:10px;">
            <i class="fas fa-pen me-1"></i> Modifier
        </a>
        <a href="index.php" class="btn btn-light border btn-sm px-3" style="border-radius:10px;">
            <i class="fas fa-arrow-left me-1"></i> Retour
        </a>
    </div>
</div>

<!-- CARTE PROFIL -->
<div class="profile-card-wrap mb-4">
    <!-- Couverture -->
    <div class="profile-cover">
        <?php if (!empty($client['photo'])): ?>
            <img class="cover-photo-img" src="<?php echo app_base_url('uploads/clients/' . rawurlencode($client['photo'])); ?>" alt="Cover">
        <?php else: ?>
            <div class="cover-pattern"></div>
        <?php endif; ?>
        <div class="cover-overlay"></div>
        <!-- Nom affiché sur la couverture -->
        <div class="profile-cover-name">
            <h3 class="text-white fw-bold mb-0" style="text-shadow:0 2px 8px rgba(0,0,0,0.3);"><?php echo htmlspecialchars($client['name']); ?></h3>
            <div class="text-white-75 small" style="text-shadow:0 1px 4px rgba(0,0,0,0.3);">
                Client depuis le <?php echo date('d/m/Y', strtotime($client['created_at'])); ?>
            </div>
        </div>
    </div>

    <!-- Avatar + infos -->
    <div style="padding: 0 1.5rem;">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-2">
            <div>
                <?php if (!empty($client['photo'])): ?>
                    <img class="profile-avatar" src="<?php echo app_base_url('uploads/clients/' . rawurlencode($client['photo'])); ?>" alt="Avatar">
                <?php else: ?>
                    <div class="profile-avatar-init"><?php echo $init; ?></div>
                <?php endif; ?>
            </div>
            <div class="pb-3 d-flex gap-2 flex-wrap">
                <?php if ($client['phone']): ?>
                <a href="tel:<?php echo htmlspecialchars($client['phone']); ?>" class="btn btn-sm btn-light border fw-600 px-3" style="border-radius:10px;">
                    <i class="fas fa-phone me-1 text-success"></i><?php echo htmlspecialchars($client['phone']); ?>
                </a>
                <?php endif; ?>
                <?php if ($client['email']): ?>
                <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>" class="btn btn-sm btn-light border fw-600 px-3" style="border-radius:10px;">
                    <i class="fas fa-envelope me-1 text-primary"></i><?php echo htmlspecialchars($client['email']); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-info-zone pt-2">
            <div class="profile-name-badge">
                <h4 class="fw-bold mb-0"><?php echo htmlspecialchars($client['name']); ?></h4>
                <span class="client-since-badge">
                    <i class="fas fa-star me-1"></i>Client fidèle
                </span>
            </div>
            <?php if ($client['address']): ?>
            <div class="text-muted small mt-1"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($client['address']); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ligne KPI -->
    <div class="kpi-row pb-3">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:rgba(13,110,253,0.1);"><i class="fas fa-receipt" style="color:#0d6efd;"></i></div>
            <div class="kpi-val"><?php echo count($sales); ?></div>
            <div class="kpi-label">Total Achats</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:rgba(5,150,105,0.1);"><i class="fas fa-coins" style="color:#059669;"></i></div>
            <div class="kpi-val" style="font-size:1rem;"><?php echo format_currency($total_spent); ?></div>
            <div class="kpi-label">Total Dépensé</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:rgba(124,58,237,0.1);"><i class="fas fa-chart-line" style="color:#7c3aed;"></i></div>
            <div class="kpi-val" style="font-size:1rem;"><?php echo format_currency($avg_sale); ?></div>
            <div class="kpi-label">Panier Moyen</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background:rgba(217,119,6,0.1);"><i class="fas fa-tag" style="color:#d97706;"></i></div>
            <div class="kpi-val" style="font-size:1rem;"><?php echo format_currency($total_discount); ?></div>
            <div class="kpi-label">Remises Reçues</div>
        </div>
    </div>
</div>

<!-- LIGNE BASSE : Graphique + Top Produits + Historique -->
<div class="row g-4">

    <!-- Chart -->
    <div class="col-lg-8">
        <div class="content-card" style="animation-delay:0.1s;">
            <div class="cc-header">
                <h6 class="cc-title"><i class="fas fa-chart-bar me-2" style="color:<?php echo $pal['from']; ?>"></i>Évolution des achats — 6 derniers mois</h6>
            </div>
            <div class="cc-body chart-wrap">
                <canvas id="clientChart" height="100"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Produits -->
    <div class="col-lg-4">
        <div class="content-card" style="animation-delay:0.2s;">
            <div class="cc-header">
                <h6 class="cc-title"><i class="fas fa-star me-2" style="color:<?php echo $pal['from']; ?>"></i>Top produits achetés</h6>
            </div>
            <div class="cc-body">
                <?php if (count($top_prods) > 0):
                    $max_qty = $top_prods[0]['qty_bought'];
                    foreach ($top_prods as $p):
                        $pct = $max_qty > 0 ? round($p['qty_bought'] / $max_qty * 100) : 0;
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-600 small text-truncate" style="max-width:60%"><?php echo htmlspecialchars($p['name']); ?></span>
                        <span class="text-muted small"><?php echo $p['qty_bought']; ?> unités</span>
                    </div>
                    <div class="prod-bar">
                        <div class="prod-bar-fill" style="width:0%" data-width="<?php echo $pct; ?>%"></div>
                    </div>
                </div>
                <?php endforeach; else: ?>
                <p class="text-muted text-center py-3 small">Aucune donnée de produit.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Historique des ventes -->
    <div class="col-12">
        <div class="content-card" style="animation-delay:0.3s;">
            <div class="cc-header">
                <h6 class="cc-title"><i class="fas fa-history me-2" style="color:<?php echo $pal['from']; ?>"></i>Historique des achats</h6>
                <span class="badge rounded-pill" style="background:<?php echo $pal['from']; ?>"><?php echo count($sales); ?> ventes</span>
            </div>
            <div class="cc-body p-0">
                <?php if (count($sales) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">N° Vente</th>
                                <th>Date</th>
                                <th>Montant</th>
                                <th>Remise</th>
                                <th>Caissier</th>
                                <th class="text-center pe-4">Reçu</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($sales as $s): ?>
                        <tr class="purchase-row">
                            <td class="ps-4"><span class="sale-num">#<?php echo str_pad($s['id'], 5, '0', STR_PAD_LEFT); ?></span></td>
                            <td><small><?php echo date('d/m/Y H:i', strtotime($s['sale_date'])); ?></small></td>
                            <td class="sale-amt"><?php echo format_currency($s['total_amount']); ?></td>
                            <td class="text-danger small"><?php echo $s['discount'] > 0 ? '-'.format_currency($s['discount']) : '—'; ?></td>
                            <td><small class="text-muted"><i class="fas fa-user-tie me-1"></i><?php echo htmlspecialchars($s['user_name'] ?? 'N/D'); ?></small></td>
                            <td class="text-center pe-4">
                                <a href="../sales/receipt.php?id=<?php echo $s['id']; ?>" target="_blank" 
                                   class="btn btn-sm btn-light border" style="border-radius:8px;" title="Imprimer reçu">
                                    <i class="fas fa-print text-muted small"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-receipt fa-2x mb-2 d-block opacity-25"></i>
                    Aucune vente enregistrée pour ce client.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Graphique
    var ctx = document.getElementById('clientChart').getContext('2d');
    var gradFill = ctx.createLinearGradient(0, 0, 0, 300);
    gradFill.addColorStop(0, 'rgba(13,110,253,0.35)');
    gradFill.addColorStop(1, 'rgba(13,110,253,0.02)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($monthly_data, 'label')); ?>,
            datasets: [{
                label: 'Achats (FC)',
                data: <?php echo json_encode(array_column($monthly_data, 'total')); ?>,
                backgroundColor: gradFill,
                borderColor: '<?php echo $pal['from']; ?>',
                borderWidth: 3,
                tension: 0.45,
                fill: true,
                pointBackgroundColor: '#fff',
                pointBorderColor: '<?php echo $pal['from']; ?>',
                pointBorderWidth: 3,
                pointRadius: 5,
                pointHoverRadius: 8,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    cornerRadius: 12,
                    callbacks: {
                        label: ctx => ' ' + new Intl.NumberFormat('fr-FR').format(ctx.parsed.y) + ' FC'
                    }
                }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f0f4ff' }, ticks: { color: '#94a3b8' } },
                x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
            },
            animation: { duration: 1200, easing: 'easeInOutQuart' }
        }
    });

    // Animation des barres de progression
    setTimeout(() => {
        document.querySelectorAll('.prod-bar-fill').forEach(bar => {
            bar.style.width = bar.dataset.width;
        });
    }, 300);
});
</script>

<?php require_once '../../includes/footer.php'; ?>
