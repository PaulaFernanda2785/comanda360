<?php
$totalSales = (float) ($kpis['total_sales'] ?? 0);
$avgTicket = (float) ($kpis['avg_ticket'] ?? 0);
$canceledOrders = (int) ($kpis['canceled_orders'] ?? 0);
$totalOrders = (int) ($kpis['total_orders'] ?? 0);
$closedCashAmount = (float) ($cashKpis['total_closed_amount'] ?? 0);
$totalClosings = (int) ($cashKpis['total_closings'] ?? 0);
$totalDifference = (float) ($cashKpis['total_difference'] ?? 0);

$maxDaySales = 0.0;
foreach ($salesByDay as $row) {
    $value = (float) ($row['total_sales'] ?? 0);
    if ($value > $maxDaySales) {
        $maxDaySales = $value;
    }
}

$statusColorMap = [
    'pending' => '#f59e0b',
    'received' => '#3b82f6',
    'preparing' => '#6366f1',
    'ready' => '#22c55e',
    'delivered' => '#06b6d4',
    'finished' => '#10b981',
    'paid' => '#16a34a',
    'canceled' => '#ef4444',
];

$totalStatusOrders = 0;
foreach ($ordersByStatus as $statusRow) {
    $totalStatusOrders += (int) ($statusRow['total_orders'] ?? 0);
}

$donutOffset = 0.0;
$donutStops = [];
foreach ($ordersByStatus as $statusRow) {
    $statusKey = strtolower(trim((string) ($statusRow['status'] ?? '')));
    $count = (int) ($statusRow['total_orders'] ?? 0);
    if ($count <= 0 || $totalStatusOrders <= 0) {
        continue;
    }
    $percentage = ($count / $totalStatusOrders) * 100;
    $color = $statusColorMap[$statusKey] ?? '#94a3b8';
    $start = $donutOffset;
    $end = min(100, $donutOffset + $percentage);
    $donutStops[] = $color . ' ' . number_format($start, 4, '.', '') . '% ' . number_format($end, 4, '.', '') . '%';
    $donutOffset = $end;
}
$donutBackground = $donutStops !== [] ? implode(', ', $donutStops) : '#e2e8f0 0% 100%';
?>

<section class="dash-section<?= $activeSection === 'overview' ? ' active' : '' ?>" data-section="overview">
    <div class="card" style="background:linear-gradient(115deg,#0f172a 0%,#1e293b 55%,#334155 100%);color:#fff;overflow:hidden;position:relative">
        <div style="position:absolute;top:-38px;right:-60px;width:220px;height:220px;border-radius:999px;background:rgba(56,189,248,.18)"></div>
        <div style="position:absolute;bottom:-50px;left:-42px;width:200px;height:200px;border-radius:999px;background:rgba(34,197,94,.15)"></div>
        <div style="position:relative;z-index:1">
            <h2 style="margin:0 0 8px">Painel Estatístico Inteligente</h2>
            <p style="margin:0;color:#cbd5e1;max-width:840px">Visual moderno com filtros avançados, indicadores executivos e ação de gerar relatório em prévia na mesma aba com impressão integrada.</p>
        </div>
    </div>

    <div class="card">
        <form method="GET" action="<?= htmlspecialchars(base_url('/admin/dashboard')) ?>">
            <input type="hidden" name="section" value="overview">
            <div class="dash-filter-grid" style="grid-template-columns:repeat(4,minmax(140px,1fr));margin-bottom:10px">
                <div class="field" style="margin:0">
                    <label for="dash_period_preset">Período rápido</label>
                    <select id="dash_period_preset" name="period_preset">
                        <?php foreach ($periodPresetOptions as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>" <?= $periodPresetFilter === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field" style="margin:0">
                    <label for="dash_start_date">Data inicial</label>
                    <input id="dash_start_date" type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="field" style="margin:0">
                    <label for="dash_end_date">Data final</label>
                    <input id="dash_end_date" type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <div class="field" style="margin:0">
                    <label for="dash_status">Status do pedido</label>
                    <select id="dash_status" name="status">
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field" style="margin:0">
                    <label for="dash_channel">Canal</label>
                    <select id="dash_channel" name="channel">
                        <?php foreach ($channelOptions as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>" <?= $channelFilter === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field" style="margin:0">
                    <label for="dash_payment_status">Status do pagamento</label>
                    <select id="dash_payment_status" name="payment_status">
                        <?php foreach ($paymentStatusOptions as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>" <?= $paymentStatusFilter === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field" style="margin:0">
                    <label for="dash_min_amount">Valor mínimo</label>
                    <input id="dash_min_amount" type="number" step="0.01" min="0" name="min_amount" value="<?= htmlspecialchars($minAmountFilter) ?>" placeholder="0,00">
                </div>
                <div class="field" style="margin:0">
                    <label for="dash_max_amount">Valor máximo</label>
                    <input id="dash_max_amount" type="number" step="0.01" min="0" name="max_amount" value="<?= htmlspecialchars($maxAmountFilter) ?>" placeholder="9999,99">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr auto auto;gap:10px;align-items:end">
                <div class="field" style="margin:0">
                    <label for="dash_search">Busca (pedido, cliente, canal, status)</label>
                    <input id="dash_search" type="text" name="search" value="<?= htmlspecialchars($searchFilter) ?>" placeholder="Ex.: #PDV-20260410-0001 ou Ana">
                </div>
                <button class="btn" type="submit">Aplicar filtros</button>
                <button
                    class="btn secondary"
                    type="submit"
                    formaction="<?= htmlspecialchars(base_url('/admin/dashboard/report')) ?>"
                    formmethod="GET"
                >
                    Gerar relatório
                </button>
            </div>
        </form>
        <p class="muted" style="margin:10px 0 0">Filtros operacionais + financeiros aplicados em tempo real no painel e no relatório prévio.</p>
    </div>

    <?php if (!$reportViews['ready']): ?>
        <div class="card" style="border:1px solid #fecaca;background:#fff1f2">
            <h3 style="margin-top:0;color:#9f1239">Views de relatorio ausentes no banco</h3>
            <p class="ticket-note">Execute `basedados/schema_views_relatorios_comanda360.sql` para liberar o painel.</p>
            <p class="ticket-note"><strong>Views faltantes:</strong> <?= htmlspecialchars(implode(', ', is_array($reportViews['missing'] ?? null) ? $reportViews['missing'] : [])) ?></p>
        </div>
    <?php endif; ?>

    <div class="dash-kpi-grid">
        <article class="dash-kpi"><strong><?= $formatMoney($totalSales) ?></strong><span>Vendas</span></article>
        <article class="dash-kpi"><strong><?= $formatMoney($avgTicket) ?></strong><span>Ticket médio</span></article>
        <article class="dash-kpi"><strong><?= $formatInt($canceledOrders) ?></strong><span>Cancelados</span></article>
        <article class="dash-kpi"><strong><?= $formatMoney($closedCashAmount) ?></strong><span>Fechamento de caixa</span></article>
        <article class="dash-kpi"><strong><?= $formatInt($totalOrders) ?></strong><span>Total pedidos</span></article>
        <article class="dash-kpi"><strong><?= $formatMoney($totalDifference) ?></strong><span>Divergência caixa</span></article>
    </div>

    <div class="dash-grid-2">
        <div class="card">
            <h3 style="margin-top:0">Evolução de vendas por dia</h3>
            <?php if ($salesByDay === []): ?>
                <div class="empty-state">Sem dados no período selecionado.</div>
            <?php else: ?>
                <div class="dash-bars">
                    <?php foreach ($salesByDay as $row): ?>
                        <?php
                        $rawDate = (string) ($row['report_date'] ?? '');
                        $shortDate = $rawDate !== '' ? date('d/m', strtotime($rawDate . ' 00:00:00')) : '--/--';
                        $dayValue = (float) ($row['total_sales'] ?? 0);
                        $dayOrders = (int) ($row['total_orders'] ?? 0);
                        $pct = $maxDaySales > 0 ? max(2, ($dayValue / $maxDaySales) * 100) : 2;
                        ?>
                        <div class="dash-bar-row" data-dash-sales-day-row>
                            <span class="dash-label"><?= htmlspecialchars($shortDate) ?></span>
                            <div class="dash-bar-track"><span class="dash-bar-fill" style="width:<?= number_format($pct, 2, '.', '') ?>%"></span></div>
                            <span class="dash-value"><?= htmlspecialchars($formatMoney($dayValue)) ?> / <?= $dayOrders ?> ped.</span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <section class="dash-págination" id="dashSalesDayPagination" hidden>
                    <span class="dash-págination-info" id="dashSalesDayPaginationInfo"></span>
                    <div class="dash-págination-controls" id="dashSalesDayPaginationControls"></div>
                </section>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 style="margin-top:0">Distribuição de pedidos por status</h3>
            <div class="dash-donut-wrap">
                <div class="dash-donut" style="background:conic-gradient(<?= htmlspecialchars($donutBackground) ?>)">
                    <div class="dash-donut-hole"><strong><?= $totalStatusOrders ?></strong><span>pedidos</span></div>
                </div>
                <div class="dash-legend" style="width:100%">
                    <?php if ($ordersByStatus === []): ?>
                        <div class="empty-state">Nenhum status para exibir.</div>
                    <?php else: ?>
                        <?php foreach ($ordersByStatus as $statusRow): ?>
                            <?php
                            $statusRaw = strtolower(trim((string) ($statusRow['status'] ?? 'pending')));
                            $statusCount = (int) ($statusRow['total_orders'] ?? 0);
                            $statusTotalValue = (float) ($statusRow['total_amount'] ?? 0);
                            $dotColor = $statusColorMap[$statusRaw] ?? '#94a3b8';
                            ?>
                            <div class="dash-legend-item">
                                <span><i class="dash-dot" style="background:<?= htmlspecialchars($dotColor) ?>"></i><?= htmlspecialchars(status_label('order_status', $statusRaw)) ?></span>
                                <strong><?= $statusCount ?> / <?= htmlspecialchars($formatMoney($statusTotalValue)) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="dash-grid-3">
        <div class="card">
            <h3 style="margin-top:0">Vendas por canal</h3>
            <?php if ($salesByChannel === []): ?>
                <div class="empty-state">Sem dados por canal no período.</div>
            <?php else: ?>
                <table class="dash-table">
                    <thead><tr><th>Canal</th><th>Pedidos</th><th>Total vendido</th></tr></thead>
                    <tbody>
                        <?php foreach ($salesByChannel as $row): ?>
                            <tr data-dash-sales-channel-row>
                                <td><?= htmlspecialchars(status_label('order_channel', (string) ($row['channel'] ?? ''))) ?></td>
                                <td><?= (int) ($row['total_orders'] ?? 0) ?></td>
                                <td><?= htmlspecialchars($formatMoney((float) ($row['total_sales'] ?? 0))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <section class="dash-págination" id="dashSalesChannelPagination" hidden>
                    <span class="dash-págination-info" id="dashSalesChannelPaginationInfo"></span>
                    <div class="dash-págination-controls" id="dashSalesChannelPaginationControls"></div>
                </section>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 style="margin-top:0">Pagamentos por situação</h3>
            <?php if ($paymentSummary === []): ?>
                <div class="empty-state">Sem dados de pagamento no período.</div>
            <?php else: ?>
                <table class="dash-table">
                    <thead><tr><th>Status</th><th>Pedidos</th><th>Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($paymentSummary as $row): ?>
                            <?php $status = strtolower(trim((string) ($row['payment_status'] ?? 'pending'))); ?>
                            <tr data-dash-payment-summary-row>
                                <td><span class="badge <?= htmlspecialchars(status_badge_class('order_payment_status', $status)) ?>"><?= htmlspecialchars(status_label('order_payment_status', $status)) ?></span></td>
                                <td><?= (int) ($row['total_orders'] ?? 0) ?></td>
                                <td><?= htmlspecialchars($formatMoney((float) ($row['total_amount'] ?? 0))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <section class="dash-págination" id="dashPaymentSummaryPagination" hidden>
                    <span class="dash-págination-info" id="dashPaymentSummaryPaginationInfo"></span>
                    <div class="dash-págination-controls" id="dashPaymentSummaryPaginationControls"></div>
                </section>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 style="margin-top:0">Top produtos</h3>
            <?php if ($topProducts === []): ?>
                <div class="empty-state">Sem ranking de produtos.</div>
            <?php else: ?>
                <table class="dash-table">
                    <thead><tr><th>Produto</th><th>Qtd.</th><th>Valor</th></tr></thead>
                    <tbody>
                        <?php foreach ($topProducts as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars((string) ($row['product_name'] ?? '-')) ?></strong><br><span class="muted"><?= htmlspecialchars((string) ($row['category_name'] ?? 'Sem categoria')) ?></span></td>
                                <td><?= (int) ($row['total_quantidade_vendida'] ?? 0) ?></td>
                                <td><?= htmlspecialchars($formatMoney((float) ($row['valor_total_vendido'] ?? 0))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
(() => {
    const pageSize = 10;

    const setupPagination = (rowsSelector, páginationId, infoId, controlsId) => {
        const rows = Array.from(document.querySelectorAll(rowsSelector));
        const págination = document.getElementById(páginationId);
        const info = document.getElementById(infoId);
        const controls = document.getElementById(controlsId);

        if (!(págination instanceof HTMLElement) || !(info instanceof HTMLElement) || !(controls instanceof HTMLElement) || rows.length === 0) {
            if (págination instanceof HTMLElement) {
                págination.hidden = true;
            }
            return;
        }

        let currentPage = 1;

        const addButton = (label, page, disabled, active = false) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `dash-page-btn${active ? ' is-active' : ''}`;
            button.textContent = label;
            button.disabled = disabled;
            button.addEventListener('click', () => {
                if (!disabled && page !== currentPage) {
                    currentPage = page;
                    render();
                }
            });
            controls.appendChild(button);
        };

        const render = () => {
            const total = rows.length;
            const totalPages = Math.max(1, Math.ceil(total / pageSize));
            if (currentPage > totalPages) {
                currentPage = 1;
            }

            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = Math.min(startIndex + pageSize, total);
            rows.forEach((row, index) => {
                row.style.display = index >= startIndex && index < endIndex ? '' : 'none';
            });

            info.textContent = `Mostrando ${startIndex + 1}-${endIndex} de ${total} registro(s).`;
            controls.innerHTML = '';

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
            págination.hidden = false;
        };

        render();
    };

    setupPagination('[data-dash-sales-day-row]', 'dashSalesDayPagination', 'dashSalesDayPaginationInfo', 'dashSalesDayPaginationControls');
    setupPagination('[data-dash-sales-channel-row]', 'dashSalesChannelPagination', 'dashSalesChannelPaginationInfo', 'dashSalesChannelPaginationControls');
    setupPagination('[data-dash-payment-summary-row]', 'dashPaymentSummaryPagination', 'dashPaymentSummaryPaginationInfo', 'dashPaymentSummaryPaginationControls');
})();
</script>
