<?php
$confirmation = is_array($confirmation ?? null) ? $confirmation : [];
$logoUrl = public_logo_url();

$formatMoney = static function (?float $amount): string {
    if ($amount === null) {
        return 'Sob consulta';
    }

    return 'R$ ' . number_format($amount, 2, ',', '.');
};

$formatDate = static function (?string $value): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '-';
    }

    $date = date_create_immutable($raw);
    if (!$date instanceof DateTimeImmutable) {
        return $raw;
    }

    return $date->format('d/m/Y H:i');
};
?>

<style>
    :root{
        --bg:#071523;
        --text:#102235;
        --muted:#607284;
        --line:rgba(9,27,44,.12);
        --primary:#ff7a18;
        --success:#157f5b;
        --radius-xl:28px;
        --radius-lg:22px;
        --shadow:0 30px 70px rgba(7,21,35,.16);
        --max:1040px;
    }
    *{box-sizing:border-box}
    body{
        margin:0;
        color:var(--text);
        background:
            radial-gradient(circle at top left, rgba(21,127,91,.16), transparent 30%),
            radial-gradient(circle at right center, rgba(255,122,24,.14), transparent 24%),
            linear-gradient(180deg,#f3f8f5 0%, #eef5fb 44%, #f8fbff 100%);
        font-family:"Manrope","Segoe UI",sans-serif;
    }
    a{color:inherit}
    .page-shell{padding:38px 0 70px}
    .container{width:min(calc(100% - 32px), var(--max));margin:0 auto}
    .topbar{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:18px;
        padding:0 0 22px;
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
        max-width:min(300px, 40vw);
        object-fit:contain;
        flex:0 0 auto;
    }
    .panel{
        background:rgba(255,255,255,.92);
        border:1px solid rgba(255,255,255,.76);
        border-radius:var(--radius-xl);
        box-shadow:var(--shadow);
        overflow:hidden;
    }
    .hero{
        padding:34px;
        background:linear-gradient(155deg,#0a7d58 0%, #081b2e 58%, #ff7a18 148%);
        color:#f4fbff;
    }
    .eyebrow{
        display:inline-flex;
        padding:8px 14px;
        border-radius:999px;
        background:rgba(255,255,255,.14);
        border:1px solid rgba(255,255,255,.18);
        font-size:12px;
        font-weight:800;
        letter-spacing:.12em;
        text-transform:uppercase;
    }
    h1,h2{margin:0;font-family:"Space Grotesk","Manrope",sans-serif}
    .hero h1{margin-top:18px;font-size:clamp(34px,4.6vw,58px);line-height:1.03}
    .hero p{margin:16px 0 0;color:rgba(244,251,255,.82);font-size:17px;line-height:1.7}
    .content{
        display:grid;
        grid-template-columns:minmax(0,1fr) minmax(300px,.82fr);
        gap:24px;
        padding:28px;
    }
    .card{
        padding:24px;
        border-radius:24px;
        border:1px solid rgba(8,27,46,.08);
        background:#fff;
    }
    .card h2{font-size:28px;color:#081b2e}
    .card p{margin:12px 0 0;color:#5c6b7c;line-height:1.7}
    .checklist{
        display:grid;
        gap:12px;
        margin-top:20px;
        padding:0;
        list-style:none;
    }
    .checklist li{
        padding:14px 16px;
        border-radius:18px;
        background:#f6fbf8;
        border:1px solid rgba(21,127,91,.12);
        color:#184a37;
        line-height:1.6;
    }
    .summary{
        display:grid;
        gap:12px;
        margin-top:20px;
    }
    .summary-item{
        padding:16px 18px;
        border-radius:18px;
        background:#f8fbff;
        border:1px solid rgba(8,27,46,.06);
    }
    .summary-item span{
        display:block;
        font-size:11px;
        font-weight:800;
        letter-spacing:.08em;
        text-transform:uppercase;
        color:#607284;
    }
    .summary-item strong{
        display:block;
        margin-top:6px;
        font-size:16px;
        color:#102235;
    }
    .actions{
        display:flex;
        gap:12px;
        flex-wrap:wrap;
        margin-top:22px;
    }
    .btn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-height:48px;
        padding:0 20px;
        border-radius:999px;
        text-decoration:none;
        font-weight:800;
    }
    .btn-primary{
        background:linear-gradient(135deg,var(--primary),#ff934a);
        color:#fff;
        box-shadow:0 18px 36px rgba(255,122,24,.28);
    }
    .btn-secondary{
        background:#fff;
        color:#081b2e;
        border:1px solid rgba(8,27,46,.12);
    }
    @media (max-width:860px){
        .content{grid-template-columns:1fr}
        .topbar{padding-bottom:18px}
        .hero,.content{padding:22px}
    }
</style>

<div class="page-shell">
    <div class="container">
        <div class="topbar">
            <a class="brand" href="<?= htmlspecialchars(base_url('/')) ?>">
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Comanda360">
            </a>
            <a class="btn btn-secondary" href="<?= htmlspecialchars(base_url('/login')) ?>">Ir para o login</a>
        </div>
        <section class="panel">
            <div class="hero">
                <span class="eyebrow">Assinatura confirmada</span>
                <h1>Agora faça seu primeiro login.</h1>
                <p>O pagamento foi reconhecido e o acesso inicial da empresa ja esta liberado. O proximo passo correto e entrar na plataforma com o e-mail principal cadastrado e a senha definida no onboarding.</p>
            </div>

            <div class="content">
                <article class="card">
                    <h2>Primeiro acesso liberado</h2>
                    <p>A contratacao foi concluida sem depender de ajuste manual interno. Isso fecha a jornada comercial com previsibilidade melhor para o cliente e para o financeiro.</p>

                    <ul class="checklist">
                        <li>Empresa ativada: <strong><?= htmlspecialchars((string) ($confirmation['company_name'] ?? 'Empresa')) ?></strong></li>
                        <li>Plano contratado: <strong><?= htmlspecialchars((string) ($confirmation['plan_name'] ?? 'Plano')) ?></strong></li>
                        <li>E-mail de acesso: <strong><?= htmlspecialchars((string) ($confirmation['company_email'] ?? '-')) ?></strong></li>
                    </ul>

                    <div class="actions">
                        <a class="btn btn-primary" href="<?= htmlspecialchars(base_url('/login')) ?>">Ir para o login</a>
                        <a class="btn btn-secondary" href="<?= htmlspecialchars(base_url('/')) ?>">Voltar para a pagina inicial</a>
                    </div>
                </article>

                <aside class="card">
                    <h2>Resumo da confirmacao</h2>
                    <div class="summary">
                        <div class="summary-item">
                            <span>Valor confirmado</span>
                            <strong><?= htmlspecialchars($formatMoney(isset($confirmation['amount']) ? (float) $confirmation['amount'] : null)) ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Ciclo contratado</span>
                            <strong><?= htmlspecialchars(status_label('billing_cycle', (string) ($confirmation['billing_cycle'] ?? 'mensal'))) ?></strong>
                        </div>
                        <div class="summary-item">
                            <span>Confirmado em</span>
                            <strong><?= htmlspecialchars($formatDate((string) ($confirmation['paid_at'] ?? ''))) ?></strong>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    </div>
</div>
