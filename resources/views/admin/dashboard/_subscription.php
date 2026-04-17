<?php
$subscriptionModule = is_array($subscriptionModule ?? null) ? $subscriptionModule : [];
$subscription = is_array($subscriptionModule['subscription'] ?? null) ? $subscriptionModule['subscription'] : [];
$subscriptionSummary = is_array($subscriptionModule['summary'] ?? null) ? $subscriptionModule['summary'] : [];
$openSubscriptionPayments = is_array($subscriptionModule['open_payments'] ?? null) ? $subscriptionModule['open_payments'] : [];
$subscriptionHistory = is_array($subscriptionModule['payment_history'] ?? null) ? $subscriptionModule['payment_history'] : [];
$subscriptionHistoryFilters = is_array($subscriptionModule['history_filters'] ?? null) ? $subscriptionModule['history_filters'] : [];
$subscriptionHistoryPagination = is_array($subscriptionModule['history_pagination'] ?? null) ? $subscriptionModule['history_pagination'] : [];
$subscriptionFeatures = is_array($subscriptionModule['features'] ?? null) ? $subscriptionModule['features'] : [];
$subscriptionGateway = is_array($subscriptionModule['gateway'] ?? null) ? $subscriptionModule['gateway'] : [];

$currentSubscriptionQuery = is_array($_GET ?? null) ? $_GET : [];
$currentSubscriptionQuery['section'] = 'subscription';
$returnSubscriptionQuery = http_build_query($currentSubscriptionQuery);

$formatSubscriptionMoney = static fn (mixed $value): string => 'R$ ' . number_format((float) $value, 2, ',', '.');
$formatSubscriptionDate = static function (mixed $value, bool $withTime = false): string {
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

$paymentMethodLabels = [
    '' => 'Nao definido',
    'pix' => 'PIX manual',
    'credito' => 'Cartao de credito',
    'debito' => 'Cartao de debito',
];

$cardBrandOptions = [
    'Visa',
    'Mastercard',
    'Elo',
    'American Express',
    'Hipercard',
    'Cabal',
    'Outra',
];

$historyStatusOptions = [
    '' => 'Todos os status',
    'pendente' => 'Pendente',
    'pago' => 'Pago',
    'vencido' => 'Vencido',
    'cancelado' => 'Cancelado',
];

$historyMethodOptions = [
    '' => 'Todos os metodos',
    'pix' => 'PIX',
    'credito' => 'Cartao de credito',
    'debito' => 'Cartao de debito',
    'none' => 'Sem metodo',
];

$historySearch = trim((string) ($subscriptionHistoryFilters['search'] ?? ''));
$historyStatus = trim((string) ($subscriptionHistoryFilters['status'] ?? ''));
$historyMethod = trim((string) ($subscriptionHistoryFilters['method'] ?? ''));
$historyPage = max(1, (int) ($subscriptionHistoryPagination['page'] ?? 1));
$historyLastPage = max(1, (int) ($subscriptionHistoryPagination['last_page'] ?? 1));
$historyPages = is_array($subscriptionHistoryPagination['pages'] ?? null) ? $subscriptionHistoryPagination['pages'] : [1];

$buildSubscriptionUrl = static function (array $overrides = []) use ($historySearch, $historyStatus, $historyMethod): string {
    $params = array_merge([
        'section' => 'subscription',
        'subscription_history_search' => $historySearch,
        'subscription_history_status' => $historyStatus,
        'subscription_history_method' => $historyMethod,
    ], $overrides);

    foreach ($params as $key => $value) {
        if ($key !== 'section' && trim((string) $value) === '') {
            unset($params[$key]);
        }
    }

    return base_url('/admin/dashboard?' . http_build_query($params));
};
?>

<section class="dash-section<?= $activeSection === 'subscription' ? ' active' : '' ?>" data-section="subscription">
    <style>
        .sb-shell{display:grid;gap:14px}
        .sb-hero{border:1px solid #bfdbfe;background:linear-gradient(118deg,#111827 0%,#1d4ed8 54%,#38bdf8 100%);color:#fff;border-radius:14px;padding:16px;position:relative;overflow:hidden}
        .sb-hero:before{content:"";position:absolute;top:-54px;right:-46px;width:220px;height:220px;border-radius:999px;background:rgba(255,255,255,.12)}
        .sb-hero:after{content:"";position:absolute;bottom:-72px;left:-36px;width:190px;height:190px;border-radius:999px;background:rgba(255,255,255,.1)}
        .sb-hero-body{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
        .sb-hero h2{margin:0 0 8px;font-size:22px;color:#fff}
        .sb-hero p{margin:0;max-width:820px;line-height:1.45;color:#dbeafe}
        .sb-hero-metrics{display:flex;gap:8px;flex-wrap:wrap}
        .sb-hero-pill{border:1px solid rgba(255,255,255,.28);background:rgba(15,23,42,.34);border-radius:999px;padding:6px 11px;font-size:12px;font-weight:700;white-space:nowrap}

        .sb-layout{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(0,.95fr);gap:14px;align-items:start}
        .sb-main,.sb-side{display:grid;gap:14px}
        .sb-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
        .sb-card-head h3{margin:0;color:#0f172a}
        .sb-card-note{margin:4px 0 0;color:#475569;font-size:13px;line-height:1.45;max-width:760px}
        .sb-badges{display:flex;gap:6px;flex-wrap:wrap;align-items:center}

        .sb-summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
        .sb-summary-item{border:1px solid #dbeafe;border-radius:12px;background:linear-gradient(180deg,#ffffff 0%,#eff6ff 100%);padding:12px}
        .sb-summary-item span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
        .sb-summary-item strong{display:block;margin-top:6px;font-size:18px;color:#0f172a}
        .sb-summary-item small{display:block;margin-top:4px;color:#475569;font-size:12px;line-height:1.4}

        .sb-profile-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:10px}
        .sb-profile-box{border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;padding:12px;display:grid;gap:10px}
        .sb-profile-box h4{margin:0;color:#0f172a}
        .sb-profile-list{display:grid;gap:8px}
        .sb-profile-row{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:8px 0;border-bottom:1px dashed #cbd5e1}
        .sb-profile-row:last-child{border-bottom:none;padding-bottom:0}
        .sb-profile-row span{font-size:12px;color:#64748b}
        .sb-profile-row strong{font-size:13px;color:#0f172a;text-align:right}

        .sb-feature-list{display:flex;gap:6px;flex-wrap:wrap}
        .sb-feature-pill{padding:6px 10px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:12px;font-weight:700}
        .sb-resources-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
        .sb-resource-card{border:1px solid #dbeafe;border-radius:12px;background:linear-gradient(180deg,#ffffff 0%,#eff6ff 100%);padding:12px;display:grid;gap:8px}
        .sb-resource-card strong{font-size:13px;color:#0f172a}
        .sb-resource-card span{font-size:12px;color:#475569;line-height:1.45}

        .sb-charge-list{display:grid;gap:10px}
        .sb-charge-item{border:1px solid #dbeafe;border-radius:12px;background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%);padding:12px;display:grid;gap:12px}
        .sb-charge-top{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
        .sb-charge-title{display:grid;gap:4px}
        .sb-charge-title strong{font-size:15px;color:#0f172a}
        .sb-charge-title small{font-size:12px;color:#64748b;line-height:1.4}
        .sb-charge-info{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}
        .sb-charge-info-item{border:1px solid #e2e8f0;border-radius:10px;background:#fff;padding:10px}
        .sb-charge-info-item span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
        .sb-charge-info-item strong{display:block;margin-top:4px;font-size:13px;color:#0f172a}

        .sb-method-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        .sb-method-card{border:1px solid #e2e8f0;border-radius:12px;background:#fff;padding:12px;display:grid;gap:10px}
        .sb-method-card h4{margin:0;font-size:14px;color:#0f172a}
        .sb-method-card p{margin:0;color:#475569;font-size:12px;line-height:1.45}
        .sb-method-card .field{margin:0}
        .sb-method-card textarea{min-height:96px}
        .sb-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        .sb-form-grid .field{margin:0}
        .sb-form-grid .field.full{grid-column:1 / -1}
        .sb-form-footer{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
        .sb-form-note{font-size:12px;color:#64748b;line-height:1.45;max-width:640px}
        .sb-qr-wrap{display:grid;grid-template-columns:180px minmax(0,1fr);gap:12px;align-items:start;margin-top:10px}
        .sb-qr-image{border:1px solid #dbeafe;border-radius:12px;background:#fff;padding:10px;display:grid;place-items:center;min-height:180px}
        .sb-qr-image img{display:block;max-width:100%;height:auto}
        .sb-qr-data{display:grid;gap:10px}
        .sb-gateway-box{border:1px solid #c7d2fe;border-radius:12px;background:linear-gradient(180deg,#eef2ff 0%,#f8fafc 100%);padding:12px;display:grid;gap:10px}
        .sb-gateway-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
        .sb-filter-grid{display:grid;grid-template-columns:1.6fr 1fr 1fr auto;gap:10px;align-items:end}
        .sb-filter-grid .field{margin:0}
        .sb-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px}
        .sb-pagination-controls{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        .sb-page-info{font-size:12px;color:#475569}
        .sb-page-btn{border:1px solid #cbd5e1;background:#fff;color:#0f172a;border-radius:8px;padding:7px 10px;text-decoration:none;min-width:36px;text-align:center}
        .sb-page-btn.is-active{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
        .sb-page-btn.is-disabled{pointer-events:none;opacity:.45}

        .sb-history-table{width:100%;border-collapse:collapse}
        .sb-history-table th,.sb-history-table td{padding:10px;border-bottom:1px solid #e2e8f0;font-size:13px;text-align:left;vertical-align:top}
        .sb-history-table th{font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.04em}

        .sb-governance{border:1px solid #c7d2fe;background:linear-gradient(130deg,#eef2ff 0%,#f8fafc 100%);border-radius:14px;padding:14px}
        .sb-governance h4{margin:0 0 8px;color:#1e1b4b}
        .sb-governance p{margin:0;color:#3730a3;font-size:13px;line-height:1.5}
        .sb-governance-list{display:grid;gap:8px;margin-top:10px}
        .sb-governance-item{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:8px 10px;border-radius:10px;background:#fff;border:1px solid #c7d2fe;font-size:13px}
        .sb-governance-item strong{color:#312e81}
        .sb-governance-badge{padding:4px 8px;border-radius:999px;background:#e0e7ff;color:#312e81;font-size:11px;font-weight:600}

        @media (max-width:1180px){
            .sb-layout{grid-template-columns:1fr}
        }
        @media (max-width:900px){
            .sb-summary-grid,.sb-charge-info,.sb-profile-grid,.sb-method-grid,.sb-resources-grid{grid-template-columns:1fr 1fr}
        }
        @media (max-width:760px){
            .sb-summary-grid,.sb-charge-info,.sb-profile-grid,.sb-method-grid,.sb-form-grid,.sb-resources-grid,.sb-qr-wrap,.sb-filter-grid{grid-template-columns:1fr}
        }
    </style>

    <div class="sb-shell">
        <div class="sb-hero">
            <div class="sb-hero-body">
                <div>
                    <h2>Assinatura do plano</h2>
                    <p>Administrador e gerente acompanham a situacao da assinatura no proprio dashboard, com baixa manual em PIX, cobranca por cartao e recorrencia automatica para credito ou debito.</p>
                </div>
                <div class="sb-hero-metrics">
                    <span class="sb-hero-pill">Plano: <?= htmlspecialchars((string) ($subscriptionSummary['plan_name'] ?? 'Sem plano')) ?></span>
                    <span class="sb-hero-pill">Em aberto: <?= htmlspecialchars((string) ($subscriptionSummary['open_count'] ?? 0)) ?></span>
                    <span class="sb-hero-pill">Vencidas: <?= htmlspecialchars((string) ($subscriptionSummary['overdue_count'] ?? 0)) ?></span>
                    <span class="sb-hero-pill">Ciclo: <?= htmlspecialchars(status_label('billing_cycle', (string) ($subscriptionSummary['billing_cycle'] ?? ''))) ?></span>
                </div>
            </div>
        </div>

        <?php if ($subscription === []): ?>
            <div class="card">
                <div class="empty-state">
                    Nenhuma assinatura ativa foi localizada para esta empresa. Cadastre ou vincule um plano no ambiente SaaS para liberar o acompanhamento financeiro aqui.
                </div>
            </div>
        <?php else: ?>
            <div class="sb-summary-grid">
                <div class="sb-summary-item">
                    <span>Status da assinatura</span>
                    <strong><?= htmlspecialchars(status_label('subscription_status', (string) ($subscription['status'] ?? ''))) ?></strong>
                    <small>Snapshot da empresa: <?= htmlspecialchars(status_label('company_subscription_status', (string) ($subscription['company_subscription_status'] ?? ''))) ?></small>
                </div>
                <div class="sb-summary-item">
                    <span>Valor contratado</span>
                    <strong><?= htmlspecialchars($formatSubscriptionMoney($subscription['amount'] ?? 0)) ?></strong>
                    <small><?= htmlspecialchars(status_label('billing_cycle', (string) ($subscription['billing_cycle'] ?? ''))) ?> da assinatura atual</small>
                </div>
                <div class="sb-summary-item">
                    <span>Proximo vencimento</span>
                    <strong><?= htmlspecialchars($formatSubscriptionDate($subscriptionSummary['next_due_date'] ?? null)) ?></strong>
                    <small>Cobrancas em aberto: <?= htmlspecialchars($formatSubscriptionMoney($subscriptionSummary['open_amount'] ?? 0)) ?></small>
                </div>
                <div class="sb-summary-item">
                    <span>Historico pago</span>
                    <strong><?= htmlspecialchars($formatSubscriptionMoney($subscriptionSummary['paid_amount'] ?? 0)) ?></strong>
                    <small>Registros processados: <?= htmlspecialchars((string) ($subscriptionSummary['total_history'] ?? 0)) ?></small>
                </div>
            </div>

            <div class="sb-layout">
                <div class="sb-main">
                    <div class="card">
                        <div class="sb-card-head">
                            <div>
                                <h3>Painel de cobranca</h3>
                                <p class="sb-card-note">Cada competencia permanece no mesmo historico da assinatura. PIX continua manual, enquanto cartao de credito ou debito ativa a recorrencia automatica das proximas parcelas.</p>
                            </div>
                            <div class="sb-badges">
                                <span class="badge <?= htmlspecialchars(status_badge_class('subscription_status', (string) ($subscription['status'] ?? ''))) ?>">
                                    <?= htmlspecialchars(status_label('subscription_status', (string) ($subscription['status'] ?? ''))) ?>
                                </span>
                                <span class="badge status-default"><?= htmlspecialchars($paymentMethodLabels[(string) ($subscription['preferred_payment_method'] ?? '')] ?? 'Nao definido') ?></span>
                                <?php if (!empty($subscription['auto_charge_enabled'])): ?>
                                    <span class="badge status-paid">Recorrencia automatica ativa</span>
                                <?php else: ?>
                                    <span class="badge status-default">Recorrencia automatica inativa</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="sb-profile-grid" style="margin-top:12px">
                            <div class="sb-profile-box">
                                <h4>Perfil atual da assinatura</h4>
                                <div class="sb-profile-list">
                                    <div class="sb-profile-row">
                                        <span>Plano comercial</span>
                                        <strong><?= htmlspecialchars((string) ($subscription['plan_name'] ?? '-')) ?></strong>
                                    </div>
                                    <div class="sb-profile-row">
                                        <span>Ciclo contratado</span>
                                        <strong><?= htmlspecialchars(status_label('billing_cycle', (string) ($subscription['billing_cycle'] ?? ''))) ?></strong>
                                    </div>
                                    <div class="sb-profile-row">
                                        <span>Inicio da assinatura</span>
                                        <strong><?= htmlspecialchars($formatSubscriptionDate($subscription['starts_at'] ?? null)) ?></strong>
                                    </div>
                                    <div class="sb-profile-row">
                                        <span>Metodo preferencial</span>
                                        <strong><?= htmlspecialchars($paymentMethodLabels[(string) ($subscription['preferred_payment_method'] ?? '')] ?? 'Nao definido') ?></strong>
                                    </div>
                                    <div class="sb-profile-row">
                                        <span>Cartao salvo</span>
                                        <strong>
                                            <?php if (trim((string) ($subscription['card_last_digits'] ?? '')) !== ''): ?>
                                                <?= htmlspecialchars(trim((string) ($subscription['card_brand'] ?? 'Cartao')) . ' final ' . trim((string) ($subscription['card_last_digits'] ?? ''))) ?>
                                            <?php else: ?>
                                                Nao configurado
                                            <?php endif; ?>
                                        </strong>
                                    </div>
                                </div>

                                <?php if (!empty($subscription['auto_charge_enabled'])): ?>
                                    <form method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/subscription/auto-charge/disable')) ?>">
                                        <?= form_security_fields('dashboard.subscription.auto_charge.disable') ?>
                                        <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnSubscriptionQuery) ?>">
                                        <button class="btn secondary" type="submit">Desativar recorrencia automatica</button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <div class="sb-profile-box">
                                <h4>Recursos do plano</h4>
                                <div class="sb-resources-grid">
                                    <div class="sb-resource-card">
                                        <strong>Plano atual</strong>
                                        <span><?= htmlspecialchars((string) ($subscription['plan_name'] ?? 'Sem plano')) ?></span>
                                    </div>
                                    <div class="sb-resource-card">
                                        <strong>Ciclo e valor</strong>
                                        <span><?= htmlspecialchars(status_label('billing_cycle', (string) ($subscription['billing_cycle'] ?? ''))) ?> de <?= htmlspecialchars($formatSubscriptionMoney($subscription['amount'] ?? 0)) ?></span>
                                    </div>
                                    <div class="sb-resource-card">
                                        <strong>Recursos habilitados</strong>
                                        <span><?= htmlspecialchars((string) count($subscriptionFeatures)) ?> itens funcionais publicados</span>
                                    </div>
                                    <div class="sb-resource-card">
                                        <strong>Gateway recorrente</strong>
                                        <span><?= !empty($subscriptionGateway['configured']) ? htmlspecialchars((string) ($subscriptionGateway['provider'] ?? 'Gateway')) : 'Nao configurado no ambiente' ?></span>
                                    </div>
                                </div>

                                <?php if ($subscriptionFeatures !== []): ?>
                                    <div class="sb-feature-list">
                                        <?php foreach ($subscriptionFeatures as $feature): ?>
                                            <span class="sb-feature-pill"><?= htmlspecialchars((string) $feature) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="sb-card-note">O plano atual nao possui recursos detalhados publicados em `features_json`.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="sb-card-head">
                            <div>
                                <h3>Recorrencia real no gateway</h3>
                                <p class="sb-card-note">Para debito ou credito recorrente de verdade, o fluxo sai do formulario manual e passa para checkout autorizado do gateway. Depois da primeira autorizacao, as proximas cobrancas ficam automatizadas pelo provedor.</p>
                            </div>
                            <div class="sb-badges">
                                <?php if (!empty($subscriptionGateway['configured'])): ?>
                                    <span class="badge status-paid"><?= htmlspecialchars((string) ($subscriptionGateway['provider'] ?? 'Gateway')) ?> configurado</span>
                                <?php else: ?>
                                    <span class="badge status-overdue">Gateway nao configurado</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="sb-gateway-box" style="margin-top:12px">
                            <div class="sb-profile-list">
                                <div class="sb-profile-row">
                                    <span>Status externo</span>
                                    <strong><?= htmlspecialchars((string) ($subscription['gateway_status'] ?? 'Nao iniciado')) ?></strong>
                                </div>
                                <div class="sb-profile-row">
                                    <span>ID da assinatura externa</span>
                                    <strong><?= htmlspecialchars((string) ($subscription['gateway_subscription_id'] ?? '-')) ?></strong>
                                </div>
                                <div class="sb-profile-row">
                                    <span>Ultima sincronizacao</span>
                                    <strong><?= htmlspecialchars($formatSubscriptionDate($subscription['gateway_last_synced_at'] ?? null, true)) ?></strong>
                                </div>
                            </div>
                            <div class="sb-gateway-actions">
                                <form method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/subscription/gateway/checkout')) ?>">
                                    <?= form_security_fields('dashboard.subscription.gateway.checkout') ?>
                                    <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnSubscriptionQuery) ?>">
                                    <button class="btn" type="submit" <?= empty($subscriptionGateway['configured']) ? 'disabled' : '' ?>>Gerar link recorrente</button>
                                </form>
                                <?php if (trim((string) ($subscription['gateway_checkout_url'] ?? '')) !== ''): ?>
                                    <a class="btn secondary" href="<?= htmlspecialchars((string) ($subscription['gateway_checkout_url'] ?? '#')) ?>" target="_blank" rel="noopener">Abrir checkout do gateway</a>
                                <?php endif; ?>
                            </div>
                            <p class="sb-form-note">Baseado na documentacao oficial do Mercado Pago, a assinatura programatica usa `POST /preapproval`, devolve `init_point` para checkout e, apos a primeira autorizacao, as cobrancas futuras passam a ser automáticas pelo provedor.</p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="sb-card-head">
                            <div>
                                <h3>Cobrancas em aberto</h3>
                                <p class="sb-card-note">O sistema gera a competencia de acordo com o ciclo do plano. PIX exige confirmacao manual; cartao quita a parcela atual e passa a automatizar as proximas quando vencerem.</p>
                            </div>
                            <div class="sb-badges">
                                <span class="badge status-default">Total em aberto: <?= htmlspecialchars($formatSubscriptionMoney($subscriptionSummary['open_amount'] ?? 0)) ?></span>
                            </div>
                        </div>

                        <?php if ($openSubscriptionPayments === []): ?>
                            <div class="empty-state" style="margin-top:12px">
                                Nao ha cobrancas pendentes ou vencidas no momento.
                            </div>
                        <?php else: ?>
                            <div class="sb-charge-list" style="margin-top:12px">
                                <?php foreach ($openSubscriptionPayments as $payment): ?>
                                    <?php
                                    $paymentId = (int) ($payment['id'] ?? 0);
                                    $paymentStatus = (string) ($payment['status'] ?? '');
                                    $referenceLabel = sprintf('%02d/%04d', (int) ($payment['reference_month'] ?? 0), (int) ($payment['reference_year'] ?? 0));
                                    ?>
                                    <div class="sb-charge-item">
                                        <div class="sb-charge-top">
                                            <div class="sb-charge-title">
                                                <strong>Competencia <?= htmlspecialchars($referenceLabel) ?></strong>
                                                <small>Cobranca <?= htmlspecialchars((string) ($payment['charge_origin'] ?? 'manual')) ?> com vencimento em <?= htmlspecialchars($formatSubscriptionDate($payment['due_date'] ?? null)) ?>.</small>
                                            </div>
                                            <div class="sb-badges">
                                                <span class="badge <?= htmlspecialchars(status_badge_class('subscription_payment_status', $paymentStatus)) ?>">
                                                    <?= htmlspecialchars(status_label('subscription_payment_status', $paymentStatus)) ?>
                                                </span>
                                                <span class="badge status-default"><?= htmlspecialchars($formatSubscriptionMoney($payment['amount'] ?? 0)) ?></span>
                                            </div>
                                        </div>

                                        <div class="sb-charge-info">
                                            <div class="sb-charge-info-item">
                                                <span>Metodo sugerido</span>
                                                <strong><?= htmlspecialchars($paymentMethodLabels[(string) ($subscription['preferred_payment_method'] ?? '')] ?? 'PIX manual') ?></strong>
                                            </div>
                                            <div class="sb-charge-info-item">
                                                <span>Plano</span>
                                                <strong><?= htmlspecialchars((string) ($payment['plan_name'] ?? $subscription['plan_name'] ?? '-')) ?></strong>
                                            </div>
                                            <div class="sb-charge-info-item">
                                                <span>Vencimento</span>
                                                <strong><?= htmlspecialchars($formatSubscriptionDate($payment['due_date'] ?? null)) ?></strong>
                                            </div>
                                            <div class="sb-charge-info-item">
                                                <span>Pagamento registrado</span>
                                                <strong><?= htmlspecialchars($paymentMethodLabels[(string) ($payment['payment_method'] ?? '')] ?? 'Aguardando definicao') ?></strong>
                                            </div>
                                        </div>

                                        <div class="sb-method-grid">
                                            <div class="sb-method-card">
                                                <h4>PIX com QR code</h4>
                                                <p>O QR e o copia-e-cola podem ser gerados no gateway. Depois que a transferencia for concluida, a baixa ainda precisa ser confirmada no historico local se o webhook ainda nao tiver retornado.</p>
                                                <div class="sb-gateway-actions">
                                                    <form method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/subscription/pix/generate')) ?>">
                                                        <?= form_security_fields('dashboard.subscription.pix.generate.' . $paymentId) ?>
                                                        <input type="hidden" name="subscription_payment_id" value="<?= htmlspecialchars((string) $paymentId) ?>">
                                                        <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnSubscriptionQuery) ?>">
                                                        <button class="btn secondary" type="submit" <?= empty($subscriptionGateway['configured']) ? 'disabled' : '' ?>>Gerar QR PIX real</button>
                                                    </form>
                                                    <?php if (trim((string) ($payment['pix_ticket_url'] ?? '')) !== ''): ?>
                                                        <a class="btn secondary" href="<?= htmlspecialchars((string) ($payment['pix_ticket_url'] ?? '#')) ?>" target="_blank" rel="noopener">Abrir tela PIX</a>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (trim((string) ($payment['pix_qr_image_base64'] ?? '')) !== ''): ?>
                                                    <div class="sb-qr-wrap">
                                                        <div class="sb-qr-image">
                                                            <img src="data:image/png;base64,<?= htmlspecialchars((string) ($payment['pix_qr_image_base64'] ?? '')) ?>" alt="QR code PIX da cobranca">
                                                        </div>
                                                        <div class="sb-qr-data">
                                                            <div class="field">
                                                                <label>PIX copia e cola</label>
                                                                <textarea readonly><?= htmlspecialchars((string) ($payment['pix_code'] ?? '')) ?></textarea>
                                                            </div>
                                                            <?php if (trim((string) ($payment['pix_ticket_url'] ?? '')) !== ''): ?>
                                                                <div class="field">
                                                                    <label>Link hospedado no gateway</label>
                                                                    <input type="text" readonly value="<?= htmlspecialchars((string) ($payment['pix_ticket_url'] ?? '')) ?>">
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="field">
                                                        <label>PIX copia e cola</label>
                                                        <textarea readonly><?= htmlspecialchars((string) ($payment['pix_qr_payload'] ?? 'Gere o QR no gateway para liberar o codigo real.')) ?></textarea>
                                                    </div>
                                                <?php endif; ?>
                                                <form method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/subscription/pix')) ?>">
                                                    <?= form_security_fields('dashboard.subscription.pix.' . $paymentId) ?>
                                                    <input type="hidden" name="subscription_payment_id" value="<?= htmlspecialchars((string) $paymentId) ?>">
                                                    <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnSubscriptionQuery) ?>">
                                                    <div class="sb-form-grid">
                                                        <div class="field full">
                                                            <label for="subscription_pix_reference_<?= $paymentId ?>">Referencia da transferencia</label>
                                                            <input id="subscription_pix_reference_<?= $paymentId ?>" name="transaction_reference" type="text" maxlength="120" placeholder="Ex.: txid do banco ou comprovante interno">
                                                        </div>
                                                    </div>
                                                    <div class="sb-form-footer" style="margin-top:10px">
                                                        <p class="sb-form-note">Ao confirmar, a cobranca entra como paga e o metodo preferencial da assinatura volta para PIX manual.</p>
                                                        <button class="btn" type="submit">Confirmar PIX</button>
                                                    </div>
                                                </form>
                                            </div>

                                            <div class="sb-method-card">
                                                <h4>Baixa manual administrativa</h4>
                                                <p>Use apenas como contingencia interna. Para recorrencia real em credito ou debito, prefira o checkout do gateway na area acima.</p>
                                                <form method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/subscription/card')) ?>">
                                                    <?= form_security_fields('dashboard.subscription.card.' . $paymentId) ?>
                                                    <input type="hidden" name="subscription_payment_id" value="<?= htmlspecialchars((string) $paymentId) ?>">
                                                    <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnSubscriptionQuery) ?>">
                                                    <div class="sb-form-grid">
                                                        <div class="field">
                                                            <label for="subscription_card_method_<?= $paymentId ?>">Tipo de cartao</label>
                                                            <select id="subscription_card_method_<?= $paymentId ?>" name="payment_method" required>
                                                                <option value="credito">Credito</option>
                                                                <option value="debito">Debito</option>
                                                            </select>
                                                        </div>
                                                        <div class="field">
                                                            <label for="subscription_card_brand_<?= $paymentId ?>">Bandeira</label>
                                                            <select id="subscription_card_brand_<?= $paymentId ?>" name="card_brand" required>
                                                                <?php foreach ($cardBrandOptions as $brand): ?>
                                                                    <option value="<?= htmlspecialchars($brand) ?>"><?= htmlspecialchars($brand) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="field full">
                                                            <label for="subscription_card_last_digits_<?= $paymentId ?>">4 ultimos digitos</label>
                                                            <input id="subscription_card_last_digits_<?= $paymentId ?>" name="card_last_digits" type="text" inputmode="numeric" maxlength="4" pattern="\d{4}" required placeholder="0000">
                                                        </div>
                                                    </div>
                                                    <div class="sb-form-footer" style="margin-top:10px">
                                                        <p class="sb-form-note">Nao salve numero completo nem CVV. Apenas a bandeira e os 4 ultimos digitos ficam registrados para auditoria e conciliacao manual.</p>
                                                        <button class="btn" type="submit">Registrar baixa manual</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <div class="sb-card-head">
                            <div>
                                <h3>Historico da assinatura</h3>
                                <p class="sb-card-note">Todas as competencias ficam na mesma trilha da assinatura atual, com filtro por status, metodo e busca por referencia, origem, gateway ou PIX.</p>
                            </div>
                            <div class="sb-badges">
                                <span class="badge status-default">Pagina <?= htmlspecialchars((string) $historyPage) ?> de <?= htmlspecialchars((string) $historyLastPage) ?></span>
                                <span class="badge status-default">Total filtrado: <?= htmlspecialchars((string) ($subscriptionHistoryPagination['total'] ?? count($subscriptionHistory))) ?></span>
                            </div>
                        </div>

                        <form method="GET" action="<?= htmlspecialchars(base_url('/admin/dashboard')) ?>" style="margin-top:12px">
                            <input type="hidden" name="section" value="subscription">
                            <div class="sb-filter-grid">
                                <div class="field">
                                    <label for="subscription_history_search">Busca inteligente</label>
                                    <input id="subscription_history_search" name="subscription_history_search" type="text" value="<?= htmlspecialchars($historySearch) ?>" placeholder="Referencia, gateway, PIX, metodo ou origem">
                                </div>
                                <div class="field">
                                    <label for="subscription_history_status">Status</label>
                                    <select id="subscription_history_status" name="subscription_history_status">
                                        <?php foreach ($historyStatusOptions as $value => $label): ?>
                                            <option value="<?= htmlspecialchars($value) ?>" <?= $historyStatus === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="subscription_history_method">Metodo</label>
                                    <select id="subscription_history_method" name="subscription_history_method">
                                        <?php foreach ($historyMethodOptions as $value => $label): ?>
                                            <option value="<?= htmlspecialchars($value) ?>" <?= $historyMethod === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="sb-gateway-actions">
                                    <button class="btn" type="submit">Aplicar</button>
                                    <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/dashboard?section=subscription')) ?>">Limpar</a>
                                </div>
                            </div>
                        </form>

                        <?php if ($subscriptionHistory === []): ?>
                            <div class="empty-state" style="margin-top:12px">
                                Ainda nao existe historico financeiro registrado para esta assinatura.
                            </div>
                        <?php else: ?>
                            <div style="overflow:auto;margin-top:12px">
                                <table class="sb-history-table">
                                    <thead>
                                        <tr>
                                            <th>Competencia</th>
                                            <th>Vencimento</th>
                                            <th>Valor</th>
                                            <th>Status</th>
                                            <th>Metodo</th>
                                            <th>Pagamento</th>
                                            <th>Referencia</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($subscriptionHistory as $payment): ?>
                                            <tr>
                                                <td><?= htmlspecialchars(sprintf('%02d/%04d', (int) ($payment['reference_month'] ?? 0), (int) ($payment['reference_year'] ?? 0))) ?></td>
                                                <td><?= htmlspecialchars($formatSubscriptionDate($payment['due_date'] ?? null)) ?></td>
                                                <td><?= htmlspecialchars($formatSubscriptionMoney($payment['amount'] ?? 0)) ?></td>
                                                <td>
                                                    <span class="badge <?= htmlspecialchars(status_badge_class('subscription_payment_status', (string) ($payment['status'] ?? ''))) ?>">
                                                        <?= htmlspecialchars(status_label('subscription_payment_status', (string) ($payment['status'] ?? ''))) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($paymentMethodLabels[(string) ($payment['payment_method'] ?? '')] ?? 'Nao definido') ?></td>
                                                <td><?= htmlspecialchars($formatSubscriptionDate($payment['paid_at'] ?? null, true)) ?></td>
                                                <td><?= htmlspecialchars((string) ($payment['transaction_reference'] ?? '-')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="sb-pagination">
                                <div class="sb-page-info">
                                    <?php if ((int) ($subscriptionHistoryPagination['total'] ?? 0) > 0): ?>
                                        Exibindo <?= htmlspecialchars((string) ($subscriptionHistoryPagination['from'] ?? 0)) ?>-<?= htmlspecialchars((string) ($subscriptionHistoryPagination['to'] ?? 0)) ?> de <?= htmlspecialchars((string) ($subscriptionHistoryPagination['total'] ?? 0)) ?> registros
                                    <?php else: ?>
                                        Nenhum registro encontrado
                                    <?php endif; ?>
                                </div>
                                <div class="sb-pagination-controls">
                                    <a class="sb-page-btn<?= $historyPage <= 1 ? ' is-disabled' : '' ?>" href="<?= htmlspecialchars($buildSubscriptionUrl(['subscription_history_page' => max(1, $historyPage - 1)])) ?>">Anterior</a>
                                    <?php foreach ($historyPages as $pageNumber): ?>
                                        <a class="sb-page-btn<?= $pageNumber === $historyPage ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildSubscriptionUrl(['subscription_history_page' => $pageNumber])) ?>"><?= htmlspecialchars((string) $pageNumber) ?></a>
                                    <?php endforeach; ?>
                                    <a class="sb-page-btn<?= $historyPage >= $historyLastPage ? ' is-disabled' : '' ?>" href="<?= htmlspecialchars($buildSubscriptionUrl(['subscription_history_page' => min($historyLastPage, $historyPage + 1)])) ?>">Proxima</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sb-side">
                    <div class="card">
                        <div class="sb-card-head">
                            <div>
                                <h3>Gestao da assinatura</h3>
                                <p class="sb-card-note">Resumo rapido para acompanhamento executivo da saude financeira do plano.</p>
                            </div>
                        </div>

                        <div class="sb-profile-list" style="margin-top:12px">
                            <div class="sb-profile-row">
                                <span>Status atual</span>
                                <strong><?= htmlspecialchars(status_label('subscription_status', (string) ($subscription['status'] ?? ''))) ?></strong>
                            </div>
                            <div class="sb-profile-row">
                                <span>Snapshot da empresa</span>
                                <strong><?= htmlspecialchars(status_label('company_subscription_status', (string) ($subscription['company_subscription_status'] ?? ''))) ?></strong>
                            </div>
                            <div class="sb-profile-row">
                                <span>Em aberto</span>
                                <strong><?= htmlspecialchars((string) ($subscriptionSummary['open_count'] ?? 0)) ?> cobrancas</strong>
                            </div>
                            <div class="sb-profile-row">
                                <span>Vencidas</span>
                                <strong><?= htmlspecialchars((string) ($subscriptionSummary['overdue_count'] ?? 0)) ?> cobrancas</strong>
                            </div>
                            <div class="sb-profile-row">
                                <span>Valor em aberto</span>
                                <strong><?= htmlspecialchars($formatSubscriptionMoney($subscriptionSummary['open_amount'] ?? 0)) ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="sb-governance">
                        <h4>Regras operacionais</h4>
                        <p>O dashboard aplica a regra de negocio da assinatura sem apagar historico. O que muda aqui reflete a situacao financeira da empresa e o snapshot exibido no SaaS.</p>
                        <div class="sb-governance-list">
                            <div class="sb-governance-item">
                                <strong>PIX manual</strong>
                                <span class="sb-governance-badge">gera por competencia</span>
                            </div>
                            <div class="sb-governance-item">
                                <strong>Credito e debito</strong>
                                <span class="sb-governance-badge">ativam recorrencia</span>
                            </div>
                            <div class="sb-governance-item">
                                <strong>Ciclo mensal</strong>
                                <span class="sb-governance-badge">1 cobranca por mes</span>
                            </div>
                            <div class="sb-governance-item">
                                <strong>Ciclo anual</strong>
                                <span class="sb-governance-badge">1 cobranca por renovacao</span>
                            </div>
                            <div class="sb-governance-item">
                                <strong>Cobranca vencida</strong>
                                <span class="sb-governance-badge">empresa inadimplente</span>
                            </div>
                            <div class="sb-governance-item">
                                <strong>Cobranca paga</strong>
                                <span class="sb-governance-badge">assinatura volta a ativa</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
