<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

$featuredProducts = $pdo->query(
    "SELECT p.*, c.name as category_name
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.qty > 0
     ORDER BY p.id DESC
     LIMIT 6"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacie Blessing — Dépôt de référence</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/logo-anim.css">
    <link rel="stylesheet" href="assets/css/public.css">
</head>
<body class="public-body">

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
                <li class="nav-item"><a class="nav-link" href="#accueil">Accueil</a></li>
                <li class="nav-item"><a class="nav-link" href="#mission">Mission</a></li>
                <li class="nav-item"><a class="nav-link" href="#produits">Médicaments</a></li>
                <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
            </ul>
            <a href="login.php" class="btn-nav-login"><i class="fa-solid fa-right-to-bracket"></i> Se connecter</a>
        </div>
    </div>
</nav>

<section id="accueil" class="hero">
    <div class="hero-slides" aria-hidden="true">
        <div class="hero-slide is-active" style="background-image: url('assets/img/a.jpeg');"></div>
        <div class="hero-slide" style="background-image: url('assets/img/b.jpeg');"></div>
        <div class="hero-slide" style="background-image: url('assets/img/c.jpeg');"></div>
    </div>
    <div class="hero-veil"></div>
    <div class="container hero-inner">
        <h1 class="hero-brand">Pharmacie Blessing</h1>
        <p class="hero-headline">Votre pharmacie de proximité, accessible en ligne.</p>
        <p class="hero-lead">Médicaments de qualité, conseils professionnels et stock suivi en temps réel.</p>
        <div class="hero-actions">
            <a href="#produits" class="btn-hero btn-hero-primary">Explorer les produits</a>
            <a href="#contact" class="btn-hero btn-hero-ghost">Nous contacter</a>
        </div>
    </div>
</section>

<section id="mission" class="section section-mission">
    <div class="container">
        <div class="section-head is-center reveal">
            <span class="section-kicker">Notre engagement</span>
            <h2 class="section-title">Nous prenons soin de votre santé au quotidien</h2>
            <p class="section-text">Un dépôt pharmaceutique de référence, pour des produits sûrs et un accompagnement clair.</p>
        </div>
        <div class="mission-grid">
            <article class="mission-item reveal">
                <div class="mission-icon"><i class="fa-solid fa-shield-heart"></i></div>
                <h3>Qualité certifiée</h3>
                <p>Produits issus de laboratoires sélectionnés, pour une sécurité optimale.</p>
            </article>
            <article class="mission-item reveal reveal-delay-1">
                <div class="mission-icon"><i class="fa-solid fa-bolt"></i></div>
                <h3>Disponibilité rapide</h3>
                <p>Stock suivi en continu pour répondre rapidement à vos besoins.</p>
            </article>
            <article class="mission-item reveal reveal-delay-2">
                <div class="mission-icon"><i class="fa-solid fa-user-doctor"></i></div>
                <h3>Conseils pharmaceutiques</h3>
                <p>Une équipe disponible pour vous orienter avec précision.</p>
            </article>
        </div>
    </div>
</section>

<section id="produits" class="section section-products">
    <div class="container">
        <div class="products-toolbar reveal">
            <div class="section-head mb-0">
                <span class="section-kicker">Catalogue</span>
                <h2 class="section-title">Produits disponibles</h2>
                <p class="section-text">Consultez les médicaments en stock et filtrez en temps réel.</p>
            </div>
            <div class="search-field">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="liveSearchInput" placeholder="Rechercher un médicament…" autocomplete="off">
            </div>
        </div>

        <div class="row g-4" id="productsGrid">
            <?php if (!empty($featuredProducts)): ?>
                <?php foreach ($featuredProducts as $product): ?>
                    <div class="col-md-6 col-lg-4 product-card-col reveal"
                         data-name="<?php echo htmlspecialchars(strtolower($product['name'])); ?>"
                         data-category="<?php echo htmlspecialchars(strtolower($product['category_name'] ?: '')); ?>">
                        <article class="product-tile">
                            <div class="product-media">
                                <?php if (!empty($product['image'])): ?>
                                    <img src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>"
                                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                    <i class="fa-solid fa-briefcase-medical fa-3x text-muted opacity-50"></i>
                                <?php endif; ?>
                            </div>
                            <div class="product-body">
                                <span class="product-cat"><?php echo htmlspecialchars($product['category_name'] ?: 'Produit'); ?></span>
                                <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="product-desc"><?php echo htmlspecialchars($product['description'] ?: 'Disponible immédiatement en pharmacie.'); ?></p>
                                <div class="product-foot">
                                    <div>
                                        <span class="product-price"><?php echo number_format((float)$product['sell_price'], 0, ',', ' '); ?> FC</span>
                                        <span class="product-stock">Stock : <?php echo (int)$product['qty']; ?> u.</span>
                                    </div>
                                    <a href="login.php" class="btn-order">Commander <i class="fa-solid fa-arrow-right"></i></a>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="empty-state">
                        <i class="fa-solid fa-info-circle fa-2x mb-3 text-primary"></i>
                        <p class="mb-0">Aucun produit disponible pour le moment.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section id="contact" class="section section-contact">
    <div class="container">
        <div class="contact-layout">
            <div class="reveal">
                <div class="section-head">
                    <span class="section-kicker">Contact</span>
                    <h2 class="section-title">Nous joindre facilement</h2>
                    <p class="section-text">Une équipe locale, un parcours clair, et une disponibilité adaptée à vos urgences.</p>
                </div>
                <ul class="contact-list">
                    <li>
                        <i class="fa-solid fa-location-dot"></i>
                        <div>
                            <strong>Adresse</strong>
                            <span>Kinshasa, République Démocratique du Congo</span>
                        </div>
                    </li>
                    <li>
                        <i class="fa-solid fa-mobile-screen-button"></i>
                        <div>
                            <strong>Téléphone</strong>
                            <span>+243 972 573 971</span>
                        </div>
                    </li>
                    <li>
                        <i class="fa-solid fa-envelope-open-text"></i>
                        <div>
                            <strong>Email</strong>
                            <span>contact@blessingpharmacie.com</span>
                        </div>
                    </li>
                </ul>
            </div>
            <aside class="hours-panel reveal reveal-delay-1">
                <h3><i class="fa-solid fa-clock me-2 text-primary"></i>Horaires</h3>
                <p>Lundi au samedi : 8h00 – 22h00<br>Dimanche : garde d’urgence</p>
            </aside>
        </div>
    </div>
</section>

<footer class="public-footer">
    <div class="container">
        <div class="row align-items-start g-3">
            <div class="col-md-7">
                <div class="footer-brand">Pharmacie Blessing</div>
                <p class="mb-0" style="max-width: 34rem;">Dépôt pharmaceutique de référence — qualité, traçabilité et service de proximité.</p>
            </div>
            <div class="col-md-5 text-md-end footer-social">
                <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook"></i></a>
                <a href="#" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
            </div>
        </div>
        <div class="footer-copy">© <?php echo date('Y'); ?> Pharmacie Blessing. Tous droits réservés.</div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/public.js"></script>
</body>
</html>
