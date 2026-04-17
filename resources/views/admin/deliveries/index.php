<?php
$deliveries = is_array($deliveries ?? null) ? $deliveries : [];
$deliveriesSummary = is_array($deliveriesSummary ?? null) ? $deliveriesSummary : [];
$deliveryUsers = is_array($deliveryUsers ?? null) ? $deliveryUsers : [];
$isDeliveryRole = !empty($isDeliveryRole);
$currentUserId = (int) ($currentUserId ?? 0);

$formatMoney = static fn (mixed $value): string => 'R$ ' . number_format((float) $value, 2, ',', '.');
$statuses = ['pending', 'assigned', 'in_route', 'delivered', 'failed', 'canceled'];
$channelsSummary = [];

foreach ($deliveries as $delivery) {
    if (!is_array($delivery)) {
        continue;
    }
    $channel = trim((string) ($delivery['channel'] ?? 'delivery'));
    if ($channel === '') {
        $channel = 'delivery';
    }
    if (!isset($channelsSummary[$channel])) {
        $channelsSummary[$channel] = 0;
    }
    $channelsSummary[$channel]++;
}
ksort($channelsSummary);
?>

<style>
    .deliveries-page{display:grid;gap:16px}
    .deliveries-topbar p{margin:6px 0 0;color:#475569}
    .deliveries-kpi-grid{display:grid;grid-template-columns:repeat(7,minmax(120px,1fr));gap:12px}
    .deliveries-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px;box-shadow:0 8px 20px rgba(15,23,42,.05)}
    .deliveries-kpi strong{display:block;font-size:22px;line-height:1.1;color:#0f172a}
    .deliveries-kpi span{display:block;margin-top:6px;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.04em}
    .deliveries-summary-row{display:flex;gap:8px;flex-wrap:wrap}
    .deliveries-summary-pill{background:#eff6ff;border:1px solid #bfdbfe;border-radius:999px;color:#1e3a8a;font-size:12px;padding:4px 10px}
    .deliveries-filter-card{display:grid;gap:10px}
    .deliveries-filter-grid{display:grid;grid-template-columns:minmax(220px,1fr) minmax(180px,220px) minmax(170px,220px) minmax(170px,220px) minmax(170px,220px) auto;gap:10px;align-items:end}
    .deliveries-filter-note{margin:0;color:#64748b;font-size:12px}
    .deliveries-board{display:grid;grid-template-columns:repeat(auto-fill,minmax(330px,1fr));gap:12px}
    .delivery-card{background:#fff;border:1px solid #dbeafe;border-radius:12px;padding:12px;display:grid;gap:10px;box-shadow:0 10px 18px rgba(15,23,42,.06)}
    .delivery-card.status-pending{border-left:4px solid #f59e0b}
    .delivery-card.status-assigned{border-left:4px solid #3b82f6}
    .delivery-card.status-in_route{border-left:4px solid #6366f1}
    .delivery-card.status-delivered{border-left:4px solid #16a34a}
    .delivery-card.status-failed{border-left:4px solid #dc2626}
    .delivery-card.status-canceled{border-left:4px solid #64748b}
    .delivery-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px}
    .delivery-head strong{display:block;font-size:16px;color:#0f172a;line-height:1.25;word-break:break-word}
    .delivery-head span{font-size:12px;color:#64748b;word-break:break-word}
    .delivery-badges{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}
    .delivery-meta-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    .delivery-meta-item{border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;padding:8px}
    .delivery-meta-item span{display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
    .delivery-meta-item strong{display:block;font-size:13px;color:#0f172a;word-break:break-word}
    .delivery-address{border:1px dashed #cbd5e1;border-radius:10px;background:#fff;padding:8px;font-size:12px;color:#334155;line-height:1.4;word-break:break-word}
    .delivery-actions{display:grid;gap:8px}
    .delivery-actions-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
    .delivery-actions-row{display:flex;gap:8px;flex-wrap:wrap}
    .deliveries-empty{border:1px dashed #cbd5e1;border-radius:12px;padding:18px;color:#334155;background:#fff}
    .deliveries-págination{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .deliveries-págination-controls{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .deliveries-page-btn{border:1px solid #cbd5e1;background:#fff;color:#0f172a;border-radius:8px;padding:7px 10px;cursor:pointer;min-width:36px}
    .deliveries-page-btn[disabled]{opacity:.5;cursor:not-allowed}
    .deliveries-page-btn.is-active{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
    @media (max-width:1200px){.deliveries-kpi-grid{grid-template-columns:repeat(4,minmax(120px,1fr))}.deliveries-filter-grid{grid-template-columns:1fr 1fr 1fr}}
    @media (max-width:900px){.deliveries-filter-grid{grid-template-columns:1fr 1fr}}
    @media (max-width:680px){.deliveries-kpi-grid{grid-template-columns:repeat(2,minmax(120px,1fr))}.deliveries-board{grid-template-columns:1fr}.delivery-meta-grid,.delivery-actions-grid,.deliveries-filter-grid{grid-template-columns:1fr}}
</style>

<div class="deliveries-page">
    <div class="topbar deliveries-topbar">
        <div>
            <h1>Painel de Entregas</h1>
            <p>Fluxo moderno com filtros, páginação (10 por página), atualização em 30s e ações rápidas de entrega.</p>
        </div>
        <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/delivery-zones')) ?>">Gerenciar zonas e taxas</a>
    </div>

    <section class="deliveries-kpi-grid">
        <article class="deliveries-kpi"><strong><?= (int) ($deliveriesSummary['total'] ?? 0) ?></strong><span>Total</span></article>
        <article class="deliveries-kpi"><strong><?= (int) ($deliveriesSummary['pending'] ?? 0) ?></strong><span>Pendentes</span></article>
        <article class="deliveries-kpi"><strong><?= (int) ($deliveriesSummary['assigned'] ?? 0) ?></strong><span>Atribuídas</span></article>
        <article class="deliveries-kpi"><strong><?= (int) ($deliveriesSummary['in_route'] ?? 0) ?></strong><span>Em rota</span></article>
        <article class="deliveries-kpi"><strong><?= (int) ($deliveriesSummary['delivered'] ?? 0) ?></strong><span>Entregues</span></article>
        <article class="deliveries-kpi"><strong><?= (int) ($deliveriesSummary['failed'] ?? 0) ?></strong><span>Falhas</span></article>
        <article class="deliveries-kpi"><strong><?= (int) ($deliveriesSummary['canceled'] ?? 0) ?></strong><span>Canceladas</span></article>
    </section>

    <?php if ($channelsSummary !== []): ?>
        <section class="card"><div class="deliveries-summary-row">
            <?php foreach ($channelsSummary as $channel => $count): ?>
                <span class="deliveries-summary-pill"><?= htmlspecialchars(status_label('order_channel', $channel)) ?>: <?= (int) $count ?></span>
            <?php endforeach; ?>
        </div></section>
    <?php endif; ?>

    <section class="card deliveries-filter-card">
        <div class="deliveries-filter-grid">
            <div class="field" style="margin:0">
                <label for="deliveriesSearch">Busca rápida</label>
                <input id="deliveriesSearch" type="text" placeholder="Pedido, cliente, endereço, zona, motoboy...">
            </div>
            <div class="field" style="margin:0">
                <label for="deliveriesStatusFilter">Status da entrega</label>
                <select id="deliveriesStatusFilter">
                    <option value="">Todos</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars(status_label('delivery_status', $status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" style="margin:0">
                <label for="deliveriesChannelFilter">Canal</label>
                <select id="deliveriesChannelFilter">
                    <option value="">Todos</option>
                    <?php foreach (array_keys($channelsSummary) as $channel): ?>
                        <option value="<?= htmlspecialchars((string) $channel) ?>"><?= htmlspecialchars(status_label('order_channel', $channel)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" style="margin:0">
                <label for="deliveriesCourierFilter">Motoboy</label>
                <select id="deliveriesCourierFilter">
                    <option value="">Todos</option>
                    <?php foreach ($deliveryUsers as $deliveryUser): ?>
                        <?php
                        if (!is_array($deliveryUser)) {
                            continue;
                        }
                        $candidateId = (int) ($deliveryUser['id'] ?? 0);
                        if ($candidateId <= 0) {
                            continue;
                        }
                        if ($isDeliveryRole && $currentUserId > 0 && $candidateId !== $currentUserId) {
                            continue;
                        }
                        ?>
                        <option value="<?= $candidateId ?>"><?= htmlspecialchars((string) ($deliveryUser['name'] ?? ('Usuário #' . $candidateId))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" style="margin:0">
                <label for="deliveriesPeriodFilter">Período</label>
                <select id="deliveriesPeriodFilter">
                    <option value="all">Todos</option>
                    <option value="today">Hoje</option>
                    <option value="yesterday">Ontem</option>
                    <option value="last7">Últimos 7 dias</option>
                    <option value="last30">Últimos 30 dias</option>
                    <option value="month_current">Mês atual</option>
                    <option value="month_previous">Mês anterior</option>
                    <option value="year_current">Ano atual</option>
                </select>
            </div>
            <button id="deliveriesClearFilters" class="btn secondary" type="button">Limpar</button>
        </div>
        <p id="deliveriesFilterInfo" class="deliveries-filter-note">Filtros ativos para acelerar operação do painel de entregas.</p>
    </section>

    <?php if ($deliveries === []): ?>
        <div class="deliveries-empty">Nenhuma entrega registrada ainda.</div>
    <?php else: ?>
        <section class="deliveries-board" id="deliveriesBoard">
            <?php foreach ($deliveries as $delivery): ?>
                <?php
                if (!is_array($delivery)) {
                    continue;
                }
                $deliveryId = (int) ($delivery['id'] ?? 0);
                if ($deliveryId <= 0) {
                    continue;
                }
                $orderId = (int) ($delivery['order_id'] ?? 0);
                $status = trim((string) ($delivery['status'] ?? 'pending'));
                if ($status === '') {
                    $status = 'pending';
                }
                $channel = trim((string) ($delivery['channel'] ?? 'delivery'));
                if ($channel === '') {
                    $channel = 'delivery';
                }
                $orderNumber = trim((string) ($delivery['order_number'] ?? 'Pedido sem número'));
                $customerName = trim((string) ($delivery['customer_name'] ?? ''));
                $deliveryUserId = $delivery['delivery_user_id'] !== null ? (int) $delivery['delivery_user_id'] : 0;
                $createdAt = trim((string) ($delivery['created_at'] ?? ''));
                $addressSummary = trim(implode(', ', array_filter([
                    (string) ($delivery['street'] ?? ''),
                    (string) ($delivery['number'] ?? ''),
                    (string) ($delivery['complement'] ?? ''),
                    (string) ($delivery['neighborhood'] ?? ''),
                    (string) ($delivery['city'] ?? ''),
                    (string) ($delivery['state'] ?? ''),
                ], static fn (string $value): bool => trim($value) !== '')));
                $searchText = implode(' ', [
                    (string) $deliveryId,
                    (string) $orderId,
                    (string) $orderNumber,
                    (string) $status,
                    (string) status_label('delivery_status', $status),
                    (string) $channel,
                    (string) status_label('order_channel', $channel),
                    (string) ($delivery['zone_name'] ?? ''),
                    (string) ($delivery['delivery_user_name'] ?? ''),
                    (string) $customerName,
                    (string) $addressSummary,
                    (string) ($delivery['reference'] ?? ''),
                    (string) ($delivery['notes'] ?? ''),
                    (string) $createdAt,
                ]);
                ?>
                <article
                    class="delivery-card status-<?= htmlspecialchars($status) ?>"
                    data-delivery-card
                    data-status="<?= htmlspecialchars($status) ?>"
                    data-channel="<?= htmlspecialchars($channel) ?>"
                    data-courier-id="<?= $deliveryUserId > 0 ? (string) $deliveryUserId : '' ?>"
                    data-created-at="<?= htmlspecialchars($createdAt) ?>"
                    data-search="<?= htmlspecialchars($searchText) ?>"
                >
                    <header class="delivery-head">
                        <div>
                            <strong><?= htmlspecialchars($orderNumber !== '' ? $orderNumber : 'Pedido') ?></strong>
                            <span>Entrega #<?= $deliveryId ?><?= $customerName !== '' ? ' | Cliente: ' . htmlspecialchars($customerName) : '' ?></span>
                        </div>
                        <div class="delivery-badges">
                            <span class="badge <?= htmlspecialchars(status_badge_class('delivery_status', $status)) ?>"><?= htmlspecialchars(status_label('delivery_status', $status)) ?></span>
                            <span class="badge <?= htmlspecialchars(status_badge_class('order_channel', $channel)) ?>"><?= htmlspecialchars(status_label('order_channel', $channel)) ?></span>
                        </div>
                    </header>

                    <div class="delivery-meta-grid">
                        <div class="delivery-meta-item"><span>Taxa</span><strong><?= $formatMoney($delivery['delivery_fee'] ?? 0) ?></strong></div>
                        <div class="delivery-meta-item"><span>Zona</span><strong><?= htmlspecialchars((string) ($delivery['zone_name'] ?? 'Sem zona')) ?></strong></div>
                        <div class="delivery-meta-item"><span>Motoboy</span><strong><?= htmlspecialchars((string) ($delivery['delivery_user_name'] ?? 'Não atribuído')) ?></strong></div>
                        <div class="delivery-meta-item"><span>Criada em</span><strong><?= htmlspecialchars($createdAt !== '' ? $createdAt : '-') ?></strong></div>
                        <div class="delivery-meta-item"><span>Atribuída</span><strong><?= htmlspecialchars((string) ($delivery['assigned_at'] ?? '-') ?: '-') ?></strong></div>
                        <div class="delivery-meta-item"><span>Saiu para rota</span><strong><?= htmlspecialchars((string) ($delivery['left_at'] ?? '-') ?: '-') ?></strong></div>
                        <div class="delivery-meta-item"><span>Concluída</span><strong><?= htmlspecialchars((string) ($delivery['delivered_at'] ?? '-') ?: '-') ?></strong></div>
                        <div class="delivery-meta-item"><span>Status do pedido</span><strong><span class="badge <?= htmlspecialchars(status_badge_class('order_status', (string) ($delivery['order_status'] ?? 'pending'))) ?>"><?= htmlspecialchars(status_label('order_status', (string) ($delivery['order_status'] ?? 'pending'))) ?></span></strong></div>
                    </div>

                    <div class="delivery-address">
                        <strong>Endereço:</strong> <?= htmlspecialchars($addressSummary !== '' ? $addressSummary : 'Não informado') ?><br>
                        <?php if (!empty($delivery['reference'])): ?>
                            <strong>Referência:</strong> <?= htmlspecialchars((string) $delivery['reference']) ?><br>
                        <?php endif; ?>
                        <?php if (!empty($delivery['notes'])): ?>
                            <strong>Obs. entrega:</strong> <?= htmlspecialchars((string) $delivery['notes']) ?>
                        <?php endif; ?>
                    </div>

                    <form class="delivery-actions" method="POST" action="<?= htmlspecialchars(base_url('/admin/deliveries/update')) ?>">
                        <?= form_security_fields('deliveries.update') ?>
                        <input type="hidden" name="delivery_id" value="<?= $deliveryId ?>">
                        <div class="delivery-actions-grid">
                            <div class="field" style="margin:0">
                                <label>Motoboy</label>
                                <select name="delivery_user_id">
                                    <option value="">Não atribuído</option>
                                    <?php foreach ($deliveryUsers as $deliveryUser): ?>
                                        <?php
                                        if (!is_array($deliveryUser)) {
                                            continue;
                                        }
                                        $candidateId = (int) ($deliveryUser['id'] ?? 0);
                                        if ($candidateId <= 0) {
                                            continue;
                                        }
                                        if ($isDeliveryRole && $currentUserId > 0 && $candidateId !== $currentUserId) {
                                            continue;
                                        }
                                        ?>
                                        <option value="<?= $candidateId ?>" <?= $deliveryUserId === $candidateId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string) ($deliveryUser['name'] ?? ('Usuário #' . $candidateId))) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field" style="margin:0">
                                <label>Novo status</label>
                                <select name="new_status" required>
                                    <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars(status_label('delivery_status', $status)) ?> (atual)</option>
                                    <?php foreach ($statuses as $candidateStatus): ?>
                                        <?php if ($candidateStatus !== $status): ?>
                                            <option value="<?= htmlspecialchars($candidateStatus) ?>"><?= htmlspecialchars(status_label('delivery_status', $candidateStatus)) ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="field" style="margin:0">
                            <label>Observações</label>
                            <input name="notes" type="text" value="<?= htmlspecialchars((string) ($delivery['notes'] ?? '')) ?>" placeholder="Opcional">
                        </div>
                        <div class="delivery-actions-row">
                            <?php if ($orderId > 0): ?>
                                <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/orders/print-ticket?order_id=' . $orderId . '&return_to=deliveries')) ?>">Emitir ticket (prévia/impressão)</a>
                            <?php endif; ?>
                            <button class="btn secondary" type="submit">Atualizar entrega</button>
                        </div>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="card deliveries-págination" id="deliveriesPagination" hidden>
            <span id="deliveriesPaginationInfo"></span>
            <div id="deliveriesPaginationControls" class="deliveries-págination-controls"></div>
        </section>

        <div id="deliveriesNoResults" class="deliveries-empty" hidden>Nenhuma entrega encontrada com os filtros atuais.</div>
    <?php endif; ?>
</div>

<script>
(() => {
    const board = document.getElementById('deliveriesBoard');
    const cards = board ? Array.from(board.querySelectorAll('[data-delivery-card]')) : [];
    const searchInput = document.getElementById('deliveriesSearch');
    const statusFilter = document.getElementById('deliveriesStatusFilter');
    const channelFilter = document.getElementById('deliveriesChannelFilter');
    const courierFilter = document.getElementById('deliveriesCourierFilter');
    const periodFilter = document.getElementById('deliveriesPeriodFilter');
    const clearButton = document.getElementById('deliveriesClearFilters');
    const filterInfo = document.getElementById('deliveriesFilterInfo');
    const noResults = document.getElementById('deliveriesNoResults');
    const págination = document.getElementById('deliveriesPagination');
    const páginationInfo = document.getElementById('deliveriesPaginationInfo');
    const páginationControls = document.getElementById('deliveriesPaginationControls');
    const refreshMs = 30000;

    window.setInterval(() => {
        if (document.hidden) {
            return;
        }
        const activeElement = document.activeElement;
        if (activeElement instanceof HTMLElement) {
            const tagName = activeElement.tagName;
            if (tagName === 'INPUT' || tagName === 'TEXTAREA' || tagName === 'SELECT') {
                return;
            }
        }
        window.location.reload();
    }, refreshMs);

    if (!board || cards.length === 0 || !searchInput || !statusFilter || !channelFilter || !courierFilter || !periodFilter || !clearButton) {
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

    const renderPagination = (totalFiltered) => {
        if (!págination || !páginationInfo || !páginationControls) {
            return;
        }

        if (totalFiltered <= 0) {
            págination.hidden = true;
            páginationInfo.textContent = '';
            páginationControls.innerHTML = '';
            return;
        }

        págination.hidden = false;
        const totalPages = Math.max(1, Math.ceil(totalFiltered / pageSize));
        if (currentPage > totalPages) {
            currentPage = 1;
        }

        const start = (currentPage - 1) * pageSize + 1;
        const end = Math.min(currentPage * pageSize, totalFiltered);
        páginationInfo.textContent = `Mostrando ${start}-${end} de ${totalFiltered} entrega(s).`;
        páginationControls.innerHTML = '';

        const addButton = (label, page, disabled, active = false) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `deliveries-page-btn${active ? ' is-active' : ''}`;
            button.textContent = label;
            button.disabled = disabled;
            button.addEventListener('click', () => {
                if (!disabled && page !== currentPage) {
                    currentPage = page;
                    applyFilter(false);
                }
            });
            páginationControls.appendChild(button);
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
        addButton('Próxima', Math.min(totalPages, currentPage + 1), currentPage >= totalPages);
    };

    const applyFilter = (resetPage = true) => {
        const searchText = normalize(searchInput.value);
        const statusValue = normalize(statusFilter.value);
        const channelValue = normalize(channelFilter.value);
        const courierValue = normalize(courierFilter.value);
        const periodValue = normalize(periodFilter.value || 'all');

        const filteredCards = cards.filter((card) => {
            const cardSearch = normalize(card.getAttribute('data-search'));
            const cardStatus = normalize(card.getAttribute('data-status'));
            const cardChannel = normalize(card.getAttribute('data-channel'));
            const cardCourier = normalize(card.getAttribute('data-courier-id'));
            const cardDate = parseDate(card.getAttribute('data-created-at'));
            return (searchText === '' || cardSearch.includes(searchText))
                && (statusValue === '' || cardStatus === statusValue)
                && (channelValue === '' || cardChannel === channelValue)
                && (courierValue === '' || cardCourier === courierValue)
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
            if (searchText === '' && statusValue === '' && channelValue === '' && courierValue === '' && periodValue === 'all') {
                filterInfo.textContent = 'Filtros ativos para acelerar operação do painel de entregas.';
            } else if (filteredCards.length > 0) {
                filterInfo.textContent = `Filtro ativo: ${filteredCards.length} entrega(s) encontrada(s).`;
            } else {
                filterInfo.textContent = 'Nenhuma entrega encontrada para os filtros aplicados.';
            }
        }

        if (noResults) {
            noResults.hidden = filteredCards.length > 0;
        }

        renderPagination(filteredCards.length);
    };

    searchInput.addEventListener('input', () => applyFilter(true));
    statusFilter.addEventListener('change', () => applyFilter(true));
    channelFilter.addEventListener('change', () => applyFilter(true));
    courierFilter.addEventListener('change', () => applyFilter(true));
    periodFilter.addEventListener('change', () => applyFilter(true));

    clearButton.addEventListener('click', () => {
        searchInput.value = '';
        statusFilter.value = '';
        channelFilter.value = '';
        courierFilter.value = '';
        periodFilter.value = 'all';
        applyFilter(true);
        searchInput.focus();
    });

    applyFilter(true);
})();
</script>
