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
                <th>Criado em</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($orders)): ?>
            <tr><td colspan="9">Nenhum pedido encontrado.</td></tr>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= (int) $order['id'] ?></td>
                    <td><?= htmlspecialchars((string) $order['order_number']) ?></td>
                    <td><?= $order['command_id'] !== null ? '#' . (int) $order['command_id'] : '-' ?></td>
                    <td><?= $order['table_number'] !== null ? 'Mesa ' . (int) $order['table_number'] : '-' ?></td>
                    <td><?= (int) $order['items_count'] ?></td>
                    <td><span class="badge"><?= htmlspecialchars((string) $order['status']) ?></span></td>
                    <td><span class="badge"><?= htmlspecialchars((string) $order['payment_status']) ?></span></td>
                    <td>R$ <?= number_format((float) $order['total_amount'], 2, ',', '.') ?></td>
                    <td><?= htmlspecialchars((string) $order['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
