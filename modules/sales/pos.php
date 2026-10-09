<?php
$page_title = "Vente Rapide (POS) - PHARMACIE BLESSING";
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!is_logged_in()) redirect('../../index.php');
authorize(['Super Admin', 'Admin']);

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$products = $pdo->query("SELECT p.*, c.name as category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.qty > 0 ORDER BY p.name ASC")->fetchAll();
$clients = $pdo->query("SELECT id, name, phone FROM clients ORDER BY name ASC")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="row g-4 pos-layout">
    <!-- Product Selection (Left) -->
    <div class="col-12 col-lg-8 d-flex flex-column pos-products-pane">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-3">
                <div class="row g-2">
                    <div class="col-12 col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="posSearch" class="form-control border-start-0" placeholder="Rechercher un produit ou scanner...">
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="d-flex overflow-auto pb-1" id="categoryFilters">
                            <button class="btn btn-primary btn-sm me-2 text-nowrap filter-cat active" data-cat="0">Tous</button>
                            <?php foreach ($categories as $cat): ?>
                                <button class="btn btn-outline-primary btn-sm me-2 text-nowrap filter-cat" data-cat="<?php echo $cat['id']; ?>">
                                    <?php echo $cat['name']; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 overflow-auto flex-grow-1 px-1" id="productList">
            <?php foreach ($products as $p): ?>
                <div class="col-6 col-md-4 col-lg-3 product-item" data-id="<?php echo $p['id']; ?>" data-name="<?php echo $p['name']; ?>" data-price="<?php echo $p['sell_price']; ?>" data-cat="<?php echo $p['category_id']; ?>" data-code="<?php echo $p['code']; ?>">
                    <div class="card pos-product-card shadow-sm h-100">
                        <div class="card-body p-2 text-center">
                            <?php if ($p['image']): ?>
                                <img src="../../uploads/products/<?php echo $p['image']; ?>" class="rounded mb-2" style="width: 100%; height: 80px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-light rounded mb-2 d-flex align-items-center justify-content-center" style="width: 100%; height: 80px;">
                                    <i class="fas fa-pills fa-2x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            <h6 class="mb-1 fw-bold text-truncate" title="<?php echo $p['name']; ?>"><?php echo $p['name']; ?></h6>
                            <p class="text-primary fw-bold mb-1 small"><?php echo format_currency($p['sell_price']); ?></p>
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
                <h5 class="fw-bold mb-0">Panier Actuel</h5>
            </div>
            
            <div class="card-body p-0 overflow-auto flex-grow-1">
                <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="sticky-top bg-light">
                        <tr>
                            <th class="ps-3">Article</th>
                            <th>Qté</th>
                            <th>Prix</th>
                            <th class="text-end pe-3"></th>
                        </tr>
                    </thead>
                    <tbody id="cartItems">
                        <!-- Cart items injected by JS -->
                    </tbody>
                </table>
                </div>
                <div id="emptyCart" class="text-center py-5 text-muted">
                    <i class="fas fa-shopping-basket fa-3x mb-3 opacity-25"></i>
                    <p>Le panier est vide</p>
                </div>
            </div>

            <div class="card-footer bg-white border-0 p-4 shadow-sm mt-auto">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Sous-total</span>
                    <span class="fw-bold" id="subtotal">0 FC</span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Remise</span>
                    <input type="number" id="discount" class="form-control form-control-sm text-end" style="width: 80px;" value="0">
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">TVA (%)</span>
                    <input type="number" id="taxRateInline" class="form-control form-control-sm text-end" style="width: 80px;" value="0" min="0" step="0.01">
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-4">
                    <span class="h5 fw-bold">TOTAL</span>
                    <span class="h5 fw-bold text-primary" id="finalTotal">0 FC</span>
                </div>
                <div class="d-grid gap-2">
                    <button class="btn btn-primary py-3 fw-bold" id="btnPay" disabled>
                        <i class="fas fa-check-circle me-2"></i> VALIDER LE PAIEMENT
                    </button>
                    <button class="btn btn-light" id="btnClearCart">Vider le panier</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Payment -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content pos-pay-modal border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <div>
                    <p class="text-muted small mb-1 text-uppercase fw-bold" style="letter-spacing:.06em;">Point de vente</p>
                    <h5 class="modal-title fw-bold mb-0" id="paymentModalLabel">Finaliser la vente</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="pos-pay-amount text-center mb-4">
                    <div class="pos-pay-amount-label">Montant à encaisser</div>
                    <div class="pos-pay-amount-value" id="modalFinalAmount">0 FC</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted" for="clientSelect">Client</label>
                    <select class="form-select" id="clientSelect">
                        <option value="">Client de passage</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>">
                                <?php echo htmlspecialchars($client['name']); ?>
                                <?php if (!empty($client['phone'])): ?>
                                    (<?php echo htmlspecialchars($client['phone']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted" for="taxRate">TVA (%)</label>
                    <input type="number" class="form-control" id="taxRate" value="0" min="0" step="0.01">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted" for="amountReceived">Montant reçu</label>
                    <div class="input-group">
                        <input type="number" class="form-control form-control-lg fw-bold" id="amountReceived" placeholder="0" min="0" step="1">
                        <span class="input-group-text">FC</span>
                    </div>
                </div>

                <div class="pos-pay-change text-center">
                    <p class="text-muted mb-1 small">Rendu de monnaie</p>
                    <h3 class="fw-bold mb-0 text-primary" id="changeAmount">0 FC</h3>
                </div>

                <div id="payError" class="alert alert-danger mt-3 mb-0 d-none" role="alert"></div>
            </div>
            <div class="modal-footer border-0 pt-0 flex-column flex-sm-row gap-2">
                <button type="button" class="btn btn-light w-100 w-sm-auto" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary px-4 fw-bold w-100 w-sm-auto" id="btnConfirmSale">
                    <i class="fas fa-check-circle me-2"></i>Confirmer & ouvrir la facture
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.pos-pay-modal {
    background: linear-gradient(180deg, rgba(17, 27, 47, 0.98) 0%, rgba(11, 18, 32, 0.98) 100%) !important;
    color: #e2e8f0;
    border: 1px solid #223253 !important;
    border-radius: 18px !important;
    overflow: hidden;
}
.pos-pay-modal .modal-header,
.pos-pay-modal .modal-footer {
    background: transparent;
}
.pos-pay-amount {
    background: rgba(29, 78, 216, 0.14);
    border: 1px solid rgba(147, 197, 253, 0.28);
    border-radius: 16px;
    padding: 1.15rem 1rem;
}
.pos-pay-amount-label {
    color: #94a3b8;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.35rem;
}
.pos-pay-amount-value {
    font-size: clamp(1.8rem, 5vw, 2.4rem);
    font-weight: 800;
    color: #93c5fd;
    line-height: 1.1;
}
.pos-pay-change {
    background: #0b1426;
    border: 1px solid #223253;
    border-radius: 14px;
    padding: 1rem;
}
.pos-pay-modal .form-label { margin-bottom: 0.35rem; }
@media (min-width: 576px) {
    .w-sm-auto { width: auto !important; }
}
</style>

<script>
let cart = [];

function updateCart() {
    const cartItems = document.getElementById('cartItems');
    const emptyCart = document.getElementById('emptyCart');
    const subtotalEl = document.getElementById('subtotal');
    const finalTotalEl = document.getElementById('finalTotal');
    const btnPay = document.getElementById('btnPay');

    if (cart.length === 0) {
        cartItems.innerHTML = '';
        emptyCart.classList.remove('d-none');
        subtotalEl.innerText = '0 FC';
        finalTotalEl.innerText = '0 FC';
        btnPay.disabled = true;
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
                    <small class="text-muted">${item.price.toLocaleString()} FC</small>
                </td>
                <td style="width: 100px;">
                    <div class="input-group input-group-sm">
                        <button class="btn btn-outline-secondary" onclick="changeQty(${index}, -1)">-</button>
                        <input type="text" class="form-control text-center px-0" value="${item.qty}" readonly>
                        <button class="btn btn-outline-secondary" onclick="changeQty(${index}, 1)">+</button>
                    </div>
                </td>
                <td class="fw-bold small">${itemTotal.toLocaleString()}</td>
                <td class="text-end pe-3">
                    <button class="btn btn-sm btn-light text-danger" onclick="removeFromCart(${index})"><i class="fas fa-times"></i></button>
                </td>
            </tr>
        `;
    });

    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const taxRate = parseFloat(document.getElementById('taxRateInline').value) || 0;
    const taxable = Math.max(0, total - discount);
    const taxAmount = (taxable * taxRate) / 100;
    const finalTotal = taxable + taxAmount;

    subtotalEl.innerText = total.toLocaleString() + ' FC';
    finalTotalEl.innerText = (finalTotal < 0 ? 0 : finalTotal).toLocaleString() + ' FC';
    btnPay.disabled = false;
}

function changeQty(index, delta) {
    cart[index].qty += delta;
    if (cart[index].qty <= 0) cart.splice(index, 1);
    updateCart();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    updateCart();
}

function parseMoney(text) {
    return parseFloat(String(text).replace(/[^\d.-]/g, '')) || 0;
}

function showPayError(message) {
    const box = document.getElementById('payError');
    if (!box) return;
    box.textContent = message;
    box.classList.remove('d-none');
}

function hidePayError() {
    const box = document.getElementById('payError');
    if (!box) return;
    box.textContent = '';
    box.classList.add('d-none');
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

document.getElementById('posSearch').addEventListener('input', applyFilters);

document.querySelectorAll('.filter-cat').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-cat').forEach(b => b.classList.remove('active', 'btn-primary'));
        document.querySelectorAll('.filter-cat').forEach(b => b.classList.add('btn-outline-primary'));
        this.classList.add('active', 'btn-primary');
        this.classList.remove('btn-outline-primary');
        applyFilters();
    });
});

function applyFilters() {
    const q = document.getElementById('posSearch').value.toLowerCase();
    const activeCatBtn = document.querySelector('.filter-cat.active');
    const catId = activeCatBtn ? activeCatBtn.dataset.cat : '0';

    document.querySelectorAll('.product-item').forEach(item => {
        const text = item.innerText.toLowerCase();
        const code = (item.dataset.code || '').toLowerCase();
        const itemCat = item.dataset.cat;
        
        const matchesSearch = q === '' || text.includes(q) || code.includes(q);
        const matchesCat = catId === '0' || itemCat == catId;

        item.style.display = (matchesSearch && matchesCat) ? '' : 'none';
    });
}

document.getElementById('btnClearCart').addEventListener('click', () => {
    cart = [];
    updateCart();
});

document.getElementById('discount').addEventListener('input', updateCart);
document.getElementById('taxRateInline').addEventListener('input', function() {
    const modalTax = document.getElementById('taxRate');
    if (modalTax) modalTax.value = this.value;
    updateCart();
});

document.getElementById('btnPay').addEventListener('click', () => {
    hidePayError();
    const finalAmount = document.getElementById('finalTotal').innerText;
    document.getElementById('modalFinalAmount').innerText = finalAmount;
    const inlineTax = document.getElementById('taxRateInline');
    const modalTax = document.getElementById('taxRate');
    if (inlineTax && modalTax) modalTax.value = inlineTax.value;
    document.getElementById('amountReceived').value = '';
    document.getElementById('changeAmount').innerText = '0 FC';
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
});

document.getElementById('taxRate').addEventListener('input', function() {
    const inlineTax = document.getElementById('taxRateInline');
    if (inlineTax) {
        inlineTax.value = this.value;
        updateCart();
        document.getElementById('modalFinalAmount').innerText = document.getElementById('finalTotal').innerText;
    }
});

document.getElementById('amountReceived').addEventListener('input', function() {
    const final = parseMoney(document.getElementById('modalFinalAmount').innerText);
    const received = parseFloat(this.value) || 0;
    const change = received - final;
    document.getElementById('changeAmount').innerText = (change < 0 ? 0 : change).toLocaleString() + ' FC';
});

document.getElementById('btnConfirmSale').addEventListener('click', function() {
    const btn = this;
    hidePayError();
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Traitement...';

    const data = {
        cart: cart,
        total_amount: cart.reduce((sum, item) => sum + (item.price * item.qty), 0),
        discount: parseFloat(document.getElementById('discount').value) || 0,
        client_id: document.getElementById('clientSelect').value,
        tax_rate: parseFloat(document.getElementById('taxRate').value) || 0,
        legal_note: 'Les médicaments vendus ne sont ni repris ni échangés.'
    };

    fetch('save_sale.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(async (res) => {
        const text = await res.text();
        let json;
        try {
            json = JSON.parse(text);
        } catch (e) {
            throw new Error('Réponse serveur invalide. Réessayez.');
        }
        return json;
    })
    .then(res => {
        if (res.success) {
            const modalEl = document.getElementById('paymentModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            window.open(`invoice.php?id=${res.sale_id}&paid=1&share=1&auto_send=1`, '_blank');
            location.reload();
        } else {
            showPayError(res.message || 'La vente n\'a pas pu être enregistrée.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Confirmer & ouvrir la facture';
        }
    })
    .catch(err => {
        console.error(err);
        showPayError(err.message || 'Une erreur est survenue.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Confirmer & ouvrir la facture';
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
