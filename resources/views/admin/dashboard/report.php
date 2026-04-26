<?php
$report = is_array($report ?? null) ? $report : [];
$company = is_array($report['company'] ?? null) ? $report['company'] : [];
$filters = is_array($report['filters'] ?? null) ? $report['filters'] : [];
$kpis = is_array($report['kpis'] ?? null) ? $report['kpis'] : [];
$ordersByStatus = is_array($report['orders_by_status'] ?? null) ? $report['orders_by_status'] : [];
$salesByChannel = is_array($report['sales_by_channel'] ?? null) ? $report['sales_by_channel'] : [];
$paymentSummary = is_array($report['payment_summary'] ?? null) ? $report['payment_summary'] : [];
$dailySeries = is_array($report['daily_series'] ?? null) ? $report['daily_series'] : [];
$cashKpis = is_array($report['cash_kpis'] ?? null) ? $report['cash_kpis'] : [];
$topProducts = is_array($report['top_products'] ?? null) ? $report['top_products'] : [];
$orders = is_array($report['orders'] ?? null) ? $report['orders'] : [];

$periodPreset = trim((string) ($filters['period_preset'] ?? 'custom'));
$periodPresetLabels = [
    'custom' => 'Personalizado',
    'today' => 'Hoje',
    'yesterday' => 'Ontem',
    'last7' => 'Últimos 7 dias',
    'last30' => 'Últimos 30 dias',
    'month_current' => 'Mes atual',
    'month_previous' => 'Mes anterior',
];

$statusFilter = trim((string) ($filters['status'] ?? ''));
$channelFilter = trim((string) ($filters['channel'] ?? ''));
$paymentStatusFilter = trim((string) ($filters['payment_status'] ?? ''));
$searchFilter = trim((string) ($filters['search'] ?? ''));
$minAmountFilter = trim((string) ($filters['min_amount'] ?? ''));
$maxAmountFilter = trim((string) ($filters['max_amount'] ?? ''));

$startDate = trim((string) ($filters['start_date'] ?? '-'));
$endDate = trim((string) ($filters['end_date'] ?? '-'));

$companyName = trim((string) ($company['name'] ?? 'Estabelecimento'));
$companyTitle = trim((string) ($company['title'] ?? $companyName));
$companyDescription = trim((string) ($company['description'] ?? ''));
$logoPath = trim((string) ($company['logo_path'] ?? ''));
$logoUrl = $logoPath !== '' ? company_image_url($logoPath) : '';

$generatedAt = trim((string) ($report['generated_at'] ?? date('Y-m-d H:i:s')));
$generatedBy = trim((string) ($report['generated_by'] ?? '-'));
$reportId = 'REL-' . date('Ymd-His');

$formatMoney = static fn (mixed $value): string => 'R$ ' . number_format((float) $value, 2, ',', '.');
$formatInt = static fn (mixed $value): string => number_format((int) $value, 0, ',', '.');

$statusLabel = $statusFilter !== '' ? status_label('order_status', $statusFilter) : 'Todos';
$channelLabel = $channelFilter !== '' ? status_label('order_channel', $channelFilter) : 'Todos';
$paymentLabel = $paymentStatusFilter !== '' ? status_label('order_payment_status', $paymentStatusFilter) : 'Todos';
?>

<style>
    .report-page{display:grid;gap:14px}
    .report-actions{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
    .report-actions .btn{min-width:170px;text-align:center}
    .report-paper{background:#fff;border:1px solid #dbeafe;border-radius:14px;box-shadow:0 12px 28px rgba(15,23,42,.12);overflow:hidden}
    .report-header{background:linear-gradient(120deg,var(--theme-main-card,#0f172a) 0%,#1e293b 55%,#334155 100%);color:#fff;padding:18px 20px;display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
    .report-header h1{margin:0;font-size:24px}
    .report-header p{margin:8px 0 0;color:#cbd5e1;max-width:720px}
    .report-id{font-size:12px;background:rgba(255,255,255,.12);padding:8px 10px;border-radius:10px;border:1px solid rgba(255,255,255,.25)}
    .report-body{padding:18px 20px;display:grid;gap:16px}
    .report-cover{display:grid;grid-template-columns:90px 1fr;gap:14px;align-items:center;padding:12px;border:1px solid #dbeafe;border-radius:12px;background:#f8fbff}
    .report-cover img{width:90px;height:90px;border-radius:12px;object-fit:cover;border:1px solid #cbd5e1}
    .report-cover h2{margin:0}
    .report-cover p{margin:6px 0 0;color:#475569}
    .report-index{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .report-index-card{border:1px solid #c7d2fe;border-radius:14px;padding:12px;background:linear-gradient(125deg,#eef2ff 0%,#f8fafc 100%)}
    .report-index-card h3{margin:0 0 10px;color:#1e1b4b}
    .report-index-list{list-style:none;margin:0;padding:0;display:grid;gap:8px}
    .report-index-item{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:8px;align-items:center;padding:8px 10px;background:#fff;border:1px solid #c7d2fe;border-radius:10px}
    .report-index-order{display:inline-grid;place-items:center;width:28px;height:28px;border-radius:999px;background:#1d4ed8;color:#fff;font-size:11px;font-weight:700;letter-spacing:.04em}
    .report-index-link{color:#1f2937;text-decoration:none;font-size:13px;font-weight:600}
    .report-index-link:hover{text-decoration:underline}
    .report-index-tag{padding:4px 8px;border-radius:999px;background:#e0e7ff;color:#312e81;font-size:11px;font-weight:600;white-space:nowrap}
    .report-params{display:flex;flex-wrap:wrap;gap:8px}
    .report-chip{padding:7px 10px;border-radius:999px;border:1px solid #bfdbfe;background:#eff6ff;font-size:12px;color:#1e3a8a}
    .report-kpis{display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:10px}
    .report-kpi{border:1px solid #e2e8f0;border-radius:10px;padding:12px;background:#fff}
    .report-kpi strong{display:block;font-size:20px;color:#0f172a}
    .report-kpi span{display:block;margin-top:4px;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.05em}
    .report-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .report-card{border:1px solid #e2e8f0;border-radius:12px;padding:12px;background:#fff}
    .report-card h3{margin:0 0 10px}
    .report-table{width:100%;border-collapse:collapse}
    .report-table th,.report-table td{padding:8px;border-bottom:1px solid #e2e8f0;font-size:12px;text-align:left;vertical-align:top}
    .report-table th{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#64748b}
    .report-series{display:grid;gap:8px}
    .report-series-row{display:grid;grid-template-columns:70px 1fr 90px;gap:8px;align-items:center}
    .report-series-track{height:9px;background:#e2e8f0;border-radius:999px;overflow:hidden}
    .report-series-fill{height:100%;background:linear-gradient(90deg,#2563eb,#3b82f6);border-radius:999px}
    .report-section{page-break-inside:avoid}
    .report-footer{padding:10px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;font-size:12px;color:#475569;display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap}
    .report-footer .page-counter::after{content:"Previa de impressao"}
    .report-muted{color:#64748b}
    .no-print{display:block}

    @media (max-width:980px){
        .report-index,.report-grid{grid-template-columns:1fr}
        .report-kpis{grid-template-columns:1fr 1fr}
    }
    @media (max-width:680px){
        .report-cover{grid-template-columns:1fr}
        .report-cover img{width:72px;height:72px}
        .report-kpis{grid-template-columns:1fr}
        .report-series-row{grid-template-columns:58px 1fr 78px}
    }

    @page {
        size: A4 portrait;
        margin: 14mm 10mm 16mm 10mm;
        @bottom-right {
            content: "Pagina " counter(page);
            font-size: 9pt;
            color: #475569;
        }
    }
    @media print {
        body{background:#fff}
        .shell{display:block !important}
        .shell > aside{display:none !important}
        .shell-main{display:block !important}
        .shell-header,.shell-footer{display:none !important}
        main{padding:0 !important}
        .no-print{display:none !important}
        .report-paper{border:none;box-shadow:none;border-radius:0}
        .report-body{padding:8mm 7mm 10mm}
        .report-section{break-inside:avoid-page}
        .report-index-card{background:#fff}
        .report-index-item{background:#fff}
        .report-index-tag{background:#eef2ff}
        .report-footer{position:static;background:#fff;border-top:1px solid #d1d5db;padding:4mm 0 0;margin-top:6mm}
        .report-footer .page-counter{display:none}
    }
</style>

<div class="report-page">
    <div class="card no-print report-actions">
        <div>
            <h2 style="margin:0">Prévia do Relatório</h2>
            <p class="report-muted" style="margin:6px 0 0">A mesma página serve para pré-visualizar e imprimir.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/dashboard?section=overview')) ?>">Voltar ao painel</a>
            <button class="btn" type="button" onclick="window.print()">Imprimir relatório</button>
        </div>
    </div>

    <article class="report-paper">
        <header class="report-header">
            <div>
                <h1>Relatório Estratégico</h1>
                <p><?= htmlspecialchars($companyTitle !== '' ? $companyTitle : $companyName) ?> • Painel operacional e financeiro do estabelecimento.</p>
            </div>
            <div class="report-id">
                <strong><?= htmlspecialchars($reportId) ?></strong><br>
                Gerado em <?= htmlspecialchars($generatedAt) ?><br>
                Por <?= htmlspecialchars($generatedBy !== '' ? $generatedBy : '-') ?>
            </div>
        </header>

        <div class="report-body">
            <section class="report-cover report-section" id="sec-capa">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo da empresa">
                <?php else: ?>
                    <div style="width:90px;height:90px;border-radius:12px;background:#e2e8f0;border:1px solid #cbd5e1"></div>
                <?php endif; ?>
                <div>
                    <h2 style="margin:0"><?= htmlspecialchars($companyName) ?></h2>
                    <p><?= htmlspecialchars($companyDescription !== '' ? $companyDescription : 'Documento executivo com indicadores, filtros aplicados e base analítica para tomada de decisão.') ?></p>
                </div>
            </section>

                        <section class="report-index report-section" id="sec-indice">
                <div class="report-index-card">
                    <h3>Indice do relatorio</h3>
                    <ol class="report-index-list">
                        <li class="report-index-item">
                            <span class="report-index-order">01</span>
                            <a class="report-index-link" href="#sec-capa">Capa e identificacao do documento</a>
                            <span class="report-index-tag">Resumo</span>
                        </li>
                        <li class="report-index-item">
                            <span class="report-index-order">02</span>
                            <a class="report-index-link" href="#sec-filtros">Parametros de filtros aplicados</a>
                            <span class="report-index-tag">Filtros</span>
                        </li>
                        <li class="report-index-item">
                            <span class="report-index-order">03</span>
                            <a class="report-index-link" href="#sec-kpis">Indices executivos principais</a>
                            <span class="report-index-tag">KPIs</span>
                        </li>
                        <li class="report-index-item">
                            <span class="report-index-order">04</span>
                            <a class="report-index-link" href="#sec-analitico">Analises por status e canal</a>
                            <span class="report-index-tag">Analitico</span>
                        </li>
                    </ol>
                </div>
                <div class="report-index-card">
                    <h3>Indice operacional</h3>
                    <ol class="report-index-list">
                        <li class="report-index-item">
                            <span class="report-index-order">05</span>
                            <a class="report-index-link" href="#sec-diario">Serie diaria de vendas</a>
                            <span class="report-index-tag">Evolucao</span>
                        </li>
                        <li class="report-index-item">
                            <span class="report-index-order">06</span>
                            <a class="report-index-link" href="#sec-produtos">Ranking de top produtos</a>
                            <span class="report-index-tag">Produtos</span>
                        </li>
                        <li class="report-index-item">
                            <span class="report-index-order">07</span>
                            <a class="report-index-link" href="#sec-detalhado">Pedidos detalhados</a>
                            <span class="report-index-tag">Operação</span>
                        </li>
                        <li class="report-index-item">
                            <span class="report-index-order">08</span>
                            <a class="report-index-link" href="#sec-rodape">Rodapé técnico</a>
                            <span class="report-index-tag">Controle</span>
                        </li>
                    </ol>
                </div>
            </section>

            <section class="report-section" id="sec-filtros">
                <div class="report-card">
                    <h3>Parâmetros de filtros aplicados</h3>
                    <div class="report-params">
                        <span class="report-chip">Período rápido: <?= htmlspecialchars($periodPresetLabels[$periodPreset] ?? 'Personalizado') ?></span>
                        <span class="report-chip">De: <?= htmlspecialchars($startDate) ?></span>
                        <span class="report-chip">Até: <?= htmlspecialchars($endDate) ?></span>
                        <span class="report-chip">Status pedido: <?= htmlspecialchars($statusLabel) ?></span>
                        <span class="report-chip">Canal: <?= htmlspecialchars($channelLabel) ?></span>
                        <span class="report-chip">Pagamento: <?= htmlspecialchars($paymentLabel) ?></span>
                        <span class="report-chip">Valor mínimo: <?= htmlspecialchars($minAmountFilter !== '' ? $formatMoney((float) $minAmountFilter) : 'Não definido') ?></span>
                        <span class="report-chip">Valor máximo: <?= htmlspecialchars($maxAmountFilter !== '' ? $formatMoney((float) $maxAmountFilter) : 'Não definido') ?></span>
                        <span class="report-chip">Busca: <?= htmlspecialchars($searchFilter !== '' ? $searchFilter : 'Não aplicada') ?></span>
                    </div>
                </div>
            </section>

            <section class="report-section" id="sec-kpis">
                <div class="report-kpis">
                    <article class="report-kpi"><strong><?= htmlspecialchars($formatMoney((float) ($kpis['total_sales'] ?? 0))) ?></strong><span>Vendas</span></article>
                    <article class="report-kpi"><strong><?= htmlspecialchars($formatMoney((float) ($kpis['avg_ticket'] ?? 0))) ?></strong><span>Ticket médio</span></article>
                    <article class="report-kpi"><strong><?= htmlspecialchars($formatInt((int) ($kpis['total_orders'] ?? 0))) ?></strong><span>Total pedidos</span></article>
                    <article class="report-kpi"><strong><?= htmlspecialchars($formatInt((int) ($kpis['canceled_orders'] ?? 0))) ?></strong><span>Cancelados</span></article>
                    <article class="report-kpi"><strong><?= htmlspecialchars($formatMoney((float) ($cashKpis['total_closed_amount'] ?? 0))) ?></strong><span>Fechamento caixa</span></article>
                    <article class="report-kpi"><strong><?= htmlspecialchars($formatMoney((float) ($cashKpis['total_difference'] ?? 0))) ?></strong><span>Divergência caixa</span></article>
                    <article class="report-kpi"><strong><?= htmlspecialchars($formatInt((int) ($cashKpis['total_closings'] ?? 0))) ?></strong><span>Fechamentos</span></article>
                    <article class="report-kpi"><strong><?= htmlspecialchars($formatInt(count($orders))) ?></strong><span>Pedidos listados</span></article>
                </div>
            </section>

            <section class="report-grid report-section" id="sec-analitico">
                <div class="report-card">
                    <h3>Pedidos por status</h3>
                    <table class="report-table">
                        <thead><tr><th>Status</th><th>Pedidos</th><th>Total</th></tr></thead>
                        <tbody>
                            <?php if ($ordersByStatus === []): ?>
                                <tr><td colspan="3">Sem dados no período.</td></tr>
                            <?php else: ?>
                                <?php foreach ($ordersByStatus as $row): ?>
                                    <?php $rawStatus = strtolower(trim((string) ($row['status'] ?? 'pending'))); ?>
                                    <tr>
                                        <td><?= htmlspecialchars(status_label('order_status', $rawStatus)) ?></td>
                                        <td><?= (int) ($row['total_orders'] ?? 0) ?></td>
                                        <td><?= htmlspecialchars($formatMoney((float) ($row['total_amount'] ?? 0))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="report-card">
                    <h3>Vendas por canal</h3>
                    <table class="report-table">
                        <thead><tr><th>Canal</th><th>Pedidos</th><th>Total</th></tr></thead>
                        <tbody>
                            <?php if ($salesByChannel === []): ?>
                                <tr><td colspan="3">Sem dados no período.</td></tr>
                            <?php else: ?>
                                <?php foreach ($salesByChannel as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(status_label('order_channel', (string) ($row['channel'] ?? ''))) ?></td>
                                        <td><?= (int) ($row['total_orders'] ?? 0) ?></td>
                                        <td><?= htmlspecialchars($formatMoney((float) ($row['total_sales'] ?? 0))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="report-grid report-section">
                <div class="report-card">
                    <h3>Pagamentos por situação</h3>
                    <table class="report-table">
                        <thead><tr><th>Status pagamento</th><th>Pedidos</th><th>Total</th></tr></thead>
                        <tbody>
                            <?php if ($paymentSummary === []): ?>
                                <tr><td colspan="3">Sem dados de pagamento.</td></tr>
                            <?php else: ?>
                                <?php foreach ($paymentSummary as $row): ?>
                                    <?php $paymentStatus = strtolower(trim((string) ($row['payment_status'] ?? 'pending'))); ?>
                                    <tr>
                                        <td><?= htmlspecialchars(status_label('order_payment_status', $paymentStatus)) ?></td>
                                        <td><?= (int) ($row['total_orders'] ?? 0) ?></td>
                                        <td><?= htmlspecialchars($formatMoney((float) ($row['total_amount'] ?? 0))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="report-card" id="sec-diario">
                    <h3>Série diária de vendas</h3>
                    <?php
                    $maxDayValue = 0.0;
                    foreach ($dailySeries as $day) {
                        $candidate = (float) ($day['total_sales'] ?? 0);
                        if ($candidate > $maxDayValue) {
                            $maxDayValue = $candidate;
                        }
                    }
                    ?>
                    <div class="report-series">
                        <?php if ($dailySeries === []): ?>
                            <div class="report-muted">Sem série diária no período.</div>
                        <?php else: ?>
                            <?php foreach ($dailySeries as $day): ?>
                                <?php
                                $rawDate = (string) ($day['report_date'] ?? '');
                                $labelDate = $rawDate !== '' ? date('d/m', strtotime($rawDate . ' 00:00:00')) : '--/--';
                                $dayValue = (float) ($day['total_sales'] ?? 0);
                                $barPercent = $maxDayValue > 0 ? max(3, ($dayValue / $maxDayValue) * 100) : 3;
                                ?>
                                <div class="report-series-row">
                                    <span><?= htmlspecialchars($labelDate) ?></span>
                                    <div class="report-series-track"><span class="report-series-fill" style="width:<?= number_format($barPercent, 2, '.', '') ?>%"></span></div>
                                    <strong style="font-size:11px;text-align:right"><?= htmlspecialchars($formatMoney($dayValue)) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="report-section" id="sec-produtos">
                <div class="report-card">
                    <h3>Top produtos no período</h3>
                    <table class="report-table">
                        <thead><tr><th>Produto</th><th>Categoria</th><th>Quantidade</th><th>Valor</th></tr></thead>
                        <tbody>
                            <?php if ($topProducts === []): ?>
                                <tr><td colspan="4">Sem produtos para ranking no período.</td></tr>
                            <?php else: ?>
                                <?php foreach ($topProducts as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) ($row['product_name'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars((string) ($row['category_name'] ?? 'Sem categoria')) ?></td>
                                        <td><?= (int) ($row['total_quantidade_vendida'] ?? 0) ?></td>
                                        <td><?= htmlspecialchars($formatMoney((float) ($row['valor_total_vendido'] ?? 0))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="report-section" id="sec-detalhado">
                <div class="report-card">
                    <h3>Pedidos detalhados (amostra analítica)</h3>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th>Data/Hora</th>
                                <th>Canal</th>
                                <th>Status</th>
                                <th>Pagamento</th>
                                <th>Cliente</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($orders === []): ?>
                                <tr><td colspan="7">Nenhum pedido encontrado com os filtros aplicados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <?php
                                    $orderStatus = strtolower(trim((string) ($order['status'] ?? 'pending')));
                                    $orderPayment = strtolower(trim((string) ($order['payment_status'] ?? 'pending')));
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars((string) ($order['order_number'] ?? '-')) ?></strong></td>
                                        <td><?= htmlspecialchars((string) ($order['created_at'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars(status_label('order_channel', (string) ($order['channel'] ?? ''))) ?></td>
                                        <td><?= htmlspecialchars(status_label('order_status', $orderStatus)) ?></td>
                                        <td><?= htmlspecialchars(status_label('order_payment_status', $orderPayment)) ?></td>
                                        <td><?= htmlspecialchars((string) ($order['customer_name'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars($formatMoney((float) ($order['total_amount'] ?? 0))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <footer class="report-footer report-section" id="sec-rodape">
            <span>Relatorio gerado automaticamente pelo MesiMenu - <?= htmlspecialchars($companyName) ?></span>
            <span class="page-counter"></span>
        </footer>
    </article>
</div>

<script>
(() => {
    const autoRefreshMs = 30000;
    window.setInterval(() => {
        if (document.hidden) {
            return;
        }
        const active = document.activeElement;
        if (active instanceof HTMLElement) {
            const tag = active.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
                return;
            }
        }
        window.location.reload();
    }, autoRefreshMs);
})();
</script>
