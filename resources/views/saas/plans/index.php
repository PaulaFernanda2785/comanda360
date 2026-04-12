<div class="topbar">
    <div>
        <h1>Planos</h1>
        <p class="muted">Tabela de planos comerciais do Comanda360.</p>
    </div>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Plano</th>
                <th>Preço mensal</th>
                <th>Preço anual</th>
                <th>Limites</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($plans)): ?>
            <tr><td colspan="6">Nenhum plano encontrado.</td></tr>
        <?php else: ?>
            <?php foreach ($plans as $plan): ?>
                <tr>
                    <td><?= (int)$plan['id'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars((string)$plan['name']) ?></strong><br>
                        <span class="muted"><?= htmlspecialchars((string)$plan['slug']) ?></span>
                    </td>
                    <td>R$ <?= number_format((float)$plan['price_monthly'], 2, ',', '.') ?></td>
                    <td>
                        <?= $plan['price_yearly'] !== null
                            ? 'R$ ' . number_format((float)$plan['price_yearly'], 2, ',', '.')
                            : '-' ?>
                    </td>
                    <td>
                        Usuários: <?= $plan['max_users'] !== null ? (int)$plan['max_users'] : 'Ilimitado' ?><br>
                        Produtos: <?= $plan['max_products'] !== null ? (int)$plan['max_products'] : 'Ilimitado' ?><br>
                        Mesas: <?= $plan['max_tables'] !== null ? (int)$plan['max_tables'] : 'Ilimitado' ?>
                    </td>
                    <td><span class="badge"><?= htmlspecialchars((string)$plan['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
