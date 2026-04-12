<?php
$companies = $summary['companies'] ?? [];
$plans = $summary['plans'] ?? [];
$subscriptions = $summary['subscriptions'] ?? [];
?>

<div class="topbar">
    <div>
        <h1>Dashboard SaaS</h1>
        <p class="muted">Visão institucional de empresas, planos e assinaturas.</p>
    </div>
</div>

<div class="grid">
    <div class="kpi">
        <strong><?= (int)($companies['total_companies'] ?? 0) ?></strong>
        <span>Empresas totais</span>
    </div>
    <div class="kpi">
        <strong><?= (int)($companies['active_companies'] ?? 0) ?></strong>
        <span>Empresas ativas</span>
    </div>
    <div class="kpi">
        <strong><?= (int)($subscriptions['active_subscriptions'] ?? 0) ?></strong>
        <span>Assinaturas ativas</span>
    </div>
    <div class="kpi">
        <strong>R$ <?= number_format((float)($subscriptions['active_monthly_mrr'] ?? 0), 2, ',', '.') ?></strong>
        <span>MRR ativo (mensal)</span>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <table>
        <thead>
            <tr>
                <th>Métrica</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Empresas em trial</td><td><?= (int)($companies['trial_companies'] ?? 0) ?></td></tr>
            <tr><td>Empresas inadimplentes</td><td><?= (int)($companies['delinquent_companies'] ?? 0) ?></td></tr>
            <tr><td>Empresas suspensas</td><td><?= (int)($companies['suspended_companies'] ?? 0) ?></td></tr>
            <tr><td>Planos totais</td><td><?= (int)($plans['total_plans'] ?? 0) ?></td></tr>
            <tr><td>Planos ativos</td><td><?= (int)($plans['active_plans'] ?? 0) ?></td></tr>
            <tr><td>Assinaturas totais</td><td><?= (int)($subscriptions['total_subscriptions'] ?? 0) ?></td></tr>
            <tr><td>Assinaturas trial</td><td><?= (int)($subscriptions['trial_subscriptions'] ?? 0) ?></td></tr>
            <tr><td>Assinaturas vencidas</td><td><?= (int)($subscriptions['expired_subscriptions'] ?? 0) ?></td></tr>
        </tbody>
    </table>
</div>
