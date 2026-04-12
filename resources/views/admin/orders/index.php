<div class="topbar">
    <div>
        <h1>Pedidos</h1>
        <p>Listagem de pedidos vinculados a empresa autenticada.</p>
    </div>
    <a class="btn" href="<?= htmlspecialchars(base_url('/admin/orders/create')) ?>">Novo pedido</a>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Numero</th>
                <th>Comanda</th>
                <th>Mesa</th>
                <th>Itens</th>
                <th>Status</th>
                <th>Pagamento</th>
                <th>Total</th>
                <th>Ultima mudanca</th>
                <th>Acoes</th>
                <th>Criado em</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($orders)): ?>
            <tr><td colspan="11">Nenhum pedido encontrado.</td></tr>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= (int) $order['id'] ?></td>
                    <td><?= htmlspecialchars((string) $order['order_number']) ?></td>
                    <td><?= $order['command_id'] !== null ? '#' . (int) $order['command_id'] : '-' ?></td>
                    <td><?= $order['table_number'] !== null ? 'Mesa ' . (int) $order['table_number'] : '-' ?></td>
                    <td><?= (int) $order['items_count'] ?></td>
                    <td>
                        <span class="badge <?= htmlspecialchars(status_badge_class('order_status', $order['status'] ?? null)) ?>">
                            <?= htmlspecialchars(status_label('order_status', $order['status'] ?? null)) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= htmlspecialchars(status_badge_class('order_payment_status', $order['payment_status'] ?? null)) ?>">
                            <?= htmlspecialchars(status_label('order_payment_status', $order['payment_status'] ?? null)) ?>
                        </span>
                    </td>
                    <td>R$ <?= number_format((float) $order['total_amount'], 2, ',', '.') ?></td>
                    <td>
                        <?= htmlspecialchars((string) ($order['latest_status_changed_at'] ?? '-')) ?>
                        <?php if (!empty($order['latest_status_changed_by'])): ?>
                            <br><small>por <?= htmlspecialchars((string) $order['latest_status_changed_by']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($canSendKitchen) && !empty($order['can_send_kitchen'])): ?>
                            <form method="POST" action="<?= htmlspecialchars(base_url('/admin/orders/send-kitchen')) ?>" style="margin-bottom:6px">
                                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                <button class="btn secondary" type="submit">Enviar para cozinha</button>
                            </form>
                        <?php endif; ?>

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
                        ?>

                        <?php if (!empty($canUpdateStatus) && !empty($nextStatuses)): ?>
                            <form method="POST" action="<?= htmlspecialchars(base_url('/admin/orders/status')) ?>">
                                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                <select name="new_status" required>
                                    <option value="">Selecione</option>
                                    <?php foreach ($nextStatuses as $status): ?>
                                        <option value="<?= htmlspecialchars((string) $status) ?>">
                                            <?= htmlspecialchars(status_label('order_status', $status)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input name="status_notes" type="text" placeholder="Observacao (opcional)" style="margin-top:6px">
                                <button class="btn secondary" type="submit" style="margin-top:6px">Atualizar status</button>
                            </form>
                        <?php elseif (empty($canSendKitchen) || empty($order['can_send_kitchen'])): ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars((string) $order['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
