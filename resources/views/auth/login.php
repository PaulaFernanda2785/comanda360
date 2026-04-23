<?php
$landingPage = is_array($landingPage ?? null) ? $landingPage : [];
$seo = is_array($seo ?? null) ? $seo : [];
$navigation = is_array($landingPage['navigation'] ?? null) ? $landingPage['navigation'] : [];
$heroMetrics = is_array($landingPage['hero_metrics'] ?? null) ? $landingPage['hero_metrics'] : [];
$aboutHighlights = is_array($landingPage['about_highlights'] ?? null) ? $landingPage['about_highlights'] : [];
$aboutCapabilities = is_array($landingPage['about_capabilities'] ?? null) ? $landingPage['about_capabilities'] : [];
$aboutModules = is_array($landingPage['about_modules'] ?? null) ? $landingPage['about_modules'] : [];
$problemPoints = is_array($landingPage['problem_points'] ?? null) ? $landingPage['problem_points'] : [];
$solutions = is_array($landingPage['solutions'] ?? null) ? $landingPage['solutions'] : [];
$featureGroups = is_array($landingPage['feature_groups'] ?? null) ? $landingPage['feature_groups'] : [];
$plans = is_array($landingPage['plans'] ?? null) ? $landingPage['plans'] : [];
$featuredPlans = is_array($landingPage['featured_plans'] ?? null) ? $landingPage['featured_plans'] : [];
$recommendedPlans = is_array($landingPage['recommended_plans'] ?? null) ? $landingPage['recommended_plans'] : [];
$plansStats = is_array($landingPage['plans_stats'] ?? null) ? $landingPage['plans_stats'] : [];
$workflow = is_array($landingPage['workflow'] ?? null) ? $landingPage['workflow'] : [];
$blogArticles = is_array($landingPage['blog_articles'] ?? null) ? $landingPage['blog_articles'] : [];
$faqItems = is_array($landingPage['faq'] ?? null) ? $landingPage['faq'] : [];

$logoUrl = public_logo_url();
$heroPanelImageUrl = public_embedded_image_url('img/painel-descktop.png');
$aboutPanelImageUrl = public_embedded_image_url('img/painel-tablet.png');
$featuresPanelImageUrl = public_embedded_image_url('img/painel-tablet.png');
$currentUrl = app_url((string) ($_SERVER['REQUEST_URI'] ?? '/'));

$formatMoney = static function (?float $amount): string {
    if ($amount === null) {
        return 'Sob consulta';
    }

    return 'R$ ' . number_format($amount, 2, ',', '.');
};

$formatPercent = static function (float $value): string {
    $formatted = number_format($value, 2, ',', '.');
    $formatted = rtrim(rtrim($formatted, '0'), ',');

    return $formatted !== '' ? $formatted : '0';
};

$formatLimit = static function (?int $value, string $label): string {
    if ($value === null) {
        return $label . ' ilimitados';
    }

    return $value . ' ' . $label;
};

$formatLimitValue = static function (?int $value): string {
    if ($value === null) {
        return 'Ilimitado';
    }

    return (string) $value;
};
?>

<style>
    :root{
        --bg:#04111d;
        --bg-soft:#0a1f33;
        --surface:#f6f8fb;
        --card:#ffffff;
        --card-strong:#091b2c;
        --line:rgba(12,34,56,.12);
        --text:#102235;
        --muted:#5c6b7c;
        --primary:#ff7a18;
        --primary-deep:#d85a00;
        --secondary:#0ea5a4;
        --accent:#facc15;
        --success:#0f9f66;
        --danger:#b42318;
        --shadow:0 24px 60px rgba(4,17,29,.16);
        --radius-xl:28px;
        --radius-lg:20px;
        --radius-md:16px;
        --max:1180px;
    }

    *{box-sizing:border-box}
    html{
        scroll-behavior:smooth;
        scroll-padding-top:128px;
    }
    body{
        margin:0;
        color:var(--text);
        background:
            radial-gradient(circle at top left, rgba(14,165,164,.22), transparent 28%),
            radial-gradient(circle at top right, rgba(250,204,21,.16), transparent 26%),
            linear-gradient(180deg, #f4efe7 0%, #eef4fb 26%, #f8fbff 100%);
        font-family:"Manrope","Segoe UI",sans-serif;
    }

    a{color:inherit}
    img{max-width:100%;display:block}
    .page-shell{
        position:relative;
        overflow:hidden;
        padding-top:108px;
    }
    .page-shell::before,
    .page-shell::after{
        content:"";
        position:fixed;
        inset:auto;
        width:420px;
        height:420px;
        border-radius:999px;
        filter:blur(60px);
        opacity:.22;
        pointer-events:none;
        z-index:0;
    }
    .page-shell::before{top:-140px;left:-120px;background:#0ea5a4}
    .page-shell::after{right:-120px;bottom:12%;background:#ff7a18}

    .container{width:min(calc(100% - 32px), var(--max));margin:0 auto;position:relative;z-index:1}
    .section{padding:88px 0}
    .eyebrow{
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:8px 14px;
        border-radius:999px;
        background:rgba(255,255,255,.68);
        border:1px solid rgba(255,255,255,.82);
        font-size:12px;
        font-weight:800;
        letter-spacing:.12em;
        text-transform:uppercase;
        color:#0b3954;
        backdrop-filter:blur(16px);
    }
    .eyebrow::before{
        content:"";
        width:9px;
        height:9px;
        border-radius:999px;
        background:linear-gradient(135deg,var(--primary),var(--accent));
        box-shadow:0 0 0 5px rgba(255,122,24,.12);
    }
    .section-head{max-width:760px;margin-bottom:28px}
    .section-head h2{
        margin:16px 0 12px;
        font:700 clamp(30px,4vw,52px)/1.02 "Space Grotesk","Manrope",sans-serif;
        letter-spacing:-.04em;
        color:#061320;
    }
    .section-head p{margin:0;font-size:18px;line-height:1.65;color:var(--muted)}
    #sobre .section-head h2,
    #problemas .section-head h2,
    #solucoes .section-head h2,
    #funcionalidades .section-head h2{
        font-size:clamp(24px,3vw,38px);
        line-height:1.08;
    }
    .reveal{
        opacity:0;
        transform:translateY(28px);
        transition:opacity .7s ease, transform .7s ease;
    }
    .reveal.is-visible{
        opacity:1;
        transform:translateY(0);
    }

    .site-header{
        position:fixed;
        top:16px;
        left:0;
        right:0;
        z-index:28;
        padding:0;
        background:transparent;
    }
    .site-header-inner{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:16px;
        padding:14px 18px;
        border-radius:28px;
        background:rgba(255,255,255,.42);
        border:1px solid rgba(255,255,255,.58);
        box-shadow:0 18px 42px rgba(8,27,46,.10);
        backdrop-filter:blur(22px);
        transition:background .24s ease, border-color .24s ease, box-shadow .24s ease, transform .24s ease;
    }
    .site-header.is-scrolled .site-header-inner{
        background:rgba(255,255,255,.78);
        border-color:rgba(255,255,255,.84);
        box-shadow:0 24px 54px rgba(8,27,46,.14);
        transform:translateY(-1px);
    }
    .brand{
        display:flex;
        align-items:center;
        text-decoration:none;
        min-width:0;
        flex:0 0 auto;
    }
    .brand-mark{
        display:flex;
        align-items:center;
        justify-content:center;
        min-width:0;
        flex:0 0 auto;
    }
    .brand-mark img{
        display:block;
        height:54px;
        width:auto;
        max-width:min(300px, 32vw);
        object-fit:contain;
        flex:0 0 auto;
    }
    .brand-copy strong{
        display:block;
        font:700 19px/1 "Space Grotesk","Manrope",sans-serif;
        color:#081b2e;
    }
    .brand-copy span{
        display:block;
        margin-top:4px;
        font-size:12px;
        color:#5a6a7d;
    }
    .site-nav{
        display:flex;
        align-items:center;
        gap:10px;
        flex-wrap:wrap;
        padding:6px;
        border-radius:999px;
        background:rgba(255,255,255,.16);
        border:1px solid rgba(255,255,255,.18);
    }
    .site-nav a{
        text-decoration:none;
        padding:11px 14px;
        border-radius:999px;
        color:#274056;
        font-size:14px;
        font-weight:700;
        transition:background .24s ease,color .24s ease,transform .24s ease;
    }
    .site-nav a:hover{
        background:#ffffff;
        color:#081b2e;
        transform:translateY(-1px);
    }
    .header-actions{display:flex;align-items:center;gap:12px}
    .header-actions .btn-secondary{
        min-height:48px;
        padding:0 18px;
        background:rgba(255,255,255,.84);
        border-color:rgba(8,27,46,.08);
    }
    .menu-toggle{
        display:none;
        width:48px;
        height:48px;
        border-radius:16px;
        border:1px solid rgba(8,27,46,.12);
        background:#fff;
        color:#081b2e;
        cursor:pointer;
    }

    .btn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:10px;
        padding:15px 22px;
        border-radius:16px;
        border:0;
        text-decoration:none;
        font-weight:800;
        font-size:15px;
        cursor:pointer;
        transition:transform .24s ease, box-shadow .24s ease, background .24s ease, color .24s ease;
    }
    .btn:hover{transform:translateY(-2px)}
    .btn-primary{
        color:#fff;
        background:linear-gradient(135deg,var(--primary) 0%, #f95f2b 100%);
        box-shadow:0 18px 28px rgba(249,95,43,.28);
    }
    .btn-secondary{
        color:#081b2e;
        background:#fff;
        border:1px solid rgba(8,27,46,.12);
        box-shadow:0 14px 24px rgba(8,27,46,.08);
    }
    .btn-ghost{
        color:#f0f5fa;
        background:rgba(255,255,255,.08);
        border:1px solid rgba(255,255,255,.16);
    }

    .hero{padding:48px 0 36px}
    .hero-grid{
        display:grid;
        grid-template-columns:minmax(0,1fr);
        gap:24px;
        align-items:stretch;
    }
    .hero-copy{
        position:relative;
        padding:34px;
        min-height:100%;
        border-radius:var(--radius-xl);
        background:
            linear-gradient(120deg, rgba(3,11,28,.92) 0%, rgba(6,22,56,.84) 32%, rgba(7,24,63,.68) 52%, rgba(7,24,63,.36) 72%, rgba(255,122,24,.14) 100%),
            url('<?= htmlspecialchars($heroPanelImageUrl) ?>') center center/cover no-repeat;
        color:#f7fbff;
        box-shadow:0 36px 72px rgba(4,17,29,.26);
        overflow:hidden;
        border:1px solid rgba(255,255,255,.14);
    }
    .hero-copy::before,
    .hero-copy::after{
        content:"";
        position:absolute;
        border-radius:999px;
        pointer-events:none;
    }
    .hero-copy::before{
        inset:0;
        border-radius:inherit;
        background:
            linear-gradient(180deg, rgba(255,255,255,.10) 0%, rgba(255,255,255,0) 22%),
            linear-gradient(90deg, rgba(3,11,28,.82) 0%, rgba(3,11,28,.58) 36%, rgba(3,11,28,.18) 62%, rgba(3,11,28,.52) 100%);
    }
    .hero-copy::after{
        width:320px;
        height:320px;
        right:-120px;
        top:-110px;
        background:radial-gradient(circle, rgba(255,160,64,.40) 0%, rgba(255,160,64,0) 72%);
        filter:blur(10px);
    }
    .hero-copy > *{position:relative;z-index:1}
    .hero-copy-top{
        display:grid;
        grid-template-columns:minmax(0,1fr);
        gap:18px;
        align-items:start;
    }
    .hero-kicker{
        display:flex;
        flex-direction:column;
        gap:12px;
        max-width:560px;
    }
    .hero-copy h1{
        margin:10px 0 14px;
        font:700 clamp(26px,3vw,44px)/1.04 "Space Grotesk","Manrope",sans-serif;
        letter-spacing:-.05em;
        max-width:520px;
        text-shadow:0 12px 30px rgba(0,0,0,.28);
    }
    .hero-copy p{
        max-width:540px;
        margin:0;
        font-size:17px;
        line-height:1.68;
        color:rgba(240,245,250,.88);
    }
    .hero-actions{
        display:flex;
        gap:14px;
        flex-wrap:wrap;
        margin:22px 0 28px;
    }
    .hero-metrics{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:14px;
    }
    .hero-metric{
        padding:18px 18px 16px;
        border-radius:20px;
        background:linear-gradient(180deg, rgba(255,255,255,.14) 0%, rgba(255,255,255,.08) 100%);
        border:1px solid rgba(255,255,255,.14);
        backdrop-filter:blur(16px);
        box-shadow:inset 0 1px 0 rgba(255,255,255,.08);
    }
    .hero-metric strong{
        display:block;
        font:700 24px/1 "Space Grotesk","Manrope",sans-serif;
        color:#fff;
    }
    .hero-metric span{
        display:block;
        margin-top:8px;
        font-size:13px;
        line-height:1.45;
        color:rgba(240,245,250,.82);
    }
    .hero-meta-row{
        display:grid;
        grid-template-columns:minmax(0,1fr);
        gap:16px;
    }

    .field{display:grid;gap:8px}
    .field label{
        font-size:13px;
        font-weight:800;
        letter-spacing:.03em;
        text-transform:uppercase;
        color:#274056;
    }
    .field input,
    .field textarea,
    .field select{
        width:100%;
        border:1px solid rgba(12,34,56,.14);
        background:#fff;
        color:#081b2e;
        border-radius:16px;
        padding:15px 16px;
        font:600 15px/1.4 "Manrope","Segoe UI",sans-serif;
        outline:none;
        transition:border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }
    .field textarea{min-height:130px;resize:vertical}
    .field input:focus,
    .field textarea:focus,
    .field select:focus{
        border-color:rgba(255,122,24,.56);
        box-shadow:0 0 0 4px rgba(255,122,24,.12);
    }
    .form-grid{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:14px;
    }
    .form-grid .field.full{grid-column:1 / -1}
    .about-grid,
    .feature-grid,
    .problems-grid,
    .solutions-grid,
    .plans-grid,
    .blog-grid,
    .contact-grid,
    .footer-grid{
        display:grid;
        gap:18px;
    }
    .about-grid{grid-template-columns:minmax(0,1.08fr) minmax(360px,.92fr);align-items:stretch}
    .problems-layout{display:grid;grid-template-columns:minmax(300px,.82fr) minmax(0,1.18fr);gap:20px;align-items:stretch}
    .problems-grid{grid-template-columns:repeat(2,minmax(0,1fr));align-content:start}
    .solutions-layout{display:grid;grid-template-columns:minmax(300px,.84fr) minmax(0,1.16fr);gap:20px;align-items:stretch}
    .solutions-grid{grid-template-columns:repeat(2,minmax(0,1fr));align-content:start}
    .feature-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .plans-grid{grid-template-columns:repeat(3,minmax(0,1fr));align-items:stretch}
    .blog-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
    .contact-grid{grid-template-columns:minmax(0,1.05fr) minmax(320px,.95fr)}
    .footer-grid{grid-template-columns:minmax(0,1.1fr) repeat(3,minmax(0,.5fr))}

    .content-card,
    .feature-card,
    .problem-card,
    .solution-card,
    .plan-card,
    .blog-card,
    .contact-card,
    .metric-card{
        padding:24px;
        border-radius:24px;
        background:rgba(255,255,255,.82);
        border:1px solid rgba(255,255,255,.9);
        box-shadow:var(--shadow);
        backdrop-filter:blur(18px);
    }
    .content-card h3,
    .feature-card h3,
    .problem-card h3,
    .solution-card h3,
    .plan-card h3,
    .blog-card h3,
    .contact-card h3,
    .metric-card h3{
        margin:0;
        font:700 24px/1.08 "Space Grotesk","Manrope",sans-serif;
        letter-spacing:-.04em;
        color:#081b2e;
    }
    .content-card p,
    .feature-card p,
    .problem-card p,
    .solution-card p,
    .blog-card p,
    .contact-card p,
    .metric-card p{
        margin:12px 0 0;
        color:var(--muted);
        line-height:1.72;
    }
    .about-panel{
        position:relative;
        min-height:100%;
        padding:32px;
        border-radius:30px;
        overflow:hidden;
    }
    .about-panel h3{
        margin:0;
        font:700 clamp(22px,2.4vw,30px)/1.08 "Space Grotesk","Manrope",sans-serif;
        letter-spacing:-.05em;
    }
    .about-panel p{
        margin:0;
        line-height:1.72;
    }
    .about-story{
        background:linear-gradient(180deg, rgba(255,255,255,.9) 0%, rgba(247,250,255,.82) 100%);
        border:1px solid rgba(255,255,255,.95);
        box-shadow:var(--shadow);
        backdrop-filter:blur(20px);
    }
    .about-story::before{
        content:"";
        position:absolute;
        inset:-80px auto auto -60px;
        width:220px;
        height:220px;
        border-radius:999px;
        background:radial-gradient(circle, rgba(14,165,164,.18) 0%, rgba(14,165,164,0) 72%);
        pointer-events:none;
    }
    .about-story > *{position:relative;z-index:1}
    .about-kicker{
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:9px 14px;
        border-radius:999px;
        background:#eef6ff;
        border:1px solid rgba(17,24,39,.06);
        color:#10324d;
        font-size:12px;
        font-weight:800;
        letter-spacing:.08em;
        text-transform:uppercase;
    }
    .about-kicker::before{
        content:"";
        width:9px;
        height:9px;
        border-radius:999px;
        background:linear-gradient(135deg,var(--primary),var(--accent));
        box-shadow:0 0 0 5px rgba(255,122,24,.1);
    }
    .about-kicker.is-contrast{
        background:rgba(255,255,255,.1);
        border-color:rgba(255,255,255,.16);
        color:#f8fbff;
    }
    .about-lead{
        display:grid;
        gap:18px;
    }
    .about-lead p{
        color:#526476;
        font-size:17px;
    }
    .about-highlights{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:12px;
        margin-top:24px;
    }
    .about-highlight{
        display:grid;
        gap:6px;
        padding:18px;
        border-radius:22px;
        background:#fff;
        border:1px solid rgba(12,34,56,.08);
        box-shadow:0 18px 30px rgba(8,27,46,.06);
    }
    .about-highlight strong{
        font:700 20px/1.1 "Space Grotesk","Manrope",sans-serif;
        color:#07192b;
    }
    .about-highlight span{
        color:#5f7182;
        font-size:13px;
        line-height:1.5;
    }
    .about-capabilities{
        display:flex;
        flex-wrap:wrap;
        gap:10px;
        margin-top:20px;
    }
    .about-capabilities span{
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:10px 14px;
        border-radius:999px;
        background:rgba(8,27,46,.04);
        border:1px solid rgba(8,27,46,.08);
        color:#16324a;
        font-size:13px;
        font-weight:700;
    }
    .about-capabilities span::before{
        content:"";
        width:8px;
        height:8px;
        border-radius:999px;
        background:var(--secondary);
        box-shadow:0 0 0 4px rgba(14,165,164,.12);
    }
    .about-modules{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:12px;
        margin-top:20px;
    }
    .about-module{
        padding:18px;
        border-radius:22px;
        background:linear-gradient(180deg,#f9fbff 0%, #edf4fb 100%);
        border:1px solid rgba(8,27,46,.08);
        box-shadow:inset 0 1px 0 rgba(255,255,255,.78);
    }
    .about-module span{
        display:inline-block;
        font-size:11px;
        font-weight:800;
        letter-spacing:.1em;
        text-transform:uppercase;
        color:#ff7a18;
    }
    .about-module strong{
        display:block;
        margin-top:10px;
        font:700 20px/1.15 "Space Grotesk","Manrope",sans-serif;
        color:#081b2e;
    }
    .about-module p{
        margin-top:10px;
        color:#5c6b7c;
        font-size:14px;
    }
    .about-visual{
        color:#edf5fd;
        background:
            radial-gradient(circle at top right, rgba(255,160,64,.26) 0%, rgba(255,160,64,0) 36%),
            radial-gradient(circle at bottom left, rgba(14,165,164,.22) 0%, rgba(14,165,164,0) 42%),
            linear-gradient(160deg,#061526 0%, #0c2440 52%, #08192a 100%);
        border:1px solid rgba(255,255,255,.08);
        box-shadow:0 34px 78px rgba(4,17,29,.26);
    }
    .about-visual::before{
        content:"";
        position:absolute;
        inset:0;
        border-radius:inherit;
        background:linear-gradient(180deg, rgba(255,255,255,.08) 0%, rgba(255,255,255,0) 22%);
        pointer-events:none;
    }
    .about-visual > *{position:relative;z-index:1}
    .about-visual-copy{
        display:grid;
        gap:16px;
        max-width:520px;
    }
    .about-visual-copy p{
        color:rgba(237,245,253,.8);
        font-size:16px;
    }
    .tablet-stage{
        position:relative;
        display:grid;
        place-items:center;
        margin-top:24px;
        padding:26px 14px 42px;
    }
    .tablet-stage::before{
        content:"";
        position:absolute;
        width:82%;
        height:62%;
        border-radius:999px;
        background:radial-gradient(circle, rgba(255,122,24,.34) 0%, rgba(255,122,24,0) 74%);
        filter:blur(28px);
        z-index:0;
    }
    .tablet-stage img{
        position:relative;
        z-index:1;
        width:min(100%, 560px);
        margin:0 auto;
        transform:perspective(1400px) rotateY(-9deg) rotateX(4deg) rotateZ(-1deg);
        filter:drop-shadow(0 32px 54px rgba(1,10,23,.44));
    }
    .about-floating-tag{
        position:absolute;
        z-index:2;
        max-width:260px;
        padding:12px 16px;
        border-radius:18px;
        background:linear-gradient(180deg, rgba(4,17,29,.92) 0%, rgba(6,22,39,.84) 100%);
        border:1px solid rgba(255,255,255,.18);
        box-shadow:0 20px 34px rgba(0,0,0,.28);
        backdrop-filter:blur(18px);
        color:#fff;
        font-size:13px;
        font-weight:800;
        line-height:1.5;
        text-shadow:0 1px 2px rgba(0,0,0,.24);
    }
    .about-floating-tag.tag-top{top:0;left:6px}
    .about-floating-tag.tag-bottom{
        right:18px;
        bottom:4px;
        max-width:300px;
        background:linear-gradient(180deg, rgba(255,122,24,.96) 0%, rgba(214,92,0,.92) 100%);
        border-color:rgba(255,255,255,.24);
    }
    .about-visual-footer{margin-top:22px}
    .about-footer-card{
        display:grid;
        gap:6px;
        padding:18px;
        border-radius:22px;
        background:rgba(255,255,255,.08);
        border:1px solid rgba(255,255,255,.12);
    }
    .about-footer-card strong{
        font:700 18px/1.15 "Space Grotesk","Manrope",sans-serif;
        color:#fff;
    }
    .about-footer-card span{
        color:rgba(237,245,253,.76);
        line-height:1.6;
    }
    .problems-panel{
        position:relative;
        display:grid;
        gap:18px;
        min-height:100%;
        padding:30px;
        border-radius:30px;
        color:#eef7ff;
        overflow:hidden;
        background:
            radial-gradient(circle at top right, rgba(255,160,64,.28) 0%, rgba(255,160,64,0) 34%),
            radial-gradient(circle at bottom left, rgba(14,165,164,.22) 0%, rgba(14,165,164,0) 38%),
            linear-gradient(165deg,#08192b 0%, #0b2942 54%, #0f3857 100%);
        box-shadow:0 36px 70px rgba(4,17,29,.24);
        border:1px solid rgba(255,255,255,.08);
    }
    .problems-panel::before{
        content:"";
        position:absolute;
        inset:0;
        border-radius:inherit;
        background:linear-gradient(180deg, rgba(255,255,255,.08) 0%, rgba(255,255,255,0) 22%);
        pointer-events:none;
    }
    .problems-panel > *{position:relative;z-index:1}
    .problem-panel-badge{
        display:inline-flex;
        align-items:center;
        gap:8px;
        width:max-content;
        padding:9px 14px;
        border-radius:999px;
        background:rgba(255,255,255,.09);
        border:1px solid rgba(255,255,255,.14);
        color:#f8fbff;
        font-size:12px;
        font-weight:800;
        letter-spacing:.08em;
        text-transform:uppercase;
    }
    .problem-panel-badge::before{
        content:"";
        width:8px;
        height:8px;
        border-radius:999px;
        background:linear-gradient(135deg,var(--primary),var(--accent));
        box-shadow:0 0 0 4px rgba(255,122,24,.14);
    }
    .problems-panel h3{
        margin:0;
        max-width:12ch;
        font:700 clamp(22px,2.4vw,32px)/1.06 "Space Grotesk","Manrope",sans-serif;
        letter-spacing:-.05em;
        color:#fff;
    }
    .problems-panel p{
        margin:0;
        max-width:48ch;
        color:rgba(238,247,255,.8);
        line-height:1.75;
        font-size:16px;
    }
    .problems-panel-list{
        display:grid;
        gap:12px;
        margin-top:4px;
    }
    .problems-panel-list div{
        padding:16px 18px;
        border-radius:22px;
        background:rgba(255,255,255,.08);
        border:1px solid rgba(255,255,255,.12);
        backdrop-filter:blur(14px);
    }
    .problems-panel-list strong{
        display:block;
        font:700 18px/1.15 "Space Grotesk","Manrope",sans-serif;
        color:#fff;
    }
    .problems-panel-list span{
        display:block;
        margin-top:8px;
        color:rgba(238,247,255,.78);
        line-height:1.65;
        font-size:14px;
    }
    .problem-card{
        position:relative;
        overflow:hidden;
        min-height:220px;
        padding-top:28px;
        background:linear-gradient(180deg,#fff7f2 0%, #ffffff 100%);
        border-color:rgba(255,122,24,.16);
        box-shadow:0 24px 52px rgba(8,27,46,.1);
    }
    .problem-card::before{
        content:"";
        position:absolute;
        inset:0 auto auto 0;
        width:100%;
        height:4px;
        background:linear-gradient(90deg,var(--primary) 0%, #ffb366 52%, rgba(255,179,102,0) 100%);
    }
    .problem-card .problem-index,
    .workflow-item .workflow-step{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:42px;
        height:42px;
        border-radius:14px;
        background:#081b2e;
        color:#fff;
        font-weight:800;
    }
    .problem-card h3{
        margin-top:18px;
        max-width:15ch;
        font-size:22px;
    }
    .problem-card p{
        margin-top:14px;
        font-size:15px;
        line-height:1.7;
    }
    .solution-card{
        position:relative;
        overflow:hidden;
        min-height:220px;
        padding-top:28px;
        background:linear-gradient(180deg,#eefbf9 0%, #ffffff 100%);
        border-color:rgba(14,165,164,.16);
        box-shadow:0 24px 52px rgba(8,27,46,.09);
    }
    .solution-card::before{
        content:"";
        position:absolute;
        inset:0 auto auto 0;
        width:100%;
        height:4px;
        background:linear-gradient(90deg,var(--secondary) 0%, #6fe4cf 54%, rgba(111,228,207,0) 100%);
    }
    .solution-eyebrow,
    .blog-category,
    .plan-badge,
    .feature-chip{
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:8px 12px;
        border-radius:999px;
        font-size:12px;
        font-weight:800;
        letter-spacing:.08em;
        text-transform:uppercase;
    }
    .solution-eyebrow{background:#dff8f5;color:#0a5c67}
    .solution-card h3{
        margin-top:18px;
        max-width:16ch;
        font-size:22px;
    }
    .solution-card p{
        margin-top:14px;
        font-size:15px;
        line-height:1.7;
    }
    .solutions-panel{
        position:relative;
        display:grid;
        gap:18px;
        min-height:100%;
        padding:30px;
        border-radius:30px;
        overflow:hidden;
        color:#f7fcfb;
        background:
            radial-gradient(circle at top left, rgba(111,228,207,.22) 0%, rgba(111,228,207,0) 34%),
            radial-gradient(circle at bottom right, rgba(255,160,64,.18) 0%, rgba(255,160,64,0) 38%),
            linear-gradient(165deg,#05232b 0%, #0b4b53 54%, #0f6a66 100%);
        box-shadow:0 36px 70px rgba(4,17,29,.22);
        border:1px solid rgba(255,255,255,.08);
    }
    .solutions-panel::before{
        content:"";
        position:absolute;
        inset:0;
        border-radius:inherit;
        background:linear-gradient(180deg, rgba(255,255,255,.08) 0%, rgba(255,255,255,0) 22%);
        pointer-events:none;
    }
    .solutions-panel > *{position:relative;z-index:1}
    .solutions-panel-badge{
        display:inline-flex;
        align-items:center;
        gap:8px;
        width:max-content;
        padding:9px 14px;
        border-radius:999px;
        background:rgba(255,255,255,.1);
        border:1px solid rgba(255,255,255,.14);
        color:#f8fbff;
        font-size:12px;
        font-weight:800;
        letter-spacing:.08em;
        text-transform:uppercase;
    }
    .solutions-panel-badge::before{
        content:"";
        width:8px;
        height:8px;
        border-radius:999px;
        background:linear-gradient(135deg,var(--secondary),#6fe4cf);
        box-shadow:0 0 0 4px rgba(111,228,207,.12);
    }
    .solutions-panel h3{
        margin:0;
        max-width:12ch;
        font:700 clamp(22px,2.5vw,34px)/1.05 "Space Grotesk","Manrope",sans-serif;
        letter-spacing:-.05em;
        color:#fff;
    }
    .solutions-panel p{
        margin:0;
        max-width:48ch;
        color:rgba(247,252,251,.82);
        line-height:1.75;
        font-size:16px;
    }
    .solutions-panel-list{
        display:grid;
        gap:12px;
        margin-top:4px;
    }
    .solutions-panel-list div{
        padding:16px 18px;
        border-radius:22px;
        background:rgba(255,255,255,.09);
        border:1px solid rgba(255,255,255,.12);
        backdrop-filter:blur(14px);
    }
    .solutions-panel-list strong{
        display:block;
        font:700 18px/1.15 "Space Grotesk","Manrope",sans-serif;
        color:#fff;
    }
    .solutions-panel-list span{
        display:block;
        margin-top:8px;
        color:rgba(247,252,251,.78);
        line-height:1.65;
        font-size:14px;
    }
    .blog-category{background:#edf2ff;color:#21358a}
    .feature-chip{background:#eef4fb;color:#19344f}
    .plan-badge{background:#fff4d4;color:#8b5c00;white-space:nowrap}
    .features-showcase{
        display:grid;
        gap:20px;
    }
    .features-hero{
        position:relative;
        display:grid;
        grid-template-columns:minmax(0,.92fr) minmax(320px,1.08fr);
        gap:24px;
        padding:30px;
        border-radius:30px;
        overflow:hidden;
        color:#f7fbff;
        background:
            radial-gradient(circle at top left, rgba(14,165,164,.22) 0%, rgba(14,165,164,0) 34%),
            radial-gradient(circle at bottom right, rgba(255,160,64,.22) 0%, rgba(255,160,64,0) 38%),
            linear-gradient(160deg,#071728 0%, #0b3146 52%, #0f5060 100%);
        border:1px solid rgba(255,255,255,.08);
        box-shadow:0 36px 72px rgba(4,17,29,.24);
    }
    .features-hero::before{
        content:"";
        position:absolute;
        inset:0;
        border-radius:inherit;
        background:linear-gradient(180deg, rgba(255,255,255,.08) 0%, rgba(255,255,255,0) 22%);
        pointer-events:none;
    }
    .features-hero > *{position:relative;z-index:1}
    .features-hero-copy{
        display:grid;
        gap:18px;
        align-content:start;
    }
    .features-hero-badge{
        display:inline-flex;
        align-items:center;
        gap:8px;
        width:max-content;
        padding:9px 14px;
        border-radius:999px;
        background:rgba(255,255,255,.1);
        border:1px solid rgba(255,255,255,.14);
        color:#f8fbff;
        font-size:12px;
        font-weight:800;
        letter-spacing:.08em;
        text-transform:uppercase;
    }
    .features-hero-badge::before{
        content:"";
        width:8px;
        height:8px;
        border-radius:999px;
        background:linear-gradient(135deg,var(--secondary),#6fe4cf);
        box-shadow:0 0 0 4px rgba(111,228,207,.12);
    }
    .features-hero-copy h3{
        margin:0;
        max-width:16ch;
        font:700 clamp(20px,2.2vw,30px)/1.06 "Space Grotesk","Manrope",sans-serif;
        letter-spacing:-.05em;
        color:#fff;
    }
    .features-hero-copy p{
        margin:0;
        max-width:54ch;
        color:rgba(247,251,255,.8);
        line-height:1.75;
        font-size:16px;
    }
    .features-hero-points{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:12px;
    }
    .features-hero-point{
        display:grid;
        gap:6px;
        padding:16px 18px;
        border-radius:22px;
        background:rgba(255,255,255,.09);
        border:1px solid rgba(255,255,255,.12);
        backdrop-filter:blur(14px);
    }
    .features-hero-point strong{
        font:700 17px/1.15 "Space Grotesk","Manrope",sans-serif;
        color:#fff;
    }
    .features-hero-point span{
        color:rgba(247,251,255,.78);
        line-height:1.6;
        font-size:13px;
    }
    .features-hero-visual{
        position:relative;
        display:grid;
        place-items:center;
        min-height:100%;
        padding:12px 12px 0;
    }
    .features-hero-visual::before{
        content:"";
        position:absolute;
        width:82%;
        height:64%;
        border-radius:999px;
        background:radial-gradient(circle, rgba(255,122,24,.28) 0%, rgba(255,122,24,0) 74%);
        filter:blur(28px);
        z-index:0;
    }
    .features-hero-visual img{
        position:relative;
        z-index:1;
        width:min(100%, 560px);
        margin:0 auto;
        transform:none;
        filter:drop-shadow(0 26px 42px rgba(1,10,23,.34));
    }
    .feature-card{
        position:relative;
        display:grid;
        gap:18px;
        overflow:hidden;
        min-height:100%;
        padding-top:24px;
        background:linear-gradient(180deg,#f2f8ff 0%, #ffffff 100%);
        border-color:rgba(11,75,83,.12);
        box-shadow:0 24px 52px rgba(8,27,46,.09);
    }
    .feature-card::before{
        content:"";
        position:absolute;
        inset:0 auto auto 0;
        width:100%;
        height:4px;
        background:linear-gradient(90deg,#0b4b53 0%, #0ea5a4 56%, rgba(14,165,164,0) 100%);
    }
    .feature-card-head{
        display:flex;
        align-items:center;
        gap:12px;
        flex-wrap:wrap;
    }
    .feature-step{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:42px;
        height:42px;
        border-radius:14px;
        background:#081b2e;
        color:#fff;
        font-weight:800;
    }
    .feature-card-copy{
        display:grid;
        gap:12px;
    }
    .feature-card h3{
        margin:0;
        max-width:16ch;
        font-size:20px;
        line-height:1.14;
    }
    .feature-card p{
        margin:0;
        color:#56697c;
        font-size:15px;
        line-height:1.72;
    }
    .feature-media{
        position:relative;
        margin:0;
        padding:20px 20px 16px;
        min-height:240px;
        border-radius:24px;
        background:
            radial-gradient(circle at top left, rgba(14,165,164,.12) 0%, rgba(14,165,164,0) 38%),
            linear-gradient(180deg,#f8fbff 0%, #eaf3fb 100%);
        border:1px solid rgba(8,27,46,.08);
        overflow:hidden;
    }
    .feature-media::after{
        content:"";
        position:absolute;
        inset:auto -28px -38px auto;
        width:140px;
        height:140px;
        border-radius:999px;
        background:radial-gradient(circle, rgba(255,122,24,.18) 0%, rgba(255,122,24,0) 72%);
        pointer-events:none;
    }
    .feature-media img{
        position:relative;
        z-index:1;
        width:100%;
        height:260px;
        object-fit:contain;
        object-position:center bottom;
        filter:drop-shadow(0 18px 28px rgba(8,27,46,.16));
    }

    .feature-card ul,
    .plan-features,
    .footer-list{
        margin:16px 0 0;
        padding:0;
        list-style:none;
        display:grid;
        gap:10px;
    }
    .feature-card li,
    .plan-features li,
    .footer-list li{
        display:flex;
        align-items:flex-start;
        gap:10px;
        color:#2f4659;
        line-height:1.55;
    }
    .feature-card li::before,
    .plan-features li::before,
    .footer-list li::before{
        content:"";
        width:10px;
        height:10px;
        margin-top:7px;
        border-radius:999px;
        background:linear-gradient(135deg,var(--primary),var(--secondary));
        flex-shrink:0;
    }
    .feature-result{
        display:flex;
        align-items:flex-start;
        gap:10px;
        padding:14px 16px;
        border-radius:18px;
        background:#f7fbff;
        border:1px solid rgba(8,27,46,.08);
        color:#17314a;
        font-size:14px;
        font-weight:700;
        line-height:1.6;
    }
    .feature-result::before{
        content:"";
        width:10px;
        height:10px;
        margin-top:6px;
        border-radius:999px;
        background:linear-gradient(135deg,var(--secondary),var(--accent));
        box-shadow:0 0 0 5px rgba(14,165,164,.10);
        flex-shrink:0;
    }
    .workflow{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:18px;
        margin-top:24px;
    }
    .workflow-item{
        padding:24px;
        border-radius:24px;
        background:linear-gradient(180deg,#091b2c 0%, #133653 100%);
        color:#f4f8fb;
        box-shadow:0 24px 44px rgba(9,27,44,.24);
    }
    .workflow-item h3{
        margin:18px 0 10px;
        font:700 21px/1.12 "Space Grotesk","Manrope",sans-serif;
    }
    .workflow-item p{
        margin:0;
        color:rgba(244,248,251,.74);
        line-height:1.7;
    }

    .plans-head{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:16px;
        flex-wrap:wrap;
        margin-bottom:22px;
    }
    #planos > .container > .section-head{
        max-width:700px;
    }
    #planos > .container > .section-head h2{
        font-size:clamp(24px,2.7vw,36px);
        line-height:1.08;
    }
    .pricing-toggle{
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:8px;
        border-radius:999px;
        background:#fff;
        border:1px solid rgba(12,34,56,.1);
        box-shadow:0 12px 24px rgba(8,27,46,.08);
    }
    .pricing-toggle button{
        border:0;
        background:transparent;
        color:#4f6175;
        padding:10px 16px;
        border-radius:999px;
        font-weight:800;
        cursor:pointer;
    }
    .pricing-toggle button.is-active{
        background:#081b2e;
        color:#fff;
    }
    .pricing-hint{font-size:13px;color:#5c6b7c}
    .plan-card{
        position:relative;
        display:flex;
        flex-direction:column;
        gap:18px;
        height:100%;
        align-content:start;
        transition:transform .24s ease, box-shadow .24s ease;
    }
    .plan-card:hover{
        transform:translateY(-6px);
        box-shadow:0 30px 56px rgba(8,27,46,.18);
    }
    .plan-card.is-featured{
        background:linear-gradient(180deg,#fff7e7 0%, #ffffff 100%);
        border-color:rgba(255,199,90,.78);
    }
    .plan-card.is-recommended{
        background:linear-gradient(160deg,#081b2e 0%, #12324f 54%, #ff7a18 135%);
        border-color:rgba(255,190,92,.92);
        color:#eff7ff;
        transform:translateY(-10px);
        box-shadow:0 34px 64px rgba(8,27,46,.3);
    }
    .plan-card.is-recommended .plan-price strong,
    .plan-card.is-recommended h3,
    .plan-card.is-recommended .plan-meta-box strong{
        color:#fff;
    }
    .plan-card.is-recommended p,
    .plan-card.is-recommended .plan-price span,
    .plan-card.is-recommended .plan-disclaimer,
    .plan-card.is-recommended .plan-meta-box span{
        color:rgba(239,247,255,.78);
    }
    .plan-card.is-recommended .plan-meta-box{
        background:rgba(255,255,255,.1);
        border-color:rgba(255,255,255,.14);
    }
    .plan-card.is-recommended .btn-secondary{
        background:rgba(255,255,255,.12);
        border-color:rgba(255,255,255,.16);
        color:#fff;
    }
    .plan-card.is-recommended .plan-badge{
        background:#fff;
        color:#081b2e;
    }
    .plan-card.is-recommended .plan-features li{
        color:rgba(239,247,255,.86);
    }
    .plan-card.is-recommended .plan-annual-note{
        color:rgba(239,247,255,.86);
    }
    .plan-card.is-recommended .plan-features li::before{
        background:linear-gradient(135deg,#facc15,#fff7cc);
    }
    .plan-card .btn.is-disabled{
        opacity:.56;
        pointer-events:none;
        box-shadow:none;
    }
    .plan-badges{display:flex;gap:10px;flex-wrap:wrap;min-height:32px;align-items:flex-start}
    .plan-copy{display:grid;gap:10px;min-height:108px}
    .plan-card h3{
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }
    .plan-copy p{
        display:-webkit-box;
        -webkit-line-clamp:2;
        -webkit-box-orient:vertical;
        overflow:hidden;
    }
    .plan-price{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:10px;
        flex-wrap:nowrap;
    }
    .plan-price strong{
        font:700 40px/.95 "Space Grotesk","Manrope",sans-serif;
        letter-spacing:-.05em;
        color:#081b2e;
        white-space:nowrap;
    }
    .plan-price span{color:#5c6b7c;font-weight:700;white-space:nowrap}
    .plan-annual-note{
        font-size:12px;
        font-weight:800;
        line-height:1.4;
        color:#365067;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }
    .plan-meta{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:10px;
    }
    .plan-meta-box{
        padding:14px;
        border-radius:18px;
        background:#f6f8fb;
        border:1px solid rgba(12,34,56,.08);
    }
    .plan-meta-box span{
        display:block;
        font-size:11px;
        text-transform:uppercase;
        letter-spacing:.08em;
        color:#66788b;
    }
    .plan-meta-box strong{
        display:block;
        margin-top:8px;
        color:#0c2238;
        line-height:1.4;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }
    .plan-features{
        margin:0;
        padding:0;
        list-style:none;
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:8px;
    }
    .plan-features li{
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }
    .plan-actions{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:12px;
        margin-top:auto;
    }
    .plan-actions .btn{
        width:100%;
        min-width:0;
        padding-inline:14px;
        white-space:nowrap;
    }
    .plan-disclaimer{
        font-size:12px;
        color:#66788b;
        line-height:1.45;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
        margin-top:2px;
    }

    .blog-card a,
    .contact-card a,
    .footer-list a{
        color:#0b4b77;
        text-decoration:none;
        font-weight:800;
    }
    .blog-card a:hover,
    .contact-card a:hover,
    .footer-list a:hover{text-decoration:underline}

    .contact-card.is-dark{
        background:linear-gradient(150deg,#091b2c 0%, #143753 100%);
        color:#eff7fb;
    }
    .contact-card.is-dark h3{color:#fff}
    .contact-card.is-dark p{color:rgba(239,247,251,.74)}
    .contact-pillars{
        display:grid;
        gap:12px;
        margin-top:18px;
    }
    .contact-pillar{
        padding:16px;
        border-radius:18px;
        border:1px solid rgba(255,255,255,.12);
        background:rgba(255,255,255,.06);
    }
    .contact-pillar strong{display:block;color:#fff}
    .contact-pillar span{
        display:block;
        margin-top:6px;
        color:rgba(239,247,251,.72);
        line-height:1.6;
    }

    details.faq-item{
        padding:20px 22px;
        border-radius:20px;
        background:#fff;
        border:1px solid rgba(12,34,56,.1);
        box-shadow:0 14px 28px rgba(8,27,46,.06);
    }
    details.faq-item + details.faq-item{margin-top:12px}
    details.faq-item summary{
        cursor:pointer;
        list-style:none;
        font:700 18px/1.4 "Space Grotesk","Manrope",sans-serif;
        color:#081b2e;
    }
    details.faq-item summary::-webkit-details-marker{display:none}
    details.faq-item p{margin:14px 0 0;color:var(--muted);line-height:1.7}

    .site-footer{
        padding:40px 0 52px;
        background:#07141f;
        color:#dbe6ef;
        margin-top:32px;
    }
    .site-footer .brand-copy strong{color:#fff}
    .site-footer .brand-copy span{color:#9db1c2}
    .footer-title{
        display:block;
        margin-bottom:12px;
        font-size:12px;
        text-transform:uppercase;
        letter-spacing:.12em;
        color:#8da6bc;
        font-weight:800;
    }
    .site-footer p{margin:14px 0 0;color:#a9bccd;line-height:1.72}
    .footer-bottom{
        display:flex;
        justify-content:space-between;
        gap:16px;
        align-items:center;
        flex-wrap:wrap;
        padding-top:22px;
        margin-top:28px;
        border-top:1px solid rgba(255,255,255,.08);
        color:#8da6bc;
        font-size:13px;
    }

    @media (max-width:1120px){
        .hero-grid,
        .about-grid,
        .problems-layout,
        .solutions-layout,
        .contact-grid,
        .footer-grid{grid-template-columns:1fr}
        .features-hero{grid-template-columns:1fr}
        .about-highlights,
        .about-modules{grid-template-columns:repeat(2,minmax(0,1fr))}
        .problems-grid,
        .solutions-grid,
        .feature-grid,
        .plans-grid,
        .blog-grid,
        .workflow{grid-template-columns:repeat(2,minmax(0,1fr))}
        .hero-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}
    }

    @media (max-width:1080px){
        html{scroll-padding-top:96px}
        .page-shell{padding-top:12px}
        .site-header{
            position:sticky;
            top:12px;
            left:auto;
            right:auto;
            margin-bottom:14px;
        }
        .brand-mark img{
            height:50px;
            max-width:min(260px, 34vw);
        }
        .site-nav{
            position:absolute;
            top:100%;
            right:16px;
            left:auto;
            width:min(420px, calc(100vw - 32px));
            padding:16px;
            border-radius:24px;
            background:rgba(255,255,255,.96);
            border:1px solid rgba(8,27,46,.1);
            box-shadow:0 24px 48px rgba(8,27,46,.12);
            display:none;
            flex-direction:column;
            align-items:stretch;
            padding:16px;
        }
        .site-nav.is-open{display:flex}
        .site-nav a{padding:14px 16px;border-radius:16px}
        .menu-toggle{display:inline-flex;align-items:center;justify-content:center}
        .header-actions .btn-secondary{display:none}
        .site-header-inner{
            padding:14px 16px;
            border-radius:24px;
            background:rgba(255,255,255,.72);
            border-color:rgba(255,255,255,.84);
        }
        .hero-copy{padding:28px}
        .hero-copy h1{font-size:36px}
        .hero-copy-top{grid-template-columns:1fr}
    }

    @media (max-width:720px){
        .section{padding:72px 0}
        .container{width:min(calc(100% - 22px), var(--max))}
        .site-header{top:10px}
        .about-panel{padding:24px}
        .problems-panel{padding:24px}
        .solutions-panel{padding:24px}
        .features-hero{padding:24px}
        .feature-media{padding:18px 18px 14px}
        .content-card,
        .feature-card,
        .problem-card,
        .solution-card,
        .plan-card,
        .blog-card,
        .contact-card,
        .metric-card,
        .workflow-item{padding:22px}
        .hero-metrics,
        .about-highlights,
        .about-modules,
        .features-hero-points,
        .problems-grid,
        .solutions-grid,
        .feature-grid,
        .plans-grid,
        .blog-grid,
        .workflow,
        .form-grid,
        .plan-meta,
        .plan-features{grid-template-columns:1fr}
        .plans-head{align-items:flex-start}
        .plan-price strong{font-size:34px}
        .plan-actions{grid-template-columns:1fr}
        .hero-copy h1{font-size:30px}
        .hero-copy p{font-size:16px}
        .hero-actions .btn{width:100%}
        .features-hero-visual img{transform:none}
        .about-floating-tag{
            position:static;
            max-width:none;
            display:block;
        }
        .tablet-stage{
            gap:14px;
            padding:12px 0 0;
        }
        .tablet-stage img{transform:none}
    }
</style>

<div class="page-shell">
    <header class="site-header">
        <div class="container site-header-inner">
            <a class="brand" href="<?= htmlspecialchars(base_url('/')) ?>">
                <span class="brand-mark">
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Comanda360">
                </span>
            </a>

            <nav class="site-nav" data-site-nav>
                <?php foreach ($navigation as $item): ?>
                    <?php if (!is_array($item)): continue; endif; ?>
                    <a href="<?= htmlspecialchars((string) ($item['href'] ?? '#')) ?>"><?= htmlspecialchars((string) ($item['label'] ?? 'Menu')) ?></a>
                <?php endforeach; ?>
            </nav>

            <div class="header-actions">
                <a class="btn btn-secondary" href="<?= htmlspecialchars(base_url('/login')) ?>">Acessar agora</a>
                <button class="menu-toggle" type="button" aria-label="Abrir menu" data-menu-toggle>
                    <span>Menu</span>
                </button>
            </div>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="container hero-grid">
                <div class="hero-copy reveal is-visible">
                    <div class="hero-copy-top">
                        <div class="hero-kicker">
                            <span class="eyebrow">Comanda360 com foco em atracao, operacao e recorrencia</span>
                            <h1>Transforme vendas manuais em um fluxo digital que vende, cobra e escala.</h1>
                            <p>A Comanda360 posiciona sua operacao com mais clareza comercial, entrada de novos clientes, planos ativos prontos para vitrine publica, assinaturas recorrentes e pagamento via PIX ou cartao. Tudo em uma pagina preparada para SEO, indexacao no Google e conversao.</p>

                            <div class="hero-actions">
                                <a class="btn btn-primary" href="#planos">Ver planos ativos</a>
                                <a class="btn btn-ghost" href="#contato">Quero falar com o comercial</a>
                            </div>
                        </div>
                    </div>

                    <div class="hero-meta-row">
                        <div class="hero-metrics">
                            <?php foreach ($heroMetrics as $metric): ?>
                                <?php if (!is_array($metric)): continue; endif; ?>
                                <div class="hero-metric">
                                    <strong><?= htmlspecialchars((string) ($metric['value'] ?? '-')) ?></strong>
                                    <span><?= htmlspecialchars((string) ($metric['label'] ?? '')) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section" id="sobre">
            <div class="container">
                <div class="section-head reveal">
                    <span class="eyebrow">Sobre</span>
                    <h2>A Comanda360 foi desenhada para digitalizar atendimento, operacao e receita em um unico fluxo.</h2>
                </div>

                <div class="about-grid">
                    <article class="about-panel about-story reveal">
                        <div class="about-lead">
                            <span class="about-kicker">Plataforma operacional Comanda360</span>
                            <h3>O valor da Comanda360 nao esta em um modulo isolado, mas na integracao entre atendimento, operacao e gestao.</h3>
                            <p>Na pratica, a plataforma conecta cardapio digital, pedidos por QR Code, comandas, cozinha, caixa, entregas, estoque, usuarios, suporte e assinaturas recorrentes em uma arquitetura multiempresa preparada para uso diario.</p>
                            <p>Isso reduz o problema mais comum desse mercado: sistemas e processos soltos demais para quem precisa vender rapido, operar com menos erro e manter leitura gerencial confiavel.</p>
                        </div>

                        <div class="about-highlights">
                            <?php foreach ($aboutHighlights as $highlight): ?>
                                <?php if (!is_array($highlight)): continue; endif; ?>
                                <div class="about-highlight">
                                    <strong><?= htmlspecialchars((string) ($highlight['value'] ?? '')) ?></strong>
                                    <span><?= htmlspecialchars((string) ($highlight['label'] ?? '')) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="about-capabilities">
                            <?php foreach ($aboutCapabilities as $capability): ?>
                                <span><?= htmlspecialchars((string) $capability) ?></span>
                            <?php endforeach; ?>
                        </div>

                        <div class="about-modules">
                            <?php foreach ($aboutModules as $module): ?>
                                <?php if (!is_array($module)): continue; endif; ?>
                                <article class="about-module">
                                    <span><?= htmlspecialchars((string) ($module['eyebrow'] ?? '')) ?></span>
                                    <strong><?= htmlspecialchars((string) ($module['title'] ?? 'Modulo')) ?></strong>
                                    <p><?= htmlspecialchars((string) ($module['description'] ?? '')) ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <aside class="about-panel about-visual reveal">
                        <div class="about-visual-copy">
                            <span class="about-kicker is-contrast">Painel em tablet</span>
                            <h3>Uma interface pensada para uso real em operacao, gestao e acompanhamento institucional.</h3>
                            <p>A imagem abaixo usa o painel do proprio sistema em formato tablet para mostrar um ponto importante: a Comanda360 nao depende de uma unica tela. Ela foi estruturada para funcionar com clareza em contextos moveis, administrativos e operacionais.</p>
                        </div>

                        <div class="tablet-stage">
                            <span class="about-floating-tag tag-top">Dashboard, usuarios, suporte e assinatura no mesmo ecossistema</span>
                            <img src="<?= htmlspecialchars($aboutPanelImageUrl) ?>" alt="Painel da Comanda360 exibido em um tablet">
                            <span class="about-floating-tag tag-bottom">Leitura confortavel para salao, caixa e acompanhamento gerencial em rotina de campo</span>
                        </div>

                        <div class="about-visual-footer">
                            <div class="about-footer-card">
                                <strong>Produto com camadas bem separadas</strong>
                                <span>Cliente final, equipe operacional, administracao do estabelecimento e area global da Comanda360 convivem no mesmo produto sem misturar contexto nem permissao.</span>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </section>

        <section class="section" id="problemas">
            <div class="container">
                <div class="section-head reveal">
                    <span class="eyebrow">Problemas das empresas</span>
                    <h2>Quem vende no manual normalmente perde margem antes mesmo de perceber.</h2>
                    <p>O custo nao aparece so no caixa. Ele aparece em pedido errado, atraso, falta de visibilidade, cobranca desorganizada e marketing que atrai curiosos em vez de compradores.</p>
                </div>

                <div class="problems-layout">
                    <aside class="problems-panel reveal">
                        <span class="problem-panel-badge">Leitura executiva</span>
                        <h3>O prejuizo comeca muito antes do fechamento do caixa.</h3>
                        <p>Quando a operacao depende de memoria, improviso e comunicacao manual, o negocio perde velocidade, margem e previsibilidade ao mesmo tempo. O problema nao fica em um setor so.</p>

                        <div class="problems-panel-list">
                            <div>
                                <strong>Operacao fragil</strong>
                                <span>Pedido errado, atraso no atendimento e retrabalho viram rotina quando o fluxo nao esta organizado em um sistema.</span>
                            </div>
                            <div>
                                <strong>Financeiro reativo</strong>
                                <span>Cobranca sem processo e sem leitura clara de receita gera atraso, mistura caixa com operacao e reduz previsibilidade.</span>
                            </div>
                            <div>
                                <strong>Crescimento sem consistencia</strong>
                                <span>Marketing sozinho nao sustenta resultado quando a pagina publica nao converte e a equipe nao consegue absorver a demanda.</span>
                            </div>
                        </div>
                    </aside>

                    <div class="problems-grid">
                        <?php foreach ($problemPoints as $index => $problem): ?>
                            <?php if (!is_array($problem)): continue; endif; ?>
                            <article class="problem-card reveal">
                                <span class="problem-index"><?= htmlspecialchars(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                                <h3><?= htmlspecialchars((string) ($problem['title'] ?? 'Problema')) ?></h3>
                                <p><?= htmlspecialchars((string) ($problem['description'] ?? '')) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="section" id="solucoes">
            <div class="container">
                <div class="section-head reveal">
                    <span class="eyebrow">Solucoes</span>
                    <h2>Menos erro, mais controle e uma operacao pronta para vender melhor.</h2>
                    <p>A Comanda360 foi pensada para transformar atendimento, comandas, pagamentos e entrada comercial em um fluxo mais rapido, organizado e lucrativo para a empresa.</p>
                </div>

                <div class="solutions-layout">
                    <aside class="solutions-panel reveal">
                        <span class="solutions-panel-badge">Resposta estrutural</span>
                        <h3>A Comanda360 entra para conectar operacao, pagamento e crescimento.</h3>
                        <p>A proposta nao e colocar mais uma camada de improviso em cima da rotina da empresa. E transformar atendimento, comandas, caixa e captacao comercial em um fluxo mais previsivel, legivel e escalavel.</p>

                        <div class="solutions-panel-list">
                            <div>
                                <strong>Menos ruído operacional</strong>
                                <span>Pedidos, comandas e mesas deixam de depender de anotacao dispersa e passam a seguir um processo mais claro para equipe e cliente.</span>
                            </div>
                            <div>
                                <strong>Mais controle no fechamento</strong>
                                <span>Consumo, pagamento e caixa ficam melhor conectados, reduzindo erro de conferencia, atraso no fechamento e divergencia financeira.</span>
                            </div>
                            <div>
                                <strong>Venda com mais coerencia</strong>
                                <span>A pagina publica passa a vender exatamente o que o produto resolve, melhorando a leitura comercial e a entrada de leads.</span>
                            </div>
                        </div>
                    </aside>

                    <div class="solutions-grid">
                        <?php foreach ($solutions as $solution): ?>
                            <?php if (!is_array($solution)): continue; endif; ?>
                            <article class="solution-card reveal">
                                <span class="solution-eyebrow"><?= htmlspecialchars((string) ($solution['eyebrow'] ?? '')) ?></span>
                                <h3><?= htmlspecialchars((string) ($solution['title'] ?? 'Solucao')) ?></h3>
                                <p><?= htmlspecialchars((string) ($solution['description'] ?? '')) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="workflow">
                    <?php foreach ($workflow as $item): ?>
                        <?php if (!is_array($item)): continue; endif; ?>
                        <article class="workflow-item reveal">
                            <span class="workflow-step"><?= htmlspecialchars((string) ($item['step'] ?? '--')) ?></span>
                            <h3><?= htmlspecialchars((string) ($item['title'] ?? 'Etapa')) ?></h3>
                            <p><?= htmlspecialchars((string) ($item['description'] ?? '')) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="section" id="funcionalidades">
            <div class="container">
                <div class="section-head reveal">
                    <span class="eyebrow">Funcionalidades</span>
                    <h2>Da entrada do cliente ao fechamento, a Comanda360 organiza a rotina em etapas claras.</h2>
                    <p>Veja como a Comanda360 ajuda a acelerar atendimento, vender com mais clareza, fechar com menos erro e dar mais controle para a empresa no dia a dia.</p>
                </div>

                <div class="features-showcase">
                    <article class="features-hero reveal">
                        <div class="features-hero-copy">
                            <span class="features-hero-badge">Fluxo funcional Comanda360</span>
                            <h3>Uma jornada pensada para vender mais, atender melhor e fechar com mais seguranca.</h3>
                            <p>A Comanda360 conecta o que normalmente fica solto na rotina da empresa: entrada do cliente, escolha do pedido, registro de consumo, fechamento financeiro e leitura gerencial da operacao.</p>

                            <div class="features-hero-points">
                                <div class="features-hero-point">
                                    <strong>Cliente</strong>
                                    <span>QR Code, cardapio e pedido no celular com mais autonomia.</span>
                                </div>
                                <div class="features-hero-point">
                                    <strong>Equipe</strong>
                                    <span>Comandas, caixa e atendimento em um fluxo mais claro para a rotina.</span>
                                </div>
                                <div class="features-hero-point">
                                    <strong>Gestao</strong>
                                    <span>Indicadores e visibilidade para decidir com mais base real.</span>
                                </div>
                            </div>
                        </div>

                        <div class="features-hero-visual">
                            <img src="<?= htmlspecialchars($featuresPanelImageUrl) ?>" alt="Painel principal da Comanda360">
                        </div>
                    </article>

                    <div class="feature-grid">
                        <?php foreach ($featureGroups as $group): ?>
                            <?php if (!is_array($group)): continue; endif; ?>
                            <article class="feature-card reveal">
                                <div class="feature-card-head">
                                    <span class="feature-step"><?= htmlspecialchars((string) ($group['step'] ?? '--')) ?></span>
                                    <span class="feature-chip"><?= htmlspecialchars((string) ($group['eyebrow'] ?? ($group['title'] ?? 'Grupo'))) ?></span>
                                </div>

                                <div class="feature-card-copy">
                                    <h3><?= htmlspecialchars((string) ($group['title'] ?? 'Funcionalidade')) ?></h3>
                                    <p><?= htmlspecialchars((string) ($group['description'] ?? '')) ?></p>
                                </div>

                                <?php $featureImage = trim((string) ($group['image'] ?? '')); ?>
                                <?php $featureImageUrl = $featureImage !== '' ? public_embedded_image_url($featureImage) : ''; ?>
                                <?php if ($featureImageUrl !== ''): ?>
                                    <div class="feature-media">
                                        <img src="<?= htmlspecialchars($featureImageUrl) ?>" alt="<?= htmlspecialchars((string) ($group['image_alt'] ?? ($group['title'] ?? 'Funcionalidade Comanda360'))) ?>">
                                    </div>
                                <?php endif; ?>

                                <ul>
                                    <?php foreach ((array) ($group['items'] ?? []) as $item): ?>
                                        <li><?= htmlspecialchars((string) $item) ?></li>
                                    <?php endforeach; ?>
                                </ul>

                                <?php $featureResult = trim((string) ($group['result'] ?? '')); ?>
                                <?php if ($featureResult !== ''): ?>
                                    <div class="feature-result"><?= htmlspecialchars($featureResult) ?></div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="section" id="planos">
            <div class="container">
                <div class="section-head reveal">
                    <span class="eyebrow">Planos</span>
                    <h2>Os planos desta pagina seguem o cadastro ativo da Comanda360.</h2>
                </div>

                <div class="plans-head reveal">
                    <div>
                        <strong style="display:block;font:700 22px/1.1 'Space Grotesk','Manrope',sans-serif;color:#081b2e">Catalogo comercial ativo</strong>
                        <span class="pricing-hint"><?= htmlspecialchars((string) ($plansStats['total_active'] ?? 0)) ?> planos ativos, <?= htmlspecialchars((string) ($plansStats['featured'] ?? 0)) ?> destaques e <?= htmlspecialchars((string) ($plansStats['recommended'] ?? 0)) ?> recomendados publicados.</span>
                    </div>
                    <div class="pricing-toggle" data-pricing-toggle>
                        <button class="is-active" type="button" data-cycle="mensal">Mensal</button>
                        <button type="button" data-cycle="anual">Anual</button>
                    </div>
                </div>

                <?php if ($plans === []): ?>
                    <article class="content-card reveal">
                        <h3>Nenhum plano publico disponivel</h3>
                        <p>O catalogo ainda nao possui planos ativos para exibicao. A proxima acao correta e ativar os planos no painel Comanda360 antes de escalar trafego para esta pagina.</p>
                    </article>
                <?php else: ?>
                    <div class="plans-grid">
                        <?php foreach ($plans as $plan): ?>
                            <?php if (!is_array($plan)): continue; endif; ?>
                            <?php
                            $planName = (string) ($plan['name'] ?? 'Plano');
                            $planDescription = trim((string) ($plan['description'] ?? ''));
                            $priceMonthly = isset($plan['price_monthly']) ? (float) $plan['price_monthly'] : 0.0;
                            $priceYearly = $plan['price_yearly'] !== null ? (float) $plan['price_yearly'] : null;
                            $priceYearlyBase = isset($plan['price_yearly_base']) ? (float) $plan['price_yearly_base'] : ($priceMonthly * 12);
                            $yearlyDiscountPercent = (float) ($plan['price_yearly_discount_percent'] ?? 0);
                            ?>
                            <article class="plan-card reveal<?= !empty($plan['is_featured']) ? ' is-featured' : '' ?><?= !empty($plan['is_recommended']) ? ' is-recommended' : '' ?>" data-plan-card data-plan-slug="<?= htmlspecialchars((string) ($plan['slug'] ?? '')) ?>" data-monthly="<?= htmlspecialchars(number_format($priceMonthly, 2, '.', '')) ?>" data-yearly="<?= htmlspecialchars($priceYearly !== null ? number_format($priceYearly, 2, '.', '') : '') ?>" data-yearly-base="<?= htmlspecialchars(number_format($priceYearlyBase, 2, '.', '')) ?>" data-yearly-discount="<?= htmlspecialchars((string) $yearlyDiscountPercent) ?>">
                                <div class="plan-badges">
                                    <?php if (!empty($plan['is_recommended'])): ?>
                                        <span class="plan-badge">Plano recomendado</span>
                                    <?php endif; ?>
                                </div>
                                <div class="plan-copy">
                                    <h3><?= htmlspecialchars($planName) ?></h3>
                                    <p><?= htmlspecialchars($planDescription !== '' ? $planDescription : 'Plano comercial ativo para operacao da Comanda360 com cobranca recorrente.') ?></p>
                                </div>

                                <div class="plan-price">
                                    <strong data-plan-price><?= htmlspecialchars($formatMoney($priceMonthly)) ?></strong>
                                    <span data-plan-cycle>/ mensal</span>
                                </div>
                                <div class="plan-annual-note" data-plan-note>
                                    Anual: <?= htmlspecialchars($formatMoney($priceYearly)) ?> com <?= htmlspecialchars($formatPercent($yearlyDiscountPercent)) ?>% OFF
                                </div>

                                <div class="plan-meta">
                                    <div class="plan-meta-box">
                                        <span>Usuarios</span>
                                        <strong title="<?= htmlspecialchars($formatLimit($plan['max_users'] ?? null, 'usuarios')) ?>"><?= htmlspecialchars($formatLimitValue($plan['max_users'] ?? null)) ?></strong>
                                    </div>
                                    <div class="plan-meta-box">
                                        <span>Produtos</span>
                                        <strong title="<?= htmlspecialchars($formatLimit($plan['max_products'] ?? null, 'produtos')) ?>"><?= htmlspecialchars($formatLimitValue($plan['max_products'] ?? null)) ?></strong>
                                    </div>
                                    <div class="plan-meta-box">
                                        <span>Mesas</span>
                                        <strong title="<?= htmlspecialchars($formatLimit($plan['max_tables'] ?? null, 'mesas')) ?>"><?= htmlspecialchars($formatLimitValue($plan['max_tables'] ?? null)) ?></strong>
                                    </div>
                                </div>

                                <?php if (!empty($plan['feature_labels'])): ?>
                                    <ul class="plan-features">
                                        <?php foreach ((array) ($plan['feature_labels'] ?? []) as $featureLabel): ?>
                                            <li title="<?= htmlspecialchars((string) $featureLabel) ?>"><?= htmlspecialchars((string) $featureLabel) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <div class="plan-actions">
                                    <a class="btn btn-primary" href="<?= htmlspecialchars(base_url('/cadastro/empresa?plano=' . rawurlencode((string) ($plan['slug'] ?? '')) . '&ciclo=mensal')) ?>" data-plan-signup>Quero este plano</a>
                                    <a class="btn btn-secondary" href="<?= htmlspecialchars(base_url('/login')) ?>">Ja sou cliente</a>
                                </div>

                                <div class="plan-disclaimer">
                                    Pagamento recorrente com suporte a PIX e cartao.
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="section" id="blog">
            <div class="container">
                <div class="section-head reveal">
                    <span class="eyebrow">Blog</span>
                    <h2>Conteudo para ampliar autoridade, indexacao e argumentos de venda.</h2>
                    <p>O blog entra como ativo de topo e meio de funil. Mesmo antes de um modulo editorial completo, a landing ja pode sinalizar pauta, posicionamento e temas com forte busca comercial.</p>
                </div>

                <div class="blog-grid">
                    <?php foreach ($blogArticles as $article): ?>
                        <?php if (!is_array($article)): continue; endif; ?>
                        <article class="blog-card reveal">
                            <span class="blog-category"><?= htmlspecialchars((string) ($article['category'] ?? 'Conteudo')) ?></span>
                            <h3 style="margin-top:18px"><?= htmlspecialchars((string) ($article['title'] ?? 'Artigo')) ?></h3>
                            <p><?= htmlspecialchars((string) ($article['excerpt'] ?? '')) ?></p>
                            <p style="margin-top:16px"><a href="#contato">Quero receber essa pauta no comercial</a></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="section" id="contato">
            <div class="container">
                <div class="section-head reveal">
                    <span class="eyebrow">Contato</span>
                    <h2>Capte leads com contexto comercial, nao apenas mensagens soltas.</h2>
                    <p>O formulario abaixo registra interesse diretamente no ambiente do projeto e preserva dados de origem. Isso melhora resposta comercial, leitura de campanha e prioridade de follow-up.</p>
                </div>

                <div class="contact-grid">
                    <article class="contact-card reveal">
                        <h3>Solicitar contato comercial</h3>
                        <p>Preencha o essencial. Nome, e-mail, empresa, plano de interesse e mensagem curta ja bastam para qualificar a abordagem inicial.</p>

                        <form method="POST" action="<?= htmlspecialchars(base_url('/contact')) ?>" style="margin-top:20px">
                            <?= form_security_fields('marketing.contact.store') ?>
                            <input type="hidden" name="source_url" value="<?= htmlspecialchars($currentUrl) ?>" data-source-url>
                            <input type="hidden" name="utm_source" value="">
                            <input type="hidden" name="utm_medium" value="">
                            <input type="hidden" name="utm_campaign" value="">
                            <input type="hidden" name="utm_term" value="">
                            <input type="hidden" name="utm_content" value="">

                            <div class="form-grid">
                                <div class="field">
                                    <label for="lead_name">Nome</label>
                                    <input id="lead_name" name="name" type="text" required placeholder="Seu nome">
                                </div>
                                <div class="field">
                                    <label for="lead_email">E-mail</label>
                                    <input id="lead_email" name="email" type="email" required placeholder="voce@empresa.com.br">
                                </div>
                                <div class="field">
                                    <label for="lead_company">Empresa</label>
                                    <input id="lead_company" name="company" type="text" placeholder="Nome da empresa">
                                </div>
                                <div class="field">
                                    <label for="lead_phone">Telefone</label>
                                    <input id="lead_phone" name="phone" type="text" placeholder="WhatsApp ou telefone">
                                </div>
                                <div class="field">
                                    <label for="lead_plan_interest">Plano de interesse</label>
                                    <select id="lead_plan_interest" name="plan_interest">
                                        <option value="">Selecionar depois</option>
                                        <?php foreach ($plans as $plan): ?>
                                            <?php if (!is_array($plan)): continue; endif; ?>
                                            <option value="<?= htmlspecialchars((string) ($plan['name'] ?? 'Plano')) ?>"><?= htmlspecialchars((string) ($plan['name'] ?? 'Plano')) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="billing_cycle_interest">Ciclo desejado</label>
                                    <select id="billing_cycle_interest" name="billing_cycle_interest">
                                        <option value="">Definir depois</option>
                                        <option value="mensal">Mensal</option>
                                        <option value="anual">Anual</option>
                                    </select>
                                </div>
                                <div class="field full">
                                    <label for="lead_message">Mensagem</label>
                                    <textarea id="lead_message" name="message" placeholder="Descreva rapidamente a operacao, o momento comercial e o que voce quer estruturar."></textarea>
                                </div>
                            </div>

                            <button class="btn btn-primary" type="submit" style="margin-top:18px">Registrar interesse</button>
                        </form>
                    </article>

                    <aside class="contact-card is-dark reveal">
                        <h3>O que essa pagina ja sustenta</h3>
                        <p>Mais do que apresentacao. Ela sustenta descoberta, avaliacao, comparacao e entrada comercial com narrativa coerente para a Comanda360.</p>

                        <div class="contact-pillars">
                            <div class="contact-pillar">
                                <strong>Publicidade digital</strong>
                                <span>Estrutura pronta para anuncios, campanhas sazonais e paginas com CTA forte por secao.</span>
                            </div>
                            <div class="contact-pillar">
                                <strong>SEO e indexacao</strong>
                                <span>Conteudo semantico, FAQ e termos comerciais relevantes para ampliar leitura do Google.</span>
                            </div>
                            <div class="contact-pillar">
                                <strong>Operacao comercial</strong>
                                <span>Planos ativos, acesso a plataforma, fluxo de assinatura e captura de lead no mesmo ambiente.</span>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="container">
                <div class="section-head reveal">
                    <span class="eyebrow">FAQ</span>
                    <h2>Perguntas que reduzem friccao antes do contato comercial.</h2>
                    <p>FAQ nao e detalhe de rodape. Ele ajuda SEO, elimina duvida repetida e melhora a taxa de clique em trafego qualificado.</p>
                </div>

                <?php foreach ($faqItems as $faq): ?>
                    <?php if (!is_array($faq)): continue; endif; ?>
                    <details class="faq-item reveal">
                        <summary><?= htmlspecialchars((string) ($faq['question'] ?? 'Pergunta')) ?></summary>
                        <p><?= htmlspecialchars((string) ($faq['answer'] ?? '')) ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <a class="brand" href="<?= htmlspecialchars(base_url('/')) ?>">
                        <span class="brand-mark">
                            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Comanda360">
                        </span>
                    </a>
                    <p>Landing publica desenhada para atrair novos clientes, sustentar indexacao e transformar visitas em operacoes comerciais rastreaveis.</p>
                </div>

                <div>
                    <span class="footer-title">Navegacao</span>
                    <ul class="footer-list">
                        <?php foreach ($navigation as $item): ?>
                            <?php if (!is_array($item)): continue; endif; ?>
                            <li><a href="<?= htmlspecialchars((string) ($item['href'] ?? '#')) ?>"><?= htmlspecialchars((string) ($item['label'] ?? 'Link')) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div>
                    <span class="footer-title">Comercial</span>
                    <ul class="footer-list">
                        <li><a href="#planos">Planos ativos</a></li>
                        <li><a href="#contato">Solicitar contato</a></li>
                        <li><a href="<?= htmlspecialchars(base_url('/login')) ?>">Acesso a plataforma</a></li>
                    </ul>
                </div>

                <div>
                    <span class="footer-title">Crescimento</span>
                    <ul class="footer-list">
                        <li><a href="#blog">Conteudo e blog</a></li>
                        <li><a href="#problemas">Dores do mercado</a></li>
                        <li><a href="#solucoes">Solucoes do produto</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <span><?= htmlspecialchars((string) ($seo['title'] ?? 'Comanda360')) ?></span>
                <span>Pagina publica modular para SEO, publicidade digital e captacao de novos clientes.</span>
            </div>
        </div>
    </footer>
</div>

<script>
(() => {
    const header = document.querySelector('.site-header');
    const nav = document.querySelector('[data-site-nav]');
    const toggle = document.querySelector('[data-menu-toggle]');
    const revealItems = Array.from(document.querySelectorAll('.reveal'));
    const pricingToggle = document.querySelector('[data-pricing-toggle]');
    const planCards = Array.from(document.querySelectorAll('[data-plan-card]'));

    const syncHeaderState = () => {
        if (!(header instanceof HTMLElement)) {
            return;
        }

        const shouldElevate = window.scrollY > 28;
        header.classList.toggle('is-scrolled', shouldElevate);
    };

    if (toggle instanceof HTMLButtonElement && nav instanceof HTMLElement) {
        toggle.addEventListener('click', () => {
            nav.classList.toggle('is-open');
        });

        nav.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => nav.classList.remove('is-open'));
        });
    }

    syncHeaderState();
    window.addEventListener('scroll', syncHeaderState, { passive: true });

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.18 });

        revealItems.forEach((item) => {
            if (!item.classList.contains('is-visible')) {
                observer.observe(item);
            }
        });
    } else {
        revealItems.forEach((item) => item.classList.add('is-visible'));
    }

    const formatMoney = (amount) => {
        if (amount === null || amount === '') {
            return 'Sob consulta';
        }

        const parsed = Number(amount);
        if (!Number.isFinite(parsed)) {
            return 'Sob consulta';
        }

        return parsed.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    };

    const formatDiscount = (amount) => {
        const parsed = Number(amount || 0);
        if (!Number.isFinite(parsed)) {
            return '0';
        }

        return parsed.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    };

    const updatePrices = (cycle) => {
        planCards.forEach((card) => {
            const priceNode = card.querySelector('[data-plan-price]');
            const cycleNode = card.querySelector('[data-plan-cycle]');
            const noteNode = card.querySelector('[data-plan-note]');
            const signupNode = card.querySelector('[data-plan-signup]');
            if (!(priceNode instanceof HTMLElement) || !(cycleNode instanceof HTMLElement) || !(noteNode instanceof HTMLElement)) {
                return;
            }

            const monthly = card.getAttribute('data-monthly') || '';
            const yearly = card.getAttribute('data-yearly') || '';
            const yearlyBase = card.getAttribute('data-yearly-base') || '';
            const yearlyDiscount = card.getAttribute('data-yearly-discount') || '0';
            const planSlug = card.getAttribute('data-plan-slug') || '';
            const useYearly = cycle === 'anual' && yearly !== '';

            priceNode.textContent = formatMoney(useYearly ? yearly : monthly);
            cycleNode.textContent = useYearly ? '/ anual' : (cycle === 'anual' ? '/ anual sob consulta' : '/ mensal');

            if (useYearly) {
                noteNode.textContent = `Economia de ${formatDiscount(yearlyDiscount)}% sobre ${formatMoney(yearlyBase)} no plano anual`;
                return;
            }

            noteNode.textContent = `Anual: ${formatMoney(yearly)} com ${formatDiscount(yearlyDiscount)}% OFF`;

            if (signupNode instanceof HTMLAnchorElement) {
                if (cycle === 'anual' && yearly === '') {
                    signupNode.classList.add('is-disabled');
                    signupNode.href = '#planos';
                    signupNode.textContent = 'Anual indisponivel';
                } else {
                    signupNode.classList.remove('is-disabled');
                    signupNode.href = `${<?= json_encode(base_url('/cadastro/empresa')) ?>}?plano=${encodeURIComponent(planSlug)}&ciclo=${encodeURIComponent(useYearly ? 'anual' : 'mensal')}`;
                    signupNode.textContent = 'Quero este plano';
                }
            }
        });
    };

    if (pricingToggle instanceof HTMLElement) {
        pricingToggle.querySelectorAll('button[data-cycle]').forEach((button) => {
            button.addEventListener('click', () => {
                pricingToggle.querySelectorAll('button[data-cycle]').forEach((node) => node.classList.remove('is-active'));
                button.classList.add('is-active');
                updatePrices(button.getAttribute('data-cycle') || 'mensal');
            });
        });
    }

    updatePrices('mensal');

    const params = new URLSearchParams(window.location.search);
    ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'].forEach((key) => {
        const field = document.querySelector(`input[name="${key}"]`);
        if (field instanceof HTMLInputElement) {
            field.value = params.get(key) || '';
        }
    });

    const sourceField = document.querySelector('[data-source-url]');
    if (sourceField instanceof HTMLInputElement) {
        sourceField.value = window.location.href;
    }

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
        const submitter = event.submitter instanceof HTMLElement ? event.submitter : form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitter instanceof HTMLButtonElement) {
            submitter.disabled = true;
            submitter.textContent = 'Processando...';
        } else if (submitter instanceof HTMLInputElement) {
            submitter.disabled = true;
            submitter.value = 'Processando...';
        }
    });
})();
</script>
