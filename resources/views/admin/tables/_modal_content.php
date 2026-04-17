<?php
$tableCommands = is_array($tableCommands ?? null) ? $tableCommands : [];
$tableOrders = is_array($tableOrders ?? null) ? $tableOrders : [];
$tableOrdersCount = (int) ($tableOrdersCount ?? 0);
$tableItemsTotal = (int) ($tableItemsTotal ?? 0);
$tableAmountTotal = (float) ($tableAmountTotal ?? 0);
$canUpdateStatus = !empty($canUpdateStatus);
$canCancelOrder = !empty($canCancelOrder);
$canSendKitchen = !empty($canSendKitchen);
?>

<div class="modal-grid">
    <section class="modal-block">
        <h4>Comandas da mesa</h4>
        <p class="muted" style="margin:0 0 8px">Atualizado em <?= htmlspecialchars(date('d/m/Y H:i:s')) ?></p>
        <?php if (empty($tableCommands)): ?>
            <p class="muted">Nenhuma comanda aberta para esta mesa.</p>
        <?php else: ?>
            <div class="command-list">
                <?php foreach ($tableCommands as $command): ?>
                    <div class="command-item">
                        <strong>Comanda #<?= (int) ($command['id'] ?? 0) ?></strong>
                        <span class="badge <?= htmlspecialchars(status_badge_class('command_status', $command['status'] ?? null)) ?>" style="margin-left:6px">
                            <?= htmlspecialchars(status_label('command_status', $command['status'] ?? null)) ?>
                        </span>
                        <p>
                            Cliente: <?= htmlspecialchars((string) ($command['customer_name'] ?? '-')) ?><br>
                            Aberta em: <?= htmlspecialchars((string) ($command['opened_at'] ?? '-')) ?><br>
                            Responsavel: <?= htmlspecialchars((string) ($command['opened_by_user_name'] ?? '-')) ?>
                        </p>
                        <?php if (!empty($command['notes'])): ?>
                            <p><strong>Obs.:</strong> <?= htmlspecialchars((string) $command['notes']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="modal-block">
        <h4>Pedidos e servicos</h4>
        <p class="muted" style="margin:0 0 8px">
            Pedidos ativos: <?= $tableOrdersCount ?>
            | Itens: <?= $tableItemsTotal ?>
            | Total: R$ <?= number_format($tableAmountTotal, 2, ',', '.') ?>
        </p>
        <?php if (empty($tableOrders)): ?>
            <p class="muted">Nenhum pedido ativo no momento para esta mesa.</p>
        <?php else: ?>
            <div class="orders-list">
                <?php foreach ($tableOrders as $order): ?>
                    <?php
                    $nextStatuses = $order['next_statuses'] ?? [];
                    if (!is_array($nextStatuses)) {
                        $nextStatuses = [];
                    }

                    if (empty($canCancelOrder)) {
                        $nextStatuses = array_values(array_filter(
                            $nextStatuses,
                            static fn (mixed $status): bool => (string) $status !== 'canceled'
                        ));
                    }
                    if (!empty($order['can_send_kitchen'])) {
                        $nextStatuses = array_values(array_filter(
                            $nextStatuses,
                            static fn (mixed $status): bool => (string) $status !== 'received'
                        ));
                    }

                    $canDirectCancel = $canCancelOrder && in_array('canceled', $nextStatuses, true);
                    if ($canDirectCancel) {
                        $nextStatuses = array_values(array_filter(
                            $nextStatuses,
                            static fn (mixed $status): bool => (string) $status !== 'canceled'
                        ));
                    }

                    $orderItems = $order['items'] ?? [];
                    if (!is_array($orderItems)) {
                        $orderItems = [];
                    }
                    ?>
                    <article class="order-item">
                        <div class="order-item-head">
                            <div>
                                <strong><?= htmlspecialchars((string) ($order['order_number'] ?? '-')) ?></strong>
                                <p>
                                    Cliente: <?= htmlspecialchars((string) ($order['customer_name'] ?? '-')) ?><br>
                                    Criado em: <?= htmlspecialchars((string) ($order['created_at'] ?? '-')) ?>
                                </p>
                            </div>
                            <div class="order-badges">
                                <span class="badge <?= htmlspecialchars(status_badge_class('order_status', $order['status'] ?? null)) ?>">
                                    Servico: <?= htmlspecialchars(status_label('order_status', $order['status'] ?? null)) ?>
                                </span>
                                <span class="badge <?= htmlspecialchars(status_badge_class('order_payment_status', $order['payment_status'] ?? null)) ?>">
                                    Pagamento: <?= htmlspecialchars(status_label('order_payment_status', $order['payment_status'] ?? null)) ?>
                                </span>
                                <?php if (!empty($order['is_paid_waiting_production'])): ?>
                                    <span class="badge <?= htmlspecialchars(status_badge_class('order_operational_flag', 'paid_waiting_production')) ?>">
                                        <?= htmlspecialchars(status_label('order_operational_flag', 'paid_waiting_production')) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <p class="muted" style="margin:4px 0 0">
                            Itens: <?= (int) ($order['items_count'] ?? 0) ?>
                            | Total: R$ <?= number_format((float) ($order['total_amount'] ?? 0), 2, ',', '.') ?>
                            <?php if (!empty($order['latest_status_changed_at'])): ?>
                                | Última mudança: <?= htmlspecialchars((string) $order['latest_status_changed_at']) ?>
                            <?php endif; ?>
                        </p>

                        <div class="order-products">
                            <div class="title">Produtos selecionados</div>
                            <?php if (empty($orderItems)): ?>
                                <span class="muted">Itens detalhados indisponiveis para este pedido.</span>
                            <?php else: ?>
                                <?php foreach ($orderItems as $item): ?>
                                    <?php
                                    $itemAdditionals = $item['additionals'] ?? [];
                                    if (!is_array($itemAdditionals)) {
                                        $itemAdditionals = [];
                                    }
                                    ?>
                                    <div class="order-product-item">
                                        <div class="order-product-head">
                                            <strong><?= (int) ($item['quantity'] ?? 0) ?>x <?= htmlspecialchars((string) ($item['name'] ?? 'Produto')) ?></strong>
                                            <span class="order-product-meta">R$ <?= number_format((float) ($item['line_subtotal'] ?? 0), 2, ',', '.') ?></span>
                                        </div>
                                        <div class="order-product-meta">
                                            Unitario: R$ <?= number_format((float) ($item['unit_price'] ?? 0), 2, ',', '.') ?>
                                        </div>
                                        <?php if (!empty($item['notes'])): ?>
                                            <div class="order-product-notes">Obs.: <?= htmlspecialchars((string) $item['notes']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($itemAdditionals)): ?>
                                            <div class="order-product-additionals">
                                                Adicionais:
                                                <?php
                                                $additionalParts = [];
                                                foreach ($itemAdditionals as $additional) {
                                                    $additionalParts[] =
                                                        (int) ($additional['quantity'] ?? 0) . 'x ' .
                                                        (string) ($additional['name'] ?? 'Adicional') .
                                                        ' (R$ ' . number_format((float) ($additional['line_subtotal'] ?? 0), 2, ',', '.') . ')';
                                                }
                                                ?>
                                                <?= htmlspecialchars(implode(' | ', $additionalParts)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="order-actions">
                            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/orders/print-ticket?order_id=' . (int) $order['id'])) ?>">
                                Imprimir ticket
                            </a>

                            <?php if ($canSendKitchen && !empty($order['can_send_kitchen'])): ?>
                                <form method="POST" action="<?= htmlspecialchars(base_url('/admin/orders/send-kitchen')) ?>" data-modal-async-action="1">
                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                    <button class="btn secondary" type="submit">Enviar para cozinha</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($canDirectCancel): ?>
                                <form method="POST" action="<?= htmlspecialchars(base_url('/admin/orders/status')) ?>" data-modal-async-action="1">
                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                    <input type="hidden" name="new_status" value="canceled">
                                    <input type="hidden" name="status_notes" value="Cancelado pelo painel de mesas.">
                                    <button class="btn secondary" type="submit" onclick="return confirm('Cancelar este pedido agora?');">Cancelar pedido</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($canUpdateStatus && !empty($nextStatuses)): ?>
                                <form method="POST" action="<?= htmlspecialchars(base_url('/admin/orders/status')) ?>" data-modal-async-action="1">
                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                    <div class="row">
                                        <select name="new_status" required>
                                            <option value="">Selecione</option>
                                            <?php foreach ($nextStatuses as $status): ?>
                                                <option value="<?= htmlspecialchars((string) $status) ?>">
                                                    <?= htmlspecialchars(status_label('order_status', $status)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="row">
                                        <input name="status_notes" type="text" placeholder="Observacao (opcional)">
                                        <button class="btn secondary" type="submit">Atualizar status</button>
                                    </div>
                                </form>
                            <?php elseif (!$canSendKitchen || empty($order['can_send_kitchen'])): ?>
                                <span class="muted">Sem acoes disponiveis para este pedido.</span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
