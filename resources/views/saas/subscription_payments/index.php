<?php
$filters = is_array($filters ?? null) ? $filters : [];
$search = trim((string) ($filters['search'] ?? ''));
$status = trim((string) ($filters['status'] ?? ''));
$statusOptions = [
    '' => 'Todos os status',
    'pendente' => 'Pendentes',
    'vencido' => 'Vencidas',
    'pago' => 'Pagas',
    'cancelado' => 'Canceladas',
];
$formatMoney = static fn (mixed $value): string => 'R$ ' . number_format((float) $value, 2, ',', '.');
$formatDate = static function (mixed $value): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '-';
    }
    $timestamp = strtotime($raw);
    return $timestamp === false ? $raw : date('d/m/Y', $timestamp);
};
?>

<style>
    .spx-shell{display:grid;gap:16px}
    .spx-topbar{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
    .spx-topbar h1{margin:0}
    .spx-topbar p{margin:4px 0 0;color:#64748b}
    .spx-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}
    .spx-kpi{border:1px solid #dbeafe;border-radius:14px;background:linear-gradient(180deg,#fff 0%,#eff6ff 100%);padding:14px}
    .spx-kpi span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
    .spx-kpi strong{display:block;margin-top:6px;font-size:22px;color:#0f172a}
    .spx-kpi small{display:block;margin-top:4px;color:#475569}
    .spx-filter{display:grid;grid-template-columns:1.6fr 1fr auto;gap:10px;align-items:end}
    .spx-filter .field{margin:0}
    .spx-list{display:grid;gap:12px}
    .spx-item{border:1px solid #dbeafe;border-radius:14px;background:#fff;padding:14px;display:grid;gap:12px}
    .spx-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
    .spx-company strong{display:block;color:#0f172a}
    .spx-company small{display:block;margin-top:4px;color:#64748b}
    .spx-meta{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
    .spx-meta-box{border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;padding:10px}
    .spx-meta-box span{display:block;font-size:11px;text-transform:uppercase;color:#64748b}
    .spx-meta-box strong{display:block;margin-top:4px;font-size:13px;color:#0f172a}
    .spx-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:end}
    .spx-actions form{margin:0}
    .spx-paid{color:#166534;font-weight:700}
    @media (max-width:1100px){.spx-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
    @media (max-width:800px){.spx-grid,.spx-meta,.spx-filter{grid-template-columns:1fr}}
</style>

<div class="spx-shell">
    <div class="spx-topbar">
        <div>
            <h1>Pagamentos das assinaturas</h1>
            <p>Operacao simplificada: acompanhe as cobrancas em aberto, registre pagamento e trate atraso sem navegar por telas confusas.</p>
        </div>
        <a class="btn" href="<?= htmlspecialchars(base_url('/saas/subscription-payments/create')) ?>">Nova cobranca PIX</a>
    </div>

    <div class="spx-grid">
        <div class="spx-kpi">
            <span>Total de cobrancas</span>
            <strong><?= (int) ($summary['total_charges'] ?? 0) ?></strong>
            <small>Historico completo</small>
        </div>
        <div class="spx-kpi">
            <span>Pendentes</span>
            <strong><?= (int) ($summary['pending_charges'] ?? 0) ?></strong>
            <small>Aguardando pagamento</small>
        </div>
        <div class="spx-kpi">
            <span>Vencidas</span>
            <strong><?= (int) ($summary['overdue_charges'] ?? 0) ?></strong>
            <small>Exigem tratativa</small>
        </div>
        <div class="spx-kpi">
            <span>Pagas</span>
            <strong><?= (int) ($summary['paid_charges'] ?? 0) ?></strong>
            <small>Ja conciliadas</small>
        </div>
        <div class="spx-kpi">
            <span>Receita recebida</span>
            <strong><?= htmlspecialchars($formatMoney($summary['total_paid_amount'] ?? 0)) ?></strong>
            <small>Total confirmado</small>
        </div>
    </div>

    <div class="card">
        <form method="GET" action="<?= htmlspecialchars(base_url('/saas/subscription-payments')) ?>">
            <div class="spx-filter">
                <div class="field">
                    <label for="saas_payment_search">Buscar empresa, plano ou referencia</label>
                    <input id="saas_payment_search" name="search" type="text" value="<?= htmlspecialchars($search) ?>" placeholder="Empresa, slug, plano, gateway ou referencia">
                </div>
                <div class="field">
                    <label for="saas_payment_status">Status</label>
                    <select id="saas_payment_status" name="status">
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>" <?= $status === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="spx-actions">
                    <button class="btn" type="submit">Filtrar</button>
                    <a class="btn secondary" href="<?= htmlspecialchars(base_url('/saas/subscription-payments')) ?>">Limpar</a>
                </div>
            </div>
        </form>
    </div>

    <?php if (empty($subscriptionPayments)): ?>
        <div class="card">
            <div class="empty-state">Nenhuma cobranca encontrada com esse filtro.</div>
        </div>
    <?php else: ?>
        <div class="spx-list">
            <?php foreach ($subscriptionPayments as $charge): ?>
                <div class="spx-item">
                    <div class="spx-head">
                        <div class="spx-company">
                            <strong><?= htmlspecialchars((string) $charge['company_name']) ?></strong>
                            <small><?= htmlspecialchars((string) $charge['company_slug']) ?> • <?= htmlspecialchars((string) $charge['plan_name']) ?> • <?= htmlspecialchars(status_label('billing_cycle', $charge['billing_cycle'] ?? null)) ?></small>
                        </div>
                        <span class="badge <?= htmlspecialchars(status_badge_class('subscription_payment_status', $charge['status'] ?? null)) ?>">
                            <?= htmlspecialchars(status_label('subscription_payment_status', $charge['status'] ?? null)) ?>
                        </span>
                    </div>

                    <div class="spx-meta">
                        <div class="spx-meta-box">
                            <span>Referencia</span>
                            <strong><?= str_pad((string) (int) $charge['reference_month'], 2, '0', STR_PAD_LEFT) ?>/<?= (int) $charge['reference_year'] ?></strong>
                        </div>
                        <div class="spx-meta-box">
                            <span>Vencimento</span>
                            <strong><?= htmlspecialchars($formatDate($charge['due_date'] ?? null)) ?></strong>
                        </div>
                        <div class="spx-meta-box">
                            <span>Valor</span>
                            <strong><?= htmlspecialchars($formatMoney($charge['amount'] ?? 0)) ?></strong>
                        </div>
                        <div class="spx-meta-box">
                            <span>Ultima referencia</span>
                            <strong><?= htmlspecialchars((string) ($charge['transaction_reference'] ?? '-')) ?></strong>
                        </div>
                    </div>

                    <div class="spx-actions">
                        <?php if ((string) $charge['status'] !== 'pago' && (string) $charge['status'] !== 'cancelado'): ?>
                            <form method="POST" action="<?= htmlspecialchars(base_url('/saas/subscription-payments/mark-paid')) ?>">
                                <?= form_security_fields('saas.subscription_payments.mark_paid.' . (int) $charge['id']) ?>
                                <input type="hidden" name="subscription_payment_id" value="<?= (int) $charge['id'] ?>">
                                <input type="hidden" name="payment_method" value="pix">
                                <input type="text" name="transaction_reference" placeholder="Comprovante ou txid (opcional)">
                                <button class="btn" type="submit">Registrar como pago</button>
                            </form>

                            <form method="POST" action="<?= htmlspecialchars(base_url('/saas/subscription-payments/mark-overdue')) ?>">
                                <?= form_security_fields('saas.subscription_payments.mark_overdue.' . (int) $charge['id']) ?>
                                <input type="hidden" name="subscription_payment_id" value="<?= (int) $charge['id'] ?>">
                                <button class="btn secondary" type="submit">Marcar em atraso</button>
                            </form>

                            <form method="POST" action="<?= htmlspecialchars(base_url('/saas/subscription-payments/cancel')) ?>" onsubmit="return confirm('Cancelar esta cobranca?');">
                                <?= form_security_fields('saas.subscription_payments.cancel.' . (int) $charge['id']) ?>
                                <input type="hidden" name="subscription_payment_id" value="<?= (int) $charge['id'] ?>">
                                <button class="btn secondary" type="submit">Cancelar cobranca</button>
                            </form>
                        <?php else: ?>
                            <span class="<?= (string) $charge['status'] === 'pago' ? 'spx-paid' : 'muted' ?>">
                                <?= (string) $charge['status'] === 'pago' ? 'Pagamento ja conciliado' : 'Cobranca encerrada' ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
