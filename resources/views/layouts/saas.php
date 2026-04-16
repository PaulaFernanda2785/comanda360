<?php
$user = is_array($user ?? null) ? $user : [];
$currentPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');

$rawNavItems = is_array($navItems ?? null)
    ? $navItems
    : [
        ['href' => '/saas/dashboard', 'label' => 'Dashboard', 'description' => 'Indicadores da plataforma'],
        ['href' => '/saas/companies', 'label' => 'Empresas', 'description' => 'Clientes da plataforma'],
        ['href' => '/saas/plans', 'label' => 'Planos', 'description' => 'Catalogo comercial'],
        ['href' => '/saas/subscriptions', 'label' => 'Assinaturas', 'description' => 'Ciclo de contratos'],
        ['href' => '/saas/subscription-payments', 'label' => 'Cobrancas', 'description' => 'Recebimentos SaaS'],
    ];

$normalizedNavItems = [];
foreach ($rawNavItems as $item) {
    if (!is_array($item)) {
        continue;
    }

    $path = trim((string) ($item['href'] ?? $item[0] ?? ''));
    $label = trim((string) ($item['label'] ?? $item[1] ?? ''));
    if ($path === '' || $label === '') {
        continue;
    }

    $matchRoutes = $item['match'] ?? [$path];
    if (!is_array($matchRoutes)) {
        $matchRoutes = [$matchRoutes];
    }

    $routes = [];
    foreach ($matchRoutes as $route) {
        $value = trim((string) $route);
        if ($value !== '') {
            $routes[] = $value;
        }
    }
    if ($routes === []) {
        $routes[] = $path;
    }

    $normalizedNavItems[] = [
        'href' => $path,
        'label' => $label,
        'description' => trim((string) ($item['description'] ?? '')),
        'match' => array_values(array_unique($routes)),
    ];
}

$routeMatches = static function (string $path, array $routes): bool {
    foreach ($routes as $candidate) {
        $route = trim((string) $candidate);
        if ($route === '') {
            continue;
        }

        if ($path === $route || str_starts_with($path, $route . '/')) {
            return true;
        }
    }

    return false;
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'SaaS') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root{
            --theme-primary:#1d4ed8;
            --theme-accent:#0891b2;
            --theme-dark:#0f172a;
            --line:#dbe2ea;
            --surface:#ffffff;
        }

        *{box-sizing:border-box}
        body{
            font-family:"Segoe UI Variable","Trebuchet MS","Tahoma",sans-serif;
            margin:0;
            background:radial-gradient(circle at 0% 0%, #e0f2fe 0%, #edf3ff 40%, #e2e8f0 100%);
            color:#111827
        }

        .shell{display:grid;grid-template-columns:304px minmax(0,1fr);min-height:100vh}
        .sidebar{
            background:linear-gradient(200deg, #0f172a 0%, #020617 100%);
            color:#fff;
            padding:20px 16px;
            border-right:1px solid rgba(148,163,184,.22);
            display:grid;
            align-content:start;
            gap:14px;
        }
        .sidebar h2{margin:0;font-size:23px;line-height:1.1}
        .sidebar p{margin:0;color:#94a3b8;font-size:12px}
        .sidebar-label{margin:8px 0 0;font-size:11px;text-transform:uppercase;letter-spacing:.14em;color:#64748b;font-weight:700}

        .nav-links{display:grid;gap:9px}
        .nav-link,
        .logout-button{
            width:100%;
            display:flex;
            align-items:center;
            gap:10px;
            color:#cbd5e1;
            text-decoration:none;
            border-radius:14px;
            border:1px solid rgba(148,163,184,.18);
            padding:10px;
            background:linear-gradient(132deg, rgba(15,23,42,.64) 0%, rgba(15,23,42,.25) 100%);
            transition:transform .18s ease, border-color .18s ease, background .18s ease, color .18s ease;
            cursor:pointer;
        }
        .nav-link:hover,
        .logout-button:hover{
            color:#f8fafc;
            border-color:rgba(125,211,252,.48);
            transform:translateY(-1px);
            background:linear-gradient(132deg, rgba(30,41,59,.72) 0%, rgba(15,23,42,.36) 100%);
        }
        .nav-link.active{
            color:#f8fafc;
            border-color:rgba(56,189,248,.72);
            background:linear-gradient(125deg, rgba(56,189,248,.24) 0%, rgba(15,23,42,.65) 100%);
            box-shadow:0 12px 26px rgba(8,47,73,.24);
        }
        .nav-link-badge{
            width:34px;
            height:34px;
            border-radius:10px;
            display:grid;
            place-items:center;
            flex-shrink:0;
            font-size:11px;
            font-weight:700;
            letter-spacing:.08em;
            text-transform:uppercase;
            color:#f8fafc;
            background:linear-gradient(145deg, var(--theme-primary) 0%, var(--theme-accent) 100%);
            box-shadow:0 8px 18px rgba(15,23,42,.36);
        }
        .nav-link-copy{display:grid;min-width:0;gap:2px;flex:1}
        .nav-link-copy strong{font-size:14px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .nav-link-copy small{font-size:11px;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .nav-link-arrow{font-size:15px;color:#64748b;flex-shrink:0}
        .nav-link.active .nav-link-arrow{color:#bae6fd}
        .nav-empty{border:1px dashed rgba(148,163,184,.3);border-radius:12px;padding:12px;font-size:12px;color:#94a3b8;background:rgba(15,23,42,.35)}

        .logout-form{margin-top:4px}
        .logout-button{
            border-color:rgba(239,68,68,.35);
            color:#fecaca;
            background:linear-gradient(135deg, rgba(127,29,29,.4) 0%, rgba(69,10,10,.34) 100%);
        }
        .logout-button .nav-link-badge{background:linear-gradient(145deg, #ef4444 0%, #b91c1c 100%)}
        .logout-button .nav-link-copy small{color:#fca5a5}
        .logout-button .nav-link-arrow{color:#fecaca}

        main{padding:24px}
        .card{background:var(--surface);border-radius:12px;padding:24px;box-shadow:0 4px 16px rgba(0,0,0,.06)}
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
        .btn.is-loading{opacity:.92;pointer-events:none}
        .btn-spinner{display:inline-block;width:12px;height:12px;border:2px solid rgba(255,255,255,.65);border-top-color:#fff;border-radius:50%;animation:btnspin .7s linear infinite;vertical-align:-2px;margin-right:6px}
        @keyframes btnspin{to{transform:rotate(360deg)}}
        input, select, textarea{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box}
        label{display:block;font-weight:bold;margin-bottom:6px}
        .field{margin-bottom:14px}
        .grid.two{grid-template-columns:1fr 1fr}

        @media (max-width:980px){
            .shell{grid-template-columns:1fr}
            .sidebar{border-right:0;border-bottom:1px solid rgba(148,163,184,.2)}
            .nav-links{grid-template-columns:repeat(auto-fit,minmax(168px,1fr));gap:8px}
            .nav-link-copy small{display:none}
            main{padding:16px}
            .grid{grid-template-columns:1fr 1fr}
        }
    </style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <h2>Comanda360 SaaS</h2>
        <p><?= htmlspecialchars((string) ($user['name'] ?? 'Usuario')) ?></p>

        <p class="sidebar-label">Navegacao</p>
        <nav class="nav-links">
            <?php foreach ($normalizedNavItems as $item): ?>
                <?php
                $path = (string) ($item['href'] ?? '#');
                $label = (string) ($item['label'] ?? 'Menu');
                $description = trim((string) ($item['description'] ?? ''));
                $matches = is_array($item['match'] ?? null) ? $item['match'] : [$path];
                $isActive = $routeMatches($currentPath, $matches);
                $badge = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $label), 0, 2));
                if ($badge === '') {
                    $badge = 'MN';
                }
                ?>
                <a href="<?= htmlspecialchars(base_url($path)) ?>" class="nav-link<?= $isActive ? ' active' : '' ?>">
                    <span class="nav-link-badge"><?= htmlspecialchars($badge) ?></span>
                    <span class="nav-link-copy">
                        <strong><?= htmlspecialchars($label) ?></strong>
                        <?php if ($description !== ''): ?>
                            <small><?= htmlspecialchars($description) ?></small>
                        <?php endif; ?>
                    </span>
                    <span class="nav-link-arrow">&gt;</span>
                </a>
            <?php endforeach; ?>

            <?php if ($normalizedNavItems === []): ?>
                <div class="nav-empty">Nenhum modulo disponivel para o perfil logado.</div>
            <?php endif; ?>
        </nav>

        <form method="POST" action="<?= htmlspecialchars(base_url('/logout')) ?>" class="logout-form">
            <?= form_security_fields('auth.logout') ?>
            <button type="submit" class="logout-button">
                <span class="nav-link-badge">OUT</span>
                <span class="nav-link-copy">
                    <strong>Sair</strong>
                    <small>Encerrar sessao atual</small>
                </span>
                <span class="nav-link-arrow">&gt;</span>
            </button>
        </form>
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
    const submitControls = (form) => Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'));

    const loading = (control) => {
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
        if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post') {
            return;
        }
        if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }

        form.dataset.submitting = '1';
        const controls = submitControls(form);
        controls.forEach((control) => control.disabled = true);

        const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
        const preferred = submitter && (submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement)
            ? submitter
            : (controls[0] ?? null);
        if (preferred) {
            loading(preferred);
        }
    });
})();
</script>
</body>
</html>
