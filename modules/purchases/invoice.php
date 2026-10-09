<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) {
    redirect('../../index.php');
}
authorize(['Super Admin', 'Admin', 'Gérant']);

$business = get_business_profile($pdo);

if (!isset($_GET['id'])) {
    die("ID d'achat manquant.");
}

$purchase_id = (int)$_GET['id'];

// Get purchase details
$stmt = $pdo->prepare("SELECT p.*, s.name as supplier_name, s.phone as supplier_phone, s.address as supplier_address, u.full_name as user_name 
                       FROM purchases p 
                       LEFT JOIN suppliers s ON p.supplier_id = s.id 
                       LEFT JOIN users u ON p.created_by = u.id 
                       WHERE p.id = ?");
$stmt->execute([$purchase_id]);
$purchase = $stmt->fetch();

if (!$purchase) {
    die("Achat introuvable.");
}

// Get purchase items
$stmt_items = $pdo->prepare("SELECT pd.*, pr.name as product_name, pr.code as product_code
                             FROM purchase_details pd 
                             JOIN products pr ON pd.product_id = pr.id 
                             WHERE pd.purchase_id = ?");
$stmt_items->execute([$purchase_id]);
$items = $stmt_items->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bordereau d'Achat N° ACH-<?php echo str_pad($purchase['id'], 5, '0', STR_PAD_LEFT); ?></title>
    <!-- Bootstrap CSS for basic grid structure -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #e2e8f0;
            color: #334155;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .invoice-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
            max-width: 850px;
            margin: 30px auto;
            padding: 40px 50px;
        }

        .invoice-header {
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 25px;
            margin-bottom: 30px;
        }

        .logo-img {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #0ea5e9;
            animation: logo-clock-spin 12s linear infinite;
            transform-origin: center center;
        }
        @keyframes logo-clock-spin {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }
        @media (prefers-reduced-motion: reduce), print {
            .logo-img { animation: none !important; }
        }

        .company-title {
            font-weight: 800;
            color: #0f172a;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
        }

        .company-subtitle {
            font-size: 0.8rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .invoice-title {
            font-size: 1.8rem;
            font-weight: 850;
            color: #1d4ed8;
            text-align: right;
            line-height: 1.1;
        }

        .meta-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #64748b;
        }

        .meta-val {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0f172a;
        }

        .section-title {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #1d4ed8;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 6px;
            margin-bottom: 12px;
        }

        .party-details {
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .table-invoice th {
            background-color: #f8fafc !important;
            color: #475569;
            font-weight: 705;
            font-size: 0.85rem;
            text-transform: uppercase;
            border-top: none;
            border-bottom: 2px solid #e2e8f0;
            padding: 12px 10px;
        }

        .table-invoice td {
            padding: 14px 10px;
            vertical-align: middle;
            font-size: 0.9rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .totals-box {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .totals-row:last-child {
            margin-bottom: 0;
        }

        .grand-total {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0f172a;
            border-top: 1px solid #cbd5e1;
            padding-top: 10px;
            margin-top: 10px;
        }

        .invoice-footer {
            margin-top: 50px;
            border-top: 1px solid #f1f5f9;
            padding-top: 25px;
            font-size: 0.8rem;
            color: #64748b;
        }

        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            width: 250px;
            text-align: center;
        }

        .signature-line {
            border-top: 1.5px dashed #cbd5e1;
            margin-top: 55px;
            padding-top: 5px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #475569;
        }

        /* Screen only action bar */
        .action-bar {
            background-color: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 0;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        /* Print overrides */
        @media print {
            body {
                background-color: #ffffff;
                color: #000;
            }
            .invoice-card {
                border: none;
                box-shadow: none;
                padding: 0;
                margin: 0;
                max-width: 100%;
            }
            .action-bar {
                display: none !important;
            }
            .totals-box {
                background-color: transparent !important;
                border: 1px solid #000;
            }
            .table-invoice th {
                background-color: #f1f5f9 !important;
            }
        }
    </style>
</head>
<body onload="window.print();">

<!-- Live Action Bar (Hidden in prints) -->
<div class="action-bar d-print-none">
    <div class="container max-width-850 d-flex justify-content-between align-items-center" style="max-width: 850px;">
        <div>
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Retour à l'historique
            </a>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-primary fw-bold px-3" onclick="window.print();">
                <i class="fa-solid fa-print me-1"></i> Imprimer
            </button>
            <button class="btn btn-sm btn-danger px-3" onclick="window.close();">
                <i class="fa-solid fa-times me-1"></i> Fermer
            </button>
        </div>
    </div>
</div>

<!-- Main A4 Invoice Container -->
<div class="container">
    <div class="invoice-card">
        
        <!-- Header -->
        <div class="row invoice-header align-items-center">
            <div class="col-7">
                <div class="d-flex align-items-center gap-3">
                    <img src="../../assets/img/pha.jpeg" alt="Logo" class="logo-img">
                    <div>
                        <div class="company-title"><?php echo htmlspecialchars($business['name']); ?></div>
                        <div class="company-subtitle"><?php echo htmlspecialchars($business['subtitle']); ?></div>
                    </div>
                </div>
                <div class="party-details text-muted mt-3 small">
                    <i class="fa-solid fa-location-dot me-1"></i> <?php echo htmlspecialchars($business['address']); ?><br>
                    <i class="fa-solid fa-phone me-1"></i> Tél: <?php echo htmlspecialchars($business['phone']); ?> | Email: <?php echo htmlspecialchars($business['email']); ?><br>
                    <?php echo htmlspecialchars($business['legal_ids']); ?>
                </div>
            </div>
            
            <div class="col-5">
                <div class="invoice-title">BON D'ACHAT (ENTRÉE)</div>
                <div class="text-end mt-3">
                    <div class="mb-1"><span class="meta-label">Bordereau N° :</span> <span class="meta-val">ACH-<?php echo str_pad($purchase['id'], 5, '0', STR_PAD_LEFT); ?></span></div>
                    <div class="mb-1"><span class="meta-label">Date :</span> <span class="meta-val"><?php echo date('d/m/Y', strtotime($purchase['purchase_date'])); ?></span></div>
                    <div><span class="meta-label">Type :</span> <span class="meta-val text-success">Entrée en stock</span></div>
                </div>
            </div>
        </div>

        <!-- Parties details -->
        <div class="row mb-4">
            <div class="col-6">
                <div class="section-title">Destinataire (Espace Réception)</div>
                <div class="party-details fw-bold text-dark"><?php echo htmlspecialchars($business['name']); ?></div>
                <div class="party-details text-muted">
                    Réceptionné par : <?php echo htmlspecialchars($purchase['user_name'] ?? 'Gérant Principal'); ?><br>
                    <?php echo htmlspecialchars($business['address']); ?>
                </div>
            </div>
            <div class="col-6">
                <div class="section-title font-success">Fournisseur</div>
                <div class="party-details fw-bold text-dark">
                    <?php echo htmlspecialchars($purchase['supplier_name'] ?? 'Non spécifié'); ?>
                </div>
                <div class="party-details text-muted">
                    <?php if (!empty($purchase['supplier_phone'])): ?>
                        Tél : <?php echo htmlspecialchars($purchase['supplier_phone']); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($purchase['supplier_address'])): ?>
                        Adresse : <?php echo htmlspecialchars($purchase['supplier_address']); ?><br>
                    <?php else: ?>
                        Adresse: RDC
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Invoice Table -->
        <div class="table-responsive mb-4">
            <table class="table table-invoice m-0">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 15%;">Code</th>
                        <th style="width: 45%;">Designation</th>
                        <th style="width: 10%;" class="text-center">Qté</th>
                        <th style="width: 12%;" class="text-end">P.A. Unit.</th>
                        <th style="width: 13%;" class="text-end">Montant Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $index => $item): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td class="text-muted"><?php echo htmlspecialchars($item['product_code'] ?? 'N/A'); ?></td>
                        <td class="fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td class="text-center"><?php echo $item['qty']; ?></td>
                        <td class="text-end"><?php echo number_format($item['buy_price'], 0, ',', ' '); ?> FC</td>
                        <td class="text-end fw-bold"><?php echo number_format($item['buy_price'] * $item['qty'], 0, ',', ' '); ?> FC</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals & Payment Summary -->
        <div class="row align-items-start">
            <div class="col-6 party-details">
                <div class="mb-4">
                    <span class="fw-bold text-dark">Note de contrôle de stock :</span><br>
                    <span class="text-muted small">Les produits ci-dessus ont été réceptionnés et inspectés en bon état conformes aux normes et validités imposées par la politique interne de distribution.</span>
                </div>
            </div>
            
            <div class="col-6">
                <div class="totals-box">
                    <div class="totals-row grand-total" style="border-top: none; padding-top: 0; margin-top: 0;">
                        <span>TOTAL ACHAT (HT)</span>
                        <span class="text-success"><?php echo number_format($purchase['total_amount'], 0, ',', ' '); ?> FC</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line">Pour le Fournisseur / Livreur</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Pour la Direction Réceptrice</div>
            </div>
        </div>

        <!-- Invoice footer -->
        <div class="invoice-footer text-center">
            <p class="mb-1"><strong><?php echo htmlspecialchars($business['name']); ?></strong> - Agréée par le Ministère de la Santé de la République Démocratique du Congo.</p>
            <p class="mb-0">Validation informatisée des entrées Gérant.</p>
        </div>

    </div>
</div>

</body>
</html>
