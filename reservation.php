<?php
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/ui_shell.php';
require_once 'includes/public_chrome.php';

$error = '';
$products = $pdo->query(
    "SELECT p.id, p.name, p.code, p.sell_price, p.qty, p.image, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.qty > 0
     ORDER BY p.name ASC"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $error = 'La page a expiré. Rechargez-la puis validez à nouveau.';
    } else {
        $lastName = trim(strip_tags($_POST['last_name'] ?? ''));
        $firstName = trim(strip_tags($_POST['first_name'] ?? ''));
        $phone = trim(strip_tags($_POST['phone'] ?? ''));
        $email = trim(strip_tags($_POST['email'] ?? ''));
        $commune = trim(strip_tags($_POST['location_commune'] ?? ''));
        $avenue = trim(strip_tags($_POST['location_avenue'] ?? ''));
        $landmark = trim(strip_tags($_POST['location_landmark'] ?? ''));
        $addressExtra = trim(strip_tags($_POST['address'] ?? ''));
        $pickup = $_POST['pickup_date'] ?? '';
        $fulfillment = ($_POST['fulfillment_type'] ?? 'retrait_depot') === 'livraison_domicile' ? 'livraison_domicile' : 'retrait_depot';
        $geoLat = trim($_POST['geo_lat'] ?? '');
        $geoLng = trim($_POST['geo_lng'] ?? '');
        $cart = json_decode($_POST['cart_json'] ?? '[]', true);
        $address = $fulfillment === 'livraison_domicile'
            ? build_location_address($commune, $avenue, $landmark, $addressExtra)
            : $addressExtra;

        if ($lastName === '' || $firstName === '') {
            $error = 'Indiquez le nom et le prénom.';
        } elseif (strlen(reservation_phone_key($phone)) < 8) {
            $error = 'Indiquez un numéro de téléphone valide.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'L’adresse e-mail n’est pas valide.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $pickup) || $pickup < date('Y-m-d')) {
            $error = $fulfillment === 'livraison_domicile'
                ? 'Indiquez le jour de livraison souhaité.'
                : 'Indiquez le jour de récupération de votre marchandise au dépôt.';
        } elseif ($fulfillment === 'livraison_domicile' && ($commune === '' || $avenue === '' || $landmark === '')) {
            $error = 'Pour la livraison, renseignez la commune/quartier, l’avenue ou rue, et un point de repère.';
        } elseif ($fulfillment === 'livraison_domicile' && (!is_numeric($geoLat) || !is_numeric($geoLng))) {
            $error = 'Partagez votre position GPS pour que le livreur puisse vous localiser.';
        } elseif (!is_array($cart) || count($cart) === 0) {
            $error = 'Ajoutez au moins un produit au panier.';
        } else {
            try {
                $pdo->beginTransaction();
                $lines = [];
                $subtotal = 0;
                foreach ($cart as $item) {
                    $productId = (int)($item['id'] ?? 0);
                    $qty = (int)($item['qty'] ?? 0);
                    if ($productId <= 0 || $qty <= 0) {
                        throw new Exception('Article invalide dans le panier.');
                    }
                    $stmt = $pdo->prepare("SELECT id, name, code, sell_price, qty FROM products WHERE id = ?");
                    $stmt->execute([$productId]);
                    $product = $stmt->fetch();
                    if (!$product) {
                        throw new Exception('Un produit du panier n’est plus disponible.');
                    }
                    if ($qty > (int)$product['qty']) {
                        throw new Exception('La quantité demandée pour « ' . $product['name'] . ' » dépasse le stock affiché (' . (int)$product['qty'] . ').');
                    }
                    $price = (float)$product['sell_price'];
                    $lineTotal = $price * $qty;
                    $subtotal += $lineTotal;
                    $lines[] = [
                        'product_id' => (int)$product['id'],
                        'product_name' => $product['name'],
                        'product_code' => $product['code'],
                        'qty' => $qty,
                        'unit_price' => $price,
                        'line_total' => $lineTotal,
                    ];
                }

                $tempRef = 'TMP-' . bin2hex(random_bytes(8));
                $stmt = $pdo->prepare("INSERT INTO reservations
                    (reference, last_name, first_name, phone, email, address, pickup_date, status, subtotal, fulfillment_type, geo_lat, geo_lng, location_commune, location_avenue, location_landmark)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente', ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $tempRef,
                    $lastName,
                    $firstName,
                    $phone,
                    $email !== '' ? $email : null,
                    $address !== '' ? $address : null,
                    $pickup,
                    $subtotal,
                    $fulfillment,
                    $fulfillment === 'livraison_domicile' ? (float)$geoLat : null,
                    $fulfillment === 'livraison_domicile' ? (float)$geoLng : null,
                    $fulfillment === 'livraison_domicile' ? $commune : null,
                    $fulfillment === 'livraison_domicile' ? $avenue : null,
                    $fulfillment === 'livraison_domicile' ? $landmark : null,
                ]);
                $reservationId = (int)$pdo->lastInsertId();
                $reference = format_reservation_number($reservationId);
                $pdo->prepare("UPDATE reservations SET reference = ? WHERE id = ?")->execute([$reference, $reservationId]);

                $lineStmt = $pdo->prepare("INSERT INTO reservation_items
                    (reservation_id, product_id, product_name, product_code, qty, unit_price, line_total)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($lines as $line) {
                    $lineStmt->execute([
                        $reservationId,
                        $line['product_id'],
                        $line['product_name'],
                        $line['product_code'],
                        $line['qty'],
                        $line['unit_price'],
                        $line['line_total'],
                    ]);
                }

                $pdo->commit();
                $_SESSION['reservation_phone'] = reservation_phone_key($phone);
                redirect('proforma.php?ref=' . urlencode($reference));
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e->getMessage();
            }
        }
    }
}

render_public_chrome_start('Réserver une commande — Pharmacie Blessing');
?>

<section class="section section-products">
    <div class="container">
        <div class="section-head">
            <span class="section-kicker">Espace client</span>
            <h2 class="section-title">Réserver une commande</h2>
            <p class="section-text">Choisissez les produits, le mode (retrait au dépôt ou livraison à domicile), la date, puis vos coordonnées. Cette réservation n’est pas un paiement : elle est transmise au facturier.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" id="reservationForm" class="row g-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="cart_json" id="cartJson" value="[]">

            <div class="col-12 col-lg-7">
                <div class="reserve-panel">
                    <h3 class="reserve-title">Produits disponibles</h3>
                    <?php if (empty($products)): ?>
                        <p class="mb-0 text-muted">Aucun produit en stock pour le moment.</p>
                    <?php else: ?>
                        <div class="reserve-products">
                            <?php foreach ($products as $product): ?>
                                <article class="reserve-product"
                                    data-id="<?php echo (int)$product['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-price="<?php echo (float)$product['sell_price']; ?>"
                                    data-stock="<?php echo (int)$product['qty']; ?>">
                                    <div>
                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                        <div class="reserve-meta"><?php echo htmlspecialchars($product['category_name'] ?: 'Produit'); ?> · Stock <?php echo (int)$product['qty']; ?></div>
                                    </div>
                                    <div class="reserve-product-side">
                                        <span><?php echo number_format((float)$product['sell_price'], 0, ',', ' '); ?> FC</span>
                                        <button type="button" class="btn-reserve-add">Ajouter</button>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <div class="reserve-panel">
                    <h3 class="reserve-title">Panier</h3>
                    <div id="cartEmpty" class="text-muted">Le panier est vide.</div>
                    <div id="cartLines"></div>
                    <div class="reserve-total">
                        <span>Montant prévisionnel</span>
                        <strong id="cartTotal">0 FC</strong>
                    </div>

                    <h3 class="reserve-title mt-4">Mode et coordonnées</h3>
                    <div class="mb-3">
                        <label class="form-label d-block">Mode de remise</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="fulfillment_type" id="modeRetrait" value="retrait_depot" <?php echo (($_POST['fulfillment_type'] ?? 'retrait_depot') !== 'livraison_domicile') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="modeRetrait">À récupérer au dépôt</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="fulfillment_type" id="modeLivraison" value="livraison_domicile" <?php echo (($_POST['fulfillment_type'] ?? '') === 'livraison_domicile') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="modeLivraison">Livraison à domicile</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="pickup_date" id="dateLabel">Jour de récupération de la marchandise</label>
                        <input type="date" class="form-control" id="pickup_date" name="pickup_date" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['pickup_date'] ?? ''); ?>">
                        <small class="text-muted" id="dateHelp">Obligatoire si vous récupérez au dépôt.</small>
                    </div>
                    <div class="row g-2">
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="last_name">Nom</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required maxlength="100" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="first_name">Prénom</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required maxlength="100" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mb-3 mt-2">
                        <label class="form-label" for="phone">Téléphone (joignable)</label>
                        <input type="tel" class="form-control" id="phone" name="phone" required maxlength="30" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">E-mail (facultatif)</label>
                        <input type="email" class="form-control" id="email" name="email" maxlength="150" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    <div id="deliveryFields" class="d-none">
                        <div class="mb-3">
                            <label class="form-label" for="location_commune">Commune / quartier</label>
                            <input type="text" class="form-control" id="location_commune" name="location_commune" maxlength="120" value="<?php echo htmlspecialchars($_POST['location_commune'] ?? ''); ?>" placeholder="Ex: Gombe, Lemba…">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="location_avenue">Avenue / rue / numéro</label>
                            <input type="text" class="form-control" id="location_avenue" name="location_avenue" maxlength="180" value="<?php echo htmlspecialchars($_POST['location_avenue'] ?? ''); ?>" placeholder="Ex: Av. de la Liberte n°12">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="location_landmark">Point de repère</label>
                            <input type="text" class="form-control" id="location_landmark" name="location_landmark" maxlength="180" value="<?php echo htmlspecialchars($_POST['location_landmark'] ?? ''); ?>" placeholder="Ex: près de l’église, après le marché…">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="address">Complément d’adresse (facultatif)</label>
                            <input type="text" class="form-control" id="address" name="address" maxlength="255" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <input type="hidden" name="geo_lat" id="geo_lat" value="<?php echo htmlspecialchars($_POST['geo_lat'] ?? ''); ?>">
                            <input type="hidden" name="geo_lng" id="geo_lng" value="<?php echo htmlspecialchars($_POST['geo_lng'] ?? ''); ?>">
                            <button type="button" class="btn btn-outline-primary w-100" id="btnGeo">Partager ma position GPS</button>
                            <p class="reserve-note mb-0" id="geoStatus">Obligatoire pour localiser la livraison.</p>
                        </div>
                    </div>
                    <button type="submit" class="btn-hero btn-hero-primary w-100" id="btnReserve" <?php echo empty($products) ? 'disabled' : ''; ?>>Valider la réservation</button>
                    <p class="reserve-note">Pro forma uniquement. Paiement au dépôt : facturier / caisse, puis livreur.</p>
                </div>
            </div>
        </form>
    </div>
</section>

<script>
(function () {
    var cart = [];
    var linesEl = document.getElementById('cartLines');
    var emptyEl = document.getElementById('cartEmpty');
    var totalEl = document.getElementById('cartTotal');
    var jsonEl = document.getElementById('cartJson');

    function money(n) {
        return Math.round(n).toLocaleString('fr-FR') + ' FC';
    }

    function render() {
        if (cart.length === 0) {
            linesEl.innerHTML = '';
            emptyEl.classList.remove('d-none');
            totalEl.textContent = '0 FC';
            jsonEl.value = '[]';
            return;
        }
        emptyEl.classList.add('d-none');
        var total = 0;
        linesEl.innerHTML = cart.map(function (item, index) {
            var line = item.price * item.qty;
            total += line;
            return '<div class="reserve-line">' +
                '<div><strong>' + item.name + '</strong><div class="reserve-meta">' + money(item.price) + '</div></div>' +
                '<div class="reserve-qty">' +
                    '<button type="button" data-index="' + index + '" data-delta="-1">−</button>' +
                    '<span>' + item.qty + '</span>' +
                    '<button type="button" data-index="' + index + '" data-delta="1">+</button>' +
                '</div>' +
                '<div>' + money(line) + '</div>' +
            '</div>';
        }).join('');
        totalEl.textContent = money(total);
        jsonEl.value = JSON.stringify(cart.map(function (item) {
            return { id: item.id, qty: item.qty };
        }));
    }

    document.querySelectorAll('.btn-reserve-add').forEach(function (button) {
        button.addEventListener('click', function () {
            var card = button.closest('.reserve-product');
            var id = card.dataset.id;
            var stock = parseInt(card.dataset.stock, 10) || 0;
            var existing = cart.find(function (item) { return item.id === id; });
            if (existing) {
                if (existing.qty >= stock) return;
                existing.qty += 1;
            } else {
                cart.push({
                    id: id,
                    name: card.dataset.name,
                    price: parseFloat(card.dataset.price) || 0,
                    qty: 1,
                    stock: stock
                });
            }
            render();
        });
    });

    linesEl.addEventListener('click', function (event) {
        var button = event.target.closest('button');
        if (!button) return;
        var index = parseInt(button.dataset.index, 10);
        var delta = parseInt(button.dataset.delta, 10);
        if (!cart[index]) return;
        cart[index].qty += delta;
        if (cart[index].qty > cart[index].stock) cart[index].qty = cart[index].stock;
        if (cart[index].qty <= 0) cart.splice(index, 1);
        render();
    });

    function syncFulfillmentUI() {
        var delivery = document.getElementById('modeLivraison').checked;
        document.getElementById('dateLabel').textContent = delivery ? 'Jour de livraison souhaité' : 'Jour de récupération de la marchandise';
        document.getElementById('dateHelp').textContent = delivery
            ? 'Indiquez le jour où le livreur doit venir.'
            : 'Obligatoire : jour où vous récupérez votre marchandise au dépôt.';
        document.getElementById('deliveryFields').classList.toggle('d-none', !delivery);
        ['location_commune', 'location_avenue', 'location_landmark'].forEach(function (id) {
            document.getElementById(id).required = delivery;
        });
    }
    document.getElementById('modeRetrait').addEventListener('change', syncFulfillmentUI);
    document.getElementById('modeLivraison').addEventListener('change', syncFulfillmentUI);
    syncFulfillmentUI();

    document.getElementById('btnGeo').addEventListener('click', function () {
        var status = document.getElementById('geoStatus');
        if (!navigator.geolocation) {
            status.textContent = 'La géolocalisation n’est pas disponible sur cet appareil.';
            return;
        }
        status.textContent = 'Localisation en cours…';
        navigator.geolocation.getCurrentPosition(function (pos) {
            document.getElementById('geo_lat').value = pos.coords.latitude.toFixed(7);
            document.getElementById('geo_lng').value = pos.coords.longitude.toFixed(7);
            status.textContent = 'Position GPS enregistrée.';
        }, function () {
            status.textContent = 'Impossible d’obtenir la position. Autorisez la localisation.';
        }, { enableHighAccuracy: true, timeout: 15000 });
    });

    document.getElementById('reservationForm').addEventListener('submit', function (event) {
        if (cart.length === 0) {
            event.preventDefault();
            alert('Ajoutez au moins un produit au panier.');
            return;
        }
        if (!document.getElementById('pickup_date').value) {
            event.preventDefault();
            alert('Indiquez le jour de récupération ou de livraison.');
            return;
        }
        if (document.getElementById('modeLivraison').checked) {
            if (!document.getElementById('location_commune').value || !document.getElementById('location_avenue').value || !document.getElementById('location_landmark').value) {
                event.preventDefault();
                alert('Renseignez commune/quartier, avenue/rue et point de repère.');
                return;
            }
            if (!document.getElementById('geo_lat').value || !document.getElementById('geo_lng').value) {
                event.preventDefault();
                alert('Partagez votre position GPS pour la livraison à domicile.');
            }
        }
    });
})();
</script>

<?php render_public_chrome_end(); ?>
