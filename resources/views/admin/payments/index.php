
<?php
$payments = is_array($payments ?? null) ? $payments : [];
$formatMoney = static fn (mixed $value): string => 'R$ ' . number_format((float) $value, 2, ',', '.');
$todayDate = (new DateTimeImmutable('now'))->format('Y-m-d');
$totalAmount = 0.0;
$paidCount = 0;
$todayCount = 0;
$withReferenceCount = 0;
$methodsSummary = [];
$statusSummary = [];

foreach ($payments as $payment) {
    if (!is_array($payment)) {
        continue;
    }

    $amount = (float) ($payment['amount'] ?? 0);
    $totalAmount += $amount;

    $paymentStatus = trim((string) ($payment['status'] ?? ''));
    if ($paymentStatus === 'paid') {
        $paidCount++;
    }

    $reference = trim((string) ($payment['transaction_reference'] ?? ''));
    if ($reference !== '') {
        $withReferenceCount++;
    }

    $methodName = trim((string) ($payment['payment_method_name'] ?? 'Nao informado'));
    if (!isset($methodsSummary[$methodName])) {
        $methodsSummary[$methodName] = ['count' => 0, 'amount' => 0.0];
    }
    $methodsSummary[$methodName]['count']++;
    $methodsSummary[$methodName]['amount'] += $amount;

    $orderStatus = trim((string) ($payment['order_status'] ?? 'without_order'));
    if ($orderStatus === '') {
        $orderStatus = 'without_order';
    }
    if (!isset($statusSummary[$orderStatus])) {
        $statusSummary[$orderStatus] = 0;
    }
    $statusSummary[$orderStatus]++;

    $paidAt = trim((string) ($payment['paid_at'] ?? ''));
    $createdAt = trim((string) ($payment['created_at'] ?? ''));
    $dateBase = $paidAt !== '' ? $paidAt : $createdAt;
    if ($dateBase !== '' && str_starts_with($dateBase, $todayDate)) {
        $todayCount++;
    }
}

uasort($methodsSummary, static function (array $left, array $right): int {
    return ($right['count'] <=> $left['count']) ?: ($right['amount'] <=> $left['amount']);
});

$topMethods = array_slice($methodsSummary, 0, 4, true);
ksort($statusSummary);
?>

<style>
body.modal-open{overflow:hidden}
.payments-page{display:grid;gap:16px}
.payments-topbar p{margin:6px 0 0;color:#475569}
.payments-kpi-grid{display:grid;grid-template-columns:repeat(5,minmax(130px,1fr));gap:12px}
.payments-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px;box-shadow:0 8px 20px rgba(15,23,42,.05)}
.payments-kpi strong{display:block;font-size:22px;line-height:1.1;color:#0f172a}
.payments-kpi span{display:block;margin-top:6px;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.04em}
.payments-summary-row{display:flex;gap:8px;flex-wrap:wrap}
.payments-summary-pill{background:#eff6ff;border:1px solid #bfdbfe;border-radius:999px;color:#1e3a8a;font-size:12px;padding:4px 10px}
.payments-filter-card{display:grid;gap:10px}
.payments-filter-grid{display:grid;grid-template-columns:minmax(220px,1fr) minmax(180px,220px) minmax(180px,220px) minmax(180px,220px) auto;gap:10px;align-items:end}
.payments-filter-note{margin:0;color:#64748b;font-size:12px}
.payments-board{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px}
.payment-card{background:#fff;border:1px solid #dbeafe;border-radius:12px;padding:12px;display:grid;gap:10px;box-shadow:0 10px 18px rgba(15,23,42,.06)}
.payment-card.order-pending{border-left:4px solid #f59e0b}
.payment-card.order-received{border-left:4px solid #3b82f6}
.payment-card.order-preparing{border-left:4px solid #6366f1}
.payment-card.order-ready{border-left:4px solid #22c55e}
.payment-card.order-delivered{border-left:4px solid #06b6d4}
.payment-card.order-finished{border-left:4px solid #16a34a}
.payment-card.order-canceled{border-left:4px solid #dc2626}
.payment-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px}
.payment-card-order strong{font-size:16px;color:#0f172a;line-height:1.25}
.payment-card-order span{font-size:12px;color:#64748b}
.payment-card-value{font-size:19px;font-weight:700;color:#0f172a;text-align:right}
.payment-meta-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.payment-meta-item{border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;padding:8px}
.payment-meta-item span{display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
.payment-meta-item strong{display:block;font-size:13px;color:#0f172a;word-break:break-word}
.payment-actions{display:flex;justify-content:space-between;gap:8px;align-items:center;flex-wrap:wrap}
.payments-empty{border:1px dashed #cbd5e1;border-radius:12px;padding:18px;color:#334155;background:#fff}
.payments-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.payments-pagination-controls{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.payments-page-btn{border:1px solid #cbd5e1;background:#fff;color:#0f172a;border-radius:8px;padding:7px 10px;cursor:pointer;min-width:36px}
.payments-page-btn.is-active{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
.payment-modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.6);display:grid;place-items:center;padding:14px;z-index:1300}
.payment-modal-backdrop[hidden]{display:none !important}
.payment-modal{width:min(980px,calc(100vw - 28px));max-height:calc(100vh - 28px);overflow:auto;background:#fff;border:1px solid #cbd5e1;border-radius:14px;padding:14px;display:grid;gap:12px}
.payment-modal-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}
.payment-modal-box{border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;padding:8px}
.payment-modal-box span{display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
.payment-modal-box strong{font-size:13px;color:#0f172a;display:block;word-break:break-word}
.payment-modal-items{border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;padding:10px;display:grid;gap:8px}
.payment-modal-item{border:1px solid #dbeafe;background:#fff;border-radius:10px;padding:10px;display:grid;gap:6px}
.payment-modal-item-top{display:flex;justify-content:space-between;gap:8px;align-items:flex-start;flex-wrap:wrap}
.payment-modal-item-name{font-weight:700;color:#0f172a}
.payment-modal-item-meta{font-size:12px;color:#475569}
.payment-modal-additional{padding-left:10px;font-size:12px;color:#334155}
.payment-modal-notes{border:1px dashed #cbd5e1;background:#fff;border-radius:10px;padding:8px;font-size:12px;color:#475569}
.payment-modal-actions{display:flex;justify-content:space-between;gap:8px;align-items:center;flex-wrap:wrap}
@media (max-width:1120px){.payments-kpi-grid{grid-template-columns:repeat(3,minmax(130px,1fr))}}
@media (max-width:980px){.payments-filter-grid{grid-template-columns:1fr 1fr}.payment-modal-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:680px){.payments-kpi-grid{grid-template-columns:repeat(2,minmax(130px,1fr))}.payments-board{grid-template-columns:1fr}.payment-meta-grid{grid-template-columns:1fr}.payments-filter-grid{grid-template-columns:1fr}.payment-modal-grid{grid-template-columns:1fr}}
</style>
<div class="payments-page">
    <div class="topbar payments-topbar">
        <div>
            <h1>Painel de Pagamentos</h1>
            <p>Visual moderno com filtros de periodo/status, paginação de 10 registros e modal completo do pedido.</p>
        </div>
        <a class="btn" href="<?= htmlspecialchars(base_url('/admin/payments/create')) ?>">Registrar pagamento</a>
    </div>

    <section class="payments-kpi-grid">
        <article class="payments-kpi"><strong><?= count($payments) ?></strong><span>Lancamentos</span></article>
        <article class="payments-kpi"><strong><?= $formatMoney($totalAmount) ?></strong><span>Total registrado</span></article>
        <article class="payments-kpi"><strong><?= $paidCount ?></strong><span>Pagos</span></article>
        <article class="payments-kpi"><strong><?= $todayCount ?></strong><span>Movimentacoes hoje</span></article>
        <article class="payments-kpi"><strong><?= $withReferenceCount ?></strong><span>Com referencia</span></article>
    </section>

    <?php if ($topMethods !== []): ?>
        <section class="card"><div class="payments-summary-row">
            <?php foreach ($topMethods as $methodName => $methodData): ?>
                <span class="payments-summary-pill"><?= htmlspecialchars((string) $methodName) ?>: <?= (int) ($methodData['count'] ?? 0) ?></span>
            <?php endforeach; ?>
        </div></section>
    <?php endif; ?>

    <section class="card payments-filter-card">
        <div class="payments-filter-grid">
            <div class="field" style="margin:0">
                <label for="paymentsSearch">Busca rapida</label>
                <input id="paymentsSearch" type="text" placeholder="Pedido, cliente, referencia, operador...">
            </div>
            <div class="field" style="margin:0">
                <label for="paymentsStatusFilter">Status do pedido</label>
                <select id="paymentsStatusFilter">
                    <option value="">Todos</option>
                    <?php foreach (array_keys($statusSummary) as $orderStatus): ?>
                        <option value="<?= htmlspecialchars($orderStatus) ?>"><?= htmlspecialchars($orderStatus === 'without_order' ? 'Sem pedido' : status_label('order_status', $orderStatus)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" style="margin:0">
                <label for="paymentsMethodFilter">Metodo</label>
                <select id="paymentsMethodFilter">
                    <option value="">Todos</option>
                    <?php foreach (array_keys($methodsSummary) as $methodName): ?>
                        <option value="<?= htmlspecialchars($methodName) ?>"><?= htmlspecialchars($methodName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" style="margin:0">
                <label for="paymentsPeriodFilter">Periodo de pagamento</label>
                <select id="paymentsPeriodFilter">
                    <option value="all">Todos</option>
                    <option value="today">Hoje</option>
                    <option value="yesterday">Ontem</option>
                    <option value="last7">Ultimos 7 dias</option>
                    <option value="last30">Ultimos 30 dias</option>
                    <option value="month_current">Mes atual</option>
                    <option value="month_previous">Mes anterior</option>
                    <option value="year_current">Ano atual</option>
                </select>
            </div>
            <button id="paymentsClearFilters" class="btn secondary" type="button">Limpar</button>
        </div>
        <p id="paymentsFilterInfo" class="payments-filter-note">Filtros ativos para acelerar conferencia do historico de pagamentos.</p>
    </section>

    <?php if (empty($payments)): ?>
        <div class="payments-empty">Nenhum pagamento encontrado.</div>
    <?php else: ?>
        <section class="payments-board" id="paymentsBoard">
            <?php foreach ($payments as $payment): ?>
                <?php
                $paymentId = (int) ($payment['id'] ?? 0);
                $orderId = $payment['order_id'] !== null ? (int) $payment['order_id'] : null;
                $orderStatus = trim((string) ($payment['order_status'] ?? 'without_order'));
                if ($orderStatus === '') {
                    $orderStatus = 'without_order';
                }
                $orderPaymentStatus = trim((string) ($payment['order_payment_status'] ?? 'pending'));
                $orderNumber = trim((string) ($payment['order_number'] ?? ''));
                $orderLabel = $orderNumber !== '' ? $orderNumber : 'Sem pedido vinculado';
                $methodName = trim((string) ($payment['payment_method_name'] ?? '-'));
                $reference = trim((string) ($payment['transaction_reference'] ?? ''));
                $receiverName = trim((string) ($payment['received_by_user_name'] ?? '-'));
                $paidAt = trim((string) ($payment['paid_at'] ?? ''));
                $createdAt = trim((string) ($payment['created_at'] ?? ''));
                $dateLabel = $paidAt !== '' ? $paidAt : $createdAt;
                $customerName = trim((string) ($payment['order_customer_name'] ?? ''));
                $tableNumberRaw = $payment['order_table_number'] ?? null;
                $tableLabel = $tableNumberRaw !== null ? 'Mesa ' . (int) $tableNumberRaw : 'Sem mesa';
                $searchText = implode(' ', [(string) $paymentId, (string) $orderLabel, (string) $customerName, (string) $methodName, (string) $reference, (string) $receiverName, (string) $dateLabel, (string) $tableLabel, (string) status_label('order_status', $orderStatus)]);
                ?>
                <article class="payment-card order-<?= htmlspecialchars(preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($orderStatus))) ?>" data-payment-card data-status="<?= htmlspecialchars($orderStatus) ?>" data-method="<?= htmlspecialchars($methodName) ?>" data-paid-at="<?= htmlspecialchars($dateLabel) ?>" data-search="<?= htmlspecialchars($searchText) ?>">
                    <header class="payment-card-head">
                        <div class="payment-card-order">
                            <strong><?= htmlspecialchars($orderLabel) ?></strong>
                            <span>Pagamento #<?= $paymentId ?> | <?= htmlspecialchars($tableLabel) ?></span>
                        </div>
                        <div class="payment-card-value"><?= $formatMoney($payment['amount'] ?? 0) ?></div>
                    </header>
                    <div class="payment-meta-grid">
                        <div class="payment-meta-item"><span>Metodo</span><strong><?= htmlspecialchars($methodName !== '' ? $methodName : '-') ?></strong></div>
                        <div class="payment-meta-item"><span>Status do pedido</span><strong><span class="badge <?= htmlspecialchars(status_badge_class('order_status', $orderStatus === 'without_order' ? null : $orderStatus)) ?>"><?= htmlspecialchars($orderStatus === 'without_order' ? 'Sem pedido' : status_label('order_status', $orderStatus)) ?></span></strong></div>
                        <div class="payment-meta-item"><span>Status financeiro</span><strong><span class="badge <?= htmlspecialchars(status_badge_class('order_payment_status', $orderPaymentStatus)) ?>"><?= htmlspecialchars(status_label('order_payment_status', $orderPaymentStatus)) ?></span></strong></div>
                        <div class="payment-meta-item"><span>Recebido por</span><strong><?= htmlspecialchars($receiverName !== '' ? $receiverName : '-') ?></strong></div>
                        <div class="payment-meta-item" style="grid-column:1 / -1"><span>Referencia / Historico</span><strong><?= htmlspecialchars($reference !== '' ? $reference : ((string) ($payment['payment_history_note'] ?? '-') ?: '-')) ?></strong></div>
                        <div class="payment-meta-item" style="grid-column:1 / -1"><span>Pago em</span><strong><?= htmlspecialchars($dateLabel !== '' ? $dateLabel : '-') ?></strong></div>
                    </div>
                    <footer class="payment-actions">
                        <span class="badge status-default">ID: <?= $paymentId ?></span>
                        <?php if ($orderId !== null && $orderId > 0): ?>
                            <button class="btn secondary" type="button" data-open-order-modal="<?= $paymentId ?>">Ver pedido</button>
                        <?php endif; ?>
                    </footer>
                </article>
                <?php if ($orderId !== null && $orderId > 0): ?>
                    <?php
                    $orderItems = is_array($payment['order_items'] ?? null) ? $payment['order_items'] : [];
                    $orderCreatedAt = trim((string) ($payment['order_created_at'] ?? ''));
                    $orderNotes = trim((string) ($payment['order_notes'] ?? ''));
                    $orderSubtotal = (float) ($payment['order_subtotal_amount'] ?? 0);
                    $orderDiscount = (float) ($payment['order_discount_amount'] ?? 0);
                    $orderDeliveryFee = (float) ($payment['order_delivery_fee'] ?? 0);
                    $orderTotal = (float) ($payment['order_total_amount'] ?? 0);
                    ?>
                    <template id="payment-order-modal-template-<?= $paymentId ?>">
                        <div class="payment-modal-header">
                            <div>
                                <h3><?= htmlspecialchars($orderLabel) ?></h3>
                                <p><?= htmlspecialchars($tableLabel) ?><?= $customerName !== '' ? ' | Cliente: ' . htmlspecialchars($customerName) : '' ?></p>
                            </div>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', $orderStatus)) ?>"><?= htmlspecialchars(status_label('order_status', $orderStatus)) ?></span>
                                <span class="badge <?= htmlspecialchars(status_badge_class('order_payment_status', $orderPaymentStatus)) ?>"><?= htmlspecialchars(status_label('order_payment_status', $orderPaymentStatus)) ?></span>
                            </div>
                        </div>

                        <div class="payment-modal-grid">
                            <div class="payment-modal-box"><span>Pedido</span><strong><?= htmlspecialchars($orderLabel) ?></strong></div>
                            <div class="payment-modal-box"><span>Criado em</span><strong><?= htmlspecialchars($orderCreatedAt !== '' ? $orderCreatedAt : '-') ?></strong></div>
                            <div class="payment-modal-box"><span>Metodo</span><strong><?= htmlspecialchars($methodName !== '' ? $methodName : '-') ?></strong></div>
                            <div class="payment-modal-box"><span>Pagamento</span><strong><?= $formatMoney($payment['amount'] ?? 0) ?></strong></div>
                            <div class="payment-modal-box"><span>Subtotal</span><strong><?= $formatMoney($orderSubtotal) ?></strong></div>
                            <div class="payment-modal-box"><span>Desconto</span><strong><?= $formatMoney($orderDiscount) ?></strong></div>
                            <div class="payment-modal-box"><span>Taxa entrega</span><strong><?= $formatMoney($orderDeliveryFee) ?></strong></div>
                            <div class="payment-modal-box"><span>Total pedido</span><strong><?= $formatMoney($orderTotal) ?></strong></div>
                        </div>

                        <section class="payment-modal-items">
                            <strong style="font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#64748b">Itens do pedido</strong>
                            <?php if ($orderItems === []): ?>
                                <div class="payment-modal-notes">Sem itens detalhados para este pedido.</div>
                            <?php else: ?>
                                <?php foreach ($orderItems as $item): ?>
                                    <?php
                                    $itemName = (string) ($item['name'] ?? 'Item');
                                    $itemQuantity = (int) ($item['quantity'] ?? 0);
                                    $itemSubtotal = (float) ($item['line_subtotal'] ?? 0);
                                    $itemUnitPrice = (float) ($item['unit_price'] ?? 0);
                                    $itemNotes = trim((string) ($item['notes'] ?? ''));
                                    $itemAdditionals = is_array($item['additionals'] ?? null) ? $item['additionals'] : [];
                                    ?>
                                    <article class="payment-modal-item">
                                        <div class="payment-modal-item-top">
                                            <span class="payment-modal-item-name"><?= $itemQuantity ?>x <?= htmlspecialchars($itemName) ?></span>
                                            <strong><?= $formatMoney($itemSubtotal) ?></strong>
                                        </div>
                                        <div class="payment-modal-item-meta">Unitario: <?= $formatMoney($itemUnitPrice) ?></div>
                                        <?php if ($itemNotes !== ''): ?>
                                            <div class="payment-modal-item-meta">Obs.: <?= htmlspecialchars($itemNotes) ?></div>
                                        <?php endif; ?>
                                        <?php foreach ($itemAdditionals as $additional): ?>
                                            <div class="payment-modal-additional">+ <?= (int) ($additional['quantity'] ?? 0) ?>x <?= htmlspecialchars((string) ($additional['name'] ?? 'Adicional')) ?> (<?= $formatMoney($additional['line_subtotal'] ?? 0) ?>)</div>
                                        <?php endforeach; ?>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </section>

                        <?php if ($orderNotes !== ''): ?>
                            <div class="payment-modal-notes"><strong>Observacoes do pedido:</strong> <?= htmlspecialchars($orderNotes) ?></div>
                        <?php endif; ?>

                        <div class="payment-modal-actions">
                            <a class="btn" href="<?= htmlspecialchars(base_url('/admin/orders/print-ticket?order_id=' . $orderId . '&return_to=payments')) ?>">Emitir ticket (previa/impressao)</a>
                            <button class="btn secondary" type="button" data-close-payment-modal>Fechar</button>
                        </div>
                    </template>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>

        <section class="card payments-pagination" id="paymentsPagination" hidden>
            <span class="payments-pagination-info" id="paymentsPaginationInfo"></span>
            <div class="payments-pagination-controls" id="paymentsPaginationControls"></div>
        </section>

        <div id="paymentsNoResults" class="payments-empty" hidden>Nenhum registro encontrado com os filtros atuais.</div>
    <?php endif; ?>
</div>

<div class="payment-modal-backdrop" id="paymentModalRoot" hidden>
    <section class="payment-modal" role="dialog" aria-modal="true"><div id="paymentModalBody"></div></section>
</div>
<script>
(() => {
    const board = document.getElementById('paymentsBoard');
    const cards = board ? Array.from(board.querySelectorAll('[data-payment-card]')) : [];
    const searchInput = document.getElementById('paymentsSearch');
    const statusFilter = document.getElementById('paymentsStatusFilter');
    const methodFilter = document.getElementById('paymentsMethodFilter');
    const periodFilter = document.getElementById('paymentsPeriodFilter');
    const clearButton = document.getElementById('paymentsClearFilters');
    const filterInfo = document.getElementById('paymentsFilterInfo');
    const noResults = document.getElementById('paymentsNoResults');
    const pagination = document.getElementById('paymentsPagination');
    const paginationInfo = document.getElementById('paymentsPaginationInfo');
    const paginationControls = document.getElementById('paymentsPaginationControls');
    const modalRoot = document.getElementById('paymentModalRoot');
    const modalBody = document.getElementById('paymentModalBody');

    if (!board || cards.length === 0 || !searchInput || !statusFilter || !methodFilter || !periodFilter || !clearButton) {
        return;
    }

    const pageSize = 10;
    let currentPage = 1;
    const normalize = (value) => String(value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

    const parseDate = (value) => {
        const raw = String(value || '').trim();
        if (raw === '') {
            return null;
        }
        const date = new Date(raw.replace(' ', 'T'));
        return Number.isNaN(date.getTime()) ? null : date;
    };

    const isDateInPeriod = (date, period) => {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return false;
        }
        if (period === 'all') {
            return true;
        }

        const now = new Date();
        const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());

        if (period === 'today') {
            return date >= startOfToday;
        }
        if (period === 'yesterday') {
            const start = new Date(startOfToday);
            start.setDate(start.getDate() - 1);
            return date >= start && date < startOfToday;
        }
        if (period === 'last7') {
            const start = new Date(startOfToday);
            start.setDate(start.getDate() - 6);
            return date >= start;
        }
        if (period === 'last30') {
            const start = new Date(startOfToday);
            start.setDate(start.getDate() - 29);
            return date >= start;
        }
        if (period === 'month_current') {
            return date.getFullYear() === now.getFullYear() && date.getMonth() === now.getMonth();
        }
        if (period === 'month_previous') {
            const previousMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            return date.getFullYear() === previousMonth.getFullYear() && date.getMonth() === previousMonth.getMonth();
        }
        if (period === 'year_current') {
            return date.getFullYear() === now.getFullYear();
        }
        return true;
    };

    const closeModal = () => {
        if (!modalRoot || !modalBody) {
            return;
        }
        modalRoot.hidden = true;
        modalBody.innerHTML = '';
        document.body.classList.remove('modal-open');
    };

    const openOrderModal = (paymentId) => {
        if (!modalRoot || !modalBody) {
            return;
        }

        const template = document.getElementById(`payment-order-modal-template-${paymentId}`);
        if (!(template instanceof HTMLTemplateElement)) {
            return;
        }

        modalBody.innerHTML = template.innerHTML;
        modalRoot.hidden = false;
        document.body.classList.add('modal-open');

        Array.from(modalBody.querySelectorAll('[data-close-payment-modal]')).forEach((button) => {
            button.addEventListener('click', closeModal);
        });
    };

    const renderPagination = (totalFiltered) => {
        if (!pagination || !paginationInfo || !paginationControls) {
            return;
        }

        if (totalFiltered <= 0) {
            pagination.hidden = true;
            paginationControls.innerHTML = '';
            paginationInfo.textContent = '';
            return;
        }

        pagination.hidden = false;
        const totalPages = Math.max(1, Math.ceil(totalFiltered / pageSize));
        if (currentPage > totalPages) {
            currentPage = 1;
        }

        const start = (currentPage - 1) * pageSize + 1;
        const end = Math.min(currentPage * pageSize, totalFiltered);
        paginationInfo.textContent = `Mostrando ${start}-${end} de ${totalFiltered} registro(s).`;
        paginationControls.innerHTML = '';

        const addButton = (label, page, disabled, active = false) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `payments-page-btn${active ? ' is-active' : ''}`;
            button.textContent = label;
            button.disabled = disabled;
            button.addEventListener('click', () => {
                if (!disabled && page !== currentPage) {
                    currentPage = page;
                    applyFilter(false);
                }
            });
            paginationControls.appendChild(button);
        };

        addButton('Anterior', Math.max(1, currentPage - 1), currentPage <= 1);
        const maxButtons = 5;
        const half = Math.floor(maxButtons / 2);
        let first = Math.max(1, currentPage - half);
        let last = Math.min(totalPages, first + maxButtons - 1);
        first = Math.max(1, last - maxButtons + 1);
        for (let page = first; page <= last; page += 1) {
            addButton(String(page), page, false, page === currentPage);
        }
        addButton('Proxima', Math.min(totalPages, currentPage + 1), currentPage >= totalPages);
    };

    const applyFilter = (resetPage = true) => {
        const searchText = normalize(searchInput.value);
        const statusValue = normalize(statusFilter.value);
        const methodValue = normalize(methodFilter.value);
        const periodValue = normalize(periodFilter.value || 'all');

        const filteredCards = cards.filter((card) => {
            const cardSearch = normalize(card.getAttribute('data-search'));
            const cardStatus = normalize(card.getAttribute('data-status'));
            const cardMethod = normalize(card.getAttribute('data-method'));
            const cardDate = parseDate(card.getAttribute('data-paid-at'));
            return (searchText === '' || cardSearch.includes(searchText))
                && (statusValue === '' || cardStatus === statusValue)
                && (methodValue === '' || cardMethod === methodValue)
                && isDateInPeriod(cardDate, periodValue === '' ? 'all' : periodValue);
        });

        if (resetPage) {
            currentPage = 1;
        }

        const from = (currentPage - 1) * pageSize;
        const visible = filteredCards.slice(from, from + pageSize);
        const visibleSet = new Set(visible);

        cards.forEach((card) => {
            card.style.display = visibleSet.has(card) ? '' : 'none';
        });

        if (filterInfo) {
            if (searchText === '' && statusValue === '' && methodValue === '' && periodValue === 'all') {
                filterInfo.textContent = 'Filtros ativos para acelerar conferencia do historico de pagamentos.';
            } else if (filteredCards.length > 0) {
                filterInfo.textContent = `Filtro ativo: ${filteredCards.length} registro(s) encontrados.`;
            } else {
                filterInfo.textContent = 'Nenhum registro encontrado para os filtros aplicados.';
            }
        }

        if (noResults) {
            noResults.hidden = filteredCards.length > 0;
        }

        renderPagination(filteredCards.length);
    };

    searchInput.addEventListener('input', () => applyFilter(true));
    statusFilter.addEventListener('change', () => applyFilter(true));
    methodFilter.addEventListener('change', () => applyFilter(true));
    periodFilter.addEventListener('change', () => applyFilter(true));

    clearButton.addEventListener('click', () => {
        searchInput.value = '';
        statusFilter.value = '';
        methodFilter.value = '';
        periodFilter.value = 'all';
        applyFilter(true);
        searchInput.focus();
    });

    Array.from(document.querySelectorAll('[data-open-order-modal]')).forEach((button) => {
        button.addEventListener('click', () => {
            const paymentId = Number(button.getAttribute('data-open-order-modal') || 0);
            if (paymentId > 0) {
                openOrderModal(paymentId);
            }
        });
    });

    if (modalRoot) {
        modalRoot.addEventListener('click', (event) => {
            if (event.target === modalRoot) {
                closeModal();
            }
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modalRoot && !modalRoot.hidden) {
            closeModal();
        }
    });

    applyFilter(true);
})();
</script>
