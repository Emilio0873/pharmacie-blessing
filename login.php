<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    if ($_SESSION['role'] === 'Caissier') {
        redirect('modules/caisse/index.php');
    } else {
        redirect('dashboard.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'active') {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role_name'];

                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                log_activity($pdo, $user['id'], 'Connexion', "L'utilisateur s'est connecte.");

                if ($_SESSION['role'] === 'Caissier') {
                    redirect('modules/caisse/index.php');
                } else {
                    redirect('dashboard.php');
                }
            } else {
                $error = "Votre compte est desactive. Contactez l'administrateur.";
            }
        } else {
            $error = "Identifiants invalides.";
        }
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - PHARMACIE BLESSING</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: radial-gradient(circle at top right, #1e3a8a 0%, #0f172a 45%, #020617 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            padding: 16px;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: rgba(15, 23, 42, 0.94);
            border-radius: 16px;
            box-shadow: 0 16px 40px rgba(2, 6, 23, 0.45);
            padding: 40px;
            border: 1px solid #334155;
        }
        @media (max-width: 400px) {
            .login-card { padding: 24px; }
            .login-title { font-size: 1.25rem; }
        }
        .login-title {
            color: #dbeafe;
            font-weight: 700;
            font-size: 1.5rem;
        }
        .login-subtitle {
            color: #94a3b8;
            font-size: 0.875rem;
        }
        .form-control {
            border-radius: 8px;
            border: 1px solid #334155;
            padding: 11px 14px;
            font-size: 0.95rem;
            background: #0b1220;
            color: #e2e8f0;
        }
        .form-control:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.2);
            background: #111b2f;
            color: #f8fafc;
        }
        .form-label {
            font-weight: 600;
            color: #cbd5e1;
            font-size: 0.875rem;
        }
        .btn-login {
            background: linear-gradient(135deg, #1d4ed8, #1e3a8a);
            border: none;
            padding: 12px;
            font-weight: 700;
            border-radius: 8px;
            color: #fff;
            width: 100%;
            font-size: 1rem;
            transition: background 0.2s;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #1e40af, #1e3a8a);
            color: #fff;
        }
        .alert {
            border-radius: 8px;
            font-size: 0.875rem;
        }
        .divider {
            height: 3px;
            background: linear-gradient(90deg, #1d4ed8, #93c5fd);
            border-radius: 2px;
            margin-bottom: 28px;
        }
        @keyframes logo-clock-spin {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }
        .logo-clock {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #1d4ed8;
            box-shadow: 0 8px 24px rgba(29, 78, 216, 0.35);
            margin: 0 auto 14px;
            display: block;
            animation: logo-clock-spin 12s linear infinite;
            transform-origin: center center;
        }
        @media (prefers-reduced-motion: reduce) {
            .logo-clock { animation: none; }
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="text-center mb-4">
        <img src="assets/img/pha.jpeg" alt="Pharmacie Blessing" class="logo-clock">
        <div class="login-title">PHARMACIE BLESSING</div>
        <div class="login-subtitle">Gestion de Stock Professionnelle</div>
    </div>
    <div class="divider"></div>

    <?php if ($error): ?>
        <div class="alert alert-danger mb-3"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label for="username" class="form-label">Nom d'utilisateur</label>
            <input type="text" class="form-control" id="username" name="username" placeholder="Votre identifiant" required>
        </div>
        <div class="mb-4">
            <label for="password" class="form-label">Mot de passe</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="Votre mot de passe" required>
        </div>
        <button type="submit" class="btn-login">Se Connecter</button>
    </form>
    <div class="text-center mt-3">
        <a href="index.php" class="small text-decoration-none text-primary">Retour à l'accueil</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
