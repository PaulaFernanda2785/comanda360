<?php
$access = is_array($access ?? null) ? $access : [];
$company = is_array($access['company'] ?? null) ? $access['company'] : [];
$table = is_array($access['table'] ?? null) ? $access['table'] : [];
$currentCommand = is_array($currentCommand ?? null) ? $currentCommand : null;
$currentCommandPanel = is_array($currentCommandPanel ?? null) ? $currentCommandPanel : ['summary' => [], 'orders' => []];
$currentSummary = is_array($currentCommandPanel['summary'] ?? null) ? $currentCommandPanel['summary'] : [];
$products = is_array($products ?? null) ? $products : [];
$refreshIntervalSeconds = max(1200, (int) ($refreshIntervalSeconds ?? 1200));
$fatalError = trim((string) ($fatalError ?? ''));
$companySlug = trim((string) ($company['slug'] ?? ''));
$tableNumber = (int) ($table['number'] ?? 0);
$token = trim((string) ($access['token'] ?? ''));
$currentCommandId = (int) ($currentCommand['id'] ?? 0);
$showPublicTotals = !array_key_exists('show_public_totals', $menuTheme ?? []) || (int) (($menuTheme ?? [])['show_public_totals'] ?? 1) === 1;
$menuBaseUrl = $companySlug !== '' && $tableNumber > 0 && $token !== ''
    ? base_url('/menu-digital?empresa=' . rawurlencode($companySlug) . '&mesa=' . $tableNumber . '&token=' . rawurlencode($token))
    : base_url('/menu-digital');
$storeOrderAction = $menuBaseUrl !== '' ? str_replace('/menu-digital?', '/menu-digital/order/store?', $menuBaseUrl) : base_url('/menu-digital/order/store');
$cartStorageKey = 'digital-menu-cart:' . $companySlug . ':' . $tableNumber . ':' . $token . ':' . $currentCommandId;
$formatMoney = static fn (float $value): string => 'R$ ' . number_format($value, 2, ',', '.');
$productsJson = json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
if (!is_string($productsJson)) {
    $productsJson = '[]';
}
?>

<style>
    .dm-checkout{display:grid;gap:18px;padding-bottom:28px}
    .dm-checkout,.dm-checkout > *,.dm-checkout-grid,.dm-checkout-grid > *,.dm-review-list,.dm-review-item,.dm-review-item > *,.dm-summary-box,.dm-summary-box > *{min-width:0}
    .dm-checkout-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(280px,.75fr);gap:18px;align-items:start}
    .dm-review-card,.dm-summary-card{
        background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(255,255,255,.91));
        border:1px solid rgba(219,228,240,.96);
        border-radius:26px;
        box-shadow:var(--dm-shadow);
        padding:18px;
    }
    .dm-section-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:14px}
    .dm-section-head h2{margin:0;font-size:24px;letter-spacing:-.02em}
    .dm-section-head p{margin:6px 0 0;color:var(--dm-muted);font-size:14px;line-height:1.5;max-width:760px}
    .dm-chip-row{display:flex;gap:8px;flex-wrap:wrap}
    .dm-chip{
        display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;
        border:1px solid var(--dm-border);background:#fff;color:var(--dm-secondary);font-size:12px;font-weight:700
    }
    .dm-review-list{display:grid;gap:12px}
    .dm-review-item{padding:14px;border-radius:22px;background:var(--dm-surface-soft);border:1px solid var(--dm-border);display:grid;gap:10px}
    .dm-review-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
    .dm-review-head strong{font-size:16px;overflow-wrap:anywhere}
    .dm-review-head span{font-size:14px;font-weight:800;color:var(--dm-secondary)}
    .dm-review-meta,.dm-review-note{font-size:12px;color:var(--dm-muted);line-height:1.55;overflow-wrap:anywhere}
    .dm-review-actions{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
    .dm-empty{padding:18px;border-radius:20px;border:1px dashed var(--dm-border);background:var(--dm-surface-soft);color:var(--dm-muted)}
    .dm-summary-stack{display:grid;gap:14px}
    .dm-summary-box{padding:14px;border-radius:20px;background:var(--dm-surface-soft);border:1px solid var(--dm-border);display:grid;gap:8px}
    .dm-summary-box strong{font-size:22px;line-height:1}
    .dm-summary-box span{font-size:12px;color:var(--dm-muted)}
    .dm-total-line{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:14px;border-radius:18px;background:color-mix(in srgb, var(--dm-accent) 12%, white 88%);border:1px solid color-mix(in srgb, var(--dm-accent) 22%, white 78%)}
    .dm-total-line strong{font-size:20px}
    .dm-form{display:grid;gap:14px}
    .dm-form textarea{
        width:100%;border:1px solid var(--dm-border);border-radius:14px;padding:12px 14px;background:#fff;color:var(--dm-text);
        font:inherit;resize:vertical;min-height:120px
    }
    .dm-hidden-fields{display:none}
    .dm-actions{display:flex;gap:10px;flex-wrap:wrap}
    @media (max-width:980px){
        .dm-checkout-grid{grid-template-columns:1fr}
    }
    @media (max-width:640px){
        .dm-review-head,.dm-actions{flex-direction:column;align-items:stretch}
        .dm-review-actions{justify-content:stretch}
        .dm-review-actions .btn-secondary,.dm-actions .btn,.dm-actions .btn-secondary{width:100%}
    }
</style>

<?php if ($fatalError !== ''): ?>
    <section class="dm-card dm-hero">
        <div class="dm-hero-grid">
            <div class="dm-hero-copy">
                <span class="dm-eyebrow">Carrinho da mesa</span>
                <h1>Fluxo indisponível</h1>
                <p><?= htmlspecialchars($fatalError) ?></p>
            </div>
            <div class="dm-action-bar" style="justify-content:flex-start">
                <a class="btn" href="<?= htmlspecialchars($menuBaseUrl) ?>">Voltar ao menu</a>
            </div>
        </div>
    </section>
<?php else: ?>
    <div class="dm-checkout">
        <section class="dm-card dm-hero">
            <div class="dm-hero-grid">
                <div class="dm-hero-copy">
                    <span class="dm-eyebrow">Confirmação do pedido</span>
                    <h1>Carrinho da mesa</h1>
                    <p>Revise os itens do seu pedido, ajuste o carrinho se necessário e envie somente para a sua comanda atual.</p>
                    <div class="dm-chip-row">
                        <span class="dm-pill">Mesa <?= $tableNumber ?></span>
                        <?php if ($currentCommand !== null): ?>
                            <span class="dm-pill">Comanda #<?= $currentCommandId ?></span>
                            <span class="dm-pill"><?= htmlspecialchars((string) ($currentCommand['customer_name'] ?? 'Cliente')) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="dm-action-bar" style="justify-content:flex-end">
                    <a class="btn-secondary" href="<?= htmlspecialchars($menuBaseUrl) ?>">Voltar ao menu</a>
                </div>
            </div>
        </section>

        <div class="dm-checkout-grid">
            <main class="dm-review-card">
                <div class="dm-section-head">
                    <div>
                        <h2>Revisão do pedido</h2>
                        <p>Esta etapa centraliza o envio do pedido. Os itens do carrinho continuam vinculados somente à sua comanda nesta mesa.</p>
                    </div>
                    <div class="dm-chip-row">
                        <span class="dm-chip">Atualização do menu a cada <?= htmlspecialchars((string) ($refreshIntervalSeconds / 60)) ?> min</span>
                    </div>
                </div>

                <?php if ($currentCommand === null): ?>
                    <div class="dm-empty">Abra uma comanda no menu da mesa para ativar o carrinho.</div>
                <?php else: ?>
                    <form method="POST" action="<?= htmlspecialchars($storeOrderAction) ?>" id="digitalCheckoutForm" class="dm-form">
                        <?= form_security_fields('digital_menu.order.store') ?>
                        <div id="digitalCheckoutList" class="dm-review-list">
                            <div class="dm-empty">Nenhum item foi adicionado ao carrinho.</div>
                        </div>

                        <div class="field">
                            <label for="digital_order_notes">Observações gerais do pedido</label>
                            <textarea id="digital_order_notes" name="notes" placeholder="Opcional"></textarea>
                        </div>

                        <div id="digitalCheckoutHiddenFields" class="dm-hidden-fields"></div>

                        <div class="dm-actions">
                            <a class="btn-secondary" href="<?= htmlspecialchars($menuBaseUrl) ?>">Voltar</a>
                            <button class="btn" id="digitalCheckoutSubmit" type="submit" disabled>Enviar pedido para minha comanda</button>
                        </div>
                    </form>
                <?php endif; ?>
            </main>

            <aside class="dm-summary-card">
                <div class="dm-section-head">
                    <div>
                        <h2>Resumo rápido</h2>
                        <p>Conferência final antes do envio para a operação.</p>
                    </div>
                </div>

                <div class="dm-summary-stack">
                    <div class="dm-summary-box">
                        <strong id="digitalCheckoutQuantity">0</strong>
                        <span>Item(ns) no carrinho</span>
                    </div>
                    <div class="dm-summary-box">
                        <strong><?= $currentCommand !== null ? htmlspecialchars((string) ($currentCommand['customer_name'] ?? 'Cliente')) : '---' ?></strong>
                        <span><?= $currentCommand !== null ? 'Comanda atual #' . $currentCommandId : 'Sem comanda ativa' ?></span>
                    </div>
                    <div class="dm-summary-box">
                        <strong><?= $showPublicTotals ? $formatMoney((float) ($currentSummary['total_amount'] ?? 0)) : (int) ($currentSummary['total_orders'] ?? 0) ?></strong>
                        <span><?= $showPublicTotals ? 'Total atual da sua comanda antes deste novo pedido' : 'Pedido(s) ja lancado(s) na sua comanda' ?></span>
                    </div>
                    <?php if ($showPublicTotals): ?>
                        <div class="dm-total-line">
                            <span>Total do pedido</span>
                            <strong id="digitalCheckoutTotal">R$ 0,00</strong>
                        </div>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </div>

    <script>
    (() => {
        const currentCommandEnabled = <?= $currentCommand !== null ? 'true' : 'false' ?>;
        const cartStorageKey = <?= json_encode($cartStorageKey, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const cartList = document.getElementById('digitalCheckoutList');
        const cartHiddenFields = document.getElementById('digitalCheckoutHiddenFields');
        const cartSubmit = document.getElementById('digitalCheckoutSubmit');
        const cartTotal = document.getElementById('digitalCheckoutTotal');
        const cartQuantity = document.getElementById('digitalCheckoutQuantity');
        const checkoutForm = document.getElementById('digitalCheckoutForm');
        const products = <?= $productsJson ?>;
        const productsById = {};
        products.forEach((product) => {
            if (product && typeof product.id !== 'undefined') {
                productsById[String(product.id)] = product;
            }
        });
        const cart = (() => {
            try {
                const raw = window.localStorage.getItem(cartStorageKey);
                const parsed = raw ? JSON.parse(raw) : [];
                if (!Array.isArray(parsed)) {
                    return [];
                }

                return parsed
                    .slice(0, 60)
                    .map((item) => ({
                        productId: Number(item?.productId || 0),
                        name: String(item?.name || 'Produto'),
                        quantity: Math.max(0, Number(item?.quantity || 0)),
                        notes: String(item?.notes || '').slice(0, 500),
                        additionals: Array.isArray(item?.additionals)
                            ? item.additionals.slice(0, 20).map((additional) => ({
                                id: Number(additional?.id || 0),
                                name: String(additional?.name || 'Adicional'),
                                price: Number(additional?.price || 0),
                                quantity: Math.max(0, Number(additional?.quantity ?? 1)),
                            }))
                            : [],
                        lineTotal: Number(item?.lineTotal || 0),
                    }))
                    .map((item) => ({
                        ...item,
                        additionals: item.additionals.filter((additional) => additional.id > 0 && additional.quantity > 0),
                    }))
                    .filter((item) => {
                        const product = productsById[String(item.productId)] || null;
                        return item.productId > 0
                            && item.quantity > 0
                            && Number.isFinite(item.lineTotal)
                            && item.lineTotal >= 0
                            && product
                            && product.stock_available !== false;
                    });
            } catch (error) {
                return [];
            }
        })();

        const money = (value) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value || 0));
        const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char] || char));

        const saveCart = () => {
            try {
                window.localStorage.setItem(cartStorageKey, JSON.stringify(cart));
            } catch (error) {
            }
        };

        const renderCart = () => {
            if (!cartList || !cartHiddenFields || !cartSubmit || !cartQuantity) {
                return;
            }

            if (!currentCommandEnabled || cart.length === 0) {
                cartList.innerHTML = '<div class="dm-empty">Nenhum item foi adicionado ao carrinho.</div>';
                cartHiddenFields.innerHTML = '';
                if (cartTotal) {
                    cartTotal.textContent = money(0);
                }
                cartQuantity.textContent = '0';
                cartSubmit.disabled = true;
                return;
            }

            let html = '';
            let total = 0;
            let quantity = 0;
            cartHiddenFields.replaceChildren();

            cart.forEach((item, index) => {
                total += Number(item.lineTotal || 0);
                quantity += Number(item.quantity || 0);
                const additionalsLabel = Array.isArray(item.additionals) && item.additionals.length > 0
                    ? item.additionals.map((additional) => `${additional.quantity}x ${additional.name}`).join(' - ')
                    : 'Sem adicionais';

                html += `
                    <article class="dm-review-item">
                        <div class="dm-review-head">
                            <div>
                                <strong>${item.quantity}x ${escapeHtml(item.name)}</strong>
                                <div class="dm-review-meta">${escapeHtml(additionalsLabel)}</div>
                            </div>
                            ${cartTotal ? `<span>${money(item.lineTotal)}</span>` : ''}
                        </div>
                        ${item.notes ? `<div class="dm-review-note">Observação: ${escapeHtml(item.notes)}</div>` : ''}
                        <div class="dm-review-actions">
                            <button class="btn-secondary" type="button" data-cart-remove="${index}">Remover item</button>
                        </div>
                    </article>
                `;

                const appendHiddenField = (name, value) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = String(value ?? '');
                    cartHiddenFields.appendChild(input);
                };

                appendHiddenField('product_id[]', Number(item.productId || 0));
                appendHiddenField('quantity[]', Number(item.quantity || 0));
                appendHiddenField('item_notes[]', String(item.notes || ''));
                appendHiddenField(
                    'additional_item_ids[]',
                    Array.isArray(item.additionals)
                        ? item.additionals.map((additional) => `${Number(additional.id || 0)}:${Math.max(0, Number(additional.quantity || 0))}`).filter((token) => !token.startsWith('0:')).join(',')
                        : ''
                );
            });

            cartList.innerHTML = html;
            if (cartTotal) {
                cartTotal.textContent = money(total);
            }
            cartQuantity.textContent = String(quantity);
            cartSubmit.disabled = false;
        };

        cartList?.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }

            const index = target.getAttribute('data-cart-remove');
            if (index === null) {
                return;
            }

            const numericIndex = Number(index);
            if (Number.isNaN(numericIndex) || numericIndex < 0 || numericIndex >= cart.length) {
                return;
            }

            cart.splice(numericIndex, 1);
            saveCart();
            renderCart();
        });

        checkoutForm?.addEventListener('submit', () => {
            if (cart.length === 0) {
                return;
            }
        });

        renderCart();
    })();
    </script>
<?php endif; ?>
