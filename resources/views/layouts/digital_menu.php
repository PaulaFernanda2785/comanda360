<?php
$menuTheme = is_array($menuTheme ?? null) ? $menuTheme : [];
$theme = $menuTheme + [
    'company_name' => 'Estabelecimento',
    'title' => 'Menu digital',
    'description' => '',
    'primary_color' => '#1d4ed8',
    'secondary_color' => '#0f172a',
    'accent_color' => '#0ea5e9',
    'main_card_color' => '#0f172a',
    'logo_path' => '',
    'banner_path' => '',
    'footer_text' => 'MesiMenu - Atendimento digital da mesa.',
];
$logoUrl = trim((string) ($theme['logo_path'] ?? '')) !== '' ? company_image_url((string) $theme['logo_path']) : '';
$bannerUrl = trim((string) ($theme['banner_path'] ?? '')) !== '' ? company_image_url((string) $theme['banner_path']) : '';
$brandTitle = trim((string) ($theme['title'] ?? 'Menu digital'));
$brandDescription = trim((string) ($theme['description'] ?? ''));
$companyName = trim((string) ($theme['company_name'] ?? 'Estabelecimento'));
$footerText = trim((string) ($theme['footer_text'] ?? 'MesiMenu - Atendimento digital da mesa.'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Menu digital') ?></title>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars(base_url('/img/logo-mesimenu.ico')) ?>">
    <style>
        :root{
            --dm-primary: <?= htmlspecialchars((string) ($theme['primary_color'] ?? '#1d4ed8')) ?>;
            --dm-secondary: <?= htmlspecialchars((string) ($theme['secondary_color'] ?? '#0f172a')) ?>;
            --dm-accent: <?= htmlspecialchars((string) ($theme['accent_color'] ?? '#0ea5e9')) ?>;
            --dm-main-card: <?= htmlspecialchars((string) ($theme['main_card_color'] ?? '#0f172a')) ?>;
            --dm-topbar-offset: 76px;
            --dm-surface:#ffffff;
            --dm-surface-soft:#f8fafc;
            --dm-border:#dbe4f0;
            --dm-text:#0f172a;
            --dm-muted:#64748b;
            --dm-shadow:0 20px 45px rgba(15,23,42,.12);
            --dm-radius:22px;
        }
        *{box-sizing:border-box}
        html,body{margin:0;padding:0;max-width:100%;overflow-x:hidden}
        img{max-width:100%;display:block}
        body{
            font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif;
            color:var(--dm-text);
            background:
                radial-gradient(circle at top left, color-mix(in srgb, var(--dm-accent) 16%, transparent), transparent 28%),
                linear-gradient(180deg,#f6f8fc 0%,#eef3f9 100%);
            min-height:100vh;
            overflow-x:hidden;
        }
        a{color:inherit}
        .dm-shell{width:min(1180px,calc(100vw - 16px));max-width:calc(100vw - 16px);margin:0 auto;padding:18px 0 28px;display:grid;gap:18px}
        .dm-topbar{
            position:sticky;top:0;z-index:20;
            backdrop-filter:blur(18px);
            background:rgba(255,255,255,.78);
            border:1px solid rgba(219,228,240,.9);
            border-radius:20px;
            padding:12px 16px;
            display:flex;justify-content:space-between;align-items:center;gap:14px;box-shadow:0 10px 24px rgba(15,23,42,.08)
        }
        .dm-brand{display:flex;align-items:center;gap:12px;min-width:0}
        .dm-logo{
            width:52px;height:52px;border-radius:16px;overflow:hidden;background:#fff;border:1px solid rgba(255,255,255,.6);
            display:flex;align-items:center;justify-content:center;font-weight:800;color:var(--dm-secondary);box-shadow:inset 0 0 0 1px rgba(148,163,184,.14)
        }
        .dm-logo img{width:100%;height:100%;object-fit:cover}
        .dm-brand-copy{display:grid;gap:4px;min-width:0}
        .dm-brand-copy strong{font-size:15px;line-height:1.1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .dm-brand-copy span{font-size:12px;color:var(--dm-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .dm-topbar-badge{
            padding:8px 12px;border-radius:999px;background:rgba(15,23,42,.06);color:var(--dm-secondary);
            font-size:12px;font-weight:700;white-space:nowrap
        }
        .dm-shell,.dm-topbar,.dm-topbar > *,.dm-card,.dm-hero,.dm-hero-grid,.dm-content,.dm-stack,.dm-section-head,.dm-section-head > *{min-width:0}
        .dm-card{
            background:var(--dm-surface);border:1px solid var(--dm-border);border-radius:var(--dm-radius);
            box-shadow:var(--dm-shadow);padding:18px
        }
        .dm-hero{
            position:relative;overflow:hidden;padding:24px;
            background:
                linear-gradient(140deg, color-mix(in srgb, var(--dm-main-card) 92%, white 8%), color-mix(in srgb, var(--dm-primary) 74%, var(--dm-main-card) 26%) 58%, color-mix(in srgb, var(--dm-accent) 64%, var(--dm-main-card) 36%) 100%);
            color:#fff;
        }
        .dm-hero::after{
            content:"";position:absolute;inset:0;
            background:
                linear-gradient(180deg,rgba(15,23,42,.12),rgba(15,23,42,.48)),
                url('<?= htmlspecialchars($bannerUrl) ?>') center/cover no-repeat;
            opacity:<?= $bannerUrl !== '' ? '0.28' : '0' ?>;
            pointer-events:none
        }
        .dm-hero-grid{position:relative;z-index:1;display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,.8fr);gap:18px;align-items:end}
        .dm-hero-copy{display:grid;gap:12px}
        .dm-eyebrow{font-size:11px;text-transform:uppercase;letter-spacing:.14em;font-weight:800;opacity:.8}
        .dm-hero h1{margin:0;font-size:clamp(28px,4vw,44px);line-height:.95}
        .dm-hero p{margin:0;max-width:720px;color:rgba(255,255,255,.88);line-height:1.5}
        .dm-hero-pills{display:flex;gap:8px;flex-wrap:wrap}
        .dm-pill{padding:8px 12px;border-radius:999px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.18);font-size:12px;font-weight:700}
        .dm-flash{padding:14px 16px;border-radius:18px;border:1px solid #dbeafe;background:#eff6ff;color:#1d4ed8;font-weight:600}
        .dm-flash.error{border-color:#fecaca;background:#fef2f2;color:#b91c1c}
        .dm-content{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(0,.85fr);gap:18px;align-items:start}
        .dm-stack{display:grid;gap:18px}
        .dm-section-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:14px}
        .dm-section-head h2{margin:0;font-size:22px}
        .dm-section-head p{margin:6px 0 0;color:var(--dm-muted);font-size:14px;max-width:680px}
        .dm-footer{text-align:center;color:var(--dm-muted);font-size:12px;padding-bottom:10px}
        .btn,.btn-secondary,.btn-soft{
            appearance:none;border:0;border-radius:14px;padding:12px 16px;font-size:14px;font-weight:700;cursor:pointer;
            text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:8px;transition:transform .15s ease,filter .15s ease,background .15s ease
        }
        .btn{background:var(--dm-primary);color:#fff}
        .btn-secondary{background:rgba(15,23,42,.08);color:var(--dm-secondary)}
        .btn-soft{background:color-mix(in srgb, var(--dm-accent) 12%, white 88%);color:var(--dm-secondary)}
        .btn:hover,.btn-secondary:hover,.btn-soft:hover{transform:translateY(-1px);filter:brightness(1.02)}
        .btn[disabled]{opacity:.58;cursor:not-allowed;transform:none}
        .field{display:grid;gap:8px}
        .field label{font-size:13px;font-weight:700;color:var(--dm-secondary)}
        .field input,.field textarea,.field select{
            width:100%;border:1px solid var(--dm-border);border-radius:14px;padding:12px 14px;background:#fff;color:var(--dm-text);
            font:inherit
        }
        .field textarea{resize:vertical;min-height:100px}
        @media (max-width:980px){
            .dm-hero-grid,.dm-content{grid-template-columns:1fr}
        }
        @media (max-width:640px){
            .dm-shell{width:min(1180px,calc(100vw - 12px));max-width:calc(100vw - 12px);padding:10px 0 20px}
            .dm-topbar{padding:10px 12px;flex-direction:column;align-items:stretch}
            .dm-card,.dm-hero{padding:16px}
            .dm-brand{align-items:flex-start}
            .dm-topbar-badge{align-self:flex-start;max-width:100%}
            .dm-brand-copy strong,.dm-brand-copy span,.dm-topbar-badge{white-space:normal}
        }
    </style>
</head>
<body>
    <div class="dm-shell">
        <header class="dm-topbar">
            <div class="dm-brand">
                <div class="dm-logo">
                    <?php if ($logoUrl !== ''): ?>
                        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo da empresa">
                    <?php else: ?>
                        <?= htmlspecialchars(substr($companyName !== '' ? $companyName : 'ME', 0, 2)) ?>
                    <?php endif; ?>
                </div>
                <div class="dm-brand-copy">
                    <strong><?= htmlspecialchars($brandTitle !== '' ? $brandTitle : $companyName) ?></strong>
                    <span><?= htmlspecialchars($brandDescription !== '' ? $brandDescription : 'Atendimento digital da mesa com comanda vinculada ao QR Code.') ?></span>
                </div>
            </div>
            <span class="dm-topbar-badge">MesiMenu QR</span>
        </header>

        <?php if (!empty($flashSuccess)): ?>
            <div class="dm-flash"><?= htmlspecialchars((string) $flashSuccess) ?></div>
        <?php endif; ?>
        <?php if (!empty($flashError)): ?>
            <div class="dm-flash error"><?= htmlspecialchars((string) $flashError) ?></div>
        <?php endif; ?>

        <?= $content ?>

        <footer class="dm-footer"><?= htmlspecialchars($footerText) ?></footer>
    </div>
    <script>
    (() => {
        const topbar = document.querySelector('.dm-topbar');
        if (!(topbar instanceof HTMLElement)) {
            return;
        }

        const syncTopbarOffset = () => {
            const height = Math.ceil(topbar.getBoundingClientRect().height || 0);
            document.documentElement.style.setProperty('--dm-topbar-offset', `${Math.max(56, height)}px`);
        };

        syncTopbarOffset();
        window.addEventListener('resize', syncTopbarOffset);
        window.addEventListener('load', syncTopbarOffset);
    })();
    </script>
</body>
</html>
