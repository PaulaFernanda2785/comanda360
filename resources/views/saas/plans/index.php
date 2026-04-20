<?php
$planPanel = is_array($planPanel ?? null) ? $planPanel : [];
$plans = is_array($planPanel['plans'] ?? null) ? $planPanel['plans'] : [];
$filters = is_array($planPanel['filters'] ?? null) ? $planPanel['filters'] : [];
$summary = is_array($planPanel['summary'] ?? null) ? $planPanel['summary'] : [];
$pagination = is_array($planPanel['pagination'] ?? null) ? $planPanel['pagination'] : [];
$featureCatalog = is_array($planPanel['feature_catalog'] ?? null) ? $planPanel['feature_catalog'] : [];
$canManagePlans = (bool) ($canManagePlans ?? false);

$planSearch = trim((string) ($filters['search'] ?? ''));
$planStatus = trim((string) ($filters['status'] ?? ''));

$planTotal = (int) ($summary['total_plans'] ?? ($pagination['total'] ?? count($plans)));
$planActiveCount = (int) ($summary['active_plans'] ?? 0);
$planInactiveCount = (int) ($summary['inactive_plans'] ?? 0);
$planCompanyUseCount = (int) ($summary['plans_in_company_use'] ?? 0);
$planHistoryCount = (int) ($summary['plans_with_subscription_history'] ?? 0);
$lastPlanCreatedAt = trim((string) ($summary['last_created_at'] ?? ''));

$planPage = max(1, (int) ($pagination['page'] ?? 1));
$planLastPage = max(1, (int) ($pagination['last_page'] ?? 1));
$planFrom = (int) ($pagination['from'] ?? 0);
$planTo = (int) ($pagination['to'] ?? 0);
$planPages = is_array($pagination['pages'] ?? null) ? $pagination['pages'] : [];

$currentQuery = is_array($_GET ?? null) ? $_GET : [];
$returnQuery = http_build_query($currentQuery);

$statusOptions = [
    '' => 'Todos os status',
    'ativo' => 'Ativo',
    'inativo' => 'Inativo',
];

$buildPlansUrl = static function (array $overrides = []) use ($planSearch, $planStatus): string {
    $params = array_merge([
        'plan_search' => $planSearch,
        'plan_status' => $planStatus,
    ], $overrides);

    foreach ($params as $key => $value) {
        if (trim((string) $value) === '') {
            unset($params[$key]);
        }
    }

    $query = http_build_query($params);
    return base_url('/saas/plans' . ($query !== '' ? '?' . $query : ''));
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

$formatLimit = static function (mixed $value): string {
    if ($value === null || $value === '') {
        return 'Ilimitado';
    }

    return (string) (int) $value;
};

$calculateYearlyDiscountPrice = static function (mixed $monthlyValue, mixed $discountPercentValue): float {
    $monthly = (float) ($monthlyValue ?? 0);
    $discountPercent = (float) ($discountPercentValue ?? 0);
    $discountPercent = max(0, min(100, $discountPercent));

    return round(($monthly * 12) * ((100 - $discountPercent) / 100), 2);
};

$formatDiscountPercent = static function (mixed $value): string {
    $percent = round((float) ($value ?? 0), 2);
    $formatted = number_format($percent, 2, ',', '.');
    $formatted = rtrim(rtrim($formatted, '0'), ',');

    return $formatted !== '' ? $formatted : '0';
};

$formatFeaturesPreview = static function (mixed $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return "{\n  \"gerado_automaticamente\": true\n}";
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $raw;
    }

    $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($pretty) && $pretty !== '' ? $pretty : $raw;
};

$featureStateFromJson = static function (mixed $value) use ($featureCatalog): array {
    $defaults = [];
    foreach ($featureCatalog as $feature) {
        if (!is_array($feature)) {
            continue;
        }

        $key = trim((string) ($feature['key'] ?? ''));
        if ($key === '') {
            continue;
        }

        $defaults[$key] = false;
    }

    $raw = trim((string) $value);
    if ($raw === '' || $defaults === []) {
        return $defaults;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $defaults;
    }

    $business = is_array($decoded['recursos_negocio'] ?? null)
        ? $decoded['recursos_negocio']
        : $decoded;

    foreach ($defaults as $key => $default) {
        $defaults[$key] = (bool) ($business[$key] ?? false);
    }

    return $defaults;
};

$landingStateFromJson = static function (mixed $value): array {
    $raw = trim((string) $value);
    if ($raw === '') {
        return ['destaque' => false, 'recomendado' => false];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['destaque' => false, 'recomendado' => false];
    }

    $publicConfig = is_array($decoded['vitrine_publica'] ?? null)
        ? $decoded['vitrine_publica']
        : [];

    return [
        'destaque' => (bool) ($publicConfig['destaque'] ?? false),
        'recomendado' => (bool) ($publicConfig['recomendado'] ?? false),
    ];
};

$pricingStateFromJson = static function (mixed $value, mixed $fallbackMonthly = null, mixed $fallbackYearly = null): array {
    $monthly = $fallbackMonthly !== null ? round((float) $fallbackMonthly, 2) : 0.0;
    $yearly = $fallbackYearly !== null ? round((float) $fallbackYearly, 2) : null;
    $discountPercent = 0.0;

    $raw = trim((string) $value);
    if ($raw === '') {
        return [
            'mensal' => $monthly,
            'anual' => $yearly,
            'desconto_anual_percentual' => $discountPercent,
        ];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [
            'mensal' => $monthly,
            'anual' => $yearly,
            'desconto_anual_percentual' => $discountPercent,
        ];
    }

    $pricing = is_array($decoded['precificacao'] ?? null)
        ? $decoded['precificacao']
        : [];

    return [
        'mensal' => isset($pricing['mensal']) && is_numeric((string) $pricing['mensal'])
            ? round((float) $pricing['mensal'], 2)
            : $monthly,
        'anual' => isset($pricing['anual']) && is_numeric((string) $pricing['anual'])
            ? round((float) $pricing['anual'], 2)
            : $yearly,
        'desconto_anual_percentual' => isset($pricing['desconto_anual_percentual']) && is_numeric((string) $pricing['desconto_anual_percentual'])
            ? round((float) $pricing['desconto_anual_percentual'], 2)
            : $discountPercent,
    ];
};
?>

<style>
    .saas-plan-page{display:grid;gap:16px}
    .saas-plan-hero{border:1px solid #bfdbfe;background:linear-gradient(118deg,var(--theme-main-card,#0f172a) 0%,#1d4ed8 52%,#7c3aed 100%);color:#fff;border-radius:16px;padding:18px;position:relative;overflow:hidden}
    .saas-plan-hero:before{content:"";position:absolute;top:-54px;right:-38px;width:216px;height:216px;border-radius:999px;background:rgba(255,255,255,.11)}
    .saas-plan-hero:after{content:"";position:absolute;bottom:-74px;left:-32px;width:180px;height:180px;border-radius:999px;background:rgba(255,255,255,.08)}
    .saas-plan-hero-body{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
    .saas-plan-hero h1{margin:0 0 8px;font-size:26px}
    .saas-plan-hero p{margin:0;color:#dbeafe;max-width:860px;line-height:1.5}
    .saas-plan-pills{display:flex;gap:8px;flex-wrap:wrap}
    .saas-plan-pill{border:1px solid rgba(255,255,255,.26);background:rgba(15,23,42,.35);padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700}

    .saas-plan-layout{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(320px,.95fr);gap:16px;align-items:start}
    .saas-plan-main,.saas-plan-side{display:grid;gap:16px}

    .saas-plan-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
    .saas-plan-head h2,.saas-plan-head h3{margin:0}
    .saas-plan-note{margin:4px 0 0;color:#64748b;font-size:13px;line-height:1.45}
    .saas-plan-badges{display:flex;gap:8px;flex-wrap:wrap}

    .saas-plan-filter-grid{display:grid;grid-template-columns:1.45fr 1fr auto;gap:10px;align-items:end}
    .saas-plan-filter-grid .field{margin:0}
    .saas-plan-filter-actions{display:flex;gap:8px;flex-wrap:wrap}

    .saas-plan-list{display:grid;gap:12px}
    .saas-plan-card{border:1px solid #dbeafe;border-radius:14px;background:linear-gradient(180deg,#fff,#f8fafc);padding:14px;display:grid;gap:12px}
    .saas-plan-card-top{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
    .saas-plan-title{display:grid;gap:4px}
    .saas-plan-title strong{font-size:16px;color:#0f172a}
    .saas-plan-title small{font-size:12px;color:#64748b;line-height:1.4}
    .saas-plan-info{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}
    .saas-plan-box{border:1px solid #e2e8f0;background:#fff;border-radius:10px;padding:10px}
    .saas-plan-box span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
    .saas-plan-box strong{display:block;margin-top:4px;font-size:13px;color:#0f172a;overflow-wrap:anywhere}
    .saas-plan-box strong.amount{font-size:15px}

    .saas-plan-details{border-top:1px dashed #cbd5e1;padding-top:12px}
    .saas-plan-details summary{display:flex;justify-content:space-between;align-items:center;gap:10px;cursor:pointer;list-style:none;font-weight:700;color:#0f172a}
    .saas-plan-details summary::-webkit-details-marker{display:none}
    .saas-plan-details-toggle{font-size:11px;color:#1d4ed8;background:#dbeafe;border:1px solid #bfdbfe;border-radius:999px;padding:4px 9px;font-weight:700}
    .saas-plan-details[open] .saas-plan-details-toggle{background:#eff6ff}
    .saas-plan-details-body{display:grid;gap:12px;margin-top:12px}

    .saas-plan-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .saas-plan-form-grid .field{margin:0}
    .saas-plan-form-grid .field.full{grid-column:1 / -1}
    .saas-plan-form-footer{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}
    .saas-plan-form-note{font-size:12px;color:#64748b;line-height:1.45;max-width:760px}
    .saas-plan-danger{display:flex;justify-content:flex-end}
    .saas-plan-danger .btn{background:#b91c1c}
    .saas-plan-json-preview{margin:0;padding:12px;border:1px solid #dbeafe;border-radius:12px;background:#0f172a;color:#dbeafe;font-size:12px;line-height:1.5;overflow:auto;max-height:260px}
    .saas-plan-feature-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    .saas-plan-feature-option{display:flex;align-items:flex-start;gap:10px;padding:10px;border:1px solid #dbeafe;border-radius:12px;background:#f8fafc}
    .saas-plan-feature-option input{width:auto;margin-top:2px}
    .saas-plan-feature-option strong{display:block;font-size:13px;color:#0f172a}
    .saas-plan-feature-option small{display:block;color:#64748b;font-size:12px;line-height:1.4}

    .saas-plan-summary-grid{display:grid;gap:8px}
    .saas-plan-summary-item{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px}
    .saas-plan-summary-item strong{color:#0f172a}
    .saas-plan-summary-item span{padding:4px 8px;border-radius:999px;background:#dbeafe;color:#1e3a8a;font-size:11px;font-weight:700}

    .saas-plan-create-card{display:grid;gap:12px}
    .saas-plan-governance{border:1px solid #c7d2fe;background:linear-gradient(130deg,#eef2ff 0%,#f8fafc 100%);border-radius:14px;padding:14px}
    .saas-plan-governance h3{margin:0 0 8px;color:#1e1b4b;font-size:16px}
    .saas-plan-governance p{margin:0;color:#3730a3;font-size:13px;line-height:1.5}
    .saas-plan-governance ul{margin:10px 0 0;padding-left:18px;color:#312e81;font-size:13px;display:grid;gap:6px}

    .saas-plan-pagination{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
    .saas-plan-pagination-controls{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .saas-page-btn{display:inline-block;padding:8px 11px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#0f172a;text-decoration:none}
    .saas-page-btn.is-active{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
    .saas-page-ellipsis{color:#64748b;padding:0 2px}

    @media (max-width:1180px){
        .saas-plan-layout{grid-template-columns:1fr}
    }
    @media (max-width:980px){
        .saas-plan-filter-grid,.saas-plan-info,.saas-plan-form-grid{grid-template-columns:1fr 1fr}
    }
    @media (max-width:760px){
        .saas-plan-filter-grid,.saas-plan-info,.saas-plan-form-grid{grid-template-columns:1fr}
        .saas-plan-feature-grid{grid-template-columns:1fr}
        .saas-plan-hero h1{font-size:22px}
    }
</style>

<div class="saas-plan-page">
    <div class="saas-plan-hero">
        <div class="saas-plan-hero-body">
            <div>
                <h1>Planos</h1>
                <p>Catálogo comercial do SaaS para governar oferta, precificação e limites operacionais. A tela centraliza cadastro, edição e exclusão segura dos planos usados por empresas e assinaturas.</p>
            </div>
            <div class="saas-plan-pills">
                <span class="saas-plan-pill">Planos filtrados: <?= htmlspecialchars((string) $planTotal) ?></span>
                <span class="saas-plan-pill">Ativos: <?= htmlspecialchars((string) $planActiveCount) ?></span>
                <span class="saas-plan-pill">Em uso por empresas: <?= htmlspecialchars((string) $planCompanyUseCount) ?></span>
                <span class="saas-plan-pill">Com histórico: <?= htmlspecialchars((string) $planHistoryCount) ?></span>
            </div>
        </div>
    </div>

    <div class="saas-plan-layout">
        <div class="saas-plan-main">
            <section class="card">
                <div class="saas-plan-head">
                    <div>
                        <h2>Painel de planos</h2>
                        <p class="saas-plan-note">Filtre por nome, slug ou status. Toda alteração aqui afeta a oferta comercial futura; por isso a exclusão física só aparece quando o plano não tem uso nem histórico de assinatura.</p>
                    </div>
                    <div class="saas-plan-badges">
                        <?php if ($lastPlanCreatedAt !== ''): ?>
                            <span class="badge">Último cadastro: <?= htmlspecialchars($formatDate($lastPlanCreatedAt)) ?></span>
                        <?php endif; ?>
                        <span class="badge">Inativos: <?= htmlspecialchars((string) $planInactiveCount) ?></span>
                    </div>
                </div>

                <form method="GET" action="<?= htmlspecialchars(base_url('/saas/plans')) ?>">
                    <div class="saas-plan-filter-grid">
                        <div class="field">
                            <label for="plan_search">Busca inteligente</label>
                            <input id="plan_search" name="plan_search" type="text" value="<?= htmlspecialchars($planSearch) ?>" placeholder="Nome, slug, descrição ou ID do plano">
                        </div>
                        <div class="field">
                            <label for="plan_status">Status</label>
                            <select id="plan_status" name="plan_status">
                                <?php foreach ($statusOptions as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= $planStatus === (string) $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="saas-plan-filter-actions">
                            <button class="btn" type="submit">Aplicar</button>
                            <a class="btn secondary" href="<?= htmlspecialchars(base_url('/saas/plans')) ?>">Limpar</a>
                        </div>
                    </div>
                </form>

                <?php if ($plans === []): ?>
                    <div class="card" style="margin-top:16px;padding:14px;border:1px dashed #cbd5e1;box-shadow:none">
                        <?= ($planSearch !== '' || $planStatus !== '')
                            ? 'Nenhum plano encontrado para os filtros aplicados.'
                            : 'Nenhum plano cadastrado até o momento.' ?>
                    </div>
                <?php else: ?>
                    <div class="saas-plan-list" style="margin-top:16px">
                        <?php foreach ($plans as $plan): ?>
                            <?php
                            $planId = (int) ($plan['id'] ?? 0);
                            $planName = trim((string) ($plan['name'] ?? 'Plano'));
                            $planStatusValue = trim((string) ($plan['status'] ?? ''));
                            $planStatusClass = status_badge_class('plan_status', $planStatusValue);
                            $linkedCompanies = (int) ($plan['linked_companies_count'] ?? 0);
                            $linkedSubscriptions = (int) ($plan['linked_subscriptions_count'] ?? 0);
                            $canDeletePlan = $linkedCompanies <= 0 && $linkedSubscriptions <= 0;
                            $featureState = $featureStateFromJson($plan['features_json'] ?? '');
                            $landingState = $landingStateFromJson($plan['features_json'] ?? '');
                            $pricingState = $pricingStateFromJson($plan['features_json'] ?? '', $plan['price_monthly'] ?? null, $plan['price_yearly'] ?? null);
                            $yearlyDiscountPercent = (float) ($pricingState['desconto_anual_percentual'] ?? 0);
                            ?>
                            <article class="saas-plan-card">
                                <div class="saas-plan-card-top">
                                    <div class="saas-plan-title">
                                        <strong>#<?= $planId ?> - <?= htmlspecialchars($planName) ?></strong>
                                        <small><?= htmlspecialchars((string) ($plan['slug'] ?? '-')) ?> &middot; <?= htmlspecialchars((string) ($plan['description'] ?? 'Sem descrição comercial')) ?></small>
                                    </div>
                                    <div class="saas-plan-badges">
                                        <span class="badge <?= htmlspecialchars($planStatusClass) ?>"><?= htmlspecialchars(status_label('plan_status', $planStatusValue)) ?></span>
                                        <?php if (!empty($landingState['destaque'])): ?>
                                            <span class="badge">Destaque na landing</span>
                                        <?php endif; ?>
                                        <?php if (!empty($landingState['recomendado'])): ?>
                                            <span class="badge">Recomendado na landing</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="saas-plan-info">
                                    <div class="saas-plan-box">
                                        <span>Preço mensal</span>
                                        <strong class="amount">R$ <?= number_format((float) ($pricingState['mensal'] ?? 0), 2, ',', '.') ?></strong>
                                    </div>
                                    <div class="saas-plan-box">
                                        <span>Preço anual</span>
                                        <strong class="amount">
                                            <?= ($pricingState['anual'] ?? null) !== null
                                                ? 'R$ ' . number_format((float) $pricingState['anual'], 2, ',', '.')
                                                : 'Não informado' ?>
                                        </strong>
                                        <strong><?= htmlspecialchars($formatDiscountPercent($yearlyDiscountPercent)) ?>% OFF</strong>
                                    </div>
                                    <div class="saas-plan-box">
                                        <span>Limites</span>
                                        <strong>Usuários: <?= htmlspecialchars($formatLimit($plan['max_users'] ?? null)) ?></strong>
                                        <strong>Produtos: <?= htmlspecialchars($formatLimit($plan['max_products'] ?? null)) ?></strong>
                                        <strong>Mesas: <?= htmlspecialchars($formatLimit($plan['max_tables'] ?? null)) ?></strong>
                                    </div>
                                    <div class="saas-plan-box">
                                        <span>Uso atual</span>
                                        <strong>Empresas: <?= $linkedCompanies ?></strong>
                                        <strong>Assinaturas: <?= $linkedSubscriptions ?></strong>
                                    </div>
                                </div>

                                <?php if ($canManagePlans): ?>
                                    <details class="saas-plan-details">
                                        <summary>
                                            <span>Editar plano</span>
                                            <span class="saas-plan-details-toggle">Expandir / recolher</span>
                                        </summary>

                                        <div class="saas-plan-details-body">
                                            <form method="POST" action="<?= htmlspecialchars(base_url('/saas/plans/update')) ?>">
                                                <?= form_security_fields('saas.plans.update.' . $planId) ?>
                                                <input type="hidden" name="plan_id" value="<?= $planId ?>">
                                                <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">

                                                <div class="saas-plan-form-grid">
                                                    <div class="field">
                                                        <label for="plan_name_<?= $planId ?>">Nome do plano</label>
                                                        <input id="plan_name_<?= $planId ?>" name="name" type="text" required value="<?= htmlspecialchars($planName) ?>">
                                                    </div>
                                                    <div class="field">
                                                        <label for="plan_slug_<?= $planId ?>">Slug</label>
                                                        <input id="plan_slug_<?= $planId ?>" name="slug" type="text" value="<?= htmlspecialchars((string) ($plan['slug'] ?? '')) ?>" placeholder="Opcional">
                                                    </div>
                                                    <div class="field">
                                                        <label for="plan_landing_featured_<?= $planId ?>">Vitrine publica</label>
                                                        <label class="saas-plan-feature-option" for="plan_landing_featured_<?= $planId ?>">
                                                            <input type="hidden" name="landing_featured" value="0">
                                                            <input id="plan_landing_featured_<?= $planId ?>" type="checkbox" name="landing_featured" value="1" <?= !empty($landingState['destaque']) ? 'checked' : '' ?>>
                                                            <span>
                                                                <strong>Destacar na landing</strong>
                                                                <small>Publica o plano como destaque na pagina publica do SaaS.</small>
                                                            </span>
                                                        </label>
                                                    </div>
                                                    <div class="field">
                                                        <label for="plan_landing_recommended_<?= $planId ?>">Plano recomendado</label>
                                                        <label class="saas-plan-feature-option" for="plan_landing_recommended_<?= $planId ?>">
                                                            <input type="hidden" name="landing_recommended" value="0">
                                                            <input id="plan_landing_recommended_<?= $planId ?>" type="checkbox" name="landing_recommended" value="1" <?= !empty($landingState['recomendado']) ? 'checked' : '' ?>>
                                                            <span>
                                                                <strong>Marcar como recomendado</strong>
                                                                <small>Ao salvar um recomendado ativo, o recomendado ativo anterior perde essa marcacao.</small>
                                                            </span>
                                                        </label>
                                                    </div>
                                                    <div class="field full">
                                                        <label for="plan_description_<?= $planId ?>">Descrição comercial</label>
                                                        <textarea id="plan_description_<?= $planId ?>" name="description" rows="3" placeholder="Resumo da proposta comercial do plano"><?= htmlspecialchars((string) ($plan['description'] ?? '')) ?></textarea>
                                                    </div>
                                                    <div class="field">
                                                        <label for="plan_price_monthly_<?= $planId ?>">Preço mensal</label>
                                                        <input id="plan_price_monthly_<?= $planId ?>" name="price_monthly" type="number" min="0" step="0.01" required value="<?= htmlspecialchars(number_format((float) ($pricingState['mensal'] ?? 0), 2, '.', '')) ?>">
                                                    </div>
                                                    <div class="field">
                                                        <label for="plan_yearly_discount_percent_<?= $planId ?>">Desconto anual (%)</label>
                                                        <input id="plan_yearly_discount_percent_<?= $planId ?>" name="yearly_discount_percent" type="number" min="0" max="100" step="0.01" value="<?= htmlspecialchars(number_format($yearlyDiscountPercent, 2, '.', '')) ?>" data-yearly-discount>
                                                        <small class="muted">Percentual manual aplicado sobre 12 mensalidades.</small>
                                                    </div>
                                                    <div class="field">
                                                        <label for="plan_price_yearly_<?= $planId ?>">Preço anual calculado</label>
                                                        <input id="plan_price_yearly_<?= $planId ?>" name="price_yearly" type="number" min="0" step="0.01" readonly value="<?= htmlspecialchars(number_format((float) ($pricingState['anual'] ?? $calculateYearlyDiscountPrice($pricingState['mensal'] ?? 0, $yearlyDiscountPercent)), 2, '.', '')) ?>" data-yearly-price>
                                                        <small class="muted">Atualizado automaticamente conforme mensal e desconto.</small>
                                                    </div>
                                                    <div class="field">
                                                        <label for="plan_max_users_<?= $planId ?>">Limite de usuários</label>
                                                        <input id="plan_max_users_<?= $planId ?>" name="max_users" type="number" min="0" step="1" value="<?= $plan['max_users'] !== null ? (int) $plan['max_users'] : '' ?>" placeholder="Vazio = ilimitado">
                                                    </div>
                                                    <div class="field">
                                                        <label for="plan_max_products_<?= $planId ?>">Limite de produtos</label>
                                                        <input id="plan_max_products_<?= $planId ?>" name="max_products" type="number" min="0" step="1" value="<?= $plan['max_products'] !== null ? (int) $plan['max_products'] : '' ?>" placeholder="Vazio = ilimitado">
                                                    </div>
                                                    <div class="field">
                                                        <label for="plan_max_tables_<?= $planId ?>">Limite de mesas</label>
                                                        <input id="plan_max_tables_<?= $planId ?>" name="max_tables" type="number" min="0" step="1" value="<?= $plan['max_tables'] !== null ? (int) $plan['max_tables'] : '' ?>" placeholder="Vazio = ilimitado">
                                                    </div>
                                                    <div class="field">
                                                        <label for="plan_status_edit_<?= $planId ?>">Status</label>
                                                        <select id="plan_status_edit_<?= $planId ?>" name="status" required>
                                                            <option value="ativo" <?= $planStatusValue === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                                            <option value="inativo" <?= $planStatusValue === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                                                        </select>
                                                    </div>
                                                    <div class="field full">
                                                        <label>Recursos de negócio</label>
                                                        <div class="saas-plan-feature-grid">
                                                            <?php foreach ($featureCatalog as $feature): ?>
                                                                <?php
                                                                $featureKey = trim((string) ($feature['key'] ?? ''));
                                                                if ($featureKey === '') {
                                                                    continue;
                                                                }
                                                                ?>
                                                                <label class="saas-plan-feature-option">
                                                                    <input type="hidden" name="<?= htmlspecialchars($featureKey) ?>" value="0">
                                                                    <input type="checkbox" name="<?= htmlspecialchars($featureKey) ?>" value="1" <?= !empty($featureState[$featureKey]) ? 'checked' : '' ?>>
                                                                    <span>
                                                                        <strong><?= htmlspecialchars((string) ($feature['label'] ?? $featureKey)) ?></strong>
                                                                        <small><?= htmlspecialchars((string) ($feature['description'] ?? '')) ?></small>
                                                                    </span>
                                                                </label>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <div class="field full">
                                                        <label>Recursos do plano em JSON</label>
                                                        <pre class="saas-plan-json-preview"><?= htmlspecialchars($formatFeaturesPreview($plan['features_json'] ?? '')) ?></pre>
                                                    </div>
                                                </div>

                                                <div class="saas-plan-form-footer" style="margin-top:12px">
                                                    <p class="saas-plan-form-note">O JSON de recursos é gerado automaticamente a partir de status, precificação e limites. Se o plano já tem uso histórico, prefira inativar em vez de tentar substituir retroativamente a precificação.</p>
                                                    <button class="btn" type="submit">Salvar ajustes</button>
                                                </div>
                                            </form>

                                            <?php if ($canDeletePlan): ?>
                                                <div class="saas-plan-danger">
                                                    <form method="POST" action="<?= htmlspecialchars(base_url('/saas/plans/delete')) ?>" onsubmit="return confirm('Confirmar exclusão definitiva deste plano?');">
                                                        <?= form_security_fields('saas.plans.delete.' . $planId) ?>
                                                        <input type="hidden" name="plan_id" value="<?= $planId ?>">
                                                        <input type="hidden" name="return_query" value="<?= htmlspecialchars($returnQuery) ?>">
                                                        <button class="btn" type="submit">Excluir plano</button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <div class="saas-plan-form-note">Exclusão bloqueada: este plano possui empresas vinculadas ou histórico de assinaturas.</div>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($planLastPage > 1): ?>
                        <div class="saas-plan-pagination">
                            <div class="muted">Exibindo <?= htmlspecialchars((string) $planFrom) ?> a <?= htmlspecialchars((string) $planTo) ?> de <?= htmlspecialchars((string) $planTotal) ?> planos.</div>
                            <div class="saas-plan-pagination-controls">
                                <?php if ($planPage > 1): ?>
                                    <a class="saas-page-btn" href="<?= htmlspecialchars($buildPlansUrl(['plan_page' => $planPage - 1])) ?>">Anterior</a>
                                <?php endif; ?>
                                <?php
                                $previousPage = null;
                                foreach ($planPages as $pageNumber):
                                    $pageNumber = (int) $pageNumber;
                                    if ($previousPage !== null && $pageNumber - $previousPage > 1): ?>
                                        <span class="saas-page-ellipsis">...</span>
                                    <?php endif; ?>
                                    <a class="saas-page-btn<?= $pageNumber === $planPage ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildPlansUrl(['plan_page' => $pageNumber])) ?>"><?= $pageNumber ?></a>
                                    <?php
                                    $previousPage = $pageNumber;
                                endforeach;
                                ?>
                                <?php if ($planPage < $planLastPage): ?>
                                    <a class="saas-page-btn" href="<?= htmlspecialchars($buildPlansUrl(['plan_page' => $planPage + 1])) ?>">Próxima</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>

        <aside class="saas-plan-side">
            <section class="card">
                <div class="saas-plan-head">
                    <div>
                        <h3>Resumo comercial</h3>
                        <p class="saas-plan-note">Leitura consolidada do catálogo no recorte filtrado.</p>
                    </div>
                </div>
                <div class="saas-plan-summary-grid">
                    <div class="saas-plan-summary-item"><strong>Total de planos</strong><span><?= htmlspecialchars((string) $planTotal) ?></span></div>
                    <div class="saas-plan-summary-item"><strong>Ativos</strong><span><?= htmlspecialchars((string) $planActiveCount) ?></span></div>
                    <div class="saas-plan-summary-item"><strong>Inativos</strong><span><?= htmlspecialchars((string) $planInactiveCount) ?></span></div>
                    <div class="saas-plan-summary-item"><strong>Em uso por empresas</strong><span><?= htmlspecialchars((string) $planCompanyUseCount) ?></span></div>
                    <div class="saas-plan-summary-item"><strong>Com histórico de assinaturas</strong><span><?= htmlspecialchars((string) $planHistoryCount) ?></span></div>
                </div>
            </section>

            <?php if ($canManagePlans): ?>
                <section class="card saas-plan-create-card">
                    <div class="saas-plan-head">
                        <div>
                            <h3>Cadastrar plano</h3>
                            <p class="saas-plan-note">Crie uma nova oferta comercial com precificação, limites operacionais e conjunto de recursos.</p>
                        </div>
                    </div>

                    <form method="POST" action="<?= htmlspecialchars(base_url('/saas/plans/store')) ?>">
                        <?= form_security_fields('saas.plans.store') ?>

                        <div class="saas-plan-form-grid">
                            <div class="field">
                                <label for="new_plan_name">Nome do plano</label>
                                <input id="new_plan_name" name="name" type="text" required>
                            </div>
                            <div class="field">
                                <label for="new_plan_slug">Slug</label>
                                <input id="new_plan_slug" name="slug" type="text" placeholder="Opcional">
                            </div>
                            <div class="field full">
                                <label for="new_plan_description">Descrição comercial</label>
                                <textarea id="new_plan_description" name="description" rows="3"></textarea>
                            </div>
                            <div class="field">
                                <label for="new_plan_price_monthly">Preço mensal</label>
                                <input id="new_plan_price_monthly" name="price_monthly" type="number" min="0" step="0.01" required>
                            </div>
                            <div class="field">
                                <label for="new_plan_yearly_discount_percent">Desconto anual (%)</label>
                                <input id="new_plan_yearly_discount_percent" name="yearly_discount_percent" type="number" min="0" max="100" step="0.01" value="0.00" data-yearly-discount>
                                <small class="muted">Percentual manual aplicado sobre 12 mensalidades.</small>
                            </div>
                            <div class="field">
                                <label for="new_plan_price_yearly">Preço anual calculado</label>
                                <input id="new_plan_price_yearly" name="price_yearly" type="number" min="0" step="0.01" readonly value="0.00" data-yearly-price>
                                <small class="muted">Atualizado automaticamente conforme mensal e desconto.</small>
                            </div>
                            <div class="field">
                                <label for="new_plan_max_users">Limite de usuários</label>
                                <input id="new_plan_max_users" name="max_users" type="number" min="0" step="1" placeholder="Vazio = ilimitado">
                            </div>
                            <div class="field">
                                <label for="new_plan_max_products">Limite de produtos</label>
                                <input id="new_plan_max_products" name="max_products" type="number" min="0" step="1" placeholder="Vazio = ilimitado">
                            </div>
                            <div class="field">
                                <label for="new_plan_max_tables">Limite de mesas</label>
                                <input id="new_plan_max_tables" name="max_tables" type="number" min="0" step="1" placeholder="Vazio = ilimitado">
                            </div>
                            <div class="field">
                                <label for="new_plan_status">Status</label>
                                <select id="new_plan_status" name="status" required>
                                    <option value="ativo">Ativo</option>
                                    <option value="inativo">Inativo</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="new_plan_landing_featured">Vitrine publica</label>
                                <label class="saas-plan-feature-option" for="new_plan_landing_featured">
                                    <input type="hidden" name="landing_featured" value="0">
                                    <input id="new_plan_landing_featured" type="checkbox" name="landing_featured" value="1">
                                    <span>
                                        <strong>Destacar na landing</strong>
                                        <small>Exibe este plano como destaque na pagina publica do SaaS.</small>
                                    </span>
                                </label>
                            </div>
                            <div class="field">
                                <label for="new_plan_landing_recommended">Plano recomendado</label>
                                <label class="saas-plan-feature-option" for="new_plan_landing_recommended">
                                    <input type="hidden" name="landing_recommended" value="0">
                                    <input id="new_plan_landing_recommended" type="checkbox" name="landing_recommended" value="1">
                                    <span>
                                        <strong>Marcar como recomendado</strong>
                                        <small>Ao salvar um recomendado ativo, o recomendado ativo anterior perde essa marcacao.</small>
                                    </span>
                                </label>
                            </div>
                            <div class="field full">
                                <label>Recursos de negócio</label>
                                <div class="saas-plan-feature-grid">
                                    <?php foreach ($featureCatalog as $feature): ?>
                                        <?php
                                        $featureKey = trim((string) ($feature['key'] ?? ''));
                                        if ($featureKey === '') {
                                            continue;
                                        }
                                        ?>
                                        <label class="saas-plan-feature-option">
                                            <input type="hidden" name="<?= htmlspecialchars($featureKey) ?>" value="0">
                                            <input type="checkbox" name="<?= htmlspecialchars($featureKey) ?>" value="1" <?= !empty($feature['default']) ? 'checked' : '' ?>>
                                            <span>
                                                <strong><?= htmlspecialchars((string) ($feature['label'] ?? $featureKey)) ?></strong>
                                                <small><?= htmlspecialchars((string) ($feature['description'] ?? '')) ?></small>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="saas-plan-form-footer" style="margin-top:12px">
                            <p class="saas-plan-form-note">Cadastre apenas ofertas coerentes com a estratégia comercial. O campo de recursos em JSON será gerado automaticamente com base nos dados comerciais informados.</p>
                            <button class="btn" type="submit">Cadastrar plano</button>
                        </div>
                    </form>
                </section>
            <?php else: ?>
                <section class="card">
                    <div class="saas-plan-head">
                        <div>
                            <h3>Ações restritas</h3>
                            <p class="saas-plan-note">Seu perfil possui acesso de visualização. Cadastro, edição e exclusão exigem a permissão <code>plans.manage</code>.</p>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <section class="saas-plan-governance">
                <h3>Regra operacional</h3>
                <p>Plano não é só tabela de preço. Ele define expectativa comercial e capacidade operacional. Exclusão física deve ser rara e restrita a rascunhos sem uso.</p>
                <ul>
                    <li>Plano com histórico comercial deve ser inativado, não apagado.</li>
                    <li>Limites precisam refletir o contrato vendido de verdade.</li>
                    <li>Apenas um plano recomendado ativo pode existir por vez.</li>
                    <li>JSON de recursos deve permanecer consistente com os módulos que o produto realmente entrega.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>
<script>
(() => {
    const clampDiscount = (discountRaw) => {
        const discount = Number(discountRaw || 0);
        if (!Number.isFinite(discount)) {
            return 0;
        }

        return Math.min(100, Math.max(0, discount));
    };

    const calculateYearly = (monthlyRaw, discountRaw) => {
        const monthly = Number(monthlyRaw || 0);
        if (!Number.isFinite(monthly) || monthly <= 0) {
            return '0.00';
        }

        const discount = clampDiscount(discountRaw);
        return ((monthly * 12) * ((100 - discount) / 100)).toFixed(2);
    };

    document.querySelectorAll('form').forEach((form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const monthlyField = form.querySelector('input[name="price_monthly"]');
        const discountField = form.querySelector('[data-yearly-discount]');
        const yearlyField = form.querySelector('[data-yearly-price]');
        if (!(monthlyField instanceof HTMLInputElement) || !(discountField instanceof HTMLInputElement) || !(yearlyField instanceof HTMLInputElement)) {
            return;
        }

        const syncYearly = () => {
            yearlyField.value = calculateYearly(monthlyField.value, discountField.value);
        };

        monthlyField.addEventListener('input', syncYearly);
        monthlyField.addEventListener('change', syncYearly);
        discountField.addEventListener('input', syncYearly);
        discountField.addEventListener('change', syncYearly);
        syncYearly();
    });
})();
</script>
