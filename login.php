<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/ui_shell.php';

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
    <meta name="theme-color" content="#1d4ed8">
    <link rel="manifest" href="<?php echo app_base_url('manifest.webmanifest'); ?>">
    <link rel="apple-touch-icon" href="<?php echo app_base_url('assets/img/pha.jpeg'); ?>">
    <?php render_theme_boot(rtrim(app_base_url(), '/')); ?>
    <title>Connexion — Pharmacie Blessing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/logo-anim.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        :root {
            --pb-blue: #1d4ed8;
            --pb-blue-deep: #1e3a8a;
            --font-display: "Outfit", sans-serif;
            --font-body: "Plus Jakarta Sans", sans-serif;
        }
        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            font-family: var(--font-body);
            background:
                radial-gradient(circle at 85% 10%, rgba(29, 78, 216, 0.45), transparent 40%),
                radial-gradient(circle at 10% 90%, rgba(14, 165, 233, 0.18), transparent 35%),
                linear-gradient(160deg, #0f172a 0%, #020617 55%, #020617 100%);
        }
        .login-shell {
            width: 100%;
            max-width: 420px;
            background: rgba(11, 18, 32, 0.94);
            border: 1px solid #334155;
            border-radius: 20px;
            box-shadow: 0 24px 48px rgba(2, 6, 23, 0.45);
            padding: 2.25rem 2rem;
        }
        .login-logo {
            width: 72px;
            height: 72px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--pb-blue);
            box-shadow: 0 8px 24px rgba(29, 78, 216, 0.35);
            display: block;
            margin: 0 auto 0.9rem;
        }
        .login-title {
            font-family: var(--font-display);
            font-weight: 800;
            font-size: 1.45rem;
            letter-spacing: -0.03em;
            color: #dbeafe;
            text-align: center;
            margin: 0;
        }
        .login-subtitle {
            text-align: center;
            color: #94a3b8;
            font-size: 0.9rem;
            margin: 0.35rem 0 1.35rem;
        }
        .login-rule {
            height: 3px;
            border-radius: 2px;
            background: linear-gradient(90deg, var(--pb-blue), #93c5fd);
            margin-bottom: 1.5rem;
        }
        .form-label {
            font-weight: 600;
            color: #cbd5e1;
            font-size: 0.875rem;
        }
        .form-control {
            border-radius: 10px;
            border: 1px solid #475569;
            padding: 0.8rem 0.95rem;
            background: #f8fafc;
            color: #0f172a;
            font-size: 1rem;
            font-weight: 500;
            caret-color: #1d4ed8;
        }
        .form-control::placeholder {
            color: #64748b;
            opacity: 1;
        }
        .form-control:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
            background: #ffffff;
            color: #020617;
        }
        .form-control:-webkit-autofill,
        .form-control:-webkit-autofill:hover,
        .form-control:-webkit-autofill:focus {
            -webkit-text-fill-color: #0f172a;
            box-shadow: 0 0 0 1000px #f8fafc inset;
            transition: background-color 9999s ease-in-out 0s;
        }
        .password-wrap {
            position: relative;
        }
        .password-wrap .form-control {
            padding-right: 3rem;
            letter-spacing: 0.04em;
            font-size: 1.05rem;
        }
        .password-wrap .form-control[type="text"] {
            letter-spacing: normal;
            font-weight: 600;
        }
        .btn-toggle-password {
            position: absolute;
            top: 50%;
            right: 0.55rem;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #334155;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .btn-toggle-password:hover,
        .btn-toggle-password:focus {
            color: #1d4ed8;
            background: rgba(29, 78, 216, 0.08);
            outline: none;
        }
        .btn-login {
            width: 100%;
            border: none;
            border-radius: 10px;
            padding: 0.85rem;
            font-weight: 700;
            font-size: 1rem;
            color: #fff;
            background: linear-gradient(135deg, var(--pb-blue), var(--pb-blue-deep));
        }
        .btn-login:hover {
            color: #fff;
            filter: brightness(1.05);
        }
        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: #93c5fd;
            text-decoration: none;
            font-size: 0.88rem;
        }
        .back-link:hover { color: #bfdbfe; }
        .alert { border-radius: 10px; font-size: 0.875rem; }
        @media (max-width: 400px) {
            .login-shell { padding: 1.5rem 1.15rem; }
            .login-title { font-size: 1.25rem; }
        }
    </style>
</head>
<body>
<div class="d-flex justify-content-center pt-3"><?php render_theme_switch(); ?></div>
<div class="login-shell">
    <img src="assets/img/pha.jpeg" alt="Pharmacie Blessing" class="login-logo logo-clock">
    <h1 class="login-title">Pharmacie Blessing</h1>
    <p class="login-subtitle">Espace de gestion</p>
    <div class="login-rule"></div>

    <?php if ($error): ?>
        <div class="alert alert-danger mb-3"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label for="username" class="form-label">Nom d'utilisateur</label>
            <input type="text" class="form-control" id="username" name="username" placeholder="Votre identifiant" required autocomplete="username">
        </div>
        <div class="mb-4">
            <label for="password" class="form-label">Mot de passe</label>
            <div class="password-wrap">
                <input type="password" class="form-control" id="password" name="password" placeholder="Votre mot de passe" required autocomplete="current-password">
                <button type="button" class="btn-toggle-password" id="togglePassword" aria-label="Afficher le mot de passe" title="Afficher / masquer">
                    <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
        </div>
        <button type="submit" class="btn-login">Se connecter</button>
    </form>
    <div class="text-center">
        <a href="index.php" class="back-link">Retour à l'accueil</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/theme.js"></script>
<script>
document.getElementById('togglePassword').addEventListener('click', function () {
    var input = document.getElementById('password');
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    this.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
    this.style.color = show ? '#1d4ed8' : '';
});
</script>
</body>
</html>
