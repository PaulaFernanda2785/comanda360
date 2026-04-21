<?php
$landingPage = is_array($landingPage ?? null) ? $landingPage : [];
$seo = is_array($seo ?? null) ? $seo : [];
$navigation = is_array($landingPage['navigation'] ?? null) ? $landingPage['navigation'] : [];
$heroMetrics = is_array($landingPage['hero_metrics'] ?? null) ? $landingPage['hero_metrics'] : [];
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
    html{scroll-behavior:smooth}
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
    .page-shell{position:relative;overflow:hidden}
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
        position:sticky;
        top:0;
        z-index:20;
        padding:18px 0;
        backdrop-filter:blur(18px);
        background:rgba(244,239,231,.68);
        border-bottom:1px solid rgba(16,34,53,.08);
    }
    .site-header-inner{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:16px;
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
        grid-template-columns:minmax(0,1.2fr) minmax(340px,.82fr);
        gap:24px;
        align-items:stretch;
    }
    .hero-copy{
        position:relative;
        padding:38px;
        min-height:100%;
        border-radius:var(--radius-xl);
        background:
            linear-gradient(140deg, rgba(6,19,32,.98) 0%, rgba(13,48,74,.96) 48%, rgba(14,165,164,.92) 100%);
        color:#f7fbff;
        box-shadow:0 36px 72px rgba(4,17,29,.26);
        overflow:hidden;
    }
    .hero-copy::before,
    .hero-copy::after{
        content:"";
        position:absolute;
        border-radius:999px;
        background:rgba(255,255,255,.08);
    }
    .hero-copy::before{width:260px;height:260px;top:-60px;right:-50px}
    .hero-copy::after{width:180px;height:180px;bottom:-60px;left:-40px}
    .hero-copy > *{position:relative;z-index:1}
    .hero-copy h1{
        margin:18px 0 18px;
        font:700 clamp(38px,5vw,72px)/.95 "Space Grotesk","Manrope",sans-serif;
        letter-spacing:-.06em;
        max-width:760px;
    }
    .hero-copy p{
        max-width:700px;
        margin:0;
        font-size:19px;
        line-height:1.72;
        color:rgba(240,245,250,.86);
    }
    .hero-actions{
        display:flex;
        gap:14px;
        flex-wrap:wrap;
        margin:28px 0 32px;
    }
    .hero-metrics{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:12px;
    }
    .hero-metric{
        padding:16px;
        border-radius:18px;
        background:rgba(255,255,255,.08);
        border:1px solid rgba(255,255,255,.12);
        backdrop-filter:blur(10px);
    }
    .hero-metric strong{
        display:block;
        font:700 22px/1 "Space Grotesk","Manrope",sans-serif;
        color:#fff;
    }
    .hero-metric span{
        display:block;
        margin-top:8px;
        font-size:13px;
        line-height:1.45;
        color:rgba(240,245,250,.72);
    }

    .panel{
        padding:28px;
        border-radius:var(--radius-xl);
        background:rgba(255,255,255,.86);
        border:1px solid rgba(255,255,255,.94);
        box-shadow:var(--shadow);
        backdrop-filter:blur(18px);
    }
    .login-panel{
        display:grid;
        gap:18px;
        align-self:start;
    }
    .panel-header h2,
    .panel-header h3{
        margin:0;
        font:700 30px/1.02 "Space Grotesk","Manrope",sans-serif;
        letter-spacing:-.04em;
        color:#081b2e;
    }
    .panel-header p{
        margin:10px 0 0;
        color:var(--muted);
        line-height:1.65;
    }
    .flash-stack{display:grid;gap:10px}
    .flash{
        padding:14px 16px;
        border-radius:16px;
        font-size:14px;
        line-height:1.5;
        border:1px solid transparent;
    }
    .flash.success{background:#ecfdf3;color:#0f6b46;border-color:#b7ebcf}
    .flash.error{background:#fff0ef;color:#9c2a10;border-color:#f7c2b7}

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
    .helper-list{display:grid;gap:10px;margin:0;padding:0;list-style:none}
    .helper-list li{
        padding:12px 14px;
        border-radius:16px;
        background:#f6f8fb;
        color:#365067;
        line-height:1.5;
        border:1px solid rgba(12,34,56,.08);
    }

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
    .about-grid{grid-template-columns:minmax(0,1.1fr) minmax(320px,.9fr);align-items:start}
    .problems-grid{grid-template-columns:repeat(4,minmax(0,1fr))}
    .solutions-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
    .feature-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
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
    .problem-card{
        background:linear-gradient(180deg,#fff7f2 0%, #ffffff 100%);
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
    .solution-card{
        background:linear-gradient(180deg,#eefbf9 0%, #ffffff 100%);
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
    .blog-category{background:#edf2ff;color:#21358a}
    .feature-chip{background:#eef4fb;color:#19344f}
    .plan-badge{background:#fff4d4;color:#8b5c00;white-space:nowrap}

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
        .contact-grid,
        .footer-grid{grid-template-columns:1fr}
        .problems-grid,
        .solutions-grid,
        .feature-grid,
        .plans-grid,
        .blog-grid,
        .workflow{grid-template-columns:repeat(2,minmax(0,1fr))}
        .hero-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}
    }

    @media (max-width:860px){
        .site-nav{
            position:absolute;
            top:100%;
            left:16px;
            right:16px;
            padding:16px;
            border-radius:24px;
            background:rgba(255,255,255,.96);
            border:1px solid rgba(8,27,46,.1);
            box-shadow:0 24px 48px rgba(8,27,46,.12);
            display:none;
            flex-direction:column;
            align-items:stretch;
        }
        .site-nav.is-open{display:flex}
        .site-nav a{padding:14px 16px;border-radius:16px}
        .menu-toggle{display:inline-flex;align-items:center;justify-content:center}
        .header-actions .btn-secondary{display:none}
        .hero-copy{padding:28px}
        .hero-copy h1{font-size:42px}
    }

    @media (max-width:720px){
        .section{padding:72px 0}
        .container{width:min(calc(100% - 22px), var(--max))}
        .panel,
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
                    <span class="eyebrow">SaaS com foco em atracao, operacao e recorrencia</span>
                    <h1>Transforme vendas manuais em um fluxo digital que vende, cobra e escala.</h1>
                    <p>O Comanda360 posiciona sua operacao com mais clareza comercial, entrada de novos clientes, planos ativos prontos para vitrine publica, assinaturas recorrentes e pagamento via PIX ou cartao. Tudo em uma pagina preparada para SEO, indexacao no Google e conversao.</p>

                    <div class="hero-actions">
                        <a class="btn btn-primary" href="#planos">Ver planos ativos</a>
                        <a class="btn btn-ghost" href="#contato">Quero falar com o comercial</a>
                    </div>

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

                <aside class="panel login-panel reveal">
                    <div class="panel-header">
                        <span class="eyebrow">Clientes ja ativos</span>
                        <h2>Acesso separado da pagina comercial</h2>
                        <p>Quem ja tem empresa liberada ou usuario interno agora entra por uma tela exclusiva. A landing publica fica focada em conversao, nao em autenticacao.</p>
                    </div>

                    <a class="btn btn-primary" href="<?= htmlspecialchars(base_url('/login')) ?>" style="width:100%">Ir para a tela de login</a>

                    <ul class="helper-list">
                        <li>Clientes novos continuam entrando pelo fluxo de plano, cadastro e pagamento.</li>
                        <li>Usuarios operacionais e SaaS acessam a plataforma pela nova pagina dedicada.</li>
                        <li>Separacao de acessos por contexto: empresa, operacao e ambiente SaaS.</li>
                    </ul>
                </aside>
            </div>
        </section>

        <section class="section" id="sobre">
            <div class="container">
                <div class="section-head reveal">
                    <span class="eyebrow">Sobre</span>
                    <h2>Uma pagina publica que nao so apresenta o produto, mas organiza a entrada comercial do SaaS.</h2>
                    <p>Se a homepage publica nao vende, ela so ocupa espaco. Aqui a proposta foi estruturar uma landing com discurso comercial, foco em publicidade digital e uma narrativa clara para busca organica, campanhas pagas e decisao de compra.</p>
                </div>

                <div class="about-grid">
                    <article class="content-card reveal">
                        <h3>O que a pagina resolve de verdade</h3>
                        <p>Ela conecta comunicacao, credibilidade e acao. O visitante entende o problema, enxerga a solucao, compara planos ativos e escolhe entre acessar a plataforma ou gerar um contato comercial. Esse encadeamento e mais importante do que qualquer efeito visual isolado.</p>
                        <p>O ganho pratico esta em reduzir friccao: o marketing nao promete uma coisa e o sistema entrega outra. A vitrine publica ja nasce alinhada ao cadastro de planos, ao ciclo de assinatura e aos meios de pagamento que o produto suporta hoje.</p>
                    </article>

                    <article class="metric-card reveal">
                        <h3>Indicadores de posicionamento</h3>
                        <p>A pagina foi estruturada para deixar explicitos os pilares que mais influenciam conversao e indexacao.</p>
                        <ul class="feature-card" style="margin-top:16px;padding:0;box-shadow:none;border:0;background:transparent">
                            <li>Arquitetura semantica com H1, H2, FAQ e blocos textuais ricos em contexto.</li>
                            <li>CTA de acesso e CTA de contato em pontos diferentes da jornada.</li>
                            <li>Planos dinamicos baseados no cadastro ativo do SaaS.</li>
                            <li>Formulario de lead com captura de origem e parametros UTM.</li>
                        </ul>
                    </article>
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

                <div class="problems-grid">
                    <?php foreach ($problemPoints as $index => $problem): ?>
                        <?php if (!is_array($problem)): continue; endif; ?>
                        <article class="problem-card reveal">
                            <span class="problem-index"><?= htmlspecialchars(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                            <h3 style="margin-top:18px"><?= htmlspecialchars((string) ($problem['title'] ?? 'Problema')) ?></h3>
                            <p><?= htmlspecialchars((string) ($problem['description'] ?? '')) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="section" id="solucoes">
            <div class="container">
                <div class="section-head reveal">
                    <span class="eyebrow">Solucoes</span>
                    <h2>O caminho mais consistente nao e improvisar mais, e padronizar melhor.</h2>
                    <p>A solucao aqui nao foi desenhar uma pagina genrica. Foi alinhar marketing, produto e operacao para que a experiencia publica seja coerente com o que o SaaS realmente entrega.</p>
                </div>

                <div class="solutions-grid">
                    <?php foreach ($solutions as $solution): ?>
                        <?php if (!is_array($solution)): continue; endif; ?>
                        <article class="solution-card reveal">
                            <span class="solution-eyebrow"><?= htmlspecialchars((string) ($solution['eyebrow'] ?? '')) ?></span>
                            <h3 style="margin-top:18px"><?= htmlspecialchars((string) ($solution['title'] ?? 'Solucao')) ?></h3>
                            <p><?= htmlspecialchars((string) ($solution['description'] ?? '')) ?></p>
                        </article>
                    <?php endforeach; ?>
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
                    <h2>Funcionalidade so tem valor quando conversa com receita, operacao e crescimento.</h2>
                    <p>Por isso a apresentacao foi organizada em blocos de negocio. O visitante entende rapidamente o que ajuda a vender, o que ajuda a operar e o que ajuda a receber.</p>
                </div>

                <div class="feature-grid">
                    <?php foreach ($featureGroups as $group): ?>
                        <?php if (!is_array($group)): continue; endif; ?>
                        <article class="feature-card reveal">
                            <span class="feature-chip"><?= htmlspecialchars((string) ($group['title'] ?? 'Grupo')) ?></span>
                            <h3 style="margin-top:18px"><?= htmlspecialchars((string) ($group['title'] ?? 'Funcionalidades')) ?></h3>
                            <ul>
                                <?php foreach ((array) ($group['items'] ?? []) as $item): ?>
                                    <li><?= htmlspecialchars((string) $item) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="section" id="planos">
            <div class="container">
                <div class="section-head reveal">
                    <span class="eyebrow">Planos</span>
                    <h2>Os planos desta pagina seguem o cadastro ativo do SaaS.</h2>
                    <p>Isso evita promessa comercial fora da realidade. O que aparece aqui respeita o catalogo vigente e aplica os marcadores comerciais de destaque e recomendado definidos na administracao interna.</p>
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
                        <p>O catalogo ainda nao possui planos ativos para exibicao. A proxima acao correta e ativar os planos no painel SaaS antes de escalar trafego para esta pagina.</p>
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
                                    <p><?= htmlspecialchars($planDescription !== '' ? $planDescription : 'Plano comercial ativo para operacao SaaS com cobranca recorrente.') ?></p>
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
                        <p>Mais do que apresentacao. Ela sustenta descoberta, avaliacao, comparacao e entrada comercial com narrativa coerente para venda SaaS.</p>

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
    const nav = document.querySelector('[data-site-nav]');
    const toggle = document.querySelector('[data-menu-toggle]');
    const revealItems = Array.from(document.querySelectorAll('.reveal'));
    const pricingToggle = document.querySelector('[data-pricing-toggle]');
    const planCards = Array.from(document.querySelectorAll('[data-plan-card]'));

    if (toggle instanceof HTMLButtonElement && nav instanceof HTMLElement) {
        toggle.addEventListener('click', () => {
            nav.classList.toggle('is-open');
        });

        nav.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => nav.classList.remove('is-open'));
        });
    }

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
