<?php
$page_title = "Rapports & Statistiques - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Magasinier']);

$business = get_business_profile($pdo);

$allowed_tabs = ['overview', 'products', 'clients', 'purchases', 'stock'];
$active_tab = isset($_GET['tab']) && in_array($_GET['tab'], $allowed_tabs, true) ? $_GET['tab'] : 'overview';

$date_from_raw = $_GET['date_from'] ?? date('Y-m-01');
$date_to_raw   = $_GET['date_to'] ?? date('Y-m-d');
$date_from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from_raw) ? $date_from_raw : date('Y-m-01');
$date_to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to_raw) ? $date_to_raw : date('Y-m-d');
if (strtotime($date_from) > strtotime($date_to)) {
    $tmp = $date_from;
    $date_from = $date_to;
    $date_to = $tmp;
}

// ──────────────────────────────────────────────────
// GLOBAL KPIs
// ──────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT
    COALESCE(SUM(total_amount), 0) as ca,
    COALESCE(SUM(discount), 0) as remises,
    COUNT(*) as nb_ventes,
    COALESCE(SUM(final_amount), 0) as net
    FROM sales WHERE DATE(sale_date) BETWEEN ? AND ?");
$stmt->execute([$date_from, $date_to]);
$kpi_sales = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total, COUNT(*) as nb
    FROM purchases WHERE DATE(purchase_date) BETWEEN ? AND ?");
$stmt->execute([$date_from, $date_to]);
$kpi_purchases = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COALESCE(SUM((sd.unit_price - p.buy_price) * sd.qty), 0) as profit
    FROM sale_details sd
    JOIN sales s ON sd.sale_id = s.id
    JOIN products p ON sd.product_id = p.id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?");
$stmt->execute([$date_from, $date_to]);
$profit_raw = (float)($stmt->fetchColumn() ?: 0);
$estimated_profit = $profit_raw - (float)($kpi_sales['remises'] ?? 0);

// ──────────────────────────────────────────────────
// CHART DATA
// ──────────────────────────────────────────────────
$days_diff = (strtotime($date_to) - strtotime($date_from)) / 86400;
$chart_labels = $chart_sales_day = $chart_pur_day = [];

if ($days_diff <= 31) {
    $cursor = strtotime($date_from);
    $end    = strtotime($date_to);
    while ($cursor <= $end) {
        $d = date('Y-m-d', $cursor);
        $chart_labels[] = date('d/m', $cursor);
        $s = $pdo->prepare("SELECT COALESCE(SUM(final_amount), 0) FROM sales WHERE DATE(sale_date) = ?");
        $s->execute([$d]);
        $chart_sales_day[] = (float)$s->fetchColumn();
        $p = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM purchases WHERE DATE(purchase_date) = ?");
        $p->execute([$d]);
        $chart_pur_day[] = (float)$p->fetchColumn();
        $cursor = strtotime('+1 day', $cursor);
    }
} else {
    $cursor = strtotime(date('Y-m-01', strtotime($date_from)));
    $end    = strtotime(date('Y-m-01', strtotime($date_to)));
    while ($cursor <= $end) {
        $ms = date('Y-m-01', $cursor);
        $me = date('Y-m-t', $cursor);
        $chart_labels[] = date('M Y', $cursor);
        $s = $pdo->prepare("SELECT COALESCE(SUM(final_amount), 0) FROM sales WHERE DATE(sale_date) BETWEEN ? AND ?");
        $s->execute([$ms, $me]);
        $chart_sales_day[] = (float)$s->fetchColumn();
        $p = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM purchases WHERE DATE(purchase_date) BETWEEN ? AND ?");
        $p->execute([$ms, $me]);
        $chart_pur_day[] = (float)$p->fetchColumn();
        $cursor = strtotime('+1 month', $cursor);
    }
}

// ──────────────────────────────────────────────────
// TOP PRODUCTS / CLIENTS / STOCK / PURCHASES
// ──────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT p.id, p.name,
    COALESCE(SUM(sd.qty), 0) as qty_sold,
    COALESCE(SUM(sd.qty * sd.unit_price), 0) as revenue,
    COALESCE(SUM(sd.qty * p.buy_price), 0) as cost
    FROM sale_details sd
    JOIN sales s ON sd.sale_id = s.id
    JOIN products p ON sd.product_id = p.id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
    GROUP BY p.id, p.name
    ORDER BY qty_sold DESC
    LIMIT 10");
$stmt->execute([$date_from, $date_to]);
$top_products = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT c.id, c.name, COUNT(s.id) as nb, COALESCE(SUM(s.final_amount), 0) as spent
    FROM clients c
    JOIN sales s ON c.id = s.client_id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
    GROUP BY c.id, c.name
    ORDER BY spent DESC
    LIMIT 10");
$stmt->execute([$date_from, $date_to]);
$top_clients = $stmt->fetchAll();

$out_of_stock = $pdo->query("SELECT p.*, c.name as cat_name FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.qty <= p.alert_threshold
    ORDER BY p.qty ASC, p.name ASC")->fetchAll();

$stmt = $pdo->prepare("SELECT pu.*, s.name as supplier_name FROM purchases pu
    LEFT JOIN suppliers s ON pu.supplier_id = s.id
    WHERE DATE(pu.purchase_date) BETWEEN ? AND ?
    ORDER BY pu.purchase_date DESC
    LIMIT 50");
$stmt->execute([$date_from, $date_to]);
$purchases_list = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT cat.id, cat.name as cat_name, COALESCE(SUM(sd.qty * sd.unit_price), 0) as revenue
    FROM sale_details sd
    JOIN products p ON sd.product_id = p.id
    JOIN categories cat ON p.category_id = cat.id
    JOIN sales s ON sd.sale_id = s.id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
    GROUP BY cat.id, cat.name
    ORDER BY revenue DESC");
$stmt->execute([$date_from, $date_to]);
$by_category = $stmt->fetchAll();

$tab_query = 'tab=' . urlencode($active_tab);
$period_label = date('d/m/Y', strtotime($date_from)) . ' — ' . date('d/m/Y', strtotime($date_to));

require_once '../../includes/header.php';
?>

<style>
.report-hero {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 1.25rem;
}
.report-filter {
    border: 1px solid #223253 !important;
    background: linear-gradient(180deg, rgba(17,27,47,.98), rgba(11,18,32,.98)) !important;
}
.report-filter .quick-range .btn.active {
    background: #1d4ed8 !important;
    border-color: #1d4ed8 !important;
    color: #fff !important;
}
.report-kpi {
    border: 1px solid #223253 !important;
    border-left-width: 4px !important;
    background: linear-gradient(180deg, rgba(17,27,47,.98), rgba(11,18,32,.98)) !important;
}
.report-tabs {
    border-bottom: 1px solid #223253;
    gap: .35rem;
    flex-wrap: nowrap;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.report-tabs .nav-link {
    color: #94a3b8;
    border: 1px solid transparent;
    border-radius: 10px 10px 0 0;
    white-space: nowrap;
    background: transparent;
}
.report-tabs .nav-link:hover {
    color: #e2e8f0;
    background: rgba(29,78,216,.12);
}
.report-tabs .nav-link.active {
    color: #fff !important;
    background: linear-gradient(135deg, #1d4ed8, #1e40af) !important;
    border-color: rgba(147,197,253,.35) !important;
}
.report-print-sheet {
    display: none;
}
.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    height: 2rem;
    border-radius: 8px;
    font-weight: 800;
    font-size: .85rem;
}
.rank-1 { background: rgba(245,158,11,.2); color: #fbbf24; }
.rank-2 { background: rgba(148,163,184,.2); color: #cbd5e1; }
.rank-3 { background: rgba(249,115,22,.2); color: #fb923c; }
.rank-n { background: rgba(30,41,59,.8); color: #94a3b8; }

@media print {
    body {
        background: #fff !important;
        color: #0f172a !important;
    }
    .wrapper #sidebar,
    .navbar,
    .sidebar-overlay,
    .d-print-none {
        display: none !important;
    }
    #content, .container-fluid {
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
    }
    .report-print-sheet {
        display: block !important;
        margin-bottom: 18px;
        padding-bottom: 12px;
        border-bottom: 2px solid #1d4ed8;
    }
    .report-print-sheet .brand {
        font-size: 1.35rem;
        font-weight: 800;
        color: #1e3a8a;
        margin: 0;
    }
    .report-print-sheet .meta {
        color: #475569;
        font-size: .9rem;
    }
    .card, .report-kpi {
        background: #fff !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: none !important;
        color: #0f172a !important;
        break-inside: avoid;
    }
    .card-header, .text-muted, .text-primary, .text-success, .text-warning, .text-danger, .text-info {
        color: #0f172a !important;
    }
    .table {
        color: #0f172a !important;
    }
    .table thead th {
        background: #f1f5f9 !important;
        color: #334155 !important;
        border-bottom: 1px solid #cbd5e1 !important;
    }
    .table tbody td {
        color: #0f172a !important;
        border-bottom: 1px solid #e2e8f0 !important;
        background: #fff !important;
    }
    .badge {
        border: 1px solid #cbd5e1 !important;
        color: #0f172a !important;
        background: #f8fafc !important;
    }
    a { color: #0f172a !important; text-decoration: none !important; }
    canvas { max-width: 100% !important; }
}
</style>

<div class="report-hero">
    <div>
        <h3 class="fw-bold mb-1"><i class="fas fa-chart-line text-primary me-2"></i>Rapports &amp; Statistiques</h3>
        <p class="text-muted mb-0">Analyse de la période du <strong><?php echo htmlspecialchars(date('d/m/Y', strtotime($date_from))); ?></strong> au <strong><?php echo htmlspecialchars(date('d/m/Y', strtotime($date_to))); ?></strong></p>
    </div>
    <div class="d-flex flex-wrap gap-2 d-print-none">
        <a href="full_report.php?date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" class="btn btn-primary shadow-sm fw-bold">
            <i class="fas fa-file-alt me-2"></i>Rapport complet
        </a>
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary shadow-sm">
            <i class="fas fa-print me-2"></i>Imprimer
        </button>
    </div>
</div>

<!-- Print-only professional header -->
<div class="report-print-sheet">
    <div class="d-flex justify-content-between align-items-start gap-3">
        <div>
            <p class="brand mb-1"><?php echo htmlspecialchars($business['name']); ?></p>
            <div class="meta">
                <?php echo htmlspecialchars($business['address']); ?><br>
                Tél: <?php echo htmlspecialchars($business['phone']); ?>
                <?php if (!empty($business['email'])): ?> | <?php echo htmlspecialchars($business['email']); ?><?php endif; ?>
            </div>
        </div>
        <div class="text-end meta">
            <strong>Rapport d'activité</strong><br>
            Période : <?php echo htmlspecialchars($period_label); ?><br>
            Édité le <?php echo date('d/m/Y H:i'); ?><br>
            Par <?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>
        </div>
    </div>
</div>

<!-- Filter form -->
<div class="card report-filter shadow-sm border-0 mb-4 overflow-hidden d-print-none">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($active_tab); ?>">
            <div class="col-12 col-sm-6 col-lg-2">
                <label class="form-label small fw-bold text-muted" for="date_from">Du</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" required>
            </div>
            <div class="col-12 col-sm-6 col-lg-2">
                <label class="form-label small fw-bold text-muted" for="date_to">Au</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" required>
            </div>
            <div class="col-12 col-sm-6 col-lg-2">
                <button type="submit" class="btn btn-primary w-100 fw-bold">
                    <i class="fas fa-filter me-1"></i> Actualiser
                </button>
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label small fw-bold text-muted d-none d-lg-block">&nbsp;</label>
                <div class="btn-group quick-range flex-wrap w-100" role="group" aria-label="Périodes rapides">
                    <?php
                    $today = date('Y-m-d');
                    $month_start = date('Y-m-01');
                    $year_start = date('Y-01-01');
                    $year_end = date('Y-12-31');
                    $is_today = ($date_from === $today && $date_to === $today);
                    $is_month = ($date_from === $month_start && $date_to === $today);
                    $is_year  = ($date_from === $year_start && $date_to === $year_end);
                    ?>
                    <a href="?<?php echo $tab_query; ?>&date_from=<?php echo $today; ?>&date_to=<?php echo $today; ?>" class="btn btn-outline-secondary btn-sm <?php echo $is_today ? 'active' : ''; ?>">Aujourd'hui</a>
                    <a href="?<?php echo $tab_query; ?>&date_from=<?php echo $month_start; ?>&date_to=<?php echo $today; ?>" class="btn btn-outline-secondary btn-sm <?php echo $is_month ? 'active' : ''; ?>">Ce mois</a>
                    <a href="?<?php echo $tab_query; ?>&date_from=<?php echo $year_start; ?>&date_to=<?php echo $year_end; ?>" class="btn btn-outline-secondary btn-sm <?php echo $is_year ? 'active' : ''; ?>">Cette année</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- KPI -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card report-kpi shadow-sm p-3 h-100" style="border-left-color:#3b82f6 !important;">
            <small class="text-muted fw-bold text-uppercase">Chiffre d'affaires</small>
            <h3 class="fw-bold mb-0 text-primary"><?php echo format_currency($kpi_sales['net'] ?? 0); ?></h3>
            <small class="text-muted"><?php echo (int)($kpi_sales['nb_ventes'] ?? 0); ?> vente(s) · HT <?php echo format_currency($kpi_sales['ca'] ?? 0); ?></small>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card report-kpi shadow-sm p-3 h-100" style="border-left-color:#22c55e !important;">
            <small class="text-muted fw-bold text-uppercase">Bénéfice estimé</small>
            <h3 class="fw-bold mb-0 text-success"><?php echo format_currency(max(0, $estimated_profit)); ?></h3>
            <small class="text-muted">Marge brute après remises</small>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card report-kpi shadow-sm p-3 h-100" style="border-left-color:#f59e0b !important;">
            <small class="text-muted fw-bold text-uppercase">Total achats</small>
            <h3 class="fw-bold mb-0 text-warning"><?php echo format_currency($kpi_purchases['total'] ?? 0); ?></h3>
            <small class="text-muted"><?php echo (int)($kpi_purchases['nb'] ?? 0); ?> commande(s)</small>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card report-kpi shadow-sm p-3 h-100" style="border-left-color:#ef4444 !important;">
            <small class="text-muted fw-bold text-uppercase">Remises accordées</small>
            <h3 class="fw-bold mb-0 text-danger"><?php echo format_currency($kpi_sales['remises'] ?? 0); ?></h3>
            <small class="text-muted">Rabais sur ventes</small>
        </div>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs report-tabs mb-4 d-print-none" id="reportTabs">
    <?php
    $tabs = [
        'overview'  => ['icon' => 'fa-chart-area', 'label' => 'Vue générale'],
        'products'  => ['icon' => 'fa-pills', 'label' => 'Top produits'],
        'clients'   => ['icon' => 'fa-users', 'label' => 'Top clients'],
        'purchases' => ['icon' => 'fa-truck', 'label' => 'Achats'],
        'stock'     => ['icon' => 'fa-exclamation-triangle', 'label' => 'Alertes stock'],
    ];
    foreach ($tabs as $key => $tab): ?>
    <li class="nav-item">
        <a class="nav-link fw-bold <?php echo $active_tab === $key ? 'active' : ''; ?>"
           href="?tab=<?php echo urlencode($key); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
            <i class="fas <?php echo $tab['icon']; ?> me-1"></i><?php echo $tab['label']; ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<?php if ($active_tab === 'overview'): ?>

<div class="row g-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-chart-area text-primary me-2"></i>Évolution ventes &amp; achats</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <canvas id="mainChart" height="80"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-chart-pie text-warning me-2"></i>CA par catégorie</h5>
            </div>
            <div class="card-body">
                <?php if (count($by_category) > 0): ?>
                <canvas id="categoryChart" height="200"></canvas>
                <?php else: ?>
                <p class="text-center text-muted py-4 mb-0">Aucune donnée pour cette période.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header border-0 pt-4 px-4">
                <h5 class="fw-bold mb-0"><i class="fas fa-fire text-danger me-2"></i>Top 5 produits</h5>
            </div>
            <div class="card-body px-4">
                <?php
                $top5 = array_slice($top_products, 0, 5);
                $maxRev = count($top5) > 0 ? max(array_map('floatval', array_column($top5, 'revenue'))) : 1;
                $colors = ['primary', 'success', 'warning', 'info', 'danger'];
                foreach ($top5 as $i => $p):
                    $pct = $maxRev > 0 ? ((float)$p['revenue'] / $maxRev * 100) : 0;
                    $col = $colors[$i % count($colors)];
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1 gap-2">
                        <span class="fw-bold text-truncate"><?php echo htmlspecialchars($p['name']); ?></span>
                        <span class="text-muted small text-nowrap"><?php echo format_currency($p['revenue']); ?></span>
                    </div>
                    <div class="progress" style="height:8px;background:#0b1426;">
                        <div class="progress-bar bg-<?php echo $col; ?>" style="width:<?php echo min(100, $pct); ?>%;border-radius:4px"></div>
                    </div>
                    <small class="text-muted"><?php echo (int)$p['qty_sold']; ?> unité(s) vendue(s)</small>
                </div>
                <?php endforeach; ?>
                <?php if (count($top5) === 0): ?><p class="text-center text-muted py-4 mb-0">Aucune donnée.</p><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php elseif ($active_tab === 'products'): ?>

<div class="card shadow-sm border-0">
    <div class="card-header border-0 pt-4 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="fw-bold mb-0"><i class="fas fa-pills text-primary me-2"></i>Produits les plus vendus</h5>
        <span class="badge bg-primary rounded-pill"><?php echo count($top_products); ?> produit(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">#</th>
                        <th>Produit</th>
                        <th class="text-center">Qté vendue</th>
                        <th class="text-end">CA généré</th>
                        <th class="text-end">Coût d'achat</th>
                        <th class="text-end">Marge</th>
                        <th class="text-end pe-4">% Marge</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($top_products) > 0): ?>
                        <?php foreach ($top_products as $i => $p):
                            $margin = (float)$p['revenue'] - (float)$p['cost'];
                            $margin_pct = (float)$p['revenue'] > 0 ? ($margin / (float)$p['revenue'] * 100) : 0;
                            $rankClass = $i === 0 ? 'rank-1' : ($i === 1 ? 'rank-2' : ($i === 2 ? 'rank-3' : 'rank-n'));
                        ?>
                        <tr>
                            <td class="ps-4"><span class="rank-badge <?php echo $rankClass; ?>"><?php echo $i + 1; ?></span></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($p['name']); ?></td>
                            <td class="text-center"><span class="badge bg-success rounded-pill"><?php echo (int)$p['qty_sold']; ?></span></td>
                            <td class="text-end fw-bold text-primary"><?php echo format_currency($p['revenue']); ?></td>
                            <td class="text-end text-muted"><?php echo format_currency($p['cost']); ?></td>
                            <td class="text-end fw-bold <?php echo $margin >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo format_currency($margin); ?></td>
                            <td class="text-end pe-4">
                                <span class="badge bg-<?php echo $margin_pct >= 20 ? 'success' : ($margin_pct >= 10 ? 'warning text-dark' : 'danger'); ?>">
                                    <?php echo number_format($margin_pct, 1, ',', ' '); ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">Aucune vente sur cette période.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($active_tab === 'clients'): ?>

<div class="card shadow-sm border-0">
    <div class="card-header border-0 pt-4 px-4">
        <h5 class="fw-bold mb-0"><i class="fas fa-trophy text-warning me-2"></i>Meilleurs clients</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Rang</th>
                        <th>Client</th>
                        <th class="text-center">Nb achats</th>
                        <th class="text-end">Total dépensé</th>
                        <th class="text-end pe-4">Panier moyen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($top_clients) > 0): ?>
                        <?php foreach ($top_clients as $i => $c):
                            $avg = (int)$c['nb'] > 0 ? ((float)$c['spent'] / (int)$c['nb']) : 0;
                            $rankClass = $i === 0 ? 'rank-1' : ($i === 1 ? 'rank-2' : ($i === 2 ? 'rank-3' : 'rank-n'));
                        ?>
                        <tr>
                            <td class="ps-4"><span class="rank-badge <?php echo $rankClass; ?>"><?php echo $i + 1; ?></span></td>
                            <td>
                                <a href="../clients/view.php?id=<?php echo (int)$c['id']; ?>" class="fw-bold text-decoration-none text-primary">
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </a>
                            </td>
                            <td class="text-center"><span class="badge bg-info rounded-pill"><?php echo (int)$c['nb']; ?></span></td>
                            <td class="text-end fw-bold text-success"><?php echo format_currency($c['spent']); ?></td>
                            <td class="text-end pe-4 text-muted"><?php echo format_currency($avg); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">Aucun client trouvé pour cette période.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($active_tab === 'purchases'): ?>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card report-kpi shadow-sm p-3" style="border-left-color:#f59e0b !important;">
            <small class="text-muted fw-bold text-uppercase">Total achats (dépenses)</small>
            <h2 class="fw-bold mb-0 text-warning"><?php echo format_currency($kpi_purchases['total'] ?? 0); ?></h2>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card report-kpi shadow-sm p-3" style="border-left-color:#3b82f6 !important;">
            <small class="text-muted fw-bold text-uppercase">CA ventes (même période)</small>
            <h2 class="fw-bold mb-0 text-primary"><?php echo format_currency($kpi_sales['net'] ?? 0); ?></h2>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header border-0 pt-4 px-4">
        <h5 class="fw-bold mb-0"><i class="fas fa-truck text-warning me-2"></i>Historique des achats</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">N° bon</th>
                        <th>Date</th>
                        <th>Fournisseur</th>
                        <th class="text-end pe-4">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($purchases_list) > 0): ?>
                        <?php foreach ($purchases_list as $pu): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-warning">#<?php echo str_pad((int)$pu['id'], 5, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($pu['purchase_date'])); ?></td>
                            <td><?php echo htmlspecialchars($pu['supplier_name'] ?? 'N/A'); ?></td>
                            <td class="text-end pe-4 fw-bold"><?php echo format_currency($pu['total_amount']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">Aucun achat sur cette période.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($active_tab === 'stock'): ?>

<?php
$rupture = 0;
$faible = 0;
foreach ($out_of_stock as $item) {
    if ((int)$item['qty'] <= 0) $rupture++;
    else $faible++;
}
?>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card report-kpi shadow-sm p-3" style="border-left-color:#ef4444 !important;">
            <small class="text-muted fw-bold text-uppercase">En rupture totale</small>
            <h2 class="fw-bold mb-0 text-danger"><?php echo $rupture; ?> produit(s)</h2>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card report-kpi shadow-sm p-3" style="border-left-color:#f59e0b !important;">
            <small class="text-muted fw-bold text-uppercase">Stock faible (sous seuil)</small>
            <h2 class="fw-bold mb-0 text-warning"><?php echo $faible; ?> produit(s)</h2>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header border-0 pt-4 px-4">
        <h5 class="fw-bold mb-0 text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Rapport d'alertes stock</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Produit</th>
                        <th>Catégorie</th>
                        <th>Lot</th>
                        <th class="text-center">Seuil</th>
                        <th class="text-center">Stock</th>
                        <th>Statut</th>
                        <th class="text-end pe-4 d-print-none">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($out_of_stock) > 0): ?>
                        <?php foreach ($out_of_stock as $item): ?>
                        <tr>
                            <td class="ps-4 fw-bold"><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><small class="text-muted"><?php echo htmlspecialchars($item['cat_name'] ?? 'N/A'); ?></small></td>
                            <td>
                                <?php if (!empty($item['lot_number'])): ?>
                                    <span class="badge bg-light text-primary border"><?php echo htmlspecialchars($item['lot_number']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?php echo (int)$item['alert_threshold']; ?></td>
                            <td class="text-center fw-bold <?php echo (int)$item['qty'] <= 0 ? 'text-danger' : 'text-warning'; ?>"><?php echo (int)$item['qty']; ?></td>
                            <td>
                                <?php if ((int)$item['qty'] <= 0): ?>
                                    <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>En rupture</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-circle me-1"></i>Stock faible</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4 d-print-none">
                                <a href="../purchases/add.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus me-1"></i>Commander
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-success">
                                <i class="fas fa-check-circle fa-2x d-block mb-2"></i>
                                Tous les stocks sont en ordre.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($active_tab === 'overview'): ?>
    var tickColor = '#94a3b8';
    var gridColor = '#1e293b';

    var ctx = document.getElementById('mainChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_labels, JSON_UNESCAPED_UNICODE); ?>,
            datasets: [
                {
                    label: 'Ventes',
                    data: <?php echo json_encode($chart_sales_day); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,0.12)',
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#3b82f6',
                    pointRadius: 3,
                    borderWidth: 2
                },
                {
                    label: 'Achats',
                    data: <?php echo json_encode($chart_pur_day); ?>,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,0.10)',
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#f59e0b',
                    pointRadius: 3,
                    borderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { labels: { color: tickColor } },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ctx.dataset.label + ': ' + new Intl.NumberFormat('fr-FR').format(ctx.parsed.y) + ' FC';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: tickColor,
                        callback: function(v) {
                            return new Intl.NumberFormat('fr-FR', { notation: 'compact' }).format(v) + ' FC';
                        }
                    },
                    grid: { color: gridColor }
                },
                x: {
                    ticks: { color: tickColor },
                    grid: { display: false }
                }
            }
        }
    });

    <?php if (count($by_category) > 0): ?>
    var ctx2 = document.getElementById('categoryChart').getContext('2d');
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($by_category, 'cat_name'), JSON_UNESCAPED_UNICODE); ?>,
            datasets: [{
                data: <?php echo json_encode(array_map('floatval', array_column($by_category, 'revenue'))); ?>,
                backgroundColor: ['#3b82f6','#22c55e','#f59e0b','#ef4444','#06b6d4','#8b5cf6','#f97316','#64748b'],
                borderWidth: 2,
                borderColor: '#0b1426'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: tickColor, padding: 12 }
                },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ctx.label + ': ' + new Intl.NumberFormat('fr-FR').format(ctx.parsed) + ' FC';
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
    <?php endif; ?>
});
</script>

<?php require_once '../../includes/footer.php'; ?>
