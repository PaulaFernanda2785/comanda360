<?php
$appShellTheme = is_array($appShellTheme ?? null) ? $appShellTheme : [];
$user = is_array($user ?? null) ? $user : [];

$themePrimary = (string) ($appShellTheme['primary_color'] ?? '#1d4ed8');
$themeSecondary = (string) ($appShellTheme['secondary_color'] ?? '#0f172a');
$themeAccent = (string) ($appShellTheme['accent_color'] ?? '#0ea5e9');

$companyName = trim((string) ($appShellTheme['company_name'] ?? 'Estabelecimento'));
$brandTitle = trim((string) ($appShellTheme['title'] ?? 'Painel da Empresa'));
$brandDescription = trim((string) ($appShellTheme['description'] ?? ''));
$footerText = trim((string) ($appShellTheme['footer_text'] ?? 'Comanda360 - Sistema de gestão de atendimento e vendas.'));

$logoPath = trim((string) ($appShellTheme['logo_path'] ?? ''));
$bannerPath = trim((string) ($appShellTheme['banner_path'] ?? ''));
$logoUrl = $logoPath !== '' ? company_image_url($logoPath) : '';
$bannerUrl = $bannerPath !== '' ? company_image_url($bannerPath) : '';

$userName = trim((string) ($user['name'] ?? 'Usuário'));
$userRole = trim((string) ($user['role_name'] ?? 'Perfil'));
$currentPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
$idleTimeoutSeconds = session_idle_timeout_seconds();

$rawNavItems = is_array($navItems ?? null)
    ? $navItems
    : [
        ['href' => '/admin/dashboard', 'label' => 'Dashboard', 'description' => 'Visão geral da operação', 'match' => ['/admin/dashboard', '/admin/dashboard/report']],
        ['href' => '/admin/products', 'label' => 'Produtos', 'description' => 'Cardápio e categorias'],
        ['href' => '/admin/tables', 'label' => 'Mesas', 'description' => 'Gestão de salão'],
        ['href' => '/admin/orders', 'label' => 'Pedidos', 'description' => 'Fila de atendimento'],
        ['href' => '/account/password', 'label' => 'Alterar senha', 'description' => 'Conta e segurança'],
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
    <title><?= htmlspecialchars($title ?? 'Sistema') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root{
            --theme-primary:<?= htmlspecialchars($themePrimary) ?>;
            --theme-secondary:<?= htmlspecialchars($themeSecondary) ?>;
            --theme-accent:<?= htmlspecialchars($themeAccent) ?>;
            --surface:#ffffff;
            --surface-soft:#f8fafc;
            --line:#e2e8f0;
            --text:#0f172a;
            --text-muted:#475569;
            --text-light:#e2e8f0;
            --danger:#ef4444;
        }

        *{box-sizing:border-box}
        html{max-width:100%;overflow-x:hidden}
        body{
            font-family:"Segoe UI Variable","Trebuchet MS","Tahoma",sans-serif;
            margin:0;
            background:radial-gradient(circle at 8% 4%, #eff6ff 0%, #e9eef7 45%, #e2e8f0 100%);
            color:var(--text);
            max-width:100%;
            overflow-x:hidden;
        }
        .shell{display:grid;grid-template-columns:304px minmax(0,1fr);min-height:100vh;width:100%;max-width:100%}

        .sidebar{
            background:linear-gradient(198deg, var(--theme-secondary) 0%, #020617 100%);
            color:#fff;
            padding:20px 16px;
            display:grid;
            align-content:start;
            gap:16px;
            border-right:1px solid rgba(148,163,184,.2);
        }
        .brand-stack{display:grid;gap:10px}
        .brand-logo-wrap{
            width:88px;
            height:88px;
            border-radius:20px;
            background:linear-gradient(160deg, rgba(255,255,255,.18) 0%, rgba(255,255,255,.06) 100%);
            display:grid;
            place-items:center;
            border:1px solid rgba(255,255,255,.24);
            overflow:hidden;
            box-shadow:0 16px 24px rgba(2,6,23,.28)
        }
        .brand-logo-wrap img{width:100%;height:100%;object-fit:cover}
        .brand-title{margin:0;font-size:13px;font-weight:600;color:#cbd5e1;letter-spacing:.04em;text-transform:uppercase}
        .brand-headline{margin:0;font-size:25px;line-height:1.05;letter-spacing:.01em}
        .brand-company{margin:0;color:#94a3b8;font-size:12px}

        .sidebar-group-title{
            margin:2px 0 0;
            font-size:11px;
            text-transform:uppercase;
            letter-spacing:.14em;
            color:#64748b;
            font-weight:700;
        }

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
            border-color:rgba(125,211,252,.7);
            background:linear-gradient(125deg, rgba(56,189,248,.24) 0%, rgba(15,23,42,.65) 100%);
            box-shadow:0 12px 26px rgba(8,47,73,.28);
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

        .nav-empty{
            border:1px dashed rgba(148,163,184,.3);
            border-radius:12px;
            padding:12px;
            font-size:12px;
            color:#94a3b8;
            background:rgba(15,23,42,.35);
        }

        .logout-form{margin-top:6px}
        .logout-button{
            border-color:rgba(239,68,68,.35);
            color:#fecaca;
            background:linear-gradient(135deg, rgba(127,29,29,.4) 0%, rgba(69,10,10,.34) 100%);
        }
        .logout-button .nav-link-badge{
            background:linear-gradient(145deg, #ef4444 0%, #b91c1c 100%);
            color:#fff;
        }
        .logout-button .nav-link-copy small{color:#fca5a5}
        .logout-button .nav-link-arrow{color:#fecaca}

        .shell-main{display:grid;grid-template-rows:auto 1fr auto;min-height:100vh;min-width:0}
        .shell-header{
            padding:14px 22px;
            border-bottom:1px solid var(--line);
            background:
                linear-gradient(110deg, rgba(15,23,42,.84) 0%, rgba(15,23,42,.56) 48%, rgba(15,23,42,.66) 100%),
                linear-gradient(122deg, var(--theme-accent) 0%, var(--theme-secondary) 100%);
            color:#fff;
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:14px;
            min-height:108px;
        }
        .shell-header > *{min-width:0}
        .shell-header.with-banner{
            background:
                linear-gradient(110deg, rgba(15,23,42,.84) 0%, rgba(15,23,42,.58) 48%, rgba(15,23,42,.7) 100%),
                url('<?= htmlspecialchars($bannerUrl) ?>') center/cover no-repeat;
        }
        .shell-header h1{margin:0;font-size:22px;line-height:1.2}
        .shell-header p{margin:6px 0 0;color:#dbeafe;font-size:13px;max-width:720px}

        .shell-user-chip{
            background:rgba(15,23,42,.42);
            border:1px solid rgba(148,163,184,.4);
            border-radius:12px;
            padding:10px 12px;
            text-align:right;
            min-width:220px;
            max-width:100%;
            backdrop-filter:blur(2px);
        }
        .shell-user-chip strong{display:block;font-size:14px}
        .shell-user-chip span{font-size:12px;color:#cbd5e1}

        main{padding:22px;overflow-y:auto;overflow-x:hidden;min-width:0}
        .shell-footer{
            padding:10px 22px;
            background:
                linear-gradient(120deg, rgba(15,23,42,.9) 0%, rgba(15,23,42,.75) 100%),
                linear-gradient(122deg, var(--theme-accent) 0%, var(--theme-secondary) 100%);
            color:#cbd5e1;
            font-size:12px;
            border-top:1px solid rgba(148,163,184,.25);
            overflow-wrap:anywhere;
        }

        .card{background:var(--surface);border-radius:12px;padding:24px;box-shadow:0 10px 28px rgba(15,23,42,.08);border:1px solid rgba(226,232,240,.9);min-width:0;max-width:100%}
        .flash{padding:12px 14px;border-radius:8px;margin-bottom:16px}
        .flash.success{background:#dcfce7;color:#166534}
        .flash.error{background:#fee2e2;color:#991b1b}
        table{width:100%;max-width:100%;border-collapse:collapse;margin-top:16px;table-layout:fixed}
        th,td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top;overflow-wrap:anywhere;word-break:break-word}
        .topbar{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;margin-bottom:16px;gap:16px}
        .topbar > *{min-width:0}

        .btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;background:var(--theme-primary);color:#fff;text-decoration:none;border-radius:8px;border:0;cursor:pointer;max-width:100%;text-align:center;white-space:normal;word-break:break-word}
        .btn.secondary{background:#475569}
        .grid{display:grid;gap:16px;min-width:0}
        .grid.two{grid-template-columns:1fr 1fr}
        input, select, textarea{width:100%;max-width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box;background:#fff}
        label{display:block;font-weight:bold;margin-bottom:6px}
        .field{margin-bottom:14px;min-width:0}
        img,svg,canvas,video,iframe{max-width:100%}

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

        @media (max-width:980px){
            .shell{grid-template-columns:1fr}
            .sidebar{padding:14px;border-right:0;border-bottom:1px solid rgba(148,163,184,.2)}
            .brand-stack{grid-template-columns:auto 1fr;align-items:center;gap:8px 12px}
            .brand-logo-wrap{width:70px;height:70px}
            .brand-title,.brand-headline,.brand-company{grid-column:2}
            .sidebar-group-title{display:none}
            .nav-links{grid-template-columns:repeat(auto-fit,minmax(168px,1fr));gap:8px}
            .nav-link-copy small{display:none}
            .logout-form{margin:0}
            .shell-header{flex-direction:column;align-items:flex-start;min-height:auto}
            .shell-user-chip{width:100%;text-align:left;min-width:0}
            main{padding:16px}
            .shell-footer{padding:10px 16px}
        }
        @media (max-width:640px){
            .grid.two{grid-template-columns:1fr}
            .card{padding:18px}
            .nav-link-copy strong{white-space:normal}
        }
    </style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand-stack">
            <div class="brand-logo-wrap">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo do estabelecimento">
                <?php endif; ?>
            </div>
            <p class="brand-title"><?= htmlspecialchars($brandTitle !== '' ? $brandTitle : $companyName) ?></p>
            <h2 class="brand-headline">Comanda360</h2>
            <p class="brand-company"><?= htmlspecialchars($companyName) ?></p>
        </div>

        <p class="sidebar-group-title">Navegacao</p>
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
            <input type="hidden" name="logout_reason" value="" data-logout-reason>
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

    <div class="shell-main">
        <header class="shell-header<?= $bannerUrl !== '' ? ' with-banner' : '' ?>">
            <div>
                <h1><?= htmlspecialchars($title ?? 'Painel administrativo') ?></h1>
                <?php if ($brandDescription !== ''): ?>
                    <p><?= htmlspecialchars($brandDescription) ?></p>
                <?php else: ?>
                    <p>Painel de operação, relatórios e configurações do seu estabelecimento.</p>
                <?php endif; ?>
            </div>
            <div class="shell-user-chip">
                <strong><?= htmlspecialchars($userName) ?></strong>
                <span><?= htmlspecialchars($userRole) ?></span>
            </div>
        </header>

        <main>
            <?php if (!empty($flashSuccess)): ?>
                <div class="flash success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>

            <?php if (!empty($flashError)): ?>
                <div class="flash error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <?= $content ?>
        </main>

        <footer class="shell-footer">
            <?= htmlspecialchars($footerText) ?>
        </footer>
    </div>
</div>
<script>
(() => {
    const idleTimeoutMs = <?= (int) $idleTimeoutSeconds ?> * 1000;
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

    const logoutForm = document.querySelector('.logout-form');
    const logoutReasonField = logoutForm instanceof HTMLFormElement
        ? logoutForm.querySelector('[data-logout-reason]')
        : null;

    if (!(logoutForm instanceof HTMLFormElement) || idleTimeoutMs <= 0) {
        return;
    }

    let idleTimer = null;
    let logoutTriggered = false;

    const triggerIdleLogout = () => {
        if (logoutTriggered) {
            return;
        }

        logoutTriggered = true;

        if (logoutReasonField instanceof HTMLInputElement) {
            logoutReasonField.value = 'idle_timeout';
        }

        if (typeof logoutForm.requestSubmit === 'function') {
            logoutForm.requestSubmit();
            return;
        }

        logoutForm.submit();
    };

    const resetIdleTimer = () => {
        if (logoutTriggered) {
            return;
        }

        if (idleTimer !== null) {
            window.clearTimeout(idleTimer);
        }

        idleTimer = window.setTimeout(triggerIdleLogout, idleTimeoutMs);
    };

    ['click', 'keydown', 'mousemove', 'mousedown', 'scroll', 'touchstart'].forEach((eventName) => {
        document.addEventListener(eventName, resetIdleTimer, { passive: true });
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            resetIdleTimer();
        }
    });

    resetIdleTimer();
})();
</script>
</body>
</html>
