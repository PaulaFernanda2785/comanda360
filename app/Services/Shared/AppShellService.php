<?php
declare(strict_types=1);

namespace App\Services\Shared;

use App\Repositories\AppShellRepository;
use App\Repositories\PermissionRepository;

final class AppShellService
{
    public function __construct(
        private readonly AppShellRepository $repository = new AppShellRepository(),
        private readonly PermissionRepository $permissions = new PermissionRepository()
    ) {}

    public function resolveForUser(?array $user): array
    {
        $defaults = [
            'company_name' => 'Estabelecimento',
            'title' => 'Seu painel',
            'description' => '',
            'primary_color' => '#1d4ed8',
            'secondary_color' => '#0f172a',
            'accent_color' => '#0ea5e9',
            'logo_path' => '',
            'banner_path' => '',
            'footer_text' => 'Comanda360 - Sistema de gestão de atendimento e vendas.',
        ];

        if (!is_array($user)) {
            return $defaults;
        }

        $companyId = (int) ($user['company_id'] ?? 0);
        if ($companyId <= 0) {
            return $defaults;
        }

        $profile = $this->repository->findCompanyShellConfig($companyId);
        if ($profile === null) {
            return $defaults;
        }

        $companyName = trim((string) ($profile['name'] ?? ''));
        $title = trim((string) ($profile['title'] ?? ''));

        $normalized = [
            'company_name' => $companyName !== '' ? $companyName : $defaults['company_name'],
            'title' => $title !== '' ? $title : ($companyName !== '' ? $companyName : $defaults['title']),
            'description' => trim((string) ($profile['description'] ?? '')),
            'primary_color' => $this->normalizeColor($profile['primary_color'] ?? null, $defaults['primary_color']),
            'secondary_color' => $this->normalizeColor($profile['secondary_color'] ?? null, $defaults['secondary_color']),
            'accent_color' => $this->normalizeColor($profile['accent_color'] ?? null, $defaults['accent_color']),
            'logo_path' => trim((string) ($profile['logo_path'] ?? '')),
            'banner_path' => trim((string) ($profile['banner_path'] ?? '')),
            'footer_text' => trim((string) ($profile['footer_text'] ?? '')),
        ];

        if ($normalized['footer_text'] === '') {
            $normalized['footer_text'] = $defaults['footer_text'];
        } elseif ($normalized['footer_text'] === 'Comanda360 - Sistema de gestao de atendimento e vendas.') {
            $normalized['footer_text'] = $defaults['footer_text'];
        }

        return $normalized;
    }

    private function normalizeColor(mixed $value, string $fallback): string
    {
        $color = strtolower(trim((string) ($value ?? '')));
        if (preg_match('/^#[0-9a-f]{6}$/', $color) !== 1) {
            return $fallback;
        }

        return $color;
    }

    public function resolveNavigationForUser(?array $user, ?string $contextHint = null): array
    {
        $normalizedUser = is_array($user) ? $user : [];
        $context = $this->resolveContext($normalizedUser, $contextHint);
        $items = $context === 'saas' ? $this->saasNavigationDefinition() : $this->companyNavigationDefinition();

        $roleId = (int) ($normalizedUser['role_id'] ?? 0);
        $roleSlug = strtolower(trim((string) ($normalizedUser['role_slug'] ?? '')));
        $permissionSet = $this->permissionSetForRole($roleId);

        $visibleItems = [];
        foreach ($items as $item) {
            $allowedRoles = $item['roles'] ?? [];
            if (is_array($allowedRoles) && $allowedRoles !== []) {
                $normalizedAllowedRoles = [];
                foreach ($allowedRoles as $allowedRole) {
                    $normalized = strtolower(trim((string) $allowedRole));
                    if ($normalized !== '') {
                        $normalizedAllowedRoles[] = $normalized;
                    }
                }

                if ($normalizedAllowedRoles !== [] && !in_array($roleSlug, $normalizedAllowedRoles, true)) {
                    continue;
                }
            }

            $permission = trim((string) ($item['permission'] ?? ''));
            if ($permission !== '' && !isset($permissionSet[$permission])) {
                continue;
            }

            $href = trim((string) ($item['href'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));
            if ($href === '' || $label === '') {
                continue;
            }

            $visibleItems[] = [
                'href' => $href,
                'label' => $label,
                'description' => trim((string) ($item['description'] ?? '')),
                'match' => $this->normalizeMatchRoutes($item['match'] ?? [$href], $href),
            ];
        }

        return $visibleItems;
    }

    private function resolveContext(?array $user, ?string $contextHint): string
    {
        $normalizedHint = strtolower(trim((string) ($contextHint ?? '')));
        if ($normalizedHint === 'company' || $normalizedHint === 'saas') {
            return $normalizedHint;
        }

        $roleContext = strtolower(trim((string) ($user['role_context'] ?? '')));
        if ($roleContext === 'company' || $roleContext === 'saas') {
            return $roleContext;
        }

        return (int) ($user['is_saas_user'] ?? 0) === 1 ? 'saas' : 'company';
    }

    private function permissionSetForRole(int $roleId): array
    {
        $set = [];
        foreach ($this->permissions->listPermissionSlugsByRole($roleId) as $slug) {
            $set[$slug] = true;
        }

        return $set;
    }

    private function normalizeMatchRoutes(mixed $rawMatch, string $fallback): array
    {
        $values = is_array($rawMatch) ? $rawMatch : [$rawMatch];
        $routes = [];
        foreach ($values as $value) {
            $route = trim((string) $value);
            if ($route !== '') {
                $routes[] = $route;
            }
        }

        if ($routes === []) {
            return [$fallback];
        }

        return array_values(array_unique($routes));
    }

    private function companyNavigationDefinition(): array
    {
        return [
            [
                'href' => '/admin/dashboard',
                'label' => 'Dashboard',
                'description' => 'Visão geral da operação',
                'permission' => 'dashboard.view',
                'roles' => ['admin_establishment', 'manager'],
                'match' => ['/admin/dashboard', '/admin/dashboard/report'],
            ],
            [
                'href' => '/admin/products',
                'label' => 'Produtos',
                'description' => 'Cardápio e categorias',
                'permission' => 'products.view',
            ],
            [
                'href' => '/admin/tables',
                'label' => 'Mesas',
                'description' => 'Gestão de salão',
                'permission' => 'tables.view',
            ],
            [
                'href' => '/admin/commands',
                'label' => 'Comandas',
                'description' => 'Abertura e controle',
                'permission' => 'commands.view',
            ],
            [
                'href' => '/admin/orders',
                'label' => 'Pedidos',
                'description' => 'Fila de atendimento',
                'permission' => 'orders.view',
            ],
            [
                'href' => '/admin/kitchen',
                'label' => 'Cozinha',
                'description' => 'Produção e impressão',
                'permission' => 'orders.view',
            ],
            [
                'href' => '/admin/delivery-zones',
                'label' => 'Zonas de Entrega',
                'description' => 'Cobertura e taxa',
                'permission' => 'orders.create',
            ],
            [
                'href' => '/admin/deliveries',
                'label' => 'Entregas',
                'description' => 'Roteiro e status',
                'permission' => 'orders.view',
            ],
            [
                'href' => '/admin/payments',
                'label' => 'Pagamentos',
                'description' => 'Cobranças e recebimentos',
                'permission' => 'payments.view',
            ],
            [
                'href' => '/admin/cash-registers',
                'label' => 'Caixa',
                'description' => 'Abertura e fechamento',
                'permission' => 'cash_registers.open',
            ],
        ];
    }

    private function saasNavigationDefinition(): array
    {
        return [
            [
                'href' => '/saas/dashboard',
                'label' => 'Dashboard',
                'description' => 'Indicadores da plataforma',
                'permission' => 'dashboard.view',
            ],
            [
                'href' => '/saas/support',
                'label' => 'Chamados',
                'description' => 'Atendimento tecnico SaaS',
                'permission' => 'support.view',
            ],
            [
                'href' => '/saas/companies',
                'label' => 'Empresas',
                'description' => 'Clientes da plataforma',
                'permission' => 'companies.view',
            ],
            [
                'href' => '/saas/plans',
                'label' => 'Planos',
                'description' => 'Catalogo comercial',
                'permission' => 'plans.view',
            ],
            [
                'href' => '/saas/subscriptions',
                'label' => 'Assinaturas',
                'description' => 'Ciclo de contratos',
                'permission' => 'subscriptions.view',
            ],
            [
                'href' => '/saas/subscription-payments',
                'label' => 'Cobrancas',
                'description' => 'Recebimentos SaaS',
                'permission' => 'subscriptions.view',
            ],
        ];
    }
}
