<?php
$subscriptionPanel = is_array($subscriptionPanel ?? null) ? $subscriptionPanel : [];
$subscriptions = is_array($subscriptionPanel['subscriptions'] ?? null) ? $subscriptionPanel['subscriptions'] : [];
$filters = is_array($subscriptionPanel['filters'] ?? null) ? $subscriptionPanel['filters'] : [];
$summary = is_array($subscriptionPanel['summary'] ?? null) ? $subscriptionPanel['summary'] : [];
$pagination = is_array($subscriptionPanel['pagination'] ?? null) ? $subscriptionPanel['pagination'] : [];

$search = trim((string) ($filters['search'] ?? ''));
$status = trim((string) ($filters['status'] ?? ''));

$statusOptions = [
    '' => 'Todos os status',
    'ativa' => 'Ativas',
    'trial' => 'Trial',
    'vencida' => 'Vencidas',
    'cancelada' => 'Canceladas',
];

$totalSubscriptions = (int) ($summary['total_subscriptions'] ?? ($pagination['total'] ?? count($subscriptions)));
$activeSubscriptions = (int) ($summary['active_subscriptions'] ?? 0);
$trialSubscriptions = (int) ($summary['trial_subscriptions'] ?? 0);
$expiredSubscriptions = (int) ($summary['expired_subscriptions'] ?? 0);
$canceledSubscriptions = (int) ($summary['canceled_subscriptions'] ?? 0);
$activeMonthlyMrr = (float) ($summary['active_monthly_mrr'] ?? 0);
$autoChargeEnabled = (int) ($summary['auto_charge_enabled'] ?? 0);
$gatewayBound = (int) ($summary['gateway_bound'] ?? 0);

$subscriptionPage = max(1, (int) ($pagination['page'] ?? 1));
$subscriptionLastPage = max(1, (int) ($pagination['last_page'] ?? 1));
$subscriptionFrom = (int) ($pagination['from'] ?? 0);
$subscriptionTo = (int) ($pagination['to'] ?? 0);
$subscriptionPages = is_array($pagination['pages'] ?? null) ? $pagination['pages'] : [];

$buildSubscriptionsUrl = static function (array $overrides = []) use ($search, $status): string {
    $params = array_merge([
        'search' => $search,
        'status' => $status,
    ], $overrides);

    foreach ($params as $key => $value) {
        if (trim((string) $value) === '') {
            unset($params[$key]);
        }
    }

    $query = http_build_query($params);
    return base_url('/saas/subscriptions' . ($query !== '' ? '?' . $query : ''));
};

$formatMoney = static fn (mixed $value): string => 'R$ ' . number_format((float) $value, 2, ',', '.');

$formatDate = static function (mixed $value, bool $withTime = false): string {
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

$paymentMethodLabel = static function (mixed $value): string {
    $raw = strtolower(trim((string) ($value ?? '')));

    return match ($raw) {
        'pix' => 'PIX',
        'card', 'credit_card' => 'Cartao',
        'boleto' => 'Boleto',
        '' => 'Nao definido',
        default => ucfirst(str_replace(['_', '-'], ' ', $raw)),
    };
};
?>

<style>
    .saas-billing-page{display:grid;gap:16px}
    .saas-billing-hero{border:1px solid #c7d2fe;background:linear-gradient(125deg,#0f172a 0%,#0f766e 45%,#1d4ed8 100%);color:#fff;border-radius:18px;padding:20px;position:relative;overflow:hidden}
    .saas-billing-hero:before{content:"";position:absolute;top:-46px;right:-28px;width:190px;height:190px;border-radius:999px;background:rgba(255,255,255,.12)}
    .saas-billing-hero:after{content:"";position:absolute;bottom:-70px;left:-34px;width:170px;height:170px;border-radius:999px;background:rgba(255,255,255,.08)}
    .saas-billing-hero-body{position:relative;z-index:1;display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
    .saas-billing-hero h1{margin:0 0 8px;font-size:28px}
    .saas-billing-hero p{margin:0;max-width:840px;color:#dbeafe;line-height:1.55}
    .saas-billing-pills{display:flex;gap:8px;flex-wrap:wrap}
    .saas-billing-pill{border:1px solid rgba(255,255,255,.24);background:rgba(15,23,42,.35);padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700}

    .saas-billing-layout{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(300px,.9fr);gap:16px;align-items:start}
    .saas-billing-main,.saas-billing-side{display:grid;gap:16px}

    .saas-billing-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
    .saas-billing-head h2,.saas-billing-head h3{margin:0}
    .saas-billing-note{margin:4px 0 0;color:#64748b;font-size:13px;line-height:1.45}
    .saas-billing-badges{display:flex;gap:8px;flex-wrap:wrap}

    .saas-billing-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
    .saas-billing-kpi{border:1px solid #dbeafe;border-radius:14px;background:linear-gradient(180deg,#fff,#eff6ff);padding:14px}
    .saas-billing-kpi span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
    .saas-billing-kpi strong{display:block;margin-top:6px;font-size:24px;color:#0f172a}
    .saas-billing-kpi small{display:block;margin-top:4px;color:#475569}

    .saas-billing-filter-grid{display:grid;grid-template-columns:1.5fr 1fr auto;gap:10px;align-items:end}
    .saas-billing-filter-grid .field{margin:0}
    .saas-billing-filter-actions{display:flex;gap:8px;flex-wrap:wrap}

    .saas-billing-table{display:grid;gap:10px}
    .saas-billing-row{border:1px solid #dbeafe;border-radius:14px;background:linear-gradient(180deg,#fff,#f8fafc);overflow:hidden}
    .saas-billing-row-head{display:grid;grid-template-columns:minmax(220px,1.3fr) repeat(5,minmax(110px,.8fr)) auto;gap:10px;align-items:center;padding:14px}
    .saas-billing-col span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
    .saas-billing-col strong{display:block;margin-top:4px;font-size:13px;color:#0f172a;overflow-wrap:anywhere}
    .saas-billing-company strong{font-size:15px}
    .saas-billing-company small{display:block;margin-top:4px;color:#64748b;line-height:1.35}
    .saas-billing-flags{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
    .saas-billing-mode{display:inline-flex;align-items:center;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:700}
    .saas-billing-mode.auto{background:#dcfce7;color:#166534}
    .saas-billing-mode.manual{background:#fef3c7;color:#92400e}

    .saas-billing-details{border-top:1px dashed #cbd5e1;padding:0 14px 14px}
    .saas-billing-details summary{display:flex;justify-content:space-between;align-items:center;gap:10px;cursor:pointer;list-style:none;padding-top:12px;font-weight:700;color:#0f172a}
    .saas-billing-details summary::-webkit-details-marker{display:none}
    .saas-billing-details-toggle{font-size:11px;color:#1d4ed8;background:#dbeafe;border:1px solid #bfdbfe;border-radius:999px;padding:4px 9px;font-weight:700}
    .saas-billing-details-body{display:grid;gap:12px;margin-top:12px}
    .saas-billing-details-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}
    .saas-billing-box{border:1px solid #e2e8f0;background:#fff;border-radius:10px;padding:10px}
    .saas-billing-box span{display:block;font-size:11px;text-transform:uppercase;color:#64748b}
    .saas-billing-box strong{display:block;margin-top:4px;color:#0f172a;overflow-wrap:anywhere}
    .saas-billing-callout{border-radius:12px;padding:12px;font-size:13px;line-height:1.5}
    .saas-billing-callout.auto{border:1px solid #bbf7d0;background:#f0fdf4;color:#166534}
    .saas-billing-callout.manual{border:1px solid #fde68a;background:#fffbeb;color:#92400e}
    .saas-billing-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:end}

    .saas-billing-summary-grid{display:grid;gap:8px}
    .saas-billing-summary-item{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px}
    .saas-billing-summary-item strong{color:#0f172a}
    .saas-billing-summary-item span{padding:4px 8px;border-radius:999px;background:#dbeafe;color:#1e3a8a;font-size:11px;font-weight:700}

    .saas-billing-flow{border:1px solid #c7d2fe;background:linear-gradient(130deg,#eef2ff 0%,#f8fafc 100%);border-radius:14px;padding:14px}
    .saas-billing-flow h3{margin:0 0 8px;color:#1e1b4b;font-size:16px}
    .saas-billing-flow p{margin:0;color:#3730a3;font-size:13px;line-height:1.5}
    .saas-billing-flow ul{margin:10px 0 0;padding-left:18px;color:#312e81;font-size:13px;display:grid;gap:6px}

    .saas-billing-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
    .saas-billing-pagination-controls{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .saas-page-btn{display:inline-block;padding:8px 11px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#0f172a;text-decoration:none}
    .saas-page-btn.is-active{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
    .saas-page-ellipsis{color:#64748b;padding:0 2px}

    @media (max-width:1280px){
        .saas-billing-row-head{grid-template-columns:minmax(220px,1.4fr) repeat(3,minmax(110px,.8fr)) auto}
        .saas-billing-row-head .saas-billing-col.hide-md{display:none}
    }
    @media (max-width:1180px){
        .saas-billing-layout{grid-template-columns:1fr}
    }
    @media (max-width:980px){
        .saas-billing-kpis,.saas-billing-filter-grid,.saas-billing-details-grid{grid-template-columns:1fr 1fr}
        .saas-billing-row-head{grid-template-columns:1fr;align-items:flex-start}
        .saas-billing-flags{justify-content:flex-start}
        .saas-billing-row-head .saas-billing-col.hide-mobile{display:none}
    }
    @media (max-width:760px){
        .saas-billing-kpis,.saas-billing-filter-grid,.saas-billing-details-grid{grid-template-columns:1fr}
        .saas-billing-hero h1{font-size:22px}
    }
</style>

<div class="saas-billing-page">
    <div class="saas-billing-hero">
        <div class="saas-billing-hero-body">
            <div>
                <h1>Assinaturas</h1>
                <p>O painel de assinaturas agora usa a mesma leitura operacional de cobrancas: visao executiva no topo, fila filtravel no centro e detalhes expansiveis so quando a decisao realmente exige contexto.</p>
            </div>
            <div class="saas-billing-pills">
                <span class="saas-billing-pill">Assinaturas filtradas: <?= htmlspecialchars((string) $totalSubscriptions) ?></span>
                <span class="saas-billing-pill">Ativas: <?= htmlspecialchars((string) $activeSubscriptions) ?></span>
                <span class="saas-billing-pill">Trial: <?= htmlspecialchars((string) $trialSubscriptions) ?></span>
                <span class="saas-billing-pill">MRR ativo: <?= htmlspecialchars($formatMoney($activeMonthlyMrr)) ?></span>
            </div>
        </div>
    </div>

    <div class="saas-billing-layout">
        <div class="saas-billing-main">
            <section class="card">
                <div class="saas-billing-head">
                    <div>
                        <h2>Fila de assinaturas</h2>
                        <p class="saas-billing-note">A lista agora deixa claro status contratual, vinculo com gateway e capacidade de cobranca automatica antes de abrir qualquer detalhe operacional.</p>
                    </div>
                    <div class="saas-billing-badges">
                        <span class="badge">Com gateway: <?= htmlspecialchars((string) $gatewayBound) ?></span>
                        <span class="badge">Auto cobranca: <?= htmlspecialchars((string) $autoChargeEnabled) ?></span>
                    </div>
                </div>

                <div class="saas-billing-kpis" style="margin-top:16px">
                    <div class="saas-billing-kpi">
                        <span>Total</span>
                        <strong><?= htmlspecialchars((string) $totalSubscriptions) ?></strong>
                        <small>Base filtrada no momento</small>
                    </div>
                    <div class="saas-billing-kpi">
                        <span>Ativas</span>
                        <strong><?= htmlspecialchars((string) $activeSubscriptions) ?></strong>
                        <small>Operando normalmente</small>
                    </div>
                    <div class="saas-billing-kpi">
                        <span>Vencidas</span>
                        <strong><?= htmlspecialchars((string) $expiredSubscriptions) ?></strong>
                        <small>Exigem acao comercial</small>
                    </div>
                    <div class="saas-billing-kpi">
                        <span>MRR ativo</span>
                        <strong><?= htmlspecialchars($formatMoney($activeMonthlyMrr)) ?></strong>
                        <small>Somente ciclos mensais ativos</small>
                    </div>
                </div>

                <form method="GET" action="<?= htmlspecialchars(base_url('/saas/subscriptions')) ?>" style="margin-top:16px">
                    <div class="saas-billing-filter-grid">
                        <div class="field">
                            <label for="saas_subscription_search">Busca inteligente</label>
                            <input id="saas_subscription_search" name="search" type="text" value="<?= htmlspecialchars($search) ?>" placeholder="Empresa, slug, plano, e-mail ou gateway">
                        </div>
                        <div class="field">
                            <label for="saas_subscription_status">Status</label>
                            <select id="saas_subscription_status" name="status">
                                <?php foreach ($statusOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= $status === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="saas-billing-filter-actions">
                            <button class="btn" type="submit">Aplicar</button>
                            <a class="btn secondary" href="<?= htmlspecialchars($buildSubscriptionsUrl(['search' => '', 'status' => '', 'subscription_page' => ''])) ?>">Limpar</a>
                        </div>
                    </div>
                </form>

                <?php if ($subscriptions === []): ?>
                    <div class="card" style="margin-top:16px;padding:14px;border:1px dashed #cbd5e1;box-shadow:none">
                        <?= ($search !== '' || $status !== '')
                            ? 'Nenhuma assinatura encontrada para os filtros aplicados.'
                            : 'Nenhuma assinatura cadastrada ate o momento.' ?>
                    </div>
                <?php else: ?>
                    <div class="saas-billing-table" style="margin-top:16px">
                        <?php foreach ($subscriptions as $subscription): ?>
                            <?php
                            $subscriptionStatus = trim((string) ($subscription['status'] ?? ''));
                            $hasGatewayBinding = trim((string) ($subscription['gateway_subscription_id'] ?? '')) !== ''
                                || trim((string) ($subscription['gateway_checkout_url'] ?? '')) !== '';
                            $autoCharge = (int) ($subscription['auto_charge_enabled'] ?? 0) === 1;
                            $modeClass = ($hasGatewayBinding && $autoCharge) ? 'auto' : 'manual';
                            $chargeSearchUrl = base_url('/saas/subscription-payments?search=' . rawurlencode((string) ($subscription['company_slug'] ?? '')));
                            ?>
                            <article class="saas-billing-row">
                                <div class="saas-billing-row-head">
                                    <div class="saas-billing-col saas-billing-company">
                                        <span>Empresa</span>
                                        <strong><?= htmlspecialchars((string) ($subscription['company_name'] ?? 'Empresa')) ?></strong>
                                        <small><?= htmlspecialchars((string) ($subscription['company_slug'] ?? '-')) ?> · <?= htmlspecialchars((string) ($subscription['plan_name'] ?? 'Sem plano')) ?> · <?= htmlspecialchars(status_label('billing_cycle', $subscription['billing_cycle'] ?? null)) ?></small>
                                    </div>
                                    <div class="saas-billing-col">
                                        <span>Inicio</span>
                                        <strong><?= htmlspecialchars($formatDate($subscription['starts_at'] ?? null)) ?></strong>
                                    </div>
                                    <div class="saas-billing-col">
                                        <span>Fim</span>
                                        <strong><?= htmlspecialchars($formatDate($subscription['ends_at'] ?? null)) ?></strong>
                                    </div>
                                    <div class="saas-billing-col hide-md">
                                        <span>Valor</span>
                                        <strong><?= htmlspecialchars($formatMoney($subscription['amount'] ?? 0)) ?></strong>
                                    </div>
                                    <div class="saas-billing-col hide-mobile">
                                        <span>Gateway</span>
                                        <strong><?= htmlspecialchars($gatewayStatusLabel($subscription['gateway_status'] ?? null)) ?></strong>
                                    </div>
                                    <div class="saas-billing-col hide-mobile">
                                        <span>Ultima sync</span>
                                        <strong><?= htmlspecialchars($formatDate($subscription['gateway_last_synced_at'] ?? null, true)) ?></strong>
                                    </div>
                                    <div class="saas-billing-flags">
                                        <span class="saas-billing-mode <?= $modeClass ?>"><?= htmlspecialchars($autoCharge ? 'Automatico' : 'Manual') ?></span>
                                        <span class="badge <?= htmlspecialchars(status_badge_class('subscription_status', $subscriptionStatus)) ?>"><?= htmlspecialchars(status_label('subscription_status', $subscriptionStatus)) ?></span>
                                    </div>
                                </div>

                                <details class="saas-billing-details">
                                    <summary>
                                        <span>Detalhes contratuais e operacionais</span>
                                        <span class="saas-billing-details-toggle">Expandir / recolher</span>
                                    </summary>

                                    <div class="saas-billing-details-body">
                                        <div class="saas-billing-details-grid">
                                            <div class="saas-billing-box">
                                                <span>E-mail da empresa</span>
                                                <strong><?= htmlspecialchars((string) ($subscription['company_email'] ?? '-')) ?></strong>
                                            </div>
                                            <div class="saas-billing-box">
                                                <span>Documento</span>
                                                <strong><?= htmlspecialchars((string) ($subscription['company_document_number'] ?? '-')) ?></strong>
                                            </div>
                                            <div class="saas-billing-box">
                                                <span>Gateway provider</span>
                                                <strong><?= htmlspecialchars((string) ($subscription['gateway_provider'] ?? '-')) ?></strong>
                                            </div>
                                            <div class="saas-billing-box">
                                                <span>Gateway subscription ID</span>
                                                <strong><?= htmlspecialchars((string) ($subscription['gateway_subscription_id'] ?? '-')) ?></strong>
                                            </div>
                                            <div class="saas-billing-box">
                                                <span>Pagamento preferencial</span>
                                                <strong><?= htmlspecialchars($paymentMethodLabel($subscription['preferred_payment_method'] ?? null)) ?></strong>
                                            </div>
                                            <div class="saas-billing-box">
                                                <span>Auto cobranca</span>
                                                <strong><?= htmlspecialchars($autoCharge ? 'Habilitada' : 'Desabilitada') ?></strong>
                                            </div>
                                            <div class="saas-billing-box">
                                                <span>Cartao salvo</span>
                                                <strong><?= htmlspecialchars(trim((string) ($subscription['card_brand'] ?? '')) !== '' ? ((string) $subscription['card_brand'] . ' final ' . (string) ($subscription['card_last_digits'] ?? '')) : '-') ?></strong>
                                            </div>
                                            <div class="saas-billing-box">
                                                <span>Cancelada em</span>
                                                <strong><?= htmlspecialchars($formatDate($subscription['canceled_at'] ?? null, true)) ?></strong>
                                            </div>
                                        </div>

                                        <div class="saas-billing-callout <?= $modeClass ?>">
                                            <?php if ($hasGatewayBinding && $autoCharge): ?>
                                                A assinatura esta no trilho operacional correto: possui vinculo com gateway e auto cobranca habilitada. A tela de cobrancas deve ser usada mais para excecao, auditoria e sincronizacao.
                                            <?php elseif ($hasGatewayBinding): ?>
                                                Existe vinculo com gateway, mas a recorrencia ainda nao esta totalmente automatizada. O ponto fraco aqui nao e contrato, e sim a politica de cobranca.
                                            <?php else: ?>
                                                Esta assinatura ainda nao esta ancorada no gateway. Sem esse vinculo, o painel de cobrancas vira operacao manual e perde previsibilidade.
                                            <?php endif; ?>
                                        </div>

                                        <div class="saas-billing-actions">
                                            <a class="btn" href="<?= htmlspecialchars($chargeSearchUrl) ?>">Ver cobrancas da empresa</a>
                                            <?php if (trim((string) ($subscription['gateway_checkout_url'] ?? '')) !== ''): ?>
                                                <a class="btn secondary" href="<?= htmlspecialchars((string) $subscription['gateway_checkout_url']) ?>" target="_blank" rel="noreferrer">Abrir checkout</a>
                                            <?php endif; ?>
                                            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/saas/companies?search=' . rawurlencode((string) ($subscription['company_slug'] ?? '')))) ?>">Abrir empresa</a>
                                        </div>
                                    </div>
                                </details>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($subscriptionLastPage > 1): ?>
                        <div class="saas-billing-pagination">
                            <div class="muted">Exibindo <?= htmlspecialchars((string) $subscriptionFrom) ?> a <?= htmlspecialchars((string) $subscriptionTo) ?> de <?= htmlspecialchars((string) $totalSubscriptions) ?> assinaturas.</div>
                            <div class="saas-billing-pagination-controls">
                                <?php if ($subscriptionPage > 1): ?>
                                    <a class="saas-page-btn" href="<?= htmlspecialchars($buildSubscriptionsUrl(['subscription_page' => $subscriptionPage - 1])) ?>">Anterior</a>
                                <?php endif; ?>
                                <?php
                                $previousPage = null;
                                foreach ($subscriptionPages as $pageNumber):
                                    $pageNumber = (int) $pageNumber;
                                    if ($previousPage !== null && $pageNumber - $previousPage > 1): ?>
                                        <span class="saas-page-ellipsis">...</span>
                                    <?php endif; ?>
                                    <a class="saas-page-btn<?= $pageNumber === $subscriptionPage ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildSubscriptionsUrl(['subscription_page' => $pageNumber])) ?>"><?= $pageNumber ?></a>
                                    <?php
                                    $previousPage = $pageNumber;
                                endforeach;
                                ?>
                                <?php if ($subscriptionPage < $subscriptionLastPage): ?>
                                    <a class="saas-page-btn" href="<?= htmlspecialchars($buildSubscriptionsUrl(['subscription_page' => $subscriptionPage + 1])) ?>">Proxima</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>

        <aside class="saas-billing-side">
            <section class="card">
                <div class="saas-billing-head">
                    <div>
                        <h3>Resumo rapido</h3>
                        <p class="saas-billing-note">Os indicadores deixam claro onde esta o risco contratual: inadimplencia, excesso de assinatura manual ou pouca integracao com gateway.</p>
                    </div>
                </div>
                <div class="saas-billing-summary-grid">
                    <div class="saas-billing-summary-item"><strong>Total</strong><span><?= htmlspecialchars((string) $totalSubscriptions) ?></span></div>
                    <div class="saas-billing-summary-item"><strong>Ativas</strong><span><?= htmlspecialchars((string) $activeSubscriptions) ?></span></div>
                    <div class="saas-billing-summary-item"><strong>Trial</strong><span><?= htmlspecialchars((string) $trialSubscriptions) ?></span></div>
                    <div class="saas-billing-summary-item"><strong>Vencidas</strong><span><?= htmlspecialchars((string) $expiredSubscriptions) ?></span></div>
                    <div class="saas-billing-summary-item"><strong>Canceladas</strong><span><?= htmlspecialchars((string) $canceledSubscriptions) ?></span></div>
                    <div class="saas-billing-summary-item"><strong>MRR ativo</strong><span><?= htmlspecialchars($formatMoney($activeMonthlyMrr)) ?></span></div>
                </div>
            </section>

            <section class="card">
                <div class="saas-billing-head">
                    <div>
                        <h3>Atalhos de cobranca</h3>
                        <p class="saas-billing-note">Assinatura sem trilho financeiro consistente sempre estoura depois na fila de cobrancas. O fluxo operacional precisa continuar acoplado.</p>
                    </div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <a class="btn" href="<?= htmlspecialchars(base_url('/saas/subscription-payments')) ?>">Abrir cobrancas</a>
                    <a class="btn secondary" href="<?= htmlspecialchars(base_url('/saas/subscription-payments/create')) ?>">Nova cobranca</a>
                </div>
            </section>

            <section class="saas-billing-flow">
                <h3>Regra operacional</h3>
                <p>Assinatura e cobranca precisam falar a mesma lingua visual porque sao partes do mesmo problema. Quando a assinatura parece saudavel mas a cobranca nao esta automatizada, o risco comercial esta apenas escondido.</p>
                <ul>
                    <li>Assinatura ativa sem gateway ainda depende de rotina manual.</li>
                    <li>Gateway vinculado sem auto cobranca indica trilho incompleto.</li>
                    <li>Cobrancas e assinaturas devem ser analisadas como um unico fluxo operacional.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>
