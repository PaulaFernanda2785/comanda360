<?php
$dashboardPanel = is_array($dashboardPanel ?? null) ? $dashboardPanel : [];
$overview = is_array($dashboardPanel['overview'] ?? null) ? $dashboardPanel['overview'] : [];
$companiesPanel = is_array($dashboardPanel['companies'] ?? null) ? $dashboardPanel['companies'] : [];
$plansPanel = is_array($dashboardPanel['plans'] ?? null) ? $dashboardPanel['plans'] : [];
$subscriptionsPanel = is_array($dashboardPanel['subscriptions'] ?? null) ? $dashboardPanel['subscriptions'] : [];
$paymentsPanel = is_array($dashboardPanel['subscription_payments'] ?? null) ? $dashboardPanel['subscription_payments'] : [];
$supportPanel = is_array($dashboardPanel['support'] ?? null) ? $dashboardPanel['support'] : [];

$companySummary = is_array($companiesPanel['summary'] ?? null) ? $companiesPanel['summary'] : [];
$planSummary = is_array($plansPanel['summary'] ?? null) ? $plansPanel['summary'] : [];
$subscriptionSummary = is_array($subscriptionsPanel['summary'] ?? null) ? $subscriptionsPanel['summary'] : [];
$paymentSummary = is_array($paymentsPanel['summary'] ?? null) ? $paymentsPanel['summary'] : [];
$supportSummary = is_array($supportPanel['summary'] ?? null) ? $supportPanel['summary'] : [];

$companies = is_array($companiesPanel['items'] ?? null) ? $companiesPanel['items'] : [];
$plans = is_array($plansPanel['items'] ?? null) ? $plansPanel['items'] : [];
$subscriptions = is_array($subscriptionsPanel['items'] ?? null) ? $subscriptionsPanel['items'] : [];
$payments = is_array($paymentsPanel['items'] ?? null) ? $paymentsPanel['items'] : [];
$tickets = is_array($supportPanel['items'] ?? null) ? $supportPanel['items'] : [];

$paymentFilters = is_array($paymentsPanel['filters'] ?? null) ? $paymentsPanel['filters'] : [];
$paymentPagination = is_array($paymentsPanel['pagination'] ?? null) ? $paymentsPanel['pagination'] : [];

$dashboardPaymentSearch = trim((string) ($paymentFilters['search'] ?? ''));
$dashboardPaymentStatus = trim((string) ($paymentFilters['status'] ?? ''));
$dashboardPaymentPage = max(1, (int) ($paymentPagination['page'] ?? 1));
$dashboardPaymentLastPage = max(1, (int) ($paymentPagination['last_page'] ?? 1));
$dashboardPaymentFrom = (int) ($paymentPagination['from'] ?? 0);
$dashboardPaymentTo = (int) ($paymentPagination['to'] ?? 0);
$dashboardPaymentTotal = (int) ($paymentPagination['total'] ?? ($paymentSummary['total_charges'] ?? count($payments)));
$dashboardPaymentPages = is_array($paymentPagination['pages'] ?? null) ? $paymentPagination['pages'] : [];

$paymentStatusOptions = [
    '' => 'Todos os status',
    'pendente' => 'Pendentes',
    'vencido' => 'Vencidas',
    'pago' => 'Pagas',
    'cancelado' => 'Canceladas',
];

$formatMoney = static fn (mixed $value): string => 'R$ ' . number_format((float) $value, 2, ',', '.');

$formatDate = static function (mixed $value, bool $withTime = true): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '-';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return $raw;
    }

    return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $timestamp);
};

$supportStatusLabel = static function (mixed $value): string {
    return match (strtolower(trim((string) ($value ?? '')))) {
        'open' => 'Aberto',
        'in_progress' => 'Em andamento',
        'resolved' => 'Resolvido',
        'closed' => 'Fechado',
        default => 'Sem status',
    };
};

$supportPriorityLabel = static function (mixed $value): string {
    return match (strtolower(trim((string) ($value ?? '')))) {
        'urgent' => 'Urgente',
        'high' => 'Alta',
        'medium' => 'Media',
        'low' => 'Baixa',
        default => 'Sem prioridade',
    };
};

$gatewayStatusLabel = static function (mixed $value): string {
    $raw = strtolower(trim((string) ($value ?? '')));

    return match ($raw) {
        'authorized', 'active' => 'Ativa no gateway',
        'pending', 'in_process' => 'Pendente no gateway',
        'paused' => 'Pausada no gateway',
        'cancelled', 'canceled', 'cancelled_by_payer' => 'Cancelada no gateway',
        '' => 'Sem retorno do gateway',
        default => ucfirst(str_replace(['_', '-'], ' ', $raw)),
    };
};

$chartPercent = static function (mixed $value, mixed $total): float {
    $numericTotal = (float) $total;
    if ($numericTotal <= 0) {
        return 0.0;
    }

    return round((((float) $value) / $numericTotal) * 100, 1);
};

$currentQuery = is_array($_GET ?? null) ? $_GET : [];
$buildDashboardPaymentsUrl = static function (array $overrides = []) use ($currentQuery): string {
    $params = array_merge($currentQuery, $overrides);

    foreach ($params as $key => $value) {
        if (in_array($key, ['dashboard_payment_search', 'dashboard_payment_status', 'dashboard_payment_page'], true)
            && trim((string) $value) === '') {
            unset($params[$key]);
        }
    }

    $query = http_build_query($params);

    return base_url('/saas/dashboard' . ($query !== '' ? '?' . $query : ''));
};

$companyChartItems = [
    ['label' => 'Ativas', 'value' => (int) ($companySummary['active_companies'] ?? 0), 'tone' => 'success'],
    ['label' => 'Em teste', 'value' => (int) ($companySummary['testing_companies'] ?? 0), 'tone' => 'info'],
    ['label' => 'Suspensas', 'value' => (int) ($companySummary['suspended_companies'] ?? 0), 'tone' => 'warning'],
    ['label' => 'Canceladas', 'value' => (int) ($companySummary['canceled_companies'] ?? 0), 'tone' => 'danger'],
];
$companyChartTotal = max(1, (int) ($companySummary['total_companies'] ?? 0));

$subscriptionChartItems = [
    ['label' => 'Ativas', 'value' => (int) ($subscriptionSummary['active_subscriptions'] ?? 0), 'tone' => 'success'],
    ['label' => 'Trial', 'value' => (int) ($subscriptionSummary['trial_subscriptions'] ?? 0), 'tone' => 'info'],
    ['label' => 'Expiradas', 'value' => (int) ($subscriptionSummary['expired_subscriptions'] ?? 0), 'tone' => 'warning'],
    ['label' => 'Auto cobranca', 'value' => (int) ($subscriptionSummary['auto_charge_enabled'] ?? 0), 'tone' => 'primary'],
];
$subscriptionChartTotal = max(
    1,
    (int) ($subscriptionSummary['active_subscriptions'] ?? 0)
    + (int) ($subscriptionSummary['trial_subscriptions'] ?? 0)
    + (int) ($subscriptionSummary['expired_subscriptions'] ?? 0)
);

$financialChartItems = [
    ['label' => 'Pendentes', 'value' => (int) ($paymentSummary['pending_charges'] ?? 0), 'tone' => 'warning'],
    ['label' => 'Vencidas', 'value' => (int) ($paymentSummary['overdue_charges'] ?? 0), 'tone' => 'danger'],
    ['label' => 'Pagas', 'value' => (int) ($paymentSummary['paid_charges'] ?? 0), 'tone' => 'success'],
];
$financialChartTotal = max(1, (int) ($paymentSummary['total_charges'] ?? $dashboardPaymentTotal));

$supportChartItems = [
    ['label' => 'Abertos', 'value' => (int) ($supportSummary['open_count'] ?? 0), 'tone' => 'warning'],
    ['label' => 'Em andamento', 'value' => (int) ($supportSummary['in_progress_count'] ?? 0), 'tone' => 'primary'],
    ['label' => 'Urgentes', 'value' => (int) ($supportSummary['urgent_count'] ?? 0), 'tone' => 'danger'],
    ['label' => 'Resolvidos', 'value' => (int) ($supportSummary['resolved_count'] ?? 0), 'tone' => 'success'],
];
$supportChartTotal = max(
    1,
    (int) ($supportSummary['open_count'] ?? 0)
    + (int) ($supportSummary['in_progress_count'] ?? 0)
    + (int) ($supportSummary['resolved_count'] ?? 0)
    + (int) ($supportSummary['closed_count'] ?? 0)
);
?>

<style>
    .saas-command-page{display:grid;gap:16px}
    .saas-command-hero{border:1px solid #bfdbfe;background:linear-gradient(120deg,#0f172a 0%,#1d4ed8 42%,#0f766e 100%);color:#fff;border-radius:18px;padding:20px;position:relative;overflow:hidden}
    .saas-command-hero:before{content:"";position:absolute;top:-54px;right:-34px;width:220px;height:220px;border-radius:999px;background:rgba(255,255,255,.11)}
    .saas-command-hero:after{content:"";position:absolute;bottom:-82px;left:-38px;width:190px;height:190px;border-radius:999px;background:rgba(255,255,255,.08)}
    .saas-command-hero-body{position:relative;z-index:1;display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap}
    .saas-command-hero h1{margin:0 0 8px;font-size:30px}
    .saas-command-hero p{margin:0;max-width:920px;color:#dbeafe;line-height:1.55}
    .saas-command-pills{display:flex;gap:8px;flex-wrap:wrap}
    .saas-command-pill{border:1px solid rgba(255,255,255,.24);background:rgba(15,23,42,.35);padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700}

    .saas-command-layout{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(320px,.95fr);gap:16px;align-items:start}
    .saas-command-main,.saas-command-side{display:grid;gap:16px}
    .saas-command-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
    .saas-command-head h2,.saas-command-head h3{margin:0}
    .saas-command-note{margin:4px 0 0;color:#64748b;font-size:13px;line-height:1.45}
    .saas-command-badges{display:flex;gap:8px;flex-wrap:wrap}

    .saas-command-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
    .saas-command-kpi{border:1px solid #dbeafe;border-radius:14px;background:linear-gradient(180deg,#fff,#eff6ff);padding:14px}
    .saas-command-kpi span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
    .saas-command-kpi strong{display:block;margin-top:6px;font-size:24px;color:#0f172a}
    .saas-command-kpi small{display:block;margin-top:4px;color:#475569}

    .saas-command-chart-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
    .saas-command-chart-card{border:1px solid #dbeafe;border-radius:16px;background:linear-gradient(180deg,#fff,#f8fafc);padding:16px;display:grid;gap:14px}
    .saas-command-chart-card h3{margin:0;color:#0f172a;font-size:17px}
    .saas-command-chart-card p{margin:0;color:#64748b;font-size:13px;line-height:1.45}
    .saas-command-chart-stack{display:grid;gap:10px}
    .saas-command-chart-row{display:grid;gap:6px}
    .saas-command-chart-meta{display:flex;justify-content:space-between;align-items:center;gap:10px;font-size:13px;color:#0f172a}
    .saas-command-chart-meta span:last-child{color:#475569;font-weight:700}
    .saas-command-bar{height:10px;border-radius:999px;background:#e2e8f0;overflow:hidden}
    .saas-command-bar-fill{display:block;height:100%;border-radius:999px}
    .saas-command-bar-fill.tone-success{background:linear-gradient(90deg,#16a34a,#4ade80)}
    .saas-command-bar-fill.tone-info{background:linear-gradient(90deg,#0ea5e9,#38bdf8)}
    .saas-command-bar-fill.tone-warning{background:linear-gradient(90deg,#f59e0b,#facc15)}
    .saas-command-bar-fill.tone-danger{background:linear-gradient(90deg,#dc2626,#fb7185)}
    .saas-command-bar-fill.tone-primary{background:linear-gradient(90deg,#1d4ed8,#60a5fa)}

    .saas-command-alert-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .saas-command-alert{display:grid;gap:10px;border-radius:14px;padding:14px;border:1px solid #e2e8f0;background:linear-gradient(180deg,#fff,#f8fafc)}
    .saas-command-alert.is-danger{border-color:#fecaca;background:linear-gradient(180deg,#fff,#fef2f2)}
    .saas-command-alert.is-warning{border-color:#fde68a;background:linear-gradient(180deg,#fff,#fffbeb)}
    .saas-command-alert.is-info{border-color:#bfdbfe;background:linear-gradient(180deg,#fff,#eff6ff)}
    .saas-command-alert-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
    .saas-command-alert strong{font-size:18px;color:#0f172a}
    .saas-command-alert span{font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.04em}
    .saas-command-alert p{margin:0;color:#475569;font-size:13px;line-height:1.45}

    .saas-command-card-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
    .saas-command-list{display:grid;gap:10px}
    .saas-command-item{border:1px solid #dbeafe;border-radius:14px;background:linear-gradient(180deg,#fff,#f8fafc);padding:12px;display:grid;gap:8px}
    .saas-command-item-top{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
    .saas-command-item-title{display:grid;gap:4px}
    .saas-command-item-title strong{font-size:15px;color:#0f172a}
    .saas-command-item-title small{font-size:12px;color:#64748b;line-height:1.35}
    .saas-command-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    .saas-command-meta-box{border:1px solid #e2e8f0;background:#fff;border-radius:10px;padding:9px}
    .saas-command-meta-box span{display:block;font-size:11px;text-transform:uppercase;color:#64748b}
    .saas-command-meta-box strong{display:block;margin-top:4px;font-size:13px;color:#0f172a}
    .saas-command-actions{display:flex;gap:8px;flex-wrap:wrap}
    .saas-command-empty{padding:14px;border:1px dashed #cbd5e1;border-radius:14px;background:#f8fafc;color:#64748b;font-size:13px;line-height:1.5}

    .saas-command-filter-grid{display:grid;grid-template-columns:1.45fr 1fr auto;gap:10px;align-items:end;margin-top:16px}
    .saas-command-filter-grid .field{margin:0}
    .saas-command-filter-actions{display:flex;gap:8px;flex-wrap:wrap}
    .saas-command-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:14px}
    .saas-command-pagination-controls{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .saas-page-btn{display:inline-block;padding:8px 11px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#0f172a;text-decoration:none}
    .saas-page-btn.is-active{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
    .saas-page-ellipsis{color:#64748b;padding:0 2px}

    .saas-command-summary-grid{display:grid;gap:8px}
    .saas-command-summary-item{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px}
    .saas-command-summary-item strong{color:#0f172a}
    .saas-command-summary-item span{padding:4px 8px;border-radius:999px;background:#dbeafe;color:#1e3a8a;font-size:11px;font-weight:700}

    .saas-command-hub{display:grid;gap:10px}
    .saas-command-hub a{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 14px;border-radius:12px;border:1px solid #dbeafe;background:linear-gradient(180deg,#fff,#eff6ff);text-decoration:none;color:#0f172a}
    .saas-command-hub a small{display:block;color:#64748b;margin-top:3px}

    .saas-command-rule{border:1px solid #c7d2fe;background:linear-gradient(130deg,#eef2ff 0%,#f8fafc 100%);border-radius:14px;padding:14px}
    .saas-command-rule h3{margin:0 0 8px;color:#1e1b4b;font-size:16px}
    .saas-command-rule p{margin:0;color:#3730a3;font-size:13px;line-height:1.5}
    .saas-command-rule ul{margin:10px 0 0;padding-left:18px;color:#312e81;font-size:13px;display:grid;gap:6px}

    @media (max-width:1180px){
        .saas-command-layout{grid-template-columns:1fr}
    }
    @media (max-width:980px){
        .saas-command-kpis,.saas-command-alert-grid,.saas-command-card-grid,.saas-command-meta,.saas-command-chart-grid,.saas-command-filter-grid{grid-template-columns:1fr 1fr}
    }
    @media (max-width:760px){
        .saas-command-kpis,.saas-command-alert-grid,.saas-command-card-grid,.saas-command-meta,.saas-command-chart-grid,.saas-command-filter-grid{grid-template-columns:1fr}
        .saas-command-hero h1{font-size:24px}
    }
</style>

<div class="saas-command-page">
    <div class="saas-command-hero">
        <div class="saas-command-hero-body">
            <div>
                <h1>Dashboard SaaS</h1>
                <p>O painel foi organizado para servir como centro de gestao e operacao do administrador. A tela combina leitura executiva, distribuicao da base, pressao financeira e filas acionaveis sem quebrar o padrao visual dos demais modulos.</p>
            </div>
            <div class="saas-command-pills">
                <span class="saas-command-pill">Empresas: <?= htmlspecialchars((string) ($overview['total_companies'] ?? 0)) ?></span>
                <span class="saas-command-pill">Assinaturas ativas: <?= htmlspecialchars((string) ($overview['active_subscriptions'] ?? 0)) ?></span>
                <span class="saas-command-pill">MRR ativo: <?= htmlspecialchars($formatMoney($overview['active_monthly_mrr'] ?? 0)) ?></span>
                <span class="saas-command-pill">Chamados urgentes: <?= htmlspecialchars((string) ($overview['urgent_tickets'] ?? 0)) ?></span>
            </div>
        </div>
    </div>

    <div class="saas-command-layout">
        <div class="saas-command-main">
            <section class="card">
                <div class="saas-command-head">
                    <div>
                        <h2>Visao executiva</h2>
                        <p class="saas-command-note">Os indicadores principais resumem tracao, receita viva, risco financeiro e gargalos operacionais. O objetivo aqui nao e volume visual, e sim direcao para decisao.</p>
                    </div>
                    <div class="saas-command-badges">
                        <span class="badge">Gateway ativo: <?= htmlspecialchars((string) ($overview['gateway_bound_subscriptions'] ?? 0)) ?></span>
                        <span class="badge">Auto cobranca: <?= htmlspecialchars((string) ($overview['auto_charge_enabled'] ?? 0)) ?></span>
                    </div>
                </div>

                <div class="saas-command-kpis" style="margin-top:16px">
                    <div class="saas-command-kpi">
                        <span>Empresas</span>
                        <strong><?= htmlspecialchars((string) ($overview['total_companies'] ?? 0)) ?></strong>
                        <small>Base SaaS cadastrada</small>
                    </div>
                    <div class="saas-command-kpi">
                        <span>Assinaturas ativas</span>
                        <strong><?= htmlspecialchars((string) ($overview['active_subscriptions'] ?? 0)) ?></strong>
                        <small>Receita recorrente em producao</small>
                    </div>
                    <div class="saas-command-kpi">
                        <span>MRR ativo</span>
                        <strong><?= htmlspecialchars($formatMoney($overview['active_monthly_mrr'] ?? 0)) ?></strong>
                        <small>Somente contratos mensais ativos</small>
                    </div>
                    <div class="saas-command-kpi">
                        <span>Cobrancas vencidas</span>
                        <strong><?= htmlspecialchars((string) ($overview['overdue_charges'] ?? 0)) ?></strong>
                        <small>Risco de caixa imediato</small>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="saas-command-head">
                    <div>
                        <h2>Graficos operacionais</h2>
                        <p class="saas-command-note">Os graficos consolidam proporcao e pressao entre base, assinaturas, financeiro e suporte. Isso evita analisar numero isolado como se fosse tendencia.</p>
                    </div>
                    <div class="saas-command-badges">
                        <span class="badge">Leitura comparativa</span>
                        <span class="badge">Sem dependencia externa</span>
                    </div>
                </div>

                <div class="saas-command-chart-grid" style="margin-top:16px">
                    <article class="saas-command-chart-card">
                        <div>
                            <h3>Distribuicao da base</h3>
                            <p>Mostra maturidade e situacao operacional das empresas no SaaS.</p>
                        </div>
                        <div class="saas-command-chart-stack">
                            <?php foreach ($companyChartItems as $item): ?>
                                <div class="saas-command-chart-row">
                                    <div class="saas-command-chart-meta">
                                        <span><?= htmlspecialchars($item['label']) ?></span>
                                        <span><?= htmlspecialchars((string) $item['value']) ?> (<?= number_format($chartPercent($item['value'], $companyChartTotal), 1, ',', '.') ?>%)</span>
                                    </div>
                                    <div class="saas-command-bar">
                                        <span class="saas-command-bar-fill tone-<?= htmlspecialchars($item['tone']) ?>" style="width: <?= htmlspecialchars((string) $chartPercent($item['value'], $companyChartTotal)) ?>%"></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="saas-command-chart-card">
                        <div>
                            <h3>Carteira de assinaturas</h3>
                            <p>Aqui a leitura separa base ativa, trial, expiracao e nivel de automacao.</p>
                        </div>
                        <div class="saas-command-chart-stack">
                            <?php foreach ($subscriptionChartItems as $item): ?>
                                <div class="saas-command-chart-row">
                                    <div class="saas-command-chart-meta">
                                        <span><?= htmlspecialchars($item['label']) ?></span>
                                        <span><?= htmlspecialchars((string) $item['value']) ?> (<?= number_format($chartPercent($item['value'], $subscriptionChartTotal), 1, ',', '.') ?>%)</span>
                                    </div>
                                    <div class="saas-command-bar">
                                        <span class="saas-command-bar-fill tone-<?= htmlspecialchars($item['tone']) ?>" style="width: <?= htmlspecialchars((string) $chartPercent($item['value'], $subscriptionChartTotal)) ?>%"></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="saas-command-chart-card">
                        <div>
                            <h3>Pressao financeira</h3>
                            <p>A fila precisa mostrar claramente o peso de pendencia, atraso e recuperacao.</p>
                        </div>
                        <div class="saas-command-chart-stack">
                            <?php foreach ($financialChartItems as $item): ?>
                                <div class="saas-command-chart-row">
                                    <div class="saas-command-chart-meta">
                                        <span><?= htmlspecialchars($item['label']) ?></span>
                                        <span><?= htmlspecialchars((string) $item['value']) ?> (<?= number_format($chartPercent($item['value'], $financialChartTotal), 1, ',', '.') ?>%)</span>
                                    </div>
                                    <div class="saas-command-bar">
                                        <span class="saas-command-bar-fill tone-<?= htmlspecialchars($item['tone']) ?>" style="width: <?= htmlspecialchars((string) $chartPercent($item['value'], $financialChartTotal)) ?>%"></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="saas-command-chart-card">
                        <div>
                            <h3>Suporte e resposta</h3>
                            <p>Chamado urgente acumulado nao e detalhe de atendimento, e sinal de fragilidade operacional.</p>
                        </div>
                        <div class="saas-command-chart-stack">
                            <?php foreach ($supportChartItems as $item): ?>
                                <div class="saas-command-chart-row">
                                    <div class="saas-command-chart-meta">
                                        <span><?= htmlspecialchars($item['label']) ?></span>
                                        <span><?= htmlspecialchars((string) $item['value']) ?> (<?= number_format($chartPercent($item['value'], $supportChartTotal), 1, ',', '.') ?>%)</span>
                                    </div>
                                    <div class="saas-command-bar">
                                        <span class="saas-command-bar-fill tone-<?= htmlspecialchars($item['tone']) ?>" style="width: <?= htmlspecialchars((string) $chartPercent($item['value'], $supportChartTotal)) ?>%"></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                </div>
            </section>

            <section class="card">
                <div class="saas-command-head">
                    <div>
                        <h2>Radar de prioridade</h2>
                        <p class="saas-command-note">Esses blocos existem para puxar acao. Quando um numero aqui sobe, o impacto ja cruzou modulo e passou a ser assunto de gestao.</p>
                    </div>
                </div>

                <div class="saas-command-alert-grid" style="margin-top:16px">
                    <article class="saas-command-alert is-danger">
                        <div class="saas-command-alert-top">
                            <div>
                                <span>Financeiro</span>
                                <strong><?= htmlspecialchars((string) ($overview['delinquent_companies'] ?? 0)) ?> empresas inadimplentes</strong>
                            </div>
                            <span class="badge status-overdue">Risco</span>
                        </div>
                        <p>Essas empresas ja sairam do campo de acompanhamento leve. O problema agora envolve caixa, relacionamento comercial e chance de cancelamento.</p>
                        <a class="btn" href="<?= htmlspecialchars(base_url('/saas/companies?company_subscription_status=inadimplente')) ?>">Abrir empresas em atraso</a>
                    </article>

                    <article class="saas-command-alert is-warning">
                        <div class="saas-command-alert-top">
                            <div>
                                <span>Cobranca</span>
                                <strong><?= htmlspecialchars((string) ($overview['pending_charges'] ?? 0)) ?> pendentes e <?= htmlspecialchars((string) ($overview['overdue_charges'] ?? 0)) ?> vencidas</strong>
                            </div>
                            <span class="badge status-pending">Fila</span>
                        </div>
                        <p>Fila financeira longa demais costuma mascarar atraso operacional como se fosse apenas etapa normal de cobranca.</p>
                        <div class="saas-command-actions">
                            <a class="btn" href="<?= htmlspecialchars(base_url('/saas/subscription-payments?status=pendente')) ?>">Pendentes</a>
                            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/saas/subscription-payments?status=vencido')) ?>">Vencidas</a>
                        </div>
                    </article>

                    <article class="saas-command-alert is-info">
                        <div class="saas-command-alert-top">
                            <div>
                                <span>Suporte</span>
                                <strong><?= htmlspecialchars((string) ($supportSummary['open_count'] ?? 0)) ?> abertos, <?= htmlspecialchars((string) ($supportSummary['urgent_count'] ?? 0)) ?> urgentes</strong>
                            </div>
                            <span class="badge status-received">Atendimento</span>
                        </div>
                        <p>Chamado aberto sem dono claro vira ruina de percepcao do sistema inteiro, mesmo quando o problema tecnico e localizado.</p>
                        <div class="saas-command-actions">
                            <a class="btn" href="<?= htmlspecialchars(base_url('/saas/support?support_status=open')) ?>">Chamados abertos</a>
                            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/saas/support?support_priority=urgent')) ?>">Urgentes</a>
                        </div>
                    </article>

                    <article class="saas-command-alert is-info">
                        <div class="saas-command-alert-top">
                            <div>
                                <span>Automacao</span>
                                <strong><?= htmlspecialchars((string) ($overview['gateway_bound_subscriptions'] ?? 0)) ?> com gateway, <?= htmlspecialchars((string) ($overview['auto_charge_enabled'] ?? 0)) ?> com auto cobranca</strong>
                            </div>
                            <span class="badge status-active">Escala</span>
                        </div>
                        <p>Assinatura sem trilho automatico ainda depende de operacao humana. Em escala, isso deixa de ser excecao e vira custo estrutural.</p>
                        <div class="saas-command-actions">
                            <a class="btn" href="<?= htmlspecialchars(base_url('/saas/subscriptions')) ?>">Ver assinaturas</a>
                            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/saas/subscription-payments')) ?>">Ver cobrancas</a>
                        </div>
                    </article>
                </div>
            </section>

            <div class="saas-command-card-grid">
                <section class="card">
                    <div class="saas-command-head">
                        <div>
                            <h2>Empresas recentes</h2>
                            <p class="saas-command-note">Novos clientes e casos recentes ajudam a enxergar expansao, risco contratual e qualidade de entrada da base.</p>
                        </div>
                        <a class="btn secondary" href="<?= htmlspecialchars(base_url('/saas/companies')) ?>">Abrir modulo</a>
                    </div>

                    <div class="saas-command-list" style="margin-top:16px">
                        <?php if ($companies === []): ?>
                            <div class="saas-command-empty">Nenhuma empresa encontrada para exibir no painel.</div>
                        <?php endif; ?>

                        <?php foreach ($companies as $company): ?>
                            <article class="saas-command-item">
                                <div class="saas-command-item-top">
                                    <div class="saas-command-item-title">
                                        <strong><?= htmlspecialchars((string) ($company['name'] ?? 'Empresa')) ?></strong>
                                        <small><?= htmlspecialchars((string) ($company['slug'] ?? '-')) ?> · <?= htmlspecialchars((string) ($company['plan_name'] ?? 'Sem plano')) ?></small>
                                    </div>
                                    <span class="badge <?= htmlspecialchars(status_badge_class('company_subscription_status', $company['subscription_status'] ?? null)) ?>"><?= htmlspecialchars(status_label('company_subscription_status', $company['subscription_status'] ?? null)) ?></span>
                                </div>
                                <div class="saas-command-meta">
                                    <div class="saas-command-meta-box">
                                        <span>Status operacional</span>
                                        <strong><?= htmlspecialchars(status_label('company_status', $company['status'] ?? null)) ?></strong>
                                    </div>
                                    <div class="saas-command-meta-box">
                                        <span>Proxima cobranca</span>
                                        <strong><?= htmlspecialchars($formatDate($company['next_charge_due_date'] ?? null, false)) ?></strong>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="card">
                    <div class="saas-command-head">
                        <div>
                            <h2>Assinaturas recentes</h2>
                            <p class="saas-command-note">A carteira precisa ser lida com contexto financeiro e operacional, nao so por quantidade.</p>
                        </div>
                        <a class="btn secondary" href="<?= htmlspecialchars(base_url('/saas/subscriptions')) ?>">Abrir modulo</a>
                    </div>

                    <div class="saas-command-list" style="margin-top:16px">
                        <?php if ($subscriptions === []): ?>
                            <div class="saas-command-empty">Nenhuma assinatura encontrada para exibir no painel.</div>
                        <?php endif; ?>

                        <?php foreach ($subscriptions as $subscription): ?>
                            <article class="saas-command-item">
                                <div class="saas-command-item-top">
                                    <div class="saas-command-item-title">
                                        <strong><?= htmlspecialchars((string) ($subscription['company_name'] ?? 'Empresa')) ?></strong>
                                        <small><?= htmlspecialchars((string) ($subscription['plan_name'] ?? 'Sem plano')) ?> · <?= htmlspecialchars(status_label('billing_cycle', $subscription['billing_cycle'] ?? null)) ?></small>
                                    </div>
                                    <span class="badge <?= htmlspecialchars(status_badge_class('subscription_status', $subscription['status'] ?? null)) ?>"><?= htmlspecialchars(status_label('subscription_status', $subscription['status'] ?? null)) ?></span>
                                </div>
                                <div class="saas-command-meta">
                                    <div class="saas-command-meta-box">
                                        <span>Valor</span>
                                        <strong><?= htmlspecialchars($formatMoney($subscription['amount'] ?? 0)) ?></strong>
                                    </div>
                                    <div class="saas-command-meta-box">
                                        <span>Gateway</span>
                                        <strong><?= htmlspecialchars($gatewayStatusLabel($subscription['gateway_status'] ?? null)) ?></strong>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="card">
                    <div class="saas-command-head">
                        <div>
                            <h2>Fila financeira</h2>
                            <p class="saas-command-note">Este card agora trabalha com filtro proprio, limite de 10 registros e paginacao real. Sem isso, o dashboard virava vitrine e nao fila de trabalho.</p>
                        </div>
                        <div class="saas-command-badges">
                            <span class="badge">10 por pagina</span>
                            <span class="badge">Total filtrado: <?= htmlspecialchars((string) $dashboardPaymentTotal) ?></span>
                        </div>
                    </div>

                    <form method="GET" action="<?= htmlspecialchars(base_url('/saas/dashboard')) ?>" class="saas-command-filter-grid">
                        <?php foreach ($currentQuery as $queryKey => $queryValue): ?>
                            <?php if (!in_array((string) $queryKey, ['dashboard_payment_search', 'dashboard_payment_status', 'dashboard_payment_page'], true)): ?>
                                <input type="hidden" name="<?= htmlspecialchars((string) $queryKey) ?>" value="<?= htmlspecialchars((string) $queryValue) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <div class="field">
                            <label for="dashboard-payment-search">Buscar</label>
                            <input
                                id="dashboard-payment-search"
                                type="text"
                                name="dashboard_payment_search"
                                value="<?= htmlspecialchars($dashboardPaymentSearch) ?>"
                                placeholder="Empresa, plano ou referencia"
                            >
                        </div>
                        <div class="field">
                            <label for="dashboard-payment-status">Status</label>
                            <select id="dashboard-payment-status" name="dashboard_payment_status">
                                <?php foreach ($paymentStatusOptions as $optionValue => $optionLabel): ?>
                                    <option value="<?= htmlspecialchars($optionValue) ?>" <?= $dashboardPaymentStatus === $optionValue ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($optionLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="saas-command-filter-actions">
                            <input type="hidden" name="dashboard_payment_page" value="1">
                            <button class="btn" type="submit">Aplicar</button>
                            <a class="btn secondary" href="<?= htmlspecialchars($buildDashboardPaymentsUrl([
                                'dashboard_payment_search' => '',
                                'dashboard_payment_status' => '',
                                'dashboard_payment_page' => '',
                            ])) ?>">Limpar</a>
                        </div>
                    </form>

                    <div class="saas-command-list" style="margin-top:16px">
                        <?php if ($payments === []): ?>
                            <div class="saas-command-empty">Nenhuma cobranca encontrada para os filtros aplicados.</div>
                        <?php endif; ?>

                        <?php foreach ($payments as $payment): ?>
                            <article class="saas-command-item">
                                <div class="saas-command-item-top">
                                    <div class="saas-command-item-title">
                                        <strong><?= htmlspecialchars((string) ($payment['company_name'] ?? 'Empresa')) ?></strong>
                                        <small><?= htmlspecialchars((string) ($payment['plan_name'] ?? 'Sem plano')) ?> · Ref. <?= str_pad((string) (int) ($payment['reference_month'] ?? 0), 2, '0', STR_PAD_LEFT) ?>/<?= (int) ($payment['reference_year'] ?? 0) ?></small>
                                    </div>
                                    <span class="badge <?= htmlspecialchars(status_badge_class('subscription_payment_status', $payment['status'] ?? null)) ?>"><?= htmlspecialchars(status_label('subscription_payment_status', $payment['status'] ?? null)) ?></span>
                                </div>
                                <div class="saas-command-meta">
                                    <div class="saas-command-meta-box">
                                        <span>Valor</span>
                                        <strong><?= htmlspecialchars($formatMoney($payment['amount'] ?? 0)) ?></strong>
                                    </div>
                                    <div class="saas-command-meta-box">
                                        <span>Vencimento</span>
                                        <strong><?= htmlspecialchars($formatDate($payment['due_date'] ?? null, false)) ?></strong>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($dashboardPaymentTotal > 0): ?>
                        <div class="saas-command-pagination">
                            <div class="saas-command-note">
                                Exibindo <?= htmlspecialchars((string) $dashboardPaymentFrom) ?> a <?= htmlspecialchars((string) $dashboardPaymentTo) ?> de <?= htmlspecialchars((string) $dashboardPaymentTotal) ?> cobrancas filtradas.
                            </div>
                            <?php if ($dashboardPaymentLastPage > 1): ?>
                                <div class="saas-command-pagination-controls">
                                    <?php if ($dashboardPaymentPage > 1): ?>
                                        <a class="saas-page-btn" href="<?= htmlspecialchars($buildDashboardPaymentsUrl(['dashboard_payment_page' => $dashboardPaymentPage - 1])) ?>">Anterior</a>
                                    <?php endif; ?>

                                    <?php
                                    $lastRenderedPage = 0;
                                    foreach ($dashboardPaymentPages as $pageNumber):
                                        $pageNumber = (int) $pageNumber;
                                        if ($lastRenderedPage > 0 && $pageNumber - $lastRenderedPage > 1): ?>
                                            <span class="saas-page-ellipsis">...</span>
                                        <?php endif; ?>

                                        <a class="saas-page-btn<?= $pageNumber === $dashboardPaymentPage ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildDashboardPaymentsUrl(['dashboard_payment_page' => $pageNumber])) ?>">
                                            <?= $pageNumber ?>
                                        </a>

                                        <?php $lastRenderedPage = $pageNumber; ?>
                                    <?php endforeach; ?>

                                    <?php if ($dashboardPaymentPage < $dashboardPaymentLastPage): ?>
                                        <a class="saas-page-btn" href="<?= htmlspecialchars($buildDashboardPaymentsUrl(['dashboard_payment_page' => $dashboardPaymentPage + 1])) ?>">Proxima</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="card">
                    <div class="saas-command-head">
                        <div>
                            <h2>Suporte em foco</h2>
                            <p class="saas-command-note">Chamados sao sinal operacional da saude da plataforma. O dashboard precisa aproximar gestao de atendimento.</p>
                        </div>
                        <a class="btn secondary" href="<?= htmlspecialchars(base_url('/saas/support')) ?>">Abrir modulo</a>
                    </div>

                    <div class="saas-command-list" style="margin-top:16px">
                        <?php if ($tickets === []): ?>
                            <div class="saas-command-empty">Nenhum chamado encontrado para exibir no painel.</div>
                        <?php endif; ?>

                        <?php foreach ($tickets as $ticket): ?>
                            <article class="saas-command-item">
                                <div class="saas-command-item-top">
                                    <div class="saas-command-item-title">
                                        <strong>#<?= (int) ($ticket['id'] ?? 0) ?> · <?= htmlspecialchars((string) ($ticket['subject'] ?? 'Chamado')) ?></strong>
                                        <small><?= htmlspecialchars((string) ($ticket['company_name'] ?? 'Empresa')) ?> · <?= htmlspecialchars((string) ($ticket['company_slug'] ?? '-')) ?></small>
                                    </div>
                                    <span class="badge <?= htmlspecialchars(status_badge_class('print_log_status', strtolower(trim((string) ($ticket['priority'] ?? ''))) === 'urgent' ? 'failed' : 'success')) ?>"><?= htmlspecialchars($supportPriorityLabel($ticket['priority'] ?? null)) ?></span>
                                </div>
                                <div class="saas-command-meta">
                                    <div class="saas-command-meta-box">
                                        <span>Status</span>
                                        <strong><?= htmlspecialchars($supportStatusLabel($ticket['status'] ?? null)) ?></strong>
                                    </div>
                                    <div class="saas-command-meta-box">
                                        <span>Atualizacao</span>
                                        <strong><?= htmlspecialchars($formatDate($ticket['updated_at'] ?? null)) ?></strong>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </div>

        <aside class="saas-command-side">
            <section class="card">
                <div class="saas-command-head">
                    <div>
                        <h3>Resumo gerencial</h3>
                        <p class="saas-command-note">Leitura compacta para saber se o peso dominante esta em aquisicao, recorrencia, inadimplencia ou atendimento.</p>
                    </div>
                </div>
                <div class="saas-command-summary-grid">
                    <div class="saas-command-summary-item"><strong>Empresas ativas</strong><span><?= htmlspecialchars((string) ($companySummary['active_companies'] ?? 0)) ?></span></div>
                    <div class="saas-command-summary-item"><strong>Empresas em teste</strong><span><?= htmlspecialchars((string) ($companySummary['trial_companies'] ?? 0)) ?></span></div>
                    <div class="saas-command-summary-item"><strong>Planos ativos</strong><span><?= htmlspecialchars((string) ($planSummary['active_plans'] ?? 0)) ?></span></div>
                    <div class="saas-command-summary-item"><strong>Planos em uso</strong><span><?= htmlspecialchars((string) ($planSummary['plans_in_company_use'] ?? 0)) ?></span></div>
                    <div class="saas-command-summary-item"><strong>Assinaturas trial</strong><span><?= htmlspecialchars((string) ($subscriptionSummary['trial_subscriptions'] ?? 0)) ?></span></div>
                    <div class="saas-command-summary-item"><strong>Cobrancas pagas</strong><span><?= htmlspecialchars((string) ($paymentSummary['paid_charges'] ?? 0)) ?></span></div>
                    <div class="saas-command-summary-item"><strong>Recebido</strong><span><?= htmlspecialchars($formatMoney($paymentSummary['total_paid_amount'] ?? 0)) ?></span></div>
                    <div class="saas-command-summary-item"><strong>Chamados em andamento</strong><span><?= htmlspecialchars((string) ($supportSummary['in_progress_count'] ?? 0)) ?></span></div>
                </div>
            </section>

            <section class="card">
                <div class="saas-command-head">
                    <div>
                        <h3>Central de gestao</h3>
                        <p class="saas-command-note">Atalhos para os modulos que sustentam governanca, receita e operacao diaria do SaaS.</p>
                    </div>
                </div>
                <div class="saas-command-hub">
                    <a href="<?= htmlspecialchars(base_url('/saas/companies')) ?>">
                        <div>
                            <strong>Empresas</strong>
                            <small>Cadastro, status operacional, plano e ciclo de vida da carteira.</small>
                        </div>
                        <span>&gt;</span>
                    </a>
                    <a href="<?= htmlspecialchars(base_url('/saas/plans')) ?>">
                        <div>
                            <strong>Planos</strong>
                            <small>Catalogo comercial, limites e aderencia da oferta.</small>
                        </div>
                        <span>&gt;</span>
                    </a>
                    <a href="<?= htmlspecialchars(base_url('/saas/subscriptions')) ?>">
                        <div>
                            <strong>Assinaturas</strong>
                            <small>Trilho contratual, gateway e automacao de cobranca.</small>
                        </div>
                        <span>&gt;</span>
                    </a>
                    <a href="<?= htmlspecialchars(base_url('/saas/subscription-payments')) ?>">
                        <div>
                            <strong>Cobrancas</strong>
                            <small>Fila financeira, PIX real, sincronizacao e excecoes.</small>
                        </div>
                        <span>&gt;</span>
                    </a>
                    <a href="<?= htmlspecialchars(base_url('/saas/support')) ?>">
                        <div>
                            <strong>Suporte</strong>
                            <small>Atendimento, urgencia, resposta e historico operacional.</small>
                        </div>
                        <span>&gt;</span>
                    </a>
                </div>
            </section>

            <section class="card">
                <div class="saas-command-head">
                    <div>
                        <h3>Planos em evidencia</h3>
                        <p class="saas-command-note">Visao rapida de quais ofertas realmente sustentam a base.</p>
                    </div>
                </div>
                <div class="saas-command-list">
                    <?php if ($plans === []): ?>
                        <div class="saas-command-empty">Nenhum plano disponivel para destaque no painel.</div>
                    <?php endif; ?>

                    <?php foreach ($plans as $plan): ?>
                        <article class="saas-command-item">
                            <div class="saas-command-item-top">
                                <div class="saas-command-item-title">
                                    <strong><?= htmlspecialchars((string) ($plan['name'] ?? 'Plano')) ?></strong>
                                    <small><?= htmlspecialchars((string) ($plan['slug'] ?? '-')) ?></small>
                                </div>
                                <span class="badge <?= htmlspecialchars(status_badge_class('plan_status', $plan['status'] ?? null)) ?>"><?= htmlspecialchars(status_label('plan_status', $plan['status'] ?? null)) ?></span>
                            </div>
                            <div class="saas-command-meta">
                                <div class="saas-command-meta-box">
                                    <span>Empresas</span>
                                    <strong><?= (int) ($plan['linked_companies_count'] ?? 0) ?></strong>
                                </div>
                                <div class="saas-command-meta-box">
                                    <span>Mensal</span>
                                    <strong><?= htmlspecialchars($formatMoney($plan['price_monthly'] ?? 0)) ?></strong>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="saas-command-rule">
                <h3>Regra operacional</h3>
                <p>Um painel de gestao so e util quando expone relacao entre modulos. Empresa, assinatura, cobranca e suporte nao podem ser lidos como telas isoladas porque o risco do SaaS nasce justamente na passagem entre elas.</p>
                <ul>
                    <li>Empresa inadimplente e problema financeiro e tambem de relacionamento.</li>
                    <li>Assinatura sem automacao amplia custo operacional no modulo de cobrancas.</li>
                    <li>Chamado urgente recorrente e sinal de fragilidade estrutural, nao so atendimento.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>
