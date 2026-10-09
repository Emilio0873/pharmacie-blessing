<?php
$page_title = "Réservations en ligne - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Caissier', 'Facturier']);

$reservations = $pdo->query(
    "SELECT r.*,
            (SELECT COUNT(*) FROM reservation_items i WHERE i.reservation_id = r.id) AS line_count
     FROM reservations r
     ORDER BY r.created_at DESC"
)->fetchAll();

require_once '../../includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-8">
        <h3 class="fw-bold mb-1">Réservations en ligne</h3>
        <p class="text-muted mb-0">Demandes de l’espace client. Quand le client vient payer, le facturier valide le paiement : la vente passe en caisse et chez le livreur.</p>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Référence</th>
                        <th>Client</th>
                        <th>Téléphone</th>
                        <th>Retrait prévu</th>
                        <th>Produits</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        <th class="text-end pe-4">Détail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($reservations) === 0): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">Aucune réservation en ligne.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reservations as $reservation): ?>
                            <?php $paid = ($reservation['status'] ?? '') === 'payee' || !empty($reservation['sale_id']); ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?php echo htmlspecialchars($reservation['reference']); ?></td>
                                <td><?php echo htmlspecialchars($reservation['last_name'] . ' ' . $reservation['first_name']); ?></td>
                                <td><?php echo htmlspecialchars($reservation['phone']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($reservation['pickup_date'])); ?></td>
                                <td><?php echo (int)$reservation['line_count']; ?></td>
                                <td class="fw-bold"><?php echo format_currency($reservation['subtotal']); ?></td>
                                <td>
                                    <?php if ($paid): ?>
                                        <span class="badge bg-success">Payée</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">En attente</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <a class="btn btn-sm btn-primary" href="reservation_view.php?id=<?php echo (int)$reservation['id']; ?>">
                                        <?php echo $paid ? 'Voir' : 'Valider'; ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
