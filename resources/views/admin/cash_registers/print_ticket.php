<?php
$context = is_array($context ?? null) ? $context : [];
$cashRegister = is_array($context['cash_register'] ?? null) ? $context['cash_register'] : [];
$generatedAt = (string) ($context['generated_at'] ?? date('Y-m-d H:i:s'));

$companyName = trim((string) ($cashRegister['company_name'] ?? 'Empresa'));
$companyLogoPath = trim((string) ($cashRegister['company_logo_path'] ?? ''));
$companyLogoUrl = $companyLogoPath !== '' ? company_image_url($companyLogoPath) : '';

$cashId = (int) ($cashRegister['id'] ?? 0);
$status = (string) ($cashRegister['status'] ?? 'closed');
$openedAt = (string) ($cashRegister['opened_at'] ?? '-');
$closedAt = (string) ($cashRegister['closed_at'] ?? '-');
$openedBy = trim((string) ($cashRegister['opened_by_user_name'] ?? ''));
$closedBy = trim((string) ($cashRegister['closed_by_user_name'] ?? ''));
$notes = trim((string) ($cashRegister['notes'] ?? ''));

$openingAmount = (float) ($cashRegister['opening_amount'] ?? 0);
$income = (float) ($cashRegister['total_income'] ?? 0);
$expense = (float) ($cashRegister['total_expense'] ?? 0);
$adjustment = (float) ($cashRegister['total_adjustment'] ?? 0);
$calculated = (float) ($cashRegister['current_calculated_amount'] ?? 0);
$reported = $cashRegister['closing_amount_reported'] !== null ? (float) $cashRegister['closing_amount_reported'] : null;
$difference = $cashRegister['difference_amount'] !== null ? (float) $cashRegister['difference_amount'] : null;
?>

<style>
    .cash-ticket-page{display:grid;gap:16px}
    .cash-ticket-screen-only{display:block}
    .cash-ticket-actions{display:flex;gap:8px;flex-wrap:wrap}
    .cash-ticket-layout{display:grid;grid-template-columns:minmax(260px,340px) minmax(0,1fr);gap:16px}
    .cash-ticket-summary{display:grid;gap:10px}
    .cash-ticket-summary-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
    .cash-ticket-summary-box{border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;padding:8px}
    .cash-ticket-summary-box strong{display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
    .cash-ticket-summary-box span{display:block;font-size:13px;color:#0f172a;word-break:break-word}
    .cash-ticket-sheet-card{padding:16px !important}
    .cash-ticket-sheet-stage{background:linear-gradient(180deg,#f8fafc 0%,#f1f5f9 100%);border:1px solid #e2e8f0;border-radius:12px;padding:14px;display:grid;place-items:center}
    .cash-paper{width:80mm;max-width:100%;margin:0 auto;background:#fff;border:1px solid #d1d5db;box-shadow:0 10px 24px rgba(15,23,42,.08);padding:8px 8px 10px;font-family:"Courier New",Courier,monospace;font-size:12px;color:#0f172a;line-height:1.25}
    .cash-center{text-align:center}
    .cash-logo{width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb}
    .cash-title{font-size:14px;font-weight:700;margin-top:4px}
    .cash-divider{border-top:1px dashed #64748b;margin:6px 0}
    .cash-row{display:flex;justify-content:space-between;gap:8px}
    .cash-row > span,.cash-row > strong{min-width:0;word-break:break-word}
    .cash-muted{color:#475569}
    .cash-total{font-size:14px;font-weight:700}
    .cash-note{font-size:11px;color:#334155;word-break:break-word}

    @page { size: 80mm auto; margin: 4mm; }
    @media (max-width:960px){.cash-ticket-layout{grid-template-columns:1fr}.cash-ticket-summary-grid{grid-template-columns:1fr}}
    @media print {
        html,body{margin:0 !important;padding:0 !important;background:#fff !important}
        body{background:#fff}
        .shell{display:block !important}
        .shell > aside{display:none !important}
        .shell-main{display:block !important;min-height:auto !important}
        .shell-header,.shell-footer,.flash{display:none !important}
        main{padding:0 !important;overflow:visible !important}
        .card{border:none !important;box-shadow:none !important;background:#fff !important}
        .cash-ticket-screen-only{display:none !important}
        .cash-ticket-page{display:block}
        .cash-ticket-layout{display:block}
        .cash-ticket-sheet-card{padding:0 !important;box-shadow:none !important;background:#fff !important}
        .cash-ticket-sheet-stage{padding:0 !important;border:0 !important;background:#fff !important}
        .cash-paper{border:none;box-shadow:none;width:80mm;max-width:80mm;margin:0 auto;padding:0}
    }
</style>

<div class="cash-ticket-page">
    <div class="topbar cash-ticket-screen-only">
        <div>
            <h1 style="margin:0">Ticket de Caixa</h1>
            <p style="margin:6px 0 0;color:#64748b">Previa para impressao termica 80mm.</p>
        </div>
        <div class="cash-ticket-actions">
            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/cash-registers')) ?>">Voltar para caixa</a>
            <button class="btn" type="button" onclick="window.print()">Imprimir ticket</button>
        </div>
    </div>

    <div class="cash-ticket-layout">
        <section class="card cash-ticket-summary cash-ticket-screen-only">
            <h3 style="margin:0">Caixa #<?= $cashId ?></h3>
            <div>
                <span class="badge <?= htmlspecialchars(status_badge_class('cash_register_status', $status)) ?>"><?= htmlspecialchars(status_label('cash_register_status', $status)) ?></span>
            </div>

            <div class="cash-ticket-summary-grid">
                <div class="cash-ticket-summary-box"><strong>Abertura</strong><span><?= htmlspecialchars($openedAt) ?></span></div>
                <div class="cash-ticket-summary-box"><strong>Fechamento</strong><span><?= htmlspecialchars($closedAt !== '' ? $closedAt : '-') ?></span></div>
                <div class="cash-ticket-summary-box"><strong>Aberto por</strong><span><?= htmlspecialchars($openedBy !== '' ? $openedBy : '-') ?></span></div>
                <div class="cash-ticket-summary-box"><strong>Fechado por</strong><span><?= htmlspecialchars($closedBy !== '' ? $closedBy : '-') ?></span></div>
                <div class="cash-ticket-summary-box"><strong>Saldo calculado</strong><span><?= 'R$ ' . number_format($calculated, 2, ',', '.') ?></span></div>
                <div class="cash-ticket-summary-box"><strong>Saldo informado</strong><span><?= $reported !== null ? 'R$ ' . number_format($reported, 2, ',', '.') : '-' ?></span></div>
            </div>
            <div class="cash-ticket-summary-box"><strong>Observacoes</strong><span><?= htmlspecialchars($notes !== '' ? $notes : 'Sem observacoes') ?></span></div>
        </section>

        <section class="card cash-ticket-sheet-card">
            <div class="cash-ticket-sheet-stage">
                <section class="cash-paper">
                    <div class="cash-center">
                        <?php if ($companyLogoUrl !== ''): ?>
                            <img class="cash-logo" src="<?= htmlspecialchars($companyLogoUrl) ?>" alt="Logo da empresa">
                        <?php endif; ?>
                        <div class="cash-title"><?= htmlspecialchars($companyName) ?></div>
                        <div class="cash-muted">Relatorio de caixa</div>
                    </div>

                    <div class="cash-divider"></div>
                    <div class="cash-row"><span>Caixa</span><strong>#<?= $cashId ?></strong></div>
                    <div class="cash-row"><span>Status</span><span><?= htmlspecialchars(status_label('cash_register_status', $status)) ?></span></div>
                    <div class="cash-row"><span>Abertura</span><span><?= htmlspecialchars($openedAt) ?></span></div>
                    <div class="cash-row"><span>Fechamento</span><span><?= htmlspecialchars($closedAt !== '' ? $closedAt : '-') ?></span></div>
                    <div class="cash-row"><span>Aberto por</span><span><?= htmlspecialchars($openedBy !== '' ? $openedBy : '-') ?></span></div>
                    <div class="cash-row"><span>Fechado por</span><span><?= htmlspecialchars($closedBy !== '' ? $closedBy : '-') ?></span></div>

                    <div class="cash-divider"></div>
                    <div class="cash-row"><span>Valor inicial</span><span><?= 'R$ ' . number_format($openingAmount, 2, ',', '.') ?></span></div>
                    <div class="cash-row"><span>Entradas</span><span><?= 'R$ ' . number_format($income, 2, ',', '.') ?></span></div>
                    <div class="cash-row"><span>Saidas</span><span><?= 'R$ ' . number_format($expense, 2, ',', '.') ?></span></div>
                    <div class="cash-row"><span>Ajustes</span><span><?= 'R$ ' . number_format($adjustment, 2, ',', '.') ?></span></div>
                    <div class="cash-row cash-total"><span>Saldo calculado</span><span><?= 'R$ ' . number_format($calculated, 2, ',', '.') ?></span></div>
                    <div class="cash-row"><span>Saldo informado</span><span><?= $reported !== null ? 'R$ ' . number_format($reported, 2, ',', '.') : '-' ?></span></div>
                    <div class="cash-row"><span>Diferenca</span><span><?= $difference !== null ? 'R$ ' . number_format($difference, 2, ',', '.') : '-' ?></span></div>

                    <?php if ($notes !== ''): ?>
                        <div class="cash-divider"></div>
                        <div><strong>Observacoes</strong></div>
                        <div class="cash-note"><?= htmlspecialchars($notes) ?></div>
                    <?php endif; ?>

                    <div class="cash-divider"></div>
                    <div class="cash-center cash-muted">Gerado em <?= htmlspecialchars($generatedAt) ?></div>
                </section>
            </div>
        </section>
    </div>
</div>
