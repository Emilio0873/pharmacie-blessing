<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/ui_shell.php';
require_once 'includes/public_chrome.php';

ensure_client_accounts_table($pdo);

if (is_client_logged_in()) {
    redirect('client_espace.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    $email = strtolower(trim(strip_tags($_POST['email'] ?? '')));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM client_accounts WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $account = $stmt->fetch();
        if ($account && password_verify($password, $account['password'])) {
            if (($account['status'] ?? '') !== 'active') {
                $error = 'Votre compte est désactivé. Contactez la pharmacie.';
            } else {
                $_SESSION['client_account_id'] = (int)$account['id'];
                $_SESSION['client_id'] = (int)($account['client_id'] ?? 0);
                $_SESSION['client_name'] = $account['full_name'];
                $_SESSION['client_email'] = $account['email'];
                $_SESSION['client_phone'] = $account['phone'];
                $pdo->prepare("UPDATE client_accounts SET last_login = NOW() WHERE id = ?")->execute([(int)$account['id']]);
                redirect('client_espace.php');
            }
        } else {
            $error = 'E-mail ou mot de passe incorrect.';
        }
    }
}

render_public_chrome_start('Connexion client — Pharmacie Blessing');
?>

<section class="section section-products">
    <div class="container" style="max-width: 480px;">
        <div class="section-head">
            <span class="section-kicker">Espace client</span>
            <h2 class="section-title">Se connecter</h2>
            <p class="section-text">Accédez à vos réservations et factures.</p>
        </div>

        <form method="post" class="reserve-panel">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label" for="email">E-mail</label>
                <input type="email" class="form-control" id="email" name="email" required
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Mot de passe</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-hero btn-hero-primary w-100">Connexion</button>
            <p class="text-center mt-3 mb-0" style="color:var(--pb-muted);">
                Pas encore de compte ? <a href="client_register.php">Créer un compte</a>
            </p>
            <p class="text-center mt-2 mb-0 small" style="color:var(--pb-muted);">
                Personnel pharmacie : <a href="login.php">accès interne</a>
            </p>
        </form>
    </div>
</section>

<?php render_public_chrome_end(); ?>
