<?php
declare(strict_types=1);

namespace App\Controllers\Saas;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Repositories\PermissionRepository;
use App\Services\Saas\CompanyService;

final class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $service = new CompanyService(),
        private readonly PermissionRepository $permissions = new PermissionRepository()
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user() ?? [];
        $roleId = (int) ($user['role_id'] ?? 0);

        return $this->view('saas/companies/index', [
            'title' => 'Empresas',
            'user' => $user,
            'companyPanel' => $this->service->panel($request->query),
            'canManageCompanies' => $this->permissions->roleHasPermission($roleId, 'companies.manage'),
        ], 'layouts/saas');
    }

    public function store(Request $request): Response
    {
        $redirectTo = '/saas/companies';
        $guard = $this->guardSingleSubmit($request, 'saas.companies.store', $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        try {
            $this->service->createCompany($request->all());
            return $this->backWithSuccess('Empresa cadastrada com sucesso. O usuario administrador principal foi criado com o e-mail da empresa e a senha inicial informada no cadastro.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function update(Request $request): Response
    {
        $companyId = (int) ($request->input('company_id', 0));
        $redirectTo = $this->resolveCompaniesRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'saas.companies.update.' . $companyId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        try {
            $this->service->updateCompany($companyId, $request->all());
            return $this->backWithSuccess('Empresa atualizada com sucesso.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function cancel(Request $request): Response
    {
        $companyId = (int) ($request->input('company_id', 0));
        $redirectTo = $this->resolveCompaniesRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'saas.companies.cancel.' . $companyId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        try {
            $this->service->cancelCompany($companyId);
            return $this->backWithSuccess('Empresa cancelada com sucesso.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    private function resolveCompaniesRedirect(Request $request): string
    {
        $default = '/saas/companies';
        $queryRaw = trim((string) ($request->input('return_query', '')));
        if ($queryRaw === '') {
            return $default;
        }

        parse_str($queryRaw, $params);
        if (!is_array($params)) {
            return $default;
        }

        $allowedKeys = [
            'company_search',
            'company_status',
            'company_subscription_status',
            'company_plan_id',
            'company_page',
        ];

        $safe = [];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $params)) {
                $safe[$key] = (string) $params[$key];
            }
        }

        $query = http_build_query($safe);
        return '/saas/companies' . ($query !== '' ? '?' . $query : '');
    }
}
