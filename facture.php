<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

$token = trim($_GET['t'] ?? '');
if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
    http_response_code(404);
    die('Lien de facture invalide.');
}

ensure_invoicing_tables($pdo);
ensure_invoice_share_column($pdo);
$business = get_business_profile($pdo);

$stmt = $pdo->prepare("SELECT s.*, c.name as client_name, c.phone as client_phone, c.address as client_address, c.email as client_email,
                              u.full_name as user_name,
                              inv.id as invoice_id, inv.invoice_number, inv.subtotal, inv.discount as invoice_discount,
                              inv.tax_rate, inv.tax_amount, inv.total_amount as invoice_total,
                              inv.legal_note, inv.created_at as invoice_created_at
                       FROM invoices inv
                       JOIN sales s ON s.id = inv.sale_id
                       LEFT JOIN clients c ON s.client_id = c.id
                       LEFT JOIN users u ON s.user_id = u.id
                       WHERE inv.share_token = ?
                       LIMIT 1");
$stmt->execute([$token]);
$sale = $stmt->fetch();
if (!$sale) {
    http_response_code(404);
    die('Facture introuvable.');
}

$invoice_number = $sale['invoice_number'] ?: format_invoice_number($sale['id']);
$subtotal = isset($sale['subtotal']) ? (float)$sale['subtotal'] : (float)$sale['total_amount'];
$discount = isset($sale['invoice_discount']) ? (float)$sale['invoice_discount'] : (float)$sale['discount'];
$total_ttc = isset($sale['invoice_total']) ? (float)$sale['invoice_total'] : (float)$sale['final_amount'];
$invoice_date = $sale['invoice_created_at'] ?: $sale['sale_date'];

$items = [];
if (!empty($sale['invoice_id'])) {
    $qi = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC");
    $qi->execute([(int)$sale['invoice_id']]);
    $items = $qi->fetchAll();
}
if (!$items) {
    $qi = $pdo->prepare("SELECT p.name as product_name, p.code as product_code, sd.qty, sd.unit_price, (sd.qty * sd.unit_price) as line_total
                         FROM sale_details sd JOIN products p ON p.id = sd.product_id WHERE sd.sale_id = ?");
    $qi->execute([(int)$sale['id']]);
    $items = $qi->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture <?php echo htmlspecialchars($invoice_number); ?> — Pharmacie Blessing</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; padding: 1rem; }
        .sheet { max-width: 720px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; }
        h1 { margin: 0 0 .35rem; font-size: 1.35rem; color: #1e3a8a; }
        .muted { color: #64748b; font-size: .92rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { text-align: left; padding: .55rem .35rem; border-bottom: 1px solid #e2e8f0; font-size: .92rem; }
        .total { display: flex; justify-content: space-between; margin-top: 1rem; font-size: 1.1rem; font-weight: 700; }
        .actions { max-width: 720px; margin: 0 auto 1rem; display: flex; gap: .5rem; flex-wrap: wrap; }
        .btn { display: inline-block; background: #1d4ed8; color: #fff; text-decoration: none; padding: .55rem .9rem; border-radius: 8px; font-weight: 700; border: 0; cursor: pointer; }
        .btn-light { background: #e2e8f0; color: #0f172a; }
        @media print { .actions { display: none !important; } body { background: #fff; padding: 0; } .sheet { border: 0; } }
    </style>
</head>
<body>
<div class="actions">
    <button class="btn" onclick="window.print()">Imprimer / PDF</button>
    <a class="btn btn-light" href="index.php">Accueil</a>
</div>
<article class="sheet">
    <h1><?php echo htmlspecialchars($business['name']); ?></h1>
    <div class="muted"><?php echo htmlspecialchars($business['address']); ?> · <?php echo htmlspecialchars($business['phone']); ?></div>
    <hr>
    <div><strong>Facture <?php echo htmlspecialchars($invoice_number); ?></strong></div>
    <div class="muted">Date : <?php echo date('d/m/Y H:i', strtotime($invoice_date)); ?> · Statut : Payée</div>
    <div style="margin-top:.75rem;">
        <strong>Client</strong><br>
        <?php echo htmlspecialchars($sale['client_name'] ?? 'Client de passage'); ?><br>
        <?php if (!empty($sale['client_phone'])): ?>Tél : <?php echo htmlspecialchars($sale['client_phone']); ?><br><?php endif; ?>
        <?php if (!empty($sale['client_address'])): ?><?php echo htmlspecialchars($sale['client_address']); ?><?php endif; ?>
    </div>
    <table>
        <thead><tr><th>Produit</th><th>Qté</th><th>Prix</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                <td><?php echo (int)$item['qty']; ?></td>
                <td><?php echo number_format((float)$item['unit_price'], 0, ',', ' '); ?> FC</td>
                <td><?php echo number_format((float)$item['line_total'], 0, ',', ' '); ?> FC</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="total">
        <span>Total TTC</span>
        <span><?php echo number_format($total_ttc, 0, ',', ' '); ?> FC</span>
    </div>
    <?php if ($discount > 0): ?><div class="muted">Remise : <?php echo number_format($discount, 0, ',', ' '); ?> FC</div><?php endif; ?>
    <p class="muted" style="margin-top:1rem;">Les médicaments vendus ne sont ni repris ni échangés.</p>
</article>
</body>
</html>
