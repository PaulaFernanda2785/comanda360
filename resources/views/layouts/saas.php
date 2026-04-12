<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'SaaS') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body{font-family:Arial,sans-serif;margin:0;background:#f6f7fb;color:#111827}
        .shell{display:grid;grid-template-columns:280px 1fr;min-height:100vh}
        aside{background:#111827;color:#fff;padding:24px}
        aside h2{margin-top:0;font-size:20px}
        aside p{margin-top:-4px;color:#9ca3af;font-size:12px}
        aside a{display:block;color:#d1d5db;text-decoration:none;padding:10px 0}
        aside a:hover{color:#fff}
        main{padding:24px}
        .card{background:#fff;border-radius:12px;padding:24px;box-shadow:0 4px 16px rgba(0,0,0,.06)}
        .flash{padding:12px 14px;border-radius:8px;margin-bottom:16px}
        .flash.success{background:#dcfce7;color:#166534}
        .flash.error{background:#fee2e2;color:#991b1b}
        table{width:100%;border-collapse:collapse;margin-top:16px}
        th,td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}
        .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:16px}
        .grid{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:12px}
        .kpi{background:#fff;border-radius:10px;padding:14px;box-shadow:0 2px 8px rgba(0,0,0,.05)}
        .kpi strong{display:block;font-size:22px}
        .kpi span{color:#6b7280;font-size:12px}
        .badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#e5e7eb;color:#334155;font-size:12px}
        .badge.status-default{background:#e2e8f0;color:#334155}
        .badge.status-pending{background:#fef3c7;color:#92400e}
        .badge.status-paid{background:#bbf7d0;color:#14532d}
        .badge.status-canceled{background:#fee2e2;color:#991b1b}
        .badge.status-overdue{background:#fecaca;color:#7f1d1d}
        .badge.status-trial{background:#e0f2fe;color:#075985}
        .badge.status-active{background:#dcfce7;color:#166534}
        .badge.status-suspended{background:#f3e8ff;color:#6b21a8}
        .badge.status-inactive{background:#f3f4f6;color:#4b5563}
        .muted{color:#6b7280}
        .btn{display:inline-block;padding:10px 14px;background:#1d4ed8;color:#fff;text-decoration:none;border-radius:8px;border:0;cursor:pointer}
        .btn.secondary{background:#475569}
        input, select, textarea{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box}
        label{display:block;font-weight:bold;margin-bottom:6px}
        .field{margin-bottom:14px}
        .grid.two{grid-template-columns:1fr 1fr}
    </style>
</head>
<body>
<div class="shell">
    <aside>
        <h2>Comanda360 SaaS</h2>
        <p><?= htmlspecialchars((string)($user['name'] ?? '')) ?></p>
        <a href="<?= htmlspecialchars(base_url('/saas/dashboard')) ?>">Dashboard</a>
        <a href="<?= htmlspecialchars(base_url('/saas/companies')) ?>">Empresas</a>
        <a href="<?= htmlspecialchars(base_url('/saas/plans')) ?>">Planos</a>
        <a href="<?= htmlspecialchars(base_url('/saas/subscriptions')) ?>">Assinaturas</a>
        <a href="<?= htmlspecialchars(base_url('/saas/subscription-payments')) ?>">Cobrancas</a>
        <a href="<?= htmlspecialchars(base_url('/logout')) ?>">Sair</a>
    </aside>
    <main>
        <?php if (!empty($flashSuccess)): ?>
            <div class="flash success"><?= htmlspecialchars($flashSuccess) ?></div>
        <?php endif; ?>

        <?php if (!empty($flashError)): ?>
            <div class="flash error"><?= htmlspecialchars($flashError) ?></div>
        <?php endif; ?>

        <?= $content ?>
    </main>
</div>
</body>
</html>
