<?php
$page_title = "Nouvel Achat - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin', 'Magasinier']);

$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll();
$products = $pdo->query("SELECT * FROM products ORDER BY name ASC")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="row g-4 pos-layout">
    <!-- Product Selection (Left) -->
    <div class="col-12 col-lg-8 d-flex flex-column pos-products-pane">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-3">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="purchaseSearch" class="form-control border-start-0" placeholder="Rechercher un produit à commander...">
                </div>
            </div>
        </div>

        <div class="row g-3 overflow-auto flex-grow-1 px-1" id="productList">
            <?php foreach ($products as $p): ?>
                <div class="col-6 col-md-4 col-lg-3 product-item" data-id="<?php echo $p['id']; ?>" data-name="<?php echo $p['name']; ?>" data-price="<?php echo $p['buy_price']; ?>" data-code="<?php echo $p['code'] ?? ''; ?>">
                    <div class="card shadow-sm h-100 border-primary border-opacity-25 pos-product-card">
                        <div class="card-body p-2 text-center">
                            <h6 class="mb-1 fw-bold text-truncate" title="<?php echo $p['name']; ?>"><?php echo $p['name']; ?></h6>
                            <p class="text-primary fw-bold mb-1 small">PA: <?php echo format_currency($p['buy_price']); ?></p>
                            <span class="badge bg-light text-dark small">Stock: <?php echo $p['qty']; ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Cart (Right) -->
    <div class="col-12 col-lg-4 d-flex flex-column pos-cart-pane">
        <div class="card shadow-sm border-0 d-flex flex-column h-100">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="fw-bold mb-0 text-success"><i class="fas fa-shopping-basket me-2"></i> Panier d'Achat</h5>
            </div>
            
            <div class="card-body p-0 overflow-auto flex-grow-1">
                <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="sticky-top bg-light">
                        <tr>
                            <th class="ps-3">Article</th>
                            <th style="width: 80px;">Qté</th>
                            <th class="text-end pe-3">Total</th>
                        </tr>
                    </thead>
                    <tbody id="cartItems">
                        <!-- Cart items injected by JS -->
                    </tbody>
                </table>
                </div>
                <div id="emptyCart" class="text-center py-5 text-muted">
                    <i class="fas fa-box-open fa-3x mb-3 opacity-25"></i>
                    <p>La commande est vide</p>
                </div>
            </div>

            <div class="card-footer bg-white border-0 p-4 shadow-sm mt-auto">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted">Fournisseur *</label>
                    <select class="form-select form-select-sm" id="supplierSelect">
                        <option value="">Sélectionner un fournisseur</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-4">
                    <span class="h5 fw-bold">TOTAL</span>
                    <span class="h5 fw-bold text-success" id="finalTotal">0 FC</span>
                </div>
                <div class="d-grid gap-2">
                    <button class="btn btn-success py-3 fw-bold" id="btnSavePurchase" disabled>
                        <i class="fas fa-check-circle me-2"></i> VALIDER L'ACHAT
                    </button>
                    <button class="btn btn-light" id="btnClearCart">Vider le panier</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let cart = [];

function updateCart() {
    const cartItems = document.getElementById('cartItems');
    const emptyCart = document.getElementById('emptyCart');
    const finalTotalEl = document.getElementById('finalTotal');
    const btnSave = document.getElementById('btnSavePurchase');

    if (cart.length === 0) {
        cartItems.innerHTML = '';
        emptyCart.classList.remove('d-none');
        finalTotalEl.innerText = '0 FC';
        btnSave.disabled = true;
        return;
    }

    emptyCart.classList.add('d-none');
    cartItems.innerHTML = '';
    let total = 0;

    cart.forEach((item, index) => {
        const itemTotal = item.price * item.qty;
        total += itemTotal;
        cartItems.innerHTML += `
            <tr>
                <td class="ps-3">
                    <div class="fw-600 small">${item.name}</div>
                    <div class="input-group input-group-sm mt-1" style="width: 100px;">
                        <span class="input-group-text">PA:</span>
                        <input type="number" class="form-control px-1" value="${item.price}" onchange="changePrice(${index}, this.value)" min="0" step="1">
                    </div>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm text-center px-0" value="${item.qty}" onchange="changeQty(${index}, this.value)" min="1">
                    <div class="text-center mt-1">
                        <button class="btn btn-xs btn-link text-danger p-0 border-0" onclick="removeFromCart(${index})"><i class="fas fa-trash small"></i></button>
                    </div>
                </td>
                <td class="text-end fw-bold small pe-3 align-middle">${itemTotal.toLocaleString()}</td>
            </tr>
        `;
    });

    finalTotalEl.innerText = total.toLocaleString() + ' FC';
    
    // Check if supplier is selected
    const hasSupplier = document.getElementById('supplierSelect').value !== '';
    btnSave.disabled = !hasSupplier;
}

document.getElementById('supplierSelect').addEventListener('change', updateCart);

function changeQty(index, val) {
    let newQty = parseInt(val);
    if(newQty > 0) {
        cart[index].qty = newQty;
    } else {
        cart.splice(index, 1);
    }
    updateCart();
}

function changePrice(index, val) {
    let newPrice = parseFloat(val);
    if(newPrice >= 0) {
        cart[index].price = newPrice;
    }
    updateCart();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    updateCart();
}

document.querySelectorAll('.product-item').forEach(item => {
    item.addEventListener('click', function() {
        const id = this.dataset.id;
        const name = this.dataset.name;
        const price = parseFloat(this.dataset.price);

        const existingItem = cart.find(i => i.id === id);
        if (existingItem) {
            existingItem.qty++;
        } else {
            cart.push({ id, name, price, qty: 1 });
        }
        updateCart();
    });
});

document.getElementById('purchaseSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.product-item').forEach(item => {
        const text = item.innerText.toLowerCase();
        const code = (item.dataset.code || '').toLowerCase();
        if (q === '' || text.includes(q) || code.includes(q)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
});

document.getElementById('btnClearCart').addEventListener('click', () => {
    cart = [];
    updateCart();
});

document.getElementById('btnSavePurchase').addEventListener('click', function() {
    const supplier_id = document.getElementById('supplierSelect').value;
    if (!supplier_id) {
        alert("Veuillez sélectionner un fournisseur.");
        return;
    }

    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';

    const data = {
        cart: cart,
        total_amount: cart.reduce((sum, item) => sum + (item.price * item.qty), 0),
        supplier_id: supplier_id
    };

    fetch('save_purchase.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            alert('Achat enregistré. Le stock a été mis à jour.');
            window.location.href = 'index.php';
        } else {
            alert('Erreur: ' + res.message);
            btn.disabled = false;
            btn.innerHTML = 'VALIDER L\'ACHAT';
        }
    })
    .catch(err => {
        console.error(err);
        alert('Une erreur est survenue.');
        btn.disabled = false;
        btn.innerHTML = 'VALIDER L\'ACHAT';
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
