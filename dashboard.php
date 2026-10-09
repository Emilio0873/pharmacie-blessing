<?php
// Load dependencies FIRST — before any HTML output
require_once 'config/db.php';
require_once 'includes/functions.php';

if (!is_logged_in()) {
    redirect('index.php');
}

// Redirect role-specific users before any HTML is sent
if (has_role('Caissier')) {
    redirect('modules/caisse/index.php');
}
if (has_role('Magasinier')) {
    redirect('modules/products/index.php');
}

$page_title = "Tableau de Bord - PHARMACIE BLESSING";
require_once 'includes/header.php';

// Fetch stats
$total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$total_clients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$total_suppliers = $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
$low_stock = $pdo->query("SELECT COUNT(*) FROM products WHERE qty <= alert_threshold")->fetchColumn();

// Daily Sales and Purchases
$total_sales_today = $pdo->query("SELECT SUM(final_amount) FROM sales WHERE DATE(sale_date) = CURDATE()")->fetchColumn() ?: 0;
$total_purchases_today = $pdo->query("SELECT SUM(total_amount) FROM purchases WHERE DATE(purchase_date) = CURDATE()")->fetchColumn() ?: 0;

// Monthly Sales and Purchases
$current_month_start = date('Y-m-01');
$total_sales_month = $pdo->prepare("SELECT SUM(final_amount) FROM sales WHERE DATE(sale_date) >= ?");
$total_sales_month->execute([$current_month_start]);
$sales_month = $total_sales_month->fetchColumn() ?: 0;

$total_purchases_month = $pdo->prepare("SELECT SUM(total_amount) FROM purchases WHERE DATE(purchase_date) >= ?");
$total_purchases_month->execute([$current_month_start]);
$purchases_month = $total_purchases_month->fetchColumn() ?: 0;

// Recent Activities
$recent_activities = $pdo->query("SELECT a.*, u.full_name FROM audit_logs a JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 5")->fetchAll();

// Chart Data (Last 7 Days)
$chart_labels = [];
$chart_sales = [];
$chart_purchases = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d/m', strtotime("-$i days"));

    $stmt_s = $pdo->prepare("SELECT SUM(final_amount) FROM sales WHERE DATE(sale_date) = ?");
    $stmt_s->execute([$date]);
    $chart_sales[] = $stmt_s->fetchColumn() ?: 0;

    $stmt_p = $pdo->prepare("SELECT SUM(total_amount) FROM purchases WHERE DATE(purchase_date) = ?");
    $stmt_p->execute([$date]);
    $chart_purchases[] = $stmt_p->fetchColumn() ?: 0;
}
?>

<!-- Metric Cards Row 1 -->
<div class="row g-4 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100 p-3">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-light-primary text-primary me-3"><i class="fas fa-box"></i></div>
                <div><h6 class="text-muted mb-1 small fw-bold">Total Produits</h6><h3 class="mb-0 fw-bold"><?php echo $total_products; ?></h3></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100 p-3">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-light-success text-success me-3"><i class="fas fa-users"></i></div>
                <div><h6 class="text-muted mb-1 small fw-bold">Total Clients</h6><h3 class="mb-0 fw-bold"><?php echo $total_clients; ?></h3></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100 p-3">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-light-warning text-warning me-3"><i class="fas fa-truck-loading"></i></div>
                <div><h6 class="text-muted mb-1 small fw-bold">Fournisseurs</h6><h3 class="mb-0 fw-bold"><?php echo $total_suppliers; ?></h3></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100 p-3 border-start border-danger border-4">
            <div class="d-flex align-items-center">
                <div class="stat-icon bg-light-danger text-danger me-3"><i class="fas fa-exclamation-triangle"></i></div>
                <div><h6 class="text-muted mb-1 small fw-bold">Stock d'Alerte</h6><h3 class="mb-0 fw-bold text-danger"><?php echo $low_stock; ?></h3></div>
            </div>
        </div>
    </div>
</div>

<!-- Financial Cards Row 2 -->
<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card bg-success text-white shadow-sm h-100 p-3 border-0">
            <h6 class="text-white-50 fw-bold mb-3"><i class="fas fa-calendar-day me-2"></i>Ventes Aujourd'hui</h6>
            <h3 class="mb-0 fw-bold"><?php echo format_currency($total_sales_today); ?></h3>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card bg-primary text-white shadow-sm h-100 p-3 border-0">
            <h6 class="text-white-50 fw-bold mb-3"><i class="fas fa-calendar-alt me-2"></i>Ventes du Mois</h6>
            <h3 class="mb-0 fw-bold"><?php echo format_currency($sales_month); ?></h3>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card bg-warning text-dark shadow-sm h-100 p-3 border-0">
            <h6 class="text-dark opacity-75 fw-bold mb-3"><i class="fas fa-shopping-basket me-2"></i>Achats Aujourd'hui</h6>
            <h3 class="mb-0 fw-bold"><?php echo format_currency($total_purchases_today); ?></h3>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card bg-danger text-white shadow-sm h-100 p-3 border-0">
            <h6 class="text-white-50 fw-bold mb-3"><i class="fas fa-truck-moving me-2"></i>Achats du Mois</h6>
            <h3 class="mb-0 fw-bold"><?php echo format_currency($purchases_month); ?></h3>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Main Graph -->
    <div class="col-12 col-xl-8">
        <div class="card shadow-sm border-0 h-100 p-4">
            <h5 class="fw-bold mb-4">Ventes vs Achats (7 Derniers Jours)</h5>
            <canvas id="financeChart" height="100"></canvas>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="col-12 col-xl-4">
        <div class="card shadow-sm border-0 h-100 p-4">
            <h5 class="fw-bold mb-4">Activités Récentes</h5>
            <div class="activity-feed">
                <?php if (count($recent_activities) > 0): ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="d-flex mb-3">
                            <div class="me-3 position-relative">
                                <div class="bg-light-primary text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="fas fa-sync-alt small"></i>
                                </div>
                                <span class="position-absolute bottom-0 end-0 bg-success border border-white rounded-circle p-1"></span>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold small"><?php echo htmlspecialchars($activity['action']); ?></h6>
                                <p class="text-muted mb-0 small"><?php echo htmlspecialchars($activity['details']); ?></p>
                                <small class="text-muted" style="font-size: 11px;"><?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?> par <?php echo htmlspecialchars($activity['full_name']); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-muted">Aucune activité récente.</p>
                <?php endif; ?>
            </div>
            <a href="modules/audit/index.php" class="btn btn-outline-primary btn-sm w-100 mt-auto">Voir tout le journal</a>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Top Selling Products -->
    <div class="col-12 col-xl-6">
        <div class="card shadow-sm border-0 p-4 h-100">
            <h5 class="fw-bold mb-4"><i class="fas fa-fire text-danger me-2"></i>Top Produits Vendus (Ce Mois)</h5>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th class="text-center">Qté Vendue</th>
                            <th class="text-end">CA Généré</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $top_selling = $pdo->prepare("SELECT p.name, SUM(sd.qty) as total_qty, SUM(sd.qty * sd.unit_price) as total_revenue 
                                                      FROM sale_details sd 
                                                      JOIN sales s ON sd.sale_id = s.id 
                                                      JOIN products p ON sd.product_id = p.id 
                                                      WHERE DATE(s.sale_date) >= ? 
                                                      GROUP BY p.id ORDER BY total_qty DESC LIMIT 5");
                        $top_selling->execute([$current_month_start]);
                        $top_selling_items = $top_selling->fetchAll();

                        if (count($top_selling_items) > 0):
                            foreach ($top_selling_items as $item): 
                        ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($item['name']); ?></td>
                                <td class="text-center"><span class="badge bg-success rounded-pill"><?php echo $item['total_qty']; ?></span></td>
                                <td class="text-end fw-bold text-success"><?php echo format_currency($item['total_revenue']); ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="3" class="text-center py-4 text-muted">Pas de ventes ce mois.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Low Stock Table -->
    <div class="col-12 col-xl-6">
        <div class="card shadow-sm border-0 p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0 text-danger"><i class="fas fa-exclamation-circle me-2"></i>Alertes Stocks</h5>
                <a href="modules/reports/index.php?tab=stock" class="btn btn-danger btn-sm">Voir tout</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th class="text-center">Stock</th>
                            <th class="text-center">Seuil</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $low_stock_items = $pdo->query("SELECT p.name, p.qty, p.alert_threshold FROM products p WHERE p.qty <= p.alert_threshold LIMIT 5")->fetchAll();
                        if (count($low_stock_items) > 0):
                            foreach ($low_stock_items as $item): 
                        ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($item['name']); ?></td>
                                <td class="text-center fw-bold <?php echo $item['qty'] <= 0 ? 'text-danger' : 'text-warning'; ?>"><?php echo $item['qty']; ?></td>
                                <td class="text-center"><?php echo $item['alert_threshold']; ?></td>
                                <td>
                                    <?php if ($item['qty'] <= 0): ?>
                                        <span class="badge bg-danger">Rupture</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Faible</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">Aucu produit en alerte.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('financeChart').getContext('2d');
    var myChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [
                {
                    label: 'Ventes',
                    data: <?php echo json_encode($chart_sales); ?>,
                    backgroundColor: 'rgba(40, 167, 69, 0.8)',
                    borderRadius: 4
                },
                {
                    label: 'Achats',
                    data: <?php echo json_encode($chart_purchases); ?>,
                    backgroundColor: 'rgba(255, 193, 7, 0.8)',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
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
                    ticks: { color: '#94a3b8' },
                    grid: { borderDash: [2, 4], color: '#1e293b' }
                },
                x: {
                    ticks: { color: '#94a3b8' },
                    grid: { display: false }
                }
            }
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
