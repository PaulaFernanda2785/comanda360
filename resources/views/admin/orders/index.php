<?php
$panelSummary = is_array($panelSummary ?? null) ? $panelSummary : [];
$ordersByTable = is_array($ordersByTable ?? null) ? $ordersByTable : [];
$canUpdateStatus = !empty($canUpdateStatus);
$canCancelOrder = !empty($canCancelOrder);
$canSendKitchen = !empty($canSendKitchen);

$operationalSummary = [
    'pending' => (int) ($panelSummary['pending'] ?? 0),
    'received' => (int) ($panelSummary['received'] ?? 0),
    'preparing' => (int) ($panelSummary['preparing'] ?? 0),
    'ready' => (int) ($panelSummary['ready'] ?? 0),
    'delivered' => (int) ($panelSummary['delivered'] ?? 0),
];
$paymentSummary = ['pending' => 0, 'partial' => 0, 'paid' => 0];
$ordersWithCommand = 0;
$ordersWithoutCommand = 0;
$ordersWaitingKitchen = 0;
$formatMoney = static fn ($value): string => 'R$ ' . number_format((float) $value, 2, ',', '.');

foreach ($ordersByTable as $tablePanel) {
    if (!is_array($tablePanel)) {
        continue;
    }
    $orders = is_array($tablePanel['orders'] ?? null) ? $tablePanel['orders'] : [];
    foreach ($orders as $order) {
        if (!is_array($order)) {
            continue;
        }
        if (($order['command_id'] ?? null) !== null) {
            $ordersWithCommand++;
        } else {
            $ordersWithoutCommand++;
        }
        if (!empty($order['can_send_kitchen'])) {
            $ordersWaitingKitchen++;
        }
        $paymentStatus = (string) ($order['payment_status'] ?? '');
        if (isset($paymentSummary[$paymentStatus])) {
            $paymentSummary[$paymentStatus]++;
        }
    }
}
?>

<style>
    body.modal-open{overflow:hidden}
    .orders-page{display:grid;gap:16px}
    .kpi-grid{display:grid;grid-template-columns:repeat(6,minmax(135px,1fr));gap:12px}
    .kpi-item{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px}
    .kpi-item strong{display:block;font-size:24px;line-height:1.1}
    .kpi-item span{color:#64748b;font-size:12px}
    .legend-panel{display:grid;gap:10px}
    .legend-group{border:1px solid #e2e8f0;border-radius:10px;padding:10px;background:#f8fafc}
    .legend-title{display:block;font-size:12px;color:#475569;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px}
    .legend-row{display:flex;gap:8px;flex-wrap:wrap}
    .search-row{display:grid;grid-template-columns:1fr auto;gap:8px}
    .search-info{color:#64748b;font-size:12px;margin-top:6px}
    .muted{color:#64748b}
    .tables-stack{display:grid;gap:14px}
    .table-panel{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:12px;overflow:hidden}
    .table-panel-header{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;margin-bottom:10px}
    .table-panel-title{margin:0}
    .table-panel-subtitle{margin:6px 0 0;color:#64748b;font-size:13px}
    .orders-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;align-items:stretch}
    .order-card{width:100%;min-width:0;text-align:left;background:#fff;border:2px solid #dbeafe;border-radius:12px;padding:10px;display:grid;gap:8px;cursor:pointer;transition:border-color .2s,transform .12s,box-shadow .2s,background-color .2s}
    .order-card:hover{background:#f8fafc;transform:translateY(-1px);box-shadow:0 10px 20px rgba(2,6,23,.08)}
    .order-card:focus{outline:2px solid #60a5fa;outline-offset:1px}
    .order-card.status-op-pending{border-color:#f59e0b}
    .order-card.status-op-received{border-color:#3b82f6}
    .order-card.status-op-preparing{border-color:#6366f1}
    .order-card.status-op-ready{border-color:#22c55e}
    .order-card.status-op-delivered{border-color:#06b6d4}
    .order-card.search-hit{outline:2px solid #60a5fa;outline-offset:1px}
    .order-card-top{display:flex;justify-content:space-between;gap:8px;align-items:flex-start}
    .order-card-number{font-size:15px;font-weight:700;color:#0f172a;overflow-wrap:anywhere}
    .order-card-line{font-size:12px;color:#334155}
    .order-card-customer{font-size:12px;color:#475569;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .order-card-badges{display:flex;gap:6px;flex-wrap:wrap}
    .order-card-hint{font-size:11px;color:#1e40af;background:#eff6ff;border:1px solid #bfdbfe;border-radius:999px;padding:2px 8px;justify-self:start}
    .empty-card{background:#fff;border:1px dashed #cbd5e1;border-radius:12px;padding:18px;color:#475569}

    .order-modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.6);display:grid;place-items:center;padding:12px;z-index:1200}
    .order-modal-backdrop[hidden]{display:none !important}
    .order-modal{width:min(980px,calc(100vw - 24px));max-height:calc(100vh - 24px);overflow-y:auto;overflow-x:hidden;background:#fff;border:2px solid #cbd5e1;border-radius:14px;padding:16px;display:grid;gap:14px;box-sizing:border-box}
    .order-modal.status-op-pending{border-color:#f59e0b}
    .order-modal.status-op-received{border-color:#3b82f6}
    .order-modal.status-op-preparing{border-color:#6366f1}
    .order-modal.status-op-ready{border-color:#22c55e}
    .order-modal.status-op-delivered{border-color:#06b6d4}
    .order-modal *{box-sizing:border-box}
    .modal-header{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap}
    .modal-header h3{margin:0;font-size:18px}
    .modal-header p{margin:6px 0 0;color:#64748b;font-size:13px}
    .modal-meta-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;min-width:0}
    .modal-meta-item{border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;padding:8px;min-width:0}
    .modal-meta-item strong{display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:3px}
    .modal-meta-item span{font-size:13px;color:#0f172a;display:block;overflow-wrap:anywhere}
    .modal-items{border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;padding:14px;display:grid;gap:12px;min-width:0}
    .modal-items-title{display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em}
    .modal-items-list{display:grid;gap:12px;max-height:300px;overflow-y:auto;overflow-x:hidden;padding:4px 2px 2px 0}
    .modal-item{border:1px solid #dbeafe;border-radius:10px;background:#fff;padding:14px 12px}
    .modal-item-main{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap}
    .modal-item-name{font-size:13px;color:#0f172a;font-weight:600;overflow-wrap:anywhere}
    .modal-item-prices{display:flex;flex-wrap:wrap;gap:6px}
    .modal-pill{display:inline-block;padding:3px 8px;border-radius:999px;background:#e2e8f0;color:#334155;font-size:11px}
    .modal-item-additionals{margin-top:10px;display:grid;gap:8px}
    .modal-item-additional{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;padding:10px 12px;border-radius:8px;background:#f8fafc}
    .modal-item-additional strong{font-size:12px;color:#334155;font-weight:600}
    .modal-item-note{margin-top:8px;font-size:12px;color:#475569}
    .modal-actions{display:grid;gap:12px;min-width:0}
    .modal-action-block{border:1px solid #e2e8f0;border-radius:10px;background:#fff;padding:12px;display:grid;gap:10px}
    .modal-action-block form{display:grid;gap:10px;min-width:0}
    .modal-actions-row{display:flex;gap:10px;flex-wrap:wrap;min-width:0}
    .modal-actions-row > *{min-width:0}
    .modal-actions-row input[type="text"]{flex:1 1 320px}
    .modal-actions-row select{flex:1 1 100%}
    .modal-actions-row button{flex:0 0 auto}
    .modal-actions select,.modal-actions input[type="text"]{background:#fff;max-width:100%}
    .modal-actions-row .btn{max-width:100%}
    .modal-actions-row-status-submit{align-items:flex-end}
    .order-modal-feedback{padding:10px;border-radius:8px;font-size:13px}
    .order-modal-feedback.success{background:#dcfce7;color:#166534}
    .order-modal-feedback.error{background:#fee2e2;color:#991b1b}
    .modal-orders-stack{display:grid;gap:12px}
    .modal-order-card{border:1px solid #dbeafe;border-radius:12px;background:#fff;padding:12px;display:grid;gap:10px}
    .modal-order-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
    .modal-order-head-title{display:grid;gap:4px}
    .modal-order-head-title strong{font-size:15px;color:#0f172a;overflow-wrap:anywhere}
    .modal-order-head-title span{font-size:12px;color:#475569}
    .modal-order-empty{border:1px dashed #cbd5e1;border-radius:10px;padding:12px;color:#64748b;background:#f8fafc}
    .modal-footer{display:flex;justify-content:flex-end;padding-top:2px}

    @media (max-width:1160px){
        .kpi-grid{grid-template-columns:repeat(3,minmax(130px,1fr))}
        .modal-meta-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media (max-width:740px){
        .kpi-grid{grid-template-columns:repeat(2,minmax(130px,1fr))}
        .orders-grid{grid-template-columns:1fr}
        .modal-meta-grid{grid-template-columns:1fr}
        .modal-actions-row-status-submit button{width:100%}
    }
</style>

<div class="orders-page">
    <div class="topbar">
        <div>
            <h1>Painel Operacional de Pedidos</h1>
            <p>Visualizacao por mesa e comandas. Clique em uma comanda para abrir o modal de operacao.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/kitchen')) ?>">Fila de cozinha</a>
            <a class="btn" href="<?= htmlspecialchars(base_url('/admin/orders/create')) ?>">Novo pedido</a>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-item"><strong><?= (int) ($panelSummary['active_orders'] ?? 0) ?></strong><span>Pedidos ativos</span></div>
        <div class="kpi-item"><strong><?= (int) ($panelSummary['tables_in_service'] ?? 0) ?></strong><span>Mesas em atendimento</span></div>
        <div class="kpi-item"><strong><?= (int) ($panelSummary['items_total'] ?? 0) ?></strong><span>Itens em andamento</span></div>
        <div class="kpi-item"><strong>R$ <?= number_format((float) ($panelSummary['amount_total'] ?? 0), 2, ',', '.') ?></strong><span>Total em aberto</span></div>
        <div class="kpi-item"><strong><?= $ordersWithCommand ?></strong><span>Com comanda</span></div>
        <div class="kpi-item"><strong><?= $ordersWaitingKitchen ?></strong><span>Aguardando cozinha</span></div>
    </div>

    <div class="card legend-panel">
        <div class="legend-group">
            <strong class="legend-title">Cor da Borda da Comanda</strong>
            <div class="legend-row">
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'pending')) ?>">Pendente</span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'received')) ?>">Recebido</span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'preparing')) ?>">Em preparo</span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'ready')) ?>">Pronto</span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'delivered')) ?>">Entregue</span>
            </div>
        </div>
        <div class="legend-group">
            <strong class="legend-title">Contagem Operacional</strong>
            <div class="legend-row">
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'pending')) ?>">Pendentes: <?= (int) ($operationalSummary['pending'] ?? 0) ?></span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'received')) ?>">Recebidos: <?= (int) ($operationalSummary['received'] ?? 0) ?></span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'preparing')) ?>">Em preparo: <?= (int) ($operationalSummary['preparing'] ?? 0) ?></span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'ready')) ?>">Prontos: <?= (int) ($operationalSummary['ready'] ?? 0) ?></span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', 'delivered')) ?>">Entregues: <?= (int) ($operationalSummary['delivered'] ?? 0) ?></span>
            </div>
        </div>
        <div class="legend-group">
            <strong class="legend-title">Pagamentos</strong>
            <div class="legend-row">
                <span class="badge <?= htmlspecialchars(status_badge_class('order_payment_status', 'pending')) ?>">Pendente: <?= (int) ($paymentSummary['pending'] ?? 0) ?></span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_payment_status', 'partial')) ?>">Parcial: <?= (int) ($paymentSummary['partial'] ?? 0) ?></span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_payment_status', 'paid')) ?>">Pago: <?= (int) ($paymentSummary['paid'] ?? 0) ?></span>
                <span class="badge status-default">Sem comanda: <?= $ordersWithoutCommand ?></span>
                <span class="badge <?= htmlspecialchars(status_badge_class('order_operational_flag', 'paid_waiting_production')) ?>">Pagos aguardando producao: <?= (int) ($panelSummary['paid_waiting_production'] ?? 0) ?></span>
            </div>
            <p class="search-info" style="margin:8px 0 0">Atualizacao automatica da pagina e do modal a cada 30 segundos.</p>
        </div>
    </div>

    <div class="card">
        <div class="search-row">
            <input id="orderSearch" type="text" placeholder="Digite o numero da mesa (ex.: 10) ou use filtros: status:pronto pagamento:pago canal:entrega cliente:ana">
            <button id="clearOrderSearch" class="btn secondary" type="button">Limpar</button>
        </div>
        <div id="orderSearchInfo" class="search-info">Digite apenas o numero da mesa para filtrar rapido, ou use busca livre/filtros por campo.</div>
    </div>

    <?php if (empty($ordersByTable)): ?>
        <div class="empty-card">Nenhum pedido ativo no momento.</div>
    <?php else: ?>
        <div class="tables-stack" id="tablesStack">
            <?php foreach ($ordersByTable as $tablePanel): ?>
                <?php
                $orders = is_array($tablePanel['customer_cards'] ?? null) ? $tablePanel['customer_cards'] : [];
                $ordersCountRaw = (int) ($tablePanel['orders_count'] ?? 0);
                ?>
                <section class="table-panel" data-table-panel>
                    <div class="table-panel-header">
                        <div>
                            <h3 class="table-panel-title"><?= htmlspecialchars((string) ($tablePanel['label'] ?? 'Mesa')) ?></h3>
                            <p class="table-panel-subtitle">
                                Clientes/Comandas: <?= count($orders) ?>
                                | Pedidos: <?= $ordersCountRaw ?>
                                | Itens: <?= (int) ($tablePanel['items_total'] ?? 0) ?>
                                | Total em aberto: R$ <?= number_format((float) ($tablePanel['amount_total'] ?? 0), 2, ',', '.') ?>
                            </p>
                        </div>
                    </div>

                    <div class="orders-grid">
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $cardOrders = is_array($order['orders'] ?? null) ? $order['orders'] : [];
                            $orderId = (int) ($order['anchor_order_id'] ?? 0);
                            if ($orderId <= 0 && isset($cardOrders[0]) && is_array($cardOrders[0])) {
                                $orderId = (int) ($cardOrders[0]['id'] ?? 0);
                            }
                            if ($orderId <= 0) {
                                continue;
                            }

                            $statusValue = (string) ($order['status'] ?? 'pending');
                            $orderBorderClass = in_array($statusValue, ['pending', 'received', 'preparing', 'ready', 'delivered'], true)
                                ? 'status-op-' . $statusValue
                                : 'status-op-pending';

                            $orderItemsSearchParts = [];
                            $orderNumbersParts = [];
                            $orderStatusesRawParts = [];
                            $orderStatusesLabelParts = [];
                            $paymentStatusesRawParts = [];
                            $paymentStatusesLabelParts = [];
                            $channelRawParts = [];
                            $channelLabelParts = [];
                            $latestCreatedCandidates = [];
                            $latestChangedCandidates = [];
                            foreach ($cardOrders as $cardOrder) {
                                if (!is_array($cardOrder)) {
                                    continue;
                                }
                                $orderNumbersParts[] = (string) ($cardOrder['order_number'] ?? '');
                                $currentStatusRaw = (string) ($cardOrder['status'] ?? '');
                                $orderStatusesRawParts[] = $currentStatusRaw;
                                $orderStatusesLabelParts[] = (string) status_label('order_status', $currentStatusRaw);
                                $currentPaymentRaw = (string) ($cardOrder['payment_status'] ?? '');
                                $paymentStatusesRawParts[] = $currentPaymentRaw;
                                $paymentStatusesLabelParts[] = (string) status_label('order_payment_status', $currentPaymentRaw);
                                $currentChannelRaw = (string) ($cardOrder['channel'] ?? '');
                                $channelRawParts[] = $currentChannelRaw;
                                $channelLabelParts[] = (string) status_label('order_channel', $currentChannelRaw);
                                $latestCreatedCandidates[] = (string) ($cardOrder['created_at'] ?? '');
                                $latestChangedCandidates[] = (string) ($cardOrder['latest_status_changed_at'] ?? '');
                                $cardItems = is_array($cardOrder['items'] ?? null) ? $cardOrder['items'] : [];
                                foreach ($cardItems as $searchItem) {
                                    if (!is_array($searchItem)) {
                                        continue;
                                    }
                                    $orderItemsSearchParts[] = (string) ($searchItem['name'] ?? $searchItem['product_name_snapshot'] ?? '');
                                    $orderItemsSearchParts[] = (string) ($searchItem['notes'] ?? '');
                                    $searchAdditionals = is_array($searchItem['additionals'] ?? null) ? $searchItem['additionals'] : [];
                                    foreach ($searchAdditionals as $searchAdditional) {
                                        if (!is_array($searchAdditional)) {
                                            continue;
                                        }
                                        $orderItemsSearchParts[] = (string) ($searchAdditional['name'] ?? '');
                                    }
                                }
                            }

                            $tableLabel = (string) ($tablePanel['label'] ?? '');
                            $tableNumberMatches = [];
                            preg_match_all('/\d+/', $tableLabel, $tableNumberMatches);
                            $tableNumberSearch = trim(implode(' ', is_array($tableNumberMatches[0] ?? null) ? $tableNumberMatches[0] : []));
                            $orderNumberValue = trim(implode(' ', array_values(array_filter($orderNumbersParts, static fn (mixed $value): bool => trim((string) $value) !== ''))));
                            $customerNameValue = (string) ($order['customer_name'] ?? '');
                            $orderStatusRawValue = trim(implode(' ', array_values(array_unique(array_filter(array_merge(
                                [(string) ($order['status'] ?? '')],
                                $orderStatusesRawParts
                            ), static fn (mixed $value): bool => trim((string) $value) !== '')))));
                            $orderStatusLabelValue = trim(implode(' ', array_values(array_unique(array_filter(array_merge(
                                [(string) status_label('order_status', $order['status'] ?? null)],
                                $orderStatusesLabelParts
                            ), static fn (mixed $value): bool => trim((string) $value) !== '')))));
                            $paymentStatusRawValue = trim(implode(' ', array_values(array_unique(array_filter(array_merge(
                                [(string) ($order['payment_status'] ?? '')],
                                $paymentStatusesRawParts
                            ), static fn (mixed $value): bool => trim((string) $value) !== '')))));
                            $paymentStatusLabelValue = trim(implode(' ', array_values(array_unique(array_filter(array_merge(
                                [(string) status_label('order_payment_status', $order['payment_status'] ?? null)],
                                $paymentStatusesLabelParts
                            ), static fn (mixed $value): bool => trim((string) $value) !== '')))));
                            $channelRawValue = trim(implode(' ', array_values(array_unique(array_filter($channelRawParts, static fn (mixed $value): bool => trim((string) $value) !== '')))));
                            $channelLabelValue = trim(implode(' ', array_values(array_unique(array_filter($channelLabelParts, static fn (mixed $value): bool => trim((string) $value) !== '')))));
                            $channelUniqueRaw = array_values(array_unique(array_filter($channelRawParts, static fn (mixed $value): bool => trim((string) $value) !== '')));
                            $channelDisplayValue = count($channelUniqueRaw) === 1 ? (string) $channelUniqueRaw[0] : 'mixed';
                            $channelDisplayLabel = count($channelUniqueRaw) === 1
                                ? status_label('order_channel', (string) $channelUniqueRaw[0])
                                : 'Misto';
                            $paymentStatusDisplayValue = (string) ($order['payment_status'] ?? 'pending');
                            if ($paymentStatusDisplayValue === '') {
                                $paymentStatusDisplayValue = 'pending';
                            }
                            $createdAtValue = (string) ($order['latest_created_at'] ?? '');
                            if ($createdAtValue === '' && $latestCreatedCandidates !== []) {
                                $latestCreatedCandidates = array_values(array_filter($latestCreatedCandidates, static fn (mixed $value): bool => trim((string) $value) !== ''));
                                $createdAtValue = $latestCreatedCandidates !== [] ? (string) max($latestCreatedCandidates) : '';
                            }
                            $latestStatusChangedAtValue = (string) ($order['latest_status_changed_at'] ?? '');
                            if ($latestStatusChangedAtValue === '' && $latestChangedCandidates !== []) {
                                $latestChangedCandidates = array_values(array_filter($latestChangedCandidates, static fn (mixed $value): bool => trim((string) $value) !== ''));
                                $latestStatusChangedAtValue = $latestChangedCandidates !== [] ? (string) max($latestChangedCandidates) : '';
                            }
                            $totalAmountSearchValue = number_format((float) ($order['amount_total'] ?? 0), 2, '.', '');
                            $orderItemsSearchText = implode(' ', $orderItemsSearchParts);
                            $commandSearchValue = ($order['command_id'] ?? null) !== null ? (string) ((int) $order['command_id']) : '';
                            $commandSearchText = ($order['command_id'] ?? null) !== null
                                ? 'comanda ' . $commandSearchValue . ' ativa'
                                : 'sem comanda';
                            $searchText = trim(implode(' ', [
                                $tableLabel,
                                $orderNumberValue,
                                $customerNameValue,
                                $orderStatusRawValue,
                                $orderStatusLabelValue,
                                $paymentStatusRawValue,
                                $paymentStatusLabelValue,
                                $createdAtValue,
                                $latestStatusChangedAtValue,
                                $totalAmountSearchValue,
                                $commandSearchText,
                                $channelRawValue,
                                $channelLabelValue,
                                $orderItemsSearchText,
                            ]));
                            $ordersCount = (int) ($order['orders_count'] ?? count($cardOrders));
                            $itemsTotal = (int) ($order['items_total'] ?? 0);
                            $cardTotalAmount = (float) ($order['amount_total'] ?? 0);
                            $orderIdsCsv = trim((string) ($order['order_ids_csv'] ?? ''));
                            if ($orderIdsCsv === '') {
                                $orderIdsList = [];
                                foreach ($cardOrders as $orderRow) {
                                    if (!is_array($orderRow)) {
                                        continue;
                                    }
                                    $currentOrderId = (int) ($orderRow['id'] ?? 0);
                                    if ($currentOrderId > 0) {
                                        $orderIdsList[] = $currentOrderId;
                                    }
                                }
                                $orderIdsCsv = implode(',', $orderIdsList);
                            }
                            $commandLabel = ($order['command_id'] ?? null) !== null
                                ? 'Comanda ativa #' . (int) $order['command_id']
                                : 'Pedido sem comanda';
                            $customerName = trim((string) ($order['customer_name'] ?? ''));
                            $customerLabel = $customerName !== '' ? $customerName : 'Nao informado';
                            ?>
                            <button
                                type="button"
                                class="order-card <?= htmlspecialchars($orderBorderClass) ?>"
                                data-search="<?= htmlspecialchars($searchText) ?>"
                                data-order-id="<?= $orderId ?>"
                                data-status-class="<?= htmlspecialchars($orderBorderClass) ?>"
                                data-search-table="<?= htmlspecialchars($tableLabel) ?>"
                                data-search-table-number="<?= htmlspecialchars($tableNumberSearch) ?>"
                                data-search-order="<?= htmlspecialchars($orderNumberValue) ?>"
                                data-search-customer="<?= htmlspecialchars($customerNameValue) ?>"
                                data-search-status="<?= htmlspecialchars(trim($orderStatusRawValue . ' ' . $orderStatusLabelValue)) ?>"
                                data-search-payment="<?= htmlspecialchars(trim($paymentStatusRawValue . ' ' . $paymentStatusLabelValue)) ?>"
                                data-search-command="<?= htmlspecialchars(trim($commandSearchText . ' ' . $commandSearchValue)) ?>"
                                data-search-channel="<?= htmlspecialchars(trim($channelRawValue . ' ' . $channelLabelValue)) ?>"
                                data-search-time="<?= htmlspecialchars(trim($createdAtValue . ' ' . $latestStatusChangedAtValue)) ?>"
                                data-search-items="<?= htmlspecialchars($orderItemsSearchText) ?>"
                            >
                                <div class="order-card-top">
                                    <span class="order-card-number"><?= htmlspecialchars($customerLabel) ?></span>
                                    <span class="badge <?= htmlspecialchars(status_badge_class('order_status', $statusValue)) ?>">
                                        <?= htmlspecialchars(status_label('order_status', $statusValue)) ?>
                                    </span>
                                </div>
                                <span class="order-card-line">Pedidos: <?= $ordersCount ?> | Itens: <?= $itemsTotal ?> | Total: <?= htmlspecialchars($formatMoney($cardTotalAmount)) ?></span>
                                <span class="order-card-line">Ultimo pedido: <?= htmlspecialchars($createdAtValue !== '' ? $createdAtValue : '-') ?></span>
                                <span class="order-card-line"><?= htmlspecialchars($commandLabel) ?></span>
                                <span class="order-card-customer">Cliente: <?= htmlspecialchars($customerLabel) ?></span>
                                <div class="order-card-badges">
                                    <span class="badge <?= htmlspecialchars(status_badge_class('order_payment_status', $paymentStatusDisplayValue)) ?>">
                                        <?= htmlspecialchars(status_label('order_payment_status', $paymentStatusDisplayValue)) ?>
                                    </span>
                                    <span class="badge <?= htmlspecialchars($channelDisplayValue === 'mixed' ? 'status-default' : status_badge_class('order_channel', $channelDisplayValue)) ?>">
                                        <?= htmlspecialchars((string) $channelDisplayLabel) ?>
                                    </span>
                                    <?php if (!empty($order['is_paid_waiting_production'])): ?>
                                        <span class="badge <?= htmlspecialchars(status_badge_class('order_operational_flag', 'paid_waiting_production')) ?>">
                                            <?= htmlspecialchars(status_label('order_operational_flag', 'paid_waiting_production')) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <span class="order-card-hint">Abrir resumo do cliente (<?= $ordersCount ?> pedido(s))</span>
                            </button>

                            <template id="order-modal-template-<?= $orderId ?>">
                                <div class="modal-header">
                                    <div>
                                        <h3><?= htmlspecialchars($customerLabel) ?></h3>
                                        <p>
                                            <?= htmlspecialchars((string) ($tablePanel['label'] ?? 'Mesa')) ?>
                                            | <?= htmlspecialchars($commandLabel) ?>
                                            | Pedidos agrupados: <?= $ordersCount ?>
                                        </p>
                                    </div>
                                    <div class="order-card-badges">
                                        <span class="badge <?= htmlspecialchars(status_badge_class('order_status', $statusValue)) ?>">
                                            <?= htmlspecialchars(status_label('order_status', $statusValue)) ?>
                                        </span>
                                        <span class="badge <?= htmlspecialchars(status_badge_class('order_payment_status', $paymentStatusDisplayValue)) ?>">
                                            <?= htmlspecialchars(status_label('order_payment_status', $paymentStatusDisplayValue)) ?>
                                        </span>
                                        <?php if (!empty($order['is_paid_waiting_production'])): ?>
                                            <span class="badge <?= htmlspecialchars(status_badge_class('order_operational_flag', 'paid_waiting_production')) ?>">
                                                <?= htmlspecialchars(status_label('order_operational_flag', 'paid_waiting_production')) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="modal-meta-grid">
                                    <div class="modal-meta-item"><strong>Comanda</strong><span><?= htmlspecialchars($commandLabel) ?></span></div>
                                    <div class="modal-meta-item"><strong>Cliente</strong><span><?= htmlspecialchars($customerLabel) ?></span></div>
                                    <div class="modal-meta-item"><strong>Pedidos</strong><span><?= $ordersCount ?></span></div>
                                    <div class="modal-meta-item"><strong>Itens</strong><span><?= $itemsTotal ?></span></div>
                                    <div class="modal-meta-item"><strong>Total geral</strong><span><?= htmlspecialchars($formatMoney($cardTotalAmount)) ?></span></div>
                                    <div class="modal-meta-item"><strong>Canal</strong><span><?= htmlspecialchars((string) $channelDisplayLabel) ?></span></div>
                                    <div class="modal-meta-item"><strong>Ultimo pedido</strong><span><?= htmlspecialchars($createdAtValue !== '' ? $createdAtValue : '-') ?></span></div>
                                    <div class="modal-meta-item"><strong>Ultima mudanca</strong><span><?= htmlspecialchars($latestStatusChangedAtValue !== '' ? $latestStatusChangedAtValue : '-') ?></span></div>
                                    <div class="modal-meta-item"><strong>Atualizacao</strong><span>Automatica em 30s</span></div>
                                </div>

                                <div class="modal-actions">
                                    <div class="order-modal-feedback" data-modal-feedback hidden></div>
                                    <div class="modal-action-block">
                                        <div class="modal-actions-row">
                                            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/orders/print-ticket?order_ids=' . urlencode($orderIdsCsv) . '&return_order_id=' . $orderId)) ?>">Imprimir ticket geral</a>
                                        </div>
                                    </div>
                                </div>

                                <?php if (empty($cardOrders)): ?>
                                    <div class="modal-order-empty">Nenhum pedido detalhado para este cliente/comanda.</div>
                                <?php else: ?>
                                    <div class="modal-orders-stack">
                                        <?php foreach ($cardOrders as $orderRow): ?>
                                            <?php
                                            if (!is_array($orderRow)) {
                                                continue;
                                            }
                                            $rowOrderId = (int) ($orderRow['id'] ?? 0);
                                            $rowNextStatuses = $orderRow['next_statuses'] ?? [];
                                            if (!is_array($rowNextStatuses)) {
                                                $rowNextStatuses = [];
                                            }
                                            if (!$canCancelOrder) {
                                                $rowNextStatuses = array_values(array_filter(
                                                    $rowNextStatuses,
                                                    static fn (mixed $status): bool => (string) $status !== 'canceled'
                                                ));
                                            }
                                            if (!empty($orderRow['can_send_kitchen'])) {
                                                $rowNextStatuses = array_values(array_filter(
                                                    $rowNextStatuses,
                                                    static fn (mixed $status): bool => (string) $status !== 'received'
                                                ));
                                            }
                                            $rowHasOperationalActions = ($canSendKitchen && !empty($orderRow['can_send_kitchen'])) || ($canUpdateStatus && !empty($rowNextStatuses));
                                            $rowItems = is_array($orderRow['items'] ?? null) ? $orderRow['items'] : [];
                                            ?>
                                            <article class="modal-order-card">
                                                <div class="modal-order-head">
                                                    <div class="modal-order-head-title">
                                                        <strong><?= htmlspecialchars((string) ($orderRow['order_number'] ?? '-')) ?></strong>
                                                        <span>Criado em: <?= htmlspecialchars((string) ($orderRow['created_at'] ?? '-')) ?> | Itens: <?= (int) ($orderRow['items_count'] ?? 0) ?> | Total: <?= htmlspecialchars($formatMoney((float) ($orderRow['total_amount'] ?? 0))) ?></span>
                                                    </div>
                                                    <div class="order-card-badges">
                                                        <span class="badge <?= htmlspecialchars(status_badge_class('order_status', $orderRow['status'] ?? null)) ?>">
                                                            <?= htmlspecialchars(status_label('order_status', $orderRow['status'] ?? null)) ?>
                                                        </span>
                                                        <span class="badge <?= htmlspecialchars(status_badge_class('order_payment_status', $orderRow['payment_status'] ?? null)) ?>">
                                                            <?= htmlspecialchars(status_label('order_payment_status', $orderRow['payment_status'] ?? null)) ?>
                                                        </span>
                                                        <span class="badge <?= htmlspecialchars(status_badge_class('order_channel', $orderRow['channel'] ?? 'table')) ?>">
                                                            <?= htmlspecialchars(status_label('order_channel', $orderRow['channel'] ?? 'table')) ?>
                                                        </span>
                                                    </div>
                                                </div>

                                                <div class="modal-items">
                                                    <span class="modal-items-title">Produtos e adicionais do pedido</span>
                                                    <?php if (empty($rowItems)): ?>
                                                        <span class="muted">Nenhum item detalhado para este pedido.</span>
                                                    <?php else: ?>
                                                        <div class="modal-items-list">
                                                            <?php foreach ($rowItems as $item): ?>
                                                                <?php
                                                                $itemName = (string) ($item['name'] ?? $item['product_name_snapshot'] ?? 'Item');
                                                                $itemQuantity = (int) ($item['quantity'] ?? 0);
                                                                $itemUnitPrice = (float) ($item['unit_price'] ?? 0);
                                                                $itemLineSubtotal = (float) ($item['line_subtotal'] ?? 0);
                                                                $itemNotes = trim((string) ($item['notes'] ?? ''));
                                                                $itemAdditionals = is_array($item['additionals'] ?? null) ? $item['additionals'] : [];
                                                                ?>
                                                                <div class="modal-item">
                                                                    <div class="modal-item-main">
                                                                        <span class="modal-item-name"><?= $itemQuantity ?>x <?= htmlspecialchars($itemName) ?></span>
                                                                        <span class="modal-item-prices">
                                                                            <span class="modal-pill">Unit: <?= htmlspecialchars($formatMoney($itemUnitPrice)) ?></span>
                                                                            <span class="modal-pill">Total: <?= htmlspecialchars($formatMoney($itemLineSubtotal)) ?></span>
                                                                        </span>
                                                                    </div>

                                                                    <?php if ($itemAdditionals !== []): ?>
                                                                        <div class="modal-item-additionals">
                                                                            <?php foreach ($itemAdditionals as $additional): ?>
                                                                                <div class="modal-item-additional">
                                                                                    <strong>
                                                                                        + <?= (int) ($additional['quantity'] ?? 0) ?>x <?= htmlspecialchars((string) ($additional['name'] ?? 'Adicional')) ?>
                                                                                    </strong>
                                                                                    <span class="modal-item-prices">
                                                                                        <span class="modal-pill">Unit: <?= htmlspecialchars($formatMoney((float) ($additional['unit_price'] ?? 0))) ?></span>
                                                                                        <span class="modal-pill">Total: <?= htmlspecialchars($formatMoney((float) ($additional['line_subtotal'] ?? 0))) ?></span>
                                                                                    </span>
                                                                                </div>
                                                                            <?php endforeach; ?>
                                                                        </div>
                                                                    <?php endif; ?>

                                                                    <?php if ($itemNotes !== ''): ?>
                                                                        <div class="modal-item-note">Obs.: <?= htmlspecialchars($itemNotes) ?></div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="modal-actions">
                                                    <div class="modal-action-block">
                                                        <div class="modal-actions-row">
                                                            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/orders/print-ticket?order_id=' . $rowOrderId . '&return_order_id=' . $orderId)) ?>">Imprimir ticket do pedido</a>
                                                        </div>
                                                    </div>

                                                    <?php if ($canSendKitchen && !empty($orderRow['can_send_kitchen'])): ?>
                                                        <div class="modal-action-block">
                                                            <form class="js-modal-action-form" method="POST" action="<?= htmlspecialchars(base_url('/admin/orders/send-kitchen')) ?>">
                                                                <input type="hidden" name="order_id" value="<?= $rowOrderId ?>">
                                                                <button class="btn secondary" type="submit">Enviar para cozinha</button>
                                                            </form>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($canUpdateStatus && !empty($rowNextStatuses)): ?>
                                                        <div class="modal-action-block">
                                                            <form class="js-modal-action-form" method="POST" action="<?= htmlspecialchars(base_url('/admin/orders/status')) ?>">
                                                                <input type="hidden" name="order_id" value="<?= $rowOrderId ?>">
                                                                <div class="modal-actions-row modal-actions-row-status-select">
                                                                    <select name="new_status" required>
                                                                        <option value="">Selecione o novo status</option>
                                                                        <?php foreach ($rowNextStatuses as $status): ?>
                                                                            <option value="<?= htmlspecialchars((string) $status) ?>"><?= htmlspecialchars(status_label('order_status', $status)) ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="modal-actions-row modal-actions-row-status-submit">
                                                                    <input name="status_notes" type="text" placeholder="Observacao (opcional)">
                                                                    <button class="btn secondary" type="submit">Atualizar status</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (!$rowHasOperationalActions): ?>
                                                        <span class="badge status-default">Sem acoes operacionais disponiveis para este pedido</span>
                                                    <?php endif; ?>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="modal-footer">
                                    <button type="button" class="btn secondary" data-modal-close>Fechar</button>
                                </div>
                            </template>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="order-modal-backdrop" id="orderModalRoot" hidden>
    <section class="order-modal" id="orderModalDialog" role="dialog" aria-modal="true">
        <div id="orderModalBody"></div>
    </section>
</div>

<script>
(() => {
    const cards = Array.from(document.querySelectorAll('.order-card[data-order-id][data-search]'));
    const tablePanels = Array.from(document.querySelectorAll('[data-table-panel]'));
    const searchInput = document.getElementById('orderSearch');
    const clearButton = document.getElementById('clearOrderSearch');
    const searchInfo = document.getElementById('orderSearchInfo');
    const pageRefreshMs = 30000;
    const modalRoot = document.getElementById('orderModalRoot');
    const modalDialog = document.getElementById('orderModalDialog');
    const modalBody = document.getElementById('orderModalBody');
    const modalStorageKey = 'orders.activeModalOrderId';
    const modalRestoreFlagKey = 'orders.restoreModalOnNextLoad';
    let activeOrderId = null;

    const normalize = (value) => String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

    const filterAliases = {
        table: ['mesa', 'm', 'table'],
        order: ['pedido', 'ped', 'numero', 'num', 'id', 'ordem'],
        customer: ['cliente', 'cl', 'nome'],
        status: ['status', 'st', 'situacao'],
        payment: ['pagamento', 'pag', 'pg', 'payment'],
        command: ['comanda', 'cmd'],
        channel: ['canal', 'channel', 'origem'],
        items: ['item', 'itens', 'produto', 'prod', 'adicional', 'add'],
        time: ['data', 'hora', 'horario', 'dt', 'criado', 'alterado'],
    };
    const filterKeyToField = {};
    Object.entries(filterAliases).forEach(([field, aliases]) => {
        aliases.forEach((alias) => {
            filterKeyToField[normalize(alias)] = field;
        });
    });
    const cardSearchIndex = new Map(cards.map((card) => [card, {
        all: String(card.getAttribute('data-search') || ''),
        table: String(card.getAttribute('data-search-table') || ''),
        tableNumber: String(card.getAttribute('data-search-table-number') || ''),
        order: String(card.getAttribute('data-search-order') || ''),
        customer: String(card.getAttribute('data-search-customer') || ''),
        status: String(card.getAttribute('data-search-status') || ''),
        payment: String(card.getAttribute('data-search-payment') || ''),
        command: String(card.getAttribute('data-search-command') || ''),
        channel: String(card.getAttribute('data-search-channel') || ''),
        items: String(card.getAttribute('data-search-items') || ''),
        time: String(card.getAttribute('data-search-time') || ''),
    }]));
    const filterFieldLabel = {
        table: 'mesa',
        tableNumber: 'mesa',
        order: 'pedido',
        customer: 'cliente',
        status: 'status',
        payment: 'pagamento',
        command: 'comanda',
        channel: 'canal',
        items: 'item',
        time: 'data/hora',
    };
    const resetSearchInfoText = 'Digite apenas o numero da mesa para filtrar rapido, ou use busca livre/filtros por campo.';

    const splitQueryTokens = (rawQuery) => String(rawQuery || '').trim().split(/\s+/).filter(Boolean);
    const compact = (value) => normalize(value).replace(/[^a-z0-9]/g, '');
    const isSubsequence = (haystack, needle) => {
        let index = 0;
        for (let i = 0; i < haystack.length && index < needle.length; i += 1) {
            if (haystack[i] === needle[index]) {
                index += 1;
            }
        }
        return index === needle.length;
    };
    const smartIncludes = (haystackValue, needleValue) => {
        const haystack = normalize(haystackValue);
        const needle = normalize(needleValue);
        if (needle === '') {
            return true;
        }
        if (haystack.includes(needle)) {
            return true;
        }

        const haystackCompact = compact(haystack);
        const needleCompact = compact(needle);
        if (needleCompact.length >= 3 && haystackCompact.includes(needleCompact)) {
            return true;
        }
        if (needleCompact.length >= 3 && isSubsequence(haystackCompact, needleCompact)) {
            return true;
        }

        if (needle.length >= 2) {
            const words = haystack.split(/\s+/).filter(Boolean);
            if (words.some((word) => word.startsWith(needle))) {
                return true;
            }
        }

        return false;
    };
    const parseSearchQuery = (rawQuery) => {
        const filters = [];
        const freeTokens = [];

        splitQueryTokens(rawQuery).forEach((rawToken) => {
            const normalizedToken = normalize(rawToken);
            const tokenParts = normalizedToken.match(/^([^:=]+)[:=](.+)$/);
            if (!tokenParts) {
                if (/^\d+$/.test(normalizedToken)) {
                    filters.push({ field: 'tableNumber', value: normalizedToken });
                    return;
                }
                freeTokens.push(normalizedToken);
                return;
            }

            const key = normalize(tokenParts[1] || '');
            const value = normalize(tokenParts[2] || '');
            const field = filterKeyToField[key] || null;
            if (!field || value === '') {
                const expanded = normalize(rawToken.replace(/[:=]/g, ' ')).split(/\s+/).filter(Boolean);
                freeTokens.push(...expanded);
                return;
            }

            filters.push({ field, value });
        });

        return { filters, freeTokens };
    };
    const doesCardMatchQuery = (card, parsedQuery) => {
        const searchIndex = cardSearchIndex.get(card);
        if (!searchIndex) {
            return false;
        }

        const filtersMatch = parsedQuery.filters.every((filter) => {
            if (filter.field === 'table') {
                return smartIncludes(searchIndex.table || '', filter.value)
                    || smartIncludes(searchIndex.tableNumber || '', filter.value);
            }
            if (filter.field === 'tableNumber') {
                return smartIncludes(searchIndex.tableNumber || '', filter.value);
            }
            return smartIncludes(searchIndex[filter.field] || '', filter.value);
        });
        if (!filtersMatch) {
            return false;
        }

        return parsedQuery.freeTokens.every((token) => smartIncludes(searchIndex.all || '', token));
    };

    const findCardByOrderId = (orderId) => cards.find((card) => Number(card.getAttribute('data-order-id') || 0) === Number(orderId || 0)) || null;

    const applyModalStatusClass = (orderId) => {
        if (!modalDialog) {
            return;
        }
        modalDialog.classList.remove('status-op-pending', 'status-op-received', 'status-op-preparing', 'status-op-ready', 'status-op-delivered');
        const card = findCardByOrderId(orderId);
        if (!card) {
            return;
        }
        const statusClass = String(card.getAttribute('data-status-class') || '').trim();
        if (statusClass !== '') {
            modalDialog.classList.add(statusClass);
        }
    };

    const saveModalState = () => {
        if (activeOrderId !== null) {
            window.sessionStorage.setItem(modalStorageKey, String(activeOrderId));
        }
    };

    const clearModalState = () => {
        window.sessionStorage.removeItem(modalStorageKey);
        window.sessionStorage.removeItem(modalRestoreFlagKey);
    };

    const closeModal = (clearStored = true) => {
        if (!modalRoot || !modalBody) {
            return;
        }
        modalRoot.hidden = true;
        modalBody.innerHTML = '';
        if (modalDialog) {
            modalDialog.classList.remove('status-op-pending', 'status-op-received', 'status-op-preparing', 'status-op-ready', 'status-op-delivered');
        }
        document.body.classList.remove('modal-open');
        activeOrderId = null;
        if (clearStored) {
            clearModalState();
        }
    };

    const setFeedback = (container, message, isError) => {
        if (!(container instanceof HTMLElement)) {
            return;
        }
        container.hidden = false;
        container.textContent = String(message || '');
        container.classList.remove('success', 'error');
        container.classList.add(isError ? 'error' : 'success');
    };

    const bindModalActions = (orderId) => {
        if (!modalBody) {
            return;
        }

        const closeButtons = Array.from(modalBody.querySelectorAll('[data-modal-close]'));
        closeButtons.forEach((button) => {
            button.addEventListener('click', () => closeModal(true));
        });

        const forms = Array.from(modalBody.querySelectorAll('.js-modal-action-form'));
        forms.forEach((form) => {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                event.stopPropagation();
                if (typeof event.stopImmediatePropagation === 'function') {
                    event.stopImmediatePropagation();
                }
                const feedback = modalBody.querySelector('[data-modal-feedback]');
                const submitControls = Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'));
                submitControls.forEach((control) => {
                    control.disabled = true;
                });

                try {
                    const response = await window.fetch(form.action, {
                        method: 'POST',
                        body: new window.FormData(form),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });

                    let payload = null;
                    try {
                        payload = await response.json();
                    } catch (jsonError) {
                        payload = null;
                    }

                    const isOk = response.ok && payload && payload.ok === true;
                    if (!isOk) {
                        const message = payload && payload.message
                            ? payload.message
                            : 'Falha ao executar a acao operacional.';
                        setFeedback(feedback, message, true);
                        return;
                    }

                    setFeedback(feedback, payload.message || 'Acao realizada com sucesso. Atualizando painel...', false);
                    activeOrderId = Number(orderId || 0) > 0 ? Number(orderId) : null;
                    saveModalState();
                    window.sessionStorage.setItem(modalRestoreFlagKey, '1');
                    window.setTimeout(() => {
                        if (document.hidden) {
                            return;
                        }
                        window.location.reload();
                    }, 500);
                } catch (error) {
                    setFeedback(feedback, 'Falha de comunicacao com o servidor.', true);
                } finally {
                    submitControls.forEach((control) => {
                        control.disabled = false;
                    });
                }
            });
        });
    };

    const openModal = (orderId, persist = true) => {
        if (!modalRoot || !modalBody) {
            return;
        }

        const numericOrderId = Number(orderId || 0);
        if (numericOrderId <= 0) {
            return;
        }

        const template = document.getElementById(`order-modal-template-${numericOrderId}`);
        if (!(template instanceof HTMLTemplateElement)) {
            return;
        }

        modalBody.innerHTML = template.innerHTML;

        modalRoot.hidden = false;
        document.body.classList.add('modal-open');
        activeOrderId = numericOrderId;
        applyModalStatusClass(numericOrderId);
        if (persist) {
            saveModalState();
        }

        bindModalActions(numericOrderId);
    };

    const applyFilter = () => {
        const rawQuery = searchInput ? searchInput.value : '';
        const parsedQuery = parseSearchQuery(rawQuery);
        let visibleCards = 0;
        let firstVisibleCard = null;

        tablePanels.forEach((panel) => {
            const panelCards = Array.from(panel.querySelectorAll('.order-card[data-search]'));
            let visibleInPanel = 0;

            panelCards.forEach((card) => {
                card.classList.remove('search-hit');
                const match = doesCardMatchQuery(card, parsedQuery);
                card.style.display = match ? '' : 'none';

                if (match) {
                    visibleCards++;
                    visibleInPanel++;
                    if (firstVisibleCard === null) {
                        firstVisibleCard = card;
                    }
                }
            });

            panel.style.display = visibleInPanel > 0 ? '' : 'none';
        });

        if (firstVisibleCard) {
            firstVisibleCard.classList.add('search-hit');
        }

        if (searchInfo) {
            const hasFilters = parsedQuery.filters.length > 0;
            const hasFreeTokens = parsedQuery.freeTokens.length > 0;
            if (!hasFilters && !hasFreeTokens) {
                searchInfo.textContent = resetSearchInfoText;
            } else {
                const filterInfo = parsedQuery.filters
                    .map((filter) => `${filterFieldLabel[filter.field] || filter.field}:${filter.value}`)
                    .join(', ');
                const freeInfo = parsedQuery.freeTokens.join(' ');
                const criteriaInfo = [filterInfo, freeInfo].filter(Boolean).join(' | ');
                searchInfo.textContent = visibleCards > 0
                    ? `Filtro ativo (${criteriaInfo}): ${visibleCards} comanda(s) encontrada(s).`
                    : `Nenhuma comanda encontrada para: ${criteriaInfo}.`;
            }
        }
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

    cards.forEach((card) => {
        card.addEventListener('click', () => {
            const orderId = Number(card.getAttribute('data-order-id') || 0);
            openModal(orderId, true);
        });
    });

    if (modalRoot) {
        modalRoot.addEventListener('click', (event) => {
            if (event.target === modalRoot) {
                closeModal(true);
            }
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modalRoot && !modalRoot.hidden) {
            closeModal(true);
        }
    });

    window.setInterval(() => {
        if (document.hidden) {
            return;
        }
        if (modalRoot && !modalRoot.hidden) {
            saveModalState();
            window.sessionStorage.setItem(modalRestoreFlagKey, '1');
        } else {
            clearModalState();
        }
        window.location.reload();
    }, pageRefreshMs);

    applyFilter();

    const url = new URL(window.location.href);
    const queryOpenOrderId = Number(url.searchParams.get('open_order_id') || 0);
    if (queryOpenOrderId > 0 && findCardByOrderId(queryOpenOrderId)) {
        openModal(queryOpenOrderId, true);
        url.searchParams.delete('open_order_id');
        const nextSearch = url.searchParams.toString();
        const nextUrl = `${url.pathname}${nextSearch !== '' ? '?' + nextSearch : ''}${url.hash}`;
        window.history.replaceState({}, '', nextUrl);
        return;
    }

    const shouldRestoreModal = window.sessionStorage.getItem(modalRestoreFlagKey) === '1';
    window.sessionStorage.removeItem(modalRestoreFlagKey);
    const persistedOrderId = Number(window.sessionStorage.getItem(modalStorageKey) || 0);
    if (shouldRestoreModal && persistedOrderId > 0) {
        if (findCardByOrderId(persistedOrderId)) {
            openModal(persistedOrderId, true);
        } else {
            clearModalState();
        }
    } else if (!shouldRestoreModal && persistedOrderId > 0) {
        window.sessionStorage.removeItem(modalStorageKey);
    }
})();
</script>
