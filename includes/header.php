<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/ui_shell.php';

if (!is_logged_in()) {
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1d4ed8">
    <link rel="manifest" href="<?php echo app_base_url('manifest.webmanifest'); ?>">
    <link rel="apple-touch-icon" href="<?php echo app_base_url('assets/img/pha.jpeg'); ?>">
    <?php render_theme_boot(rtrim(app_base_url(), '/')); ?>
    <title><?php echo isset($page_title) ? $page_title : 'PHARMACIE BLESSING'; ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo app_base_url('assets/css/style.css'); ?>" rel="stylesheet">
    <link href="<?php echo app_base_url('assets/css/theme.css'); ?>" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/sidebar.php'; ?>

        <!-- Page Content -->
        <div id="content">
            <!-- Navbar -->
            <nav class="navbar navbar-expand-lg navbar-dark border-bottom px-2 px-md-4 py-2 py-md-3">
                <div class="container-fluid px-1 px-md-2">
                    <button type="button" id="sidebarCollapse" class="btn btn-outline-primary d-lg-none btn-sm" aria-label="Ouvrir le menu">
                        <i class="fas fa-bars"></i>
                        <span class="d-none d-sm-inline">Menu</span>
                    </button>
                    
                    <a href="<?php echo app_base_url('dashboard.php'); ?>" class="text-decoration-none d-flex align-items-center gap-2">
                        <img src="<?php echo app_base_url('assets/img/pha.jpeg'); ?>" alt="Pharmacie Blessing" class="logo-clock" style="height:38px;width:38px;object-fit:cover;border-radius:50%;border:2px solid #0d6efd;box-shadow:0 2px 8px rgba(13,110,253,0.18);">
                        <span class="fw-bold text-primary fs-6 d-none d-md-inline">PHARMACIE BLESSING</span>
                    </a>

                    <div class="ms-auto d-flex align-items-center gap-2 gap-md-3">
                        <?php render_theme_switch(); ?>
                        <!-- Notifications -->
                        <?php 
                        $notifs = get_notifications($pdo);
                        if (count($notifs) > 0): ?>
                        <div class="dropdown">
                            <a class="nav-link dropdown-toggle position-relative" href="#" role="button" data-bs-toggle="dropdown" aria-label="Notifications">
                                <i class="fas fa-bell"></i>
                                <span class="d-none d-sm-inline">Notifications</span>
                                <span class="badge bg-danger ms-1"><?php echo count($notifs); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-0 overflow-hidden" style="width: min(280px, 90vw);">
                                <li class="bg-primary text-white p-3">
                                    <h6 class="mb-0">Notifications</h6>
                                </li>
                                <?php foreach ($notifs as $notif): ?>
                                    <li>
                                        <a class="dropdown-item p-3 border-bottom small" href="#">
                                            <?php echo htmlspecialchars($notif['message']); ?>
                                            <br><small class="text-muted"><?php echo date('d/m H:i', strtotime($notif['created_at'])); ?></small>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                                <li><a class="dropdown-item text-center small text-primary py-2" href="#">Voir tout</a></li>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <!-- User Profile -->
                        <div class="dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 34px; height: 34px; font-size: 14px;">
                                    <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                                </div>
                                <div class="d-none d-md-block text-start">
                                    <div class="fw-600 small"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                                    <div class="text-muted" style="font-size: 11px;"><?php echo $_SESSION['role']; ?></div>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                                <li><a class="dropdown-item py-2" href="#">Profil</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2 text-danger fw-bold" href="<?php echo app_base_url('logout.php'); ?>">Se deconnecter</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>
            
            <div class="container-fluid p-4">
