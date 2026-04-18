<?php
$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
?>

<style>
    .saas-charge-create{display:grid;gap:16px}
    .saas-charge-create-hero{border:1px solid #c7d2fe;background:linear-gradient(125deg,var(--theme-main-card,#0f172a) 0%,#1d4ed8 52%,#0f766e 100%);color:#fff;border-radius:18px;padding:20px;position:relative;overflow:hidden}
    .saas-charge-create-hero:before{content:"";position:absolute;top:-52px;right:-26px;width:190px;height:190px;border-radius:999px;background:rgba(255,255,255,.12)}
    .saas-charge-create-hero:after{content:"";position:absolute;bottom:-66px;left:-34px;width:166px;height:166px;border-radius:999px;background:rgba(255,255,255,.08)}
    .saas-charge-create-hero-body{position:relative;z-index:1;display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
    .saas-charge-create-hero h1{margin:0 0 8px;font-size:28px}
    .saas-charge-create-hero p{margin:0;max-width:820px;color:#dbeafe;line-height:1.55}
    .saas-charge-create-pills{display:flex;gap:8px;flex-wrap:wrap}
    .saas-charge-create-pill{border:1px solid rgba(255,255,255,.24);background:rgba(15,23,42,.35);padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700}

    .saas-charge-create-layout{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(300px,.85fr);gap:16px;align-items:start}
    .saas-charge-create-main,.saas-charge-create-side{display:grid;gap:16px}
    .saas-charge-create-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
    .saas-charge-create-head h2,.saas-charge-create-head h3{margin:0}
    .saas-charge-create-note{margin:4px 0 0;color:#64748b;font-size:13px;line-height:1.45}
    .saas-charge-create-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .saas-charge-create-grid .field{margin:0}
    .saas-charge-create-grid .field.full{grid-column:1 / -1}
    .saas-charge-create-footer{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
    .saas-charge-create-footer p{margin:0;max-width:760px;font-size:12px;color:#64748b;line-height:1.45}

    .saas-charge-create-summary-grid{display:grid;gap:8px}
    .saas-charge-create-summary-item{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px}
    .saas-charge-create-summary-item strong{color:#0f172a}
    .saas-charge-create-summary-item span{padding:4px 8px;border-radius:999px;background:#dbeafe;color:#1e3a8a;font-size:11px;font-weight:700}
    .saas-charge-create-flow{border:1px solid #c7d2fe;background:linear-gradient(130deg,#eef2ff 0%,#f8fafc 100%);border-radius:14px;padding:14px}
    .saas-charge-create-flow h3{margin:0 0 8px;color:#1e1b4b;font-size:16px}
    .saas-charge-create-flow p{margin:0;color:#3730a3;font-size:13px;line-height:1.5}
    .saas-charge-create-flow ul{margin:10px 0 0;padding-left:18px;color:#312e81;font-size:13px;display:grid;gap:6px}

    @media (max-width:1180px){
        .saas-charge-create-layout{grid-template-columns:1fr}
    }
    @media (max-width:760px){
        .saas-charge-create-grid{grid-template-columns:1fr}
        .saas-charge-create-hero h1{font-size:22px}
    }
</style>

<div class="saas-charge-create">
    <div class="saas-charge-create-hero">
        <div class="saas-charge-create-hero-body">
            <div>
                <h1>Nova cobranca PIX</h1>
                <p>Esta tela cria a cobranca interna de forma objetiva. Ela ainda nao nasce automatica. O fluxo automatico so comeca depois que o administrativo abrir a lista e usar <strong>Gerar PIX no gateway</strong>.</p>
            </div>
            <div class="saas-charge-create-pills">
                <span class="saas-charge-create-pill">Passo 1: criar cobranca</span>
                <span class="saas-charge-create-pill">Passo 2: gerar PIX real</span>
                <span class="saas-charge-create-pill">Passo 3: confirmar automaticamente</span>
            </div>
        </div>
    </div>

    <div class="saas-charge-create-layout">
        <div class="saas-charge-create-main">
            <section class="card">
                <div class="saas-charge-create-head">
                    <div>
                        <h2>Cadastro da cobranca</h2>
                        <p class="saas-charge-create-note">Preencha somente os dados minimos da cobranca. O objetivo aqui nao e resolver o pagamento inteiro, e sim registrar a cobranca com clareza e preparar a entrada no fluxo automatico depois.</p>
                    </div>
                    <a class="btn secondary" href="<?= htmlspecialchars(base_url('/saas/subscription-payments')) ?>">Voltar para cobrancas</a>
                </div>

                <form method="POST" action="<?= htmlspecialchars(base_url('/saas/subscription-payments/store')) ?>" style="margin-top:16px">
                    <?= form_security_fields('saas.subscription_payments.store') ?>

                    <div class="saas-charge-create-grid">
                        <div class="field full">
                            <label for="subscription_id">Empresa e plano</label>
                            <select id="subscription_id" name="subscription_id" required>
                                <option value="">Selecione</option>
                                <?php foreach ($subscriptions as $subscription): ?>
                                    <option value="<?= (int) ($subscription['id'] ?? 0) ?>">
                                        <?= htmlspecialchars((string) ($subscription['company_name'] ?? 'Empresa')) ?>
                                        - <?= htmlspecialchars((string) ($subscription['plan_name'] ?? 'Plano')) ?>
                                        - <?= htmlspecialchars(status_label('billing_cycle', $subscription['billing_cycle'] ?? null)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="reference_month">Mes de referencia</label>
                            <input id="reference_month" name="reference_month" type="number" min="1" max="12" required>
                        </div>
                        <div class="field">
                            <label for="reference_year">Ano de referencia</label>
                            <input id="reference_year" name="reference_year" type="number" min="2020" max="2100" value="<?= date('Y') ?>" required>
                        </div>

                        <div class="field">
                            <label for="amount">Valor da cobranca</label>
                            <input id="amount" name="amount" type="number" min="0" step="0.01" required>
                        </div>
                        <div class="field">
                            <label for="due_date">Data de vencimento</label>
                            <input id="due_date" name="due_date" type="date" required>
                        </div>

                        <div class="field full">
                            <label for="transaction_reference">Observacao ou referencia interna</label>
                            <input id="transaction_reference" name="transaction_reference" type="text" placeholder="Opcional">
                        </div>
                    </div>

                    <div class="saas-charge-create-footer" style="margin-top:12px">
                        <p>Depois de salvar, a cobranca aparecera na fila administrativa. Se a intencao for PIX com confirmacao automatica, o proximo passo obrigatorio e <strong>Gerar PIX no gateway</strong>.</p>
                        <button class="btn" type="submit">Criar cobranca</button>
                    </div>
                </form>
            </section>
        </div>

        <aside class="saas-charge-create-side">
            <section class="card">
                <div class="saas-charge-create-head">
                    <div>
                        <h3>Fluxo correto</h3>
                        <p class="saas-charge-create-note">A tela precisa impedir ambiguidade operacional. O erro era parecer que criar a cobranca ja bastava para automacao.</p>
                    </div>
                </div>
                <div class="saas-charge-create-summary-grid">
                    <div class="saas-charge-create-summary-item"><strong>Passo 1</strong><span>Criar cobranca interna</span></div>
                    <div class="saas-charge-create-summary-item"><strong>Passo 2</strong><span>Gerar PIX no gateway</span></div>
                    <div class="saas-charge-create-summary-item"><strong>Passo 3</strong><span>Usuario paga o PIX</span></div>
                    <div class="saas-charge-create-summary-item"><strong>Passo 4</strong><span>Status muda para pago</span></div>
                </div>
            </section>

            <section class="saas-charge-create-flow">
                <h3>Critério de entendimento</h3>
                <p>Se a cobranca foi criada aqui, mas nao ganhou `gateway_payment_id` depois, ela continua manual. Esse corte precisa ficar evidente para o administrativo.</p>
                <ul>
                    <li>Criar cobranca nao gera PIX real por si so.</li>
                    <li>Automacao depende de vinculo real com o gateway.</li>
                    <li>Baixa manual e plano B, nao fluxo principal.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>
