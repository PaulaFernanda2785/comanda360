<div class="topbar">
    <div>
        <h1>Assinaturas</h1>
        <p class="muted">Histórico e estado atual das assinaturas por empresa.</p>
    </div>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Empresa</th>
                <th>Plano</th>
                <th>Ciclo</th>
                <th>Valor</th>
                <th>Status</th>
                <th>Início</th>
                <th>Fim</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($subscriptions)): ?>
            <tr><td colspan="8">Nenhuma assinatura encontrada.</td></tr>
        <?php else: ?>
            <?php foreach ($subscriptions as $subscription): ?>
                <tr>
                    <td><?= (int)$subscription['id'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars((string)$subscription['company_name']) ?></strong><br>
                        <span class="muted"><?= htmlspecialchars((string)$subscription['company_slug']) ?></span>
                    </td>
                    <td><?= htmlspecialchars((string)$subscription['plan_name']) ?></td>
                    <td><?= htmlspecialchars((string)$subscription['billing_cycle']) ?></td>
                    <td>R$ <?= number_format((float)$subscription['amount'], 2, ',', '.') ?></td>
                    <td><span class="badge"><?= htmlspecialchars((string)$subscription['status']) ?></span></td>
                    <td><?= htmlspecialchars((string)$subscription['starts_at']) ?></td>
                    <td><?= htmlspecialchars((string)($subscription['ends_at'] ?? '-')) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
