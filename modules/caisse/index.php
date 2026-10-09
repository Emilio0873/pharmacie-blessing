<?php
$page_title = "Module Caisse - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Caissier']);

$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard'; // dashboard, sales, purchases

// ──────────────────────────────────────────────────
// PERIOD ANALYTICS HELPER
// ──────────────────────────────────────────────────
function getPeriodStats($pdo, $year, $month = null, $day = null) {
    $where_sales = "WHERE YEAR(sale_date) = ?";
    $where_purchases = "WHERE YEAR(purchase_date) = ?";
    $params = [$year];

    if ($month) {
        $where_sales .= " AND MONTH(sale_date) = ?";
        $where_purchases .= " AND MONTH(purchase_date) = ?";
        $params[] = $month;
    }
    if ($day) {
        $where_sales .= " AND DAY(sale_date) = ?";
        $where_purchases .= " AND DAY(purchase_date) = ?";
        $params[] = $day;
    }

    $sales = $pdo->prepare("SELECT SUM(final_amount) FROM sales $where_sales");
    $sales->execute($params);
    $total_sales = $sales->fetchColumn() ?: 0;

    $purchases = $pdo->prepare("SELECT SUM(total_amount) FROM purchases $where_purchases");
    $purchases->execute($params);
    $total_purchases = $purchases->fetchColumn() ?: 0;

    return [
        'sales' => $total_sales,
        'purchases' => $total_purchases,
        'profit' => $total_sales - $total_purchases
    ];
}

// Stats Collection
$stats_today = getPeriodStats($pdo, date('Y'), date('m'), date('d'));
$stats_month = getPeriodStats($pdo, $selected_year, $selected_month);
$stats_year  = getPeriodStats($pdo, $selected_year);

// ──────────────────────────────────────────────────
// OPTIMIZED GROUP BY FOR MONTHLY DAILY TREND
// ──────────────────────────────────────────────────
$sales_query = $pdo->prepare("
    SELECT DAY(sale_date) as day_num, SUM(final_amount) as total 
    FROM sales 
    WHERE YEAR(sale_date) = ? AND MONTH(sale_date) = ? 
    GROUP BY DAY(sale_date)
");
$sales_query->execute([$selected_year, $selected_month]);
$sales_grouped = $sales_query->fetchAll(PDO::FETCH_KEY_PAIR);

$purchases_query = $pdo->prepare("
    SELECT DAY(purchase_date) as day_num, SUM(total_amount) as total 
    FROM purchases 
    WHERE YEAR(purchase_date) = ? AND MONTH(purchase_date) = ? 
    GROUP BY DAY(purchase_date)
");
$purchases_query->execute([$selected_year, $selected_month]);
$purchases_grouped = $purchases_query->fetchAll(PDO::FETCH_KEY_PAIR);

$days_in_month = cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year);
$chart_days = [];
$chart_sales = [];
$chart_purchases = [];

for ($d = 1; $d <= $days_in_month; $d++) {
    $chart_days[] = $d;
    $chart_sales[] = isset($sales_grouped[$d]) ? (float)$sales_grouped[$d] : 0.0;
    $chart_purchases[] = isset($purchases_grouped[$d]) ? (float)$purchases_grouped[$d] : 0.0;
}

// ──────────────────────────────────────────────────
// CASHIER PERSONAL SESSION METRICS
// ──────────────────────────────────────────────────
$my_sales_today = 0;
$my_sales_month = 0;
if (has_role('Caissier')) {
    $stmt = $pdo->prepare("SELECT SUM(final_amount) FROM sales WHERE user_id = ? AND DATE(sale_date) = CURDATE()");
    $stmt->execute([$_SESSION['user_id']]);
    $my_sales_today = $stmt->fetchColumn() ?: 0;

    $stmt = $pdo->prepare("SELECT SUM(final_amount) FROM sales WHERE user_id = ? AND YEAR(sale_date) = ? AND MONTH(sale_date) = ?");
    $stmt->execute([$_SESSION['user_id'], $selected_year, $selected_month]);
    $my_sales_month = $stmt->fetchColumn() ?: 0;
}

require_once '../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-0"><i class="fas fa-cash-register text-primary me-2"></i> Module Caisse</h3>
        <p class="text-muted small mb-0">Gestion financière, factures et suivi des bénéfices.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap w-100 w-md-auto" style="max-width: 320px;">
        <select class="form-select" onchange="window.location.href='?year='+this.value">
            <?php for($y=date('Y'); $y>=2023; $y--): ?>
                <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
        <button onclick="window.print()" class="btn btn-secondary d-print-none"><i class="fas fa-print me-2"></i>Imprimer</button>
    </div>
</div>

<!-- CASHIER SESSION HEADER WIDGET -->
<?php if (has_role('Caissier')): ?>
<div class="card border-0 shadow-sm mb-4 bg-light-primary text-primary-emphasis border-start border-primary border-4 p-3">
    <div class="row align-items-center g-3">
        <div class="col-md-8">
            <h5 class="fw-bold mb-1"><i class="fas fa-user-circle me-2"></i> Session de : <?php echo htmlspecialchars($_SESSION['full_name']); ?> (Caissier)</h5>
            <p class="mb-0 small">Mes performances de caisse : <strong><?php echo format_currency($my_sales_today); ?></strong> aujourd'hui et <strong><?php echo format_currency($my_sales_month); ?></strong> ce mois-ci.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="../sales/pos.php" class="btn btn-primary fw-bold shadow-sm"><i class="fas fa-plus-circle me-2"></i> Nouvelle Vente POS</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- TOP KPI CARDS (Financial Stats) -->
<div class="row g-3 mb-4">
    <!-- Daily -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm overflow-hidden h-100">
            <div class="card-header border-0 py-3">
                <h6 class="fw-bold mb-0 text-uppercase small text-muted"><i class="far fa-calendar-check me-2"></i>Journalier (Aujourd'hui)</h6>
            </div>
            <div class="card-body pt-0">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Ventes:</span>
                    <span class="fw-bold text-success"><?php echo format_currency($stats_today['sales']); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Achats:</span>
                    <span class="fw-bold text-danger"><?php echo format_currency($stats_today['purchases']); ?></span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between">
                    <span class="fw-bold">Bénéfice:</span>
                    <span class="h5 fw-bold mb-0 <?php echo $stats_today['profit'] >= 0 ? 'text-primary' : 'text-danger'; ?>">
                        <?php echo format_currency($stats_today['profit']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <!-- Monthly -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm overflow-hidden h-100" style="border-top: 4px solid #0d6efd !important;">
            <div class="card-header border-0 py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-uppercase small text-muted"><i class="far fa-calendar-alt me-2"></i>Mensuel (<?php echo date('M', mktime(0,0,0,$selected_month, 1)); ?>)</h6>
                <select class="form-select form-select-sm w-auto d-print-none" onchange="window.location.href='?year=<?php echo $selected_year; ?>&month='+this.value">
                    <?php for($m=1; $m<=12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>><?php echo date('M', mktime(0,0,0,$m, 1)); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="card-body pt-0">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Ventes:</span>
                    <span class="fw-bold text-success"><?php echo format_currency($stats_month['sales']); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Achats:</span>
                    <span class="fw-bold text-danger"><?php echo format_currency($stats_month['purchases']); ?></span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between">
                    <span class="fw-bold">Bénéfice:</span>
                    <span class="h5 fw-bold mb-0 text-primary">
                        <?php echo format_currency($stats_month['profit']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <!-- Annual -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm overflow-hidden h-100" style="border-top: 4px solid #198754 !important;">
            <div class="card-header border-0 py-3">
                <h6 class="fw-bold mb-0 text-uppercase small text-muted"><i class="fas fa-calendar me-2"></i>Annuel (<?php echo $selected_year; ?>)</h6>
            </div>
            <div class="card-body pt-0">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Ventes:</span>
                    <span class="fw-bold text-success"><?php echo format_currency($stats_year['sales']); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Achats:</span>
                    <span class="fw-bold text-danger"><?php echo format_currency($stats_year['purchases']); ?></span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between">
                    <span class="fw-bold">Bénéfice:</span>
                    <span class="h5 fw-bold mb-0 text-success">
                        <?php echo format_currency($stats_year['profit']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- NAVIGATION TABS -->
<ul class="nav nav-pills mb-4 d-print-none p-2 rounded shadow-sm border">
    <li class="nav-item">
        <a class="nav-link <?php echo $view == 'dashboard' ? 'active' : ''; ?>" href="?view=dashboard&year=<?php echo $selected_year; ?>&month=<?php echo $selected_month; ?>"><i class="fas fa-tachometer-alt me-2"></i>Vue Générale</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $view == 'sales' ? 'active' : ''; ?>" href="?view=sales&year=<?php echo $selected_year; ?>&month=<?php echo $selected_month; ?>"><i class="fas fa-file-invoice-dollar me-2"></i>Factures de Vente</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $view == 'purchases' ? 'active' : ''; ?>" href="?view=purchases&year=<?php echo $selected_year; ?>&month=<?php echo $selected_month; ?>"><i class="fas fa-truck-loading me-2"></i>Factures d'Achat</a>
    </li>
</ul>

<!-- ============== TAB CONTENT ============== -->

<?php if ($view == 'dashboard'): ?>
    <div class="row g-4">
        <!-- Monthly Sales Trend Chart -->
        <div class="col-md-7">
            <div class="card border-0 shadow-sm h-100 p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">Évolution Quotidienne</h5>
                    <span class="badge bg-light text-dark border">CA du mois: <?php echo format_currency($stats_month['sales']); ?></span>
                </div>
                <div style="position: relative; height: 260px;">
                    <canvas id="caisseTrendChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- ROI and Annual Performance -->
        <div class="col-md-5">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-4 text-center">
                    <h6 class="text-muted fw-bold text-uppercase small mb-3">Taux de Profitabilité (ROI)</h6>
                    <h2 class="fw-bold text-primary mb-2">
                        <?php 
                        $roi = ($stats_month['purchases'] > 0) ? (($stats_month['sales'] - $stats_month['purchases']) / $stats_month['purchases']) * 100 : 0;
                        echo number_format($roi, 1);
                        ?>%
                    </h2>
                    <p class="text-muted small mb-0">Rapport ventes/achats pour ce mois-ci.</p>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm">
                <div class="card-header border-0 py-3">
                    <h6 class="fw-bold mb-0">Rendement Annuel (<?php echo $selected_year; ?>)</h6>
                </div>
                <div class="card-body p-0" style="max-height: 184px; overflow-y: auto;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th class="ps-3">Mois</th>
                                <th class="text-end">Ventes</th>
                                <th class="text-end pe-3">Bénéfice</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for($m=1; $m<=12; $m++): 
                                $s = getPeriodStats($pdo, $selected_year, $m);
                                if ($s['sales'] == 0 && $s['purchases'] == 0) continue;
                            ?>
                            <tr class="<?php echo $m == date('m') && $selected_year == date('Y') ? 'table-primary' : ''; ?>">
                                <td class="ps-3"><?php echo date('F', mktime(0,0,0,$m, 1)); ?></td>
                                <td class="text-end fw-bold"><?php echo format_currency($s['sales']); ?></td>
                                <td class="text-end text-success pe-3"><?php echo format_currency($s['profit']); ?></td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($view == 'sales'): ?>
    <?php
    $stmt = $pdo->prepare("SELECT s.*, c.name as client_name, u.full_name as user_name 
                           FROM sales s 
                           LEFT JOIN clients c ON s.client_id = c.id 
                           LEFT JOIN users u ON s.user_id = u.id 
                           WHERE YEAR(s.sale_date) = ? AND MONTH(s.sale_date) = ?
                           ORDER BY s.sale_date DESC");
    $stmt->execute([$selected_year, $selected_month]);
    $sales = $stmt->fetchAll();
    ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header border-0 py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">Factures de Vente - <?php echo date('F Y', mktime(0,0,0,$selected_month, 1, $selected_year)); ?></h5>
            <span class="badge bg-primary rounded-pill"><?php echo count($sales); ?> factures</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">N° Facture</th>
                            <th>Date & Heure</th>
                            <th>Client</th>
                            <th>Montant Net</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($sales as $s): ?>
                        <tr>
                            <td class="ps-4 fw-bold">#<?php echo str_pad($s['id'], 5, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($s['sale_date'])); ?></td>
                            <td><?php echo htmlspecialchars($s['client_name'] ?? 'Client de passage'); ?></td>
                            <td class="fw-bold text-success"><?php echo format_currency($s['final_amount']); ?></td>
                            <td class="text-end pe-4">
                                <a href="../sales/invoice.php?id=<?php echo $s['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print me-1"></i>Facture</a>
                                <a href="../sales/invoice.php?id=<?php echo $s['id']; ?>&download=pdf" target="_blank" class="btn btn-sm btn-outline-primary ms-1"><i class="fas fa-download me-1"></i>PDF</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($sales)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">Aucune vente pour ce mois.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($view == 'purchases'): ?>
    <?php
    $stmt = $pdo->prepare("SELECT p.*, s.name as supplier_name FROM purchases p 
                           LEFT JOIN suppliers s ON p.supplier_id = s.id 
                           WHERE YEAR(p.purchase_date) = ? AND MONTH(p.purchase_date) = ?
                           ORDER BY p.purchase_date DESC");
    $stmt->execute([$selected_year, $selected_month]);
    $purchases = $stmt->fetchAll();
    ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header border-0 py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">Factures d'Achat - <?php echo date('F Y', mktime(0,0,0,$selected_month, 1, $selected_year)); ?></h5>
            <span class="badge bg-danger rounded-pill"><?php echo count($purchases); ?> factures</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">N° Bon</th>
                            <th>Date</th>
                            <th>Fournisseur</th>
                            <th>Statut Stock</th>
                            <th class="text-end pe-4">Montant Payé</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($purchases as $p): ?>
                        <tr>
                            <td class="ps-4 fw-bold">#<?php echo str_pad($p['id'], 5, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($p['purchase_date'])); ?></td>
                            <td><?php echo htmlspecialchars($p['supplier_name'] ?? 'Inconnu'); ?></td>
                            <td><span class="badge bg-success">Reçu (En Stock)</span></td>
                            <td class="text-end fw-bold text-danger pe-4"><?php echo format_currency($p['total_amount']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($purchases)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">Aucun achat pour ce mois.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($view == 'dashboard'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('caisseTrendChart').getContext('2d');
    var caisseTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chart_days); ?>,
            datasets: [
                {
                    label: 'Ventes',
                    data: <?php echo json_encode($chart_sales); ?>,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.05)',
                    borderWidth: 2.5,
                    tension: 0.35,
                    fill: true
                },
                {
                    label: 'Achats',
                    data: <?php echo json_encode($chart_purchases); ?>,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.05)',
                    borderWidth: 2,
                    tension: 0.35,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { boxWidth: 15, font: { weight: 'bold' } }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) { label += ': '; }
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('fr-FR').format(context.parsed.y) + ' FC';
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { borderDash: [2, 4], color: '#eef2f5' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
