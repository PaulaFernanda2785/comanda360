<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'Sistema') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body{font-family:Arial,sans-serif;margin:0;background:#f8fafc;color:#111827}
        .shell{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
        aside{background:#0f172a;color:#fff;padding:24px}
        aside h2{margin-top:0;font-size:20px}
        aside a{display:block;color:#cbd5e1;text-decoration:none;padding:10px 0}
        aside a:hover{color:#fff}
        main{padding:24px}
        .card{background:#fff;border-radius:12px;padding:24px;box-shadow:0 4px 16px rgba(0,0,0,.06)}
        .flash{padding:12px 14px;border-radius:8px;margin-bottom:16px}
        .flash.success{background:#dcfce7;color:#166534}
        .flash.error{background:#fee2e2;color:#991b1b}
        table{width:100%;border-collapse:collapse;margin-top:16px}
        th,td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left}
        .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:16px}
        .btn{display:inline-block;padding:10px 14px;background:#1d4ed8;color:#fff;text-decoration:none;border-radius:8px;border:0;cursor:pointer}
        .btn.secondary{background:#475569}
        .grid{display:grid;gap:16px}
        .grid.two{grid-template-columns:1fr 1fr}
        input, select, textarea{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box}
        label{display:block;font-weight:bold;margin-bottom:6px}
        .field{margin-bottom:14px}
        .badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#e2e8f0;font-size:12px}
    </style>
</head>
<body>
<div class="shell">
    <aside>
        <h2>Comanda360</h2>
        <a href="<?= htmlspecialchars(base_url('/admin/dashboard')) ?>">Dashboard</a>
        <a href="<?= htmlspecialchars(base_url('/admin/products')) ?>">Produtos</a>
        <a href="<?= htmlspecialchars(base_url('/admin/tables')) ?>">Mesas</a>
        <a href="<?= htmlspecialchars(base_url('/admin/commands')) ?>">Comandas</a>
        <a href="<?= htmlspecialchars(base_url('/admin/orders')) ?>">Pedidos</a>
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
