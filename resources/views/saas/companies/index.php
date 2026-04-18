<?php
$companyPanel = is_array($companyPanel ?? null) ? $companyPanel : [];
$companies = is_array($companyPanel['companies'] ?? null) ? $companyPanel['companies'] : [];
$filters = is_array($companyPanel['filters'] ?? null) ? $companyPanel['filters'] : [];
$summary = is_array($companyPanel['summary'] ?? null) ? $companyPanel['summary'] : [];
$pagination = is_array($companyPanel['pagination'] ?? null) ? $companyPanel['pagination'] : [];
$plans = is_array($companyPanel['plans'] ?? null) ? $companyPanel['plans'] : [];
$canManageCompanies = (bool) ($canManageCompanies ?? false);

$companySearch = trim((string) ($filters['search'] ?? ''));
$companyStatus = trim((string) ($filters['status'] ?? ''));
$companySubscriptionStatus = trim((string) ($filters['subscription_status'] ?? ''));
$companyPlanId = (int) ($filters['plan_id'] ?? 0);

$companyTotal = (int) ($summary['total_companies'] ?? ($pagination['total'] ?? count($companies)));
$companyActiveCount = (int) ($summary['active_companies'] ?? 0);
$companyTestingCount = (int) ($summary['testing_companies'] ?? 0);
$companySuspendedCount = (int) ($summary['suspended_companies'] ?? 0);
$companyCanceledCount = (int) ($summary['canceled_companies'] ?? 0);
$companyTrialCount = (int) ($summary['trial_companies'] ?? 0);
$companyDelinquentCount = (int) ($summary['delinquent_companies'] ?? 0);
$companyActiveSubscriptionCount = (int) ($summary['active_subscription_companies'] ?? 0);
$lastCompanyCreatedAt = trim((string) ($summary['last_created_at'] ?? ''));

$companyPage = max(1, (int) ($pagination['page'] ?? 1));
$companyLastPage = max(1, (int) ($pagination['last_page'] ?? 1));
$companyFrom = (int) ($pagination['from'] ?? 0);
$companyTo = (int) ($pagination['to'] ?? 0);
$companyPages = is_array($pagination['pages'] ?? null) ? $pagination['pages'] : [];

$currentQuery = is_array($_GET ?? null) ? $_GET : [];
$returnQuery = http_build_query($currentQuery);

$statusOptions = [
    '' => 'Todos os status operacionais',
    'ativa' => 'Ativa',
    'teste' => 'Em teste',
    'suspensa' => 'Suspensa',
    'cancelada' => 'Cancelada',
];

$subscriptionStatusOptions = [
    '' => 'Todos os status da assinatura',
    'ativa' => 'Ativa',
    'trial' => 'Em teste',
    'inadimplente' => 'Inadimplente',
    'suspensa' => 'Suspensa',
    'cancelada' => 'Cancelada',
];

$billingCycleOptions = [
    'mensal' => 'Mensal',
    'anual' => 'Anual',
];

$buildCompaniesUrl = static function (array $overrides = []) use ($companySearch, $companyStatus, $companySubscriptionStatus, $companyPlanId): string {
    $params = array_merge([
        'company_search' => $companySearch,
        'company_status' => $companyStatus,
        'company_subscription_status' => $companySubscriptionStatus,
        'company_plan_id' => $companyPlanId > 0 ? (string) $companyPlanId : '',
    ], $overrides);

    foreach ($params as $key => $value) {
        if (trim((string) $value) === '') {
            unset($params[$key]);
        }
    }

    $query = http_build_query($params);
    return base_url('/saas/companies' . ($query !== '' ? '?' . $query : ''));
};

$formatDate = static function (mixed $value, bool $withTime = true): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '-';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return $raw;
    }

    return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $timestamp);
};

$dateInputValue = static function (mixed $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d', $timestamp);
};
?>

<style>
    .saas-company-page{display:grid;gap:16px}
    .saas-company-hero{border:1px solid #bfdbfe;background:linear-gradient(118deg,#0f172a 0%,#1d4ed8 55%,#0f766e 100%);color:#fff;border-radius:16px;padding:18px;position:relative;overflow:hidden}
    .saas-company-hero:before{content:"";position:absolute;top:-54px;right:-38px;width:216px;height:216px;border-radius:999px;background:rgba(255,255,255,.11)}
    .saas-company-hero:after{content:"";position:absolute;bottom:-74px;left:-32px;width:180px;height:180px;border-radius:999px;background:rgba(255,255,255,.08)}
    .saas-company-hero-body{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
    .saas-company-hero h1{margin:0 0 8px;font-size:26px}
    .saas-company-hero p{margin:0;color:#dbeafe;max-width:860px;line-height:1.5}
    .saas-company-pills{display:flex;gap:8px;flex-wrap:wrap}
    .saas-company-pill{border:1px solid rgba(255,255,255,.26);background:rgba(15,23,42,.35);padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700}

    .saas-company-layout{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(320px,.95fr);gap:16px;align-items:start}
    .saas-company-main,.saas-company-side{display:grid;gap:16px}

    .saas-company-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
    .saas-company-head h2,.saas-company-head h3{margin:0}
    .saas-company-note{margin:4px 0 0;color:#64748b;font-size:13px;line-height:1.45}
    .saas-company-badges{display:flex;gap:8px;flex-wrap:wrap}

    .saas-company-filter-grid{display:grid;grid-template-columns:1.35fr 1fr 1fr 1fr auto;gap:10px;align-items:end}
    .saas-company-filter-grid .field{margin:0}
    .saas-company-filter-actions{display:flex;gap:8px;flex-wrap:wrap}

    .saas-company-list{display:grid;gap:12px}
    .saas-company-card{border:1px solid #dbeafe;border-radius:14px;background:linear-gradient(180deg,#fff,#f8fafc);padding:14px;display:grid;gap:12px}
    .saas-company-card-top{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
    .saas-company-title{display:grid;gap:4px}
    .saas-company-title strong{font-size:16px;color:#0f172a}
    .saas-company-title small{font-size:12px;color:#64748b;line-height:1.4}
    .saas-company-info{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}
    .saas-company-box{border:1px solid #e2e8f0;background:#fff;border-radius:10px;padding:10px}
    .saas-company-box span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
    .saas-company-box strong{display:block;margin-top:4px;font-size:13px;color:#0f172a;overflow-wrap:anywhere}
    .saas-company-box strong.amount{font-size:15px}

    .saas-company-details{border-top:1px dashed #cbd5e1;padding-top:12px}
    .saas-company-details summary{display:flex;justify-content:space-between;align-items:center;gap:10px;cursor:pointer;list-style:none;font-weight:700;color:#0f172a}
    .saas-company-details summary::-webkit-details-marker{display:none}
    .saas-company-details-toggle{font-size:11px;color:#1d4ed8;background:#dbeafe;border:1px solid #bfdbfe;border-radius:999px;padding:4px 9px;font-weight:700}
    .saas-company-details[open] .saas-company-details-toggle{background:#eff6ff}
    .saas-company-details-body{display:grid;gap:12px;margin-top:12px}

    .saas-company-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .saas-company-form-grid .field{margin:0}
    .saas-company-form-grid .field.full{grid-column:1 / -1}
    .saas-company-form-footer{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
    .saas-company-form-note{font-size:12px;color:#64748b;line-height:1.45;max-width:760px}
    .saas-company-danger{display:flex;justify-content:flex-end}
    .saas-company-danger .btn{background:#b91c1c}

    .saas-company-summary-grid{display:grid;gap:8px}
    .saas-company-summary-item{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px}
    .saas-company-summary-item strong{color:#0f172a}
    .saas-company-summary-item span{padding:4px 8px;border-radius:999px;background:#dbeafe;color:#1e3a8a;font-size:11px;font-weight:700}

    .saas-company-create-card{display:grid;gap:12px}
    .saas-company-governance{border:1px solid #c7d2fe;background:linear-gradient(130deg,#eef2ff 0%,#f8fafc 100%);border-radius:14px;padding:14px}
    .saas-company-governance h3{margin:0 0 8px;color:#1e1b4b;font-size:16px}
    .saas-company-governance p{margin:0;color:#3730a3;font-size:13px;line-height:1.5}
    .saas-company-governance ul{margin:10px 0 0;padding-left:18px;color:#312e81;font-size:13px;display:grid;gap:6px}

    .saas-company-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
    .saas-company-pagination-controls{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .saas-page-btn{display:inline-block;padding:8px 11px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#0f172a;text-decoration:none}
    .saas-page-btn.is-active{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
    .saas-page-ellipsis{color:#64748b;padding:0 2px}

    @media (max-width:1180px){
        .saas-company-layout{grid-template-columns:1fr}
    }
    @media (max-width:980px){
        .saas-company-filter-grid,.saas-company-info,.saas-company-form-grid{grid-template-columns:1fr 1fr}
    }
    @media (max-width:760px){
        .saas-company-filter-grid,.saas-company-info,.saas-company-form-grid{grid-template-columns:1fr}
        .saas-company-hero h1{font-size:22px}
    }
</style>

<div class="saas-company-page">
    <div class="saas-company-hero">
        <div class="saas-company-hero-body">
            <div>
                <h1>Empresas</h1>
                <p>Painel institucional para gerir empresas cadastradas manualmente no SaaS e empresas originadas pelo fluxo publico de assinatura. Aqui o administrador consolida dados cadastrais, plano, estado operacional, situacao da assinatura e cancelamento da conta.</p>
            </div>
            <div class="saas-company-pills">
                <span class="saas-company-pill">Empresas filtradas: <?= htmlspecialchars((string) $companyTotal) ?></span>
                <span class="saas-company-pill">Ativas: <?= htmlspecialchars((string) $companyActiveCount) ?></span>
                <span class="saas-company-pill">Em teste: <?= htmlspecialchars((string) $companyTrialCount) ?></span>
                <span class="saas-company-pill">Inadimplentes: <?= htmlspecialchars((string) $companyDelinquentCount) ?></span>
            </div>
        </div>
    </div>

    <div class="saas-company-layout">
        <div class="saas-company-main">
            <section class="card">
                <div class="saas-company-head">
                    <div>
                        <h2>Painel de gestao</h2>
                        <p class="saas-company-note">Use filtros para localizar empresas por nome, slug, contato, status ou plano. O cadastro manual e o cadastro originado por assinatura publica ficam centralizados na mesma fila administrativa.</p>
                    </div>
                    <div class="saas-company-badges">
                        <?php if ($lastCompanyCreatedAt !== ''): ?>
                            <span class="badge">Ultimo cadastro: <?= htmlspecialchars($formatDate($lastCompanyCreatedAt)) ?></span>
                        <?php endif; ?>
                        <span class="badge">Assinaturas ativas: <?= htmlspecialchars((string) $companyActiveSubscriptionCount) ?></span>
                    </div>
                </div>

                <form method="GET" action="<?= htmlspecialchars(base_url('/saas/companies')) ?>">
                    <div class="saas-company-filter-grid">
                        <div class="field">
                            <label for="company_search">Busca inteligente</label>
                            <input id="company_search" name="company_search" type="text" value="<?= htmlspecialchars($companySearch) ?>" placeholder="Nome, slug, email, telefone, documento ou ID">
                        </div>
                        <div class="field">
                            <label for="company_status">Status operacional</label>
                            <select id="company_status" name="company_status">
                                <?php foreach ($statusOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= $companyStatus === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="company_subscription_status">Status da assinatura</label>
                            <select id="company_subscription_status" name="company_subscription_status">
                                <?php foreach ($subscriptionStatusOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= $companySubscriptionStatus === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="company_plan_id">Plano</label>
                            <select id="company_plan_id" name="company_plan_id">
                                <option value="">Todos os planos</option>
                                <?php foreach ($plans as $plan): ?>
                                    <?php $planId = (int) ($plan['id'] ?? 0); ?>
                                    <option value="<?= $planId ?>" <?= $companyPlanId === $planId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) ($plan['name'] ?? 'Plano')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="saas-company-filter-actions">
                            <button class="btn" type="submit">Aplicar</button>
                            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/saas/companies')) ?>">Limpar</a>
                        </div>
                    </div>
                </form>

                <?php if ($companies === []): ?>
                    <div class="card" style="margin-top:16px;padding:14px;border:1px dashed #cbd5e1;box-shadow:none">
                        <?= ($companySearch !== '' || $companyStatus !== '' || $companySubscriptionStatus !== '' || $companyPlanId > 0)
                            ? 'Nenhuma empresa encontrada para os filtros aplicados.'
                            : 'Nenhuma empresa cadastrada ate o momento.' ?>
                    </div>
                <?php else: ?>
                    <div class="saas-company-list" style="margin-top:16px">
                        <?php foreach ($companies as $company): ?>
                            <?php
                            $companyId = (int) ($company['id'] ?? 0);
                            $companyName = trim((string) ($company['name'] ?? 'Empresa'));
                            $companyPlanName = trim((string) ($company['plan_name'] ?? 'Sem plano'));
                            $companyBillingCycle = trim((string) ($company['billing_cycle'] ?? 'mensal'));
                            $companyAmount = (float) ($company['amount'] ?? 0);
                            $companyStatusValue = trim((string) ($company['status'] ?? ''));
                            $companySubscriptionStatusValue = trim((string) ($company['subscription_status'] ?? ''));
                            $operationalBadgeClass = status_badge_class('company_status', $companyStatusValue);
                            $subscriptionBadgeClass = status_badge_class('company_subscription_status', $companySubscriptionStatusValue);
                            $isCanceledCompany = $companyStatusValue === 'cancelada' && $companySubscriptionStatusValue === 'cancelada';
                            $nextChargeDueDate = trim((string) ($company['next_charge_due_date'] ?? ''));
                            $effectiveEndDate = $companySubscriptionStatusValue === 'trial'
                                ? ($company['trial_ends_at'] ?? '')
                                : ($company['subscription_ends_at'] ?? $company['subscription_record_ends_at'] ?? '');
                            ?>
                            <article class="saas-company-card">
                                <div class="saas-company-card-top">
                                    <div class="saas-company-title">
                                        <strong>#<?= $companyId ?> - <?= htmlspecialchars($companyName) ?></strong>
                                        <small><?= htmlspecialchars((string) ($company['slug'] ?? '-')) ?> · <?= htmlspecialchars((string) ($company['email'] ?? '-')) ?> · <?= htmlspecialchars((string) ($company['phone'] ?? $company['whatsapp'] ?? '-')) ?></small>
                                    </div>
                                    <div class="saas-company-badges">
                                        <span class="badge <?= htmlspecialchars($operationalBadgeClass) ?>"><?= htmlspecialchars(status_label('company_status', $companyStatusValue)) ?></span>
                                        <span class="badge <?= htmlspecialchars($subscriptionBadgeClass) ?>"><?= htmlspecialchars(status_label('company_subscription_status', $companySubscriptionStatusValue)) ?></span>
                                    </div>
                                </div>

                                <div class="saas-company-info">
                                    <div class="saas-company-box">
                                        <span>Plano</span>
                                        <strong><?= htmlspecialchars($companyPlanName) ?></strong>
                                    </div>
                                    <div class="saas-company-box">
                                        <span>Ciclo e valor</span>
                                        <strong class="amount"><?= htmlspecialchars(status_label('billing_cycle', $companyBillingCycle)) ?> · R$ <?= number_format($companyAmount, 2, ',', '.') ?></strong>
                                    </div>
                                    <div class="saas-company-box">
                                        <span>Próxima cobrança</span>
                                        <strong><?= htmlspecialchars($formatDate($nextChargeDueDate, false)) ?></strong>
                                    </div>
                                    <div class="saas-company-box">
                                        <span>Cadastrada em</span>
                                        <strong><?= htmlspecialchars($formatDate($company['created_at'] ?? '')) ?></strong>
                                    </div>
                                </div>

                                <?php if ($canManageCompanies): ?>
                                    <details class="saas-company-details">
                                        <summary>
                                            <span>Editar empresa e contrato</span>
                                            <span class="saas-company-details-toggle">Expandir / recolher</span>
                                        </summary>

                                        <div class="saas-company-details-body">
                                            <form method="POST" action="<?= htmlspecialchars(base_url('/saas/companies/update')) ?>">
                                                <?= form_security_fields('saas.companies.update.' . $companyId) ?>
                                                <input type="hidden" name="company_id" value="<?= $companyId ?>">
                                                <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">

                                                <div class="saas-company-form-grid">
                                                    <div class="field">
                                                        <label for="company_name_<?= $companyId ?>">Nome da empresa</label>
                                                        <input id="company_name_<?= $companyId ?>" name="name" type="text" required value="<?= htmlspecialchars($companyName) ?>">
                                                    </div>
                                                    <div class="field">
                                                        <label for="company_slug_<?= $companyId ?>">Slug</label>
                                                        <input id="company_slug_<?= $companyId ?>" name="slug" type="text" value="<?= htmlspecialchars((string) ($company['slug'] ?? '')) ?>" placeholder="Opcional, gerado automaticamente se vazio">
                                                    </div>
                                                    <div class="field">
                                                        <label for="company_legal_name_<?= $companyId ?>">Razao social</label>
                                                        <input id="company_legal_name_<?= $companyId ?>" name="legal_name" type="text" value="<?= htmlspecialchars((string) ($company['legal_name'] ?? '')) ?>">
                                                    </div>
                                                    <div class="field">
                                                        <label for="company_document_<?= $companyId ?>">CPF/CNPJ</label>
                                                        <input id="company_document_<?= $companyId ?>" name="document_number" type="text" value="<?= htmlspecialchars((string) ($company['document_number'] ?? '')) ?>">
                                                    </div>
                                                    <div class="field">
                                                        <label for="company_email_<?= $companyId ?>">E-mail principal</label>
                                                        <input id="company_email_<?= $companyId ?>" name="email" type="email" required value="<?= htmlspecialchars((string) ($company['email'] ?? '')) ?>">
                                                    </div>
                                                    <div class="field">
                                                        <label for="company_phone_<?= $companyId ?>">Telefone</label>
                                                        <input id="company_phone_<?= $companyId ?>" name="phone" type="text" value="<?= htmlspecialchars((string) ($company['phone'] ?? '')) ?>">
                                                    </div>
                                                    <div class="field">
                                                        <label for="company_whatsapp_<?= $companyId ?>">WhatsApp</label>
                                                        <input id="company_whatsapp_<?= $companyId ?>" name="whatsapp" type="text" value="<?= htmlspecialchars((string) ($company['whatsapp'] ?? '')) ?>">
                                                    </div>
                                                    <div class="field">
                                                        <label for="company_plan_<?= $companyId ?>">Plano</label>
                                                        <select id="company_plan_<?= $companyId ?>" name="plan_id" required>
                                                            <option value="">Selecione</option>
                                                            <?php foreach ($plans as $plan): ?>
                                                                <?php $planId = (int) ($plan['id'] ?? 0); ?>
                                                                <option value="<?= $planId ?>" <?= (int) ($company['plan_id'] ?? 0) === $planId ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars((string) ($plan['name'] ?? 'Plano')) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="field">
                                                        <label for="company_status_edit_<?= $companyId ?>">Status operacional</label>
                                                        <select id="company_status_edit_<?= $companyId ?>" name="status" required>
                                                            <?php foreach (array_slice($statusOptions, 1, null, true) as $value => $label): ?>
                                                                <option value="<?= htmlspecialchars($value) ?>" <?= $companyStatusValue === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="field">
                                                        <label for="company_subscription_edit_<?= $companyId ?>">Status da assinatura</label>
                                                        <select id="company_subscription_edit_<?= $companyId ?>" name="subscription_status" required>
                                                            <?php foreach (array_slice($subscriptionStatusOptions, 1, null, true) as $value => $label): ?>
                                                                <option value="<?= htmlspecialchars($value) ?>" <?= $companySubscriptionStatusValue === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="field">
                                                        <label for="company_billing_cycle_<?= $companyId ?>">Ciclo de cobranca</label>
                                                        <select id="company_billing_cycle_<?= $companyId ?>" name="billing_cycle" required>
                                                            <?php foreach ($billingCycleOptions as $value => $label): ?>
                                                                <option value="<?= htmlspecialchars($value) ?>" <?= $companyBillingCycle === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="field">
                                                        <label for="company_amount_<?= $companyId ?>">Valor contratado</label>
                                                        <input id="company_amount_<?= $companyId ?>" name="amount" type="number" min="0" step="0.01" value="<?= htmlspecialchars(number_format($companyAmount, 2, '.', '')) ?>">
                                                    </div>
                                                    <div class="field">
                                                        <label for="company_starts_at_<?= $companyId ?>">Inicio da assinatura</label>
                                                        <input id="company_starts_at_<?= $companyId ?>" name="subscription_starts_at" type="date" value="<?= htmlspecialchars($dateInputValue($company['subscription_starts_at'] ?? $company['subscription_record_starts_at'] ?? '')) ?>">
                                                    </div>
                                                    <div class="field">
                                                        <label for="company_next_charge_due_date_<?= $companyId ?>">Vencimento da próxima cobrança</label>
                                                        <input id="company_next_charge_due_date_<?= $companyId ?>" name="next_charge_due_date" type="date" value="<?= htmlspecialchars($dateInputValue($company['next_charge_due_date'] ?? '')) ?>">
                                                    </div>
                                                    <div class="field">
                                                        <label for="company_trial_ends_at_<?= $companyId ?>">Fim do teste</label>
                                                        <input id="company_trial_ends_at_<?= $companyId ?>" name="trial_ends_at" type="date" value="<?= htmlspecialchars($dateInputValue($company['trial_ends_at'] ?? '')) ?>">
                                                    </div>
                                                    <div class="field">
                                                        <label for="company_subscription_ends_at_<?= $companyId ?>">Fim da assinatura</label>
                                                        <input id="company_subscription_ends_at_<?= $companyId ?>" name="subscription_ends_at" type="date" value="<?= htmlspecialchars($dateInputValue($company['subscription_ends_at'] ?? $company['subscription_record_ends_at'] ?? '')) ?>">
                                                    </div>
                                                </div>

                                                <div class="saas-company-form-footer" style="margin-top:12px">
                                                    <p class="saas-company-form-note">Edicao administrativa unifica cadastro operacional e retrato atual do contrato. O vencimento da próxima cobrança controla o alerta de atraso e o bloqueio no 4º dia corrido após o vencimento.</p>
                                                    <button class="btn" type="submit">Salvar ajustes</button>
                                                </div>
                                            </form>

                                            <?php if (!$isCanceledCompany): ?>
                                                <div class="saas-company-danger">
                                                    <form method="POST" action="<?= htmlspecialchars(base_url('/saas/companies/cancel')) ?>" onsubmit="return confirm('Confirmar cancelamento desta empresa? O historico sera preservado.');">
                                                        <?= form_security_fields('saas.companies.cancel.' . $companyId) ?>
                                                        <input type="hidden" name="company_id" value="<?= $companyId ?>">
                                                        <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">
                                                        <button class="btn" type="submit">Cancelar empresa</button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($companyLastPage > 1): ?>
                        <div class="saas-company-pagination">
                            <div class="muted">Exibindo <?= htmlspecialchars((string) $companyFrom) ?> a <?= htmlspecialchars((string) $companyTo) ?> de <?= htmlspecialchars((string) $companyTotal) ?> empresas.</div>
                            <div class="saas-company-pagination-controls">
                                <?php if ($companyPage > 1): ?>
                                    <a class="saas-page-btn" href="<?= htmlspecialchars($buildCompaniesUrl(['company_page' => $companyPage - 1])) ?>">Anterior</a>
                                <?php endif; ?>
                                <?php
                                $previousPage = null;
                                foreach ($companyPages as $pageNumber):
                                    $pageNumber = (int) $pageNumber;
                                    if ($previousPage !== null && $pageNumber - $previousPage > 1): ?>
                                        <span class="saas-page-ellipsis">...</span>
                                    <?php endif; ?>
                                    <a class="saas-page-btn<?= $pageNumber === $companyPage ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildCompaniesUrl(['company_page' => $pageNumber])) ?>"><?= $pageNumber ?></a>
                                    <?php
                                    $previousPage = $pageNumber;
                                endforeach;
                                ?>
                                <?php if ($companyPage < $companyLastPage): ?>
                                    <a class="saas-page-btn" href="<?= htmlspecialchars($buildCompaniesUrl(['company_page' => $companyPage + 1])) ?>">Proxima</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>

        <aside class="saas-company-side">
            <section class="card">
                <div class="saas-company-head">
                    <div>
                        <h3>Resumo institucional</h3>
                        <p class="saas-company-note">Panorama consolidado do recorte filtrado para operacao, cobranca e follow-up comercial.</p>
                    </div>
                </div>
                <div class="saas-company-summary-grid">
                    <div class="saas-company-summary-item"><strong>Total de empresas</strong><span><?= htmlspecialchars((string) $companyTotal) ?></span></div>
                    <div class="saas-company-summary-item"><strong>Ativas</strong><span><?= htmlspecialchars((string) $companyActiveCount) ?></span></div>
                    <div class="saas-company-summary-item"><strong>Em teste operacional</strong><span><?= htmlspecialchars((string) $companyTestingCount) ?></span></div>
                    <div class="saas-company-summary-item"><strong>Assinaturas em teste</strong><span><?= htmlspecialchars((string) $companyTrialCount) ?></span></div>
                    <div class="saas-company-summary-item"><strong>Inadimplentes</strong><span><?= htmlspecialchars((string) $companyDelinquentCount) ?></span></div>
                    <div class="saas-company-summary-item"><strong>Suspensas</strong><span><?= htmlspecialchars((string) $companySuspendedCount) ?></span></div>
                    <div class="saas-company-summary-item"><strong>Canceladas</strong><span><?= htmlspecialchars((string) $companyCanceledCount) ?></span></div>
                </div>
            </section>

            <?php if ($canManageCompanies): ?>
                <section class="card saas-company-create-card">
                    <div class="saas-company-head">
                        <div>
                            <h3>Cadastrar empresa</h3>
                            <p class="saas-company-note">Cadastro manual para entrada assistida, implantacao comercial ou regularizacao de conta fora do fluxo publico.</p>
                        </div>
                    </div>

                    <form method="POST" action="<?= htmlspecialchars(base_url('/saas/companies/store')) ?>">
                        <?= form_security_fields('saas.companies.store') ?>

                        <div class="saas-company-form-grid">
                            <div class="field">
                                <label for="new_company_name">Nome da empresa</label>
                                <input id="new_company_name" name="name" type="text" required>
                            </div>
                            <div class="field">
                                <label for="new_company_slug">Slug</label>
                                <input id="new_company_slug" name="slug" type="text" placeholder="Opcional">
                            </div>
                            <div class="field">
                                <label for="new_company_legal_name">Razao social</label>
                                <input id="new_company_legal_name" name="legal_name" type="text">
                            </div>
                            <div class="field">
                                <label for="new_company_document">CPF/CNPJ</label>
                                <input id="new_company_document" name="document_number" type="text">
                            </div>
                            <div class="field">
                                <label for="new_company_email">E-mail principal</label>
                                <input id="new_company_email" name="email" type="email" required>
                            </div>
                            <div class="field">
                                <label for="new_company_phone">Telefone</label>
                                <input id="new_company_phone" name="phone" type="text">
                            </div>
                            <div class="field">
                                <label for="new_company_whatsapp">WhatsApp</label>
                                <input id="new_company_whatsapp" name="whatsapp" type="text">
                            </div>
                            <div class="field">
                                <label for="new_company_plan">Plano</label>
                                <select id="new_company_plan" name="plan_id" required>
                                    <option value="">Selecione</option>
                                    <?php foreach ($plans as $plan): ?>
                                        <option value="<?= (int) ($plan['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($plan['name'] ?? 'Plano')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="new_company_status">Status operacional</label>
                                <select id="new_company_status" name="status" required>
                                    <option value="teste">Em teste</option>
                                    <option value="ativa">Ativa</option>
                                    <option value="suspensa">Suspensa</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="new_company_subscription_status">Status da assinatura</label>
                                <select id="new_company_subscription_status" name="subscription_status" required>
                                    <option value="trial">Em teste</option>
                                    <option value="ativa">Ativa</option>
                                    <option value="inadimplente">Inadimplente</option>
                                    <option value="suspensa">Suspensa</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="new_company_billing_cycle">Ciclo de cobranca</label>
                                <select id="new_company_billing_cycle" name="billing_cycle" required>
                                    <option value="mensal">Mensal</option>
                                    <option value="anual">Anual</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="new_company_amount">Valor contratado</label>
                                <input id="new_company_amount" name="amount" type="number" min="0" step="0.01" placeholder="Opcional">
                            </div>
                            <div class="field">
                                <label for="new_company_starts_at">Inicio da assinatura</label>
                                <input id="new_company_starts_at" name="subscription_starts_at" type="date">
                            </div>
                            <div class="field">
                                <label for="new_company_next_charge_due_date">Vencimento da próxima cobrança</label>
                                <input id="new_company_next_charge_due_date" name="next_charge_due_date" type="date">
                            </div>
                            <div class="field">
                                <label for="new_company_trial_ends_at">Fim do teste</label>
                                <input id="new_company_trial_ends_at" name="trial_ends_at" type="date">
                            </div>
                            <div class="field">
                                <label for="new_company_subscription_ends_at">Fim da assinatura</label>
                                <input id="new_company_subscription_ends_at" name="subscription_ends_at" type="date">
                            </div>
                        </div>

                        <div class="saas-company-form-footer" style="margin-top:12px">
                            <p class="saas-company-form-note">O cadastro manual cria a empresa e o registro comercial minimo da assinatura. A data de vencimento da próxima cobrança vira a referência do alerta de atraso e do bloqueio após 3 dias corridos de carência.</p>
                            <button class="btn" type="submit">Cadastrar empresa</button>
                        </div>
                    </form>
                </section>
            <?php else: ?>
                <section class="card">
                    <div class="saas-company-head">
                        <div>
                            <h3>Acoes restritas</h3>
                            <p class="saas-company-note">Seu perfil possui acesso de visualizacao. Cadastro, edicao e cancelamento exigem a permissao <code>companies.manage</code>.</p>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <section class="saas-company-governance">
                <h3>Regra operacional</h3>
                <p>Cadastro de empresa nao deve virar atalho para mascarar problema financeiro. Antes de suspender ou cancelar, avalie o estado da assinatura e documente a decisao comercial no processo interno.</p>
                <ul>
                    <li>Plano e ciclo precisam refletir o contrato vigente.</li>
                    <li>Inadimplencia nao exige remover a empresa do historico.</li>
                    <li>Cancelamento encerra a conta sem apagar rastreabilidade.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>
