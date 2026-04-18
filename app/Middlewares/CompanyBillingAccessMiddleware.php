<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Admin\SubscriptionPortalService;

final class CompanyBillingAccessMiddleware
{
    public function __construct(
        private readonly SubscriptionPortalService $subscriptionService = new SubscriptionPortalService()
    ) {}

    public function handle(Request $request): ?Response
    {
        if (!Auth::check()) {
            return null;
        }

        $user = Auth::user() ?? [];
        $companyId = (int) ($user['company_id'] ?? 0);
        $roleContext = strtolower(trim((string) ($user['role_context'] ?? '')));
        $isSaasUser = (int) ($user['is_saas_user'] ?? 0) === 1;

        if ($companyId <= 0 || $isSaasUser || $roleContext === 'saas') {
            return null;
        }

        $access = $this->subscriptionService->resolveAccessStateForMiddleware($companyId);
        Auth::updateUser(array_merge($user, [
            'billing_access_warning' => !empty($access['is_warning']) ? 1 : 0,
            'billing_access_blocked' => !empty($access['is_blocked']) ? 1 : 0,
            'billing_access_due_date' => (string) ($access['next_due_date'] ?? ''),
            'billing_access_headline' => (string) ($access['headline'] ?? ''),
            'billing_access_message' => (string) ($access['message'] ?? ''),
        ]));

        if (empty($access['is_blocked'])) {
            return null;
        }

        if ($this->isAllowedBlockedRoute($request)) {
            return null;
        }

        Session::flash('error', 'O sistema desta empresa esta bloqueado por falta de pagamento. Regularize a assinatura para liberar novamente o acesso completo.');
        return Response::redirect('/admin/dashboard?section=subscription');
    }

    private function isAllowedBlockedRoute(Request $request): bool
    {
        $path = trim($request->uri);
        if ($path === '/admin/dashboard') {
            $section = strtolower(trim((string) $request->input('section', '')));
            return in_array($section, ['support', 'subscription'], true);
        }

        if ($path === '/account/password') {
            return true;
        }

        return str_starts_with($path, '/admin/dashboard/support/')
            || str_starts_with($path, '/admin/dashboard/subscription/');
    }
}
