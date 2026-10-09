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
$success = '';

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

render_public_chrome_start('Créer mon compte — Pharmacie Blessing');
?>

<section class="section section-products">
    <div class="container" style="max-width: 560px;">
        <div class="section-head">
            <span class="section-kicker">Espace client</span>
            <h2 class="section-title">Créer mon compte</h2>
            <p class="section-text">Inscrivez-vous pour suivre vos commandes, réservations et factures en ligne.</p>
        </div>

        <form method="post" class="reserve-panel">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label" for="full_name">Nom complet</label>
                <input type="text" class="form-control" id="full_name" name="full_name" required maxlength="180"
                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">E-mail</label>
                <input type="email" class="form-control" id="email" name="email" required maxlength="180"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="phone">Téléphone</label>
                <input type="tel" class="form-control" id="phone" name="phone" required maxlength="50"
                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="+243 …">
            </div>
            <div class="mb-3">
                <label class="form-label" for="address">Adresse (optionnel)</label>
                <input type="text" class="form-control" id="address" name="address" maxlength="255"
                       value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Mot de passe</label>
                <input type="password" class="form-control" id="password" name="password" required minlength="6">
            </div>
            <div class="mb-3">
                <label class="form-label" for="password_confirm">Confirmer le mot de passe</label>
                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="6">
            </div>
            <button type="submit" class="btn-hero btn-hero-primary w-100">Créer mon compte</button>
            <p class="text-center mt-3 mb-0" style="color:var(--pb-muted);">
                Déjà inscrit ? <a href="client_login.php">Se connecter</a>
            </p>
        </form>
    </div>
</section>

<?php render_public_chrome_end(); ?>
