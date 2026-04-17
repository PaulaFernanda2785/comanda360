<?php
$commands = is_array($commands ?? null) ? $commands : [];
$commandOperational = is_array($commandOperational ?? null) ? $commandOperational : [];

$totalCommands = count($commands);
$tablesInUse = [];
$identifiedCustomers = 0;
$commandsWithoutTable = 0;
$openedByUsers = [];
$operationalSummary = [
    'pending' => 0,
    'received' => 0,
    'preparing' => 0,
    'ready' => 0,
    'delivered' => 0,
];
$paymentSummary = [
    'pending' => 0,
    'partial' => 0,
    'paid' => 0,
];

foreach ($commands as $command) {
    $tableNumber = $command['table_number'] ?? null;
    if ($tableNumber !== null && (int) $tableNumber > 0) {
        $tablesInUse[(int) $tableNumber] = true;
    } else {
        $commandsWithoutTable++;
    }

    $customerName = trim((string) ($command['customer_name'] ?? ''));
    if ($customerName !== '') {
        $identifiedCustomers++;
    }

    $openedByName = trim((string) ($command['opened_by_user_name'] ?? ''));
    if ($openedByName !== '') {
        $openedByUsers[strtolower($openedByName)] = true;
    }
}

foreach ($commandOperational as $row) {
    if (!is_array($row)) {
        continue;
    }
    $statusCounts = is_array($row['status_counts'] ?? null) ? $row['status_counts'] : [];
    foreach ($operationalSummary as $status => $_value) {
        $operationalSummary[$status] += (int) ($statusCounts[$status] ?? 0);
    }

    $paymentCounts = is_array($row['payment_status_counts'] ?? null) ? $row['payment_status_counts'] : [];
    foreach ($paymentSummary as $status => $_value) {
        $paymentSummary[$status] += (int) ($paymentCounts[$status] ?? 0);
    }
}
?>

<style>
    .commands-page{display:grid;gap:16px}
    .kpi-grid{display:grid;grid-template-columns:repeat(5,minmax(130px,1fr));gap:12px}
    .kpi-item{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px}
    .kpi-item strong{display:block;font-size:24px;line-height:1.1}
    .kpi-item span{color:#64748b;font-size:12px}
    .search-row{display:grid;grid-template-columns:1fr auto;gap:8px}
    .search-info{color:#64748b;font-size:12px;margin-top:6px}
    .commands-grid{display:grid;grid-template-columns:repeat(3,minmax(230px,1fr));gap:12px;align-items:start}
    .command-card{background:#fff;border:2px solid #e2e8f0;border-radius:12px;padding:12px;display:grid;gap:10px;align-content:start;align-self:start}
    .command-card.status-op-pending{border-color:#f59e0b}
    .command-card.status-op-received{border-color:#3b82f6}
    .command-card.status-op-preparing{border-color:#6366f1}
    .command-card.status-op-ready{border-color:#22c55e}
    .command-card.status-op-delivered{border-color:#06b6d4}
    .command-card.status-op-active{border-color:#1d4ed8}
    .command-card.status-op-table-livre{border-color:#16a34a}
    .command-card.status-op-table-ocupada{border-color:#dc2626}
    .command-card.status-op-table-aguardando_fechamento{border-color:#d97706}
    .command-card.status-op-table-bloqueada{border-color:#64748b}
    .command-card.status-op-table-sem_mesa{border-color:#94a3b8}
    .command-card.search-hit{outline:2px solid #60a5fa;outline-offset:1px}
    .command-head{display:flex;justify-content:space-between;align-items:flex-start;gap:8px}
    .command-title{font-size:16px;line-height:1.1}
    .command-meta{display:grid;grid-template-columns:1fr 1fr;gap:8px}
    .command-meta-item{border:1px solid #e2e8f0;border-radius:10px;padding:8px;background:#f8fafc}
    .command-meta-item strong{display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
    .command-meta-item span{font-size:13px;color:#0f172a}
    .command-notes{border:1px solid #dbeafe;background:#eff6ff;border-radius:10px;padding:9px}
    .command-notes strong{display:block;font-size:11px;color:#1e3a8a;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
    .command-notes p{margin:0;color:#334155;font-size:13px;line-height:1.35}
    .command-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .command-actions .btn{padding:8px 12px}
    .command-edit-panel{border-top:1px dashed #cbd5e1;padding-top:10px}
    .command-edit-panel summary{cursor:pointer;font-weight:700;color:#0f172a;list-style:none}
    .command-edit-panel summary::-webkit-details-marker{display:none}
    .command-edit-panel summary::after{content:'Expandir edicao';font-size:11px;color:#64748b;margin-left:8px;font-weight:600}
    .command-edit-panel[open] summary::after{content:'Fechar edicao'}
    .command-edit-form{display:grid;gap:10px;margin-top:10px}
    .command-edit-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .command-edit-grid .field{margin:0}
    .command-edit-form .field{margin:0}
    .command-edit-footer{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
    .command-edit-note{font-size:12px;color:#64748b;line-height:1.4;max-width:620px}
    .command-strip{display:flex;gap:6px;flex-wrap:wrap}
    .command-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;align-items:start}
    .command-stat{min-width:0;border:1px solid #e2e8f0;border-radius:10px;padding:9px 10px;background:#f8fafc;display:grid;gap:4px;align-content:start}
    .command-stat strong{font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;line-height:1.1}
    .command-stat span{font-size:15px;font-weight:700;color:#0f172a;line-height:1.15;word-break:break-word}
    .command-stat.is-orders{background:#eff6ff;border-color:#bfdbfe}
    .command-stat.is-items{background:#f8fafc}
    .command-stat.is-total{background:#f0fdf4;border-color:#bbf7d0}
    .command-strip-title{font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.04em}
    .command-border-hint{font-size:12px;color:#64748b}
    .legend-panel{display:grid;gap:10px}
    .legend-group{border:1px solid #e2e8f0;border-radius:10px;padding:10px;background:#f8fafc}
    .legend-title{display:block;font-size:12px;color:#475569;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px}
    .legend-row{display:flex;gap:8px;flex-wrap:wrap}
    .commands-págination{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .commands-págination-controls{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .commands-page-btn{border:1px solid #cbd5e1;background:#fff;color:#0f172a;border-radius:8px;padding:7px 10px;cursor:pointer;min-width:36px}
    .commands-page-btn[disabled]{opacity:.5;cursor:not-allowed}
    .commands-page-btn.is-active{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
    .commands-empty{border:1px dashed #cbd5e1;border-radius:12px;padding:18px;color:#334155;background:#fff}
    @media (max-width:1080px){
        .kpi-grid{grid-template-columns:repeat(3,minmax(120px,1fr))}
        .commands-grid{grid-template-columns:repeat(2,minmax(220px,1fr))}
    }
    @media (max-width:680px){
        .kpi-grid{grid-template-columns:repeat(2,minmax(120px,1fr))}
        .commands-grid{grid-template-columns:1fr}
        .command-edit-grid{grid-template-columns:1fr}
        .command-stats{grid-template-columns:1fr}
    }
</style>

<div class="commands-page">
    <div class="topbar">
        <div>
            <h1>Comandas Abertas</h1>
            <p>Painel operacional de comandas ativas no novo padrão visual.</p>
        </div>
        <a class="btn" href="<?= htmlspecialchars(base_url('/admin/commands/create')) ?>">Abrir comanda</a>
    </div>

    <div class="kpi-grid">
        <div class="kpi-item"><strong><?= $totalCommands ?></strong><span>Comandas abertas</span></div>
        <div class="kpi-item"><strong><?= count($tablesInUse) ?></strong><span>Mesas com comanda</span></div>
        <div class="kpi-item"><strong><?= $identifiedCustomers ?></strong><span>Clientes identificados</span></div>
        <div class="kpi-item"><strong><?= $commandsWithoutTable ?></strong><span>Sem mesa vinculada</span></div>
        <div class="kpi-item"><strong><?= count($openedByUsers) ?></strong><span>Atendentes ativos</span></div>
    </div>

    <div class="card legend-panel">
        <div class="legend-group">
            <strong class="legend-title">Cor da Borda da Comanda (Status Vigente)</strong>
            <div class="legend-row">
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'pending')) ?>">Comanda Pendente</span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'received')) ?>">Comanda Recebida</span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'preparing')) ?>">Comanda em preparo</span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'ready')) ?>">Comanda pronta</span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'delivered')) ?>">Comanda entregue</span>
                <span class="badge status-default">Comanda ativa (mista)</span>
                <span class="badge <?= htmlspecialchars(status_badge_class('table_status', 'livre')) ?>">Sem pedidos • Mesa Livre</span>
                <span class="badge <?= htmlspecialchars(status_badge_class('table_status', 'ocupada')) ?>">Sem pedidos • Mesa Ocupada</span>
                <span class="badge <?= htmlspecialchars(status_badge_class('table_status', 'aguardando_fechamento')) ?>">Sem pedidos • Aguardando fechamento</span>
                <span class="badge <?= htmlspecialchars(status_badge_class('table_status', 'bloqueada')) ?>">Sem pedidos • Mesa Bloqueada</span>
            </div>
        </div>
        <div class="legend-group">
            <strong class="legend-title">Contagem Operacional das Comandas</strong>
            <div class="legend-row">
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'pending')) ?>">Pendentes: <?= (int) ($operationalSummary['pending'] ?? 0) ?></span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'received')) ?>">Recebidos: <?= (int) ($operationalSummary['received'] ?? 0) ?></span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'preparing')) ?>">Em preparo: <?= (int) ($operationalSummary['preparing'] ?? 0) ?></span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'ready')) ?>">Prontos: <?= (int) ($operationalSummary['ready'] ?? 0) ?></span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'delivered')) ?>">Entregues: <?= (int) ($operationalSummary['delivered'] ?? 0) ?></span>
            </div>
        </div>
        <div class="legend-group">
            <strong class="legend-title">Contagem de Pagamentos</strong>
            <div class="legend-row">
                <span class="badge <?= htmlspecialchars(status_badge_class('order_payment_status', 'pending')) ?>">Pagamento Pendente: <?= (int) ($paymentSummary['pending'] ?? 0) ?></span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_payment_status', 'partial')) ?>">Pagamento Parcial: <?= (int) ($paymentSummary['partial'] ?? 0) ?></span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_payment_status', 'paid')) ?>">Pagamento Pago: <?= (int) ($paymentSummary['paid'] ?? 0) ?></span>
            </div>
        </div>
        <p class="search-info" style="margin:0">Atualizacao automatica da página a cada 30 segundos.</p>
    </div>

    <div class="card">
        <div class="search-row">
            <input id="commandSearch" type="text" placeholder="Buscar por mesa, cliente, atendente, status, numero da comanda ou observacoes">
            <button id="clearCommandSearch" class="btn secondary" type="button">Limpar</button>
        </div>
        <div id="commandSearchInfo" class="search-info">Digite para filtrar as comandas em tempo real.</div>
    </div>

    <div class="commands-grid" id="commandsGrid">
        <?php if (empty($commands)): ?>
            <div class="command-card">Nenhuma comanda aberta no momento.</div>
        <?php else: ?>
            <?php foreach ($commands as $command): ?>
                <?php
                $commandId = (int) ($command['id'] ?? 0);
                $tableNumber = $command['table_number'] !== null ? (int) $command['table_number'] : null;
                $customerName = trim((string) ($command['customer_name'] ?? ''));
                $openedBy = trim((string) ($command['opened_by_user_name'] ?? ''));
                $statusValue = (string) ($command['status'] ?? '');
                $statusLabel = status_label('command_status', $statusValue);
                $tableStatusRaw = trim((string) ($command['table_status'] ?? ''));
                $tableStatusValue = in_array($tableStatusRaw, ['livre', 'ocupada', 'aguardando_fechamento', 'bloqueada'], true)
                    ? $tableStatusRaw
                    : 'sem_mesa';
                $tableStatusLabel = $tableStatusValue === 'sem_mesa'
                    ? 'Sem mesa'
                    : status_label('table_status', $tableStatusValue);
                $tableStatusBadgeClass = $tableStatusValue === 'sem_mesa'
                    ? 'status-default'
                    : status_badge_class('table_status', $tableStatusValue);
                $openedAt = (string) ($command['opened_at'] ?? '-');
                $notes = trim((string) ($command['notes'] ?? ''));

                $operational = is_array($commandOperational[$commandId] ?? null) ? $commandOperational[$commandId] : [];
                $ordersCount = (int) ($operational['orders_count'] ?? 0);
                $itemsTotal = (int) ($operational['items_total'] ?? 0);
                $amountTotal = (float) ($operational['amount_total'] ?? 0);
                $statusCounts = is_array($operational['status_counts'] ?? null) ? $operational['status_counts'] : [];
                $paymentCounts = is_array($operational['payment_status_counts'] ?? null) ? $operational['payment_status_counts'] : [];
                $pendingPaymentTotal = (int) ($operational['pending_payment_total'] ?? 0);
                $canCancelCommand = !empty($operational['can_cancel_command']);

                $borderStatusClass = 'status-op-table-' . $tableStatusValue;
                $borderStatusLabel = 'Status vigente: Sem pedidos - Mesa ' . $tableStatusLabel;
                if ($ordersCount > 0) {
                    if ((int) ($statusCounts['pending'] ?? 0) > 0) {
                        $borderStatusClass = 'status-op-pending';
                        $borderStatusLabel = 'Status vigente: Comanda Pendente';
                    } elseif ((int) ($statusCounts['received'] ?? 0) > 0) {
                        $borderStatusClass = 'status-op-received';
                        $borderStatusLabel = 'Status vigente: Comanda Recebida';
                    } elseif ((int) ($statusCounts['preparing'] ?? 0) > 0) {
                        $borderStatusClass = 'status-op-preparing';
                        $borderStatusLabel = 'Status vigente: Comanda em preparo';
                    } elseif ((int) ($statusCounts['ready'] ?? 0) > 0) {
                        $borderStatusClass = 'status-op-ready';
                        $borderStatusLabel = 'Status vigente: Comanda pronta';
                    } elseif ((int) ($statusCounts['delivered'] ?? 0) > 0) {
                        $borderStatusClass = 'status-op-delivered';
                        $borderStatusLabel = 'Status vigente: Comanda entregue';
                    } else {
                        $borderStatusClass = 'status-op-active';
                        $borderStatusLabel = 'Status vigente: Comanda ativa (mista)';
                    }
                }

                $searchText = strtolower(trim(implode(' ', [
                    (string) $commandId,
                    $tableNumber !== null ? (string) $tableNumber : '',
                    $customerName,
                    $openedBy,
                    $statusValue,
                    (string) $statusLabel,
                    $tableStatusValue,
                    (string) $tableStatusLabel,
                    (string) $ordersCount,
                    (string) $itemsTotal,
                    number_format($amountTotal, 2, '.', ''),
                    $openedAt,
                    $notes,
                ])));
                ?>
                <article class="command-card <?= htmlspecialchars($borderStatusClass) ?>" data-search="<?= htmlspecialchars($searchText) ?>">
                    <div class="command-head">
                        <div class="command-title">
                            <strong>Comanda #<?= $commandId > 0 ? $commandId : '-' ?></strong><br>
                            <small style="color:#64748b"><?= $tableNumber !== null ? 'Mesa ' . $tableNumber : 'Mesa nao vinculada' ?></small><br>
                            <small class="command-border-hint"><?= htmlspecialchars($borderStatusLabel) ?></small>
                        </div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end">
                            <span class="badge <?= htmlspecialchars(status_badge_class('command_status', $statusValue)) ?>">
                                <?= htmlspecialchars((string) $statusLabel) ?>
                            </span>
                            <span class="badge <?= htmlspecialchars($tableStatusBadgeClass) ?>">
                                Mesa: <?= htmlspecialchars((string) $tableStatusLabel) ?>
                            </span>
                        </div>
                    </div>

                    <div class="command-meta">
                        <div class="command-meta-item">
                            <strong>Cliente</strong>
                            <span><?= htmlspecialchars($customerName !== '' ? $customerName : 'Não informado') ?></span>
                        </div>
                        <div class="command-meta-item">
                            <strong>Aberta por</strong>
                            <span><?= htmlspecialchars($openedBy !== '' ? $openedBy : 'Não identificado') ?></span>
                        </div>
                        <div class="command-meta-item" style="grid-column:1 / -1">
                            <strong>Abertura</strong>
                            <span><?= htmlspecialchars($openedAt) ?></span>
                        </div>
                    </div>

                    <div class="command-stats">
                        <div class="command-stat is-orders">
                            <strong>Pedidos ativos</strong>
                            <span><?= $ordersCount ?></span>
                        </div>
                        <div class="command-stat is-items">
                            <strong>Itens</strong>
                            <span><?= $itemsTotal ?></span>
                        </div>
                        <div class="command-stat is-total">
                            <strong>Total da comanda</strong>
                            <span>R$ <?= number_format($amountTotal, 2, ',', '.') ?></span>
                        </div>
                    </div>

                    <div>
                        <div class="command-strip-title">Status operacionais</div>
                        <div class="command-strip" style="margin-top:4px">
                            <?php if ($ordersCount <= 0): ?>
                                <span class="badge status-default">Sem pedidos ativos</span>
                            <?php else: ?>
                                <?php foreach ($statusCounts as $statusKey => $count): ?>
                                    <?php if ((int) $count > 0): ?>
                                        <span class="badge <?= htmlspecialchars(status_badge_class('order_status', $statusKey)) ?>">
                                            <?= htmlspecialchars(status_label('order_status', $statusKey)) ?>: <?= (int) $count ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <div class="command-strip-title">Status de pagamento</div>
                        <div class="command-strip" style="margin-top:4px">
                            <?php if ($pendingPaymentTotal <= 0): ?>
                                <span class="badge status-default">Sem pagamentos pendentes</span>
                            <?php else: ?>
                                <?php foreach ($paymentCounts as $paymentStatusKey => $count): ?>
                                    <?php if ((int) $count > 0): ?>
                                        <span class="badge <?= htmlspecialchars(status_badge_class('order_payment_status', $paymentStatusKey)) ?>">
                                            <?= htmlspecialchars(status_label('order_payment_status', $paymentStatusKey)) ?>: <?= (int) $count ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($notes !== ''): ?>
                        <div class="command-notes">
                            <strong>Observações</strong>
                            <p><?= htmlspecialchars($notes) ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="command-actions">
                        <details class="command-edit-panel">
                            <summary>Editar comanda</summary>
                            <form class="command-edit-form" method="POST" action="<?= htmlspecialchars(base_url('/admin/commands/update')) ?>">
                                <?= form_security_fields('commands.update.' . $commandId) ?>
                                <input type="hidden" name="command_id" value="<?= $commandId ?>">

                                <div class="command-edit-grid">
                                    <div class="field">
                                        <label for="command_customer_<?= $commandId ?>">Nome do cliente</label>
                                        <input id="command_customer_<?= $commandId ?>" name="customer_name" type="text" required value="<?= htmlspecialchars($customerName !== '' ? $customerName : '') ?>" placeholder="Ex.: Joao da Silva">
                                    </div>
                                    <div class="field">
                                        <label for="command_opened_at_<?= $commandId ?>">Abertura</label>
                                        <input id="command_opened_at_<?= $commandId ?>" type="text" value="<?= htmlspecialchars($openedAt) ?>" disabled>
                                    </div>
                                </div>

                                <div class="field">
                                    <label for="command_notes_<?= $commandId ?>">Observações</label>
                                    <textarea id="command_notes_<?= $commandId ?>" name="notes" rows="3" placeholder="Observações da comanda"><?= htmlspecialchars($notes) ?></textarea>
                                </div>

                                <div class="command-edit-footer">
                                    <p class="command-edit-note">A edicao permanece disponivel em qualquer status da comanda aberta. O cancelamento so aparece quando a comanda estiver sem pedidos ativos e sem pagamentos pendentes.</p>
                                    <button class="btn secondary" type="submit">Salvar edicao</button>
                                </div>
                            </form>
                        </details>

                        <?php if ($canCancelCommand): ?>
                            <form method="POST" action="<?= htmlspecialchars(base_url('/admin/commands/cancel')) ?>" onsubmit="return confirm('Cancelar esta comanda agora?');">
                                <?= form_security_fields('commands.cancel.' . $commandId) ?>
                                <input type="hidden" name="command_id" value="<?= $commandId ?>">
                                <button class="btn secondary" type="submit">Cancelar comanda</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($commands)): ?>
        <section class="card commands-págination" id="commandsPagination" hidden>
            <span id="commandsPaginationInfo"></span>
            <div id="commandsPaginationControls" class="commands-págination-controls"></div>
        </section>
        <div id="commandsNoResults" class="commands-empty" hidden>Nenhuma comanda encontrada para o filtro informado.</div>
    <?php endif; ?>
</div>

<script>
(() => {
    const cards = Array.from(document.querySelectorAll('.command-card[data-search]'));
    const searchInput = document.getElementById('commandSearch');
    const clearButton = document.getElementById('clearCommandSearch');
    const searchInfo = document.getElementById('commandSearchInfo');
    const págination = document.getElementById('commandsPagination');
    const páginationInfo = document.getElementById('commandsPaginationInfo');
    const páginationControls = document.getElementById('commandsPaginationControls');
    const noResults = document.getElementById('commandsNoResults');
    const pageRefreshMs = 30000;
    const pageSize = 10;
    let currentPage = 1;

    const normalize = (value) => String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

    const renderPagination = (totalFiltered) => {
        if (!págination || !páginationInfo || !páginationControls) {
            return;
        }
        if (totalFiltered <= 0) {
            págination.hidden = true;
            páginationInfo.textContent = '';
            páginationControls.innerHTML = '';
            return;
        }

        págination.hidden = false;
        const totalPages = Math.max(1, Math.ceil(totalFiltered / pageSize));
        if (currentPage > totalPages) {
            currentPage = 1;
        }

        const start = (currentPage - 1) * pageSize + 1;
        const end = Math.min(currentPage * pageSize, totalFiltered);
        páginationInfo.textContent = `Mostrando ${start}-${end} de ${totalFiltered} comanda(s).`;
        páginationControls.innerHTML = '';

        const addButton = (label, page, disabled, active = false) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `commands-page-btn${active ? ' is-active' : ''}`;
            button.textContent = label;
            button.disabled = disabled;
            button.addEventListener('click', () => {
                if (!disabled && page !== currentPage) {
                    currentPage = page;
                    applyFilter(false);
                }
            });
            páginationControls.appendChild(button);
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
        addButton('Próxima', Math.min(totalPages, currentPage + 1), currentPage >= totalPages);
    };

    const applyFilter = (resetPage = true) => {
        const rawQuery = searchInput ? searchInput.value : '';
        const tokens = normalize(rawQuery).split(/\s+/).filter(Boolean);
        const filteredCards = [];

        cards.forEach((card) => {
            card.classList.remove('search-hit');
            const haystack = normalize(card.getAttribute('data-search') || '');
            const match = tokens.every((token) => haystack.includes(token));
            if (match) {
                filteredCards.push(card);
            }
        });

        if (resetPage) {
            currentPage = 1;
        }

        const from = (currentPage - 1) * pageSize;
        const visibleCards = filteredCards.slice(from, from + pageSize);
        const visibleSet = new Set(visibleCards);
        cards.forEach((card) => {
            card.style.display = visibleSet.has(card) ? '' : 'none';
        });

        if (visibleCards[0]) {
            visibleCards[0].classList.add('search-hit');
        }

        const visibleCount = filteredCards.length;
        if (searchInfo) {
            if (tokens.length === 0) {
                searchInfo.textContent = 'Digite para filtrar as comandas em tempo real.';
            } else {
                searchInfo.textContent = visibleCount > 0
                    ? `Filtro ativo: ${visibleCount} comanda(s) encontrada(s).`
                    : 'Nenhuma comanda encontrada para o filtro informado.';
            }
        }

        if (noResults) {
            noResults.hidden = visibleCount > 0;
        }

        renderPagination(visibleCount);
    };

    if (searchInput) {
        searchInput.addEventListener('input', applyFilter);
    }

    if (clearButton && searchInput) {
        clearButton.addEventListener('click', () => {
            searchInput.value = '';
            applyFilter();
            searchInput.focus();
        });
    }

    window.setInterval(() => {
        if (document.hidden) {
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
    }, pageRefreshMs);

    applyFilter();
})();
</script>
