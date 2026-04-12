<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use App\Repositories\PermissionRepository;

final class PermissionMiddleware
{
    public function __construct(
        private readonly string $permissionSlug,
        private readonly PermissionRepository $permissions = new PermissionRepository()
    ) {}

    public function handle(Request $request): ?Response
    {
        if (!Auth::check()) {
            Session::flash('error', 'Faça login para acessar esta área.');
            return Response::redirect('/login');
        }

        $user = Auth::user() ?? [];
        $roleId = (int) ($user['role_id'] ?? 0);

        if ($roleId <= 0) {
            throw new HttpException('403 - Perfil de acesso inválido.', 403);
        }

        if (!$this->permissions->roleHasPermission($roleId, $this->permissionSlug)) {
            throw new HttpException('403 - Permissão insuficiente para acessar este recurso.', 403);
        }

        return null;
    }
}
