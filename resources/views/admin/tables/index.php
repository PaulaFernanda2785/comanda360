<div class="topbar">
    <div>
        <h1>Mesas</h1>
        <p>Listagem de mesas vinculadas à empresa autenticada.</p>
    </div>
    <a class="btn" href="<?= htmlspecialchars(base_url('/admin/tables/create')) ?>">Nova mesa</a>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Número</th>
                <th>Nome</th>
                <th>Capacidade</th>
                <th>Status</th>
                <th>QR Token</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($tables)): ?>
            <tr><td colspan="6">Nenhuma mesa encontrada.</td></tr>
        <?php else: ?>
            <?php foreach ($tables as $table): ?>
                <tr>
                    <td><?= (int)$table['id'] ?></td>
                    <td><?= (int)$table['number'] ?></td>
                    <td><?= htmlspecialchars($table['name']) ?></td>
                    <td><?= $table['capacity'] !== null ? (int)$table['capacity'] : '-' ?></td>
                    <td><span class="badge"><?= htmlspecialchars($table['status']) ?></span></td>
                    <td><?= htmlspecialchars($table['qr_code_token']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
