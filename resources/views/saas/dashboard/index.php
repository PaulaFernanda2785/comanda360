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
$supportFilters = is_array($supportPanel['filters'] ?? null) ? $supportPanel['filters'] : [];
$supportPagination = is_array($supportPanel['pagination'] ?? null) ? $supportPanel['pagination'] : [];

$dashboardPaymentSearch = trim((string) ($paymentFilters['search'] ?? ''));
$dashboardPaymentStatus = trim((string) ($paymentFilters['status'] ?? ''));
$dashboardPaymentPage = max(1, (int) ($paymentPagination['page'] ?? 1));
$dashboardPaymentLastPage = max(1, (int) ($paymentPagination['last_page'] ?? 1));
$dashboardPaymentFrom = (int) ($paymentPagination['from'] ?? 0);
$dashboardPaymentTo = (int) ($paymentPagination['to'] ?? 0);
$dashboardPaymentTotal = (int) ($paymentPagination['total'] ?? ($paymentSummary['total_charges'] ?? count($payments)));
$dashboardPaymentPages = is_array($paymentPagination['pages'] ?? null) ? $paymentPagination['pages'] : [];

$dashboardSupportSearch = trim((string) ($supportFilters['search'] ?? ''));
$dashboardSupportStatus = trim((string) ($supportFilters['status'] ?? ''));
$dashboardSupportPriority = trim((string) ($supportFilters['priority'] ?? ''));
$dashboardSupportPage = max(1, (int) ($supportPagination['page'] ?? 1));
$dashboardSupportLastPage = max(1, (int) ($supportPagination['last_page'] ?? 1));
$dashboardSupportFrom = (int) ($supportPagination['from'] ?? 0);
$dashboardSupportTo = (int) ($supportPagination['to'] ?? 0);
$dashboardSupportTotal = (int) ($supportPagination['total'] ?? ($supportSummary['total'] ?? count($tickets)));
$dashboardSupportPages = is_array($supportPagination['pages'] ?? null) ? $supportPagination['pages'] : [];

$paymentStatusOptions = [
    '' => 'Todos os status',
    'pendente' => 'Pendentes',
    'vencido' => 'Vencidas',
    'pago' => 'Pagas',
    'cancelado' => 'Canceladas',
];

$supportStatusOptions = [
    '' => 'Todos os status',
    'open' => 'Abertos',
    'in_progress' => 'Em andamento',
    'resolved' => 'Resolvidos',
    'closed' => 'Fechados',
];

$supportPriorityOptions = [
    '' => 'Todas as prioridades',
    'urgent' => 'Urgente',
    'high' => 'Alta',
    'medium' => 'Media',
    'low' => 'Baixa',
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
$buildDashboardUrl = static function (array $overrides, array $managedKeys) use ($currentQuery): string {
    $params = array_merge($currentQuery, $overrides);

    foreach ($managedKeys as $managedKey) {
        if (array_key_exists($managedKey, $params) && trim((string) $params[$managedKey]) === '') {
            unset($params[$managedKey]);
        }
    }

    $query = http_build_query($params);
    return base_url('/saas/dashboard' . ($query !== '' ? '?' . $query : ''));
};

$buildDashboardPaymentsUrl = static fn (array $overrides = []): string => $buildDashboardUrl(
    $overrides,
    ['dashboard_payment_search', 'dashboard_payment_status', 'dashboard_payment_page']
);

$buildDashboardSupportUrl = static fn (array $overrides = []): string => $buildDashboardUrl(
    $overrides,
    ['dashboard_support_search', 'dashboard_support_status', 'dashboard_support_priority', 'dashboard_support_page']
);

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

$executiveCards = [
    [
        'label' => 'Empresas',
        'value' => (string) ($overview['total_companies'] ?? 0),
        'note' => 'Base cadastrada no SaaS',
        'tone' => 'info',
    ],
    [
        'label' => 'Assinaturas ativas',
        'value' => (string) ($overview['active_subscriptions'] ?? 0),
        'note' => 'Receita recorrente em producao',
        'tone' => 'success',
    ],
    [
        'label' => 'MRR ativo',
        'value' => $formatMoney($overview['active_monthly_mrr'] ?? 0),
        'note' => 'Somente contratos mensais ativos',
        'tone' => 'primary',
    ],
    [
        'label' => 'Cobrancas vencidas',
        'value' => (string) ($overview['overdue_charges'] ?? 0),
        'note' => 'Risco financeiro imediato',
        'tone' => 'warning',
    ],
];

$chartGroups = [
    [
        'title' => 'Base de empresas',
        'description' => 'Mostra o retrato operacional da carteira e evita confundir crescimento com saude da base.',
        'items' => $companyChartItems,
        'total' => $companyChartTotal,
    ],
    [
        'title' => 'Carteira de assinaturas',
        'description' => 'Separa contratos ativos, trial, expirados e nivel de automacao da recorrencia.',
        'items' => $subscriptionChartItems,
        'total' => $subscriptionChartTotal,
    ],
    [
        'title' => 'Pressao financeira',
        'description' => 'Mostra o peso real de pendencia, atraso e recuperacao dentro da operacao.',
        'items' => $financialChartItems,
        'total' => $financialChartTotal,
    ],
    [
        'title' => 'Atendimento e suporte',
        'description' => 'Chamado urgente acumulado e sintoma de fragilidade estrutural, nao apenas fila de atendimento.',
        'items' => $supportChartItems,
        'total' => $supportChartTotal,
    ],
];

$priorityCards = [
    [
        'tone' => 'danger',
        'eyebrow' => 'Financeiro',
        'headline' => (string) ($overview['delinquent_companies'] ?? 0) . ' empresas inadimplentes',
        'copy' => 'Esse volume ja mistura risco de caixa, chance de cancelamento e desgaste comercial.',
        'actions' => [
            ['label' => 'Ver empresas em atraso', 'href' => base_url('/saas/companies?company_subscription_status=inadimplente'), 'secondary' => false],
        ],
    ],
    [
        'tone' => 'warning',
        'eyebrow' => 'Fila financeira',
        'headline' => (string) ($overview['pending_charges'] ?? 0) . ' pendentes e ' . (string) ($overview['overdue_charges'] ?? 0) . ' vencidas',
        'copy' => 'Cobranca atrasada em escala deixa de ser detalhe operacional e passa a exigir gestao ativa.',
        'actions' => [
            ['label' => 'Pendentes', 'href' => base_url('/saas/subscription-payments?status=pendente'), 'secondary' => false],
            ['label' => 'Vencidas', 'href' => base_url('/saas/subscription-payments?status=vencido'), 'secondary' => true],
        ],
    ],
    [
        'tone' => 'info',
        'eyebrow' => 'Suporte',
        'headline' => (string) ($supportSummary['open_count'] ?? 0) . ' abertos e ' . (string) ($supportSummary['urgent_count'] ?? 0) . ' urgentes',
        'copy' => 'Chamado sem dono claro piora percepcao de produto mesmo quando a falha e localizada.',
        'actions' => [
            ['label' => 'Chamados abertos', 'href' => base_url('/saas/support?support_status=open'), 'secondary' => false],
            ['label' => 'Urgentes', 'href' => base_url('/saas/support?support_priority=urgent'), 'secondary' => true],
        ],
    ],
    [
        'tone' => 'primary',
        'eyebrow' => 'Automacao',
        'headline' => (string) ($overview['gateway_bound_subscriptions'] ?? 0) . ' com gateway e ' . (string) ($overview['auto_charge_enabled'] ?? 0) . ' com auto cobranca',
        'copy' => 'Toda assinatura fora do trilho automatico aumenta custo operacional e dependencia humana.',
        'actions' => [
            ['label' => 'Assinaturas', 'href' => base_url('/saas/subscriptions'), 'secondary' => false],
            ['label' => 'Cobrancas', 'href' => base_url('/saas/subscription-payments'), 'secondary' => true],
        ],
    ],
];

$managementSummaryItems = [
    ['label' => 'Empresas ativas', 'value' => (string) ($companySummary['active_companies'] ?? 0)],
    ['label' => 'Empresas em teste', 'value' => (string) ($companySummary['testing_companies'] ?? 0)],
    ['label' => 'Planos ativos', 'value' => (string) ($planSummary['active_plans'] ?? 0)],
    ['label' => 'Planos em uso', 'value' => (string) ($planSummary['plans_in_company_use'] ?? 0)],
    ['label' => 'Assinaturas trial', 'value' => (string) ($subscriptionSummary['trial_subscriptions'] ?? 0)],
    ['label' => 'Cobrancas pagas', 'value' => (string) ($paymentSummary['paid_charges'] ?? 0)],
    ['label' => 'Recebido', 'value' => $formatMoney($paymentSummary['total_paid_amount'] ?? 0)],
    ['label' => 'Chamados em andamento', 'value' => (string) ($supportSummary['in_progress_count'] ?? 0)],
];

$hubLinks = [
    [
        'title' => 'Empresas',
        'copy' => 'Cadastro, status operacional, plano e ciclo de vida da carteira.',
        'href' => base_url('/saas/companies'),
    ],
    [
        'title' => 'Planos',
        'copy' => 'Catalogo comercial, limites, modulos e coerencia da oferta.',
        'href' => base_url('/saas/plans'),
    ],
    [
        'title' => 'Assinaturas',
        'copy' => 'Trilho contratual, recorrencia, gateway e automacao de cobranca.',
        'href' => base_url('/saas/subscriptions'),
    ],
    [
        'title' => 'Cobrancas',
        'copy' => 'Fila financeira, PIX real, sincronizacao e tratamento de excecoes.',
        'href' => base_url('/saas/subscription-payments'),
    ],
    [
        'title' => 'Suporte',
        'copy' => 'Atendimento, urgencia, historico e qualidade operacional da plataforma.',
        'href' => base_url('/saas/support'),
    ],
];

$governanceNotes = [
    'Empresa inadimplente e problema financeiro, mas tambem de relacionamento e retencao.',
    'Assinatura sem automacao amplia custo operacional e atrito no modulo de cobrancas.',
    'Chamado urgente recorrente mostra fragilidade estrutural, nao apenas carga no atendimento.',
];
?>

<style>
    .saas-dashboard-page{display:grid;gap:16px}
    .saas-dashboard-hero{border:1px solid #bfdbfe;background:linear-gradient(120deg,var(--theme-main-card,#0f172a) 0%,#1d4ed8 42%,#0f766e 100%);color:#fff;border-radius:18px;padding:20px;position:relative;overflow:hidden}
    .saas-dashboard-hero:before{content:"";position:absolute;top:-54px;right:-34px;width:220px;height:220px;border-radius:999px;background:rgba(255,255,255,.11)}
    .saas-dashboard-hero:after{content:"";position:absolute;bottom:-82px;left:-38px;width:190px;height:190px;border-radius:999px;background:rgba(255,255,255,.08)}
    .saas-dashboard-hero-body{position:relative;z-index:1;display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap}
    .saas-dashboard-hero-copy{max-width:920px}
    .saas-dashboard-hero h1{margin:0 0 8px;font-size:30px}
    .saas-dashboard-hero p{margin:0;color:#dbeafe;line-height:1.55}
    .saas-dashboard-pills{display:flex;gap:8px;flex-wrap:wrap}
    .saas-dashboard-pill{border:1px solid rgba(255,255,255,.24);background:rgba(15,23,42,.35);padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700}

    .saas-dashboard-layout{display:grid;grid-template-columns:minmax(0,2.05fr) minmax(260px,.72fr);gap:16px;align-items:start}
    .saas-dashboard-main,.saas-dashboard-side{display:grid;gap:16px}

    .saas-dashboard-panel-head{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:start}
    .saas-dashboard-panel-copy{min-width:0}
    .saas-dashboard-panel-copy h2,.saas-dashboard-panel-copy h3{margin:0;color:#0f172a}
    .saas-dashboard-panel-note{margin:4px 0 0;color:#64748b;font-size:13px;line-height:1.5}
    .saas-dashboard-panel-actions{display:grid;gap:8px;justify-items:end}
    .saas-dashboard-badges{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
    .saas-dashboard-badges .badge,.saas-dashboard-panel-actions .btn{white-space:nowrap}

    .saas-dashboard-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:16px}
    .saas-dashboard-kpi{border:1px solid #dbeafe;border-radius:14px;background:linear-gradient(180deg,#fff,#f8fafc);padding:14px}
    .saas-dashboard-kpi.tone-success{background:linear-gradient(180deg,#f0fdf4,#dcfce7)}
    .saas-dashboard-kpi.tone-info{background:linear-gradient(180deg,#f8fafc,#eff6ff)}
    .saas-dashboard-kpi.tone-primary{background:linear-gradient(180deg,#eff6ff,#dbeafe)}
    .saas-dashboard-kpi.tone-warning{background:linear-gradient(180deg,#fffbeb,#fef3c7)}
    .saas-dashboard-kpi span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
    .saas-dashboard-kpi strong{display:block;margin-top:6px;font-size:24px;color:#0f172a}
    .saas-dashboard-kpi small{display:block;margin-top:4px;color:#475569;line-height:1.4}

    .saas-dashboard-chart-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-top:16px}
    .saas-dashboard-chart-card{border:1px solid #dbeafe;border-radius:16px;background:linear-gradient(180deg,#fff,#f8fafc);padding:16px;display:grid;gap:14px}
    .saas-dashboard-chart-card h3{margin:0;color:#0f172a;font-size:17px}
    .saas-dashboard-chart-card p{margin:0;color:#64748b;font-size:13px;line-height:1.45}
    .saas-dashboard-chart-stack{display:grid;gap:10px}
    .saas-dashboard-chart-row{display:grid;gap:6px}
    .saas-dashboard-chart-meta{display:flex;justify-content:space-between;align-items:center;gap:10px;font-size:13px;color:#0f172a}
    .saas-dashboard-chart-meta span:last-child{color:#475569;font-weight:700}
    .saas-dashboard-bar{height:10px;border-radius:999px;background:#e2e8f0;overflow:hidden}
    .saas-dashboard-bar-fill{display:block;height:100%;border-radius:999px}
    .saas-dashboard-bar-fill.tone-success{background:linear-gradient(90deg,#16a34a,#4ade80)}
    .saas-dashboard-bar-fill.tone-info{background:linear-gradient(90deg,#0ea5e9,#38bdf8)}
    .saas-dashboard-bar-fill.tone-warning{background:linear-gradient(90deg,#f59e0b,#facc15)}
    .saas-dashboard-bar-fill.tone-danger{background:linear-gradient(90deg,#dc2626,#fb7185)}
    .saas-dashboard-bar-fill.tone-primary{background:linear-gradient(90deg,#1d4ed8,#60a5fa)}

    .saas-dashboard-alert-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:16px}
    .saas-dashboard-alert{display:grid;gap:10px;border-radius:14px;padding:14px;border:1px solid #e2e8f0;background:linear-gradient(180deg,#fff,#f8fafc)}
    .saas-dashboard-alert.tone-danger{border-color:#fecaca;background:linear-gradient(180deg,#fff,#fef2f2)}
    .saas-dashboard-alert.tone-warning{border-color:#fde68a;background:linear-gradient(180deg,#fff,#fffbeb)}
    .saas-dashboard-alert.tone-info{border-color:#bfdbfe;background:linear-gradient(180deg,#fff,#eff6ff)}
    .saas-dashboard-alert.tone-primary{border-color:#c7d2fe;background:linear-gradient(180deg,#fff,#eef2ff)}
    .saas-dashboard-alert-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
    .saas-dashboard-alert-head span{font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.04em}
    .saas-dashboard-alert-head strong{display:block;margin-top:4px;font-size:18px;color:#0f172a;line-height:1.35}
    .saas-dashboard-alert p{margin:0;color:#475569;font-size:13px;line-height:1.45}
    .saas-dashboard-actions{display:flex;gap:8px;flex-wrap:wrap}

    .saas-dashboard-columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
    .saas-dashboard-list{display:grid;gap:10px;margin-top:16px}
    .saas-dashboard-row{border:1px solid #dbeafe;border-radius:14px;background:linear-gradient(180deg,#fff,#f8fafc);padding:12px;display:grid;gap:8px}
    .saas-dashboard-row-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
    .saas-dashboard-row-copy{display:grid;gap:4px;min-width:0}
    .saas-dashboard-row-copy strong{font-size:15px;color:#0f172a}
    .saas-dashboard-row-copy small{font-size:12px;color:#64748b;line-height:1.35}
    .saas-dashboard-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    .saas-dashboard-meta-box{border:1px solid #e2e8f0;background:#fff;border-radius:10px;padding:9px}
    .saas-dashboard-meta-box span{display:block;font-size:11px;text-transform:uppercase;color:#64748b}
    .saas-dashboard-meta-box strong{display:block;margin-top:4px;font-size:13px;color:#0f172a}
    .saas-dashboard-empty{padding:14px;border:1px dashed #cbd5e1;border-radius:14px;background:#f8fafc;color:#64748b;font-size:13px;line-height:1.5}

    .saas-dashboard-filter{display:grid;gap:10px;align-items:end;margin-top:16px}
    .saas-dashboard-filter.is-financial{grid-template-columns:minmax(0,1.65fr) minmax(180px,.9fr) auto}
    .saas-dashboard-filter.is-support{grid-template-columns:minmax(0,1.45fr) minmax(150px,.85fr) minmax(150px,.85fr) auto}
    .saas-dashboard-filter .field{margin:0}
    .saas-dashboard-filter .field input,.saas-dashboard-filter .field select{width:100%;min-width:0}
    .saas-dashboard-filter-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;align-self:end}
    .saas-dashboard-filter-actions .btn{white-space:nowrap}

    .saas-dashboard-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:14px}
    .saas-dashboard-pagination-controls{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .saas-dashboard-page-btn{display:inline-block;padding:8px 11px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#0f172a;text-decoration:none}
    .saas-dashboard-page-btn.is-active{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
    .saas-dashboard-page-ellipsis{color:#64748b;padding:0 2px}

    .saas-dashboard-summary{display:grid;gap:8px}
    .saas-dashboard-summary-item{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px}
    .saas-dashboard-summary-item strong{color:#0f172a}
    .saas-dashboard-summary-item span{padding:4px 8px;border-radius:999px;background:#dbeafe;color:#1e3a8a;font-size:11px;font-weight:700}

    .saas-dashboard-hub{display:grid;gap:10px;margin-top:16px}
    .saas-dashboard-hub a{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 14px;border-radius:12px;border:1px solid #dbeafe;background:linear-gradient(180deg,#fff,#eff6ff);text-decoration:none;color:#0f172a}
    .saas-dashboard-hub a small{display:block;color:#64748b;margin-top:3px;line-height:1.4}

    .saas-dashboard-rule{border:1px solid #c7d2fe;background:linear-gradient(130deg,#eef2ff 0%,#f8fafc 100%);border-radius:14px;padding:14px}
    .saas-dashboard-rule h3{margin:0 0 8px;color:#1e1b4b;font-size:16px}
    .saas-dashboard-rule p{margin:0;color:#3730a3;font-size:13px;line-height:1.5}
    .saas-dashboard-rule ul{margin:10px 0 0;padding-left:18px;color:#312e81;font-size:13px;display:grid;gap:6px}

    @media (max-width:1180px){
        .saas-dashboard-layout{grid-template-columns:1fr}
    }
    @media (max-width:980px){
        .saas-dashboard-kpis,.saas-dashboard-chart-grid,.saas-dashboard-alert-grid,.saas-dashboard-columns,.saas-dashboard-meta{grid-template-columns:1fr 1fr}
        .saas-dashboard-filter.is-financial,.saas-dashboard-filter.is-support{grid-template-columns:1fr 1fr}
    }
    @media (max-width:760px){
        .saas-dashboard-hero h1{font-size:24px}
        .saas-dashboard-panel-head{grid-template-columns:1fr}
        .saas-dashboard-panel-actions{justify-items:start}
        .saas-dashboard-badges{justify-content:flex-start}
        .saas-dashboard-kpis,.saas-dashboard-chart-grid,.saas-dashboard-alert-grid,.saas-dashboard-columns,.saas-dashboard-meta,.saas-dashboard-filter.is-financial,.saas-dashboard-filter.is-support{grid-template-columns:1fr}
    }
</style>

<div class="saas-dashboard-page">
    <section class="saas-dashboard-hero">
        <div class="saas-dashboard-hero-body">
            <div class="saas-dashboard-hero-copy">
                <h1>Dashboard SaaS</h1>
                <p>O painel foi reorganizado para funcionar como centro de gestao do administrador. A pagina cruza base, recorrencia, financeiro e suporte no mesmo fluxo, sem excesso visual e sem blocos que nao agregam decisao.</p>
            </div>
            <div class="saas-dashboard-pills">
                <span class="saas-dashboard-pill">Empresas: <?= htmlspecialchars((string) ($overview['total_companies'] ?? 0)) ?></span>
                <span class="saas-dashboard-pill">Assinaturas ativas: <?= htmlspecialchars((string) ($overview['active_subscriptions'] ?? 0)) ?></span>
                <span class="saas-dashboard-pill">MRR ativo: <?= htmlspecialchars($formatMoney($overview['active_monthly_mrr'] ?? 0)) ?></span>
                <span class="saas-dashboard-pill">Chamados urgentes: <?= htmlspecialchars((string) ($overview['urgent_tickets'] ?? 0)) ?></span>
            </div>
        </div>
    </section>

    <div class="saas-dashboard-layout">
        <main class="saas-dashboard-main">
            <section class="card">
                <div class="saas-dashboard-panel-head">
                    <div class="saas-dashboard-panel-copy">
                        <h2>Visao executiva</h2>
                        <p class="saas-dashboard-panel-note">Os indicadores principais resumem tracao da base, recorrencia viva, risco financeiro e pressao operacional.</p>
                    </div>
                    <div class="saas-dashboard-badges">
                        <span class="badge">Gateway ativo: <?= htmlspecialchars((string) ($overview['gateway_bound_subscriptions'] ?? 0)) ?></span>
                        <span class="badge">Auto cobranca: <?= htmlspecialchars((string) ($overview['auto_charge_enabled'] ?? 0)) ?></span>
                    </div>
                </div>

                <div class="saas-dashboard-kpis">
                    <?php foreach ($executiveCards as $card): ?>
                        <article class="saas-dashboard-kpi tone-<?= htmlspecialchars((string) ($card['tone'] ?? 'info')) ?>">
                            <span><?= htmlspecialchars((string) ($card['label'] ?? 'Indicador')) ?></span>
                            <strong><?= htmlspecialchars((string) ($card['value'] ?? '0')) ?></strong>
                            <small><?= htmlspecialchars((string) ($card['note'] ?? '')) ?></small>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="card">
                <div class="saas-dashboard-panel-head">
                    <div class="saas-dashboard-panel-copy">
                        <h2>Leitura operacional</h2>
                        <p class="saas-dashboard-panel-note">Os graficos evitam analisar numero isolado como se fosse tendencia. O foco aqui e proporcao e pressao entre modulos.</p>
                    </div>
                    <div class="saas-dashboard-badges">
                        <span class="badge">Comparacao interna</span>
                        <span class="badge">Sem dependencia externa</span>
                    </div>
                </div>

                <div class="saas-dashboard-chart-grid">
                    <?php foreach ($chartGroups as $group): ?>
                        <article class="saas-dashboard-chart-card">
                            <div>
                                <h3><?= htmlspecialchars((string) ($group['title'] ?? 'Painel')) ?></h3>
                                <p><?= htmlspecialchars((string) ($group['description'] ?? '')) ?></p>
                            </div>
                            <div class="saas-dashboard-chart-stack">
                                <?php foreach (($group['items'] ?? []) as $item): ?>
                                    <div class="saas-dashboard-chart-row">
                                        <div class="saas-dashboard-chart-meta">
                                            <span><?= htmlspecialchars((string) ($item['label'] ?? 'Item')) ?></span>
                                            <span><?= htmlspecialchars((string) ($item['value'] ?? 0)) ?> (<?= number_format($chartPercent($item['value'] ?? 0, $group['total'] ?? 1), 1, ',', '.') ?>%)</span>
                                        </div>
                                        <div class="saas-dashboard-bar">
                                            <span class="saas-dashboard-bar-fill tone-<?= htmlspecialchars((string) ($item['tone'] ?? 'info')) ?>" style="width: <?= htmlspecialchars((string) $chartPercent($item['value'] ?? 0, $group['total'] ?? 1)) ?>%"></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="card">
                <div class="saas-dashboard-panel-head">
                    <div class="saas-dashboard-panel-copy">
                        <h2>Radar de prioridade</h2>
                        <p class="saas-dashboard-panel-note">Esses blocos puxam acao imediata. Quando um indicador sobe aqui, o impacto ja atravessou modulo e virou tema de gestao.</p>
                    </div>
                </div>

                <div class="saas-dashboard-alert-grid">
                    <?php foreach ($priorityCards as $priorityCard): ?>
                        <article class="saas-dashboard-alert tone-<?= htmlspecialchars((string) ($priorityCard['tone'] ?? 'info')) ?>">
                            <div class="saas-dashboard-alert-head">
                                <div>
                                    <span><?= htmlspecialchars((string) ($priorityCard['eyebrow'] ?? 'Painel')) ?></span>
                                    <strong><?= htmlspecialchars((string) ($priorityCard['headline'] ?? '-')) ?></strong>
                                </div>
                            </div>
                            <p><?= htmlspecialchars((string) ($priorityCard['copy'] ?? '')) ?></p>
                            <div class="saas-dashboard-actions">
                                <?php foreach (($priorityCard['actions'] ?? []) as $action): ?>
                                    <a class="btn<?= !empty($action['secondary']) ? ' secondary' : '' ?>" href="<?= htmlspecialchars((string) ($action['href'] ?? '#')) ?>">
                                        <?= htmlspecialchars((string) ($action['label'] ?? 'Abrir')) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="saas-dashboard-columns">
                <section class="card">
                    <div class="saas-dashboard-panel-head">
                        <div class="saas-dashboard-panel-copy">
                            <h2>Empresas recentes</h2>
                            <p class="saas-dashboard-panel-note">Entrada de novos clientes, status operacional e situacao contratual da base em formacao.</p>
                        </div>
                        <div class="saas-dashboard-panel-actions">
                            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/saas/companies')) ?>">Abrir empresas</a>
                        </div>
                    </div>

                    <div class="saas-dashboard-list">
                        <?php if ($companies === []): ?>
                            <div class="saas-dashboard-empty">Nenhuma empresa encontrada para exibir no painel.</div>
                        <?php endif; ?>

                        <?php foreach ($companies as $company): ?>
                            <article class="saas-dashboard-row">
                                <div class="saas-dashboard-row-head">
                                    <div class="saas-dashboard-row-copy">
                                        <strong><?= htmlspecialchars((string) ($company['name'] ?? 'Empresa')) ?></strong>
                                        <small><?= htmlspecialchars((string) ($company['slug'] ?? '-')) ?> - <?= htmlspecialchars((string) ($company['plan_name'] ?? 'Sem plano')) ?></small>
                                    </div>
                                    <span class="badge <?= htmlspecialchars(status_badge_class('company_subscription_status', $company['subscription_status'] ?? null)) ?>">
                                        <?= htmlspecialchars(status_label('company_subscription_status', $company['subscription_status'] ?? null)) ?>
                                    </span>
                                </div>
                                <div class="saas-dashboard-meta">
                                    <div class="saas-dashboard-meta-box">
                                        <span>Status operacional</span>
                                        <strong><?= htmlspecialchars(status_label('company_status', $company['status'] ?? null)) ?></strong>
                                    </div>
                                    <div class="saas-dashboard-meta-box">
                                        <span>Proxima cobranca</span>
                                        <strong><?= htmlspecialchars($formatDate($company['next_charge_due_date'] ?? null, false)) ?></strong>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="card">
                    <div class="saas-dashboard-panel-head">
                        <div class="saas-dashboard-panel-copy">
                            <h2>Assinaturas recentes</h2>
                            <p class="saas-dashboard-panel-note">A carteira precisa ser lida com contexto financeiro e operacional, nao apenas por volume.</p>
                        </div>
                        <div class="saas-dashboard-panel-actions">
                            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/saas/subscriptions')) ?>">Abrir assinaturas</a>
                        </div>
                    </div>

                    <div class="saas-dashboard-list">
                        <?php if ($subscriptions === []): ?>
                            <div class="saas-dashboard-empty">Nenhuma assinatura encontrada para exibir no painel.</div>
                        <?php endif; ?>

                        <?php foreach ($subscriptions as $subscription): ?>
                            <article class="saas-dashboard-row">
                                <div class="saas-dashboard-row-head">
                                    <div class="saas-dashboard-row-copy">
                                        <strong><?= htmlspecialchars((string) ($subscription['company_name'] ?? 'Empresa')) ?></strong>
                                        <small><?= htmlspecialchars((string) ($subscription['plan_name'] ?? 'Sem plano')) ?> - <?= htmlspecialchars(status_label('billing_cycle', $subscription['billing_cycle'] ?? null)) ?></small>
                                    </div>
                                    <span class="badge <?= htmlspecialchars(status_badge_class('subscription_status', $subscription['status'] ?? null)) ?>">
                                        <?= htmlspecialchars(status_label('subscription_status', $subscription['status'] ?? null)) ?>
                                    </span>
                                </div>
                                <div class="saas-dashboard-meta">
                                    <div class="saas-dashboard-meta-box">
                                        <span>Valor</span>
                                        <strong><?= htmlspecialchars($formatMoney($subscription['amount'] ?? 0)) ?></strong>
                                    </div>
                                    <div class="saas-dashboard-meta-box">
                                        <span>Gateway</span>
                                        <strong><?= htmlspecialchars($gatewayStatusLabel($subscription['gateway_status'] ?? null)) ?></strong>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <section class="card">
                <div class="saas-dashboard-panel-head">
                    <div class="saas-dashboard-panel-copy">
                        <h2>Fila financeira</h2>
                        <p class="saas-dashboard-panel-note">Esse card funciona como fila de trabalho: filtro proprio, leitura objetiva e paginacao de no maximo 10 registros.</p>
                    </div>
                    <div class="saas-dashboard-panel-actions">
                        <div class="saas-dashboard-badges">
                            <span class="badge">10 por pagina</span>
                            <span class="badge">Total filtrado: <?= htmlspecialchars((string) $dashboardPaymentTotal) ?></span>
                        </div>
                        <a class="btn secondary" href="<?= htmlspecialchars(base_url('/saas/subscription-payments')) ?>">Abrir cobrancas</a>
                    </div>
                </div>

                <form method="GET" action="<?= htmlspecialchars(base_url('/saas/dashboard')) ?>" class="saas-dashboard-filter is-financial">
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
                    <div class="saas-dashboard-filter-actions">
                        <input type="hidden" name="dashboard_payment_page" value="1">
                        <button class="btn" type="submit">Aplicar</button>
                        <a class="btn secondary" href="<?= htmlspecialchars($buildDashboardPaymentsUrl([
                            'dashboard_payment_search' => '',
                            'dashboard_payment_status' => '',
                            'dashboard_payment_page' => '',
                        ])) ?>">Limpar</a>
                    </div>
                </form>

                <div class="saas-dashboard-list">
                    <?php if ($payments === []): ?>
                        <div class="saas-dashboard-empty">Nenhuma cobranca encontrada para os filtros aplicados.</div>
                    <?php endif; ?>

                    <?php foreach ($payments as $payment): ?>
                        <article class="saas-dashboard-row">
                            <div class="saas-dashboard-row-head">
                                <div class="saas-dashboard-row-copy">
                                    <strong><?= htmlspecialchars((string) ($payment['company_name'] ?? 'Empresa')) ?></strong>
                                    <small><?= htmlspecialchars((string) ($payment['plan_name'] ?? 'Sem plano')) ?> - Ref. <?= str_pad((string) (int) ($payment['reference_month'] ?? 0), 2, '0', STR_PAD_LEFT) ?>/<?= (int) ($payment['reference_year'] ?? 0) ?></small>
                                </div>
                                <span class="badge <?= htmlspecialchars(status_badge_class('subscription_payment_status', $payment['status'] ?? null)) ?>">
                                    <?= htmlspecialchars(status_label('subscription_payment_status', $payment['status'] ?? null)) ?>
                                </span>
                            </div>
                            <div class="saas-dashboard-meta">
                                <div class="saas-dashboard-meta-box">
                                    <span>Valor</span>
                                    <strong><?= htmlspecialchars($formatMoney($payment['amount'] ?? 0)) ?></strong>
                                </div>
                                <div class="saas-dashboard-meta-box">
                                    <span>Vencimento</span>
                                    <strong><?= htmlspecialchars($formatDate($payment['due_date'] ?? null, false)) ?></strong>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($dashboardPaymentTotal > 0): ?>
                    <div class="saas-dashboard-pagination">
                        <div class="saas-dashboard-panel-note">
                            Exibindo <?= htmlspecialchars((string) $dashboardPaymentFrom) ?> a <?= htmlspecialchars((string) $dashboardPaymentTo) ?> de <?= htmlspecialchars((string) $dashboardPaymentTotal) ?> cobrancas filtradas.
                        </div>
                        <?php if ($dashboardPaymentLastPage > 1): ?>
                            <div class="saas-dashboard-pagination-controls">
                                <?php if ($dashboardPaymentPage > 1): ?>
                                    <a class="saas-dashboard-page-btn" href="<?= htmlspecialchars($buildDashboardPaymentsUrl(['dashboard_payment_page' => $dashboardPaymentPage - 1])) ?>">Anterior</a>
                                <?php endif; ?>

                                <?php
                                $lastRenderedPaymentPage = 0;
                                foreach ($dashboardPaymentPages as $pageNumber):
                                    $pageNumber = (int) $pageNumber;
                                    if ($lastRenderedPaymentPage > 0 && $pageNumber - $lastRenderedPaymentPage > 1): ?>
                                        <span class="saas-dashboard-page-ellipsis">...</span>
                                    <?php endif; ?>

                                    <a class="saas-dashboard-page-btn<?= $pageNumber === $dashboardPaymentPage ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildDashboardPaymentsUrl(['dashboard_payment_page' => $pageNumber])) ?>">
                                        <?= $pageNumber ?>
                                    </a>

                                    <?php $lastRenderedPaymentPage = $pageNumber; ?>
                                <?php endforeach; ?>

                                <?php if ($dashboardPaymentPage < $dashboardPaymentLastPage): ?>
                                    <a class="saas-dashboard-page-btn" href="<?= htmlspecialchars($buildDashboardPaymentsUrl(['dashboard_payment_page' => $dashboardPaymentPage + 1])) ?>">Proxima</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card">
                <div class="saas-dashboard-panel-head">
                    <div class="saas-dashboard-panel-copy">
                        <h2>Suporte em foco</h2>
                        <p class="saas-dashboard-panel-note">Chamados abertos dizem muito sobre qualidade operacional. Aqui a leitura precisa ser curta, filtravel e acionavel.</p>
                    </div>
                    <div class="saas-dashboard-panel-actions">
                        <div class="saas-dashboard-badges">
                            <span class="badge">10 por pagina</span>
                            <span class="badge">Total filtrado: <?= htmlspecialchars((string) $dashboardSupportTotal) ?></span>
                        </div>
                        <a class="btn secondary" href="<?= htmlspecialchars(base_url('/saas/support')) ?>">Abrir suporte</a>
                    </div>
                </div>

                <form method="GET" action="<?= htmlspecialchars(base_url('/saas/dashboard')) ?>" class="saas-dashboard-filter is-support">
                    <?php foreach ($currentQuery as $queryKey => $queryValue): ?>
                        <?php if (!in_array((string) $queryKey, ['dashboard_support_search', 'dashboard_support_status', 'dashboard_support_priority', 'dashboard_support_page'], true)): ?>
                            <input type="hidden" name="<?= htmlspecialchars((string) $queryKey) ?>" value="<?= htmlspecialchars((string) $queryValue) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <div class="field">
                        <label for="dashboard-support-search">Empresa</label>
                        <input
                            id="dashboard-support-search"
                            type="text"
                            name="dashboard_support_search"
                            value="<?= htmlspecialchars($dashboardSupportSearch) ?>"
                            placeholder="Empresa, slug, email ou ID"
                        >
                    </div>
                    <div class="field">
                        <label for="dashboard-support-status">Status</label>
                        <select id="dashboard-support-status" name="dashboard_support_status">
                            <?php foreach ($supportStatusOptions as $optionValue => $optionLabel): ?>
                                <option value="<?= htmlspecialchars($optionValue) ?>" <?= $dashboardSupportStatus === $optionValue ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($optionLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="dashboard-support-priority">Prioridade</label>
                        <select id="dashboard-support-priority" name="dashboard_support_priority">
                            <?php foreach ($supportPriorityOptions as $optionValue => $optionLabel): ?>
                                <option value="<?= htmlspecialchars($optionValue) ?>" <?= $dashboardSupportPriority === $optionValue ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($optionLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="saas-dashboard-filter-actions">
                        <input type="hidden" name="dashboard_support_page" value="1">
                        <button class="btn" type="submit">Aplicar</button>
                        <a class="btn secondary" href="<?= htmlspecialchars($buildDashboardSupportUrl([
                            'dashboard_support_search' => '',
                            'dashboard_support_status' => '',
                            'dashboard_support_priority' => '',
                            'dashboard_support_page' => '',
                        ])) ?>">Limpar</a>
                    </div>
                </form>

                <div class="saas-dashboard-list">
                    <?php if ($tickets === []): ?>
                        <div class="saas-dashboard-empty">Nenhum chamado encontrado para os filtros aplicados.</div>
                    <?php endif; ?>

                    <?php foreach ($tickets as $ticket): ?>
                        <article class="saas-dashboard-row">
                            <div class="saas-dashboard-row-head">
                                <div class="saas-dashboard-row-copy">
                                    <strong>#<?= (int) ($ticket['id'] ?? 0) ?> - <?= htmlspecialchars((string) ($ticket['subject'] ?? 'Chamado')) ?></strong>
                                    <small><?= htmlspecialchars((string) ($ticket['company_name'] ?? 'Empresa')) ?> - <?= htmlspecialchars((string) ($ticket['company_slug'] ?? '-')) ?></small>
                                </div>
                                <span class="badge <?= htmlspecialchars(status_badge_class('print_log_status', strtolower(trim((string) ($ticket['priority'] ?? ''))) === 'urgent' ? 'failed' : 'success')) ?>">
                                    <?= htmlspecialchars($supportPriorityLabel($ticket['priority'] ?? null)) ?>
                                </span>
                            </div>
                            <div class="saas-dashboard-meta">
                                <div class="saas-dashboard-meta-box">
                                    <span>Status</span>
                                    <strong><?= htmlspecialchars($supportStatusLabel($ticket['status'] ?? null)) ?></strong>
                                </div>
                                <div class="saas-dashboard-meta-box">
                                    <span>Atualizacao</span>
                                    <strong><?= htmlspecialchars($formatDate($ticket['updated_at'] ?? null)) ?></strong>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($dashboardSupportTotal > 0): ?>
                    <div class="saas-dashboard-pagination">
                        <div class="saas-dashboard-panel-note">
                            Exibindo <?= htmlspecialchars((string) $dashboardSupportFrom) ?> a <?= htmlspecialchars((string) $dashboardSupportTo) ?> de <?= htmlspecialchars((string) $dashboardSupportTotal) ?> chamados filtrados.
                        </div>
                        <?php if ($dashboardSupportLastPage > 1): ?>
                            <div class="saas-dashboard-pagination-controls">
                                <?php if ($dashboardSupportPage > 1): ?>
                                    <a class="saas-dashboard-page-btn" href="<?= htmlspecialchars($buildDashboardSupportUrl(['dashboard_support_page' => $dashboardSupportPage - 1])) ?>">Anterior</a>
                                <?php endif; ?>

                                <?php
                                $lastRenderedSupportPage = 0;
                                foreach ($dashboardSupportPages as $pageNumber):
                                    $pageNumber = (int) $pageNumber;
                                    if ($lastRenderedSupportPage > 0 && $pageNumber - $lastRenderedSupportPage > 1): ?>
                                        <span class="saas-dashboard-page-ellipsis">...</span>
                                    <?php endif; ?>

                                    <a class="saas-dashboard-page-btn<?= $pageNumber === $dashboardSupportPage ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildDashboardSupportUrl(['dashboard_support_page' => $pageNumber])) ?>">
                                        <?= $pageNumber ?>
                                    </a>

                                    <?php $lastRenderedSupportPage = $pageNumber; ?>
                                <?php endforeach; ?>

                                <?php if ($dashboardSupportPage < $dashboardSupportLastPage): ?>
                                    <a class="saas-dashboard-page-btn" href="<?= htmlspecialchars($buildDashboardSupportUrl(['dashboard_support_page' => $dashboardSupportPage + 1])) ?>">Proxima</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>

        <aside class="saas-dashboard-side">
            <section class="card">
                <div class="saas-dashboard-panel-head">
                    <div class="saas-dashboard-panel-copy">
                        <h3>Resumo gerencial</h3>
                        <p class="saas-dashboard-panel-note">Leitura rapida para entender onde o peso dominante esta hoje: base, recorrencia, financeiro ou atendimento.</p>
                    </div>
                </div>

                <div class="saas-dashboard-summary">
                    <?php foreach ($managementSummaryItems as $item): ?>
                        <div class="saas-dashboard-summary-item">
                            <strong><?= htmlspecialchars((string) ($item['label'] ?? 'Indicador')) ?></strong>
                            <span><?= htmlspecialchars((string) ($item['value'] ?? '0')) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="card">
                <div class="saas-dashboard-panel-head">
                    <div class="saas-dashboard-panel-copy">
                        <h3>Central de gestao</h3>
                        <p class="saas-dashboard-panel-note">Atalhos para os modulos que sustentam governanca, receita e operacao diaria do SaaS.</p>
                    </div>
                </div>

                <div class="saas-dashboard-hub">
                    <?php foreach ($hubLinks as $link): ?>
                        <a href="<?= htmlspecialchars((string) ($link['href'] ?? '#')) ?>">
                            <div>
                                <strong><?= htmlspecialchars((string) ($link['title'] ?? 'Modulo')) ?></strong>
                                <small><?= htmlspecialchars((string) ($link['copy'] ?? '')) ?></small>
                            </div>
                            <span>&gt;</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="card">
                <div class="saas-dashboard-panel-head">
                    <div class="saas-dashboard-panel-copy">
                        <h3>Planos em evidencia</h3>
                        <p class="saas-dashboard-panel-note">Visao rapida de quais ofertas sustentam a base atual do SaaS.</p>
                    </div>
                    <div class="saas-dashboard-panel-actions">
                        <a class="btn secondary" href="<?= htmlspecialchars(base_url('/saas/plans')) ?>">Abrir planos</a>
                    </div>
                </div>

                <div class="saas-dashboard-list">
                    <?php if ($plans === []): ?>
                        <div class="saas-dashboard-empty">Nenhum plano disponivel para destaque no painel.</div>
                    <?php endif; ?>

                    <?php foreach ($plans as $plan): ?>
                        <article class="saas-dashboard-row">
                            <div class="saas-dashboard-row-head">
                                <div class="saas-dashboard-row-copy">
                                    <strong><?= htmlspecialchars((string) ($plan['name'] ?? 'Plano')) ?></strong>
                                    <small><?= htmlspecialchars((string) ($plan['slug'] ?? '-')) ?></small>
                                </div>
                                <span class="badge <?= htmlspecialchars(status_badge_class('plan_status', $plan['status'] ?? null)) ?>">
                                    <?= htmlspecialchars(status_label('plan_status', $plan['status'] ?? null)) ?>
                                </span>
                            </div>
                            <div class="saas-dashboard-meta">
                                <div class="saas-dashboard-meta-box">
                                    <span>Empresas</span>
                                    <strong><?= (int) ($plan['linked_companies_count'] ?? 0) ?></strong>
                                </div>
                                <div class="saas-dashboard-meta-box">
                                    <span>Mensal</span>
                                    <strong><?= htmlspecialchars($formatMoney($plan['price_monthly'] ?? 0)) ?></strong>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="saas-dashboard-rule">
                <h3>Diretriz operacional</h3>
                <p>Um painel de gestao so e util quando mostra relacao entre modulos. Empresa, assinatura, cobranca e suporte nao podem ser lidos como telas isoladas.</p>
                <ul>
                    <?php foreach ($governanceNotes as $note): ?>
                        <li><?= htmlspecialchars((string) $note) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </aside>
    </div>
</div>
