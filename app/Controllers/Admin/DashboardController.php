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
use App\Services\Admin\SubscriptionPortalService;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $service = new DashboardService(),
        private readonly SubscriptionPortalService $subscriptionService = new SubscriptionPortalService()
    ) {}

    public function index(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);

        $companyId = (int) ($user['company_id'] ?? 0);
        $panel = $this->service->panel($companyId, $request->query);
        $panel['subscription_module'] = $this->subscriptionService->panel($companyId, $request->query);
        $activeSection = trim((string) ($request->input('section', 'overview')));
        $billingAccess = is_array($panel['subscription_module']['billing_access'] ?? null)
            ? $panel['subscription_module']['billing_access']
            : [];

        if (!empty($billingAccess['is_blocked']) && !in_array($activeSection, ['support', 'subscription'], true)) {
            $activeSection = 'subscription';
        }

        return $this->view('admin/dashboard/index', [
            'title' => 'Dashboard Administrativo',
            'user' => $user,
            'panel' => $panel,
            'activeSection' => $activeSection,
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

    public function deleteRole(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $roleId = (int) ($request->input('role_id', 0));
        $redirectTo = $this->resolveUsersRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'dashboard.roles.delete.' . $roleId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);

        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->service->deleteInternalRole($companyId, $roleId);
            return $this->backWithSuccess('Perfil interno excluido com sucesso.', $redirectTo);
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

        $redirectTo = $this->resolveSupportRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'dashboard.support.store', $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);

        $companyId = (int) ($user['company_id'] ?? 0);
        $openedByUserId = (int) ($user['id'] ?? 0);

        try {
            $this->service->openSupportTicket($companyId, $openedByUserId, $request->all(), $request->files);
            return $this->backWithSuccess('Chamado tecnico aberto e encaminhado para o administrador do sistema.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function replySupportTicket(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $ticketId = (int) ($request->input('ticket_id', 0));
        $redirectTo = $this->resolveSupportRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'dashboard.support.reply.' . $ticketId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);

        $companyId = (int) ($user['company_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);

        try {
            $this->service->replySupportTicket($companyId, $ticketId, $userId, $request->all(), $request->files);
            return $this->backWithSuccess('Mensagem enviada no historico do chamado.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function paySubscriptionWithCard(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $paymentId = (int) ($request->input('subscription_payment_id', 0));
        $redirectTo = $this->resolveSubscriptionRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'dashboard.subscription.card.' . $paymentId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);

        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->subscriptionService->payChargeWithCard($companyId, $request->all());
            return $this->backWithSuccess('Cobranca quitada com cartao e recorrencia automatica ativada.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function confirmSubscriptionPix(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $paymentId = (int) ($request->input('subscription_payment_id', 0));
        $redirectTo = $this->resolveSubscriptionRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'dashboard.subscription.pix.' . $paymentId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);

        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->subscriptionService->confirmPixPayment($companyId, $request->all());
            return $this->backWithSuccess('Pagamento via PIX confirmado na assinatura.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function generateSubscriptionPix(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $paymentId = (int) ($request->input('subscription_payment_id', 0));
        $redirectTo = $this->resolveSubscriptionRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'dashboard.subscription.pix.generate.' . $paymentId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);
        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->subscriptionService->generatePixGatewayCharge($companyId, $request->all());
            return $this->backWithSuccess('QR PIX gerado no gateway para a cobranca selecionada.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function createSubscriptionRecurringCheckout(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $redirectTo = $this->resolveSubscriptionRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'dashboard.subscription.gateway.checkout', $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);
        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $checkoutUrl = $this->subscriptionService->generateRecurringGatewayCheckout($companyId, app_url($redirectTo));
            if (trim($checkoutUrl) === '') {
                return $this->backWithError('Nao foi possivel gerar o checkout do cartao neste momento.', $redirectTo);
            }

            return $this->redirect($checkoutUrl);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function refreshSubscriptionGatewayStatus(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $redirectTo = $this->resolveSubscriptionRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'dashboard.subscription.gateway.sync', $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);
        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->subscriptionService->refreshGatewayStatus($companyId);
            return $this->backWithSuccess('Status da assinatura atualizado no sistema com base no gateway.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function pollSubscriptionPixStatus(Request $request): Response
    {
        if (!Auth::check()) {
            return Response::make(json_encode([
                'ok' => false,
                'message' => 'Nao autenticado.',
            ], JSON_UNESCAPED_SLASHES), 401, ['Content-Type' => 'application/json']);
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);
        $companyId = (int) ($user['company_id'] ?? 0);
        $paymentId = (int) ($request->input('subscription_payment_id', 0));

        try {
            $payment = $this->subscriptionService->refreshPaymentGatewayStatus($companyId, $paymentId);
            return Response::make(json_encode([
                'ok' => true,
                'payment_id' => (int) ($payment['id'] ?? 0),
                'status' => (string) ($payment['status'] ?? ''),
                'status_label' => (string) ($payment['status_label'] ?? ''),
                'gateway_status' => (string) ($payment['gateway_status'] ?? ''),
                'paid_at' => (string) ($payment['paid_at'] ?? ''),
            ], JSON_UNESCAPED_SLASHES), 200, ['Content-Type' => 'application/json']);
        } catch (ValidationException $e) {
            return Response::make(json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_SLASHES), 422, ['Content-Type' => 'application/json']);
        }
    }

    public function subscriptionReceipt(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);
        $companyId = (int) ($user['company_id'] ?? 0);
        $paymentId = (int) ($request->input('subscription_payment_id', 0));

        try {
            return $this->view('admin/dashboard/subscription_receipt', [
                'title' => 'Recibo da Assinatura',
                'user' => $user,
                'context' => $this->subscriptionService->receiptContext($companyId, $paymentId),
                'backUrl' => $this->buildSubscriptionReturnUrlFromParams($request->query),
            ]);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $this->buildSubscriptionReturnUrlFromParams($request->query));
        }
    }

    public function disableSubscriptionAutoCharge(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $redirectTo = $this->resolveSubscriptionRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'dashboard.subscription.auto_charge.disable', $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);

        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->subscriptionService->disableAutoCharge($companyId);
            return $this->backWithSuccess('Recorrencia automatica desativada. As proximas cobrancas ficarao em PIX manual.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function previewSubscriptionPlanMigration(Request $request): Response
    {
        if (!Auth::check()) {
            return Response::make(json_encode([
                'ok' => false,
                'message' => 'Nao autenticado.',
            ], JSON_UNESCAPED_SLASHES), 401, ['Content-Type' => 'application/json']);
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);
        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $preview = $this->subscriptionService->previewPlanMigration($companyId, $request->query);
            return Response::make(json_encode([
                'ok' => true,
                'preview' => $preview,
            ], JSON_UNESCAPED_SLASHES), 200, ['Content-Type' => 'application/json']);
        } catch (ValidationException $e) {
            return Response::make(json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_SLASHES), 422, ['Content-Type' => 'application/json']);
        }
    }

    public function applySubscriptionPlanMigration(Request $request): Response
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $redirectTo = $this->resolveSubscriptionRedirect($request);
        $planId = (int) ($request->input('plan_id', 0));
        $billingCycle = strtolower(trim((string) ($request->input('billing_cycle', ''))));
        $guard = $this->guardSingleSubmit($request, 'dashboard.subscription.plan_migration.' . $planId . '.' . $billingCycle, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $this->ensureAccess($user);
        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $result = $this->subscriptionService->applyPlanMigration($companyId, $request->all());
            $target = is_array($result['target'] ?? null) ? $result['target'] : [];
            $rules = is_array($result['rules'] ?? null) ? $result['rules'] : [];
            $chargeAmount = round((float) ($result['proration']['charge_amount'] ?? 0), 2);

            $message = 'Plano alterado com sucesso para ' . trim((string) ($target['plan_name'] ?? 'o novo plano')) . ' (' . trim((string) ($target['billing_cycle_label'] ?? '')) . ').';
            if ($chargeAmount > 0) {
                $message .= ' Foi gerada uma cobranca proporcional de R$ ' . number_format($chargeAmount, 2, ',', '.') . '.';
            } else {
                $message .= ' Nao houve cobranca imediata nesta migracao.';
            }

            if (!empty($rules['requires_gateway_reauthorization'])) {
                $message .= ' Se a empresa usava cartao recorrente, sera necessario autorizar novamente a recorrencia no novo valor.';
            }

            return $this->backWithSuccess($message, $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
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

    private function resolveSupportRedirect(Request $request): string
    {
        $default = '/admin/dashboard?section=support';
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
            'support_search',
            'support_status',
            'support_priority',
            'support_assignment',
            'support_page',
        ];

        $safe = ['section' => 'support'];
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

    private function resolveSubscriptionRedirect(Request $request): string
    {
        $default = '/admin/dashboard?section=subscription';
        $queryRaw = trim((string) ($request->input('return_query', '')));
        if ($queryRaw === '') {
            return $default;
        }

        parse_str($queryRaw, $params);
        if (!is_array($params)) {
            return $default;
        }

        return $this->buildSubscriptionReturnUrlFromParams($params);
    }

    private function buildSubscriptionReturnUrlFromParams(array $params): string
    {
        $allowedKeys = [
            'section',
            'subscription_history_search',
            'subscription_history_status',
            'subscription_history_method',
            'subscription_history_page',
        ];

        $safe = ['section' => 'subscription'];
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
