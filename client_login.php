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

$emailValue = htmlspecialchars($_POST['email'] ?? '');
render_public_chrome_start('Se connecter — Pharmacie Blessing');
?>

<section class="auth-stage">
    <div class="auth-shell">
        <aside class="auth-visual" aria-hidden="true">
            <div class="auth-visual-bg" style="background-image:url('assets/img/a.jpeg');"></div>
            <div class="auth-visual-veil"></div>
            <div class="auth-visual-content">
                <img src="assets/img/pha.jpeg" alt="" class="auth-brand-mark logo-clock">
                <p class="auth-brand-name">Pharmacie Blessing</p>
                <h1 class="auth-visual-title">Votre espace santé, toujours à portée de main.</h1>
                <ul class="auth-benefits">
                    <li><i class="fa-solid fa-receipt"></i> Factures et proformas en un clic</li>
                    <li><i class="fa-solid fa-truck-fast"></i> Suivi retrait &amp; livraison</li>
                    <li><i class="fa-solid fa-shield-heart"></i> Compte sécurisé et personnel</li>
                </ul>
            </div>
        </aside>

        <div class="auth-panel">
            <div class="auth-tabs" role="tablist" aria-label="Espace client">
                <a class="auth-tab is-active" href="client_login.php" role="tab" aria-selected="true">Se connecter</a>
                <a class="auth-tab" href="client_register.php" role="tab" aria-selected="false">Inscription</a>
            </div>

            <div class="auth-panel-head">
                <h2>Bon retour</h2>
                <p>Connectez-vous pour retrouver vos réservations et factures.</p>
            </div>

            <form method="post" class="auth-form" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <?php if ($error): ?>
                    <div class="auth-alert" role="alert"><i class="fa-solid fa-circle-exclamation"></i><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <label class="auth-field" for="email">
                    <span>E-mail</span>
                    <div class="auth-input">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" id="email" name="email" required value="<?php echo $emailValue; ?>" placeholder="vous@email.com" autocomplete="email">
                    </div>
                </label>

                <label class="auth-field" for="password">
                    <span>Mot de passe</span>
                    <div class="auth-input">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="password" name="password" required placeholder="••••••••" autocomplete="current-password">
                        <button type="button" class="auth-eye" data-toggle-password="password" aria-label="Afficher le mot de passe"><i class="fa-solid fa-eye"></i></button>
                    </div>
                </label>

                <button type="submit" class="auth-submit">Se connecter <i class="fa-solid fa-arrow-right"></i></button>
            </form>

            <p class="auth-switch">Pas encore de compte ? <a href="client_register.php">Créer un compte</a></p>
            <p class="auth-staff"><a href="login.php">Accès personnel pharmacie</a></p>
        </div>
    </div>
</section>

<script>
document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = document.getElementById(btn.getAttribute('data-toggle-password'));
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.innerHTML = show ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
    });
});
</script>

<?php render_public_chrome_end(); ?>
