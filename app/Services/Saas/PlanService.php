<?php
declare(strict_types=1);

namespace App\Services\Saas;

use App\Exceptions\ValidationException;
use App\Repositories\PlanRepository;
use App\Services\Shared\PlanFeatureCatalogService;

final class PlanService
{
    private const PLAN_LIST_PER_PAGE = 10;

    private const ALLOWED_STATUS = [
        'ativo',
        'inativo',
    ];

    public function __construct(
        private readonly PlanRepository $plans = new PlanRepository(),
        private readonly PlanFeatureCatalogService $featureCatalog = new PlanFeatureCatalogService()
    ) {}

    public function panel(array $filters): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        $page = $this->plans->listForSaasPaginated(
            [
                'search' => $normalizedFilters['search'],
                'status' => $normalizedFilters['status'],
            ],
            $normalizedFilters['page'],
            $normalizedFilters['per_page']
        );

        $items = is_array($page['items'] ?? null) ? $page['items'] : [];
        $total = (int) ($page['total'] ?? 0);
        $currentPage = (int) ($page['page'] ?? 1);
        $perPage = (int) ($page['per_page'] ?? self::PLAN_LIST_PER_PAGE);
        $lastPage = (int) ($page['last_page'] ?? 1);

        return [
            'plans' => $items,
            'filters' => $normalizedFilters,
            'summary' => $this->plans->summary([
                'search' => $normalizedFilters['search'],
                'status' => $normalizedFilters['status'],
            ]),
            'pagination' => [
                'total' => $total,
                'page' => $currentPage,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'from' => $total > 0 ? (($currentPage - 1) * $perPage) + 1 : 0,
                'to' => $total > 0 ? min($total, $currentPage * $perPage) : 0,
                'pages' => $this->buildPaginationPages($currentPage, $lastPage),
            ],
            'status_options' => self::ALLOWED_STATUS,
            'feature_catalog' => $this->featureCatalog->catalog(),
        ];
    }

    public function list(): array
    {
        return $this->plans->allForSaas();
    }

    public function createPlan(array $input): int
    {
        $payload = $this->normalizePayload($input, null);

        return $this->plans->transaction(function () use ($payload): int {
            $planId = $this->plans->create($payload);
            $this->syncRecommendedPlanRule($planId, $payload);

            return $planId;
        });
    }

    public function updatePlan(int $planId, array $input): void
    {
        if ($planId <= 0) {
            throw new ValidationException('Plano invalido para edicao.');
        }

        $existing = $this->plans->findById($planId);
        if ($existing === null) {
            throw new ValidationException('Plano nao encontrado para edicao.');
        }

        $payload = $this->normalizePayload($input, $existing);
        $this->plans->transaction(function () use ($planId, $payload): void {
            $this->plans->update($planId, $payload);
            $this->syncRecommendedPlanRule($planId, $payload);
        });
    }

    public function deletePlan(int $planId): void
    {
        if ($planId <= 0) {
            throw new ValidationException('Plano invalido para exclusao.');
        }

        $plan = $this->plans->findById($planId);
        if ($plan === null) {
            throw new ValidationException('Plano nao encontrado para exclusao.');
        }

        $linkedCompanies = (int) ($plan['linked_companies_count'] ?? 0);
        $linkedSubscriptions = (int) ($plan['linked_subscriptions_count'] ?? 0);
        if ($linkedCompanies > 0 || $linkedSubscriptions > 0) {
            throw new ValidationException('O plano nao pode ser excluido porque ja possui empresas ou historico de assinaturas vinculados. Inative ou edite o plano.');
        }

        $this->plans->delete($planId);
    }

    private function normalizeFilters(array $filters): array
    {
        $search = trim((string) ($filters['plan_search'] ?? ''));
        if (strlen($search) > 80) {
            $search = substr($search, 0, 80);
        }

        $status = strtolower(trim((string) ($filters['plan_status'] ?? '')));
        if ($status !== '' && !in_array($status, self::ALLOWED_STATUS, true)) {
            $status = '';
        }

        $page = (int) ($filters['plan_page'] ?? 1);
        if ($page <= 0) {
            $page = 1;
        }

        return [
            'search' => $search,
            'status' => $status,
            'page' => $page,
            'per_page' => self::PLAN_LIST_PER_PAGE,
        ];
    }

    private function normalizePayload(array $input, ?array $existing): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new ValidationException('Informe o nome do plano.');
        }

        $status = strtolower(trim((string) ($input['status'] ?? ($existing['status'] ?? 'ativo'))));
        if (!in_array($status, self::ALLOWED_STATUS, true)) {
            throw new ValidationException('Status invalido para o plano.');
        }

        $slugInput = trim((string) ($input['slug'] ?? ''));
        $slug = $this->buildUniqueSlug(
            $slugInput !== '' ? $slugInput : $name,
            $existing !== null ? (int) ($existing['id'] ?? 0) : null
        );

        $priceMonthly = $this->normalizeMoney($input['price_monthly'] ?? null, 'mensal');
        $yearlyDiscountPercent = $this->normalizeDiscountPercent(
            $input['yearly_discount_percent'] ?? null,
            $existing['features_json'] ?? null
        );
        $priceYearly = $this->calculateYearlyPrice($priceMonthly, $yearlyDiscountPercent);
        $maxUsers = $this->normalizeNullableLimit($input['max_users'] ?? null, 'usuarios');
        $maxProducts = $this->normalizeNullableLimit($input['max_products'] ?? null, 'produtos');
        $maxTables = $this->normalizeNullableLimit($input['max_tables'] ?? null, 'mesas');

        $description = $this->nullableTrim($input['description'] ?? ($existing['description'] ?? null));
        $existingFeatures = $this->decodeExistingFeatures($existing['features_json'] ?? null);
        $enabledFeatures = $this->resolveBusinessFeatures($input, $existingFeatures);
        $publicLanding = $this->resolvePublicLandingSettings($input, $existingFeatures);
        $featuresJson = $this->buildGeneratedFeaturesJson(
            $slug,
            $status,
            $priceMonthly,
            $priceYearly,
            $yearlyDiscountPercent,
            $maxUsers,
            $maxProducts,
            $maxTables,
            $enabledFeatures,
            $publicLanding
        );

        return [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'price_monthly' => $priceMonthly,
            'price_yearly' => $priceYearly,
            'max_users' => $maxUsers,
            'max_products' => $maxProducts,
            'max_tables' => $maxTables,
            'features_json' => $featuresJson,
            'status' => $status,
        ];
    }

    private function normalizeMoney(mixed $value, string $label): float
    {
        $raw = str_replace(',', '.', trim((string) ($value ?? '')));
        if ($raw === '' || !is_numeric($raw)) {
            throw new ValidationException('Informe um valor ' . $label . ' valido.');
        }

        $amount = (float) $raw;
        if ($amount < 0) {
            throw new ValidationException('O valor ' . $label . ' nao pode ser negativo.');
        }

        return round($amount, 2);
    }

    private function normalizeDiscountPercent(mixed $value, mixed $existingFeaturesJson = null): float
    {
        $raw = str_replace(',', '.', trim((string) ($value ?? '')));
        if ($raw === '') {
            $pricing = $this->featureCatalog->pricingConfigFromJson($existingFeaturesJson);
            return round((float) ($pricing['desconto_anual_percentual'] ?? 0), 2);
        }

        if (!is_numeric($raw)) {
            throw new ValidationException('Informe um percentual valido para o desconto anual.');
        }

        $discount = (float) $raw;
        if ($discount < 0 || $discount > 100) {
            throw new ValidationException('O desconto anual deve estar entre 0% e 100%.');
        }

        return round($discount, 2);
    }

    private function calculateYearlyPrice(float $priceMonthly, float $discountPercent): float
    {
        $baseYearly = $priceMonthly * 12;
        $discountFactor = max(0, (100 - $discountPercent) / 100);

        return round($baseYearly * $discountFactor, 2);
    }

    private function normalizeNullableLimit(mixed $value, string $label): ?int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        if (!ctype_digit($raw)) {
            throw new ValidationException('Informe um limite valido para ' . $label . '.');
        }

        return (int) $raw;
    }

    private function buildGeneratedFeaturesJson(
        string $slug,
        string $status,
        float $priceMonthly,
        ?float $priceYearly,
        float $yearlyDiscountPercent,
        ?int $maxUsers,
        ?int $maxProducts,
        ?int $maxTables,
        array $enabledFeatures,
        array $publicLanding
    ): string
    {
        $payload = [
            'gerado_automaticamente' => true,
            'slug_referencia' => $slug,
            'status' => $status,
            'recursos_negocio' => [
                'cardapio_digital' => (bool) ($enabledFeatures['cardapio_digital'] ?? false),
                'qrcode_mesa' => (bool) ($enabledFeatures['qrcode_mesa'] ?? false),
                'comandas' => (bool) ($enabledFeatures['comandas'] ?? false),
                'cozinha' => (bool) ($enabledFeatures['cozinha'] ?? false),
                'pagamentos' => (bool) ($enabledFeatures['pagamentos'] ?? false),
                'caixa' => (bool) ($enabledFeatures['caixa'] ?? false),
                'delivery' => (bool) ($enabledFeatures['delivery'] ?? false),
                'estoque' => (bool) ($enabledFeatures['estoque'] ?? false),
                'relatorios' => (bool) ($enabledFeatures['relatorios'] ?? false),
            ],
            'precificacao' => [
                'mensal' => round($priceMonthly, 2),
                'anual' => $priceYearly !== null ? round($priceYearly, 2) : null,
                'desconto_anual_percentual' => round($yearlyDiscountPercent, 2),
            ],
            'limites' => [
                'usuarios' => $maxUsers,
                'produtos' => $maxProducts,
                'mesas' => $maxTables,
            ],
            'flags_automaticas' => [
                'usuarios_ilimitados' => $maxUsers === null,
                'produtos_ilimitados' => $maxProducts === null,
                'mesas_ilimitadas' => $maxTables === null,
            ],
            'resumo_operacional' => [
                'usuarios_internos' => $maxUsers === null ? 'ilimitado' : $maxUsers,
                'catalogo_produtos' => $maxProducts === null ? 'ilimitado' : $maxProducts,
                'mesas_ativas' => $maxTables === null ? 'ilimitado' : $maxTables,
            ],
            'vitrine_publica' => [
                'destaque' => (bool) ($publicLanding['destaque'] ?? false),
                'recomendado' => (bool) ($publicLanding['recomendado'] ?? false),
            ],
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new ValidationException('Nao foi possivel normalizar os recursos do plano.');
        }

        return $encoded;
    }

    private function buildUniqueSlug(string $value, ?int $exceptPlanId = null): string
    {
        $base = $this->slugify($value);
        if ($base === '') {
            $base = 'plano';
        }

        $base = substr($base, 0, 120);
        $candidate = $base;
        $suffix = 2;

        while ($this->plans->slugExists($candidate, $exceptPlanId)) {
            $candidate = substr($base, 0, max(1, 120 - strlen((string) $suffix) - 1)) . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function slugify(string $value): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($normalized === false) {
            $normalized = $value;
        }

        $slug = strtolower(trim($normalized));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        return trim($slug, '-');
    }

    private function buildPaginationPages(int $currentPage, int $lastPage): array
    {
        $lastPage = max(1, $lastPage);
        $currentPage = max(1, min($currentPage, $lastPage));

        $pages = [1, $lastPage, $currentPage];
        for ($offset = -2; $offset <= 2; $offset++) {
            $pages[] = $currentPage + $offset;
        }

        $normalized = [];
        foreach ($pages as $page) {
            $pageNumber = (int) $page;
            if ($pageNumber >= 1 && $pageNumber <= $lastPage) {
                $normalized[$pageNumber] = true;
            }
        }

        $result = array_keys($normalized);
        sort($result);

        return $result;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        return $normalized !== '' ? $normalized : null;
    }

    private function decodeExistingFeatures(mixed $value): array
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function resolveBusinessFeatures(array $input, array $existingFeatures): array
    {
        $existingBusiness = is_array($existingFeatures['recursos_negocio'] ?? null)
            ? $existingFeatures['recursos_negocio']
            : $existingFeatures;

        $resolved = [];
        foreach ($this->featureCatalog->keys() as $flag) {
            if (array_key_exists($flag, $input)) {
                $resolved[$flag] = $this->normalizeFeatureFlag($input[$flag]);
                continue;
            }

            $defaults = $this->featureCatalog->defaultState();
            $resolved[$flag] = array_key_exists($flag, $existingBusiness)
                ? (bool) $existingBusiness[$flag]
                : (bool) ($defaults[$flag] ?? false);
        }

        return $resolved;
    }

    private function resolvePublicLandingSettings(array $input, array $existingFeatures): array
    {
        $existingPublic = is_array($existingFeatures['vitrine_publica'] ?? null)
            ? $existingFeatures['vitrine_publica']
            : [];

        if (array_key_exists('landing_featured', $input)) {
            return [
                'destaque' => $this->normalizeFeatureFlag($input['landing_featured']),
                'recomendado' => $this->normalizeFeatureFlag($input['landing_recommended'] ?? ($existingPublic['recomendado'] ?? false)),
            ];
        }

        return [
            'destaque' => (bool) ($existingPublic['destaque'] ?? false),
            'recomendado' => (bool) ($existingPublic['recomendado'] ?? false),
        ];
    }

    private function normalizeFeatureFlag(mixed $value): bool
    {
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'on', 'yes', 'sim'], true);
    }

    private function syncRecommendedPlanRule(int $planId, array $payload): void
    {
        if ($planId <= 0) {
            return;
        }

        if (($payload['status'] ?? '') !== 'ativo') {
            return;
        }

        $publicLanding = $this->featureCatalog->publicLandingConfigFromJson($payload['features_json'] ?? null);
        if (empty($publicLanding['recomendado'])) {
            return;
        }

        $this->plans->clearRecommendedFlagFromOtherActivePlans($planId);
    }
}
