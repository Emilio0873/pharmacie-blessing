<?php $base_url = '/PHARMACIE%20BLESSING'; ?>
<!-- Sidebar -->
<nav id="sidebar">
    <div class="sidebar-header p-4 text-center">
        <a href="<?php echo $base_url; ?>/dashboard.php" class="text-decoration-none">
            <img src="<?php echo $base_url; ?>/assets/img/pha.jpeg" alt="Logo" class="logo-clock"
                 style="width:60px;height:60px;object-fit:cover;border-radius:50%;border:3px solid rgba(255,255,255,0.4);box-shadow:0 4px 15px rgba(0,0,0,0.2);margin-bottom:8px;display:block;margin-left:auto;margin-right:auto;">
            <h5 class="mb-0 fw-bold text-white">BLESSING</h5>
            <small class="text-white-50">PHARMACIE</small>
        </a>
    </div>

    <ul class="list-unstyled components p-3">
        <?php if (has_role('Super Admin') || has_role('Admin')): ?>
        <li class="mb-2">
            <a href="<?php echo $base_url; ?>/dashboard.php" class="nav-link p-3 rounded <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                Tableau de bord
            </a>
        </li>
        <?php endif; ?>
        <?php if (!has_role('Magasinier')): ?>
        <li class="mb-2">
            <a href="<?php echo $base_url; ?>/modules/caisse/index.php" class="nav-link p-3 rounded fw-bold <?php echo strpos($_SERVER['PHP_SELF'], '/modules/caisse/') !== false ? 'active' : ''; ?>">
                Module Caisse
            </a>
        </li>
        <?php endif; ?>

        <?php if (has_role('Super Admin') || has_role('Admin') || has_role('Magasinier')): ?>
        <li class="sidebar-label px-3 pt-3 pb-2 text-uppercase fw-bold">Gestion des Stocks</li>
        <li class="mb-2">
            <a href="#productSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="nav-link p-3 rounded d-flex justify-content-between align-items-center">
                <span>Produits</span>
                <small>+</small>
            </a>
            <ul class="collapse list-unstyled ps-4" id="productSubmenu">
                <li><a href="<?php echo $base_url; ?>/modules/products/index.php" class="nav-link py-2 rounded">Liste des produits</a></li>
                <li><a href="<?php echo $base_url; ?>/modules/products/add.php" class="nav-link py-2 rounded">Ajouter un produit</a></li>
                <li><a href="<?php echo $base_url; ?>/modules/categories/index.php" class="nav-link py-2 rounded">Catégories</a></li>
            </ul>
        </li>
        <li class="mb-2">
            <a href="<?php echo $base_url; ?>/modules/stock/index.php" class="nav-link p-3 rounded">
                Mouvements de Stock
            </a>
        </li>
        <?php endif; ?>

        <?php if (has_role('Super Admin') || has_role('Admin') || has_role('Caissier')): ?>
        <li class="sidebar-label px-3 pt-3 pb-2 text-uppercase fw-bold">Ventes</li>
        <li class="mb-2">
            <a href="<?php echo $base_url; ?>/modules/sales/index.php" class="nav-link p-3 rounded">
                Ventes et Factures
            </a>
        </li>
        <li class="mb-2">
            <a href="<?php echo $base_url; ?>/modules/sales/pos.php" class="nav-link p-3 rounded fw-bold">
                Vente Rapide (POS)
            </a>
        </li>
        <?php endif; ?>

        <?php if (has_role('Super Admin') || has_role('Admin') || has_role('Magasinier')): ?>
        <li class="sidebar-label px-3 pt-3 pb-2 text-uppercase fw-bold">Achats</li>
        <li class="mb-2">
            <a href="<?php echo $base_url; ?>/modules/purchases/index.php" class="nav-link p-3 rounded">
                Achats Fournisseurs
            </a>
        </li>
        <?php endif; ?>

        <?php if (has_role('Super Admin') || has_role('Admin') || has_role('Caissier')): ?>
        <li class="sidebar-label px-3 pt-3 pb-2 text-uppercase fw-bold">Partenaires</li>
        <li class="mb-2">
            <a href="<?php echo $base_url; ?>/modules/clients/index.php" class="nav-link p-3 rounded">
                Clients
            </a>
        </li>
        <?php endif; ?>

        <?php if (has_role('Super Admin') || has_role('Admin') || has_role('Magasinier')): ?>
        <li class="mb-2">
            <a href="<?php echo $base_url; ?>/modules/suppliers/index.php" class="nav-link p-3 rounded">
                Fournisseurs
            </a>
        </li>
        <?php endif; ?>

        <?php if (has_role('Super Admin') || has_role('Admin') || has_role('Magasinier')): ?>
        <li class="sidebar-label px-3 pt-3 pb-2 text-uppercase fw-bold">Système</li>
        <li class="mb-2">
            <a href="<?php echo $base_url; ?>/modules/reports/index.php" class="nav-link p-3 rounded <?php echo (strpos($_SERVER['PHP_SELF'], '/modules/reports/') !== false && basename($_SERVER['PHP_SELF']) === 'index.php') ? 'active' : ''; ?>">
                Rapports et Statistiques
            </a>
        </li>
        <li class="mb-2">
            <a href="<?php echo $base_url; ?>/modules/reports/full_report.php" class="nav-link p-3 rounded fw-bold <?php echo basename($_SERVER['PHP_SELF']) === 'full_report.php' ? 'active' : ''; ?>">
                Rapport Complet
            </a>
        </li>
        <?php endif; ?>

        <?php if (has_role('Super Admin')): ?>
        <li class="sidebar-label px-3 pt-3 pb-2 text-uppercase fw-bold">Administration</li>
        <li class="mb-2">
            <a href="<?php echo $base_url; ?>/modules/users/index.php" class="nav-link p-3 rounded">
                Utilisateurs
            </a>
        </li>
        <li class="mb-2">
            <a href="<?php echo $base_url; ?>/modules/audit/index.php" class="nav-link p-3 rounded">
                Journal (Audit)
            </a>
        </li>
        <li class="mb-2">
            <a href="<?php echo $base_url; ?>/modules/settings/index.php" class="nav-link p-3 rounded">
                Parametres
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer p-4 mt-auto">
        <small class="text-muted">&copy; 2026 Pharmacie Blessing</small>
    </div>
</nav>
