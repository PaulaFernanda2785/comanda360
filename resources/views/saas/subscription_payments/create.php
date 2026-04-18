<div class="topbar">
    <div>
        <h1>Nova cobranca PIX</h1>
        <p class="muted">Crie uma cobranca manual apenas quando precisar ajustar ou complementar o financeiro da assinatura.</p>
    </div>
    <a class="btn secondary" href="<?= htmlspecialchars(base_url('/saas/subscription-payments')) ?>">Voltar</a>
</div>

<div class="card">
    <form method="POST" action="<?= htmlspecialchars(base_url('/saas/subscription-payments/store')) ?>">
        <?= form_security_fields('saas.subscription_payments.store') ?>
        <div class="field">
            <label for="subscription_id">Empresa e plano</label>
            <select id="subscription_id" name="subscription_id" required>
                <option value="">Selecione</option>
                <?php foreach (($subscriptions ?? []) as $subscription): ?>
                    <option value="<?= (int) $subscription['id'] ?>">
                        <?= htmlspecialchars((string) $subscription['company_name']) ?>
                        - <?= htmlspecialchars((string) $subscription['plan_name']) ?>
                        - <?= htmlspecialchars(status_label('billing_cycle', $subscription['billing_cycle'] ?? null)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grid two">
            <div class="field">
                <label for="reference_month">Mes de referencia</label>
                <input id="reference_month" name="reference_month" type="number" min="1" max="12" required>
            </div>
            <div class="field">
                <label for="reference_year">Ano de referencia</label>
                <input id="reference_year" name="reference_year" type="number" min="2020" max="2100" value="<?= date('Y') ?>" required>
            </div>
        </div>

        <div class="grid two">
            <div class="field">
                <label for="amount">Valor da cobranca</label>
                <input id="amount" name="amount" type="number" min="0" step="0.01" required>
            </div>
            <div class="field">
                <label for="due_date">Data de vencimento</label>
                <input id="due_date" name="due_date" type="date" required>
            </div>
        </div>

        <div class="field">
            <label for="transaction_reference">Observacao ou referencia interna</label>
            <input id="transaction_reference" name="transaction_reference" type="text" placeholder="Opcional">
        </div>

        <button class="btn" type="submit">Criar cobranca</button>
    </form>
</div>
