<?php
$logoUrl = public_logo_url();
?>

<style>
    :root{
        --bg:#071523;
        --surface:#f4f7fb;
        --card:#ffffff;
        --text:#102235;
        --muted:#607284;
        --line:rgba(9,27,44,.12);
        --primary:#ff7a18;
        --secondary:#0ea5a4;
        --danger:#b42318;
        --success:#157f5b;
        --radius-xl:28px;
        --radius-lg:22px;
        --radius-md:16px;
        --shadow:0 30px 70px rgba(7,21,35,.16);
        --max:1120px;
    }
    *{box-sizing:border-box}
    body{
        margin:0;
        color:var(--text);
        background:
            radial-gradient(circle at top left, rgba(14,165,164,.18), transparent 28%),
            radial-gradient(circle at right center, rgba(255,122,24,.16), transparent 24%),
            linear-gradient(180deg,#f4efe7 0%, #eef5fb 40%, #f8fbff 100%);
        font-family:"Manrope","Segoe UI",sans-serif;
    }
    a{color:inherit}
    .page-shell{padding:28px 0 64px}
    .container{width:min(calc(100% - 32px), var(--max));margin:0 auto}
    .topbar{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:18px;
        padding:16px 0 28px;
    }
    .brand{
        display:inline-flex;
        align-items:center;
        text-decoration:none;
        flex:0 0 auto;
    }
    .brand img{display:block;height:54px;width:auto;max-width:min(300px, 40vw);object-fit:contain;flex:0 0 auto}
    .topbar-links{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
    .topbar-links a{
        text-decoration:none;
        color:#375066;
        font-weight:800;
    }
    .btn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-height:48px;
        padding:0 18px;
        border-radius:999px;
        border:1px solid transparent;
        text-decoration:none;
        font-weight:800;
        cursor:pointer;
        transition:transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    }
    .btn:hover{transform:translateY(-1px)}
    .btn-primary{background:linear-gradient(135deg,var(--primary),#ff934a);color:#fff;box-shadow:0 18px 36px rgba(255,122,24,.28)}
    .btn-secondary{background:#fff;color:#081b2e;border-color:rgba(8,27,46,.12)}
    .hero{
        display:grid;
        grid-template-columns:minmax(0,1.05fr) minmax(340px,.95fr);
        gap:24px;
        align-items:start;
    }
    .card{
        background:rgba(255,255,255,.92);
        border:1px solid rgba(255,255,255,.76);
        border-radius:var(--radius-xl);
        box-shadow:var(--shadow);
        backdrop-filter:blur(14px);
    }
    .hero-copy{padding:34px}
    .eyebrow{
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:8px 14px;
        border-radius:999px;
        background:rgba(255,255,255,.82);
        border:1px solid rgba(8,27,46,.08);
        color:#0b3954;
        font-size:12px;
        font-weight:800;
        letter-spacing:.12em;
        text-transform:uppercase;
    }
    h1,h2,h3{margin:0;font-family:"Space Grotesk","Manrope",sans-serif}
    .hero-copy h1{margin-top:18px;font-size:clamp(34px,4.2vw,56px);line-height:1.04;color:#081b2e}
    .hero-copy p{margin:16px 0 0;color:#4f6175;font-size:17px;line-height:1.7}
    .hero-points{display:grid;gap:12px;margin-top:24px}
    .hero-points div{
        padding:14px 16px;
        border-radius:18px;
        background:#fff;
        border:1px solid rgba(8,27,46,.08);
        font-weight:700;
        color:#20364b;
    }
    .form-card{padding:30px}
    .form-card h2{font-size:30px;color:#081b2e}
    .form-card p{margin:10px 0 0;color:#5c6b7c;line-height:1.7}
    .flash{
        margin-top:18px;
        padding:14px 16px;
        border-radius:16px;
        border:1px solid transparent;
        font-weight:700;
    }
    .flash.success{background:#ecfdf3;color:#157f5b;border-color:#b7ebcf}
    .flash.error{background:#fff1f0;color:#b42318;border-color:#f7c5c0}
    .field{display:grid;gap:8px;margin-top:18px}
    label{font-size:13px;font-weight:800;color:#20364b}
    input{
        width:100%;
        min-height:52px;
        border-radius:14px;
        border:1px solid rgba(8,27,46,.12);
        background:#fff;
        padding:0 16px;
        color:#102235;
        font:600 14px/1.4 "Manrope","Segoe UI",sans-serif;
    }
    input:focus{outline:none;border-color:#0ea5a4;box-shadow:0 0 0 4px rgba(14,165,164,.12)}
    .form-actions{display:grid;gap:14px;margin-top:24px}
    .form-note{color:#607284;font-size:13px;line-height:1.6}
    .support-card{
        margin-top:24px;
        padding:22px;
        border-radius:22px;
        background:linear-gradient(160deg,#081b2e 0%, #10304c 58%, #ff7a18 145%);
        color:#f6fbff;
    }
    .support-card h3{font-size:24px}
    .support-card p{margin-top:10px;color:rgba(246,251,255,.82);line-height:1.7}
    .support-list{display:grid;gap:10px;margin:18px 0 0;padding:0;list-style:none}
    .support-list li{
        padding:12px 14px;
        border-radius:16px;
        background:rgba(255,255,255,.1);
        border:1px solid rgba(255,255,255,.14);
        color:rgba(246,251,255,.9);
        line-height:1.5;
    }
    @media (max-width:980px){
        .hero{grid-template-columns:1fr}
    }
    @media (max-width:720px){
        .hero-copy,.form-card,.support-card{padding:22px}
        .topbar{align-items:flex-start;flex-direction:column}
    }
</style>

<div class="page-shell">
    <div class="container">
        <div class="topbar">
            <a class="brand" href="<?= htmlspecialchars(base_url('/')) ?>">
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Comanda360">
            </a>
            <div class="topbar-links">
                <a href="<?= htmlspecialchars(base_url('/#planos')) ?>">Ver planos</a>
                <a class="btn btn-secondary" href="<?= htmlspecialchars(base_url('/')) ?>">Voltar para a pagina publica</a>
            </div>
        </div>

        <section class="hero">
            <article class="card hero-copy">
                <span class="eyebrow">Acesso dedicado</span>
                <h1>Entre na plataforma sem disputar espaco com a narrativa comercial.</h1>
                <p>A landing publica deve vender. O login deve acelerar acesso. Separar essas jornadas reduz friccao, evita distracao e deixa cada pagina com uma funcao clara.</p>
                <div class="hero-points">
                    <div>Acesso centralizado para empresa, operacao e usuarios SaaS.</div>
                    <div>Redirecionamento automatico conforme o perfil autorizado.</div>
                    <div>Mesmo endpoint de autenticacao, agora com tela exclusiva.</div>
                </div>
            </article>

            <article class="card form-card">
                <h2>Acessar agora</h2>
                <p>Use o e-mail principal cadastrado e a senha do seu usuario para entrar no ambiente correspondente.</p>

                <?php if (!empty($flashSuccess)): ?>
                    <div class="flash success"><?= htmlspecialchars((string) $flashSuccess) ?></div>
                <?php endif; ?>
                <?php if (!empty($flashError)): ?>
                    <div class="flash error"><?= htmlspecialchars((string) $flashError) ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="flash error"><?= htmlspecialchars((string) $error) ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= htmlspecialchars(base_url('/login')) ?>">
                    <?= form_security_fields('auth.login') ?>

                    <div class="field">
                        <label for="email">E-mail</label>
                        <input id="email" name="email" type="email" required autocomplete="email" placeholder="voce@empresa.com.br">
                    </div>

                    <div class="field">
                        <label for="password">Senha</label>
                        <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Sua senha de acesso">
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Entrar na plataforma</button>
                        <div class="form-note">Se o acesso ainda nao foi liberado, conclua antes o fluxo comercial e a confirmacao de pagamento.</div>
                    </div>
                </form>

                <aside class="support-card">
                    <h3>Antes de tentar de novo</h3>
                    <p>Se o login falhar, o problema normalmente nao esta na tela. Os pontos mais provaveis sao credencial incorreta, usuario ainda nao ativado ou perfil sem rota liberada.</p>
                    <ul class="support-list">
                        <li>Onboarding publico libera o acesso somente depois da confirmacao do pagamento.</li>
                        <li>Usuarios internos entram com o e-mail cadastrado no painel administrativo.</li>
                        <li>Perfis sem modulo permitido sao bloqueados por seguranca e retornam para esta tela.</li>
                    </ul>
                </aside>
            </article>
        </section>
    </div>
</div>
