<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;

final class RoleContextMiddleware
{
    public function __construct(
        private readonly string $requiredContext
    ) {}

    public function handle(Request $request): ?Response
    {
        if (!Auth::check()) {
            Session::flash('error', 'Faça login para acessar esta área.');
            return Response::redirect('/login');
        }

        $user = Auth::user() ?? [];

        $roleContext = (string) ($user['role_context'] ?? '');
        $isSaasUser = (int) ($user['is_saas_user'] ?? 0) === 1;
        $companyId = (int) ($user['company_id'] ?? 0);

        if ($roleContext === '') {
            $roleContext = $isSaasUser ? 'saas' : 'company';
        }

        $isValidCompanyUser = !$isSaasUser && $companyId > 0;
        $isValidSaasUser = $isSaasUser && $companyId === 0;

        $hasAccess = match ($this->requiredContext) {
            'company' => $roleContext === 'company' && $isValidCompanyUser,
            'saas' => $roleContext === 'saas' && $isValidSaasUser,
            default => false,
        };

        if (!$hasAccess) {
            throw new HttpException('403 - Contexto de acesso não permitido.', 403);
        }

        return null;
    }
}
