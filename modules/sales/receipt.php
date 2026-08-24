<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) {
    redirect('../../index.php');
}
authorize(['Super Admin', 'Admin', 'Caissier']);

$business = get_business_profile($pdo);

if (!isset($_GET['id'])) {
    die("ID de vente manquant.");
}

$sale_id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT s.*, c.name as client_name, c.phone as client_phone, c.address as client_address, u.full_name as user_name
                       FROM sales s
                       LEFT JOIN clients c ON s.client_id = c.id
                       LEFT JOIN users u ON s.user_id = u.id
                       WHERE s.id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();

if (!$sale) {
    die("Vente introuvable.");
}

$stmt_items = $pdo->prepare("SELECT sd.*, p.name as product_name, p.code as product_code
                             FROM sale_details sd
                             JOIN products p ON sd.product_id = p.id
                             WHERE sd.sale_id = ?");
$stmt_items->execute([$sale_id]);
$items = $stmt_items->fetchAll();

$subtotal = (float)$sale['total_amount'];
$net_total = isset($sale['final_amount']) ? (float)$sale['final_amount'] : ((float)$sale['total_amount'] - (float)$sale['discount']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture #FAC-<?php echo str_pad($sale['id'], 5, '0', STR_PAD_LEFT); ?> — PHARMACIE BLESSING</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ─── Action Bar (screen only) ─── */
        .action-bar {
            background: #1d4ed8;
            padding: 12px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .action-bar span { color: #fff; font-weight: 600; font-size: 0.95rem; }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-print { background: #fff; color: #1d4ed8; }
        .btn-print:hover { background: #e0f2fe; }
        .btn-back { background: rgba(255,255,255,0.2); color: #fff; border: 1px solid rgba(255,255,255,0.4); }
        .btn-back:hover { background: rgba(255,255,255,0.3); }

        /* ─── Invoice Sheet ─── */
        .invoice-wrapper {
            max-width: 860px;
            margin: 30px auto;
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        /* ─── Top Banner ─── */
        .invoice-top {
            background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 100%);
            padding: 36px 50px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .brand-area { display: flex; align-items: center; gap: 16px; }
        .brand-logo {
            width: 65px; height: 65px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.6);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            animation: logo-clock-spin 12s linear infinite;
            transform-origin: center center;
        }
        @keyframes logo-clock-spin {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }
        @media (prefers-reduced-motion: reduce), print {
            .brand-logo { animation: none !important; }
        }
        .brand-name { color: #fff; font-size: 1.5rem; font-weight: 800; }
        .brand-sub { color: rgba(255,255,255,0.8); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 1px; }
        .brand-contacts { margin-top: 6px; color: rgba(255,255,255,0.8); font-size: 0.78rem; line-height: 1.6; }

        .invoice-ref-box { text-align: right; }
        .invoice-label { color: rgba(255,255,255,0.7); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 1px; }
        .invoice-number { color: #fff; font-size: 2rem; font-weight: 800; line-height: 1; }
        .invoice-date { color: rgba(255,255,255,0.85); font-size: 0.88rem; margin-top: 4px; }
        .invoice-status {
            display: inline-block;
            background: rgba(255,255,255,0.25);
            color: #fff;
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 0.78rem;
            font-weight: 600;
            margin-top: 8px;
            border: 1px solid rgba(255,255,255,0.4);
        }

        /* ─── Parties ─── */
        .parties {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .party { padding: 24px 50px; }
        .party + .party { border-left: 1px solid #f1f5f9; }
        .party-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #1d4ed8;
            margin-bottom: 8px;
        }
        .party-name { font-size: 1rem; font-weight: 700; color: #0f172a; }
        .party-detail { font-size: 0.85rem; color: #64748b; line-height: 1.7; margin-top: 4px; }

        /* ─── Table ─── */
        .table-wrap { padding: 0 50px 30px; }
        .invoice-table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        .invoice-table thead tr {
            background: #f8fafc;
            border-top: 2px solid #e2e8f0;
            border-bottom: 2px solid #e2e8f0;
        }
        .invoice-table th {
            padding: 12px 14px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #475569;
            text-align: left;
        }
        .invoice-table td {
            padding: 16px 14px;
            font-size: 0.9rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .invoice-table tbody tr:last-child td { border-bottom: none; }
        .invoice-table .product-name { font-weight: 600; color: #0f172a; }
        .invoice-table .product-code { font-size: 0.78rem; color: #94a3b8; margin-top: 2px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .col-w-small { width: 60px; }
        .col-w-med { width: 100px; }
        .col-w-big { width: 130px; }

        /* ─── Totals + Footer ─── */
        .invoice-bottom {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 20px 50px 30px;
            border-top: 1px solid #f1f5f9;
            background: #fafbfc;
            gap: 30px;
        }
        .invoice-note { flex: 1; font-size: 0.82rem; color: #64748b; line-height: 1.6; }
        .invoice-note strong { color: #1e293b; }

        .totals-block { min-width: 280px; }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 0.88rem;
            border-bottom: 1px solid #f1f5f9;
            color: #475569;
        }
        .totals-row:last-child { border-bottom: none; }
        .totals-row .label { font-weight: 500; }
        .totals-row .value { font-weight: 600; }
        .totals-grand {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            background: #1d4ed8;
            border-radius: 10px;
            margin-top: 12px;
        }
        .totals-grand .label { color: rgba(255,255,255,0.85); font-size: 0.85rem; font-weight: 600; }
        .totals-grand .value { color: #fff; font-size: 1.3rem; font-weight: 800; }

        /* ─── Signatures ─── */
        .signatures {
            display: flex;
            justify-content: space-between;
            padding: 30px 50px 20px;
            border-top: 1px solid #f1f5f9;
        }
        .sig-box { text-align: center; width: 220px; }
        .sig-line {
            border-top: 1.5px dashed #cbd5e1;
            padding-top: 8px;
            margin-top: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #475569;
        }

        /* ─── Footer banner ─── */
        .invoice-footer-banner {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 16px 50px;
            text-align: center;
            font-size: 0.8rem;
            color: #64748b;
        }
        .invoice-footer-banner strong { color: #1d4ed8; }

        /* ─── Print media ─── */
        @media print {
            body { background: #fff; }
            .action-bar { display: none !important; }
            .invoice-wrapper {
                margin: 0;
                box-shadow: none;
                border-radius: 0;
            }
            .invoice-top {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .totals-grand {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        @media (max-width: 600px) {
            .invoice-top { flex-direction: column; gap: 16px; padding: 24px; }
            .invoice-ref-box { text-align: left; }
            .parties { grid-template-columns: 1fr; }
            .party + .party { border-left: none; border-top: 1px solid #f1f5f9; }
            .party { padding: 16px 20px; }
            .table-wrap { padding: 0 16px 20px; }
            .invoice-bottom { flex-direction: column; padding: 16px 20px; }
            .signatures { padding: 20px; gap: 10px; }
            .invoice-footer-banner { padding: 12px 20px; }
        }
    </style>
</head>
<body onload="window.print();">

    <!-- Action Bar -->
    <div class="action-bar d-print-none" style="display:flex;">
        <a href="index.php" class="btn-action btn-back">&#8592; Retour</a>
        <span>Facture #FAC-<?php echo str_pad($sale['id'], 5, '0', STR_PAD_LEFT); ?></span>
        <button onclick="window.print();" class="btn-action btn-print">&#128438; Imprimer</button>
    </div>

    <div class="invoice-wrapper">

        <!-- Top Banner Header -->
        <div class="invoice-top">
            <div class="brand-area">
                <img src="../../assets/img/pha.jpeg" alt="Logo" class="brand-logo">
                <div>
                    <div class="brand-name"><?php echo htmlspecialchars($business['name']); ?></div>
                    <div class="brand-sub"><?php echo htmlspecialchars($business['subtitle']); ?></div>
                    <div class="brand-contacts">
                        <?php echo htmlspecialchars($business['address']); ?><br>
                        Tél: <?php echo htmlspecialchars($business['phone']); ?> &nbsp;&bull;&nbsp; <?php echo htmlspecialchars($business['email']); ?><br>
                        <?php echo htmlspecialchars($business['legal_ids']); ?>
                    </div>
                </div>
            </div>
            <div class="invoice-ref-box">
                <div class="invoice-label">Facture</div>
                <div class="invoice-number">FAC-<?php echo str_pad($sale['id'], 5, '0', STR_PAD_LEFT); ?></div>
                <div class="invoice-date"><?php echo date('d/m/Y à H:i', strtotime($sale['sale_date'])); ?></div>
                <span class="invoice-status">&#10003; Payé</span>
            </div>
        </div>

        <!-- Parties -->
        <div class="parties">
            <div class="party">
                <div class="party-label">Émetteur</div>
                <div class="party-name"><?php echo htmlspecialchars($business['name']); ?></div>
                <div class="party-detail">
                    Caissier : <?php echo htmlspecialchars($sale['user_name'] ?? 'Caissier Principal'); ?><br>
                    <?php echo htmlspecialchars($business['address']); ?>
                </div>
            </div>
            <div class="party">
                <div class="party-label">Facturé à</div>
                <div class="party-name"><?php echo htmlspecialchars($sale['client_name'] ?? 'Client de passage'); ?></div>
                <div class="party-detail">
                    <?php if (!empty($sale['client_phone'])): ?>
                        Tél : <?php echo htmlspecialchars($sale['client_phone']); ?><br>
                    <?php endif; ?>
                    <?php echo !empty($sale['client_address']) ? htmlspecialchars($sale['client_address']) : 'Kinshasa, RDC'; ?>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="table-wrap">
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th class="col-w-small">#</th>
                        <th>Désignation</th>
                        <th class="text-center col-w-med">Qté</th>
                        <th class="text-right col-w-big">Prix Unit.</th>
                        <th class="text-right col-w-big">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $item):
                        $unit_price = (float)($item['unit_price'] ?? $item['price'] ?? 0);
                        $line_total = $unit_price * $item['qty'];
                    ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td>
                            <div class="product-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                            <?php if (!empty($item['product_code'])): ?>
                                <div class="product-code">Code: <?php echo htmlspecialchars($item['product_code']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo $item['qty']; ?></td>
                        <td class="text-right"><?php echo number_format($unit_price, 0, ',', ' '); ?> FC</td>
                        <td class="text-right" style="font-weight:700;"><?php echo number_format($line_total, 0, ',', ' '); ?> FC</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Bottom: Note + Totals -->
        <div class="invoice-bottom">
            <div class="invoice-note">
                <strong>Arrêtée à la somme de :</strong><br>
                <span style="color:#1d4ed8;font-weight:700;"><?php echo number_format($net_total, 0, ',', ' '); ?> Francs Congolais</span><br><br>
                <strong>Conditions :</strong><br>
                Les médicaments vendus ne sont ni repris ni échangés.<br>
                Nous vous remercions de votre confiance.<br>
                Vérifiez la validité des produits avant toute prise.
            </div>
            <div class="totals-block">
                <div class="totals-row">
                    <span class="label">Sous-total (HT)</span>
                    <span class="value"><?php echo number_format($subtotal, 0, ',', ' '); ?> FC</span>
                </div>
                <?php if ($sale['discount'] > 0): ?>
                <div class="totals-row">
                    <span class="label">Remise</span>
                    <span class="value" style="color:#ef4444;">-<?php echo number_format($sale['discount'], 0, ',', ' '); ?> FC</span>
                </div>
                <?php endif; ?>
                <div class="totals-grand">
                    <span class="label">NET À PAYER</span>
                    <span class="value"><?php echo number_format($net_total, 0, ',', ' '); ?> FC</span>
                </div>
            </div>
        </div>

        <!-- Signatures -->
        <div class="signatures">
            <div class="sig-box">
                <div class="sig-line">Signature du Client / Réceptionnaire</div>
            </div>
            <div class="sig-box">
                <div class="sig-line">Cachet &amp; Signature de la Direction</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="invoice-footer-banner">
            <strong><?php echo htmlspecialchars($business['name']); ?></strong> — Agréée par le Ministère de la Santé, RDC.
            &nbsp;&bull;&nbsp; Merci pour votre confiance !
        </div>

    </div>

</body>
</html>
