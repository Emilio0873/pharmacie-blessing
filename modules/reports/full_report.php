<?php
$page_title = "Rapport Complet - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Magasinier']);

ensure_invoicing_tables($pdo);
ensure_product_lot_column($pdo);

$business = get_business_profile($pdo);

$date_from_raw = $_GET['date_from'] ?? date('Y-m-01');
$date_to_raw   = $_GET['date_to'] ?? date('Y-m-d');
$date_from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from_raw) ? $date_from_raw : date('Y-m-01');
$date_to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to_raw) ? $date_to_raw : date('Y-m-d');
if (strtotime($date_from) > strtotime($date_to)) {
    $tmp = $date_from; $date_from = $date_to; $date_to = $tmp;
}

$search = isset($_GET['q']) ? trim(strip_tags((string)$_GET['q'])) : '';
$search = mb_substr($search, 0, 120);
$search_like = $search !== '' ? '%' . $search . '%' : '';
$search_digits = preg_replace('/\D+/', '', $search);

$period_label = date('d/m/Y', strtotime($date_from)) . ' au ' . date('d/m/Y', strtotime($date_to));
$generated_at = date('d/m/Y H:i');
$editor = $_SESSION['full_name'] ?? 'Utilisateur';
$report_ref = 'RPT-' . date('Ymd', strtotime($date_from)) . '-' . date('Ymd', strtotime($date_to));

// Helper: append search params to quick links
$qs_base = 'date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . ($search !== '' ? '&q=' . urlencode($search) : '');

// ── Synthèse ventes ──
$stmt = $pdo->prepare("SELECT
    COUNT(*) as nb_ventes,
    COALESCE(SUM(total_amount), 0) as ca_ht,
    COALESCE(SUM(discount), 0) as remises,
    COALESCE(SUM(final_amount), 0) as ca_ttc
    FROM sales WHERE DATE(sale_date) BETWEEN ? AND ?");
$stmt->execute([$date_from, $date_to]);
$kpi_sales = $stmt->fetch();

// TVA factures
$stmt = $pdo->prepare("SELECT COALESCE(SUM(tax_amount), 0) FROM invoices inv
    JOIN sales s ON s.id = inv.sale_id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?");
$stmt->execute([$date_from, $date_to]);
$total_tva = (float)$stmt->fetchColumn();

// Bénéfice estimé
$stmt = $pdo->prepare("SELECT COALESCE(SUM((sd.unit_price - p.buy_price) * sd.qty), 0)
    FROM sale_details sd
    JOIN sales s ON sd.sale_id = s.id
    JOIN products p ON sd.product_id = p.id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?");
$stmt->execute([$date_from, $date_to]);
$profit_raw = (float)$stmt->fetchColumn();
$estimated_profit = $profit_raw - (float)$kpi_sales['remises'];

// Achats
$stmt = $pdo->prepare("SELECT COUNT(*) as nb, COALESCE(SUM(total_amount), 0) as total
    FROM purchases WHERE DATE(purchase_date) BETWEEN ? AND ?");
$stmt->execute([$date_from, $date_to]);
$kpi_purchases = $stmt->fetch();

// Détail ventes
$sales_sql = "SELECT s.id, s.sale_date, s.total_amount, s.discount, s.final_amount,
    c.name as client_name, u.full_name as cashier,
    inv.invoice_number, inv.tax_rate, inv.tax_amount
    FROM sales s
    LEFT JOIN clients c ON c.id = s.client_id
    LEFT JOIN users u ON u.id = s.user_id
    LEFT JOIN invoices inv ON inv.sale_id = s.id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?";
$sales_params = [$date_from, $date_to];
if ($search !== '') {
    $sales_sql .= " AND (
        c.name LIKE ?
        OR u.full_name LIKE ?
        OR inv.invoice_number LIKE ?
        OR CAST(s.id AS CHAR) LIKE ?
        OR REPLACE(UPPER(COALESCE(inv.invoice_number,'')), '-', '') LIKE REPLACE(UPPER(?), '-', '')
        OR EXISTS (
            SELECT 1 FROM sale_details sd
            JOIN products p ON p.id = sd.product_id
            WHERE sd.sale_id = s.id
              AND (p.name LIKE ? OR p.code LIKE ? OR p.lot_number LIKE ?)
        )
    )";
    $sales_params[] = $search_like;
    $sales_params[] = $search_like;
    $sales_params[] = $search_like;
    $sales_params[] = $search_like;
    $sales_params[] = $search;
    $sales_params[] = $search_like;
    $sales_params[] = $search_like;
    $sales_params[] = $search_like;
}
$sales_sql .= " ORDER BY s.sale_date DESC";
$stmt = $pdo->prepare($sales_sql);
$stmt->execute($sales_params);
$sales_list = $stmt->fetchAll();

// Achats détail
$pur_sql = "SELECT pu.*, s.name as supplier_name, u.full_name as created_by_name
    FROM purchases pu
    LEFT JOIN suppliers s ON s.id = pu.supplier_id
    LEFT JOIN users u ON u.id = pu.created_by
    WHERE DATE(pu.purchase_date) BETWEEN ? AND ?";
$pur_params = [$date_from, $date_to];
if ($search !== '') {
    $pur_sql .= " AND (
        s.name LIKE ?
        OR u.full_name LIKE ?
        OR CAST(pu.id AS CHAR) LIKE ?
        OR pu.status LIKE ?
        OR EXISTS (
            SELECT 1 FROM purchase_details pd
            JOIN products p ON p.id = pd.product_id
            WHERE pd.purchase_id = pu.id
              AND (p.name LIKE ? OR p.code LIKE ? OR p.lot_number LIKE ?)
        )
    )";
    $pur_params[] = $search_like;
    $pur_params[] = $search_like;
    $pur_params[] = $search_like;
    $pur_params[] = $search_like;
    $pur_params[] = $search_like;
    $pur_params[] = $search_like;
    $pur_params[] = $search_like;
}
$pur_sql .= " ORDER BY pu.purchase_date DESC";
$stmt = $pdo->prepare($pur_sql);
$stmt->execute($pur_params);
$purchases_list = $stmt->fetchAll();

// Top produits
$prod_sql = "SELECT p.name, p.code, p.lot_number,
    COALESCE(SUM(sd.qty), 0) as qty_sold,
    COALESCE(SUM(sd.qty * sd.unit_price), 0) as revenue,
    COALESCE(SUM(sd.qty * p.buy_price), 0) as cost
    FROM sale_details sd
    JOIN sales s ON s.id = sd.sale_id
    JOIN products p ON p.id = sd.product_id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?";
$prod_params = [$date_from, $date_to];
if ($search !== '') {
    $prod_sql .= " AND (p.name LIKE ? OR p.code LIKE ? OR p.lot_number LIKE ?)";
    $prod_params[] = $search_like;
    $prod_params[] = $search_like;
    $prod_params[] = $search_like;
}
$prod_sql .= " GROUP BY p.id, p.name, p.code, p.lot_number
    ORDER BY qty_sold DESC
    LIMIT 15";
$stmt = $pdo->prepare($prod_sql);
$stmt->execute($prod_params);
$top_products = $stmt->fetchAll();

// Top clients
$cli_sql = "SELECT c.name, c.phone,
    COUNT(s.id) as nb, COALESCE(SUM(s.final_amount), 0) as spent
    FROM clients c
    JOIN sales s ON s.client_id = c.id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?";
$cli_params = [$date_from, $date_to];
if ($search !== '') {
    $cli_sql .= " AND (c.name LIKE ? OR c.phone LIKE ?)";
    $cli_params[] = $search_like;
    $cli_params[] = $search_like;
}
$cli_sql .= " GROUP BY c.id, c.name, c.phone
    ORDER BY spent DESC
    LIMIT 10";
$stmt = $pdo->prepare($cli_sql);
$stmt->execute($cli_params);
$top_clients = $stmt->fetchAll();

// CA par catégorie
$stmt = $pdo->prepare("SELECT cat.name as cat_name, COALESCE(SUM(sd.qty * sd.unit_price), 0) as revenue
    FROM sale_details sd
    JOIN products p ON p.id = sd.product_id
    JOIN categories cat ON cat.id = p.category_id
    JOIN sales s ON s.id = sd.sale_id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?
    GROUP BY cat.id, cat.name
    ORDER BY revenue DESC");
$stmt->execute([$date_from, $date_to]);
$by_category = $stmt->fetchAll();

// Alertes stock
$stock_sql = "SELECT p.name, p.code, p.lot_number, p.qty, p.alert_threshold, p.exp_date, c.name as cat_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.qty <= p.alert_threshold";
$stock_params = [];
if ($search !== '') {
    $stock_sql .= " AND (p.name LIKE ? OR p.code LIKE ? OR p.lot_number LIKE ? OR c.name LIKE ?)";
    $stock_params = [$search_like, $search_like, $search_like, $search_like];
}
$stock_sql .= " ORDER BY p.qty ASC, p.name ASC";
$stmt = $pdo->prepare($stock_sql);
$stmt->execute($stock_params);
$stock_alerts = $stmt->fetchAll();

// Produits bientôt périmés (90 jours)
$exp_sql = "SELECT name, code, lot_number, qty, exp_date
    FROM products
    WHERE exp_date IS NOT NULL AND exp_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)";
$exp_params = [];
if ($search !== '') {
    $exp_sql .= " AND (name LIKE ? OR code LIKE ? OR lot_number LIKE ?)";
    $exp_params = [$search_like, $search_like, $search_like];
}
$exp_sql .= " ORDER BY exp_date ASC LIMIT 20";
$stmt = $pdo->prepare($exp_sql);
$stmt->execute($exp_params);
$expiring = $stmt->fetchAll();

// Caissiers performance
$cash_sql = "SELECT u.full_name, COUNT(s.id) as nb, COALESCE(SUM(s.final_amount), 0) as total
    FROM sales s
    JOIN users u ON u.id = s.user_id
    WHERE DATE(s.sale_date) BETWEEN ? AND ?";
$cash_params = [$date_from, $date_to];
if ($search !== '') {
    $cash_sql .= " AND u.full_name LIKE ?";
    $cash_params[] = $search_like;
}
$cash_sql .= " GROUP BY u.id, u.full_name ORDER BY total DESC";
$stmt = $pdo->prepare($cash_sql);
$stmt->execute($cash_params);
$cashiers = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<style>
.rpt-toolbar {
    display: flex; flex-wrap: wrap; gap: 1rem;
    align-items: flex-end; justify-content: space-between;
    margin-bottom: 1.25rem;
}
.rpt-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
.rpt-sheet {
    background: linear-gradient(180deg, rgba(17,27,47,.98), rgba(11,18,32,.98));
    border: 1px solid #223253;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 14px 36px rgba(2,6,23,.35);
}
.rpt-head {
    background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 100%);
    color: #fff;
    padding: 1.5rem 1.75rem;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 1.25rem;
    align-items: start;
}
.rpt-head .brand { display: flex; gap: 14px; align-items: center; }
.rpt-head .brand img {
    width: 58px; height: 58px; border-radius: 50%;
    object-fit: cover; border: 2px solid rgba(255,255,255,.55);
}
.rpt-head h1 { font-size: 1.35rem; font-weight: 800; margin: 0; }
.rpt-head .sub { opacity: .85; font-size: .85rem; margin-top: 2px; }
.rpt-meta { text-align: right; font-size: .86rem; line-height: 1.55; }
.rpt-meta strong { display: block; font-size: 1rem; margin-bottom: 4px; }
.rpt-body { padding: 1.25rem 1.5rem 1.75rem; }
.rpt-section { margin-bottom: 1.75rem; break-inside: avoid; }
.rpt-section-title {
    font-size: .78rem; font-weight: 800; letter-spacing: .07em;
    text-transform: uppercase; color: #93c5fd;
    border-bottom: 1px solid #223253; padding-bottom: .45rem; margin-bottom: .9rem;
}
.rpt-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: .85rem;
}
.rpt-kpi {
    background: #0b1426;
    border: 1px solid #223253;
    border-radius: 12px;
    padding: .9rem 1rem;
    border-left: 4px solid #3b82f6;
}
.rpt-kpi.success { border-left-color: #22c55e; }
.rpt-kpi.warning { border-left-color: #f59e0b; }
.rpt-kpi.danger { border-left-color: #ef4444; }
.rpt-kpi .lbl { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; font-weight: 700; }
.rpt-kpi .val { font-size: 1.15rem; font-weight: 800; color: #e2e8f0; margin-top: 4px; }
.rpt-kpi .hint { font-size: .75rem; color: #64748b; margin-top: 2px; }
.rpt-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.rpt-table {
    width: 100%; border-collapse: collapse; min-width: 640px;
}
.rpt-table th {
    background: #0b1426; color: #bfdbfe; font-size: .72rem;
    text-transform: uppercase; letter-spacing: .04em;
    padding: 10px 12px; text-align: left; border-bottom: 1px solid #223253;
}
.rpt-table td {
    padding: 10px 12px; border-bottom: 1px solid #1e293b;
    color: #cbd5e1; font-size: .88rem; vertical-align: middle;
}
.rpt-table .num { text-align: right; white-space: nowrap; font-weight: 600; }
.rpt-table .ctr { text-align: center; }
.rpt-foot {
    border-top: 1px solid #223253;
    padding: 1.25rem 1.5rem 1.5rem;
    color: #94a3b8; font-size: .84rem; line-height: 1.55;
}
.rpt-signs {
    display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 1.5rem;
}
.rpt-sign { text-align: center; max-width: 280px; }
.rpt-sign:last-child { justify-self: end; }
.rpt-sign-line {
    margin-top: 48px; border-top: 1px dashed #64748b;
    padding-top: 8px; font-size: .8rem; font-weight: 600; color: #94a3b8;
}
.rpt-filter-card {
    border: 1px solid #223253 !important;
    background: linear-gradient(180deg, rgba(17,27,47,.98), rgba(11,18,32,.98)) !important;
}
.sticky-search {
    position: sticky;
    top: 64px;
    z-index: 15;
}
.rpt-table tr.trace-hit td {
    background: rgba(29, 78, 216, 0.22) !important;
}
.rpt-table tr.trace-hide {
    display: none;
}

@media (max-width: 991.98px) {
    .rpt-kpi-grid { grid-template-columns: 1fr 1fr; }
    .rpt-head { grid-template-columns: 1fr; }
    .rpt-meta { text-align: left; }
}
@media (max-width: 575.98px) {
    .rpt-kpi-grid { grid-template-columns: 1fr; }
    .rpt-signs { grid-template-columns: 1fr; }
    .rpt-sign, .rpt-sign:last-child { max-width: none; justify-self: stretch; }
}

@media print {
    body { background: #fff !important; color: #0f172a !important; }
    .wrapper #sidebar, .navbar, .sidebar-overlay, .d-print-none { display: none !important; }
    #content, .container-fluid { padding: 0 !important; margin: 0 !important; width: 100% !important; }
    .rpt-sheet {
        background: #fff !important; border: none !important; border-radius: 0 !important;
        box-shadow: none !important; color: #0f172a !important;
    }
    .rpt-head {
        background: #fff !important; color: #0f172a !important;
        border-bottom: 3px solid #1d4ed8; -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    .rpt-head .sub, .rpt-meta { color: #475569 !important; opacity: 1; }
    .rpt-section-title { color: #1e3a8a !important; border-bottom-color: #cbd5e1 !important; }
    .rpt-kpi { background: #f8fafc !important; border-color: #e2e8f0 !important; color: #0f172a !important; }
    .rpt-kpi .lbl, .rpt-kpi .hint { color: #64748b !important; }
    .rpt-kpi .val { color: #0f172a !important; }
    .rpt-table { min-width: 0 !important; }
    .rpt-table th { background: #f1f5f9 !important; color: #334155 !important; border-color: #cbd5e1 !important; }
    .rpt-table td { color: #0f172a !important; border-color: #e2e8f0 !important; }
    .rpt-foot { color: #475569 !important; border-top-color: #cbd5e1 !important; }
    .rpt-section { break-inside: avoid; page-break-inside: avoid; }
    .badge { border: 1px solid #cbd5e1 !important; color: #0f172a !important; background: #f8fafc !important; }
}
</style>

<div class="rpt-toolbar d-print-none">
    <div>
        <h3 class="fw-bold mb-1"><i class="fas fa-file-alt text-primary me-2"></i>Rapport complet d'activité</h3>
        <p class="text-muted mb-0">Document structuré regroupant ventes, achats, stocks, clients et performances.</p>
    </div>
    <div class="rpt-actions">
        <a href="index.php?date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" class="btn btn-light border">
            <i class="fas fa-chart-line me-1"></i> Statistiques
        </a>
        <button type="button" class="btn btn-primary fw-bold" onclick="window.print()">
            <i class="fas fa-print me-1"></i> Imprimer / PDF
        </button>
    </div>
</div>

<div class="card rpt-filter-card shadow-sm border-0 mb-4 d-print-none">
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end" id="reportFilterForm">
            <div class="col-12">
                <label class="form-label small fw-bold text-muted" for="reportSearch">Recherche / traçabilité</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="search" class="form-control form-control-lg" id="reportSearch" name="q"
                           value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="N° facture, client, produit, lot, caissier, fournisseur…"
                           autocomplete="off">
                    <?php if ($search !== ''): ?>
                    <a href="?date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" class="btn btn-outline-secondary" title="Effacer la recherche">
                        <i class="fas fa-times"></i>
                    </a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary fw-bold px-4">
                        <i class="fas fa-filter me-1"></i> Filtrer
                    </button>
                </div>
                <small class="text-muted">Recherche dans les factures, ventes, lots, produits, clients, fournisseurs et stocks.</small>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <label class="form-label small fw-bold text-muted" for="date_from">Du</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" required>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <label class="form-label small fw-bold text-muted" for="date_to">Au</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" required>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <button type="submit" class="btn btn-primary w-100 fw-bold">
                    <i class="fas fa-sync me-1"></i> Générer le rapport
                </button>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="btn-group w-100">
                    <a class="btn btn-outline-secondary btn-sm" href="?date_from=<?php echo date('Y-m-d'); ?>&date_to=<?php echo date('Y-m-d'); ?><?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>">Aujourd'hui</a>
                    <a class="btn btn-outline-secondary btn-sm" href="?date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-d'); ?><?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>">Ce mois</a>
                    <a class="btn btn-outline-secondary btn-sm" href="?date_from=<?php echo date('Y-01-01'); ?>&date_to=<?php echo date('Y-12-31'); ?><?php echo $search !== '' ? '&q=' . urlencode($search) : ''; ?>">Année</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($search !== ''): ?>
<div class="alert alert-info border-0 d-print-none mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <i class="fas fa-search me-2"></i>
        Traçabilité active pour : <strong><?php echo htmlspecialchars($search); ?></strong>
        — <?php echo count($sales_list); ?> vente(s), <?php echo count($purchases_list); ?> achat(s), <?php echo count($top_products); ?> produit(s) trouvé(s).
    </div>
    <a href="?date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" class="btn btn-sm btn-outline-primary">Réinitialiser</a>
</div>
<?php endif; ?>

<!-- Live highlight search within loaded report -->
<div class="card rpt-filter-card shadow-sm border-0 mb-3 d-print-none sticky-search">
    <div class="card-body py-2">
        <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="fas fa-highlighter"></i></span>
            <input type="search" class="form-control" id="liveTraceSearch" placeholder="Surbrillance rapide dans le rapport affiché (sans recharger)…">
            <span class="input-group-text" id="liveTraceCount">—</span>
        </div>
    </div>
</div>

<article class="rpt-sheet" id="fullReportSheet">
    <header class="rpt-head">
        <div class="brand">
            <img src="../../assets/img/pha.jpeg" alt="Logo" class="logo-clock">
            <div>
                <h1><?php echo htmlspecialchars($business['name']); ?></h1>
                <div class="sub">
                    <?php echo htmlspecialchars($business['subtitle']); ?><br>
                    <?php echo htmlspecialchars($business['address']); ?><br>
                    Tél: <?php echo htmlspecialchars($business['phone']); ?>
                    <?php if (!empty($business['email'])): ?> | <?php echo htmlspecialchars($business['email']); ?><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="rpt-meta">
            <strong>RAPPORT COMPLET D'ACTIVITÉ</strong>
            Réf. <?php echo htmlspecialchars($report_ref); ?><br>
            Période : <?php echo htmlspecialchars($period_label); ?><br>
            <?php if ($search !== ''): ?>
            Filtre traçabilité : « <?php echo htmlspecialchars($search); ?> »<br>
            <?php endif; ?>
            Édité le <?php echo htmlspecialchars($generated_at); ?><br>
            Par <?php echo htmlspecialchars($editor); ?> (<?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?>)
        </div>
    </header>

    <div class="rpt-body">
        <!-- 1. Synthèse -->
        <section class="rpt-section">
            <h2 class="rpt-section-title">1. Synthèse financière</h2>
            <div class="rpt-kpi-grid">
                <div class="rpt-kpi">
                    <div class="lbl">Chiffre d'affaires TTC</div>
                    <div class="val"><?php echo format_currency($kpi_sales['ca_ttc']); ?></div>
                    <div class="hint"><?php echo (int)$kpi_sales['nb_ventes']; ?> vente(s)</div>
                </div>
                <div class="rpt-kpi success">
                    <div class="lbl">Bénéfice estimé</div>
                    <div class="val"><?php echo format_currency(max(0, $estimated_profit)); ?></div>
                    <div class="hint">Marge brute après remises</div>
                </div>
                <div class="rpt-kpi warning">
                    <div class="lbl">Achats fournisseurs</div>
                    <div class="val"><?php echo format_currency($kpi_purchases['total']); ?></div>
                    <div class="hint"><?php echo (int)$kpi_purchases['nb']; ?> commande(s)</div>
                </div>
                <div class="rpt-kpi danger">
                    <div class="lbl">Remises + TVA</div>
                    <div class="val"><?php echo format_currency($kpi_sales['remises']); ?></div>
                    <div class="hint">TVA facturée : <?php echo format_currency($total_tva); ?></div>
                </div>
            </div>
        </section>

        <!-- 2. Performance caissiers -->
        <section class="rpt-section">
            <h2 class="rpt-section-title">2. Performance des caissiers</h2>
            <div class="rpt-table-wrap">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th>Caissier</th>
                            <th class="ctr">Nb ventes</th>
                            <th class="num">Montant encaissé</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($cashiers) === 0): ?>
                        <tr><td colspan="3" class="ctr text-muted">Aucune donnée.</td></tr>
                        <?php else: foreach ($cashiers as $c): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($c['full_name']); ?></td>
                            <td class="ctr"><?php echo (int)$c['nb']; ?></td>
                            <td class="num"><?php echo format_currency($c['total']); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- 4. Détail ventes -->
        <section class="rpt-section">
            <h2 class="rpt-section-title">3. Détail des ventes & factures (<?php echo count($sales_list); ?>)</h2>
            <div class="rpt-table-wrap">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th>Facture</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Caissier</th>
                            <th class="num">Remise</th>
                            <th class="num">TVA</th>
                            <th class="num">Total TTC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($sales_list) === 0): ?>
                        <tr><td colspan="7" class="ctr text-muted">Aucune vente enregistrée.</td></tr>
                        <?php else: foreach ($sales_list as $s):
                            $inv = !empty($s['invoice_number']) ? $s['invoice_number'] : format_invoice_number($s['id']);
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($inv); ?></strong></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($s['sale_date'])); ?></td>
                            <td><?php echo htmlspecialchars($s['client_name'] ?? 'Client de passage'); ?></td>
                            <td><?php echo htmlspecialchars($s['cashier'] ?? 'N/A'); ?></td>
                            <td class="num"><?php echo format_currency($s['discount']); ?></td>
                            <td class="num"><?php echo format_currency($s['tax_amount'] ?? 0); ?></td>
                            <td class="num"><?php echo format_currency($s['final_amount']); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- 5. Achats -->
        <section class="rpt-section">
            <h2 class="rpt-section-title">4. Achats fournisseurs (<?php echo count($purchases_list); ?>)</h2>
            <div class="rpt-table-wrap">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th>N° bon</th>
                            <th>Date</th>
                            <th>Fournisseur</th>
                            <th>Enregistré par</th>
                            <th>Statut</th>
                            <th class="num">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($purchases_list) === 0): ?>
                        <tr><td colspan="6" class="ctr text-muted">Aucun achat sur la période.</td></tr>
                        <?php else: foreach ($purchases_list as $pu): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad((int)$pu['id'], 5, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo date('d/m/Y', strtotime($pu['purchase_date'])); ?></td>
                            <td><?php echo htmlspecialchars($pu['supplier_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($pu['created_by_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($pu['status'] ?? 'received'); ?></td>
                            <td class="num"><?php echo format_currency($pu['total_amount']); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- 6. Top produits -->
        <section class="rpt-section">
            <h2 class="rpt-section-title">5. Produits les plus vendus</h2>
            <div class="rpt-table-wrap">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Produit</th>
                            <th>Code</th>
                            <th>Lot</th>
                            <th class="ctr">Qté</th>
                            <th class="num">CA</th>
                            <th class="num">Marge</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($top_products) === 0): ?>
                        <tr><td colspan="7" class="ctr text-muted">Aucune vente produit.</td></tr>
                        <?php else: foreach ($top_products as $i => $p):
                            $margin = (float)$p['revenue'] - (float)$p['cost'];
                        ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['code'] ?? '-'); ?></td>
                            <td><?php echo !empty($p['lot_number']) ? htmlspecialchars($p['lot_number']) : '-'; ?></td>
                            <td class="ctr"><?php echo (int)$p['qty_sold']; ?></td>
                            <td class="num"><?php echo format_currency($p['revenue']); ?></td>
                            <td class="num"><?php echo format_currency($margin); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- 7. Catégories -->
        <section class="rpt-section">
            <h2 class="rpt-section-title">6. Chiffre d'affaires par catégorie</h2>
            <div class="rpt-table-wrap">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th>Catégorie</th>
                            <th class="num">CA</th>
                            <th class="num">Part</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $cat_sum = 0;
                        foreach ($by_category as $cat) $cat_sum += (float)$cat['revenue'];
                        $cat_sum = max(0.01, $cat_sum);
                        if (count($by_category) === 0): ?>
                        <tr><td colspan="3" class="ctr text-muted">Aucune donnée.</td></tr>
                        <?php else: foreach ($by_category as $cat): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cat['cat_name']); ?></td>
                            <td class="num"><?php echo format_currency($cat['revenue']); ?></td>
                            <td class="num"><?php echo number_format(((float)$cat['revenue'] / $cat_sum) * 100, 1, ',', ' '); ?> %</td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- 8. Clients -->
        <section class="rpt-section">
            <h2 class="rpt-section-title">7. Meilleurs clients</h2>
            <div class="rpt-table-wrap">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Client</th>
                            <th>Téléphone</th>
                            <th class="ctr">Achats</th>
                            <th class="num">Total</th>
                            <th class="num">Panier moyen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($top_clients) === 0): ?>
                        <tr><td colspan="6" class="ctr text-muted">Aucun client identifié sur la période.</td></tr>
                        <?php else: foreach ($top_clients as $i => $c):
                            $avg = (int)$c['nb'] > 0 ? ((float)$c['spent'] / (int)$c['nb']) : 0;
                        ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($c['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($c['phone'] ?: '-'); ?></td>
                            <td class="ctr"><?php echo (int)$c['nb']; ?></td>
                            <td class="num"><?php echo format_currency($c['spent']); ?></td>
                            <td class="num"><?php echo format_currency($avg); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- 9. Stock -->
        <section class="rpt-section">
            <h2 class="rpt-section-title">8. Alertes de stock (<?php echo count($stock_alerts); ?>)</h2>
            <div class="rpt-table-wrap">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Code</th>
                            <th>Lot</th>
                            <th>Catégorie</th>
                            <th class="ctr">Seuil</th>
                            <th class="ctr">Stock</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($stock_alerts) === 0): ?>
                        <tr><td colspan="7" class="ctr text-muted">Aucun produit sous seuil d'alerte.</td></tr>
                        <?php else: foreach ($stock_alerts as $item): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($item['code'] ?? '-'); ?></td>
                            <td><?php echo !empty($item['lot_number']) ? htmlspecialchars($item['lot_number']) : '-'; ?></td>
                            <td><?php echo htmlspecialchars($item['cat_name'] ?? 'N/A'); ?></td>
                            <td class="ctr"><?php echo (int)$item['alert_threshold']; ?></td>
                            <td class="ctr"><strong><?php echo (int)$item['qty']; ?></strong></td>
                            <td><?php echo (int)$item['qty'] <= 0 ? 'Rupture' : 'Stock faible'; ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- 10. Péremption -->
        <section class="rpt-section">
            <h2 class="rpt-section-title">9. Produits à péremption proche (90 jours)</h2>
            <div class="rpt-table-wrap">
                <table class="rpt-table">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Code</th>
                            <th>Lot</th>
                            <th class="ctr">Stock</th>
                            <th>Expiration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($expiring) === 0): ?>
                        <tr><td colspan="5" class="ctr text-muted">Aucun produit à risque de péremption sous 90 jours.</td></tr>
                        <?php else: foreach ($expiring as $e): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($e['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($e['code'] ?? '-'); ?></td>
                            <td><?php echo !empty($e['lot_number']) ? htmlspecialchars($e['lot_number']) : '-'; ?></td>
                            <td class="ctr"><?php echo (int)$e['qty']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($e['exp_date'])); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <footer class="rpt-foot">
        <strong>Note :</strong> Les médicaments vendus ne sont ni repris ni échangés.
        Ce rapport est généré automatiquement à partir des données du système PHARMACIE BLESSING
        (ventes, factures, lots, achats, stocks et clients). Il constitue un document d'aide à la décision.
        <div class="rpt-signs">
            <div class="rpt-sign">
                <div class="rpt-sign-line">Établi par</div>
            </div>
            <div class="rpt-sign">
                <div class="rpt-sign-line">Visa direction / responsable</div>
            </div>
        </div>
    </footer>
</article>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var liveInput = document.getElementById('liveTraceSearch');
    var countEl = document.getElementById('liveTraceCount');
    if (!liveInput) return;

    function applyLiveFilter() {
        var q = (liveInput.value || '').trim().toLowerCase();
        var rows = document.querySelectorAll('#fullReportSheet .rpt-table tbody tr');
        var visible = 0;
        var hits = 0;

        rows.forEach(function (row) {
            var text = (row.textContent || '').toLowerCase();
            var emptyMsg = row.querySelector('.text-muted') && row.children.length <= 1;
            if (!q) {
                row.classList.remove('trace-hit', 'trace-hide');
                if (!emptyMsg) visible++;
                return;
            }
            if (emptyMsg) {
                row.classList.add('trace-hide');
                row.classList.remove('trace-hit');
                return;
            }
            if (text.indexOf(q) !== -1) {
                row.classList.add('trace-hit');
                row.classList.remove('trace-hide');
                visible++;
                hits++;
            } else {
                row.classList.add('trace-hide');
                row.classList.remove('trace-hit');
            }
        });

        if (countEl) {
            countEl.textContent = q ? (hits + ' résultat(s)') : '—';
        }
    }

    liveInput.addEventListener('input', applyLiveFilter);
});
</script>

<?php require_once '../../includes/footer.php'; ?>
