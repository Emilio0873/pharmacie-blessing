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
    $fullName = trim(strip_tags($_POST['full_name'] ?? ''));
    $email = strtolower(trim(strip_tags($_POST['email'] ?? '')));
    $phone = trim(strip_tags($_POST['phone'] ?? ''));
    $address = trim(strip_tags($_POST['address'] ?? ''));
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password_confirm'] ?? '';

    if ($fullName === '' || strlen($fullName) < 3) {
        $error = 'Indiquez votre nom complet.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse e-mail invalide.';
    } elseif (strlen(reservation_phone_key($phone)) < 8) {
        $error = 'Numéro de téléphone invalide.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($password !== $password2) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        try {
            $exists = $pdo->prepare("SELECT id FROM client_accounts WHERE email = ? LIMIT 1");
            $exists->execute([$email]);
            if ($exists->fetchColumn()) {
                throw new Exception('Un compte existe déjà avec cet e-mail. Connectez-vous.');
            }

            $clientId = find_or_create_client($pdo, $fullName, $phone, $email, $address !== '' ? $address : null);
            $pdo->prepare("UPDATE clients SET name = ?, email = ?, address = COALESCE(?, address) WHERE id = ?")
                ->execute([$fullName, $email, $address !== '' ? $address : null, $clientId]);

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO client_accounts (client_id, full_name, email, phone, password, status)
                VALUES (?, ?, ?, ?, ?, 'active')")
                ->execute([$clientId, $fullName, $email, $phone, $hash]);

            $_SESSION['client_account_id'] = (int)$pdo->lastInsertId();
            $_SESSION['client_id'] = $clientId;
            $_SESSION['client_name'] = $fullName;
            $_SESSION['client_email'] = $email;
            $_SESSION['client_phone'] = $phone;

            redirect('client_espace.php?welcome=1');
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

$v = static function ($key) {
    return htmlspecialchars($_POST[$key] ?? '');
};

render_public_chrome_start('Inscription — Pharmacie Blessing');
?>

<section class="auth-stage">
    <div class="auth-shell auth-shell-wide">
        <aside class="auth-visual" aria-hidden="true">
            <div class="auth-visual-bg" style="background-image:url('assets/img/b.jpeg');"></div>
            <div class="auth-visual-veil"></div>
            <div class="auth-visual-content">
                <img src="assets/img/pha.jpeg" alt="" class="auth-brand-mark logo-clock">
                <p class="auth-brand-name">Pharmacie Blessing</p>
                <h1 class="auth-visual-title">Créez votre compte en quelques minutes.</h1>
                <ul class="auth-benefits">
                    <li><i class="fa-solid fa-user-plus"></i> Inscription simple et rapide</li>
                    <li><i class="fa-solid fa-bell"></i> Suivi de vos commandes</li>
                    <li><i class="fa-solid fa-file-invoice"></i> Historique de factures</li>
                </ul>
            </div>
        </aside>

        <div class="auth-panel">
            <div class="auth-tabs" role="tablist" aria-label="Espace client">
                <a class="auth-tab" href="client_login.php" role="tab" aria-selected="false">Se connecter</a>
                <a class="auth-tab is-active" href="client_register.php" role="tab" aria-selected="true">Inscription</a>
            </div>

            <div class="auth-panel-head">
                <h2>Créer un compte</h2>
                <p>Rejoignez l’espace client Pharmacie Blessing.</p>
            </div>

            <form method="post" class="auth-form" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <?php if ($error): ?>
                    <div class="auth-alert" role="alert"><i class="fa-solid fa-circle-exclamation"></i><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <label class="auth-field" for="full_name">
                    <span>Nom complet</span>
                    <div class="auth-input">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" id="full_name" name="full_name" required maxlength="180" value="<?php echo $v('full_name'); ?>" placeholder="Jean Mukendi" autocomplete="name">
                    </div>
                </label>

                <div class="auth-grid-2">
                    <label class="auth-field" for="email">
                        <span>E-mail</span>
                        <div class="auth-input">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" id="email" name="email" required maxlength="180" value="<?php echo $v('email'); ?>" placeholder="vous@email.com" autocomplete="email">
                        </div>
                    </label>
                    <label class="auth-field" for="phone">
                        <span>Téléphone</span>
                        <div class="auth-input">
                            <i class="fa-solid fa-phone"></i>
                            <input type="tel" id="phone" name="phone" required maxlength="50" value="<?php echo $v('phone'); ?>" placeholder="+243 …" autocomplete="tel">
                        </div>
                    </label>
                </div>

                <label class="auth-field" for="address">
                    <span>Adresse <em>(optionnel)</em></span>
                    <div class="auth-input">
                        <i class="fa-solid fa-location-dot"></i>
                        <input type="text" id="address" name="address" maxlength="255" value="<?php echo $v('address'); ?>" placeholder="Commune, avenue…" autocomplete="street-address">
                    </div>
                </label>

                <div class="auth-grid-2">
                    <label class="auth-field" for="password">
                        <span>Mot de passe</span>
                        <div class="auth-input">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" id="password" name="password" required minlength="6" placeholder="6 caractères min." autocomplete="new-password">
                            <button type="button" class="auth-eye" data-toggle-password="password" aria-label="Afficher le mot de passe"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </label>
                    <label class="auth-field" for="password_confirm">
                        <span>Confirmation</span>
                        <div class="auth-input">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" id="password_confirm" name="password_confirm" required minlength="6" placeholder="Répétez le mot de passe" autocomplete="new-password">
                            <button type="button" class="auth-eye" data-toggle-password="password_confirm" aria-label="Afficher la confirmation"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </label>
                </div>

                <button type="submit" class="auth-submit">Créer mon compte <i class="fa-solid fa-arrow-right"></i></button>
            </form>

            <p class="auth-switch">Déjà inscrit ? <a href="client_login.php">Se connecter</a></p>
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
