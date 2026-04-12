<div class="topbar">
    <div>
        <h1>Comandas Abertas</h1>
        <p>Listagem de comandas abertas da empresa autenticada.</p>
    </div>
    <a class="btn" href="<?= htmlspecialchars(base_url('/admin/commands/create')) ?>">Abrir comanda</a>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Mesa</th>
                <th>Cliente</th>
                <th>Aberta por</th>
                <th>Status</th>
                <th>Abertura</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($commands)): ?>
            <tr><td colspan="6">Nenhuma comanda aberta.</td></tr>
        <?php else: ?>
            <?php foreach ($commands as $command): ?>
                <tr>
                    <td><?= (int)$command['id'] ?></td>
                    <td><?= $command['table_number'] !== null ? 'Mesa ' . (int)$command['table_number'] : '-' ?></td>
                    <td><?= htmlspecialchars($command['customer_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($command['opened_by_user_name'] ?? '-') ?></td>
                    <td><span class="badge"><?= htmlspecialchars($command['status']) ?></span></td>
                    <td><?= htmlspecialchars((string)$command['opened_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
