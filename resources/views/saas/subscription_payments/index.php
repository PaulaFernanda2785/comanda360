<?php
$paymentPanel = is_array($paymentPanel ?? null) ? $paymentPanel : [];
$subscriptionPayments = is_array($paymentPanel['payments'] ?? null) ? $paymentPanel['payments'] : [];
$filters = is_array($paymentPanel['filters'] ?? null) ? $paymentPanel['filters'] : [];
$summary = is_array($paymentPanel['summary'] ?? null) ? $paymentPanel['summary'] : [];
$pagination = is_array($paymentPanel['pagination'] ?? null) ? $paymentPanel['pagination'] : [];

$search = trim((string) ($filters['search'] ?? ''));
$status = trim((string) ($filters['status'] ?? ''));

$statusOptions = [
    '' => 'Todos os status',
    'pendente' => 'Pendentes',
    'vencido' => 'Vencidas',
    'pago' => 'Pagas',
    'cancelado' => 'Canceladas',
];

$totalCharges = (int) ($summary['total_charges'] ?? ($pagination['total'] ?? count($subscriptionPayments)));
$pendingCharges = (int) ($summary['pending_charges'] ?? 0);
$overdueCharges = (int) ($summary['overdue_charges'] ?? 0);
$paidCharges = (int) ($summary['paid_charges'] ?? 0);
$paidAmount = (float) ($summary['total_paid_amount'] ?? 0);

$paymentPage = max(1, (int) ($pagination['page'] ?? 1));
$paymentLastPage = max(1, (int) ($pagination['last_page'] ?? 1));
$paymentFrom = (int) ($pagination['from'] ?? 0);
$paymentTo = (int) ($pagination['to'] ?? 0);
$paymentPages = is_array($pagination['pages'] ?? null) ? $pagination['pages'] : [];

$currentQuery = is_array($_GET ?? null) ? $_GET : [];
$returnQuery = http_build_query($currentQuery);

$buildPaymentsUrl = static function (array $overrides = []) use ($search, $status): string {
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
    return base_url('/saas/subscription-payments' . ($query !== '' ? '?' . $query : ''));
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
        'approved' => 'Pago no gateway',
        'pending', 'in_process' => 'Pendente no gateway',
        'cancelled', 'canceled' => 'Cancelado no gateway',
        'rejected' => 'Recusado no gateway',
        '' => 'Sem retorno do gateway',
        default => ucfirst(str_replace(['_', '-'], ' ', $raw)),
    };
};

$hasGatewayBinding = static function (array $charge): bool {
    return trim((string) ($charge['gateway_payment_id'] ?? '')) !== '';
};

$chargeModeLabel = static function (bool $bound): string {
    return $bound ? 'Automatico' : 'Manual';
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
    .saas-billing-actions form{margin:0}
    .saas-billing-inline-input{min-width:220px}
    .saas-billing-disabled{opacity:.55;cursor:not-allowed}
    .saas-billing-paid{color:#166534;font-weight:700}

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
        .saas-billing-inline-input{min-width:0;width:100%}
    }
</style>

<div class="saas-billing-page">
    <div class="saas-billing-hero">
        <div class="saas-billing-hero-body">
            <div>
                <h1>Cobrancas</h1>
                <p>O painel agora prioriza leitura rapida, fila operacional e excecoes. Em vez de uma sequencia de cards longos, cada cobranca aparece primeiro como linha objetiva e so abre detalhes quando realmente for preciso agir.</p>
            </div>
            <div class="saas-billing-pills">
                <span class="saas-billing-pill">Cobrancas filtradas: <?= htmlspecialchars((string) $totalCharges) ?></span>
                <span class="saas-billing-pill">Pendentes: <?= htmlspecialchars((string) $pendingCharges) ?></span>
                <span class="saas-billing-pill">Vencidas: <?= htmlspecialchars((string) $overdueCharges) ?></span>
                <span class="saas-billing-pill">Recebido: <?= htmlspecialchars($formatMoney($paidAmount)) ?></span>
            </div>
        </div>
    </div>

    <div class="saas-billing-layout">
        <div class="saas-billing-main">
            <section class="card">
                <div class="saas-billing-head">
                    <div>
                        <h2>Fila de cobrancas</h2>
                        <p class="saas-billing-note">Use a lista como painel de decisao. Primeiro identifique status e vinculo com gateway. So depois abra a cobranca para acao manual, sincronizacao ou geracao do PIX real.</p>
                    </div>
                    <div class="saas-billing-badges">
                        <span class="badge">Pagas: <?= htmlspecialchars((string) $paidCharges) ?></span>
                        <span class="badge">Fila ativa: <?= htmlspecialchars((string) ($pendingCharges + $overdueCharges)) ?></span>
                    </div>
                </div>

                <div class="saas-billing-kpis" style="margin-top:16px">
                    <div class="saas-billing-kpi">
                        <span>Total</span>
                        <strong><?= htmlspecialchars((string) $totalCharges) ?></strong>
                        <small>Historico consolidado</small>
                    </div>
                    <div class="saas-billing-kpi">
                        <span>Pendentes</span>
                        <strong><?= htmlspecialchars((string) $pendingCharges) ?></strong>
                        <small>Aguardando pagamento</small>
                    </div>
                    <div class="saas-billing-kpi">
                        <span>Vencidas</span>
                        <strong><?= htmlspecialchars((string) $overdueCharges) ?></strong>
                        <small>Exigem prioridade</small>
                    </div>
                    <div class="saas-billing-kpi">
                        <span>Recebido</span>
                        <strong><?= htmlspecialchars($formatMoney($paidAmount)) ?></strong>
                        <small>Valor confirmado</small>
                    </div>
                </div>

                <form method="GET" action="<?= htmlspecialchars(base_url('/saas/subscription-payments')) ?>" style="margin-top:16px">
                    <div class="saas-billing-filter-grid">
                        <div class="field">
                            <label for="saas_payment_search">Busca inteligente</label>
                            <input id="saas_payment_search" name="search" type="text" value="<?= htmlspecialchars($search) ?>" placeholder="Empresa, plano, slug, referencia ou gateway">
                        </div>
                        <div class="field">
                            <label for="saas_payment_status">Status</label>
                            <select id="saas_payment_status" name="status">
                                <?php foreach ($statusOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= $status === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="saas-billing-filter-actions">
                            <button class="btn" type="submit">Aplicar</button>
                            <a class="btn secondary" href="<?= htmlspecialchars($buildPaymentsUrl(['search' => '', 'status' => '', 'payment_page' => ''])) ?>">Limpar</a>
                        </div>
                    </div>
                </form>

                <?php if ($subscriptionPayments === []): ?>
                    <div class="card" style="margin-top:16px;padding:14px;border:1px dashed #cbd5e1;box-shadow:none">
                        <?= ($search !== '' || $status !== '')
                            ? 'Nenhuma cobranca encontrada para os filtros aplicados.'
                            : 'Nenhuma cobranca cadastrada ate o momento.' ?>
                    </div>
                <?php else: ?>
                    <div class="saas-billing-table" style="margin-top:16px">
                        <?php foreach ($subscriptionPayments as $charge): ?>
                            <?php
                            $chargeId = (int) ($charge['id'] ?? 0);
                            $chargeStatus = trim((string) ($charge['status'] ?? ''));
                            $chargeHasGatewayBinding = $hasGatewayBinding($charge);
                            $chargeModeClass = $chargeHasGatewayBinding ? 'auto' : 'manual';
                            ?>
                            <article class="saas-billing-row">
                                <div class="saas-billing-row-head">
                                    <div class="saas-billing-col saas-billing-company">
                                        <span>Empresa</span>
                                        <strong><?= htmlspecialchars((string) ($charge['company_name'] ?? 'Empresa')) ?></strong>
                                        <small><?= htmlspecialchars((string) ($charge['company_slug'] ?? '-')) ?> · <?= htmlspecialchars((string) ($charge['plan_name'] ?? 'Sem plano')) ?> · <?= htmlspecialchars(status_label('billing_cycle', $charge['billing_cycle'] ?? null)) ?></small>
                                    </div>
                                    <div class="saas-billing-col">
                                        <span>Referencia</span>
                                        <strong><?= str_pad((string) (int) ($charge['reference_month'] ?? 0), 2, '0', STR_PAD_LEFT) ?>/<?= (int) ($charge['reference_year'] ?? 0) ?></strong>
                                    </div>
                                    <div class="saas-billing-col">
                                        <span>Vencimento</span>
                                        <strong><?= htmlspecialchars($formatDate($charge['due_date'] ?? null)) ?></strong>
                                    </div>
                                    <div class="saas-billing-col hide-md">
                                        <span>Valor</span>
                                        <strong><?= htmlspecialchars($formatMoney($charge['amount'] ?? 0)) ?></strong>
                                    </div>
                                    <div class="saas-billing-col hide-mobile">
                                        <span>Gateway</span>
                                        <strong><?= htmlspecialchars($gatewayStatusLabel($charge['gateway_status'] ?? null)) ?></strong>
                                    </div>
                                    <div class="saas-billing-col hide-mobile">
                                        <span>Ultima sync</span>
                                        <strong><?= htmlspecialchars($formatDate($charge['gateway_last_synced_at'] ?? null, true)) ?></strong>
                                    </div>
                                    <div class="saas-billing-flags">
                                        <span class="saas-billing-mode <?= $chargeModeClass ?>"><?= htmlspecialchars($chargeModeLabel($chargeHasGatewayBinding)) ?></span>
                                        <span class="badge <?= htmlspecialchars(status_badge_class('subscription_payment_status', $chargeStatus)) ?>"><?= htmlspecialchars(status_label('subscription_payment_status', $chargeStatus)) ?></span>
                                    </div>
                                </div>

                                <details class="saas-billing-details">
                                    <summary>
                                        <span>Detalhes e acoes da cobranca</span>
                                        <span class="saas-billing-details-toggle">Expandir / recolher</span>
                                    </summary>

                                    <div class="saas-billing-details-body">
                                        <div class="saas-billing-details-grid">
                                            <div class="saas-billing-box">
                                                <span>Valor</span>
                                                <strong><?= htmlspecialchars($formatMoney($charge['amount'] ?? 0)) ?></strong>
                                            </div>
                                            <div class="saas-billing-box">
                                                <span>Referencia interna</span>
                                                <strong><?= htmlspecialchars((string) ($charge['transaction_reference'] ?? '-')) ?></strong>
                                            </div>
                                            <div class="saas-billing-box">
                                                <span>Vinculo com gateway</span>
                                                <strong><?= htmlspecialchars($chargeHasGatewayBinding ? 'Com vinculo real no gateway' : 'Manual ou sem vinculo') ?></strong>
                                            </div>
                                            <div class="saas-billing-box">
                                                <span>Gateway payment ID</span>
                                                <strong><?= htmlspecialchars((string) ($charge['gateway_payment_id'] ?? '-')) ?></strong>
                                            </div>
                                        </div>

                                        <div class="saas-billing-callout <?= $chargeModeClass ?>">
                                            <?php if ($chargeHasGatewayBinding): ?>
                                                O trilho correto desta cobranca e automatico. Gere o PIX uma vez, deixe o usuario pagar e use sincronizacao apenas quando precisar validar divergencia.
                                            <?php else: ?>
                                                Esta cobranca ainda nao entrou no trilho automatico. Primeiro gere o PIX real no gateway. Sem isso, o sistema nao consegue confirmar pagamento sozinho.
                                            <?php endif; ?>
                                        </div>

                                        <div class="saas-billing-actions">
                                            <?php if (!$chargeHasGatewayBinding && !in_array($chargeStatus, ['pago', 'cancelado'], true)): ?>
                                                <form method="POST" action="<?= htmlspecialchars(base_url('/saas/subscription-payments/generate-gateway-pix')) ?>">
                                                    <?= form_security_fields('saas.subscription_payments.generate_gateway_pix.' . $chargeId) ?>
                                                    <input type="hidden" name="subscription_payment_id" value="<?= $chargeId ?>">
                                                    <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">
                                                    <button class="btn" type="submit">Gerar PIX no gateway</button>
                                                </form>
                                            <?php endif; ?>

                                            <form method="POST" action="<?= htmlspecialchars(base_url('/saas/subscription-payments/sync-gateway')) ?>">
                                                <?= form_security_fields('saas.subscription_payments.sync_gateway.' . $chargeId) ?>
                                                <input type="hidden" name="subscription_payment_id" value="<?= $chargeId ?>">
                                                <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">
                                                <button class="btn secondary<?= $chargeHasGatewayBinding ? '' : ' saas-billing-disabled' ?>" type="submit" <?= $chargeHasGatewayBinding ? '' : 'disabled title="Esta cobranca nao possui gateway_payment_id salvo."' ?>>Sincronizar agora</button>
                                            </form>

                                            <?php if (!in_array($chargeStatus, ['pago', 'cancelado'], true)): ?>
                                                <form method="POST" action="<?= htmlspecialchars(base_url('/saas/subscription-payments/mark-paid')) ?>">
                                                    <?= form_security_fields('saas.subscription_payments.mark_paid.' . $chargeId) ?>
                                                    <input type="hidden" name="subscription_payment_id" value="<?= $chargeId ?>">
                                                    <input type="hidden" name="payment_method" value="pix">
                                                    <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">
                                                    <input class="saas-billing-inline-input" type="text" name="transaction_reference" placeholder="Comprovante, txid ou observacao">
                                                    <button class="btn" type="submit"><?= $chargeHasGatewayBinding ? 'Baixa manual excepcional' : 'Registrar como pago' ?></button>
                                                </form>

                                                <form method="POST" action="<?= htmlspecialchars(base_url('/saas/subscription-payments/mark-overdue')) ?>">
                                                    <?= form_security_fields('saas.subscription_payments.mark_overdue.' . $chargeId) ?>
                                                    <input type="hidden" name="subscription_payment_id" value="<?= $chargeId ?>">
                                                    <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">
                                                    <button class="btn secondary" type="submit">Marcar em atraso</button>
                                                </form>

                                                <form method="POST" action="<?= htmlspecialchars(base_url('/saas/subscription-payments/cancel')) ?>" onsubmit="return confirm('Cancelar esta cobranca?');">
                                                    <?= form_security_fields('saas.subscription_payments.cancel.' . $chargeId) ?>
                                                    <input type="hidden" name="subscription_payment_id" value="<?= $chargeId ?>">
                                                    <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">
                                                    <button class="btn secondary" type="submit">Cancelar cobranca</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="<?= $chargeStatus === 'pago' ? 'saas-billing-paid' : 'muted' ?>">
                                                    <?= $chargeStatus === 'pago' ? 'Pagamento ja conciliado' : 'Cobranca encerrada' ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </details>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($paymentLastPage > 1): ?>
                        <div class="saas-billing-pagination">
                            <div class="muted">Exibindo <?= htmlspecialchars((string) $paymentFrom) ?> a <?= htmlspecialchars((string) $paymentTo) ?> de <?= htmlspecialchars((string) $totalCharges) ?> cobrancas.</div>
                            <div class="saas-billing-pagination-controls">
                                <?php if ($paymentPage > 1): ?>
                                    <a class="saas-page-btn" href="<?= htmlspecialchars($buildPaymentsUrl(['payment_page' => $paymentPage - 1])) ?>">Anterior</a>
                                <?php endif; ?>
                                <?php
                                $previousPage = null;
                                foreach ($paymentPages as $pageNumber):
                                    $pageNumber = (int) $pageNumber;
                                    if ($previousPage !== null && $pageNumber - $previousPage > 1): ?>
                                        <span class="saas-page-ellipsis">...</span>
                                    <?php endif; ?>
                                    <a class="saas-page-btn<?= $pageNumber === $paymentPage ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildPaymentsUrl(['payment_page' => $pageNumber])) ?>"><?= $pageNumber ?></a>
                                    <?php
                                    $previousPage = $pageNumber;
                                endforeach;
                                ?>
                                <?php if ($paymentPage < $paymentLastPage): ?>
                                    <a class="saas-page-btn" href="<?= htmlspecialchars($buildPaymentsUrl(['payment_page' => $paymentPage + 1])) ?>">Proxima</a>
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
                        <p class="saas-billing-note">Indicadores suficientes para saber se o gargalo esta em cobranca vencida, fila pendente ou conciliacao manual.</p>
                    </div>
                </div>
                <div class="saas-billing-summary-grid">
                    <div class="saas-billing-summary-item"><strong>Total</strong><span><?= htmlspecialchars((string) $totalCharges) ?></span></div>
                    <div class="saas-billing-summary-item"><strong>Pendentes</strong><span><?= htmlspecialchars((string) $pendingCharges) ?></span></div>
                    <div class="saas-billing-summary-item"><strong>Vencidas</strong><span><?= htmlspecialchars((string) $overdueCharges) ?></span></div>
                    <div class="saas-billing-summary-item"><strong>Pagas</strong><span><?= htmlspecialchars((string) $paidCharges) ?></span></div>
                    <div class="saas-billing-summary-item"><strong>Receita recebida</strong><span><?= htmlspecialchars($formatMoney($paidAmount)) ?></span></div>
                </div>
            </section>

            <section class="card">
                <div class="saas-billing-head">
                    <div>
                        <h3>Nova cobranca PIX</h3>
                        <p class="saas-billing-note">Crie a cobranca interna e depois transforme em PIX real no gateway quando quiser colocar a empresa no fluxo automatico.</p>
                    </div>
                </div>
                <a class="btn" href="<?= htmlspecialchars(base_url('/saas/subscription-payments/create')) ?>">Abrir formulario</a>
            </section>

            <section class="saas-billing-flow">
                <h3>Regra operacional</h3>
                <p>O painel precisa deixar uma distincao objetiva entre trilho automatico e excecao manual. Quando isso se mistura, o administrativo perde confiabilidade.</p>
                <ul>
                    <li>Sem gateway_payment_id, a cobranca continua manual.</li>
                    <li>Com gateway_payment_id, a prioridade e confirmar automaticamente.</li>
                    <li>Baixa manual deve ser excecao documentada, nao rotina operacional.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>
