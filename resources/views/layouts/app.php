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
        .badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#e2e8f0;color:#334155;font-size:12px}
        .badge.status-default{background:#e2e8f0;color:#334155}
        .badge.status-pending{background:#fef3c7;color:#92400e}
        .badge.status-received{background:#dbeafe;color:#1e40af}
        .badge.status-preparing{background:#e0e7ff;color:#4338ca}
        .badge.status-ready{background:#dcfce7;color:#166534}
        .badge.status-delivered{background:#cffafe;color:#155e75}
        .badge.status-finished{background:#bbf7d0;color:#14532d}
        .badge.status-paid{background:#bbf7d0;color:#14532d}
        .badge.status-paid-waiting-production{background:#fed7aa;color:#9a3412}
        .badge.status-partial{background:#fde68a;color:#78350f}
        .badge.status-canceled{background:#fee2e2;color:#991b1b}
        .badge.status-failed{background:#fecaca;color:#7f1d1d}
        .badge.status-refunded{background:#fce7f3;color:#9d174d}
        .badge.status-open{background:#d1fae5;color:#065f46}
        .badge.status-closed{background:#e5e7eb;color:#374151}
        .badge.status-free{background:#d1fae5;color:#065f46}
        .badge.status-busy{background:#fee2e2;color:#991b1b}
        .badge.status-waiting{background:#fef3c7;color:#92400e}
        .badge.status-blocked{background:#d1d5db;color:#111827}
        .badge.status-overdue{background:#fecaca;color:#7f1d1d}
        .badge.status-trial{background:#e0f2fe;color:#075985}
        .badge.status-active{background:#dcfce7;color:#166534}
        .badge.status-suspended{background:#f3e8ff;color:#6b21a8}
        .badge.status-inactive{background:#f3f4f6;color:#4b5563}
        .badge.status-success{background:#dcfce7;color:#166534}
        .btn.is-loading{opacity:.92;pointer-events:none}
        .btn-spinner{display:inline-block;width:12px;height:12px;border:2px solid rgba(255,255,255,.65);border-top-color:#fff;border-radius:50%;animation:btnspin .7s linear infinite;vertical-align:-2px;margin-right:6px}
        @keyframes btnspin{to{transform:rotate(360deg)}}
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
        <a href="<?= htmlspecialchars(base_url('/admin/kitchen')) ?>">Cozinha</a>
        <a href="<?= htmlspecialchars(base_url('/admin/payments')) ?>">Pagamentos</a>
        <a href="<?= htmlspecialchars(base_url('/admin/cash-registers')) ?>">Caixa</a>
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
<script>
(() => {
    const findSubmitControls = (form) => Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'));

    const setLoadingState = (control) => {
        if (!control || control.dataset.processing === '1') {
            return;
        }

        control.dataset.processing = '1';
        control.disabled = true;
        control.classList.add('is-loading');

        if (control instanceof HTMLButtonElement) {
            control.dataset.originalHtml = control.innerHTML;
            control.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span>Processando...';
        } else if (control instanceof HTMLInputElement) {
            control.dataset.originalValue = control.value;
            control.value = 'Processando...';
        }
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (form.method.toLowerCase() !== 'post') {
            return;
        }

        if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }

        form.dataset.submitting = '1';

        const controls = findSubmitControls(form);
        controls.forEach((control) => {
            control.disabled = true;
        });

        const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
        const preferred = submitter && (submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement)
            ? submitter
            : (controls[0] ?? null);
        if (preferred) {
            setLoadingState(preferred);
        }
    });
})();
</script>
</body>
</html>
