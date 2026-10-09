<?php
function public_anchor($hash) {
    $self = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($self === 'index.php' || $self === 'public_home.php') {
        return '#' . $hash;
    }
    return 'index.php#' . $hash;
}

function render_public_chrome_start($title) {
    $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1d4ed8">
    <link rel="manifest" href="' . htmlspecialchars(app_base_url('manifest.webmanifest'), ENT_QUOTES, 'UTF-8') . '">
    <link rel="apple-touch-icon" href="' . htmlspecialchars(app_base_url('assets/img/pha.jpeg'), ENT_QUOTES, 'UTF-8') . '">';
    render_theme_boot(rtrim(app_base_url(), '/'));
    echo '
    <title>' . $title . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/logo-anim.css">
    <link rel="stylesheet" href="assets/css/public.css">
    <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body class="public-body">';
    render_public_nav();
}

function render_public_nav() {
    $self = basename($_SERVER['SCRIPT_NAME'] ?? '');
    ?>
<nav class="navbar navbar-expand-lg public-nav">
    <div class="container py-2">
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <img src="assets/img/pha.jpeg" alt="Pharmacie Blessing" class="nav-logo logo-clock">
            <span>Pharmacie Blessing</span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navContent" aria-controls="navContent" aria-expanded="false" aria-label="Menu">
            <i class="fa-solid fa-bars-staggered text-primary"></i>
        </button>
        <div class="collapse navbar-collapse" id="navContent">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-lg-3">
                <li class="nav-item"><a class="nav-link" href="<?php echo public_anchor('accueil'); ?>">Accueil</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo public_anchor('mission'); ?>">Mission</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo public_anchor('produits'); ?>">Médicaments</a></li>
                <li class="nav-item"><a class="nav-link<?php echo $self === 'reservation.php' ? ' active' : ''; ?>" href="reservation.php">Réserver</a></li>
                <li class="nav-item"><a class="nav-link<?php echo $self === 'mes_reservations.php' ? ' active' : ''; ?>" href="mes_reservations.php">Mes réservations</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo public_anchor('contact'); ?>">Contact</a></li>
            </ul>
            <?php render_theme_switch(); ?>
            <a href="login.php" class="btn-nav-login"><i class="fa-solid fa-right-to-bracket"></i> Se connecter</a>
        </div>
    </div>
</nav>
    <?php
}

function render_public_chrome_end() {
    ?>
<footer class="public-footer">
    <div class="container">
        <div class="footer-copy">© <?php echo date('Y'); ?> Pharmacie Blessing. Tous droits réservés.</div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/theme.js"></script>
</body>
</html>
    <?php
}
