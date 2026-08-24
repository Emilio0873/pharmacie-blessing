<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

$featuredProducts = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.qty > 0 ORDER BY p.id DESC LIMIT 6")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blessing Pharmacie - Dépôt de Référence</title>
    <!-- Bootstrap & Icons & Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        :root {
            --primary: #1d4ed8;
            --primary-hover: #1e3a8a;
            --primary-light: #dbeafe;
            --primary-glow: rgba(59, 130, 246, 0.22);
            --dark: #020617;
            --dark-soft: #0f172a;
            --light: #f1f5f9;
            --white: #ffffff;
            --gray-soft: #e2e8f0;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--light);
            color: var(--dark);
            overflow-x: hidden;
            scroll-behavior: smooth;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
        }

        /* Modern Glassmorphic Nav */
        .custom-nav {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(14, 165, 233, 0.1);
            transition: all 0.3s ease;
        }
        
        .navbar-brand {
            font-size: 1.6rem;
            letter-spacing: -0.5px;
        }
        
        .btn-connect {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
            color: var(--white) !important;
            border: none;
            border-radius: 50px;
            padding: 10px 24px;
            font-weight: 600;
            box-shadow: 0 4px 15px var(--primary-glow);
            transition: all 0.3s ease;
        }
        .btn-connect:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(14, 165, 233, 0.3);
        }

        /* Hero Slideshow Section */
        .hero-section {
            position: relative;
            height: clamp(550px, 85vh, 750px);
            overflow: hidden;
            background: var(--dark);
            display: flex;
            align-items: center;
        }

        .slide-container {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: 1;
        }

        .hero-slide {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transition: opacity 1.6s ease-in-out;
            transform: scale(1.05);
        }

        .hero-slide.active {
            opacity: 0.45;
            animation: kenBurns 12s ease-out forwards;
        }

        @keyframes kenBurns {
            0% { transform: scale(1.03); }
            100% { transform: scale(1.1); }
        }

        .hero-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(15, 23, 42, 0.6) 50%, rgba(15, 23, 42, 0.3) 100%);
            z-index: 2;
        }

        .hero-content-wrapper {
            position: relative;
            z-index: 3;
            color: var(--white);
        }

        .badge-welcome {
            background-color: var(--primary-glow);
            border: 1px solid rgba(14, 165, 233, 0.3);
            color: var(--primary);
            font-size: 0.85rem;
            padding: 8px 16px;
            border-radius: 50px;
            backdrop-filter: blur(5px);
            text-transform: uppercase;
            letter-spacing: 1px;
            display: inline-block;
        }

        .hero-title {
            font-size: clamp(2.4rem, 5vw, 4rem);
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -1px;
            margin-top: 15px;
            background: linear-gradient(to right, #ffffff, #cceeff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-desc {
            font-size: clamp(1.05rem, 1.8vw, 1.25rem);
            font-weight: 400;
            color: rgba(255, 255, 255, 0.85);
            max-width: 600px;
            line-height: 1.6;
        }

        .btn-hero-primary {
            background: var(--primary);
            color: var(--white) !important;
            padding: 14px 32px;
            border-radius: 50px;
            font-weight: 600;
            box-shadow: 0 4px 15px var(--primary-glow);
            border: 1px solid transparent;
            transition: all 0.3s ease;
        }
        .btn-hero-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(14, 165, 233, 0.4);
        }

        .btn-hero-outline {
            background: rgba(255, 255, 255, 0.08);
            color: var(--white) !important;
            padding: 14px 32px;
            border-radius: 50px;
            font-weight: 600;
            border: 1px solid rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(8px);
            transition: all 0.3s;
        }
        .btn-hero-outline:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-3px);
        }

        /* Hero Badges Box */
        .features-badge-box {
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            color: var(--white);
        }

        .badge-box-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .badge-box-icon {
            color: var(--primary);
            font-size: 1.5rem;
        }

        .features-list li {
            position: relative;
            padding-left: 35px;
            margin-bottom: 15px;
            font-weight: 600;
            font-size: 0.98rem;
            color: rgba(255, 255, 255, 0.95);
        }
        .features-list li::after {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            left: 0;
            top: 2px;
            color: var(--primary);
        }

        /* Decorative Background */
        .blob-bg {
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.08) 0%, rgba(255, 255, 255, 0) 70%);
            border-radius: 50%;
            z-index: 0;
            pointer-events: none;
        }

        /* Highlight Features Section */
        .feature-card {
            border: 1px solid rgba(14, 165, 233, 0.06);
            background: var(--white);
            border-radius: 20px;
            padding: 35px 25px;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.02);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, #38bdf8 100%);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 45px rgba(14, 165, 233, 0.08);
            border-color: rgba(14, 165, 233, 0.15);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .icon-circle {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 24px;
            transition: all 0.3s;
        }

        .feature-card:hover .icon-circle {
            background: var(--primary);
            color: var(--white);
            transform: scale(1.05) rotate(5deg);
        }

        /* Dynamic Stats Counters */
        .stats-section {
            background: linear-gradient(135deg, var(--dark) 0%, var(--dark-soft) 100%);
            color: var(--white);
            position: relative;
            overflow: hidden;
        }

        .stat-item {
            text-align: center;
            padding: 20px;
        }

        .stat-number {
            font-size: 3.2rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
            margin-bottom: 10px;
            font-family: 'Outfit', sans-serif;
        }

        .stat-label {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Styled Filter Section */
        .search-container {
            background-color: var(--white);
            border-radius: 30px;
            padding: 6px 10px 6px 20px;
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.04);
            border: 1px solid rgba(14, 165, 233, 0.1);
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            align-items: center;
        }
        
        .search-container input {
            border: none;
            outline: none;
            width: 100%;
            padding: 10px 10px;
            font-size: 1rem;
            font-weight: 500;
        }

        .search-icon {
            color: var(--primary);
            font-size: 1.2rem;
        }

        /* Product Cards Overhaul */
        .card-product {
            background-color: var(--white);
            border: 15px;
            border: 1px solid rgba(15, 23, 42, 0.04);
            border-radius: 20px;
            padding: 18px;
            display: flex;
            flex-direction: column;
            height: 100%;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.015);
        }

        .card-product:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 40px rgba(14, 165, 233, 0.07);
            border-color: rgba(14, 165, 233, 0.12);
        }

        .product-img-wrapper {
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            background: var(--gray-soft);
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .product-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .card-product:hover .product-img-wrapper img {
            transform: scale(1.08);
        }

        .category-tag {
            background-color: var(--primary-light);
            color: var(--primary-hover);
            font-size: 0.78rem;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 50px;
            display: inline-block;
            margin-bottom: 12px;
            text-transform: uppercase;
        }

        .price-badge {
            font-size: 1.25rem;
            color: var(--primary);
            font-weight: 800;
            font-family: 'Outfit', sans-serif;
        }

        .btn-order {
            background: var(--gray-soft);
            color: var(--dark) !important;
            border-radius: 12px;
            padding: 6px 16px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            border: 1px solid transparent;
        }

        .card-product:hover .btn-order {
            background: var(--primary);
            color: var(--white) !important;
            box-shadow: 0 4px 12px var(--primary-glow);
        }

        /* Entrance Scroll Animations */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }

        .animate-on-scroll.appear {
            opacity: 1;
            transform: translateY(0);
        }

        .delay-100 { transition-delay: 100ms; }
        .delay-200 { transition-delay: 200ms; }
        .delay-300 { transition-delay: 300ms; }

        /* Elegant Footer */
        .custom-footer {
            background-color: var(--dark);
            color: rgba(255,255,255,0.7);
            border-top: 1px solid rgba(255,255,255,0.05);
            padding: 60px 0 30px;
        }

        .footer-brand {
            font-size: 1.5rem;
            color: var(--white);
            font-weight: 800;
            margin-bottom: 20px;
        }
        
        .footer-link {
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            transition: color 0.3s;
        }
        .footer-link:hover {
            color: var(--primary);
        }

        @keyframes logo-clock-spin {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }
        .logo-clock {
            animation: logo-clock-spin 12s linear infinite;
            transform-origin: center center;
        }
        @media (prefers-reduced-motion: reduce) {
            .logo-clock { animation: none; }
        }

        @media (max-width: 991.98px) {
            .hero-section {
                height: auto;
                min-height: 70vh;
                padding: 100px 0 60px;
            }
            .features-badge-box {
                margin-top: 2rem;
                padding: 22px;
            }
            .blob-bg {
                width: 280px;
                height: 280px;
                opacity: 0.6;
            }
            .navbar-brand {
                font-size: 1.15rem;
            }
        }

        @media (max-width: 767.98px) {
            .hero-title {
                font-size: clamp(1.85rem, 8vw, 2.4rem);
            }
            .hero-desc {
                font-size: 1rem;
            }
            .btn-hero-primary,
            .btn-hero-outline {
                width: 100%;
                text-align: center;
                padding: 12px 20px;
            }
            .hero-content-wrapper .d-flex.gap-3 {
                flex-direction: column;
                gap: 0.75rem !important;
            }
            .search-container {
                flex-direction: column;
                gap: 0.75rem;
            }
            .search-container .form-control,
            .search-container .btn {
                width: 100%;
            }
            .feature-card {
                padding: 24px 18px;
            }
            .custom-footer {
                padding: 40px 0 24px;
                text-align: center;
            }
        }

        @media (max-width: 575.98px) {
            .blob-bg { display: none; }
            .badge-welcome {
                font-size: 0.72rem;
                padding: 6px 12px;
            }
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg custom-nav sticky-top">
    <div class="container py-2">
        <a class="navbar-brand fw-bold text-primary d-flex align-items-center gap-2" href="index.php">
            <img src="assets/img/pha.jpeg" alt="Logo" class="rounded-circle object-fit-cover logo-clock" style="width: 40px; height: 40px; border: 2px solid var(--primary);"> Blessing Pharmacie
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
            <i class="fa-solid fa-bars-staggered text-primary"></i>
        </button>
        <div class="collapse navbar-collapse" id="navContent">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0 gap-lg-3">
                <li class="nav-item"><a class="nav-link fw-semibold" href="#accueil">Accueil</a></li>
                <li class="nav-item"><a class="nav-link fw-semibold" href="#notre-mission">Notre Mission</a></li>
                <li class="nav-item"><a class="nav-link fw-semibold" href="#produits">Médicaments</a></li>
                <li class="nav-item"><a class="nav-link fw-semibold" href="#contact">Contact</a></li>
            </ul>
            <a href="login.php" class="btn btn-connect"><i class="fa-solid fa-right-to-bracket me-2"></i>Se connecter</a>
        </div>
    </div>
</nav>

<!-- Hero Section with Animated Slideshow -->
<section id="accueil" class="hero-section">
    <div class="slide-container">
        <div class="hero-slide active" style="background-image: url('assets/img/a.jpeg');"></div>
        <div class="hero-slide" style="background-image: url('assets/img/b.jpeg');"></div>
        <div class="hero-slide" style="background-image: url('assets/img/c.jpeg');"></div>
    </div>
    <div class="hero-overlay"></div>
    
    <div class="container hero-content-wrapper py-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="badge-welcome">
                    <i class="fa-solid fa-shield-heart me-1"></i> Partenaire de Confiance
                </span>
                <h1 class="hero-title">Votre pharmacie de proximité, accessible en ligne</h1>
                <p class="hero-desc mt-3 mb-4">
                    Blessing Pharmacie s'engage à vous fournir des médicaments de qualité supérieure, des conseils hautement qualifiés et un parcours de commande fluide.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="#produits" class="btn btn-hero-primary">Explorer les produits</a>
                    <a href="#contact" class="btn btn-hero-outline">Nous contacter</a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="features-badge-box">
                    <h5 class="badge-box-title">
                        <i class="fa-solid fa-briefcase-medical badge-box-icon"></i> Services & Garanties
                    </h5>
                    <ul class="list-unstyled features-list mb-0">
                        <li>Médicaments 100% certifiés et authentiques</li>
                        <li>Disponibilité en temps réel de notre stock</li>
                        <li>Service de commande et livraison express</li>
                        <li>Pharmaciens conseil agréés à votre écoute</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Mission & Highlight Cards -->
<section id="notre-mission" class="py-5 bg-white position-relative">
    <div class="blob-bg" style="top: -100px; left: -100px;"></div>
    <div class="container py-4">
        <div class="text-center max-width-600 mx-auto mb-5 animate-on-scroll">
            <span class="badge-welcome mb-2">Pourquoi Choisir Blessing</span>
            <h2 class="section-title">Nous prenons soin de votre santé au quotidien</h2>
        </div>
        
        <div class="row g-4 justify-content-center">
            <!-- Card 1 -->
            <div class="col-md-4 animate-on-scroll">
                <div class="feature-card h-100">
                    <div class="icon-circle"><i class="fa-solid fa-lock-open"></i></div>
                    <h5 class="fw-bold mb-3">Qualité Certifiée</h5>
                    <p class="text-muted mb-0">Tous nos produits proviennent de laboratoires rigoureusement sélectionnés pour assurer une sécurité optimale.</p>
                </div>
            </div>
            <!-- Card 2 -->
            <div class="col-md-4 animate-on-scroll delay-100">
                <div class="feature-card h-100">
                    <div class="icon-circle"><i class="fa-solid fa-bolt"></i></div>
                    <h5 class="fw-bold mb-3">Livraison Express</h5>
                    <p class="text-muted mb-0">Un traitement instantané de vos ordonnances pour récupérer vos produits rapidement et en toute sécurité.</p>
                </div>
            </div>
            <!-- Card 3 -->
            <div class="col-md-4 animate-on-scroll delay-200">
                <div class="feature-card h-100">
                    <div class="icon-circle"><i class="fa-solid fa-user-doctor"></i></div>
                    <h5 class="fw-bold mb-3">Conseils Médicaux</h5>
                    <p class="text-muted mb-0">Des pharmaciens qualifiés pour répondre à vos questions et vous orienter de façon experte.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Dynamic Counter Section -->
<section class="stats-section py-5">
    <div class="container animate-on-scroll">
        <div class="row g-4 text-center">
            <div class="col-6 col-lg-3">
                <div class="stat-item">
                    <div class="stat-number" data-target="98">0</div>
                    <div class="stat-label">Taux de satisfaction (%)</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-item">
                    <div class="stat-number" data-target="1500">0</div>
                    <div class="stat-label">Clients Satisfaits</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-item">
                    <div class="stat-number" data-target="350">0</div>
                    <div class="stat-label">Médicaments Référencés</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-item">
                    <div class="stat-number" data-target="24">0</div>
                    <div class="stat-label">Heures d'Urgence / 7</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Products list with live search -->
<section id="produits" class="py-5 bg-light">
    <div class="container py-4">
        <div class="row align-items-end mb-5 g-4 animate-on-scroll">
            <div class="col-lg-6 text-center text-lg-start">
                <span class="badge-welcome mb-2">Notre Catalogue</span>
                <h2>Nos produits disponibles</h2>
                <p class="text-muted mb-0">Consultez en temps réel les médicaments en stock chez nous.</p>
            </div>
            <div class="col-lg-6">
                <!-- Search bar with dynamic matching capability -->
                <div class="search-container">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" id="liveSearchInput" placeholder="Rechercher un médicament et filtrer en temps réel...">
                </div>
            </div>
        </div>

        <div class="row g-4" id="productsGrid">
            <?php if (!empty($featuredProducts)): ?>
                <?php foreach ($featuredProducts as $index => $product): ?>
                    <div class="col-md-6 col-lg-4 product-card-col animate-on-scroll" data-name="<?php echo htmlspecialchars(strtolower($product['name'])); ?>" data-category="<?php echo htmlspecialchars(strtolower($product['category_name'] ?: '')); ?>">
                        <div class="card card-product">
                            <div class="product-img-wrapper">
                                <?php if (!empty($product['image'])): ?>
                                    <img src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                                        <i class="fa-solid fa-briefcase-medical fa-4x text-muted opacity-30"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body px-0 pb-0 d-flex flex-column h-100">
                                <span class="category-tag align-self-start"><?php echo htmlspecialchars($product['category_name'] ?: 'Produit'); ?></span>
                                <h5 class="fw-bold fs-5 mb-2"><?php echo htmlspecialchars($product['name']); ?></h5>
                                <p class="text-muted small mb-3"><?php echo htmlspecialchars($product['description'] ?: 'Produit de santé disponible immédiatement en pharmacie.'); ?></p>
                                <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-auto">
                                    <div>
                                        <div class="price-badge"><?php echo number_format($product['sell_price'], 0, ',', ' '); ?> FC</div>
                                        <small class="text-muted">Stock: <strong class="text-dark"><?php echo (int)$product['qty']; ?></strong> u.</small>
                                    </div>
                                    <a href="login.php" class="btn btn-order">Commander <i class="fa-solid fa-arrow-right ms-1"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <div class="alert alert-info py-4">
                        <i class="fa-solid fa-info-circle fa-2x mb-3 text-primary"></i> <br>
                        Aucun produit disponible pour le moment dans la boutique en ligne.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Detailed Contact / About Section -->
<section id="contact" class="py-5 bg-white position-relative">
    <div class="blob-bg" style="bottom: -100px; right: -100px;"></div>
    <div class="container py-4">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6 animate-on-scroll">
                <span class="badge-welcome mb-2">Qui sommes-nous ?</span>
                <h2 class="mb-3">Blessing Pharmacie : L'excellence à votre service</h2>
                <p class="text-muted leading-relaxed mb-4">
                    Blessing Pharmacie est un établissement de référence voué à promouvoir la santé, la prévention et le bien-être au sein de nos communautés. Grâce à une gestion moderne et informatisée de notre catalogue, nous répondons à vos réclamations médicales en un temps record.
                </p>
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-circle" style="margin-bottom: 0;"><i class="fa-solid fa-clock"></i></div>
                    <div>
                        <h6 class="fw-bold mb-0">Heures d'ouverture</h6>
                        <span class="text-muted small">Lundi au Samedi : 8h00 - 22h00 | Dimanche : Garde d'urgence</span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 animate-on-scroll">
                <div class="card border-0 shadow-lg p-5" style="border-radius: 24px; border: 1px solid rgba(14, 165, 233, 0.05) !important;">
                    <h4 class="fw-bold mb-4"><i class="fa-solid fa-location-crosshairs text-primary me-2"></i> Nos Coordonnées</h4>
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex align-items-start gap-3">
                            <i class="fa-solid fa-location-dot text-primary mt-1 fs-5"></i>
                            <div>
                                <h6 class="fw-bold mb-0">Notre Localisation</h6>
                                <p class="text-muted mb-0">Kinshasa, République Démocratique du Congo</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-start gap-3">
                            <i class="fa-solid fa-mobile-screen-button text-primary mt-1 fs-5"></i>
                            <div>
                                <h6 class="fw-bold mb-0">Téléphone Direct</h6>
                                <p class="text-muted mb-0">+243 972 573 971</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-start gap-3">
                            <i class="fa-solid fa-envelope-open-text text-primary mt-1 fs-5"></i>
                            <div>
                                <h6 class="fw-bold mb-0">Adresse Courriel</h6>
                                <p class="text-muted mb-0">contact@blessingpharmacie.com</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footnotes -->
<footer class="custom-footer">
    <div class="container">
        <div class="row g-4 mb-4">
            <div class="col-md-6 text-center text-md-start">
                <div class="footer-brand"><i class="fa-solid fa-house-medical"></i> Blessing</div>
                <p style="opacity: 0.7; font-size: 0.9rem; max-width: 400px;">Optimisé pour offrir le meilleur service possible aux bénéficiaires et faciliter le suivi des stocks en pharmacie locale.</p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <div class="d-flex gap-3 justify-content-center justify-content-md-end mb-3">
                    <a href="#" class="footer-link"><i class="fa-brands fa-facebook fa-xl"></i></a>
                    <a href="#" class="footer-link"><i class="fa-brands fa-whatsapp fa-xl"></i></a>
                    <a href="#" class="footer-link"><i class="fa-brands fa-instagram fa-xl"></i></a>
                </div>
                <p style="opacity: 0.5; font-size: 0.8rem;">Tous nos produits respectent les agréments du Ministère de la Santé.</p>
            </div>
        </div>
        <hr style="border-top: 1px solid rgba(255,255,255,0.1)">
        <div class="text-center text-muted small mt-4">
            © 2026 Blessing Pharmacie. Tous droits réservés.
        </div>
    </div>
</footer>

<!-- JS Logic for Slider, IntersectionObserver animations, Stats counters, and Live Product Filters -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        // --- 1. Hero Slideshow Auto Rotate ---
        const slides = document.querySelectorAll(".hero-slide");
        let activeIdx = 0;
        
        function rotateSlides() {
            slides[activeIdx].classList.remove("active");
            activeIdx = (activeIdx + 1) % slides.length;
            slides[activeIdx].classList.add("active");
        }
        
        // Change slide every 5 seconds
        setInterval(rotateSlides, 5000);

        // --- 2. Live Product Filtering System ---
        const liveSearchInput = document.getElementById("liveSearchInput");
        const productGrid = document.getElementById("productsGrid");
        const productCards = document.querySelectorAll(".product-card-col");

        if (liveSearchInput) {
            liveSearchInput.addEventListener("input", function (e) {
                const query = e.target.value.toLowerCase().trim();
                let matchCount = 0;

                productCards.forEach(card => {
                    const name = card.getAttribute("data-name") || "";
                    const category = card.getAttribute("data-category") || "";

                    if (name.includes(query) || category.includes(query)) {
                        card.style.display = "block";
                        matchCount++;
                    } else {
                        card.style.display = "none";
                    }
                });

                // Display info if no results
                let emptyAlert = document.getElementById("searchEmptyAlert");
                if (matchCount === 0) {
                    if (!emptyAlert) {
                        emptyAlert = document.createElement("div");
                        emptyAlert.id = "searchEmptyAlert";
                        emptyAlert.className = "col-12 text-center py-4";
                        emptyAlert.innerHTML = `
                            <div class="alert alert-warning py-3">
                                Aucun médicament ne correspond à votre recherche.
                            </div>
                        `;
                        productGrid.appendChild(emptyAlert);
                    }
                } else {
                    if (emptyAlert) {
                        emptyAlert.remove();
                    }
                }
            });
        }

        // --- 3. Scroll Entrance Animations (IntersectionObserver) ---
        const animElements = document.querySelectorAll(".animate-on-scroll");
        
        const appearanceOptions = {
            threshold: 0.1,
            rootMargin: "0px 0px -50px 0px"
        };

        const appearanceObserver = new IntersectionObserver(function (entries, observer) {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add("appear");
                
                // If it contains stats numbers, trigger stats counting
                const startCounting = entry.target.querySelectorAll(".stat-number");
                if (startCounting.length > 0) {
                    startCounting.forEach(num => {
                        const target = parseInt(num.getAttribute("data-target"), 10);
                        animateCountUp(num, target);
                    });
                }
                
                observer.unobserve(entry.target);
            });
        }, appearanceOptions);

        animElements.forEach(el => appearanceObserver.observe(el));

        // --- 4. Incrementing Counter Animation ---
        function animateCountUp(element, target) {
            let start = 0;
            const duration = 1800; // ms
            const stepTime = Math.abs(Math.floor(duration / target)) || 30; // fallback if target is small
            
            const timer = setInterval(() => {
                start += Math.ceil(target / 60); // dynamic increment
                if (start >= target) {
                    element.textContent = target + (target === 98 || target === 24 ? '' : '+');
                    clearInterval(timer);
                } else {
                    element.textContent = start;
                }
            }, stepTime);
        }
    });
</script>
</body>
</html>
