<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Exceptions\ValidationException;
use App\Services\Admin\DashboardService;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $service = new DashboardService()
    ) {}

    public function index(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);

        $companyId = (int) ($user['company_id'] ?? 0);

        return $this->view('admin/dashboard/index', [
            'title' => 'Dashboard Administrativo',
            'user' => $user,
            'panel' => $this->service->panel($companyId, $request->query),
            'activeSection' => trim((string) ($request->input('section', 'overview'))),
        ]);
    }

    public function report(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);
        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            return $this->view('admin/dashboard/report', [
                'title' => 'Previa de Relatorio',
                'user' => $user,
                'report' => $this->service->report($companyId, $request->query, $user),
            ]);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/dashboard?section=overview');
        }
    }

    public function updateTheme(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $guard = $this->guardSingleSubmit($request, 'dashboard.theme.update', '/admin/dashboard?section=branding');
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);

        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->service->updateBranding($companyId, $request->all(), $request->files);
            return $this->backWithSuccess('Identidade visual e dados da empresa atualizados com sucesso.', '/admin/dashboard?section=branding');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/dashboard?section=branding');
        }
    }

    public function restoreTheme(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $guard = $this->guardSingleSubmit($request, 'dashboard.theme.restore', '/admin/dashboard?section=branding');
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);

        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->service->restoreFactoryStyle($companyId);
            return $this->backWithSuccess('Estilo de fabrica restaurado com sucesso.', '/admin/dashboard?section=branding');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/dashboard?section=branding');
        }
    }

    public function storeUser(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $redirectTo = $this->resolveUsersRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'dashboard.users.store', $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);

        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->service->createInternalUser($companyId, $request->all());
            return $this->backWithSuccess('Usuario interno cadastrado com sucesso.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function storeRole(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $redirectTo = $this->resolveUsersRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'dashboard.roles.store', $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);

        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->service->createInternalRole($companyId, $request->all());
            return $this->backWithSuccess('Perfil interno criado com sucesso.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function updateRole(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $roleId = (int) ($request->input('role_id', 0));
        $redirectTo = $this->resolveUsersRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'dashboard.roles.update.' . $roleId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);

        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->service->updateInternalRole($companyId, $roleId, $request->all());
            return $this->backWithSuccess('Perfil interno atualizado com sucesso.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function updateUser(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $userId = (int) ($request->input('user_id', 0));
        $redirectTo = $this->resolveUsersRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'dashboard.users.update.' . $userId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);

        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->service->updateInternalUserData($companyId, $userId, $request->all());
            return $this->backWithSuccess('Dados do usuario atualizados com sucesso.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function updateUserStatus(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $userId = (int) ($request->input('user_id', 0));
        $redirectTo = $this->resolveUsersRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'dashboard.users.status.' . $userId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);

        $companyId = (int) ($user['company_id'] ?? 0);
        $currentUserId = (int) ($user['id'] ?? 0);

        try {
            $this->service->updateInternalUserStatus($companyId, $userId, $currentUserId, $request->input('status', 'ativo'));
            return $this->backWithSuccess('Status do usuario atualizado com sucesso.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function updateUserPassword(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $userId = (int) ($request->input('user_id', 0));
        $redirectTo = $this->resolveUsersRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'dashboard.users.password.' . $userId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);

        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->service->updateInternalUserPassword($companyId, $userId, $request->all());
            return $this->backWithSuccess('Senha do usuario atualizada com sucesso.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function storeSupportTicket(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $guard = $this->guardSingleSubmit($request, 'dashboard.support.store', '/admin/dashboard?section=support');
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);

        $companyId = (int) ($user['company_id'] ?? 0);
        $openedByUserId = (int) ($user['id'] ?? 0);

        try {
            $this->service->openSupportTicket($companyId, $openedByUserId, $request->all());
            return $this->backWithSuccess('Chamado tecnico aberto e encaminhado para o administrador do sistema.', '/admin/dashboard?section=support');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/dashboard?section=support');
        }
    }

    private function ensureAccess(array $user): void
    {
        $roleSlug = strtolower(trim((string) ($user['role_slug'] ?? '')));
        $companyId = (int) ($user['company_id'] ?? 0);

        if ($companyId <= 0) {
            throw new HttpException('403 - Usuario sem vinculo de empresa para acessar dashboard.', 403);
        }

        if (!in_array($roleSlug, ['admin_establishment', 'manager'], true)) {
            throw new HttpException('403 - Apenas administrador do estabelecimento e gerente podem acessar este dashboard.', 403);
        }
    }

    private function resolveUsersRedirect(Request $request): string
    {
        $default = '/admin/dashboard?section=users';
        $queryRaw = trim((string) ($request->input('return_query', '')));
        if ($queryRaw === '') {
            return $default;
        }

        parse_str($queryRaw, $params);
        if (!is_array($params)) {
            return $default;
        }

        $allowedKeys = [
            'section',
            'users_search',
            'users_status',
            'users_role_id',
            'users_per_page',
            'users_page',
        ];

        $safe = ['section' => 'users'];
        foreach ($allowedKeys as $key) {
            if ($key === 'section') {
                continue;
            }

            if (array_key_exists($key, $params)) {
                $safe[$key] = (string) $params[$key];
            }
        }

        return '/admin/dashboard?' . http_build_query($safe);
    }
}
