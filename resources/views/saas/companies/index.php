<div class="topbar">
    <div>
        <h1>Empresas</h1>
        <p class="muted">Empresas assinantes cadastradas na plataforma.</p>
    </div>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Empresa</th>
                <th>Contato</th>
                <th>Plano</th>
                <th>Status</th>
                <th>Assinatura</th>
                <th>Fim da assinatura</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($companies)): ?>
            <tr><td colspan="7">Nenhuma empresa encontrada.</td></tr>
        <?php else: ?>
            <?php foreach ($companies as $company): ?>
                <tr>
                    <td><?= (int)$company['id'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars((string)$company['name']) ?></strong><br>
                        <span class="muted"><?= htmlspecialchars((string)$company['slug']) ?></span>
                    </td>
                    <td>
                        <?= htmlspecialchars((string)($company['email'] ?? '-')) ?><br>
                        <span class="muted"><?= htmlspecialchars((string)($company['phone'] ?? '-')) ?></span>
                    </td>
                    <td><?= htmlspecialchars((string)($company['plan_name'] ?? 'Sem plano')) ?></td>
                    <td><span class="badge"><?= htmlspecialchars((string)$company['status']) ?></span></td>
                    <td><span class="badge"><?= htmlspecialchars((string)$company['subscription_status']) ?></span></td>
                    <td><?= htmlspecialchars((string)($company['subscription_ends_at'] ?? $company['trial_ends_at'] ?? '-')) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
