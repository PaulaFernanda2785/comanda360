<?php
$selectedPlan = is_array($selectedPlan ?? null) ? $selectedPlan : [];
$formData = is_array($formData ?? null) ? $formData : [];
$logoUrl = public_logo_url();

$formatMoney = static function (?float $amount): string {
    if ($amount === null) {
        return 'Sob consulta';
    }

    return 'R$ ' . number_format($amount, 2, ',', '.');
};

$formatLimit = static function (?int $value, string $label): string {
    return $value === null ? $label . ' ilimitados' : $value . ' ' . $label;
};
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
        --primary-deep:#d95a00;
        --secondary:#0ea5a4;
        --success:#157f5b;
        --danger:#b42318;
        --radius-xl:28px;
        --radius-lg:22px;
        --radius-md:16px;
        --shadow:0 30px 70px rgba(7,21,35,.16);
        --max:1180px;
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
        grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr);
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
    .plan-card{
        padding:28px;
        background:linear-gradient(160deg,#081b2e 0%, #10304c 58%, #ff7a18 145%);
        color:#f6fbff;
    }
    .plan-badge{
        display:inline-flex;
        padding:8px 12px;
        border-radius:999px;
        background:#fff;
        color:#081b2e;
        font-size:12px;
        font-weight:800;
        letter-spacing:.08em;
        text-transform:uppercase;
    }
    .plan-card h2{margin-top:18px;font-size:32px}
    .plan-card p{margin-top:12px;color:rgba(246,251,255,.82);line-height:1.6}
    .plan-price{
        display:flex;
        align-items:end;
        gap:10px;
        margin-top:20px;
        flex-wrap:wrap;
    }
    .plan-price strong{font-size:44px;line-height:.95}
    .plan-price span{font-size:14px;font-weight:800;color:rgba(246,251,255,.8)}
    .plan-meta{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:10px;
        margin-top:20px;
    }
    .plan-meta div{
        padding:14px 12px;
        border-radius:16px;
        background:rgba(255,255,255,.1);
        border:1px solid rgba(255,255,255,.14);
    }
    .plan-meta span{display:block;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:rgba(246,251,255,.72)}
    .plan-meta strong{display:block;margin-top:6px;font-size:15px;color:#fff}
    .plan-features{display:grid;gap:10px;margin:22px 0 0;padding:0;list-style:none}
    .plan-features li{
        display:flex;
        align-items:center;
        gap:10px;
        color:rgba(246,251,255,.88);
        font-size:14px;
        line-height:1.5;
    }
    .plan-features li::before{
        content:"";
        width:10px;
        height:10px;
        border-radius:999px;
        background:linear-gradient(135deg,#facc15,#fff7cc);
        flex:0 0 auto;
    }
    .content-grid{
        display:grid;
        grid-template-columns:minmax(0,1fr) minmax(320px,.75fr);
        gap:24px;
        margin-top:24px;
    }
    .form-card{padding:30px}
    .form-card h2{font-size:28px;color:#081b2e}
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
    .form-grid{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:16px;
        margin-top:24px;
    }
    .field{display:grid;gap:8px}
    .field.full{grid-column:1 / -1}
    label{font-size:13px;font-weight:800;color:#20364b}
    input{
        width:100%;
        min-height:50px;
        border-radius:14px;
        border:1px solid rgba(8,27,46,.12);
        background:#fff;
        padding:0 16px;
        color:#102235;
        font:600 14px/1.4 "Manrope","Segoe UI",sans-serif;
    }
    input:focus{outline:none;border-color:#0ea5a4;box-shadow:0 0 0 4px rgba(14,165,164,.12)}
    .form-actions{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:14px;
        flex-wrap:wrap;
        margin-top:24px;
    }
    .form-note{max-width:520px;color:#607284;font-size:13px;line-height:1.6}
    .aside-card{padding:28px}
    .aside-card h3{font-size:24px;color:#081b2e}
    .aside-card p{margin:12px 0 0;color:#5c6b7c;line-height:1.7}
    .aside-list{display:grid;gap:12px;margin:18px 0 0;padding:0;list-style:none}
    .aside-list li{
        padding:14px 16px;
        border-radius:18px;
        background:#fff;
        border:1px solid rgba(8,27,46,.08);
        color:#20364b;
        line-height:1.6;
    }
    @media (max-width:980px){
        .hero,.content-grid{grid-template-columns:1fr}
    }
    @media (max-width:720px){
        .form-grid,.plan-meta{grid-template-columns:1fr}
        .hero-copy,.form-card,.plan-card,.aside-card{padding:22px}
        .topbar{align-items:flex-start;flex-direction:column}
        .form-actions{flex-direction:column;align-items:stretch}
    }
</style>

<div class="page-shell">
    <div class="container">
        <div class="topbar">
            <a class="brand" href="<?= htmlspecialchars(base_url('/')) ?>">
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Comanda360">
            </a>
            <div class="topbar-links">
                <a href="<?= htmlspecialchars(base_url('/#planos')) ?>">Voltar aos planos</a>
                <a class="btn btn-secondary" href="<?= htmlspecialchars(base_url('/login')) ?>">Ja sou cliente</a>
            </div>
        </div>

        <section class="hero">
            <article class="card hero-copy">
                <span class="eyebrow">Onboarding comercial</span>
                <h1>Cadastro da empresa com o plano ja definido, sem desalinhar marketing, contrato e faturamento.</h1>
                <p>O ponto critico aqui nao e apenas cadastrar dados. E garantir que plano, ciclo e valor entrem corretos desde a origem para o pagamento acontecer sem retrabalho comercial depois.</p>
                <div class="hero-points">
                    <div>Plano selecionado automaticamente conforme o card da pagina publica.</div>
                    <div>Ciclo mensal ou anual travado na escolha comercial feita pelo visitante.</div>
                    <div>Depois do cadastro, a empresa segue direto para a etapa de pagamento.</div>
                </div>
            </article>

            <aside class="card plan-card">
                <span class="plan-badge">Plano escolhido</span>
                <h2><?= htmlspecialchars((string) ($selectedPlan['name'] ?? 'Plano')) ?></h2>
                <p><?= htmlspecialchars((string) (($selectedPlan['description'] ?? '') !== '' ? $selectedPlan['description'] : 'Plano ativo publicado para contratacao no ambiente SaaS.')) ?></p>
                <div class="plan-price">
                    <strong><?= htmlspecialchars($formatMoney(isset($selectedPlan['amount']) ? (float) $selectedPlan['amount'] : null)) ?></strong>
                    <span>/ <?= htmlspecialchars((string) ($selectedPlan['billing_cycle'] ?? 'mensal')) ?></span>
                </div>
                <div class="plan-meta">
                    <div>
                        <span>Usuarios</span>
                        <strong><?= htmlspecialchars($formatLimit($selectedPlan['max_users'] ?? null, 'usuarios')) ?></strong>
                    </div>
                    <div>
                        <span>Produtos</span>
                        <strong><?= htmlspecialchars($formatLimit($selectedPlan['max_products'] ?? null, 'produtos')) ?></strong>
                    </div>
                    <div>
                        <span>Mesas</span>
                        <strong><?= htmlspecialchars($formatLimit($selectedPlan['max_tables'] ?? null, 'mesas')) ?></strong>
                    </div>
                </div>
                <?php if (!empty($selectedPlan['feature_labels'])): ?>
                    <ul class="plan-features">
                        <?php foreach ((array) ($selectedPlan['feature_labels'] ?? []) as $featureLabel): ?>
                            <li><?= htmlspecialchars((string) $featureLabel) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </aside>
        </section>

        <section class="content-grid">
            <article class="card form-card">
                <h2>Dados da empresa</h2>
                <p>Preencha o cadastro principal. O sistema vai criar a empresa, associar o plano escolhido e preparar o acesso inicial do administrador assim que o pagamento for confirmado.</p>

                <?php if (!empty($flashSuccess)): ?>
                    <div class="flash success"><?= htmlspecialchars((string) $flashSuccess) ?></div>
                <?php endif; ?>
                <?php if (!empty($flashError)): ?>
                    <div class="flash error"><?= htmlspecialchars((string) $flashError) ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="flash error"><?= htmlspecialchars((string) $error) ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= htmlspecialchars(base_url('/cadastro/empresa')) ?>">
                    <?= form_security_fields('marketing.public.signup') ?>
                    <input type="hidden" name="plano" value="<?= htmlspecialchars((string) ($selectedPlan['slug'] ?? '')) ?>">
                    <input type="hidden" name="ciclo" value="<?= htmlspecialchars((string) ($selectedPlan['billing_cycle'] ?? 'mensal')) ?>">

                    <div class="form-grid">
                        <div class="field">
                            <label for="signup_company_name">Nome da empresa</label>
                            <input id="signup_company_name" name="name" type="text" value="<?= htmlspecialchars((string) ($formData['name'] ?? '')) ?>" required>
                        </div>
                        <div class="field">
                            <label for="signup_company_slug">Slug publico</label>
                            <input id="signup_company_slug" name="slug" type="text" value="<?= htmlspecialchars((string) ($formData['slug'] ?? '')) ?>" placeholder="Opcional">
                        </div>
                        <div class="field">
                            <label for="signup_company_legal_name">Razao social</label>
                            <input id="signup_company_legal_name" name="legal_name" type="text" value="<?= htmlspecialchars((string) ($formData['legal_name'] ?? '')) ?>">
                        </div>
                        <div class="field">
                            <label for="signup_company_document">CPF/CNPJ</label>
                            <input id="signup_company_document" name="document_number" type="text" value="<?= htmlspecialchars((string) ($formData['document_number'] ?? '')) ?>">
                        </div>
                        <div class="field">
                            <label for="signup_company_email">E-mail principal</label>
                            <input id="signup_company_email" name="email" type="email" value="<?= htmlspecialchars((string) ($formData['email'] ?? '')) ?>" required>
                        </div>
                        <div class="field">
                            <label for="signup_company_phone">Telefone</label>
                            <input id="signup_company_phone" name="phone" type="text" value="<?= htmlspecialchars((string) ($formData['phone'] ?? '')) ?>">
                        </div>
                        <div class="field">
                            <label for="signup_company_whatsapp">WhatsApp</label>
                            <input id="signup_company_whatsapp" name="whatsapp" type="text" value="<?= htmlspecialchars((string) ($formData['whatsapp'] ?? '')) ?>">
                        </div>
                        <div class="field">
                            <label for="signup_company_cycle">Ciclo contratado</label>
                            <input id="signup_company_cycle" type="text" value="<?= htmlspecialchars((string) ucfirst((string) ($selectedPlan['billing_cycle'] ?? 'mensal'))) ?>" readonly>
                        </div>
                        <div class="field">
                            <label for="signup_company_password">Senha inicial do administrador</label>
                            <input id="signup_company_password" name="initial_admin_password" type="password" minlength="6" required>
                        </div>
                        <div class="field">
                            <label for="signup_company_password_confirmation">Confirmar senha inicial</label>
                            <input id="signup_company_password_confirmation" name="initial_admin_password_confirmation" type="password" minlength="6" required>
                        </div>
                    </div>

                    <div class="form-actions">
                        <div class="form-note">
                            O acesso nao e liberado no cadastro. Primeiro a empresa conclui o pagamento da assinatura. Depois da confirmacao do banco, o login inicial fica disponivel com o e-mail principal e a senha definida acima.
                        </div>
                        <button class="btn btn-primary" type="submit">Salvar cadastro e seguir para pagamento</button>
                    </div>
                </form>
            </article>

            <aside class="card aside-card">
                <h3>O que acontece depois</h3>
                <p>O fluxo nao deve deixar lacuna operacional entre comercial e financeiro. Por isso o cadastro ja nasce vinculado ao plano escolhido.</p>
                <ul class="aside-list">
                    <li>1. O cadastro cria a empresa, vincula o plano e prepara a assinatura no ciclo selecionado.</li>
                    <li>2. O usuario administrador principal e criado, mas fica inativo ate a confirmacao do pagamento.</li>
                    <li>3. A proxima tela concentra PIX ou cartao para fechar a contratacao sem depender de ajuste manual interno.</li>
                </ul>
            </aside>
        </section>
    </div>
</div>
