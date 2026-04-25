<?php
$company = is_array($company ?? null) ? $company : [];
$subscription = is_array($subscription ?? null) ? $subscription : [];
$planSummary = is_array($planSummary ?? null) ? $planSummary : [];
$currentPayment = is_array($currentPayment ?? null) ? $currentPayment : [];
$paymentHistory = is_array($paymentHistory ?? null) ? $paymentHistory : [];
$gateway = is_array($gateway ?? null) ? $gateway : [];
$paymentState = is_array($paymentState ?? null) ? $paymentState : [];
$logoUrl = public_logo_url();

$formatMoney = static function (?float $amount): string {
    if ($amount === null) {
        return 'Sob consulta';
    }

    return 'R$ ' . number_format($amount, 2, ',', '.');
};

$formatDate = static function (?string $value, bool $withTime = false): string {
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '-';
    }

    $date = date_create_immutable($raw);
    if (!$date instanceof DateTimeImmutable) {
        return $raw;
    }

    return $withTime ? $date->format('d/m/Y H:i') : $date->format('d/m/Y');
};

$formatLimit = static function (?int $value, string $label): string {
    return $value === null ? $label . ' ilimitados' : $value . ' ' . $label;
};

$currentPaymentId = (int) ($currentPayment['id'] ?? 0);
$hasPixPayload = trim((string) ($currentPayment['pix_qr_payload'] ?? '')) !== '' || trim((string) ($currentPayment['pix_code'] ?? '')) !== '';
$hasGatewayTracking = trim((string) ($currentPayment['gateway_payment_id'] ?? '')) !== '' || trim((string) ($subscription['gateway_subscription_id'] ?? '')) !== '';
$paymentMethodLabels = [
    'pix' => 'PIX',
    'credito' => 'Cartão de crédito',
    'debito' => 'Cartão de débito',
];
$stateKey = trim((string) ($paymentState['key'] ?? 'pending'));
$stateTone = trim((string) ($paymentState['tone'] ?? 'pending'));
$stateTitle = trim((string) ($paymentState['title'] ?? 'Pagamento pendente'));
$stateMessage = trim((string) ($paymentState['message'] ?? 'Aguardando a confirmação do pagamento.'));
$stateHint = trim((string) ($paymentState['hint'] ?? ''));
?>

<style>
    :root{
        --bg:#071523;
        --surface:#f5f8fc;
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
        --shadow:0 30px 70px rgba(7,21,35,.14);
        --max:1180px;
    }
    *{box-sizing:border-box}
    body{
        margin:0;
        color:var(--text);
        background:
            radial-gradient(circle at top left, rgba(14,165,164,.16), transparent 28%),
            radial-gradient(circle at right center, rgba(255,122,24,.15), transparent 24%),
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
    .btn-dark{background:#081b2e;color:#fff}
    .card{
        background:rgba(255,255,255,.92);
        border:1px solid rgba(255,255,255,.76);
        border-radius:var(--radius-xl);
        box-shadow:var(--shadow);
        backdrop-filter:blur(14px);
    }
    .hero{
        display:grid;
        grid-template-columns:minmax(0,1.12fr) minmax(320px,.88fr);
        gap:24px;
        align-items:start;
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
    .hero-copy h1{margin-top:18px;font-size:clamp(24px,2.7vw,36px);line-height:1.12;color:#081b2e}
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
    .summary-card{
        padding:28px;
        background:linear-gradient(160deg,#081b2e 0%, #10304c 58%, #ff7a18 145%);
        color:#f6fbff;
    }
    .summary-card p{color:rgba(246,251,255,.82);line-height:1.6}
    .summary-price{display:flex;align-items:end;gap:10px;flex-wrap:wrap;margin-top:16px}
    .summary-price strong{font-size:44px;line-height:.95}
    .summary-price span{font-size:14px;font-weight:800;color:rgba(246,251,255,.8)}
    .summary-grid{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:10px;
        margin-top:20px;
    }
    .summary-grid div{
        padding:14px 12px;
        border-radius:16px;
        background:rgba(255,255,255,.1);
        border:1px solid rgba(255,255,255,.14);
    }
    .summary-grid span{display:block;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:rgba(246,251,255,.72)}
    .summary-grid strong{display:block;margin-top:6px;font-size:15px;color:#fff}
    .summary-features{display:grid;gap:10px;margin:20px 0 0;padding:0;list-style:none}
    .summary-features li{
        display:flex;
        align-items:center;
        gap:10px;
        color:rgba(246,251,255,.88);
        font-size:14px;
        line-height:1.5;
    }
    .summary-features li::before{
        content:"";
        width:10px;
        height:10px;
        border-radius:999px;
        background:linear-gradient(135deg,#facc15,#fff7cc);
        flex:0 0 auto;
    }
    .flash{
        margin-top:18px;
        padding:14px 16px;
        border-radius:16px;
        border:1px solid transparent;
        font-weight:700;
    }
    .flash.success{background:#ecfdf3;color:#157f5b;border-color:#b7ebcf}
    .flash.error{background:#fff1f0;color:#b42318;border-color:#f7c5c0}
    .content-grid{
        display:grid;
        grid-template-columns:minmax(0,1fr) minmax(320px,.84fr);
        gap:24px;
        margin-top:24px;
    }
    .section-card{padding:30px}
    .section-card h2{font-size:28px;color:#081b2e}
    .section-card p{margin:10px 0 0;color:#5c6b7c;line-height:1.7}
    .status-strip{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:12px;
        margin-top:22px;
    }
    .status-strip div{
        padding:14px 16px;
        border-radius:18px;
        background:#fff;
        border:1px solid rgba(8,27,46,.08);
    }
    .status-strip span{display:block;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#607284}
    .status-strip strong{display:block;margin-top:6px;font-size:16px;color:#102235}
    .payment-status-card{
        margin-top:24px;
        padding:22px;
        border-radius:24px;
        border:1px solid rgba(8,27,46,.08);
        background:#fff;
        display:grid;
        grid-template-columns:auto minmax(0,1fr);
        gap:18px;
        align-items:start;
    }
    .payment-status-card.is-processing{
        background:linear-gradient(180deg,#effaf9 0%, #ffffff 100%);
        border-color:rgba(14,165,164,.22);
    }
    .payment-status-card.is-approved{
        background:linear-gradient(180deg,#ecfdf3 0%, #ffffff 100%);
        border-color:rgba(21,127,91,.24);
    }
    .payment-status-card.is-rejected{
        background:linear-gradient(180deg,#fff3f2 0%, #ffffff 100%);
        border-color:rgba(180,35,24,.2);
    }
    .payment-status-card.is-pending{
        background:linear-gradient(180deg,#fff9ee 0%, #ffffff 100%);
        border-color:rgba(245,158,11,.2);
    }
    .status-orb{
        width:70px;
        height:70px;
        border-radius:999px;
        display:grid;
        place-items:center;
        font:900 24px/1 "Space Grotesk","Manrope",sans-serif;
        position:relative;
    }
    .payment-status-card.is-processing .status-orb{
        color:#0f766e;
        background:rgba(14,165,164,.14);
    }
    .payment-status-card.is-approved .status-orb{
        color:#157f5b;
        background:rgba(21,127,91,.12);
    }
    .payment-status-card.is-rejected .status-orb{
        color:#b42318;
        background:rgba(180,35,24,.1);
    }
    .payment-status-card.is-pending .status-orb{
        color:#b45309;
        background:rgba(245,158,11,.12);
    }
    .status-orb.is-processing::after{
        content:"";
        position:absolute;
        inset:-6px;
        border-radius:999px;
        border:2px solid rgba(14,165,164,.18);
        border-top-color:#0ea5a4;
        animation:payment-spin 1s linear infinite;
    }
    .status-copy h3{font-size:26px;color:#081b2e}
    .status-copy p{margin:10px 0 0;color:#4f6175;line-height:1.7}
    .status-hint{
        margin-top:12px;
        color:#607284;
        font-size:13px;
        line-height:1.7;
    }
    .processing-banner{
        margin-top:18px;
        padding:16px 18px;
        border-radius:18px;
        background:#081b2e;
        color:#eff7ff;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:14px;
        flex-wrap:wrap;
    }
    .processing-banner strong{font-size:15px}
    .processing-banner span{color:rgba(239,247,255,.78);font-size:13px;line-height:1.6}
    .payment-methods{display:grid;gap:18px;margin-top:24px}
    .payment-box{
        padding:22px;
        border-radius:24px;
        border:1px solid rgba(8,27,46,.08);
        background:#fff;
    }
    .payment-box h3{font-size:24px;color:#081b2e}
    .payment-box p{margin:10px 0 0;color:#5c6b7c;line-height:1.7}
    .payment-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px}
    .qr-grid{
        display:grid;
        grid-template-columns:220px minmax(0,1fr);
        gap:18px;
        align-items:start;
        margin-top:18px;
    }
    .qr-box{
        min-height:220px;
        display:grid;
        place-items:center;
        border-radius:20px;
        background:#f8fbff;
        border:1px solid rgba(8,27,46,.08);
        padding:18px;
    }
    .qr-box img{max-width:100%;height:auto}
    .field{display:grid;gap:8px}
    .field textarea{
        width:100%;
        min-height:132px;
        resize:vertical;
        border-radius:16px;
        border:1px solid rgba(8,27,46,.12);
        background:#fff;
        padding:14px 16px;
        color:#102235;
        font:600 14px/1.6 "Manrope","Segoe UI",sans-serif;
    }
    .note{
        margin-top:14px;
        color:#607284;
        font-size:13px;
        line-height:1.7;
    }
    .history-list{display:grid;gap:12px;margin-top:20px}
    .history-item{
        padding:18px;
        border-radius:20px;
        border:1px solid rgba(8,27,46,.08);
        background:#fff;
    }
    .history-head{
        display:flex;
        justify-content:space-between;
        gap:12px;
        align-items:flex-start;
        flex-wrap:wrap;
    }
    .history-meta{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:10px;
        margin-top:14px;
    }
    .history-meta div{
        padding:12px 14px;
        border-radius:16px;
        background:#f8fbff;
        border:1px solid rgba(8,27,46,.06);
    }
    .history-meta span{display:block;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#607284}
    .history-meta strong{display:block;margin-top:6px;font-size:14px;color:#102235}
    .badge{
        display:inline-flex;
        align-items:center;
        padding:8px 12px;
        border-radius:999px;
        font-size:12px;
        font-weight:800;
        letter-spacing:.05em;
        text-transform:uppercase;
    }
    .badge.success{background:#ecfdf3;color:#157f5b}
    .badge.warning{background:#fff7e6;color:#b45309}
    .badge.neutral{background:#edf2f7;color:#334155}
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
    @keyframes payment-spin{
        to{transform:rotate(360deg)}
    }
    @media (max-width:1020px){
        .hero,.content-grid{grid-template-columns:1fr}
    }
    @media (max-width:760px){
        .summary-grid,.status-strip,.history-meta{grid-template-columns:1fr}
        .qr-grid{grid-template-columns:1fr}
        .payment-status-card{grid-template-columns:1fr}
        .hero-copy,.section-card,.summary-card,.aside-card{padding:22px}
        .topbar{align-items:flex-start;flex-direction:column}
    }
</style>

<div class="page-shell">
    <div class="container">
        <div class="topbar">
            <a class="brand" href="<?= htmlspecialchars(base_url('/')) ?>">
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="MesiMenu">
            </a>
            <a class="btn btn-secondary" href="<?= htmlspecialchars(base_url('/login')) ?>">Entrar na conta</a>
        </div>

        <section class="hero">
            <article class="card hero-copy">
                <span class="eyebrow">Etapa financeira</span>
                <h1>Conclua o pagamento e ative o MesiMenu para sua empresa.</h1>
                <p>Seu cadastro foi recebido. Agora escolha a melhor forma de pagamento para liberar o acesso e começar a configurar cardápio digital, pedidos e gestão em uma única plataforma.</p>
                <div class="hero-points">
                    <div>Cadastro salvo para <strong><?= htmlspecialchars((string) ($company['name'] ?? 'Empresa')) ?></strong>.</div>
                    <div>Plano e ciclo já estão vinculados à sua contratação.</div>
                    <div>Pagamento confirmado, acesso liberado para iniciar a operação.</div>
                </div>

                <?php if (!empty($flashSuccess)): ?>
                    <div class="flash success"><?= htmlspecialchars((string) $flashSuccess) ?></div>
                <?php endif; ?>
                <?php if (!empty($flashError)): ?>
                    <div class="flash error"><?= htmlspecialchars((string) $flashError) ?></div>
                <?php endif; ?>
            </article>

            <aside class="card summary-card">
                <h2><?= htmlspecialchars((string) ($planSummary['name'] ?? 'Plano')) ?></h2>
                <p><?= htmlspecialchars((string) (($planSummary['description'] ?? '') !== '' ? $planSummary['description'] : 'Plano escolhido para sua empresa com recursos para vender melhor, organizar pedidos e simplificar a rotina.')) ?></p>
                <div class="summary-price">
                    <strong><?= htmlspecialchars($formatMoney(isset($planSummary['amount']) ? (float) $planSummary['amount'] : null)) ?></strong>
                    <span>/ <?= htmlspecialchars((string) ($planSummary['billing_cycle'] ?? 'mensal')) ?></span>
                </div>
                <div class="summary-grid">
                    <div>
                        <span>Usuários</span>
                        <strong><?= htmlspecialchars($formatLimit($planSummary['max_users'] ?? null, 'usuários')) ?></strong>
                    </div>
                    <div>
                        <span>Produtos</span>
                        <strong><?= htmlspecialchars($formatLimit($planSummary['max_products'] ?? null, 'produtos')) ?></strong>
                    </div>
                    <div>
                        <span>Mesas</span>
                        <strong><?= htmlspecialchars($formatLimit($planSummary['max_tables'] ?? null, 'mesas')) ?></strong>
                    </div>
                </div>
                <?php if (!empty($planSummary['feature_labels'])): ?>
                    <ul class="summary-features">
                        <?php foreach ((array) ($planSummary['feature_labels'] ?? []) as $featureLabel): ?>
                            <li><?= htmlspecialchars((string) $featureLabel) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </aside>
        </section>

        <section class="content-grid">
            <article class="card section-card">
                <h2>Escolha como deseja pagar</h2>
                <p>Finalize a assinatura por PIX ou cartão. Depois da confirmação, o acesso da empresa é liberado para iniciar a configuração do MesiMenu.</p>

                <div class="status-strip">
                    <div>
                        <span>Empresa</span>
                        <strong><?= htmlspecialchars((string) ($company['name'] ?? '-')) ?></strong>
                    </div>
                    <div>
                        <span>Status</span>
                        <strong><?= htmlspecialchars(status_label('subscription_status', (string) ($subscription['status'] ?? 'trial'))) ?></strong>
                    </div>
                    <div>
                        <span>Valor atual</span>
                        <strong><?= htmlspecialchars($currentPaymentId > 0 ? $formatMoney((float) ($currentPayment['amount'] ?? 0)) : 'Aguardando escolha') ?></strong>
                    </div>
                </div>

                <div class="payment-status-card is-<?= htmlspecialchars($stateTone) ?>" data-status-card>
                    <div class="status-orb<?= $stateTone === 'processing' ? ' is-processing' : '' ?>" data-status-icon>
                        <?= htmlspecialchars($stateTone === 'approved' ? 'OK' : ($stateTone === 'rejected' ? '!' : ($stateTone === 'processing' ? '...' : '...'))) ?>
                    </div>
                    <div class="status-copy">
                        <h3 data-status-title><?= htmlspecialchars($stateTitle) ?></h3>
                        <p data-status-message><?= htmlspecialchars($stateMessage) ?></p>
                        <?php if ($stateHint !== ''): ?>
                            <div class="status-hint" data-status-hint><?= htmlspecialchars($stateHint) ?></div>
                        <?php else: ?>
                            <div class="status-hint" data-status-hint hidden></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($stateTone === 'processing'): ?>
                    <div class="processing-banner" data-processing-banner>
                        <div>
                            <strong>Estamos acompanhando seu pagamento</strong>
                            <span>A página atualiza o status automaticamente e libera o acesso assim que a confirmação chegar.</span>
                        </div>
                        <button class="btn btn-secondary" type="button" data-refresh-payment>Atualizar agora</button>
                    </div>
                <?php endif; ?>

                <div class="payment-methods">
                    <section class="payment-box">
                        <h3>PIX</h3>
                        <p>Escolha PIX para pagar agora e acelerar a liberação da sua conta assim que a confirmação for recebida.</p>
                        <div class="payment-actions">
                            <form method="POST" action="<?= htmlspecialchars(base_url('/cadastro/pagamento/pix')) ?>">
                                <?= form_security_fields('marketing.public.payment.pix') ?>
                                <button class="btn btn-primary" type="submit"><?= $hasPixPayload ? 'Atualizar QR PIX' : 'Gerar QR PIX' ?></button>
                            </form>
                            <?php if (trim((string) ($currentPayment['pix_ticket_url'] ?? '')) !== ''): ?>
                                <a class="btn btn-secondary" href="<?= htmlspecialchars((string) $currentPayment['pix_ticket_url']) ?>" target="_blank" rel="noopener">Abrir tela do PIX</a>
                            <?php endif; ?>
                            <button class="btn btn-secondary" type="button" data-refresh-payment>Verificar pagamento</button>
                        </div>

                        <?php if ($hasPixPayload): ?>
                            <div class="qr-grid">
                                <div class="qr-box">
                                    <?php if (trim((string) ($currentPayment['pix_qr_image_base64'] ?? '')) !== ''): ?>
                                        <img src="data:image/png;base64,<?= htmlspecialchars((string) $currentPayment['pix_qr_image_base64']) ?>" alt="QR Code PIX">
                                    <?php else: ?>
                                        <span>QR disponível pelo código copia e cola.</span>
                                    <?php endif; ?>
                                </div>
                                <div class="field">
                                    <label for="pix_copy_paste">PIX copia e cola</label>
                                    <textarea id="pix_copy_paste" readonly><?= htmlspecialchars((string) (($currentPayment['pix_qr_payload'] ?? '') !== '' ? $currentPayment['pix_qr_payload'] : ($currentPayment['pix_code'] ?? ''))) ?></textarea>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="note" data-payment-feedback>
                            Depois de pagar, clique em verificar pagamento ou aguarde a atualização automática da página.
                        </div>
                    </section>

                    <section class="payment-box">
                        <h3>Cartão</h3>
                        <p>Use cartão para concluir a assinatura com recorrência no ciclo mensal ou anual escolhido.</p>
                        <div class="payment-actions">
                            <form method="POST" action="<?= htmlspecialchars(base_url('/cadastro/pagamento/cartao')) ?>" target="_blank" rel="noopener">
                                <?= form_security_fields('marketing.public.payment.card') ?>
                                <button class="btn btn-dark" type="submit">Pagar com cartão</button>
                            </form>
                            <button class="btn btn-secondary" type="button" data-refresh-payment>Verificar pagamento</button>
                        </div>
                        <div class="note">
                            Ao concluir o checkout, volte para esta página. Se o pagamento já estiver confirmado, você será direcionado para acessar a conta.
                        </div>
                    </section>
                </div>

                <div class="history-list">
                    <?php if ($paymentHistory === []): ?>
                        <div class="history-item">
                            <div class="history-head">
                                <div>
                                    <h3>Histórico de pagamento</h3>
                                    <p>Assim que houver uma cobrança registrada, ela aparecerá aqui para acompanhamento da assinatura.</p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($paymentHistory as $payment): ?>
                            <?php
                            $status = strtolower(trim((string) ($payment['status'] ?? '')));
                            $badgeClass = $status === 'pago'
                                ? 'success'
                                : ($status === 'pendente' ? 'warning' : 'neutral');
                            ?>
                            <div class="history-item">
                                <div class="history-head">
                                    <div>
                                        <h3>Referência <?= htmlspecialchars(sprintf('%02d/%04d', (int) ($payment['reference_month'] ?? 0), (int) ($payment['reference_year'] ?? 0))) ?></h3>
                                        <p>Método: <?= htmlspecialchars($paymentMethodLabels[(string) ($payment['payment_method'] ?? 'pix')] ?? ucfirst((string) ($payment['payment_method'] ?? 'pix'))) ?></p>
                                    </div>
                                    <span class="badge <?= htmlspecialchars($badgeClass) ?>">
                                        <?= htmlspecialchars(status_label('subscription_payment_status', (string) ($payment['status'] ?? ''))) ?>
                                    </span>
                                </div>
                                <div class="history-meta">
                                    <div>
                                        <span>Valor</span>
                                        <strong><?= htmlspecialchars($formatMoney((float) ($payment['amount'] ?? 0))) ?></strong>
                                    </div>
                                    <div>
                                        <span>Vencimento</span>
                                        <strong><?= htmlspecialchars($formatDate((string) ($payment['due_date'] ?? ''))) ?></strong>
                                    </div>
                                    <div>
                                        <span>Pago em</span>
                                        <strong><?= htmlspecialchars($formatDate((string) ($payment['paid_at'] ?? ''), true)) ?></strong>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </article>

            <aside class="card aside-card">
                <h3>Depois do pagamento</h3>
                <p>Após a confirmação, sua empresa recebe acesso ao painel para configurar cardápio, produtos, mesas, pedidos e rotina de atendimento.</p>
                <ul class="aside-list">
                    <li>1. A assinatura é ativada no plano e ciclo escolhidos.</li>
                    <li>2. O administrador principal passa a acessar o painel da empresa.</li>
                    <li>3. O login será feito com o e-mail <strong><?= htmlspecialchars((string) ($company['email'] ?? '-')) ?></strong>.</li>
                </ul>
            </aside>
        </section>
    </div>
</div>

<script>
(() => {
    const refreshButtons = Array.from(document.querySelectorAll('[data-refresh-payment]'));
    const feedback = document.querySelector('[data-payment-feedback]');
    const statusCard = document.querySelector('[data-status-card]');
    const statusTitle = document.querySelector('[data-status-title]');
    const statusMessage = document.querySelector('[data-status-message]');
    const statusHint = document.querySelector('[data-status-hint]');
    const statusIcon = document.querySelector('[data-status-icon]');
    const pollEndpoint = <?= json_encode(base_url('/cadastro/pagamento/status')) ?>;
    const shouldPoll = <?= json_encode(!empty($gateway['configured']) && $hasGatewayTracking) ?>;
    let isRefreshing = false;

    const stateMap = {
        approved: { tone: 'approved', icon: 'OK' },
        processing: { tone: 'processing', icon: '...' },
        rejected: { tone: 'rejected', icon: '!' },
        pending: { tone: 'pending', icon: '...' }
    };

    const setFeedback = (message) => {
        if (feedback instanceof HTMLElement) {
            feedback.textContent = message;
        }
    };

    const applyState = (payload) => {
        if (!(statusCard instanceof HTMLElement) || !(statusTitle instanceof HTMLElement) || !(statusMessage instanceof HTMLElement)) {
            return;
        }

        const key = typeof payload.state_key === 'string' && payload.state_key !== '' ? payload.state_key : 'pending';
        const state = stateMap[key] || stateMap.pending;
        statusCard.classList.remove('is-approved', 'is-processing', 'is-rejected', 'is-pending');
        statusCard.classList.add(`is-${state.tone}`);

        if (statusIcon instanceof HTMLElement) {
            statusIcon.textContent = state.icon;
            statusIcon.classList.toggle('is-processing', state.tone === 'processing');
        }

        statusTitle.textContent = payload.state_title || 'Pagamento pendente';
        statusMessage.textContent = payload.state_message || 'Aguardando a confirmação para liberar seu acesso.';

        if (statusHint instanceof HTMLElement) {
            const hint = payload.state_hint || '';
            statusHint.textContent = hint;
            statusHint.hidden = hint === '';
        }
    };

    const refreshStatus = async () => {
        if (isRefreshing) {
            return;
        }

        isRefreshing = true;
        setFeedback('Verificando se o pagamento já foi confirmado...');

        try {
            const response = await fetch(pollEndpoint, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            const payload = await response.json();

            if (!response.ok || payload.ok === false) {
                setFeedback(payload.message || 'Não foi possível verificar o pagamento agora.');
                return;
            }

            if (payload.access_granted && payload.redirect_url) {
                window.location.href = payload.redirect_url;
                return;
            }

            applyState(payload);
            setFeedback(payload.state_message || 'Pagamento ainda em confirmação. Se você acabou de pagar, aguarde alguns instantes e verifique novamente.');
        } catch (error) {
            setFeedback('Não foi possível verificar o pagamento neste momento. Tente novamente em alguns segundos.');
        } finally {
            isRefreshing = false;
        }
    };

    refreshButtons.forEach((button) => {
        button.addEventListener('click', refreshStatus);
    });

    if (shouldPoll) {
        window.setInterval(refreshStatus, 15000);
    }
})();
</script>
