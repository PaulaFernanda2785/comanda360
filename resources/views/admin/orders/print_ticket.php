<?php
$context = is_array($context ?? null) ? $context : [];
$order = is_array($context['order'] ?? null) ? $context['order'] : [];
$items = is_array($context['items'] ?? null) ? $context['items'] : [];
$ordersContext = is_array($context['orders'] ?? null) ? $context['orders'] : [];
$group = is_array($context['group'] ?? null) ? $context['group'] : [];
$isGrouped = !empty($context['is_grouped']);
$generatedAt = (string) ($context['generated_at'] ?? date('Y-m-d H:i:s'));

if ($ordersContext === [] && $order !== []) {
    $ordersContext = [[
        'order' => $order,
        'items' => $items,
    ]];
}

$companyName = trim((string) ($order['company_name'] ?? 'Empresa'));
$companyLogoPath = trim((string) ($order['company_logo_path'] ?? ''));
$companyLogoUrl = $companyLogoPath !== '' ? asset_url($companyLogoPath) : '';

$groupOrderNumbers = is_array($group['order_numbers'] ?? null) ? $group['order_numbers'] : [];
$orderNumber = $isGrouped
    ? 'Agrupado (' . count($ordersContext) . ' pedidos)'
    : (string) ($order['order_number'] ?? '-');
$tableNumber = $group['table_number'] ?? ($order['table_number'] ?? null);
$tableNumber = $tableNumber !== null ? (int) $tableNumber : null;
$commandId = $group['command_id'] ?? ($order['command_id'] ?? null);
$commandId = $commandId !== null ? (int) $commandId : null;
$customerName = trim((string) ($group['customer_name'] ?? ($order['customer_name'] ?? '')));
$createdAt = (string) ($order['created_at'] ?? '-');
$statusValue = (string) ($group['status'] ?? ($order['status'] ?? ''));
$paymentStatusValue = (string) ($group['payment_status'] ?? ($order['payment_status'] ?? ''));
$notes = trim((string) ($group['notes'] ?? ($order['notes'] ?? '')));
$returnOrderId = isset($_GET['return_order_id']) ? (int) $_GET['return_order_id'] : 0;
$backToOrdersUrl = $returnOrderId > 0
    ? base_url('/admin/orders?open_order_id=' . $returnOrderId)
    : base_url('/admin/orders');

$subtotal = (float) ($group['subtotal_amount'] ?? ($order['subtotal_amount'] ?? 0));
$discount = (float) ($group['discount_amount'] ?? ($order['discount_amount'] ?? 0));
$deliveryFee = (float) ($group['delivery_fee'] ?? ($order['delivery_fee'] ?? 0));
$total = (float) ($group['total_amount'] ?? ($order['total_amount'] ?? 0));
?>

<style>
    .ticket-preview-page{display:grid;gap:16px}
    .ticket-screen-only{display:block}
    .ticket-actions{display:flex;gap:8px;flex-wrap:wrap}
    .ticket-preview-grid{display:grid;grid-template-columns:minmax(260px,340px) minmax(0,1fr);gap:16px;align-items:start}
    .ticket-summary{display:grid;gap:12px}
    .ticket-summary-head{display:grid;gap:8px}
    .ticket-summary-head h3{margin:0;font-size:18px;overflow-wrap:anywhere}
    .ticket-summary-badges{display:flex;gap:6px;flex-wrap:wrap}
    .ticket-summary-meta{display:grid;grid-template-columns:1fr 1fr;gap:8px}
    .ticket-meta-item{border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;padding:8px;min-width:0}
    .ticket-meta-item strong{display:block;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
    .ticket-meta-item span{display:block;color:#0f172a;font-size:13px;overflow-wrap:anywhere}
    .ticket-order-notes{border:1px solid #dbeafe;background:#eff6ff;border-radius:10px;padding:10px;font-size:13px;color:#1e3a8a;overflow-wrap:anywhere}
    .ticket-order-notes strong{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px}
    .ticket-sheet-card{padding:16px !important}
    .ticket-sheet-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px}
    .ticket-sheet-head strong{font-size:15px}
    .ticket-sheet-head span{font-size:12px;color:#64748b}
    .ticket-sheet-stage{background:linear-gradient(180deg,#f8fafc 0%,#f1f5f9 100%);border:1px solid #e2e8f0;border-radius:12px;padding:14px;display:grid;place-items:center}
    .ticket-paper{width:80mm;max-width:100%;margin:0 auto;background:#fff;border:1px solid #d1d5db;box-shadow:0 10px 24px rgba(15,23,42,.08);padding:8px 8px 10px;font-family:"Courier New",Courier,monospace;font-size:12px;color:#0f172a;line-height:1.25}
    .ticket-center{text-align:center}
    .ticket-logo{width:56px;height:56px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb}
    .ticket-title{font-size:14px;font-weight:700;margin-top:4px}
    .ticket-divider{border-top:1px dashed #64748b;margin:6px 0}
    .ticket-row{display:flex;justify-content:space-between;gap:8px}
    .ticket-row > span,.ticket-row > strong{min-width:0;overflow-wrap:anywhere}
    .ticket-muted{color:#475569}
    .ticket-item{padding:4px 0;border-bottom:1px dotted #cbd5e1}
    .ticket-item-name{font-weight:700}
    .ticket-item-meta{font-size:11px;color:#334155}
    .ticket-additional{padding-left:10px;font-size:11px;color:#334155;overflow-wrap:anywhere}
    .ticket-total{font-size:14px;font-weight:700}
    .ticket-order-section{padding:6px 0;border-bottom:1px dashed #cbd5e1}
    .ticket-order-section:last-child{border-bottom:0}
    .ticket-order-title{font-size:12px;font-weight:700;color:#0f172a}

    @page { size: 80mm auto; margin: 4mm; }

    @media (max-width:960px){
        .ticket-preview-grid{grid-template-columns:1fr}
        .ticket-summary-meta{grid-template-columns:1fr}
    }

    @media print {
        body{background:#fff}
        .shell{display:block !important}
        .shell > aside{display:none !important}
        main{padding:0 !important}
        .ticket-screen-only{display:none !important}
        .ticket-preview-grid{display:block}
        .ticket-sheet-card{padding:0 !important;box-shadow:none !important;background:#fff !important}
        .ticket-sheet-stage{padding:0 !important;border:0 !important;background:#fff !important}
        .ticket-paper{border:none;box-shadow:none;width:80mm;max-width:80mm;padding:0}
    }
</style>

<div class="ticket-preview-page">
    <div class="topbar ticket-screen-only">
        <div>
            <h1 style="margin:0">Ticket do pedido</h1>
            <p style="margin:6px 0 0;color:#64748b">Previa no padrao operacional do sistema com impressao termica 80mm.</p>
        </div>
        <div class="ticket-actions">
            <a class="btn secondary" href="<?= htmlspecialchars($backToOrdersUrl) ?>">Voltar</a>
            <button class="btn" type="button" onclick="window.print()">Imprimir ticket</button>
        </div>
    </div>

    <div class="ticket-preview-grid">
        <section class="card ticket-summary ticket-screen-only">
            <div class="ticket-summary-head">
                <h3><?= htmlspecialchars($orderNumber) ?></h3>
                <div class="ticket-summary-badges">
                    <span class="badge <?= htmlspecialchars(status_badge_class('order_status', $statusValue)) ?>">
                        <?= htmlspecialchars(status_label('order_status', $statusValue)) ?>
                    </span>
                    <span class="badge <?= htmlspecialchars(status_badge_class('order_payment_status', $paymentStatusValue)) ?>">
                        <?= htmlspecialchars(status_label('order_payment_status', $paymentStatusValue)) ?>
                    </span>
                </div>
            </div>

            <div class="ticket-summary-meta">
                <div class="ticket-meta-item"><strong>Mesa</strong><span><?= $tableNumber !== null ? 'Mesa ' . $tableNumber : '-' ?></span></div>
                <div class="ticket-meta-item"><strong>Comanda</strong><span><?= $commandId !== null ? '#' . $commandId : '-' ?></span></div>
                <div class="ticket-meta-item"><strong>Cliente</strong><span><?= htmlspecialchars($customerName !== '' ? $customerName : '-') ?></span></div>
                <div class="ticket-meta-item"><strong>Pedidos</strong><span><?= count($ordersContext) ?></span></div>
                <div class="ticket-meta-item"><strong>Criado em</strong><span><?= htmlspecialchars($createdAt) ?></span></div>
                <div class="ticket-meta-item"><strong>Subtotal</strong><span>R$ <?= number_format($subtotal, 2, ',', '.') ?></span></div>
                <div class="ticket-meta-item"><strong>Total</strong><span>R$ <?= number_format($total, 2, ',', '.') ?></span></div>
            </div>

            <?php if ($isGrouped && $groupOrderNumbers !== []): ?>
                <div class="ticket-order-notes">
                    <strong>Pedidos agrupados</strong>
                    <span><?= htmlspecialchars(implode(' | ', array_map(static fn (mixed $value): string => (string) $value, $groupOrderNumbers))) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($notes !== ''): ?>
                <div class="ticket-order-notes">
                    <strong>Observacao do pedido</strong>
                    <span><?= htmlspecialchars($notes) ?></span>
                </div>
            <?php endif; ?>
        </section>

        <section class="card ticket-sheet-card">
            <div class="ticket-sheet-head ticket-screen-only">
                <strong>Previa do ticket</strong>
                <span>Area exata para impressora termica 80mm.</span>
            </div>

            <div class="ticket-sheet-stage">
                <section class="ticket-paper">
                    <div class="ticket-center">
                        <?php if ($companyLogoUrl !== ''): ?>
                            <img class="ticket-logo" src="<?= htmlspecialchars($companyLogoUrl) ?>" alt="Logo da empresa">
                        <?php endif; ?>
                        <div class="ticket-title"><?= htmlspecialchars($companyName) ?></div>
                        <div class="ticket-muted">Comanda360.com.br</div>
                    </div>

                    <div class="ticket-divider"></div>

                    <div class="ticket-row"><span>Pedido</span><strong><?= htmlspecialchars($orderNumber) ?></strong></div>
                    <div class="ticket-row"><span>Mesa</span><span><?= $tableNumber !== null ? 'Mesa ' . $tableNumber : '-' ?></span></div>
                    <div class="ticket-row"><span>Comanda</span><span><?= $commandId !== null ? '#' . $commandId : '-' ?></span></div>
                    <div class="ticket-row"><span>Cliente</span><span><?= htmlspecialchars($customerName !== '' ? $customerName : '-') ?></span></div>
                    <div class="ticket-row"><span>Data</span><span><?= htmlspecialchars($createdAt) ?></span></div>
                    <div class="ticket-row"><span>Status</span><span><?= htmlspecialchars(status_label('order_status', $statusValue)) ?></span></div>
                    <div class="ticket-row"><span>Pagamento</span><span><?= htmlspecialchars(status_label('order_payment_status', $paymentStatusValue)) ?></span></div>
                    <div class="ticket-row"><span>Pedidos no ticket</span><span><?= count($ordersContext) ?></span></div>

                    <div class="ticket-divider"></div>

                    <?php if (empty($ordersContext)): ?>
                        <div class="ticket-muted">Sem pedidos para este ticket.</div>
                    <?php else: ?>
                        <?php foreach ($ordersContext as $bundle): ?>
                            <?php
                            $bundleOrder = is_array($bundle['order'] ?? null) ? $bundle['order'] : [];
                            $bundleItems = is_array($bundle['items'] ?? null) ? $bundle['items'] : [];
                            $bundleOrderNumber = (string) ($bundleOrder['order_number'] ?? '-');
                            $bundleCreatedAt = (string) ($bundleOrder['created_at'] ?? '-');
                            $bundleStatus = (string) ($bundleOrder['status'] ?? '');
                            $bundlePaymentStatus = (string) ($bundleOrder['payment_status'] ?? '');
                            ?>
                            <div class="ticket-order-section">
                                <div class="ticket-row">
                                    <span class="ticket-order-title"><?= htmlspecialchars($bundleOrderNumber) ?></span>
                                    <strong>R$ <?= number_format((float) ($bundleOrder['total_amount'] ?? 0), 2, ',', '.') ?></strong>
                                </div>
                                <div class="ticket-item-meta">Data: <?= htmlspecialchars($bundleCreatedAt) ?> | Status: <?= htmlspecialchars(status_label('order_status', $bundleStatus)) ?> | Pagamento: <?= htmlspecialchars(status_label('order_payment_status', $bundlePaymentStatus)) ?></div>
                                <?php if (empty($bundleItems)): ?>
                                    <div class="ticket-item-meta">Sem itens detalhados neste pedido.</div>
                                <?php else: ?>
                                    <?php foreach ($bundleItems as $item): ?>
                                        <?php
                                        $itemAdditionals = is_array($item['additionals'] ?? null) ? $item['additionals'] : [];
                                        $itemName = (string) ($item['name'] ?? 'Produto');
                                        $itemQty = (int) ($item['quantity'] ?? 0);
                                        $itemSubtotal = (float) ($item['line_subtotal'] ?? 0);
                                        $itemUnitPrice = (float) ($item['unit_price'] ?? 0);
                                        $itemNotes = trim((string) ($item['notes'] ?? ''));
                                        ?>
                                        <div class="ticket-item">
                                            <div class="ticket-row">
                                                <span class="ticket-item-name"><?= $itemQty ?>x <?= htmlspecialchars($itemName) ?></span>
                                                <strong>R$ <?= number_format($itemSubtotal, 2, ',', '.') ?></strong>
                                            </div>
                                            <div class="ticket-item-meta">Unitario: R$ <?= number_format($itemUnitPrice, 2, ',', '.') ?></div>
                                            <?php if ($itemNotes !== ''): ?>
                                                <div class="ticket-item-meta">Obs.: <?= htmlspecialchars($itemNotes) ?></div>
                                            <?php endif; ?>
                                            <?php foreach ($itemAdditionals as $additional): ?>
                                                <div class="ticket-additional">
                                                    + <?= (int) ($additional['quantity'] ?? 0) ?>x <?= htmlspecialchars((string) ($additional['name'] ?? 'Adicional')) ?>
                                                    (R$ <?= number_format((float) ($additional['line_subtotal'] ?? 0), 2, ',', '.') ?>)
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="ticket-divider"></div>

                    <div class="ticket-row"><span>Subtotal</span><span>R$ <?= number_format($subtotal, 2, ',', '.') ?></span></div>
                    <div class="ticket-row"><span>Desconto</span><span>R$ <?= number_format($discount, 2, ',', '.') ?></span></div>
                    <div class="ticket-row"><span>Taxa entrega</span><span>R$ <?= number_format($deliveryFee, 2, ',', '.') ?></span></div>
                    <div class="ticket-row ticket-total"><span>Total</span><span>R$ <?= number_format($total, 2, ',', '.') ?></span></div>

                    <?php if ($notes !== ''): ?>
                        <div class="ticket-divider"></div>
                        <div><strong>Obs. do pedido</strong></div>
                        <div><?= htmlspecialchars($notes) ?></div>
                    <?php endif; ?>

                    <div class="ticket-divider"></div>
                    <div class="ticket-center ticket-muted">Gerado em <?= htmlspecialchars($generatedAt) ?></div>
                </section>
            </div>
        </section>
    </div>
</div>
