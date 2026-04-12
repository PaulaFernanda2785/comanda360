<?php
$queue = $queue ?? ['received' => [], 'preparing' => [], 'ready' => [], 'all' => []];
$receivedOrders = $queue['received'] ?? [];
$preparingOrders = $queue['preparing'] ?? [];
$readyOrders = $queue['ready'] ?? [];
?>

<div class="topbar">
    <div>
        <h1>Painel de Cozinha</h1>
        <p>Fila operacional de pedidos por status de producao.</p>
    </div>
    <a class="btn secondary" href="<?= htmlspecialchars(base_url('/admin/orders')) ?>">Ver pedidos</a>
</div>

<div class="grid two" style="margin-bottom:16px">
    <div class="card">
        <strong><?= count($receivedOrders) ?></strong>
        <p style="margin:6px 0 0">Recebidos</p>
    </div>
    <div class="card">
        <strong><?= count($preparingOrders) ?></strong>
        <p style="margin:6px 0 0">Em preparo</p>
    </div>
    <div class="card">
        <strong><?= count($readyOrders) ?></strong>
        <p style="margin:6px 0 0">Prontos</p>
    </div>
    <div class="card">
        <strong><?= count($queue['all'] ?? []) ?></strong>
        <p style="margin:6px 0 0">Total na fila</p>
    </div>
</div>

<?php
$sections = [
    'Pedidos Recebidos' => $receivedOrders,
    'Pedidos Em Preparo' => $preparingOrders,
    'Pedidos Prontos' => $readyOrders,
];
?>

<?php foreach ($sections as $sectionTitle => $orders): ?>
    <div class="card" style="margin-bottom:16px">
        <h3><?= htmlspecialchars($sectionTitle) ?></h3>
        <table>
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Mesa/Comanda</th>
                    <th>Cliente</th>
                    <th>Itens</th>
                    <th>Status</th>
                    <th>Ultima emissao</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="7">Nenhum pedido nesta fila.</td></tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars((string) $order['order_number']) ?></strong><br>
                            <small><?= htmlspecialchars((string) $order['created_at']) ?></small>
                        </td>
                        <td>
                            <?= $order['table_number'] !== null ? 'Mesa ' . (int) $order['table_number'] : '-' ?><br>
                            <?= $order['command_id'] !== null ? 'Comanda #' . (int) $order['command_id'] : '-' ?>
                        </td>
                        <td><?= htmlspecialchars((string) ($order['customer_name'] ?? '-')) ?></td>
                        <td><?= (int) ($order['items_count'] ?? 0) ?></td>
                        <td>
                            <span class="badge <?= htmlspecialchars(status_badge_class('order_status', $order['status'] ?? null)) ?>">
                                <?= htmlspecialchars(status_label('order_status', $order['status'] ?? null)) ?>
                            </span>
                        </td>
                        <td>
                            <?= htmlspecialchars((string) ($order['last_printed_at'] ?? '-')) ?>
                            <?php if (!empty($order['last_printed_by'])): ?>
                                <br><small>por <?= htmlspecialchars((string) $order['last_printed_by']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (($order['status'] ?? '') === 'received'): ?>
                                <form method="POST" action="<?= htmlspecialchars(base_url('/admin/kitchen/status')) ?>" style="margin-bottom:6px">
                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                    <input type="hidden" name="new_status" value="preparing">
                                    <button class="btn secondary" type="submit">Iniciar preparo</button>
                                </form>
                            <?php elseif (($order['status'] ?? '') === 'preparing'): ?>
                                <form method="POST" action="<?= htmlspecialchars(base_url('/admin/kitchen/status')) ?>" style="margin-bottom:6px">
                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                    <input type="hidden" name="new_status" value="ready">
                                    <button class="btn secondary" type="submit">Marcar pronto</button>
                                </form>
                            <?php endif; ?>

                            <form method="POST" action="<?= htmlspecialchars(base_url('/admin/kitchen/emit-ticket')) ?>">
                                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                <button class="btn secondary" type="submit">Emitir ticket</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endforeach; ?>

<div class="card">
    <h3>Ultimos tickets de cozinha</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Pedido</th>
                <th>Emitido em</th>
                <th>Emitido por</th>
                <th>Status</th>
                <th>Observacao</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($recentPrints)): ?>
            <tr><td colspan="6">Nenhum ticket emitido.</td></tr>
        <?php else: ?>
            <?php foreach ($recentPrints as $print): ?>
                <tr>
                    <td><?= (int) $print['id'] ?></td>
                    <td><?= htmlspecialchars((string) ($print['order_number'] ?? ('#' . $print['order_id']))) ?></td>
                    <td><?= htmlspecialchars((string) $print['printed_at']) ?></td>
                    <td><?= htmlspecialchars((string) ($print['printed_by_user_name'] ?? '-')) ?></td>
                    <td>
                        <span class="badge <?= htmlspecialchars(status_badge_class('print_log_status', $print['status'] ?? null)) ?>">
                            <?= htmlspecialchars(status_label('print_log_status', $print['status'] ?? null)) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars((string) ($print['notes'] ?? '-')) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
