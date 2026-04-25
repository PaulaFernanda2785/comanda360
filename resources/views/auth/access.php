<?php
$logoUrl = public_logo_url();
$mobilePreviewUrl = public_embedded_image_url('img/menu-celular.png');
?>

<style>
    :root{
        --bg:#07111e;
        --bg-deep:#081726;
        --surface:#ffffff;
        --surface-soft:#f4f7fb;
        --text:#102235;
        --muted:#5f7286;
        --line:rgba(10,28,46,.12);
        --line-soft:rgba(255,255,255,.12);
        --primary:#ff7a18;
        --primary-deep:#d65a00;
        --secondary:#0ea5a4;
        --success:#157f5b;
        --danger:#b42318;
        --radius-xl:34px;
        --radius-lg:24px;
        --radius-md:18px;
        --shadow-lg:0 36px 90px rgba(7,21,35,.18);
        --shadow-md:0 20px 48px rgba(7,21,35,.14);
        --max:1260px;
    }

    *{box-sizing:border-box}

    body{
        margin:0;
        color:var(--text);
        background:
            radial-gradient(circle at top left, rgba(14,165,164,.16), transparent 28%),
            radial-gradient(circle at top right, rgba(255,122,24,.16), transparent 24%),
            linear-gradient(180deg,#f4efe7 0%, #edf4fb 42%, #f8fbff 100%);
        font-family:"Manrope","Segoe UI",sans-serif;
    }

    a{color:inherit}

    .page-shell{padding:118px 0 68px}
    .container{width:min(calc(100% - 32px), var(--max));margin:0 auto}

    .topbar{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:18px;
        padding:14px 18px;
        position:fixed;
        top:16px;
        left:50%;
        transform:translateX(-50%);
        width:min(calc(100% - 32px), var(--max));
        z-index:30;
        border-radius:24px;
        background:rgba(255,255,255,.62);
        border:1px solid rgba(255,255,255,.72);
        box-shadow:0 20px 48px rgba(7,21,35,.10);
        backdrop-filter:blur(20px);
    }

    .brand{
        display:inline-flex;
        align-items:center;
        text-decoration:none;
        flex:0 0 auto;
    }

    .brand img{
        display:block;
        height:56px;
        width:auto;
        max-width:min(320px, 42vw);
        object-fit:contain;
        flex:0 0 auto;
    }

    .topbar-actions{
        display:flex;
        align-items:center;
        gap:12px;
        flex-wrap:wrap;
    }

    .btn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-height:50px;
        padding:0 20px;
        border-radius:999px;
        border:1px solid transparent;
        text-decoration:none;
        font-weight:800;
        cursor:pointer;
        transition:transform .2s ease, box-shadow .2s ease, border-color .2s ease, background .2s ease;
    }

    .btn:hover{transform:translateY(-1px)}

    .btn-primary{
        color:#fff;
        background:linear-gradient(135deg,var(--primary),#ff9551);
        box-shadow:0 18px 36px rgba(255,122,24,.24);
    }

    .btn-secondary{
        color:#081b2e;
        background:#fff;
        border-color:rgba(8,27,46,.12);
    }

    .eyebrow{
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:8px 14px;
        border-radius:999px;
        background:rgba(255,255,255,.08);
        border:1px solid rgba(255,255,255,.14);
        color:#eef6fb;
        font-size:12px;
        font-weight:800;
        letter-spacing:.11em;
        text-transform:uppercase;
        backdrop-filter:blur(12px);
    }

    h1,h2,h3{margin:0;font-family:"Space Grotesk","Manrope",sans-serif}

    .auth-stage{
        display:grid;
        grid-template-columns:minmax(0,1.08fr) minmax(390px,.92fr);
        gap:26px;
        align-items:start;
    }

    .context-panel,
    .login-panel{
        border-radius:var(--radius-xl);
        overflow:hidden;
        box-shadow:var(--shadow-lg);
    }

    .context-panel{
        position:relative;
        padding:40px;
        color:#f4fbff;
        background:
            radial-gradient(circle at top right, rgba(255,162,77,.20), transparent 24%),
            linear-gradient(155deg,#081b2e 0%, #0c2f4b 54%, #0ea5a4 148%);
        border:1px solid rgba(255,255,255,.14);
        display:grid;
        align-content:start;
        gap:22px;
    }

    .context-panel::before{
        content:"";
        position:absolute;
        inset:0;
        background:
            linear-gradient(180deg, rgba(255,255,255,.10) 0%, rgba(255,255,255,0) 22%),
            radial-gradient(circle at bottom left, rgba(255,255,255,.08), transparent 34%);
        pointer-events:none;
    }

    .context-panel > *{position:relative;z-index:1}

    .context-copy{
        display:grid;
        gap:14px;
        max-width:560px;
    }

    .context-copy h1{
        font-size:clamp(24px,2.8vw,38px);
        line-height:1.06;
        letter-spacing:-.04em;
        max-width:470px;
        text-shadow:0 14px 30px rgba(0,0,0,.22);
    }

    .context-copy p{
        margin:0;
        max-width:520px;
        color:rgba(244,251,255,.84);
        font-size:16px;
        line-height:1.7;
    }

    .context-grid{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:14px;
    }

    .context-card{
        padding:20px 20px 18px;
        border-radius:24px;
        background:rgba(255,255,255,.10);
        border:1px solid rgba(255,255,255,.12);
        backdrop-filter:blur(16px);
    }

    .context-card span{
        display:block;
        color:rgba(244,251,255,.68);
        font-size:11px;
        font-weight:800;
        letter-spacing:.10em;
        text-transform:uppercase;
    }

    .context-card strong{
        display:block;
        margin-top:10px;
        color:#fff;
        font:700 19px/1.12 "Space Grotesk","Manrope",sans-serif;
    }

    .context-card p{
        margin:10px 0 0;
        color:rgba(244,251,255,.82);
        font-size:14px;
        line-height:1.58;
    }

    .context-note{
        padding:18px 20px;
        border-radius:22px;
        background:rgba(4,18,42,.32);
        border:1px solid rgba(255,255,255,.10);
        color:rgba(244,251,255,.84);
        line-height:1.65;
    }

    .login-panel{
        background:linear-gradient(180deg,#ffffff 0%, #f7faff 100%);
        border:1px solid rgba(8,27,46,.10);
        display:grid;
        grid-template-rows:auto auto 1fr;
    }

    .login-hero{
        position:relative;
        padding:26px 28px 22px;
        overflow:hidden;
        background:
            radial-gradient(circle at top right, rgba(255,162,77,.18), transparent 24%),
            linear-gradient(155deg,#071c46 0%, #0b2f62 54%, #0ea5a4 148%);
        color:#f5fbff;
        display:grid;
        gap:16px;
    }

    .login-hero::before{
        content:"";
        position:absolute;
        inset:0;
        background:
            linear-gradient(180deg, rgba(255,255,255,.10) 0%, rgba(255,255,255,0) 18%),
            radial-gradient(circle at left bottom, rgba(255,255,255,.08), transparent 30%);
        pointer-events:none;
    }

    .device-frame{
        position:relative;
        left:auto;
        bottom:auto;
        transform:none;
        width:min(80%, 316px);
        aspect-ratio:395 / 834;
        border-radius:40px;
        background:#05070c;
        box-shadow:
            0 34px 76px rgba(5,10,22,.34),
            inset 0 0 0 1px rgba(255,255,255,.08);
        z-index:1;
        padding:10px;
        justify-self:center;
        margin-top:6px;
    }

    .device-screen{
        position:relative;
        width:100%;
        height:100%;
        border-radius:32px;
        overflow:hidden;
        background:
            linear-gradient(180deg, rgba(7,18,46,.08) 0%, rgba(7,18,46,.02) 22%, rgba(7,18,46,.14) 100%),
            url('<?= htmlspecialchars($mobilePreviewUrl) ?>') center top/cover no-repeat;
    }

    .device-screen::before{
        content:"";
        position:absolute;
        top:8px;
        left:50%;
        transform:translateX(-50%);
        width:134px;
        height:28px;
        border-radius:0 0 18px 18px;
        background:#05070c;
        box-shadow:0 8px 18px rgba(0,0,0,.28);
    }

    .device-screen::after{
        content:"";
        position:absolute;
        left:50%;
        bottom:8px;
        transform:translateX(-50%);
        width:124px;
        height:5px;
        border-radius:999px;
        background:rgba(255,255,255,.84);
    }

    .login-hero > *{
        position:relative;
        z-index:2;
    }

    .login-hero .eyebrow{background:rgba(255,255,255,.12)}

    .login-hero h2{
        margin-top:16px;
        font-size:clamp(23px,2.5vw,31px);
        line-height:1.08;
        letter-spacing:-.04em;
        max-width:400px;
    }

    .login-hero p{
        margin:14px 0 0;
        max-width:420px;
        color:rgba(245,251,255,.84);
        font-size:15px;
        line-height:1.66;
    }

    .login-pills{
        display:flex;
        gap:10px;
        flex-wrap:wrap;
        margin-top:16px;
    }

    .login-pills span{
        display:inline-flex;
        align-items:center;
        padding:8px 12px;
        border-radius:999px;
        background:rgba(255,255,255,.10);
        border:1px solid rgba(255,255,255,.14);
        color:rgba(245,251,255,.86);
        font-size:12px;
        font-weight:800;
        letter-spacing:.04em;
    }

    .login-body{
        padding:24px 28px 20px;
        display:grid;
        gap:16px;
    }

    .login-head{
        display:grid;
        gap:10px;
    }

    .login-head h3{
        font-size:24px;
        line-height:1.06;
        letter-spacing:-.04em;
        color:#081b2e;
    }

    .login-head p{
        margin:0;
        color:var(--muted);
        line-height:1.66;
    }

    .flash-stack{
        display:grid;
        gap:10px;
    }

    .flash{
        padding:14px 16px;
        border-radius:16px;
        border:1px solid transparent;
        font-weight:700;
        line-height:1.55;
        font-size:14px;
    }

    .flash.success{background:#ecfdf3;color:var(--success);border-color:#b7ebcf}
    .flash.error{background:#fff1f0;color:var(--danger);border-color:#f7c5c0}

    .form-grid{
        display:grid;
        gap:16px;
    }

    .field{
        display:grid;
        gap:8px;
    }

    label{
        font-size:12px;
        font-weight:800;
        letter-spacing:.10em;
        text-transform:uppercase;
        color:#365067;
    }

    input{
        width:100%;
        min-height:56px;
        border-radius:16px;
        border:1px solid rgba(8,27,46,.12);
        background:#fff;
        padding:0 16px;
        color:#102235;
        font:600 15px/1.4 "Manrope","Segoe UI",sans-serif;
    }

    input:focus{
        outline:none;
        border-color:rgba(255,122,24,.72);
        box-shadow:0 0 0 4px rgba(255,122,24,.12);
    }

    .login-actions{
        display:grid;
        gap:14px;
    }

    .login-actions .btn{
        width:100%;
        min-height:56px;
    }

    .login-footer{
        margin-top:0;
        padding:0 28px 24px;
    }

    .login-footer-card{
        padding:18px 20px;
        border-radius:20px;
        background:#f3f7fc;
        border:1px solid rgba(8,27,46,.08);
        color:#30495f;
        line-height:1.62;
        font-size:14px;
    }

    .page-footer{
        margin-top:28px;
        padding:0;
        border-radius:34px;
        overflow:hidden;
        background:
            radial-gradient(circle at top right, rgba(255,162,77,.18), transparent 24%),
            radial-gradient(circle at left center, rgba(14,165,164,.15), transparent 34%),
            linear-gradient(160deg,#07192d 0%, #0a2743 44%, #0d2137 100%);
        border:1px solid rgba(255,255,255,.12);
        box-shadow:var(--shadow-md);
        color:#eff7ff;
    }

    .page-footer-topline{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:14px;
        flex-wrap:wrap;
        padding:18px 26px;
        border-bottom:1px solid rgba(255,255,255,.08);
        background:linear-gradient(90deg, rgba(255,255,255,.06), rgba(255,255,255,.02));
    }

    .page-footer-kicker{
        display:inline-flex;
        align-items:center;
        gap:10px;
        color:rgba(239,247,255,.84);
        font-size:13px;
        font-weight:800;
        letter-spacing:.08em;
        text-transform:uppercase;
    }

    .page-footer-kicker::before{
        content:"";
        width:10px;
        height:10px;
        border-radius:999px;
        background:linear-gradient(135deg,var(--primary),#ffb071);
        box-shadow:0 0 0 6px rgba(255,122,24,.12);
    }

    .page-footer-topline span{
        color:rgba(239,247,255,.72);
        font-size:13px;
        line-height:1.6;
    }

    .page-footer-main{
        display:grid;
        grid-template-columns:minmax(0,1.18fr) repeat(2,minmax(220px,.46fr));
        gap:22px;
        padding:26px;
    }

    .page-footer-brand{
        display:grid;
        gap:14px;
        align-content:start;
    }

    .page-footer-brand .brand-mark{
        display:inline-flex;
        align-items:center;
        gap:12px;
    }

    .page-footer-brand .brand-mark img{
        display:block;
        height:34px;
        width:auto;
    }

    .page-footer-title{
        font:700 20px/1.06 "Space Grotesk","Manrope",sans-serif;
        color:#fff;
    }

    .page-footer-subtitle{
        color:rgba(239,247,255,.80);
        line-height:1.75;
        max-width:520px;
        font-size:14px;
    }

    .page-footer-pills{
        display:flex;
        gap:10px;
        flex-wrap:wrap;
        margin-top:4px;
    }

    .page-footer-pills span{
        display:inline-flex;
        align-items:center;
        padding:8px 12px;
        border-radius:999px;
        background:rgba(255,255,255,.08);
        border:1px solid rgba(255,255,255,.12);
        color:rgba(239,247,255,.84);
        font-size:12px;
        font-weight:800;
        letter-spacing:.04em;
    }

    .page-footer-highlight{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:12px;
    }

    .page-footer-stat{
        padding:16px 16px 14px;
        border-radius:20px;
        background:rgba(255,255,255,.06);
        border:1px solid rgba(255,255,255,.10);
    }

    .page-footer-stat strong{
        display:block;
        color:#fff;
        font:700 22px/1 "Space Grotesk","Manrope",sans-serif;
    }

    .page-footer-stat span{
        display:block;
        margin-top:8px;
        color:rgba(239,247,255,.72);
        font-size:13px;
        line-height:1.55;
    }

    .page-footer-column{
        display:grid;
        gap:12px;
        align-content:start;
    }

    .page-footer-column strong{
        display:block;
        font-size:12px;
        letter-spacing:.10em;
        text-transform:uppercase;
        color:rgba(239,247,255,.66);
    }

    .page-footer-list{
        margin:0;
        padding:0;
        list-style:none;
        display:grid;
        gap:10px;
    }

    .page-footer-list li{
        margin:0;
        padding:14px 16px;
        border-radius:18px;
        background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.04));
        border:1px solid rgba(255,255,255,.10);
        color:rgba(239,247,255,.84);
        line-height:1.62;
        font-size:14px;
    }

    .page-footer-bottom{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:14px;
        flex-wrap:wrap;
        padding:18px 26px 22px;
        border-top:1px solid rgba(255,255,255,.08);
        background:rgba(255,255,255,.04);
    }

    .page-footer-bottom span{
        color:rgba(239,247,255,.76);
        font-size:13px;
        line-height:1.6;
    }

    @media (max-width:1080px){
        .auth-stage{grid-template-columns:1fr}
        .context-panel,
        .login-panel{min-height:auto}
        .page-footer-main{grid-template-columns:1fr}
        .page-footer-highlight{grid-template-columns:repeat(3,minmax(0,1fr))}
    }

    @media (max-width:720px){
        .page-shell{padding:12px 0 54px}
        .container{width:min(calc(100% - 22px), var(--max))}
        .topbar{
            position:sticky;
            top:12px;
            left:auto;
            transform:none;
            width:100%;
            align-items:flex-start;
            flex-direction:column;
            padding:14px 16px;
            margin-bottom:16px;
        }
        .context-panel{padding:26px}
        .context-grid{grid-template-columns:1fr}
        .context-copy h1{font-size:30px}
        .login-hero{padding:24px 22px 20px}
        .login-body,
        .login-footer{padding-left:22px;padding-right:22px}
        .login-head h3{font-size:22px}
        .topbar-actions .btn{width:100%}
        .device-frame{width:min(78%, 290px)}
        .page-footer-topline,
        .page-footer-main,
        .page-footer-bottom{padding-left:20px;padding-right:20px}
        .page-footer-highlight{grid-template-columns:1fr}
    }
</style>

<div class="page-shell">
    <div class="container">
        <div class="topbar">
            <a class="brand" href="<?= htmlspecialchars(base_url('/')) ?>">
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="MesiMenu">
            </a>
            <div class="topbar-actions">
                <a class="btn btn-secondary" href="<?= htmlspecialchars(base_url('/')) ?>">Voltar para a página pública</a>
            </div>
        </div>

        <section class="auth-stage">
            <article class="context-panel">
                <div class="context-copy">
                    <span class="eyebrow">Acesso interno do MesiMenu</span>
                    <h1>Entrar no ambiente certo, com o perfil certo e sem misturar contextos de uso.</h1>
                    <p>O login interno do MesiMenu separa operação do estabelecimento, gestão da empresa assinante e ambiente institucional da plataforma. O cliente final do QR Code continua no fluxo público do cardápio e do pedido.</p>
                </div>

                <div class="context-grid">
                    <article class="context-card">
                        <span>Operação do estabelecimento</span>
                        <strong>Mesas, comandas e pedidos</strong>
                        <p>Garçom, cozinha, caixa e entrega acessam aqui os módulos de atendimento, produção, recebimento e acompanhamento operacional.</p>
                    </article>

                    <article class="context-card">
                        <span>Gestão da empresa</span>
                        <strong>Catálogo, estoque e relatórios</strong>
                        <p>Administrador e gestor entram para manter produtos, adicionais, taxas, usuários e acompanhamento gerencial da unidade.</p>
                    </article>

                    <article class="context-card">
                        <span>Ambiente institucional</span>
                        <strong>Empresas, planos e cobranças</strong>
                        <p>Usuários do MesiMenu acessam governança da plataforma, assinaturas, cobranças, suporte e operação global do MesiMenu.</p>
                    </article>

                    <article class="context-card">
                        <span>Fronteira de acesso</span>
                        <strong>Cliente final fica fora daqui</strong>
                        <p>O consumo público permanece no cardápio digital por QR Code ou link direto do estabelecimento, sem passar por este login.</p>
                    </article>
                </div>

                <div class="context-note">
                    Esta tela existe para refletir a arquitetura real do sistema: área pública do cliente, área operacional do estabelecimento, área administrativa da empresa e ambiente institucional separados por permissão e contexto.
                </div>
            </article>

            <article class="login-panel">
                <div class="login-hero">
                    <span class="eyebrow">Acesso dedicado</span>
                    <h2>Entre com seu usuário interno e siga para o módulo que pertence ao seu perfil.</h2>
                    <p>O destino do acesso respeita o contexto permitido para o usuário: operação, administração da empresa ou ambiente institucional do MesiMenu.</p>

                    <div class="login-pills">
                        <span>Pedidos e cozinha</span>
                        <span>Catálogo e estoque</span>
                        <span>Empresas e cobranças</span>
                    </div>

                    <div class="device-frame">
                        <div class="device-screen"></div>
                    </div>
                </div>

                <div class="login-body">
                    <div class="login-head">
                        <h3>Acessar agora</h3>
                        <p>Informe o e-mail e a senha do usuário cadastrado no MesiMenu. O redirecionamento é definido automaticamente pelo perfil, empresa vinculada e escopo autorizado.</p>
                    </div>

                    <div class="flash-stack">
                        <?php if (!empty($flashSuccess)): ?>
                            <div class="flash success"><?= htmlspecialchars((string) $flashSuccess) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($flashError)): ?>
                            <div class="flash error"><?= htmlspecialchars((string) $flashError) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($error)): ?>
                            <div class="flash error"><?= htmlspecialchars((string) $error) ?></div>
                        <?php endif; ?>
                    </div>

                    <form method="POST" action="<?= htmlspecialchars(base_url('/login')) ?>" class="form-grid">
                        <?= form_security_fields('auth.login') ?>

                        <div class="field">
                            <label for="email">E-mail</label>
                            <input id="email" name="email" type="email" required autocomplete="email" placeholder="voce@empresa.com.br">
                        </div>

                        <div class="field">
                            <label for="password">Senha</label>
                            <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Sua senha de acesso">
                        </div>

                        <div class="login-actions">
                            <button class="btn btn-primary" type="submit">Entrar na plataforma</button>
                        </div>
                    </form>
                </div>

                <div class="login-footer">
                    <div class="login-footer-card">
                        O cliente final não utiliza esta tela. O fluxo público continua no cardápio digital do estabelecimento, com acesso por QR Code ou link direto.
                    </div>
                </div>
            </article>
        </section>

        <footer class="page-footer">
            <div class="page-footer-topline">
                <div class="page-footer-kicker">Autenticação interna com contexto controlado</div>
                <span>Entrada central para equipes operacionais, gestores da empresa e escopo institucional do MesiMenu.</span>
            </div>

            <div class="page-footer-main">
                <div class="page-footer-brand">
                    <div class="brand-mark">
                        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="MesiMenu">
                    </div>
                    <div class="page-footer-title">MesiMenu</div>
                    <div class="page-footer-subtitle">
                        Ambiente de autenticação para operação do estabelecimento, gestão da empresa assinante e área institucional da plataforma, respeitando perfil, permissão e contexto de uso.
                    </div>
                    <div class="page-footer-pills">
                        <span>Operação interna</span>
                        <span>Gestão da empresa</span>
                        <span>Ambiente institucional</span>
                    </div>
                    <div class="page-footer-highlight">
                        <div class="page-footer-stat">
                            <strong>3</strong>
                            <span>camadas de acesso organizadas sem misturar cliente público e usuário interno.</span>
                        </div>
                        <div class="page-footer-stat">
                            <strong>1</strong>
                            <span>ponto de entrada para perfis que operam, administram e sustentam a plataforma.</span>
                        </div>
                        <div class="page-footer-stat">
                            <strong>100%</strong>
                            <span>do fluxo pensado para respeitar perfil, permissão e empresa vinculada.</span>
                        </div>
                    </div>
                </div>

                <div class="page-footer-column">
                    <strong>Escopos Cobertos</strong>
                    <ul class="page-footer-list">
                        <li>Mesas, comandas, pedidos, cozinha, caixa e entregas da empresa.</li>
                        <li>Catálogo, adicionais, estoque, usuários e acompanhamento gerencial da unidade.</li>
                        <li>Empresas, planos, assinaturas, cobranças e suporte do MesiMenu.</li>
                    </ul>
                </div>

                <div class="page-footer-column">
                    <strong>Regra de acesso</strong>
                    <ul class="page-footer-list">
                        <li>Cliente final permanece no fluxo público do cardápio digital.</li>
                        <li>Usuários internos entram conforme o perfil e a empresa vinculada.</li>
                        <li>Permissões e rotas seguem o escopo operacional, administrativo ou institucional.</li>
                    </ul>
                </div>
            </div>

            <div class="page-footer-bottom">
                <span>Login interno do MesiMenu desenhado para separar atendimento ao cliente, operação do estabelecimento e governança da plataforma.</span>
                <span>Interface de acesso alinhada a contexto, permissão e responsabilidade operacional.</span>
            </div>
        </footer>
    </div>
</div>
