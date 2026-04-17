<?php
$queue = is_array($queue ?? null) ? $queue : [];
$receivedOrders = is_array($queue['received'] ?? null) ? $queue['received'] : [];
$waitingOrders = is_array($queue['preparing'] ?? null) ? $queue['preparing'] : [];
$readyOrders = is_array($queue['ready'] ?? null) ? $queue['ready'] : [];
$allOrders = is_array($queue['all'] ?? null) ? $queue['all'] : [];

$tabs = [
    'received' => [
        'step' => '01',
        'title' => 'Pedidos recebidos',
        'subtitle' => 'Novas comandas enviadas para producao.',
        'empty' => 'Sem pedidos recebidos no momento.',
        'orders' => $receivedOrders,
    ],
    'preparing' => [
        'step' => '02',
        'title' => 'Pedidos em espera',
        'subtitle' => 'Fila em preparo aguardando finalizacao.',
        'empty' => 'Sem pedidos em espera no momento.',
        'orders' => $waitingOrders,
    ],
    'ready' => [
        'step' => '03',
        'title' => 'Pedidos prontos',
        'subtitle' => 'Ultima etapa da cozinha, com emissao de ticket.',
        'empty' => 'Sem pedidos prontos para emissao.',
        'orders' => $readyOrders,
    ],
];

$formatDateTime = static function (?string $value): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '-';
    }

    try {
        $date = new DateTimeImmutable($raw);
    } catch (Throwable $exception) {
        return $raw;
    }

    return $date->format('d/m/Y H:i');
};

$elapsedSince = static function (?string $value): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '-';
    }

    try {
        $start = new DateTimeImmutable($raw);
        $now = new DateTimeImmutable('now');
        $seconds = max(0, $now->getTimestamp() - $start->getTimestamp());
    } catch (Throwable $exception) {
        return '-';
    }

    if ($seconds < 60) {
        return 'agora';
    }

    $minutes = intdiv($seconds, 60);
    if ($minutes < 60) {
        return 'ha ' . $minutes . ' min';
    }

    $hours = intdiv($minutes, 60);
    if ($hours < 24) {
        $remainingMinutes = $minutes % 60;
        if ($remainingMinutes === 0) {
            return 'ha ' . $hours . 'h';
        }
        return 'ha ' . $hours . 'h ' . $remainingMinutes . 'm';
    }

    $days = intdiv($hours, 24);
    $remainingHours = $hours % 24;
    if ($remainingHours === 0) {
        return 'ha ' . $days . 'd';
    }

    return 'ha ' . $days . 'd ' . $remainingHours . 'h';
};
?>

<style>
    .kitchen-page{display:grid;gap:16px}
    .kitchen-topbar{align-items:flex-start}
    .kitchen-topbar p{margin:6px 0 0;color:#475569}
    .kitchen-kpis{display:grid;grid-template-columns:repeat(4,minmax(145px,1fr));gap:12px}
    .kitchen-kpi{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px;box-shadow:0 8px 20px rgba(15,23,42,.05)}
    .kitchen-kpi strong{display:block;font-size:24px;line-height:1.1;color:#0f172a}
    .kitchen-kpi span{display:block;margin-top:6px;font-size:12px;color:#475569;text-transform:uppercase;letter-spacing:.04em}
    .kitchen-refresh-note{margin:0;color:#334155;font-size:12px}

    .kitchen-tabs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
    .kitchen-tab{border:1px solid #dbe3ef;background:#fff;border-radius:14px;padding:12px;display:grid;gap:4px;text-align:left;cursor:pointer;transition:all .2s ease}
    .kitchen-tab:hover{border-color:#93c5fd;box-shadow:0 12px 24px rgba(30,64,175,.12);transform:translateY(-1px)}
    .kitchen-tab.is-active{border-color:#1d4ed8;background:linear-gradient(180deg,#eff6ff 0%,#dbeafe 100%);box-shadow:0 14px 28px rgba(30,64,175,.18)}
    .kitchen-tab-step{font-size:11px;color:#334155;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
    .kitchen-tab-title{font-size:15px;font-weight:700;color:#0f172a}
    .kitchen-tab-meta{display:flex;justify-content:space-between;gap:8px;align-items:center}
    .kitchen-tab-meta small{font-size:12px;color:#475569}

    .kitchen-panel{display:none}
    .kitchen-panel.is-active{display:block}

    .kitchen-board{border:1px solid #dbeafe;border-radius:16px;padding:14px;background:
        radial-gradient(circle at 20% 20%, rgba(191,219,254,.34), transparent 38%),
        radial-gradient(circle at 80% 0%, rgba(196,181,253,.2), transparent 34%),
        linear-gradient(160deg,#f8fafc 0%,#f1f5f9 48%,#e2e8f0 100%);
    }
    .kitchen-board-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:12px}
    .kitchen-board-head h3{margin:0;font-size:20px;color:#0f172a}
    .kitchen-board-head p{margin:6px 0 0;color:#334155;font-size:13px}
    .board-count{display:inline-flex;align-items:center;justify-content:center;min-width:36px;padding:7px 12px;border-radius:999px;background:#0f172a;color:#fff;font-weight:700;font-size:13px}

    .ticket-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:14px}
    .kitchen-ticket{position:relative;background:linear-gradient(180deg,#fffef9 0%,#ffffff 100%);border-radius:14px;border:1px solid #f1e7cc;padding:13px;box-shadow:0 12px 24px rgba(71,85,105,.16);display:grid;gap:10px}
    .kitchen-ticket::before{content:'';position:absolute;top:-8px;left:22px;width:14px;height:14px;border-radius:999px;background:#ef4444;box-shadow:0 2px 0 rgba(0,0,0,.1)}
    .kitchen-ticket.ticket-received{border-top:4px solid #3b82f6}
    .kitchen-ticket.ticket-preparing{border-top:4px solid #f59e0b}
    .kitchen-ticket.ticket-ready{border-top:4px solid #22c55e}

    .ticket-head{display:flex;justify-content:space-between;gap:8px;align-items:flex-start}
    .ticket-title{display:grid;gap:4px}
    .ticket-title strong{font-size:16px;color:#0f172a;line-height:1.2}
    .ticket-title span{font-size:12px;color:#475569}

    .ticket-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    .ticket-meta-item{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:8px;min-height:54px}
    .ticket-meta-item span{display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px}
    .ticket-meta-item strong{font-size:13px;color:#0f172a;display:block;line-height:1.3;word-break:break-word}

    .ticket-flow-badge{display:inline-flex;align-items:center;padding:4px 9px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:.03em;text-transform:uppercase}
    .ticket-flow-badge.received{background:#dbeafe;color:#1d4ed8}
    .ticket-flow-badge.preparing{background:#fef3c7;color:#b45309}
    .ticket-flow-badge.ready{background:#dcfce7;color:#15803d}

    .ticket-items{display:grid;gap:8px;padding:10px;border:1px solid #e2e8f0;border-radius:12px;background:rgba(248,250,252,.9)}
    .ticket-items-title{font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.05em;font-weight:700}
    .ticket-item{display:grid;gap:4px;padding:9px 10px;border-radius:10px;background:#fff;border:1px solid #e5e7eb}
    .ticket-item-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px}
    .ticket-item-head strong{font-size:13px;color:#0f172a;line-height:1.35;word-break:break-word}
    .ticket-item-price{font-size:12px;font-weight:700;color:#0f172a;white-space:nowrap}
    .ticket-item-note{font-size:12px;color:#92400e;background:#fffbeb;border:1px dashed #fcd34d;border-radius:8px;padding:6px 8px}
    .ticket-item-additionals{display:grid;gap:5px}
    .ticket-item-additional{font-size:12px;color:#334155;padding-left:10px;position:relative;word-break:break-word}
    .ticket-item-additional::before{content:'+';position:absolute;left:0;top:0;color:#15803d;font-weight:700}

    .ticket-actions{display:grid;gap:8px}
    .ticket-actions form{display:grid;gap:8px}
    .ticket-actions .btn{width:100%;text-align:center}
    .ticket-actions .btn.secondary{background:#0f172a}
    .ticket-note{font-size:12px;color:#64748b;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px;padding:8px}
    .ticket-print-log{font-size:12px;color:#475569;margin:0}

    .kitchen-empty{border:1px dashed #94a3b8;background:rgba(248,250,252,.85);border-radius:12px;padding:18px;color:#334155}

    @media (max-width:980px){
        .kitchen-kpis{grid-template-columns:repeat(2,minmax(145px,1fr))}
        .kitchen-tabs{grid-template-columns:1fr}
    }
    @media (max-width:660px){
        .kitchen-kpis{grid-template-columns:1fr}
        .ticket-grid{grid-template-columns:1fr}
    }
</style>

<div class="kitchen-page">
    <div class="topbar kitchen-topbar">
        <div>
            <h1>Painel de Cozinha</h1>
            <p>Fluxo visual por etapas para leitura rapida, acao imediata e menos poluicao na tela.</p>
        </div>
        <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/orders')) ?>">Voltar para pedidos</a>
    </div>

    <section class="kitchen-kpis">
        <article class="kitchen-kpi">
            <strong><?= count($receivedOrders) ?></strong>
            <span>Recebidos</span>
        </article>
        <article class="kitchen-kpi">
            <strong><?= count($waitingOrders) ?></strong>
            <span>Em espera</span>
        </article>
        <article class="kitchen-kpi">
            <strong><?= count($readyOrders) ?></strong>
            <span>Prontos</span>
        </article>
        <article class="kitchen-kpi">
            <strong><?= count($allOrders) ?></strong>
            <span>Total na fila</span>
        </article>
    </section>

    <p class="kitchen-refresh-note">Atualizacao automatica da tela a cada 30 segundos.</p>

    <div class="kitchen-tabs" role="tablist" aria-label="Fluxo da cozinha">
        <?php $tabIndex = 0; ?>
        <?php foreach ($tabs as $tabKey => $tab): ?>
            <?php $isActiveTab = $tabIndex === 0; ?>
            <button
                type="button"
                class="kitchen-tab<?= $isActiveTab ? ' is-active' : '' ?>"
                role="tab"
                aria-selected="<?= $isActiveTab ? 'true' : 'false' ?>"
                data-tab-target="<?= htmlspecialchars($tabKey) ?>"
            >
                <span class="kitchen-tab-step">Etapa <?= htmlspecialchars((string) ($tab['step'] ?? '')) ?></span>
                <span class="kitchen-tab-title"><?= htmlspecialchars((string) ($tab['title'] ?? '')) ?></span>
                <span class="kitchen-tab-meta">
                    <small><?= htmlspecialchars((string) ($tab['subtitle'] ?? '')) ?></small>
                    <strong><?= count(is_array($tab['orders'] ?? null) ? $tab['orders'] : []) ?></strong>
                </span>
            </button>
            <?php $tabIndex++; ?>
        <?php endforeach; ?>
    </div>

    <?php $panelIndex = 0; ?>
    <?php foreach ($tabs as $tabKey => $tab): ?>
        <?php
        $orders = is_array($tab['orders'] ?? null) ? $tab['orders'] : [];
        $isActivePanel = $panelIndex === 0;
        ?>
        <section
            class="kitchen-panel<?= $isActivePanel ? ' is-active' : '' ?>"
            data-tab-panel="<?= htmlspecialchars($tabKey) ?>"
            role="tabpanel"
        >
            <div class="kitchen-board">
                <header class="kitchen-board-head">
                    <div>
                        <h3><?= htmlspecialchars((string) ($tab['title'] ?? '')) ?></h3>
                        <p><?= htmlspecialchars((string) ($tab['subtitle'] ?? '')) ?></p>
                    </div>
                    <span class="board-count"><?= count($orders) ?></span>
                </header>

                <?php if (empty($orders)): ?>
                    <div class="kitchen-empty"><?= htmlspecialchars((string) ($tab['empty'] ?? 'Sem pedidos nesta etapa.')) ?></div>
                <?php else: ?>
                    <div class="ticket-grid">
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $orderId = (int) ($order['id'] ?? 0);
                            if ($orderId <= 0) {
                                continue;
                            }

                            $status = trim((string) ($order['status'] ?? ''));
                            $ticketStatusClass = in_array($status, ['received', 'preparing', 'ready'], true) ? $status : 'received';
                            $flowBadgeLabel = $status === 'preparing' ? 'Em espera' : status_label('order_status', $status);

                            $orderNumber = trim((string) ($order['order_number'] ?? ''));
                            if ($orderNumber === '') {
                                $orderNumber = 'Pedido #' . $orderId;
                            }

                            $tableLabel = ($order['table_number'] ?? null) !== null
                                ? 'Mesa ' . (int) $order['table_number']
                                : 'Sem mesa';

                            $commandLabel = ($order['command_id'] ?? null) !== null
                                ? 'Comanda ativa'
                                : 'Sem comanda';

                            $customerName = trim((string) ($order['customer_name'] ?? ''));
                            if ($customerName === '') {
                                $customerName = 'Nao informado';
                            }

                            $createdAtLabel = $formatDateTime((string) ($order['created_at'] ?? ''));
                            $elapsedLabel = $elapsedSince((string) ($order['created_at'] ?? ''));
                            $changedAtLabel = $formatDateTime((string) ($order['latest_status_changed_at'] ?? ''));
                            $lastPrintedAtLabel = $formatDateTime((string) ($order['last_printed_at'] ?? ''));
                            $lastPrintedBy = trim((string) ($order['last_printed_by'] ?? ''));
                            $orderItems = is_array($order['items'] ?? null) ? $order['items'] : [];

                            $showPaidWaiting = (($order['payment_status'] ?? '') === 'paid')
                                && in_array($status, ['received', 'preparing'], true);
                            ?>
                            <article class="kitchen-ticket ticket-<?= htmlspecialchars($ticketStatusClass) ?>">
                                <div class="ticket-head">
                                    <div class="ticket-title">
                                        <strong><?= htmlspecialchars($orderNumber) ?></strong>
                                        <span><?= htmlspecialchars($createdAtLabel) ?> | <?= htmlspecialchars($elapsedLabel) ?></span>
                                    </div>
                                    <span class="ticket-flow-badge <?= htmlspecialchars($ticketStatusClass) ?>"><?= htmlspecialchars($flowBadgeLabel) ?></span>
                                </div>

                                <div class="ticket-meta">
                                    <div class="ticket-meta-item">
                                        <span>Mesa / Comanda</span>
                                        <strong><?= htmlspecialchars($tableLabel) ?><br><?= htmlspecialchars($commandLabel) ?></strong>
                                    </div>
                                    <div class="ticket-meta-item">
                                        <span>Cliente</span>
                                        <strong><?= htmlspecialchars($customerName) ?></strong>
                                    </div>
                                    <div class="ticket-meta-item">
                                        <span>Itens</span>
                                        <strong><?= (int) ($order['items_count'] ?? 0) ?> item(ns)</strong>
                                    </div>
                                    <div class="ticket-meta-item">
                                        <span>Ultima mudanca</span>
                                        <strong><?= htmlspecialchars($changedAtLabel) ?></strong>
                                    </div>
                                </div>

                                <div class="ticket-items">
                                    <div class="ticket-items-title">Pedido e adicionais</div>
                                    <?php if (empty($orderItems)): ?>
                                        <div class="ticket-note">Itens detalhados indisponiveis para este pedido.</div>
                                    <?php else: ?>
                                        <?php foreach ($orderItems as $item): ?>
                                            <?php
                                            $itemAdditionals = is_array($item['additionals'] ?? null) ? $item['additionals'] : [];
                                            $itemNotes = trim((string) ($item['notes'] ?? ''));
                                            ?>
                                            <div class="ticket-item">
                                                <div class="ticket-item-head">
                                                    <strong><?= (int) ($item['quantity'] ?? 0) ?>x <?= htmlspecialchars((string) ($item['name'] ?? 'Produto')) ?></strong>
                                                    <span class="ticket-item-price">R$ <?= number_format((float) ($item['line_subtotal'] ?? 0), 2, ',', '.') ?></span>
                                                </div>
                                                <?php if ($itemNotes !== ''): ?>
                                                    <div class="ticket-item-note">Obs.: <?= htmlspecialchars($itemNotes) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($itemAdditionals)): ?>
                                                    <div class="ticket-item-additionals">
                                                        <?php foreach ($itemAdditionals as $additional): ?>
                                                            <div class="ticket-item-additional">
                                                                <?= (int) ($additional['quantity'] ?? 0) ?>x <?= htmlspecialchars((string) ($additional['name'] ?? 'Adicional')) ?>
                                                                (R$ <?= number_format((float) ($additional['line_subtotal'] ?? 0), 2, ',', '.') ?>)
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <?php if ($showPaidWaiting): ?>
                                    <span class="badge <?= htmlspecialchars(status_badge_class('order_operational_flag', 'paid_waiting_production')) ?>">
                                        <?= htmlspecialchars(status_label('order_operational_flag', 'paid_waiting_production')) ?>
                                    </span>
                                <?php endif; ?>

                                <div class="ticket-actions">
                                    <?php if ($status === 'received'): ?>
                                        <form method="POST" action="<?= htmlspecialchars(base_url('/admin/kitchen/status')) ?>">
                                            <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                            <input type="hidden" name="new_status" value="preparing">
                                            <button class="btn secondary" type="submit">Iniciar preparo</button>
                                        </form>
                                        <p class="ticket-note">Impressao liberada somente na etapa de pedidos prontos.</p>
                                    <?php elseif ($status === 'preparing'): ?>
                                        <form method="POST" action="<?= htmlspecialchars(base_url('/admin/kitchen/status')) ?>">
                                            <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                            <input type="hidden" name="new_status" value="ready">
                                            <button class="btn secondary" type="submit">Mover para prontos</button>
                                        </form>
                                        <p class="ticket-note">Finalize o preparo para liberar a emissao do ticket.</p>
                                    <?php elseif ($status === 'ready'): ?>
                                        <form method="POST" action="<?= htmlspecialchars(base_url('/admin/kitchen/emit-ticket')) ?>">
                                            <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                            <input type="hidden" name="redirect_to_preview" value="1">
                                            <input name="print_notes" type="text" placeholder="Observacao de impressao (opcional)">
                                            <button class="btn" type="submit">
                                                <?= $lastPrintedAtLabel === '-' ? 'Emitir ticket final e abrir previa' : 'Reemitir ticket final e abrir previa' ?>
                                            </button>
                                        </form>
                                        <p class="ticket-print-log">
                                            Ultima emissao: <?= htmlspecialchars($lastPrintedAtLabel) ?>
                                            <?php if ($lastPrintedBy !== ''): ?>
                                                | por <?= htmlspecialchars($lastPrintedBy) ?>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php $panelIndex++; ?>
    <?php endforeach; ?>
</div>

<script>
(() => {
    const tabs = Array.from(document.querySelectorAll('.kitchen-tab[data-tab-target]'));
    const panels = Array.from(document.querySelectorAll('.kitchen-panel[data-tab-panel]'));
    const storageKey = 'kitchen.activeTab';
    const autoRefreshMs = 30000;

    if (tabs.length === 0 || panels.length === 0) {
        return;
    }

    const activateTab = (target) => {
        const tabKey = String(target || '').trim();
        if (tabKey === '') {
            return;
        }

        tabs.forEach((tab) => {
            const isActive = tab.getAttribute('data-tab-target') === tabKey;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        panels.forEach((panel) => {
            const isActive = panel.getAttribute('data-tab-panel') === tabKey;
            panel.classList.toggle('is-active', isActive);
        });

        window.sessionStorage.setItem(storageKey, tabKey);
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            activateTab(tab.getAttribute('data-tab-target'));
        });
    });

    const storedTab = window.sessionStorage.getItem(storageKey);
    const fallbackTab = tabs[0].getAttribute('data-tab-target') || 'received';
    const hasStoredTab = storedTab && tabs.some((tab) => tab.getAttribute('data-tab-target') === storedTab);
    activateTab(hasStoredTab ? storedTab : fallbackTab);

    window.setInterval(() => {
        if (document.hidden) {
            return;
        }

        const activeTab = tabs.find((tab) => tab.classList.contains('is-active'));
        if (activeTab) {
            window.sessionStorage.setItem(storageKey, String(activeTab.getAttribute('data-tab-target') || fallbackTab));
        }

        window.location.reload();
    }, autoRefreshMs);
})();
</script>
