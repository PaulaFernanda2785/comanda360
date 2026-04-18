<?php
$subscriptionModule = is_array($subscriptionModule ?? null) ? $subscriptionModule : [];
$subscription = is_array($subscriptionModule['subscription'] ?? null) ? $subscriptionModule['subscription'] : [];
$subscriptionSummary = is_array($subscriptionModule['summary'] ?? null) ? $subscriptionModule['summary'] : [];
$openSubscriptionPayments = is_array($subscriptionModule['open_payments'] ?? null) ? $subscriptionModule['open_payments'] : [];
$subscriptionHistory = is_array($subscriptionModule['payment_history'] ?? null) ? $subscriptionModule['payment_history'] : [];
$subscriptionHistoryFilters = is_array($subscriptionModule['history_filters'] ?? null) ? $subscriptionModule['history_filters'] : [];
$subscriptionHistoryPagination = is_array($subscriptionModule['history_pagination'] ?? null) ? $subscriptionModule['history_pagination'] : [];
$subscriptionFeatures = is_array($subscriptionModule['features'] ?? null) ? $subscriptionModule['features'] : [];
$subscriptionBillingAccess = is_array($subscriptionModule['billing_access'] ?? null) ? $subscriptionModule['billing_access'] : [];

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
    '' => 'Não definido',
    'pix' => 'Pix',
    'credito' => 'Cartão de crédito',
    'debito' => 'Cartão de débito',
];

$historyStatusOptions = [
    '' => 'Todos os status',
    'pendente' => 'Pendente',
    'pago' => 'Pago',
    'vencido' => 'Vencido',
    'cancelado' => 'Cancelado',
];

$historyMethodOptions = [
    '' => 'Todos os métodos',
    'pix' => 'Pix',
    'credito' => 'Cartão de crédito',
    'debito' => 'Cartão de débito',
    'none' => 'Sem método',
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

$currentCharge = is_array($openSubscriptionPayments[0] ?? null) ? $openSubscriptionPayments[0] : [];
$hasCurrentCharge = $currentCharge !== [];
$currentChargeId = (int) ($currentCharge['id'] ?? 0);
$currentPixImage = trim((string) ($currentCharge['pix_qr_image_base64'] ?? ''));
$currentPixPayload = trim((string) ($currentCharge['pix_qr_payload'] ?? ''));
$currentPixTicketUrl = trim((string) ($currentCharge['pix_ticket_url'] ?? ''));
$currentPixFallbackImageUrl = $currentPixImage === '' && $currentPixPayload !== ''
    ? 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . rawurlencode($currentPixPayload)
    : '';
$currentGatewayPaymentId = trim((string) ($currentCharge['gateway_payment_id'] ?? ''));
$shouldAutoPollPixStatus = $currentChargeId > 0
    && $currentGatewayPaymentId !== ''
    && in_array((string) ($currentCharge['status'] ?? ''), ['pendente', 'vencido'], true);
$nextDueDate = $subscriptionSummary['next_due_date'] ?? ($subscriptionBillingAccess['next_due_date'] ?? null);
?>

<section class="dash-section<?= $activeSection === 'subscription' ? ' active' : '' ?>" data-section="subscription">
    <style>
        .sp-shell{display:grid;gap:14px}
        .sp-hero{border:1px solid #bfdbfe;border-radius:16px;background:linear-gradient(130deg,var(--theme-main-card,#0f172a) 0%,#0f766e 55%,#2dd4bf 100%);color:#fff;padding:18px}
        .sp-hero h2{margin:0 0 8px;color:#fff}
        .sp-hero p{margin:0;max-width:860px;color:#ccfbf1;line-height:1.5}
        .sp-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
        .sp-kpi{border:1px solid #ccfbf1;border-radius:12px;background:linear-gradient(180deg,#fff 0%,#f0fdfa 100%);padding:12px}
        .sp-kpi span{display:block;font-size:11px;text-transform:uppercase;color:#64748b}
        .sp-kpi strong{display:block;margin-top:6px;font-size:20px;color:#0f172a}
        .sp-kpi small{display:block;margin-top:4px;color:#475569}
        .sp-layout{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,.9fr);gap:14px;align-items:start}
        .sp-column{display:grid;gap:14px}
        .sp-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
        .sp-head h3{margin:0;color:#0f172a}
        .sp-note{margin:4px 0 0;color:#475569;font-size:13px;line-height:1.45}
        .sp-flow{display:grid;gap:10px}
        .sp-step{display:grid;grid-template-columns:34px minmax(0,1fr);gap:10px;align-items:flex-start;padding:10px 0;border-bottom:1px dashed #cbd5e1}
        .sp-step:last-child{border-bottom:none;padding-bottom:0}
        .sp-step-num{width:34px;height:34px;border-radius:999px;background:#ccfbf1;color:#0f766e;display:grid;place-items:center;font-weight:700}
        .sp-step strong{display:block;color:#0f172a}
        .sp-step p{margin:4px 0 0;color:#475569;font-size:13px;line-height:1.45}
        .sp-charge{border:1px solid #99f6e4;border-radius:14px;background:linear-gradient(180deg,#fff 0%,#f0fdfa 100%);padding:14px;display:grid;gap:12px}
        .sp-charge-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
        .sp-charge-top strong{display:block;font-size:18px;color:#0f172a}
        .sp-meta{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
        .sp-meta-box{border:1px solid #d1fae5;border-radius:10px;background:#fff;padding:10px}
        .sp-meta-box span{display:block;font-size:11px;text-transform:uppercase;color:#64748b}
        .sp-meta-box strong{display:block;margin-top:4px;font-size:13px;color:#0f172a}
        .sp-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
        .sp-qr{display:grid;grid-template-columns:180px minmax(0,1fr);gap:12px;align-items:start}
        .sp-qr-image{border:1px solid #d1fae5;border-radius:12px;background:#fff;padding:10px;display:grid;place-items:center;min-height:180px}
        .sp-qr-image img{display:block;max-width:100%;height:auto}
        .sp-qr-data{display:grid;gap:10px}
        .sp-info{display:grid;gap:8px}
        .sp-row{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px dashed #cbd5e1}
        .sp-row:last-child{border-bottom:none}
        .sp-row span{font-size:12px;color:#64748b}
        .sp-row strong{font-size:13px;color:#0f172a;text-align:right}
        .sp-feature-list{display:flex;gap:6px;flex-wrap:wrap}
        .sp-feature{padding:6px 10px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:12px;font-weight:700}
        .sp-history-wrap{overflow:auto;-webkit-overflow-scrolling:touch}
        .sp-history-table{width:100%;border-collapse:collapse}
        .sp-history-table th,.sp-history-table td{padding:10px;border-bottom:1px solid #e2e8f0;font-size:13px;text-align:left;vertical-align:top}
        .sp-history-table th{font-size:12px;color:#64748b;text-transform:uppercase}
        .sp-filter-grid{display:grid;grid-template-columns:1.6fr 1fr 1fr auto;gap:10px;align-items:end}
        .sp-filter-grid .field{margin:0}
        .sp-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px}
        .sp-pagination-controls{display:flex;gap:8px;flex-wrap:wrap}
        .sp-page-btn{border:1px solid #cbd5e1;background:#fff;color:#0f172a;border-radius:8px;padding:7px 10px;text-decoration:none;min-width:36px;text-align:center}
        .sp-page-btn.is-active{background:#0f766e;border-color:#0f766e;color:#fff}
        .sp-page-btn.is-disabled{pointer-events:none;opacity:.45}
        @media (max-width:1100px){.sp-layout{grid-template-columns:1fr}}
        @media (max-width:900px){.sp-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (max-width:760px){
            .sp-grid,.sp-meta,.sp-filter-grid,.sp-qr{grid-template-columns:1fr}
            .sp-history-wrap{overflow:visible}
            .sp-history-table,.sp-history-table tbody,.sp-history-table tr,.sp-history-table td{display:block;width:100%}
            .sp-history-table thead{display:none}
            .sp-history-table{border-collapse:separate;border-spacing:0 10px}
            .sp-history-table tbody{display:grid;gap:10px}
            .sp-history-table tr{border:1px solid #dbe4ee;border-radius:12px;background:#f8fafc;padding:4px 0}
            .sp-history-table td{display:grid;grid-template-columns:110px minmax(0,1fr);gap:10px;padding:8px 12px;border-bottom:1px dashed #dbe4ee}
            .sp-history-table td:last-child{border-bottom:none}
            .sp-history-table td::before{content:attr(data-label);font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#64748b}
        }
    </style>

    <div class="sp-shell">
        <div class="sp-hero">
            <h2>Pague sua assinatura via PIX</h2>
            <p>O fluxo principal agora é simples: gerar o PIX da próxima cobrança, pagar no banco e deixar o sistema confirmar automaticamente. O botão de sincronização continua disponível apenas como apoio quando você quiser forçar uma consulta imediata.</p>
        </div>

        <?php if ($subscription === []): ?>
            <div class="card">
                <div class="empty-state">
                    Nenhuma assinatura ativa foi localizada para esta empresa. Vincule um plano no SaaS antes de liberar esta área.
                </div>
            </div>
        <?php else: ?>
            <div class="sp-grid">
                <div class="sp-kpi">
                    <span>Plano</span>
                    <strong><?= htmlspecialchars((string) ($subscription['plan_name'] ?? '-')) ?></strong>
                    <small><?= htmlspecialchars(status_label('billing_cycle', (string) ($subscription['billing_cycle'] ?? ''))) ?></small>
                </div>
                <div class="sp-kpi">
                    <span>Próximo vencimento</span>
                    <strong><?= htmlspecialchars($formatSubscriptionDate($nextDueDate)) ?></strong>
                    <small>Status atual: <?= htmlspecialchars(status_label('subscription_status', (string) ($subscription['status'] ?? ''))) ?></small>
                </div>
                <div class="sp-kpi">
                    <span>Em aberto</span>
                    <strong><?= htmlspecialchars((string) ($subscriptionSummary['open_count'] ?? 0)) ?></strong>
                    <small><?= htmlspecialchars($formatSubscriptionMoney($subscriptionSummary['open_amount'] ?? 0)) ?></small>
                </div>
                <div class="sp-kpi">
                    <span>Em atraso</span>
                    <strong><?= htmlspecialchars((string) ($subscriptionSummary['overdue_count'] ?? 0)) ?></strong>
                    <small>Bloqueio local considera 3 dias de tolerância</small>
                </div>
            </div>

            <div class="sp-layout">
                <div class="sp-column">
                    <div class="card">
                        <div class="sp-head">
                            <div>
                                <h3>Como pagar agora</h3>
                                <p class="sp-note">Esse é o fluxo recomendado para a empresa. Um caminho principal, sem termos técnicos e sem decisão operacional desnecessária para o usuário.</p>
                            </div>
                        </div>

                        <div class="sp-flow" style="margin-top:12px">
                            <div class="sp-step">
                                <div class="sp-step-num">1</div>
                                <div>
                                    <strong>Gerar o PIX da próxima cobrança</strong>
                                    <p>O sistema busca o QR real da cobrança atual para você pagar no aplicativo do banco.</p>
                                </div>
                            </div>
                            <div class="sp-step">
                                <div class="sp-step-num">2</div>
                                <div>
                                    <strong>Fazer o pagamento no banco</strong>
                                    <p>Use o QR Code ou o código copia e cola. Não é preciso preencher cartão nem passar por autorização recorrente.</p>
                                </div>
                            </div>
                            <div class="sp-step">
                                <div class="sp-step-num">3</div>
                                <div>
                                    <strong>Aguardar a confirmação automática</strong>
                                    <p>Depois do pagamento, a tela verifica o gateway sozinha e muda o status para pago. Se voce quiser acelerar a consulta, use o botao de sincronizacao.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($hasCurrentCharge): ?>
                        <div class="sp-charge">
                            <div class="sp-charge-top">
                                <div>
                                    <strong>Pagar cobrança atual</strong>
                                    <div class="sp-note">
                                        Referência <?= htmlspecialchars(sprintf('%02d/%04d', (int) ($currentCharge['reference_month'] ?? 0), (int) ($currentCharge['reference_year'] ?? 0))) ?>
                                        - vencimento em <?= htmlspecialchars($formatSubscriptionDate($currentCharge['due_date'] ?? null)) ?>
                                    </div>
                                </div>
                                <span class="badge <?= htmlspecialchars(status_badge_class('subscription_payment_status', (string) ($currentCharge['status'] ?? ''))) ?>">
                                    <?= htmlspecialchars(status_label('subscription_payment_status', (string) ($currentCharge['status'] ?? ''))) ?>
                                </span>
                            </div>

                            <div class="sp-meta">
                                <div class="sp-meta-box">
                                    <span>Valor</span>
                                    <strong><?= htmlspecialchars($formatSubscriptionMoney($currentCharge['amount'] ?? 0)) ?></strong>
                                </div>
                                <div class="sp-meta-box">
                                    <span>Método atual</span>
                                    <strong><?= htmlspecialchars($paymentMethodLabels[(string) ($currentCharge['payment_method'] ?? '')] ?? 'Pix') ?></strong>
                                </div>
                                <div class="sp-meta-box">
                                    <span>Fluxo</span>
                                    <strong><?= htmlspecialchars($currentGatewayPaymentId !== '' ? 'Automático via gateway' : 'Manual sem vínculo') ?></strong>
                                </div>
                            </div>

                            <div class="sp-actions">
                                <form method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/subscription/pix/generate')) ?>">
                                    <?= form_security_fields('dashboard.subscription.pix.generate.' . $currentChargeId) ?>
                                    <input type="hidden" name="subscription_payment_id" value="<?= htmlspecialchars((string) $currentChargeId) ?>">
                                    <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnSubscriptionQuery) ?>">
                                    <button class="btn" type="submit"><?= $currentPixImage !== '' ? 'Atualizar QR PIX' : 'Gerar QR PIX' ?></button>
                                </form>

                                <?php if ($currentPixTicketUrl !== ''): ?>
                                    <a class="btn secondary" href="<?= htmlspecialchars($currentPixTicketUrl) ?>" target="_blank" rel="noopener">Abrir tela PIX</a>
                                <?php endif; ?>

                                <form method="POST" action="<?= htmlspecialchars(base_url('/admin/dashboard/subscription/gateway/sync')) ?>">
                                    <?= form_security_fields('dashboard.subscription.gateway.sync') ?>
                                    <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnSubscriptionQuery) ?>">
                                    <button class="btn secondary" type="submit">Sincronizar agora</button>
                                </form>
                            </div>

                            <?php if ($shouldAutoPollPixStatus): ?>
                                <div class="sp-note" id="pix-auto-status-note">
                                    Aguardando confirmação automática do pagamento no gateway. Esta tela consulta o status sozinha a cada 20 segundos.
                                </div>
                            <?php endif; ?>

                            <?php if ($currentPixImage !== '' || $currentPixPayload !== ''): ?>
                                <div class="sp-qr">
                                    <div class="sp-qr-image">
                                        <?php if ($currentPixImage !== ''): ?>
                                            <img src="data:image/png;base64,<?= htmlspecialchars($currentPixImage) ?>" alt="QR Code PIX">
                                        <?php elseif ($currentPixFallbackImageUrl !== ''): ?>
                                            <img src="<?= htmlspecialchars($currentPixFallbackImageUrl) ?>" alt="QR Code PIX">
                                        <?php else: ?>
                                            <span class="sp-note">QR real ainda não retornado pelo gateway.</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="sp-qr-data">
                                        <div class="field">
                                            <label>PIX copia e cola</label>
                                            <textarea readonly><?= htmlspecialchars($currentPixPayload !== '' ? $currentPixPayload : (string) ($currentCharge['pix_code'] ?? '')) ?></textarea>
                                        </div>
                                        <div class="sp-note">Depois do pagamento, aguarde alguns instantes para a confirmação automática. Se o status não mudar, use sincronizar agora. Se ainda assim não atualizar, fale com a equipe técnica.</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="empty-state">
                                Não existe cobrança em aberto agora. Se você já regularizou a assinatura, aguarde o próximo ciclo.
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="sp-head">
                            <div>
                                <h3>Histórico financeiro</h3>
                                <p class="sp-note">Consulta das cobranças já registradas para auditoria e conferência.</p>
                            </div>
                        </div>

                        <form method="GET" action="<?= htmlspecialchars(base_url('/admin/dashboard')) ?>" style="margin-top:12px">
                            <input type="hidden" name="section" value="subscription">
                            <div class="sp-filter-grid">
                                <div class="field">
                                    <label for="subscription_history_search">Busca</label>
                                    <input id="subscription_history_search" name="subscription_history_search" type="text" value="<?= htmlspecialchars($historySearch) ?>" placeholder="Referência, gateway, pix ou origem">
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
                                    <label for="subscription_history_method">Método</label>
                                    <select id="subscription_history_method" name="subscription_history_method">
                                        <?php foreach ($historyMethodOptions as $value => $label): ?>
                                            <option value="<?= htmlspecialchars($value) ?>" <?= $historyMethod === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="sp-actions">
                                    <button class="btn" type="submit">Filtrar</button>
                                    <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/dashboard?section=subscription')) ?>">Limpar</a>
                                </div>
                            </div>
                        </form>

                        <?php if ($subscriptionHistory === []): ?>
                            <div class="empty-state" style="margin-top:12px">
                                Ainda não existe histórico financeiro registrado para esta assinatura.
                            </div>
                        <?php else: ?>
                            <div class="sp-history-wrap" style="margin-top:12px">
                                <table class="sp-history-table">
                                    <thead>
                                        <tr>
                                            <th>Referência</th>
                                            <th>Vencimento</th>
                                            <th>Valor</th>
                                            <th>Status</th>
                                            <th>Método</th>
                                            <th>Pago em</th>
                                            <th>Referência externa</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($subscriptionHistory as $payment): ?>
                                            <tr>
                                                <td data-label="Referência"><?= htmlspecialchars(sprintf('%02d/%04d', (int) ($payment['reference_month'] ?? 0), (int) ($payment['reference_year'] ?? 0))) ?></td>
                                                <td data-label="Vencimento"><?= htmlspecialchars($formatSubscriptionDate($payment['due_date'] ?? null)) ?></td>
                                                <td data-label="Valor"><?= htmlspecialchars($formatSubscriptionMoney($payment['amount'] ?? 0)) ?></td>
                                                <td data-label="Status">
                                                    <span class="badge <?= htmlspecialchars(status_badge_class('subscription_payment_status', (string) ($payment['status'] ?? ''))) ?>">
                                                        <?= htmlspecialchars(status_label('subscription_payment_status', (string) ($payment['status'] ?? ''))) ?>
                                                    </span>
                                                </td>
                                                <td data-label="Método"><?= htmlspecialchars($paymentMethodLabels[(string) ($payment['payment_method'] ?? '')] ?? 'Não definido') ?></td>
                                                <td data-label="Pago em"><?= htmlspecialchars($formatSubscriptionDate($payment['paid_at'] ?? null, true)) ?></td>
                                                <td data-label="Referência externa"><?= htmlspecialchars((string) ($payment['transaction_reference'] ?? '-')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="sp-pagination">
                                <div class="sp-note">
                                    <?php if ((int) ($subscriptionHistoryPagination['total'] ?? 0) > 0): ?>
                                        Exibindo <?= htmlspecialchars((string) ($subscriptionHistoryPagination['from'] ?? 0)) ?>-<?= htmlspecialchars((string) ($subscriptionHistoryPagination['to'] ?? 0)) ?> de <?= htmlspecialchars((string) ($subscriptionHistoryPagination['total'] ?? 0)) ?> registros
                                    <?php else: ?>
                                        Nenhum registro encontrado
                                    <?php endif; ?>
                                </div>
                                <div class="sp-pagination-controls">
                                    <a class="sp-page-btn<?= $historyPage <= 1 ? ' is-disabled' : '' ?>" href="<?= htmlspecialchars($buildSubscriptionUrl(['subscription_history_page' => max(1, $historyPage - 1)])) ?>">Anterior</a>
                                    <?php foreach ($historyPages as $pageNumber): ?>
                                        <a class="sp-page-btn<?= $pageNumber === $historyPage ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildSubscriptionUrl(['subscription_history_page' => $pageNumber])) ?>"><?= htmlspecialchars((string) $pageNumber) ?></a>
                                    <?php endforeach; ?>
                                    <a class="sp-page-btn<?= $historyPage >= $historyLastPage ? ' is-disabled' : '' ?>" href="<?= htmlspecialchars($buildSubscriptionUrl(['subscription_history_page' => min($historyLastPage, $historyPage + 1)])) ?>">Próxima</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sp-column">
                    <div class="card">
                        <div class="sp-head">
                            <div>
                                <h3>Resumo da assinatura</h3>
                                <p class="sp-note">Informações essenciais para acompanhamento rápido.</p>
                            </div>
                        </div>

                        <div class="sp-info" style="margin-top:12px">
                            <div class="sp-row">
                                <span>Status local</span>
                                <strong><?= htmlspecialchars(status_label('subscription_status', (string) ($subscription['status'] ?? ''))) ?></strong>
                            </div>
                            <div class="sp-row">
                                <span>Status da empresa</span>
                                <strong><?= htmlspecialchars(status_label('company_subscription_status', (string) ($subscription['company_subscription_status'] ?? ''))) ?></strong>
                            </div>
                            <div class="sp-row">
                                <span>Próximo vencimento</span>
                                <strong><?= htmlspecialchars($formatSubscriptionDate($nextDueDate)) ?></strong>
                            </div>
                            <div class="sp-row">
                                <span>Pagamento preferencial</span>
                                <strong><?= htmlspecialchars($paymentMethodLabels[(string) ($subscription['preferred_payment_method'] ?? '')] ?? 'Não definido') ?></strong>
                            </div>
                            <div class="sp-row">
                                <span>Empresa bloqueada</span>
                                <strong><?= !empty($subscriptionBillingAccess['is_blocked']) ? 'Sim' : 'Não' ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="sp-head">
                            <div>
                                <h3>Recursos do plano</h3>
                                <p class="sp-note">Resumo comercial do que está contratado.</p>
                            </div>
                        </div>

                        <?php if ($subscriptionFeatures === []): ?>
                            <div class="empty-state" style="margin-top:12px">
                                Nenhum recurso resumido foi encontrado para este plano.
                            </div>
                        <?php else: ?>
                            <div class="sp-feature-list" style="margin-top:12px">
                                <?php foreach ($subscriptionFeatures as $feature): ?>
                                    <span class="sp-feature"><?= htmlspecialchars((string) $feature) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card">
                        <div class="sp-head">
                            <div>
                                <h3>Regra operacional</h3>
                                <p class="sp-note">Fluxo enxuto para a empresa e controle local para o SaaS.</p>
                            </div>
                        </div>

                        <div class="sp-flow" style="margin-top:12px">
                            <div class="sp-step">
                                <div class="sp-step-num">A</div>
                                <div>
                                    <strong>Sistema confirma automaticamente</strong>
                                    <p>A tela da empresa e a rotina do servidor consultam o gateway para marcar a cobrança como paga sem depender de ação manual em toda cobrança.</p>
                                </div>
                            </div>
                            <div class="sp-step">
                                <div class="sp-step-num">B</div>
                                <div>
                                    <strong>SaaS administra só as exceções</strong>
                                    <p>Quando precisar, o administrador acompanha atraso, sincroniza divergências e trata apenas os casos fora do fluxo normal.</p>
                                </div>
                            </div>
                            <div class="sp-step">
                                <div class="sp-step-num">C</div>
                                <div>
                                    <strong>SaaS administra os casos em atraso</strong>
                                    <p>Quando precisar, o administrador acompanha e regulariza cobranças no painel central.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($shouldAutoPollPixStatus): ?>
        <script>
            (function () {
                const paymentId = <?= json_encode($currentChargeId) ?>;
                const endpoint = <?= json_encode(base_url('/admin/dashboard/subscription/pix/status?subscription_payment_id=' . $currentChargeId)) ?>;
                const note = document.getElementById('pix-auto-status-note');
                let polling = false;

                const poll = async () => {
                    if (polling) {
                        return;
                    }

                    polling = true;
                    try {
                        const response = await fetch(endpoint, {
                            method: 'GET',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json'
                            }
                        });

                        const payload = await response.json();
                        if (!payload || payload.ok !== true) {
                            if (note && payload && payload.message) {
                                note.textContent = payload.message;
                            }
                            return;
                        }

                        if ((payload.payment_id || 0) !== paymentId) {
                            return;
                        }

                        if (note && payload.status_label) {
                            note.textContent = 'Status atual: ' + payload.status_label + (payload.gateway_status ? ' no gateway: ' + payload.gateway_status : '');
                        }

                        if (payload.status === 'pago') {
                            window.location.reload();
                        }
                    } catch (error) {
                        if (note) {
                            note.textContent = 'Falha ao consultar automaticamente o pagamento. Tente atualizar a tela em alguns instantes.';
                        }
                    } finally {
                        polling = false;
                    }
                };

                window.setInterval(poll, 20000);
                window.setTimeout(poll, 8000);
            })();
        </script>
    <?php endif; ?>
</section>
