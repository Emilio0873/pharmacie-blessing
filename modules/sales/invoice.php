<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) {
    redirect('../../index.php');
}
authorize(['Super Admin', 'Admin', 'Caissier']);

if (!isset($_GET['id'])) {
    die('ID de vente manquant.');
}

$sale_id = (int)$_GET['id'];
$download_pdf = isset($_GET['download']) && $_GET['download'] === 'pdf';

ensure_invoicing_tables($pdo);
$business = get_business_profile($pdo);

$stmt = $pdo->prepare("SELECT s.*, c.name as client_name, c.phone as client_phone, c.address as client_address,
                              u.full_name as user_name,
                              inv.id as invoice_id, inv.invoice_number, inv.subtotal, inv.discount as invoice_discount,
                              inv.tax_rate, inv.tax_amount, inv.total_amount as invoice_total,
                              inv.legal_note, inv.created_at as invoice_created_at
                       FROM sales s
                       LEFT JOIN clients c ON s.client_id = c.id
                       LEFT JOIN users u ON s.user_id = u.id
                       LEFT JOIN invoices inv ON inv.sale_id = s.id
                       WHERE s.id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();

if (!$sale) {
    die('Vente introuvable.');
}

$invoice_number = !empty($sale['invoice_number']) ? $sale['invoice_number'] : format_invoice_number($sale['id']);
$subtotal = isset($sale['subtotal']) ? (float)$sale['subtotal'] : (float)$sale['total_amount'];
$discount = isset($sale['invoice_discount']) ? (float)$sale['invoice_discount'] : (float)$sale['discount'];
$tax_rate = isset($sale['tax_rate']) ? (float)$sale['tax_rate'] : 0;
$tax_amount = isset($sale['tax_amount']) ? (float)$sale['tax_amount'] : 0;
$total_ttc = isset($sale['invoice_total']) ? (float)$sale['invoice_total'] : (float)($sale['final_amount'] ?? ($subtotal - $discount));
$legal_note_default = 'Les médicaments vendus ne sont ni repris ni échangés.';
$legal_note = !empty($sale['legal_note']) ? $sale['legal_note'] : $legal_note_default;
// Normalize legacy / incorrect wording from older sales
if (preg_match('/médicaments vendus ni repris/iu', $legal_note) || trim($legal_note) === 'Médicaments vendus ni repris ni échangés.') {
    $legal_note = $legal_note_default;
}
$invoice_date = !empty($sale['invoice_created_at']) ? $sale['invoice_created_at'] : $sale['sale_date'];
$amount_words = ucfirst(amount_to_words_fr($total_ttc));
$sale_ref = '#' . str_pad((int)$sale['id'], 6, '0', STR_PAD_LEFT);

$items = [];
if (!empty($sale['invoice_id'])) {
    $stmt_items = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC");
    $stmt_items->execute([$sale['invoice_id']]);
    $items = $stmt_items->fetchAll();
}

if (empty($items)) {
    $stmt_items = $pdo->prepare("SELECT sd.product_id,
                                        p.name as product_name,
                                        p.code as product_code,
                                        sd.qty,
                                        sd.unit_price,
                                        (sd.qty * sd.unit_price) as line_total,
                                        0 as line_discount,
                                        0 as line_tax
                                 FROM sale_details sd
                                 JOIN products p ON p.id = sd.product_id
                                 WHERE sd.sale_id = ?");
    $stmt_items->execute([$sale_id]);
    $items = $stmt_items->fetchAll();
}

function inv_money($amount) {
    return number_format((float)$amount, 0, ',', ' ') . ' FC';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture <?php echo htmlspecialchars($invoice_number); ?> - PHARMACIE BLESSING</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --brand: #1d4ed8;
            --brand-dark: #1e3a8a;
            --paper: #ffffff;
            --soft: #f8fafc;
            --pad: 32px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #e2e8f0;
            color: var(--ink);
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            line-height: 1.45;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .action-bar {
            position: sticky;
            top: 0;
            z-index: 30;
            background: linear-gradient(90deg, var(--brand-dark), var(--brand));
            padding: 12px 16px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18);
        }

        .action-bar .inv-ref {
            color: #fff;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .invoice-wrap {
            width: min(960px, calc(100% - 32px));
            margin: 24px auto;
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12);
            overflow: hidden;
            position: relative;
        }

        /* Filigrane FACTURE PAYÉE */
        .paid-watermark {
            position: absolute;
            inset: 0;
            z-index: 5;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .paid-watermark-stamp {
            transform: rotate(-28deg);
            border: 4px solid rgba(22, 163, 74, 0.55);
            color: rgba(22, 163, 74, 0.42);
            font-size: clamp(2.2rem, 7vw, 3.6rem);
            font-weight: 900;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            padding: 0.35em 0.7em;
            border-radius: 12px;
            white-space: nowrap;
            user-select: none;
            line-height: 1;
            text-shadow: 0 2px 0 rgba(255, 255, 255, 0.35);
            box-shadow: inset 0 0 0 2px rgba(22, 163, 74, 0.18);
            background: rgba(240, 253, 244, 0.18);
        }

        .paid-watermark-stamp small {
            display: block;
            margin-top: 0.35em;
            font-size: 0.28em;
            letter-spacing: 0.18em;
            font-weight: 700;
            text-align: center;
            opacity: 0.9;
        }

        @keyframes paid-stamp-in {
            0% {
                opacity: 0;
                transform: rotate(-28deg) scale(1.35);
            }
            60% {
                opacity: 1;
                transform: rotate(-28deg) scale(0.96);
            }
            100% {
                opacity: 1;
                transform: rotate(-28deg) scale(1);
            }
        }

        .paid-watermark.reveal .paid-watermark-stamp {
            animation: paid-stamp-in 0.85s cubic-bezier(0.2, 0.8, 0.2, 1) both;
        }

        .invoice-wrap > *:not(.paid-watermark) {
            position: relative;
            z-index: 1;
        }

        .invoice-head {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 24px;
            align-items: start;
            padding: var(--pad);
            background: linear-gradient(145deg, #eff6ff 0%, #dbeafe 100%);
            border-bottom: 1px solid #bfdbfe;
        }

        .brand-row {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 12px;
        }

        .logo {
            width: 64px;
            height: 64px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid var(--brand);
            flex-shrink: 0;
        }

        @keyframes logo-clock-spin {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }
        .logo-clock {
            animation: logo-clock-spin 12s linear infinite;
            transform-origin: center center;
        }
        @media (prefers-reduced-motion: reduce), print {
            .logo-clock { animation: none !important; }
        }

        .company {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--brand-dark);
            line-height: 1.15;
            margin: 0;
        }

        .subtitle {
            color: var(--muted);
            font-size: 0.86rem;
            margin-top: 2px;
        }

        .contact-block {
            color: var(--muted);
            font-size: 0.84rem;
            line-height: 1.55;
        }

        .invoice-meta {
            text-align: right;
            justify-self: end;
            min-width: 240px;
        }

        .invoice-title {
            margin: 0 0 12px;
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--brand-dark);
            letter-spacing: 0.04em;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 6px 12px;
            align-items: baseline;
            text-align: left;
            margin-left: auto;
            width: fit-content;
            max-width: 100%;
        }

        .meta-grid .label {
            color: var(--muted);
            font-size: 0.82rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .meta-grid .value {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--ink);
            text-align: right;
        }

        .parties {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            padding: 22px var(--pad);
            border-bottom: 1px solid var(--line);
        }

        .party-card {
            background: var(--soft);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 14px 16px;
            min-height: 100%;
        }

        .section-title {
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            color: var(--brand-dark);
            text-transform: uppercase;
            margin: 0 0 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid var(--line);
        }

        .party-name {
            font-weight: 700;
            font-size: 0.98rem;
            margin-bottom: 4px;
            color: var(--ink);
        }

        .party-details {
            color: var(--muted);
            font-size: 0.84rem;
            line-height: 1.55;
        }

        .table-wrap {
            padding: 8px var(--pad) 8px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .invoice-table {
            width: 100%;
            min-width: 640px;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 0;
        }

        .invoice-table th {
            background: #f1f5f9;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #334155;
            border-bottom: 2px solid var(--line);
            padding: 12px 10px;
            white-space: nowrap;
            vertical-align: middle;
        }

        .invoice-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            font-size: 0.88rem;
            word-wrap: break-word;
        }

        .invoice-table tbody tr:last-child td {
            border-bottom: 1px solid var(--line);
        }

        .col-num { width: 44px; text-align: center; }
        .col-code { width: 90px; }
        .col-name { width: auto; }
        .col-qty { width: 56px; text-align: center; }
        .col-money { width: 100px; text-align: right; white-space: nowrap; }
        .col-total { width: 110px; text-align: right; white-space: nowrap; font-weight: 700; }

        .product-name {
            font-weight: 600;
            color: var(--ink);
        }

        .summary {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 24px;
            align-items: start;
            padding: 16px var(--pad) 28px;
        }

        .amount-words {
            background: var(--soft);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 14px 16px;
        }

        .amount-words .label {
            display: block;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--brand-dark);
            margin-bottom: 6px;
        }

        .amount-words .text {
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.5;
            margin: 0;
        }

        .totals-card {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--soft);
            padding: 6px 0;
            overflow: hidden;
        }

        .totals-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 16px;
            align-items: center;
            padding: 9px 16px;
            font-size: 0.9rem;
            border-bottom: 1px dashed #dbeafe;
        }

        .totals-row:last-of-type {
            border-bottom: none;
        }

        .totals-row .lbl { color: var(--muted); font-weight: 500; }
        .totals-row .val { font-weight: 700; text-align: right; white-space: nowrap; }

        .total-ttc {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 16px;
            align-items: center;
            margin: 6px 8px 8px;
            border-radius: 10px;
            padding: 12px 14px;
            background: linear-gradient(90deg, var(--brand-dark), var(--brand));
            color: #fff;
            font-size: 1.02rem;
            font-weight: 800;
        }

        .total-ttc .val {
            text-align: right;
            white-space: nowrap;
        }

        .footer-zone {
            border-top: 1px solid var(--line);
            background: var(--soft);
            padding: 20px var(--pad) 28px;
        }

        .legal-note {
            color: var(--muted);
            font-size: 0.84rem;
            line-height: 1.55;
            margin-bottom: 22px;
        }

        .signature-zone {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
        }

        .signature {
            text-align: center;
            max-width: 280px;
        }

        .signature:last-child {
            justify-self: end;
        }

        .signature-line {
            margin-top: 52px;
            border-top: 1px dashed #94a3b8;
            padding-top: 8px;
            font-size: 0.82rem;
            color: #475569;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            :root { --pad: 18px; }

            .invoice-wrap {
                width: calc(100% - 16px);
                margin: 12px auto;
                border-radius: 12px;
            }

            .invoice-head {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .invoice-meta {
                text-align: left;
                justify-self: stretch;
                min-width: 0;
                width: 100%;
                padding-top: 14px;
                border-top: 1px solid #bfdbfe;
            }

            .invoice-title {
                font-size: 1.4rem;
                margin-bottom: 10px;
            }

            .meta-grid {
                margin-left: 0;
                width: 100%;
            }

            .parties {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .summary {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .signature-zone {
                grid-template-columns: 1fr;
                gap: 28px;
            }

            .signature,
            .signature:last-child {
                max-width: none;
                width: 100%;
                justify-self: stretch;
            }

            .action-bar .btn span {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .brand-row { gap: 10px; }
            .logo { width: 52px; height: 52px; }
            .company { font-size: 1.15rem; }
            .invoice-table { min-width: 560px; }
            .col-code { width: 70px; }
            .col-money { width: 88px; }
            .col-total { width: 96px; }
        }

@media (max-width: 575.98px) {
            .paid-watermark-stamp {
                font-size: 1.6rem;
                letter-spacing: 0.06em;
                border-width: 3px;
                padding: 0.3em 0.5em;
            }
        }

        @media print {
            body { background: #fff; }
            .action-bar { display: none !important; }
            .invoice-wrap {
                width: 100%;
                margin: 0;
                border: none;
                border-radius: 0;
                box-shadow: none;
            }
            .table-wrap { overflow: visible; }
            .invoice-table { min-width: 0; }
            .parties,
            .summary,
            .signature-zone,
            .invoice-head {
                break-inside: avoid;
            }
            .paid-watermark-stamp {
                color: rgba(22, 163, 74, 0.38) !important;
                border-color: rgba(22, 163, 74, 0.5) !important;
                background: transparent !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                animation: none !important;
            }
        }
    </style>
</head>
<body>
<?php
// Toute facture issue d'une vente POS validée est considérée comme payée.
$is_paid = true;
$reveal_paid = isset($_GET['paid']) && $_GET['paid'] === '1';
?>
<div class="action-bar d-print-none">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <a href="index.php" class="btn btn-sm btn-light">
            <i class="fa-solid fa-arrow-left me-1"></i><span>Retour</span>
        </a>
        <div class="inv-ref"><?php echo htmlspecialchars($invoice_number); ?></div>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-success px-3 py-2"><i class="fa-solid fa-check-circle me-1"></i> Payée</span>
            <button class="btn btn-sm btn-light" onclick="window.print();">
                <i class="fa-solid fa-print me-1"></i><span>Réimprimer</span>
            </button>
            <button class="btn btn-sm btn-warning" onclick="downloadInvoicePdf();">
                <i class="fa-solid fa-download me-1"></i><span>PDF</span>
            </button>
        </div>
    </div>
</div>

<div id="invoiceSheet" class="invoice-wrap">
    <?php if ($is_paid): ?>
    <div class="paid-watermark<?php echo $reveal_paid ? ' reveal' : ''; ?>" aria-hidden="true">
        <div class="paid-watermark-stamp">
            Facture payée
            <small>Paiement validé</small>
        </div>
    </div>
    <?php endif; ?>
    <header class="invoice-head">
        <div class="brand-block">
            <div class="brand-row">
                <img src="../../assets/img/pha.jpeg" alt="Logo" class="logo logo-clock">
                <div>
                    <h1 class="company"><?php echo htmlspecialchars($business['name']); ?></h1>
                    <div class="subtitle"><?php echo htmlspecialchars($business['subtitle']); ?></div>
                </div>
            </div>
            <div class="contact-block">
                <?php echo htmlspecialchars($business['address']); ?><br>
                Tél: <?php echo htmlspecialchars($business['phone']); ?>
                &nbsp;|&nbsp; Email: <?php echo htmlspecialchars($business['email']); ?><br>
                <?php echo htmlspecialchars($business['legal_ids']); ?>
            </div>
        </div>

        <div class="invoice-meta">
            <h2 class="invoice-title">FACTURE</h2>
            <div class="meta-grid">
                <span class="label">N°</span>
                <span class="value"><?php echo htmlspecialchars($invoice_number); ?></span>
                <span class="label">Date</span>
                <span class="value"><?php echo date('d/m/Y H:i', strtotime($invoice_date)); ?></span>
                <span class="label">Statut</span>
                <span class="value" style="color:#16a34a;">PAYÉE</span>
                <span class="label">Réf. vente</span>
                <span class="value"><?php echo htmlspecialchars($sale_ref); ?></span>
            </div>
        </div>
    </header>

    <section class="parties">
        <div class="party-card">
            <h3 class="section-title">Émetteur</h3>
            <div class="party-name"><?php echo htmlspecialchars($business['name']); ?></div>
            <div class="party-details">
                Caissier: <?php echo htmlspecialchars($sale['user_name'] ?? 'N/A'); ?><br>
                <?php echo htmlspecialchars($business['address']); ?>
            </div>
        </div>
        <div class="party-card">
            <h3 class="section-title">Facturé à</h3>
            <div class="party-name"><?php echo htmlspecialchars($sale['client_name'] ?? 'Client de passage'); ?></div>
            <div class="party-details">
                <?php if (!empty($sale['client_phone'])): ?>
                    Tél: <?php echo htmlspecialchars($sale['client_phone']); ?><br>
                <?php endif; ?>
                <?php echo !empty($sale['client_address']) ? htmlspecialchars($sale['client_address']) : 'Adresse non renseignée'; ?>
            </div>
        </div>
    </section>

    <div class="table-wrap">
        <table class="invoice-table">
            <thead>
                <tr>
                    <th class="col-num">#</th>
                    <th class="col-code">Code</th>
                    <th class="col-name">Désignation</th>
                    <th class="col-qty">Qté</th>
                    <th class="col-money">P.U</th>
                    <th class="col-money">Remise</th>
                    <th class="col-money">TVA</th>
                    <th class="col-total">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr>
                    <td colspan="8" style="text-align:center;color:var(--muted);padding:28px;">Aucun article</td>
                </tr>
                <?php else: ?>
                <?php foreach ($items as $idx => $item):
                    $unit_price = (float)($item['unit_price'] ?? 0);
                    $line_discount = (float)($item['line_discount'] ?? 0);
                    $line_tax = (float)($item['line_tax'] ?? 0);
                    $line_total = isset($item['line_total']) ? (float)$item['line_total'] : ((float)$item['qty'] * $unit_price);
                ?>
                <tr>
                    <td class="col-num"><?php echo $idx + 1; ?></td>
                    <td class="col-code"><?php echo htmlspecialchars($item['product_code'] ?? 'N/A'); ?></td>
                    <td class="col-name"><span class="product-name"><?php echo htmlspecialchars($item['product_name'] ?? 'Produit'); ?></span></td>
                    <td class="col-qty"><?php echo (int)$item['qty']; ?></td>
                    <td class="col-money"><?php echo inv_money($unit_price); ?></td>
                    <td class="col-money"><?php echo inv_money($line_discount); ?></td>
                    <td class="col-money"><?php echo inv_money($line_tax); ?></td>
                    <td class="col-total"><?php echo inv_money($line_total); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <section class="summary">
        <div class="amount-words">
            <span class="label">Montant en lettres</span>
            <p class="text"><?php echo htmlspecialchars($amount_words); ?> uniquement.</p>
        </div>
        <div class="totals-card">
            <div class="totals-row">
                <span class="lbl">Sous-total (HT)</span>
                <span class="val"><?php echo inv_money($subtotal); ?></span>
            </div>
            <div class="totals-row">
                <span class="lbl">Remise</span>
                <span class="val">-<?php echo inv_money($discount); ?></span>
            </div>
            <div class="totals-row">
                <span class="lbl">TVA (<?php echo number_format($tax_rate, 2, ',', ' '); ?>%)</span>
                <span class="val"><?php echo inv_money($tax_amount); ?></span>
            </div>
            <div class="total-ttc">
                <span>Total à payer</span>
                <span class="val"><?php echo inv_money($total_ttc); ?></span>
            </div>
        </div>
    </section>

    <footer class="footer-zone">
        <div class="legal-note">
            <strong>Note légale :</strong> <?php echo htmlspecialchars($legal_note); ?><br>
            Nous vous remercions de votre confiance. Le présent document constitue une preuve de paiement.
        </div>
        <div class="signature-zone">
            <div class="signature">
                <div class="signature-line">Signature du client</div>
            </div>
            <div class="signature">
                <div class="signature-line">Signature et cachet de l'établissement</div>
            </div>
        </div>
    </footer>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadInvoicePdf() {
    const filename = '<?php echo addslashes($invoice_number); ?>.pdf';
    const element = document.getElementById('invoiceSheet');
    const options = {
        margin: [8, 8, 8, 8],
        filename: filename,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().set(options).from(element).save();
}

<?php if ($download_pdf): ?>
document.addEventListener('DOMContentLoaded', function() {
    downloadInvoicePdf();
});
<?php endif; ?>
</script>
</body>
</html>
