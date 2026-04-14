<?php
$openCashRegister = is_array($openCashRegister ?? null) ? $openCashRegister : null;
$cashRegisters = is_array($cashRegisters ?? null) ? $cashRegisters : [];

$formatMoney = static fn (mixed $value): string => 'R$ ' . number_format((float) $value, 2, ',', '.');
$totalRegisters = count($cashRegisters);
$openCount = 0;
$closedCount = 0;
$totalIncome = 0.0;
$totalExpense = 0.0;
$totalAdjustment = 0.0;
$lastClosed = null;

foreach ($cashRegisters as $cashRegister) {
    if (!is_array($cashRegister)) {
        continue;
    }

    $status = trim((string) ($cashRegister['status'] ?? ''));
    if ($status === 'open') {
        $openCount++;
    }
    if ($status === 'closed') {
        $closedCount++;
        if ($lastClosed === null) {
            $lastClosed = $cashRegister;
        }
    }

    $totalIncome += (float) ($cashRegister['total_income'] ?? 0);
    $totalExpense += (float) ($cashRegister['total_expense'] ?? 0);
    $totalAdjustment += (float) ($cashRegister['total_adjustment'] ?? 0);
}

$openCurrentBalance = $openCashRegister !== null
    ? (float) ($openCashRegister['current_calculated_amount'] ?? 0)
    : 0.0;
?>

<style>
    body.modal-open{overflow:hidden}
    .cash-page{display:grid;gap:16px}
    .cash-topbar p{margin:6px 0 0;color:#475569}

    .cash-kpi-grid{display:grid;grid-template-columns:repeat(6,minmax(130px,1fr));gap:12px}
    .cash-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px;box-shadow:0 8px 20px rgba(15,23,42,.05)}
    .cash-kpi strong{display:block;font-size:22px;line-height:1.1;color:#0f172a}
    .cash-kpi span{display:block;margin-top:6px;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.04em}

    .cash-live-card{display:grid;gap:12px}
    .cash-live-atm{
        background:
            radial-gradient(circle at 18% 0%, rgba(96,165,250,.22), transparent 36%),
            linear-gradient(160deg,#111827 0%,#1f2937 45%,#334155 100%);
        border:1px solid #0f172a;
        color:#e2e8f0;
    }
    .cash-live-atm .cash-live-head h3{color:#f8fafc}
    .cash-live-atm .cash-live-head p{color:#cbd5e1}
    .cash-live-atm .cash-live-item{border:1px solid rgba(147,197,253,.45);background:rgba(15,23,42,.5)}
    .cash-live-atm .cash-live-item span{color:#94a3b8}
    .cash-live-atm .cash-live-item strong{color:#e2e8f0}
    .cash-live-atm .field label{color:#e2e8f0}
    .cash-live-atm input{background:#f8fafc}
    .cash-live-atm .btn{background:#2563eb}
    .cash-live-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap}
    .cash-live-head-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .cash-live-head h3{margin:0;color:#0f172a}
    .cash-live-head p{margin:6px 0 0;color:#475569;font-size:13px}
    .cash-live-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}
    .cash-live-item{border:1px solid #dbeafe;background:#f8fafc;border-radius:10px;padding:8px}
    .cash-live-item span{display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
    .cash-live-item strong{display:block;font-size:13px;color:#0f172a;word-break:break-word}
    .cash-live-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .cash-form-actions{margin-top:10px;display:flex;gap:8px;flex-wrap:wrap}

    .cash-open-card{display:grid;gap:12px}
    .cash-open-card p{margin:0;color:#475569}
    .cash-open-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}

    .cash-filter-card{display:grid;gap:10px}
    .cash-filter-grid{display:grid;grid-template-columns:minmax(220px,1fr) minmax(170px,220px) minmax(180px,220px) auto;gap:10px;align-items:end}
    .cash-filter-note{margin:0;color:#64748b;font-size:12px}

    .cash-board{display:grid;grid-template-columns:repeat(auto-fill,minmax(315px,1fr));gap:12px}
    .cash-card{background:#fff;border:1px solid #dbeafe;border-radius:12px;padding:12px;display:grid;gap:10px;box-shadow:0 10px 18px rgba(15,23,42,.06)}
    .cash-card.status-open{border-left:4px solid #16a34a}
    .cash-card.status-closed{border-left:4px solid #475569}
    .cash-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:8px}
    .cash-card-head strong{display:block;font-size:16px;color:#0f172a}
    .cash-card-head span{font-size:12px;color:#64748b}
    .cash-card-value{font-size:18px;font-weight:700;color:#0f172a;text-align:right}
    .cash-meta-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    .cash-meta-item{border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px;padding:8px}
    .cash-meta-item span{display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
    .cash-meta-item strong{display:block;font-size:13px;color:#0f172a;word-break:break-word}
    .cash-card-footer{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap}

    .cash-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .cash-page-btn{border:1px solid #cbd5e1;background:#fff;color:#0f172a;border-radius:8px;padding:7px 10px;cursor:pointer;min-width:36px}
    .cash-page-btn[disabled]{opacity:.5;cursor:not-allowed}
    .cash-page-btn.is-active{background:#1d4ed8;border-color:#1d4ed8;color:#fff}

    .cash-modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.6);display:grid;place-items:center;padding:14px;z-index:1300}
    .cash-modal-backdrop[hidden]{display:none !important}
    .cash-modal{width:min(920px,calc(100vw - 28px));max-height:calc(100vh - 28px);overflow:auto;background:#fff;border:1px solid #cbd5e1;border-radius:14px;padding:14px;display:grid;gap:12px}
    .cash-modal-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap}
    .cash-modal-head h3{margin:0;color:#0f172a}
    .cash-modal-head p{margin:6px 0 0;color:#64748b;font-size:13px}
    .cash-modal-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}
    .cash-modal-box{border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px;padding:8px}
    .cash-modal-box span{display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
    .cash-modal-box strong{display:block;font-size:13px;color:#0f172a;word-break:break-word}
    .cash-modal-note{border:1px dashed #cbd5e1;background:#fff;border-radius:10px;padding:10px;font-size:12px;color:#475569}
    .cash-modal-actions{display:flex;justify-content:space-between;gap:8px;align-items:center;margin-top:10px;flex-wrap:wrap}

    @media (max-width:1200px){
        .cash-kpi-grid{grid-template-columns:repeat(3,minmax(130px,1fr))}
    }
    @media (max-width:980px){
        .cash-live-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
        .cash-filter-grid{grid-template-columns:1fr 1fr}
        .cash-modal-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media (max-width:700px){
        .cash-kpi-grid{grid-template-columns:repeat(2,minmax(130px,1fr))}
        .cash-board{grid-template-columns:1fr}
        .cash-meta-grid,.cash-live-grid,.cash-live-form-grid,.cash-open-form-grid,.cash-filter-grid,.cash-modal-grid{grid-template-columns:1fr}
    }
</style>

<div class="cash-page">
    <div class="topbar cash-topbar">
        <div>
            <h1>Painel de Caixa</h1>
            <p>Operacao de abertura/fechamento e historico no mesmo padrao moderno da pagina de pagamentos.</p>
        </div>
        <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/payments')) ?>">Ver pagamentos</a>
    </div>

    <section class="cash-kpi-grid">
        <article class="cash-kpi"><strong><?= $totalRegisters ?></strong><span>Caixas registrados</span></article>
        <article class="cash-kpi"><strong><?= $openCount ?></strong><span>Abertos</span></article>
        <article class="cash-kpi"><strong><?= $closedCount ?></strong><span>Fechados</span></article>
        <article class="cash-kpi"><strong><?= $formatMoney($totalIncome) ?></strong><span>Entradas</span></article>
        <article class="cash-kpi"><strong><?= $formatMoney($totalExpense) ?></strong><span>Saidas</span></article>
        <article class="cash-kpi"><strong><?= $openCashRegister ? $formatMoney($openCurrentBalance) : ($lastClosed ? $formatMoney($lastClosed['closing_amount_calculated'] ?? 0) : 'R$ 0,00') ?></strong><span><?= $openCashRegister ? 'Saldo atual' : 'Ultimo saldo' ?></span></article>
    </section>

    <?php if ($openCashRegister !== null): ?>
        <?php
        $liveCashId = (int) ($openCashRegister['id'] ?? 0);
        $liveOpenedAt = (string) ($openCashRegister['opened_at'] ?? '-');
        $liveOpenedBy = (string) ($openCashRegister['opened_by_user_name'] ?? '-');
        $liveOpeningAmount = (float) ($openCashRegister['opening_amount'] ?? 0);
        $liveIncome = (float) ($openCashRegister['total_income'] ?? 0);
        $liveExpense = (float) ($openCashRegister['total_expense'] ?? 0);
        $liveAdjustment = (float) ($openCashRegister['total_adjustment'] ?? 0);
        $liveCurrent = (float) ($openCashRegister['current_calculated_amount'] ?? 0);
        ?>
        <section class="card cash-live-card cash-live-atm">
            <div class="cash-live-head">
                <div>
                    <h3>Caixa aberto</h3>
                    <p>Acompanhe o saldo em tempo real e feche com conferência financeira.</p>
                </div>
                <div class="cash-live-head-actions">
                    <span class="badge <?= htmlspecialchars(status_badge_class('cash_register_status', 'open')) ?>">Aberto</span>
                    <?php if ($liveCashId > 0): ?>
                        <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/cash-registers/print-ticket?cash_register_id=' . $liveCashId)) ?>">Gerar ticket</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="cash-live-grid">
                <div class="cash-live-item"><span>Abertura</span><strong><?= htmlspecialchars($liveOpenedAt) ?></strong></div>
                <div class="cash-live-item"><span>Aberto por</span><strong><?= htmlspecialchars($liveOpenedBy !== '' ? $liveOpenedBy : '-') ?></strong></div>
                <div class="cash-live-item"><span>Valor inicial</span><strong><?= $formatMoney($liveOpeningAmount) ?></strong></div>
                <div class="cash-live-item"><span>Saldo atual</span><strong><?= $formatMoney($liveCurrent) ?></strong></div>
                <div class="cash-live-item"><span>Entradas</span><strong><?= $formatMoney($liveIncome) ?></strong></div>
                <div class="cash-live-item"><span>Saidas</span><strong><?= $formatMoney($liveExpense) ?></strong></div>
                <div class="cash-live-item"><span>Ajustes</span><strong><?= $formatMoney($liveAdjustment) ?></strong></div>
            </div>

            <form method="POST" action="<?= htmlspecialchars(base_url('/admin/cash-registers/close')) ?>">
                <?= form_security_fields('cash_registers.close') ?>
                <div class="cash-live-form-grid">
                    <div class="field" style="margin:0">
                        <label for="closing_amount_reported">Valor informado no fechamento (R$)</label>
                        <input id="closing_amount_reported" name="closing_amount_reported" type="number" min="0" step="0.01" value="<?= htmlspecialchars(number_format($liveCurrent, 2, '.', '')) ?>" required>
                    </div>
                    <div class="field" style="margin:0">
                        <label for="close_notes">Observacoes</label>
                        <input id="close_notes" name="notes" type="text" placeholder="Opcional">
                    </div>
                </div>
                <div class="cash-form-actions">
                    <button class="btn" type="submit">Fechar caixa</button>
                    <?php if ($liveCashId > 0): ?>
                        <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/cash-registers/print-ticket?cash_register_id=' . $liveCashId)) ?>">Previa / imprimir ticket</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>
    <?php else: ?>
        <section class="card cash-open-card">
            <h3 style="margin:0">Abrir novo caixa</h3>
            <p>Inicie um novo caixa para liberar registros de pagamento no sistema.</p>
            <form method="POST" action="<?= htmlspecialchars(base_url('/admin/cash-registers/open')) ?>">
                <?= form_security_fields('cash_registers.open') ?>
                <div class="cash-open-form-grid">
                    <div class="field" style="margin:0">
                        <label for="opening_amount">Valor de abertura (R$)</label>
                        <input id="opening_amount" name="opening_amount" type="number" min="0" step="0.01" value="0.00" required>
                    </div>
                    <div class="field" style="margin:0">
                        <label for="open_notes">Observacoes</label>
                        <input id="open_notes" name="notes" type="text" placeholder="Opcional">
                    </div>
                </div>
                <div class="cash-form-actions">
                    <button class="btn" type="submit">Abrir caixa</button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <section class="card cash-filter-card">
        <div class="cash-filter-grid">
            <div class="field" style="margin:0">
                <label for="cashSearch">Busca rapida</label>
                <input id="cashSearch" type="text" placeholder="ID, operador, observacao, data...">
            </div>
            <div class="field" style="margin:0">
                <label for="cashStatusFilter">Status</label>
                <select id="cashStatusFilter">
                    <option value="">Todos</option>
                    <option value="open">Aberto</option>
                    <option value="closed">Fechado</option>
                </select>
            </div>
            <div class="field" style="margin:0">
                <label for="cashPeriodFilter">Periodo</label>
                <select id="cashPeriodFilter">
                    <option value="all">Todos</option>
                    <option value="today">Hoje</option>
                    <option value="yesterday">Ontem</option>
                    <option value="last7">Ultimos 7 dias</option>
                    <option value="last30">Ultimos 30 dias</option>
                    <option value="month_current">Mes atual</option>
                    <option value="month_previous">Mes anterior</option>
                    <option value="year_current">Ano atual</option>
                </select>
            </div>
            <button id="cashClearFilters" class="btn secondary" type="button">Limpar</button>
        </div>
        <p id="cashFilterInfo" class="cash-filter-note">Historico no padrao operacional de pagamentos: filtros + cards + detalhes.</p>
    </section>

    <?php if (empty($cashRegisters)): ?>
        <div class="payments-empty">Nenhum caixa registrado.</div>
    <?php else: ?>
        <section class="cash-board" id="cashBoard">
            <?php foreach ($cashRegisters as $cashRegister): ?>
                <?php
                if (!is_array($cashRegister)) {
                    continue;
                }

                $cashId = (int) ($cashRegister['id'] ?? 0);
                if ($cashId <= 0) {
                    continue;
                }

                $status = trim((string) ($cashRegister['status'] ?? 'closed'));
                $openedAt = trim((string) ($cashRegister['opened_at'] ?? ''));
                $closedAt = trim((string) ($cashRegister['closed_at'] ?? ''));
                $openingAmount = (float) ($cashRegister['opening_amount'] ?? 0);
                $income = (float) ($cashRegister['total_income'] ?? 0);
                $expense = (float) ($cashRegister['total_expense'] ?? 0);
                $adjustment = (float) ($cashRegister['total_adjustment'] ?? 0);
                $calculated = $cashRegister['closing_amount_calculated'] !== null
                    ? (float) $cashRegister['closing_amount_calculated']
                    : round($openingAmount + $income - $expense + $adjustment, 2);
                $reported = $cashRegister['closing_amount_reported'] !== null
                    ? (float) $cashRegister['closing_amount_reported']
                    : null;
                $difference = $reported !== null ? round($reported - $calculated, 2) : null;
                $openedBy = trim((string) ($cashRegister['opened_by_user_name'] ?? ''));
                $closedBy = trim((string) ($cashRegister['closed_by_user_name'] ?? ''));
                $notes = trim((string) ($cashRegister['notes'] ?? ''));

                $searchText = implode(' ', [
                    (string) $cashId,
                    (string) $status,
                    (string) $openedAt,
                    (string) $closedAt,
                    (string) $openedBy,
                    (string) $closedBy,
                    (string) $notes,
                ]);
                ?>
                <article
                    class="cash-card status-<?= htmlspecialchars($status) ?>"
                    data-cash-card
                    data-status="<?= htmlspecialchars($status) ?>"
                    data-opened-at="<?= htmlspecialchars($openedAt) ?>"
                    data-search="<?= htmlspecialchars($searchText) ?>"
                >
                    <header class="cash-card-head">
                        <div>
                            <strong>Caixa #<?= $cashId ?></strong>
                            <span><?= htmlspecialchars($openedAt !== '' ? $openedAt : '-') ?></span>
                        </div>
                        <div class="cash-card-value"><?= $formatMoney($calculated) ?></div>
                    </header>

                    <div class="cash-meta-grid">
                        <div class="cash-meta-item">
                            <span>Status</span>
                            <strong><span class="badge <?= htmlspecialchars(status_badge_class('cash_register_status', $status)) ?>"><?= htmlspecialchars(status_label('cash_register_status', $status)) ?></span></strong>
                        </div>
                        <div class="cash-meta-item">
                            <span>Abertura</span>
                            <strong><?= $formatMoney($openingAmount) ?></strong>
                        </div>
                        <div class="cash-meta-item">
                            <span>Entradas</span>
                            <strong><?= $formatMoney($income) ?></strong>
                        </div>
                        <div class="cash-meta-item">
                            <span>Saidas</span>
                            <strong><?= $formatMoney($expense) ?></strong>
                        </div>
                        <div class="cash-meta-item">
                            <span>Ajustes</span>
                            <strong><?= $formatMoney($adjustment) ?></strong>
                        </div>
                        <div class="cash-meta-item">
                            <span>Fechamento informado</span>
                            <strong><?= $reported !== null ? $formatMoney($reported) : '-' ?></strong>
                        </div>
                        <div class="cash-meta-item" style="grid-column:1 / -1">
                            <span>Diferenca (informado - calculado)</span>
                            <strong><?= $difference !== null ? $formatMoney($difference) : '-' ?></strong>
                        </div>
                    </div>

                    <footer class="cash-card-footer">
                        <span class="badge status-default">ID: <?= $cashId ?></span>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/cash-registers/print-ticket?cash_register_id=' . $cashId)) ?>">Ticket</a>
                            <button class="btn secondary" type="button" data-open-cash-modal="<?= $cashId ?>">Detalhes</button>
                        </div>
                    </footer>
                </article>

                <template id="cash-modal-template-<?= $cashId ?>">
                    <div class="cash-modal-head">
                        <div>
                            <h3>Caixa #<?= $cashId ?></h3>
                            <p>Abertura em <?= htmlspecialchars($openedAt !== '' ? $openedAt : '-') ?><?= $closedAt !== '' ? ' | Fechamento em ' . htmlspecialchars($closedAt) : '' ?></p>
                        </div>
                        <span class="badge <?= htmlspecialchars(status_badge_class('cash_register_status', $status)) ?>"><?= htmlspecialchars(status_label('cash_register_status', $status)) ?></span>
                    </div>

                    <div class="cash-modal-grid">
                        <div class="cash-modal-box"><span>Aberto por</span><strong><?= htmlspecialchars($openedBy !== '' ? $openedBy : '-') ?></strong></div>
                        <div class="cash-modal-box"><span>Fechado por</span><strong><?= htmlspecialchars($closedBy !== '' ? $closedBy : '-') ?></strong></div>
                        <div class="cash-modal-box"><span>Valor inicial</span><strong><?= $formatMoney($openingAmount) ?></strong></div>
                        <div class="cash-modal-box"><span>Saldo calculado</span><strong><?= $formatMoney($calculated) ?></strong></div>
                        <div class="cash-modal-box"><span>Entradas</span><strong><?= $formatMoney($income) ?></strong></div>
                        <div class="cash-modal-box"><span>Saidas</span><strong><?= $formatMoney($expense) ?></strong></div>
                        <div class="cash-modal-box"><span>Ajustes</span><strong><?= $formatMoney($adjustment) ?></strong></div>
                        <div class="cash-modal-box"><span>Fechamento informado</span><strong><?= $reported !== null ? $formatMoney($reported) : '-' ?></strong></div>
                    </div>

                    <div class="cash-modal-note">
                        <strong>Diferenca:</strong> <?= $difference !== null ? $formatMoney($difference) : '-' ?><br>
                        <strong>Observacoes:</strong> <?= htmlspecialchars($notes !== '' ? $notes : 'Sem observacoes registradas.') ?>
                    </div>

                    <div class="cash-modal-actions">
                        <a class="btn" href="<?= htmlspecialchars(base_url('/admin/cash-registers/print-ticket?cash_register_id=' . $cashId)) ?>">Gerar ticket (prévia/impressão)</a>
                        <button type="button" class="btn secondary" data-close-cash-modal>Fechar</button>
                    </div>
                </template>
            <?php endforeach; ?>
        </section>

        <section class="card cash-pagination" id="cashPagination" hidden>
            <span id="cashPaginationInfo"></span>
            <div id="cashPaginationControls" style="display:flex;gap:6px;flex-wrap:wrap"></div>
        </section>

        <div id="cashNoResults" class="payments-empty" hidden>Nenhum caixa encontrado para os filtros atuais.</div>
    <?php endif; ?>
</div>

<div class="cash-modal-backdrop" id="cashModalRoot" hidden>
    <section class="cash-modal" role="dialog" aria-modal="true">
        <div id="cashModalBody"></div>
    </section>
</div>

<script>
(() => {
    const board = document.getElementById('cashBoard');
    const cards = board ? Array.from(board.querySelectorAll('[data-cash-card]')) : [];
    const searchInput = document.getElementById('cashSearch');
    const statusFilter = document.getElementById('cashStatusFilter');
    const periodFilter = document.getElementById('cashPeriodFilter');
    const clearButton = document.getElementById('cashClearFilters');
    const filterInfo = document.getElementById('cashFilterInfo');
    const noResults = document.getElementById('cashNoResults');
    const pagination = document.getElementById('cashPagination');
    const paginationInfo = document.getElementById('cashPaginationInfo');
    const paginationControls = document.getElementById('cashPaginationControls');
    const modalRoot = document.getElementById('cashModalRoot');
    const modalBody = document.getElementById('cashModalBody');
    const autoRefreshIntervalMs = 30000;
    window.setInterval(() => {
        if (document.hidden) {
            return;
        }
        if (modalRoot && !modalRoot.hidden) {
            return;
        }
        const activeElement = document.activeElement;
        if (activeElement instanceof HTMLElement) {
            const tagName = activeElement.tagName;
            if (tagName === 'INPUT' || tagName === 'TEXTAREA' || tagName === 'SELECT') {
                return;
            }
        }
        window.location.reload();
    }, autoRefreshIntervalMs);

    if (!board || cards.length === 0 || !searchInput || !statusFilter || !periodFilter || !clearButton) {
        return;
    }

    const pageSize = 10;
    let currentPage = 1;

    const normalize = (value) => String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

    const parseDate = (value) => {
        const raw = String(value || '').trim();
        if (raw === '') {
            return null;
        }
        const date = new Date(raw.replace(' ', 'T'));
        return Number.isNaN(date.getTime()) ? null : date;
    };

    const isDateInPeriod = (date, period) => {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return false;
        }
        if (period === 'all') {
            return true;
        }

        const now = new Date();
        const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());

        if (period === 'today') {
            return date >= startOfToday;
        }
        if (period === 'yesterday') {
            const start = new Date(startOfToday);
            start.setDate(start.getDate() - 1);
            return date >= start && date < startOfToday;
        }
        if (period === 'last7') {
            const start = new Date(startOfToday);
            start.setDate(start.getDate() - 6);
            return date >= start;
        }
        if (period === 'last30') {
            const start = new Date(startOfToday);
            start.setDate(start.getDate() - 29);
            return date >= start;
        }
        if (period === 'month_current') {
            return date.getFullYear() === now.getFullYear() && date.getMonth() === now.getMonth();
        }
        if (period === 'month_previous') {
            const previousMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            return date.getFullYear() === previousMonth.getFullYear() && date.getMonth() === previousMonth.getMonth();
        }
        if (period === 'year_current') {
            return date.getFullYear() === now.getFullYear();
        }
        return true;
    };

    const closeModal = () => {
        if (!modalRoot || !modalBody) {
            return;
        }
        modalRoot.hidden = true;
        modalBody.innerHTML = '';
        document.body.classList.remove('modal-open');
    };

    const openModal = (cashId) => {
        if (!modalRoot || !modalBody) {
            return;
        }
        const template = document.getElementById(`cash-modal-template-${cashId}`);
        if (!(template instanceof HTMLTemplateElement)) {
            return;
        }
        modalBody.innerHTML = template.innerHTML;
        modalRoot.hidden = false;
        document.body.classList.add('modal-open');
        Array.from(modalBody.querySelectorAll('[data-close-cash-modal]')).forEach((button) => {
            button.addEventListener('click', closeModal);
        });
    };

    const renderPagination = (totalFiltered) => {
        if (!pagination || !paginationInfo || !paginationControls) {
            return;
        }
        if (totalFiltered <= 0) {
            pagination.hidden = true;
            paginationInfo.textContent = '';
            paginationControls.innerHTML = '';
            return;
        }
        pagination.hidden = false;
        const totalPages = Math.max(1, Math.ceil(totalFiltered / pageSize));
        if (currentPage > totalPages) {
            currentPage = 1;
        }

        const start = (currentPage - 1) * pageSize + 1;
        const end = Math.min(currentPage * pageSize, totalFiltered);
        paginationInfo.textContent = `Mostrando ${start}-${end} de ${totalFiltered} caixa(s).`;
        paginationControls.innerHTML = '';

        const addButton = (label, page, disabled, active = false) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `cash-page-btn${active ? ' is-active' : ''}`;
            button.textContent = label;
            button.disabled = disabled;
            button.addEventListener('click', () => {
                if (!disabled && page !== currentPage) {
                    currentPage = page;
                    applyFilter(false);
                }
            });
            paginationControls.appendChild(button);
        };

        addButton('Anterior', Math.max(1, currentPage - 1), currentPage <= 1);
        const maxButtons = 5;
        const half = Math.floor(maxButtons / 2);
        let first = Math.max(1, currentPage - half);
        let last = Math.min(totalPages, first + maxButtons - 1);
        first = Math.max(1, last - maxButtons + 1);
        for (let page = first; page <= last; page += 1) {
            addButton(String(page), page, false, page === currentPage);
        }
        addButton('Proxima', Math.min(totalPages, currentPage + 1), currentPage >= totalPages);
    };

    const applyFilter = (resetPage = true) => {
        const searchText = normalize(searchInput.value);
        const statusValue = normalize(statusFilter.value);
        const periodValue = normalize(periodFilter.value || 'all');

        const filtered = cards.filter((card) => {
            const cardSearch = normalize(card.getAttribute('data-search'));
            const cardStatus = normalize(card.getAttribute('data-status'));
            const cardDate = parseDate(card.getAttribute('data-opened-at'));
            return (searchText === '' || cardSearch.includes(searchText))
                && (statusValue === '' || cardStatus === statusValue)
                && isDateInPeriod(cardDate, periodValue === '' ? 'all' : periodValue);
        });

        if (resetPage) {
            currentPage = 1;
        }

        const visible = filtered.slice((currentPage - 1) * pageSize, (currentPage - 1) * pageSize + pageSize);
        const visibleSet = new Set(visible);

        cards.forEach((card) => {
            card.style.display = visibleSet.has(card) ? '' : 'none';
        });

        if (filterInfo) {
            if (searchText === '' && statusValue === '' && periodValue === 'all') {
                filterInfo.textContent = 'Historico no padrao operacional de pagamentos: filtros + cards + detalhes.';
            } else if (filtered.length > 0) {
                filterInfo.textContent = `Filtro ativo: ${filtered.length} caixa(s) encontrado(s).`;
            } else {
                filterInfo.textContent = 'Nenhum caixa encontrado para os filtros atuais.';
            }
        }

        if (noResults) {
            noResults.hidden = filtered.length > 0;
        }

        renderPagination(filtered.length);
    };

    searchInput.addEventListener('input', () => applyFilter(true));
    statusFilter.addEventListener('change', () => applyFilter(true));
    periodFilter.addEventListener('change', () => applyFilter(true));
    clearButton.addEventListener('click', () => {
        searchInput.value = '';
        statusFilter.value = '';
        periodFilter.value = 'all';
        applyFilter(true);
        searchInput.focus();
    });

    Array.from(document.querySelectorAll('[data-open-cash-modal]')).forEach((button) => {
        button.addEventListener('click', () => {
            const cashId = Number(button.getAttribute('data-open-cash-modal') || 0);
            if (cashId > 0) {
                openModal(cashId);
            }
        });
    });

    if (modalRoot) {
        modalRoot.addEventListener('click', (event) => {
            if (event.target === modalRoot) {
                closeModal();
            }
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modalRoot && !modalRoot.hidden) {
            closeModal();
        }
    });

    applyFilter(true);
})();
</script>
