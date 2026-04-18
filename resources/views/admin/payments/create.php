<?php
$hasOpenCashRegister = !empty($hasOpenCashRegister);
$orders = is_array($orders ?? null) ? $orders : [];
$paymentMethods = is_array($paymentMethods ?? null) ? $paymentMethods : [];
$selectedOrderId = isset($selectedOrderId) ? (int) $selectedOrderId : 0;

$formatMoney = static fn (mixed $value): string => 'R$ ' . number_format((float) $value, 2, ',', '.');

$totalReceivable = 0.0;
foreach ($orders as $order) {
    if (!is_array($order)) {
        continue;
    }
    $totalReceivable += (float) ($order['remaining_amount'] ?? 0);
}
?>

<style>
    .payment-create-page{display:grid;gap:16px}
    .payment-create-topbar p{margin:6px 0 0;color:#475569}

    .payment-create-kpis{display:grid;grid-template-columns:repeat(4,minmax(130px,1fr));gap:12px}
    .payment-create-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px;box-shadow:0 8px 20px rgba(15,23,42,.05)}
    .payment-create-kpi strong{display:block;font-size:22px;color:#0f172a;line-height:1.1}
    .payment-create-kpi span{display:block;margin-top:6px;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.04em}

    .payment-create-layout{display:grid;grid-template-columns:minmax(280px,360px) minmax(0,1fr);gap:14px;align-items:start}
    .payment-orders-panel{display:grid;gap:10px}
    .payment-orders-search{margin:0}
    .payment-orders-list{display:grid;gap:8px;max-height:520px;overflow:auto;padding-right:2px}
    .payment-order-card{border:1px solid #dbeafe;border-radius:12px;background:#fff;padding:10px;display:grid;gap:7px;transition:all .16s ease}
    .payment-order-card:hover{border-color:#93c5fd;box-shadow:0 10px 20px rgba(30,64,175,.14);transform:translateY(-1px)}
    .payment-order-card.is-active{border-color:#1d4ed8;background:#eff6ff}
    .payment-order-head{display:flex;justify-content:space-between;gap:8px;align-items:flex-start}
    .payment-order-head strong{font-size:14px;color:#0f172a}
    .payment-order-meta{font-size:12px;color:#475569}
    .payment-status-badge{
        display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;
        background:#dcfce7;border:1px solid #86efac;color:#166534;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;
        width:max-content
    }
    .payment-status-badge::before{content:"";width:8px;height:8px;border-radius:999px;background:#16a34a;box-shadow:0 0 0 4px rgba(22,163,74,.16)}

    .payment-form-card{display:grid;gap:12px}
    .payment-atm{background:
        radial-gradient(circle at 18% 0%, rgba(96,165,250,.22), transparent 36%),
        linear-gradient(160deg,#111827 0%,#1f2937 45%,#334155 100%);
        border:1px solid #0f172a;color:#e2e8f0}
    .payment-atm h3{color:#f8fafc}
    .payment-atm .payment-form-help{color:#cbd5e1}
    .atm-shell{display:grid;gap:12px}
    .atm-screen{border:1px solid rgba(147,197,253,.45);background:linear-gradient(180deg,#0f172a 0%,#1e293b 100%);border-radius:12px;padding:12px;display:grid;gap:8px;box-shadow:inset 0 0 0 1px rgba(148,163,184,.2)}
    .atm-screen h4{margin:0;font-size:13px;color:#bfdbfe;text-transform:uppercase;letter-spacing:.06em}
    .atm-screen-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    .atm-screen-item{border:1px solid rgba(148,163,184,.45);border-radius:10px;background:rgba(15,23,42,.5);padding:8px}
    .atm-screen-item span{display:block;font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
    .atm-screen-item strong{display:block;font-size:13px;color:#e2e8f0;word-break:break-word}
    .atm-form{display:grid;gap:12px}
    .atm-form .field label{color:#e2e8f0}
    .atm-form input,.atm-form select{background:#f8fafc}
    .atm-form .btn{background:#2563eb}
    .payment-order-summary{border:1px solid #bfdbfe;background:#eff6ff;border-radius:12px;padding:12px;display:grid;gap:8px}
    .payment-order-summary h3{margin:0;font-size:14px;color:#0f172a}
    .payment-order-summary-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    .payment-summary-item{background:#fff;border:1px solid #dbeafe;border-radius:10px;padding:8px}
    .payment-summary-item span{display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
    .payment-summary-item strong{display:block;font-size:13px;color:#0f172a;word-break:break-word}
    .payment-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .payment-form-help{margin:0;font-size:12px;color:#64748b}

    .payment-warning{border:1px solid #fcd34d;background:#fffbeb;border-radius:12px;padding:16px;color:#92400e;display:grid;gap:10px}
    .payment-warning p{margin:0}

    @media (max-width:1080px){
        .payment-create-kpis{grid-template-columns:repeat(2,minmax(130px,1fr))}
        .payment-create-layout{grid-template-columns:1fr}
        .payment-orders-list{max-height:300px}
    }
    @media (max-width:700px){
        .payment-create-kpis{grid-template-columns:1fr}
        .payment-order-summary-grid,.payment-form-grid,.atm-screen-grid{grid-template-columns:1fr}
    }
</style>

<div class="payment-create-page ops-page">
    <div class="topbar payment-create-topbar">
        <div>
            <h1>Registrar Pagamento</h1>
            <p>Fluxo guiado para selecionar pedido, conferir saldo e registrar recebimento sem retrabalho.</p>
        </div>
        <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/payments')) ?>">Voltar para pagamentos</a>
    </div>

    <section class="ops-hero">
        <div class="ops-hero-copy">
            <span class="ops-eyebrow">Recebimento e Caixa</span>
            <h1>Registrar Pagamento</h1>
            <p>Selecione o pedido, confira saldo e conclua o recebimento em um fluxo guiado com a mesma hierarquia visual das páginas principais do ambiente.</p>
            <div class="ops-hero-meta">
                <span class="ops-hero-pill"><?= count($orders) ?> pedidos com saldo</span>
                <span class="ops-hero-pill"><?= $formatMoney($totalReceivable) ?> a receber</span>
                <span class="ops-hero-pill">Caixa <?= $hasOpenCashRegister ? 'aberto' : 'fechado' ?></span>
            </div>
        </div>
        <div class="ops-hero-actions">
            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/payments')) ?>">Voltar para pagamentos</a>
            <a class="btn" href="#order_id">Registrar recebimento</a>
        </div>
    </section>

    <section class="payment-create-kpis">
        <article class="payment-create-kpi">
            <strong><?= count($orders) ?></strong>
            <span>Pedidos com saldo</span>
        </article>
        <article class="payment-create-kpi">
            <strong><?= $formatMoney($totalReceivable) ?></strong>
            <span>Total a receber</span>
        </article>
        <article class="payment-create-kpi">
            <strong><?= count($paymentMethods) ?></strong>
            <span>Metodos ativos</span>
        </article>
        <article class="payment-create-kpi">
            <strong><?= $hasOpenCashRegister ? 'Aberto' : 'Fechado' ?></strong>
            <span>Status do caixa</span>
        </article>
    </section>

    <?php if (!$hasOpenCashRegister): ?>
        <section class="payment-warning">
            <p><strong>Não há caixa aberto no momento.</strong></p>
            <p>Sem caixa aberto, o recebimento nao pode ser registrado com seguranca no fluxo financeiro.</p>
            <div>
                <a class="btn" href="<?= htmlspecialchars(base_url('/admin/cash-registers')) ?>">Abrir caixa agora</a>
            </div>
        </section>
    <?php elseif (empty($orders)): ?>
        <section class="payment-warning">
            <p><strong>Não existem pedidos com saldo pendente.</strong></p>
            <p>Todos os pedidos estao quitados ou nao ha pedidos ativos para pagamento.</p>
            <div>
                <a class="btn" href="<?= htmlspecialchars(base_url('/admin/orders')) ?>">Ver pedidos</a>
            </div>
        </section>
    <?php else: ?>
        <div class="payment-create-layout">
            <section class="card payment-orders-panel">
                <h3 style="margin:0">Pedidos entregues aguardando pagamento</h3>
                <p class="payment-form-help">Clique em um pedido entregue para preencher o formulario automaticamente.</p>
                <input id="orderQuickSearch" class="payment-orders-search" type="text" placeholder="Buscar pedido, mesa ou cliente...">
                <div id="orderQuickList" class="payment-orders-list">
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $orderId = (int) ($order['id'] ?? 0);
                        if ($orderId <= 0) {
                            continue;
                        }

                        $orderNumber = trim((string) ($order['order_number'] ?? 'Pedido sem numero'));
                        $tableLabel = ($order['table_number'] ?? null) !== null ? 'Mesa ' . (int) $order['table_number'] : 'Sem mesa';
                        $customerName = trim((string) ($order['customer_name'] ?? ''));
                        $customerLabel = $customerName !== '' ? $customerName : 'Cliente nao informado';
                        $remainingAmount = (float) ($order['remaining_amount'] ?? 0);
                        $totalAmount = (float) ($order['total_amount'] ?? 0);
                        $paidAmount = (float) ($order['paid_amount'] ?? 0);
                        $statusLabel = trim((string) ($order['status_label'] ?? 'Entregue'));

                        $searchBlob = implode(' ', [$orderNumber, $tableLabel, $customerLabel]);
                        ?>
                        <article
                            class="payment-order-card<?= $selectedOrderId === $orderId ? ' is-active' : '' ?>"
                            data-order-quick-card
                            data-order-id="<?= $orderId ?>"
                            data-order-search="<?= htmlspecialchars($searchBlob) ?>"
                        >
                            <div class="payment-order-head">
                                <strong><?= htmlspecialchars($orderNumber) ?></strong>
                                <span class="badge status-pending">Saldo: <?= $formatMoney($remainingAmount) ?></span>
                            </div>
                            <div class="payment-status-badge"><?= htmlspecialchars($statusLabel) ?></div>
                            <div class="payment-order-meta"><?= htmlspecialchars($tableLabel) ?> | <?= htmlspecialchars($customerLabel) ?></div>
                            <div class="payment-order-meta">Total: <?= $formatMoney($totalAmount) ?> | Pago: <?= $formatMoney($paidAmount) ?></div>
                            <button type="button" class="btn secondary" data-order-select-button="<?= $orderId ?>">Selecionar pedido</button>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="card payment-form-card payment-atm">
                <h3 style="margin:0">Dados do recebimento</h3>
                <p class="payment-form-help">Caixa eletronico operacional: selecione o pedido, aplique desconto (se houver) e confirme.</p>

                <div class="atm-shell">
                    <div class="atm-screen" id="selectedOrderSummary" aria-live="polite">
                        <h4>Painel do caixa</h4>
                        <div class="atm-screen-grid">
                            <div class="atm-screen-item">
                                <span>Pedido</span>
                                <strong id="summaryOrderNumber">-</strong>
                            </div>
                            <div class="atm-screen-item">
                                <span>Mesa</span>
                                <strong id="summaryTable">-</strong>
                            </div>
                            <div class="atm-screen-item">
                                <span>Cliente</span>
                                <strong id="summaryCustomer">-</strong>
                            </div>
                            <div class="atm-screen-item">
                                <span>Status</span>
                                <strong id="summaryStatus">-</strong>
                            </div>
                            <div class="atm-screen-item">
                                <span>Saldo atual</span>
                                <strong id="summaryRemaining">-</strong>
                            </div>
                            <div class="atm-screen-item" style="grid-column:1 / -1">
                                <span>Saldo apos desconto</span>
                                <strong id="summaryRemainingAfterDiscount">-</strong>
                            </div>
                        </div>
                    </div>

                    <form class="atm-form" method="POST" action="<?= htmlspecialchars(base_url('/admin/payments/store')) ?>">
                        <?= form_security_fields('payments.store') ?>

                    <div class="field">
                        <label for="order_id">Pedido</label>
                        <select id="order_id" name="order_id" required>
                            <option value="">Selecione um pedido</option>
                            <?php foreach ($orders as $order): ?>
                                <?php
                                $orderId = (int) ($order['id'] ?? 0);
                                if ($orderId <= 0) {
                                    continue;
                                }

                                $orderNumber = trim((string) ($order['order_number'] ?? 'Pedido sem numero'));
                                $tableNumber = $order['table_number'] !== null ? (int) $order['table_number'] : null;
                                $tableLabel = $tableNumber !== null ? 'Mesa ' . $tableNumber : 'Sem mesa';
                                $customerName = trim((string) ($order['customer_name'] ?? ''));
                                $customerLabel = $customerName !== '' ? $customerName : 'Não informado';
                                $remainingAmount = (float) ($order['remaining_amount'] ?? 0);
                                $totalAmount = (float) ($order['total_amount'] ?? 0);
                                $paidAmount = (float) ($order['paid_amount'] ?? 0);
                                $statusLabel = trim((string) ($order['status_label'] ?? 'Entregue'));
                                ?>
                                <option
                                    value="<?= $orderId ?>"
                                    data-order-number="<?= htmlspecialchars($orderNumber) ?>"
                                    data-table-label="<?= htmlspecialchars($tableLabel) ?>"
                                    data-customer-name="<?= htmlspecialchars($customerLabel) ?>"
                                    data-status-label="<?= htmlspecialchars($statusLabel) ?>"
                                    data-remaining-amount="<?= htmlspecialchars((string) $remainingAmount) ?>"
                                    data-total-amount="<?= htmlspecialchars((string) $totalAmount) ?>"
                                    data-paid-amount="<?= htmlspecialchars((string) $paidAmount) ?>"
                                    <?= $selectedOrderId === $orderId ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($orderNumber) ?> | <?= htmlspecialchars($tableLabel) ?> | <?= htmlspecialchars($statusLabel) ?> | Saldo: <?= $formatMoney($remainingAmount) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                        <div class="payment-form-grid">
                            <div class="field" style="margin:0">
                                <label for="payment_method_id">Metodo de pagamento</label>
                                <select id="payment_method_id" name="payment_method_id" required>
                                    <option value="">Selecione</option>
                                    <?php foreach ($paymentMethods as $method): ?>
                                        <option value="<?= (int) ($method['id'] ?? 0) ?>">
                                            <?= htmlspecialchars((string) ($method['name'] ?? 'Metodo')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field" style="margin:0">
                                <label for="amount">Valor (R$)</label>
                                <input id="amount" name="amount" type="number" min="0.01" step="0.01" required>
                            </div>
                        </div>

                        <div class="payment-form-grid">
                            <div class="field" style="margin:0">
                                <label for="discount_amount">Desconto no caixa (R$)</label>
                                <input id="discount_amount" name="discount_amount" type="number" min="0" step="0.01" value="0.00">
                            </div>
                            <div class="field" style="margin:0">
                                <label for="discount_reason">Motivo do desconto</label>
                                <input id="discount_reason" name="discount_reason" type="text" placeholder="Obrigatorio quando houver desconto">
                            </div>
                        </div>

                        <p class="payment-form-help">O desconto sera registrado no pedido, refletido no ticket e rastreado no historico financeiro.</p>

                        <div class="field">
                            <label for="transaction_reference">Referência da transação</label>
                            <input id="transaction_reference" name="transaction_reference" type="text" placeholder="Opcional: NSU, autorizacao, comprovante...">
                        </div>

                        <button class="btn" type="submit">Confirmar pagamento no caixa</button>
                    </form>
                </div>
            </section>
        </div>
    <?php endif; ?>
</div>

<script>
(() => {
    const orderSelect = document.getElementById('order_id');
    const amountInput = document.getElementById('amount');
    const discountInput = document.getElementById('discount_amount');
    const discountReasonInput = document.getElementById('discount_reason');
    const quickSearch = document.getElementById('orderQuickSearch');
    const quickCards = Array.from(document.querySelectorAll('[data-order-quick-card]'));

    const summaryOrderNumber = document.getElementById('summaryOrderNumber');
    const summaryTable = document.getElementById('summaryTable');
    const summaryCustomer = document.getElementById('summaryCustomer');
    const summaryStatus = document.getElementById('summaryStatus');
    const summaryRemaining = document.getElementById('summaryRemaining');
    const summaryRemainingAfterDiscount = document.getElementById('summaryRemainingAfterDiscount');

    if (!(orderSelect instanceof HTMLSelectElement) || !(amountInput instanceof HTMLInputElement)) {
        return;
    }

    const formatBRL = (value) => {
        const amount = Number(value || 0);
        return amount.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    };

    const normalize = (value) => String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

    const updateSummaryFromSelection = () => {
        const selected = orderSelect.selectedOptions[0] || null;
        if (!selected || selected.value === '') {
            if (summaryOrderNumber) summaryOrderNumber.textContent = '-';
            if (summaryTable) summaryTable.textContent = '-';
            if (summaryCustomer) summaryCustomer.textContent = '-';
            if (summaryStatus) summaryStatus.textContent = '-';
            if (summaryRemaining) summaryRemaining.textContent = '-';
            if (summaryRemainingAfterDiscount) summaryRemainingAfterDiscount.textContent = '-';
            return;
        }

        const orderNumber = selected.dataset.orderNumber || '-';
        const tableLabel = selected.dataset.tableLabel || '-';
        const customerName = selected.dataset.customerName || '-';
        const statusLabel = selected.dataset.statusLabel || 'Entregue';
        const remainingAmount = Number(selected.dataset.remainingAmount || '0');
        const discountAmount = discountInput instanceof HTMLInputElement
            ? Math.max(0, Number(discountInput.value || '0'))
            : 0;
        const remainingAfterDiscount = Math.max(0, remainingAmount - discountAmount);
        if (discountReasonInput instanceof HTMLInputElement) {
            discountReasonInput.required = discountAmount > 0;
        }

        if (summaryOrderNumber) summaryOrderNumber.textContent = orderNumber;
        if (summaryTable) summaryTable.textContent = tableLabel;
        if (summaryCustomer) summaryCustomer.textContent = customerName;
        if (summaryStatus) summaryStatus.textContent = statusLabel;
        if (summaryRemaining) summaryRemaining.textContent = formatBRL(remainingAmount);
        if (summaryRemainingAfterDiscount) summaryRemainingAfterDiscount.textContent = formatBRL(remainingAfterDiscount);

        const currentAmount = Number(amountInput.value || '0');
        if (!Number.isFinite(currentAmount) || currentAmount <= 0 || currentAmount > remainingAfterDiscount) {
            amountInput.value = remainingAfterDiscount > 0 ? remainingAfterDiscount.toFixed(2) : '';
        }

        quickCards.forEach((card) => {
            const isActive = card.getAttribute('data-order-id') === selected.value;
            card.classList.toggle('is-active', isActive);
        });
    };

    orderSelect.addEventListener('change', updateSummaryFromSelection);
    if (discountInput instanceof HTMLInputElement) {
        discountInput.addEventListener('input', updateSummaryFromSelection);
        discountInput.addEventListener('blur', () => {
            const value = Math.max(0, Number(discountInput.value || '0'));
            discountInput.value = value.toFixed(2);
            updateSummaryFromSelection();
        });
    }
    updateSummaryFromSelection();

    quickCards.forEach((card) => {
        const button = card.querySelector('[data-order-select-button]');
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        button.addEventListener('click', () => {
            const orderId = card.getAttribute('data-order-id') || '';
            if (orderId === '') {
                return;
            }
            orderSelect.value = orderId;
            orderSelect.dispatchEvent(new Event('change'));
            orderSelect.focus();
        });
    });

    if (quickSearch instanceof HTMLInputElement && quickCards.length > 0) {
        quickSearch.addEventListener('input', () => {
            const term = normalize(quickSearch.value);
            quickCards.forEach((card) => {
                const search = normalize(card.getAttribute('data-order-search'));
                card.style.display = term === '' || search.includes(term) ? '' : 'none';
            });
        });
    }
})();
</script>
