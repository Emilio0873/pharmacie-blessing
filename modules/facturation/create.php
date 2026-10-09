<?php
$page_title = "Nouvelle commande comptoir - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Facturier']);

ensure_counter_order_tables($pdo);

$error = '';
$products = $pdo->query(
    "SELECT id, name, code, sell_price, qty FROM products WHERE qty > 0 ORDER BY name ASC"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $error = 'Session expirée. Rechargez la page.';
    } else {
        $clientName = trim(strip_tags($_POST['client_name'] ?? ''));
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

        if ($clientName === '' || strlen(reservation_phone_key($phone)) < 8) {
            $error = 'Indiquez le nom du client et un téléphone valide.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $pickup) || $pickup < date('Y-m-d')) {
            $error = $fulfillment === 'livraison_domicile'
                ? 'Indiquez le jour de livraison.'
                : 'Indiquez le jour de récupération de la marchandise.';
        } elseif ($fulfillment === 'livraison_domicile' && ($commune === '' || $avenue === '' || $landmark === '')) {
            $error = 'Pour la livraison : commune/quartier, avenue/rue et point de repère sont obligatoires.';
        } elseif ($fulfillment === 'livraison_domicile' && (!is_numeric($geoLat) || !is_numeric($geoLng))) {
            $error = 'Géolocalisez le client pour la livraison à domicile.';
        } elseif (!is_array($cart) || !$cart) {
            $error = 'Ajoutez au moins un produit.';
        } else {
            try {
                $pdo->beginTransaction();
                $lines = [];
                $subtotal = 0;
                foreach ($cart as $row) {
                    $pid = (int)($row['id'] ?? 0);
                    $qty = (int)($row['qty'] ?? 0);
                    $p = $pdo->prepare("SELECT id, name, code, sell_price, qty FROM products WHERE id = ?");
                    $p->execute([$pid]);
                    $product = $p->fetch();
                    if (!$product || $qty <= 0 || $qty > (int)$product['qty']) {
                        throw new Exception('Stock insuffisant ou produit invalide.');
                    }
                    $lineTotal = (float)$product['sell_price'] * $qty;
                    $subtotal += $lineTotal;
                    $lines[] = [
                        'product_id' => (int)$product['id'],
                        'product_name' => $product['name'],
                        'product_code' => $product['code'],
                        'qty' => $qty,
                        'unit_price' => (float)$product['sell_price'],
                        'line_total' => $lineTotal,
                    ];
                }

                $tmp = 'TMP-' . bin2hex(random_bytes(6));
                $pdo->prepare("INSERT INTO counter_orders
                    (reference, client_name, phone, email, address, fulfillment_type, geo_lat, geo_lng, pickup_date, status, subtotal, created_by, location_commune, location_avenue, location_landmark)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_caisse', ?, ?, ?, ?, ?)")
                    ->execute([
                        $tmp,
                        $clientName,
                        $phone,
                        $email !== '' ? $email : null,
                        $address !== '' ? $address : null,
                        $fulfillment,
                        $fulfillment === 'livraison_domicile' ? (float)$geoLat : null,
                        $fulfillment === 'livraison_domicile' ? (float)$geoLng : null,
                        $pickup,
                        $subtotal,
                        (int)$_SESSION['user_id'],
                        $fulfillment === 'livraison_domicile' ? $commune : null,
                        $fulfillment === 'livraison_domicile' ? $avenue : null,
                        $fulfillment === 'livraison_domicile' ? $landmark : null,
                    ]);
                $orderId = (int)$pdo->lastInsertId();
                $ref = format_counter_order_number($orderId);
                $pdo->prepare("UPDATE counter_orders SET reference = ? WHERE id = ?")->execute([$ref, $orderId]);

                $ins = $pdo->prepare("INSERT INTO counter_order_items
                    (order_id, product_id, product_name, product_code, qty, unit_price, line_total)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($lines as $line) {
                    $ins->execute([$orderId, $line['product_id'], $line['product_name'], $line['product_code'], $line['qty'], $line['unit_price'], $line['line_total']]);
                }

                log_activity($pdo, (int)$_SESSION['user_id'], 'Commande comptoir', "$ref transférée à la caisse");
                $pdo->commit();
                redirect('view.php?id=' . $orderId . '&sent=1');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="mb-4">
    <a href="index.php" class="btn btn-light btn-sm mb-3">Retour</a>
    <h3 class="fw-bold">Nouvelle commande (facturier)</h3>
    <p class="text-muted">Procédure physique : client → facturier (cette page) → transfert caisse → paiement → livreur (livraison ou à retirer).</p>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<form method="post" id="counterForm" class="row g-4">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
    <input type="hidden" name="cart_json" id="cartJson" value="[]">
    <input type="hidden" name="geo_lat" id="geo_lat">
    <input type="hidden" name="geo_lng" id="geo_lng">

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="fw-bold">Produits</h5>
                <div class="list-group" style="max-height:420px;overflow:auto;">
                    <?php foreach ($products as $p): ?>
                        <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center add-product"
                            data-id="<?php echo (int)$p['id']; ?>"
                            data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>"
                            data-price="<?php echo (float)$p['sell_price']; ?>"
                            data-stock="<?php echo (int)$p['qty']; ?>">
                            <span><?php echo htmlspecialchars($p['name']); ?> <small class="text-muted">(stock <?php echo (int)$p['qty']; ?>)</small></span>
                            <strong><?php echo format_currency($p['sell_price']); ?></strong>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h5 class="fw-bold">Panier</h5>
                <div id="cartEmpty" class="text-muted">Vide</div>
                <div id="cartLines"></div>
                <div class="d-flex justify-content-between mt-3">
                    <span>Total</span>
                    <strong id="cartTotal">0 FC</strong>
                </div>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="mb-2">
                    <label class="form-label">Client</label>
                    <input class="form-control" name="client_name" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Téléphone</label>
                    <input class="form-control" name="phone" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">E-mail (facultatif)</label>
                    <input class="form-control" type="email" name="email">
                </div>
                <div class="mb-2">
                    <label class="form-label d-block">Mode</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="fulfillment_type" id="cRetrait" value="retrait_depot" checked>
                        <label class="form-check-label" for="cRetrait">À récupérer</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="fulfillment_type" id="cLivraison" value="livraison_domicile">
                        <label class="form-check-label" for="cLivraison">Livraison</label>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="pickup_date" id="dateLabel">Jour de récupération</label>
                    <input type="date" class="form-control" name="pickup_date" id="pickup_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div id="deliveryFields" class="d-none">
                    <div class="mb-2">
                        <label class="form-label">Commune / quartier</label>
                        <input class="form-control" name="location_commune" id="location_commune">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Avenue / rue / n°</label>
                        <input class="form-control" name="location_avenue" id="location_avenue">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Point de repère</label>
                        <input class="form-control" name="location_landmark" id="location_landmark">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Complément</label>
                        <input class="form-control" name="address" id="address">
                    </div>
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-primary w-100" id="btnGeo">Géolocaliser le client</button>
                        <small class="text-muted" id="geoStatus">GPS obligatoire pour la livraison.</small>
                    </div>
                </div>
                <button class="btn btn-primary w-100 fw-bold" type="submit">Transférer à la caisse (paiement)</button>
            </div>
        </div>
    </div>
</form>

<script>
(function(){
    var cart=[];
    function money(n){return Math.round(n).toLocaleString('fr-FR')+' FC';}
    function render(){
        var empty=document.getElementById('cartEmpty');
        var lines=document.getElementById('cartLines');
        var total=0;
        if(!cart.length){empty.classList.remove('d-none');lines.innerHTML='';document.getElementById('cartTotal').textContent='0 FC';document.getElementById('cartJson').value='[]';return;}
        empty.classList.add('d-none');
        lines.innerHTML=cart.map(function(item,i){
            total+=item.price*item.qty;
            return '<div class="d-flex justify-content-between align-items-center py-2 border-bottom"><div><strong>'+item.name+'</strong><div class="small text-muted">'+money(item.price)+'</div></div><div class="btn-group btn-group-sm"><button type="button" data-i="'+i+'" data-d="-1" class="btn btn-outline-secondary">-</button><span class="px-2">'+item.qty+'</span><button type="button" data-i="'+i+'" data-d="1" class="btn btn-outline-secondary">+</button></div></div>';
        }).join('');
        document.getElementById('cartTotal').textContent=money(total);
        document.getElementById('cartJson').value=JSON.stringify(cart.map(function(i){return{id:i.id,qty:i.qty};}));
    }
    document.querySelectorAll('.add-product').forEach(function(btn){
        btn.addEventListener('click',function(){
            var id=this.dataset.id, stock=parseInt(this.dataset.stock,10)||0;
            var ex=cart.find(function(x){return x.id===id;});
            if(ex){if(ex.qty<stock)ex.qty++;}else{cart.push({id:id,name:this.dataset.name,price:parseFloat(this.dataset.price)||0,qty:1,stock:stock});}
            render();
        });
    });
    document.getElementById('cartLines').addEventListener('click',function(e){
        var b=e.target.closest('button'); if(!b)return;
        var i=parseInt(b.dataset.i,10), d=parseInt(b.dataset.d,10);
        cart[i].qty+=d; if(cart[i].qty>cart[i].stock)cart[i].qty=cart[i].stock; if(cart[i].qty<=0)cart.splice(i,1); render();
    });
    function sync(){
        var d=document.getElementById('cLivraison').checked;
        document.getElementById('deliveryFields').classList.toggle('d-none',!d);
        document.getElementById('dateLabel').textContent=d?'Jour de livraison':'Jour de récupération';
        ['location_commune','location_avenue','location_landmark'].forEach(function(id){document.getElementById(id).required=d;});
    }
    document.getElementById('cRetrait').addEventListener('change',sync);
    document.getElementById('cLivraison').addEventListener('change',sync);
    sync();
    document.getElementById('btnGeo').addEventListener('click',function(){
        var s=document.getElementById('geoStatus');
        if(!navigator.geolocation){s.textContent='Géolocalisation indisponible.';return;}
        s.textContent='Localisation…';
        navigator.geolocation.getCurrentPosition(function(pos){
            document.getElementById('geo_lat').value=pos.coords.latitude.toFixed(7);
            document.getElementById('geo_lng').value=pos.coords.longitude.toFixed(7);
            s.textContent='Position OK';
        },function(){s.textContent='Échec de géolocalisation.';});
    });
    document.getElementById('counterForm').addEventListener('submit',function(e){
        if(!cart.length){e.preventDefault();alert('Ajoutez des produits.');return;}
        if(!document.getElementById('pickup_date').value){e.preventDefault();alert('Indiquez le jour de récupération ou de livraison.');return;}
        if(document.getElementById('cLivraison').checked){
            if(!document.getElementById('location_commune').value||!document.getElementById('location_avenue').value||!document.getElementById('location_landmark').value){
                e.preventDefault();alert('Complétez commune, avenue et point de repère.');return;
            }
            if(!document.getElementById('geo_lat').value||!document.getElementById('geo_lng').value){
                e.preventDefault();alert('Géolocalisez le client.');
            }
        }
    });
})();
</script>

<?php require_once '../../includes/footer.php'; ?>
