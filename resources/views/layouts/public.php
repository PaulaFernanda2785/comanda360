<?php
$seo = is_array($seo ?? null) ? $seo : [];
$title = trim((string) ($seo['title'] ?? ($title ?? 'MesiMenu')));
$description = trim((string) ($seo['description'] ?? ''));
$keywords = trim((string) ($seo['keywords'] ?? ''));
$canonical = trim((string) ($seo['canonical'] ?? app_url('/')));
$robots = trim((string) ($seo['robots'] ?? 'index,follow'));
$ogImage = trim((string) ($seo['og_image'] ?? asset_url('/img/logo-mesimenu.png')));
$structuredData = is_array($seo['structured_data'] ?? null) ? $seo['structured_data'] : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($description) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($keywords) ?>">
    <meta name="robots" content="<?= htmlspecialchars($robots) ?>">
    <meta name="theme-color" content="#05131f">
    <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars(asset_url('/img/logo-mesimenu.ico')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:title" content="<?= htmlspecialchars($title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($description) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonical) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($description) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>">
    <?php foreach ($structuredData as $schema): ?>
        <?php if (is_array($schema)): ?>
            <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
        <?php endif; ?>
    <?php endforeach; ?>
</head>
<body>
<?= $content ?>
<button class="back-to-top" type="button" aria-label="Voltar ao topo" data-back-to-top hidden>
    <span class="back-to-top-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" role="img">
            <path d="M12 19V5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M6 11L12 5L18 11" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </span>
</button>
<style>
    .back-to-top{
        position:fixed;
        right:22px;
        bottom:22px;
        width:56px;
        height:56px;
        border:0;
        border-radius:18px;
        background:rgba(8,27,46,.86);
        box-shadow:0 18px 32px rgba(7,21,35,.22);
        display:grid;
        place-items:center;
        color:#fff;
        cursor:pointer;
        z-index:60;
        backdrop-filter:blur(16px);
        transition:transform .2s ease, opacity .2s ease, background .2s ease;
    }
    .back-to-top:hover{
        transform:translateY(-2px);
        background:rgba(255,122,24,.92);
    }
    .back-to-top-icon{
        display:grid;
        place-items:center;
        width:24px;
        height:24px;
    }
    .back-to-top-icon svg{
        width:22px;
        height:22px;
        display:block;
    }
    .back-to-top[hidden]{display:none}
    @media (max-width:720px){
        .back-to-top{
            right:14px;
            bottom:14px;
            width:52px;
            height:52px;
            border-radius:16px;
        }
    }
</style>
<script>
(() => {
    const trigger = document.querySelector('[data-back-to-top]');
    if (!(trigger instanceof HTMLButtonElement)) {
        return;
    }

    const syncVisibility = () => {
        trigger.hidden = window.scrollY < 420;
    };

    trigger.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    syncVisibility();
    window.addEventListener('scroll', syncVisibility, { passive: true });
})();
</script>
</body>
</html>
