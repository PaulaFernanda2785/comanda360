<?php
declare(strict_types=1);

namespace App\Services\Marketing;

use App\Core\Session;
use App\Exceptions\ValidationException;
use App\Repositories\CompanyRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\PlanRepository;
use App\Repositories\SubscriptionPaymentRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\Admin\SubscriptionGatewayService;
use App\Services\Admin\SubscriptionPortalService;
use App\Services\Shared\CompanyAccessProvisioningService;
use App\Services\Shared\PlanFeatureCatalogService;

final class PublicOnboardingService
{
    private const SESSION_KEY = 'public_onboarding.company_id';
    private const CONFIRMATION_SESSION_KEY = 'public_onboarding.confirmation';
    private const INITIAL_ADMIN_ROLE_SLUG = 'admin_establishment';
    private const ALLOWED_BILLING_CYCLES = ['mensal', 'anual'];
    private const MAX_COMPANY_NAME_LENGTH = 160;
    private const MAX_LEGAL_NAME_LENGTH = 180;
    private const MAX_DOCUMENT_LENGTH = 40;
    private const MAX_EMAIL_LENGTH = 160;
    private const MAX_PHONE_LENGTH = 40;

    public function __construct(
        private readonly PlanRepository $plans = new PlanRepository(),
        private readonly CompanyRepository $companies = new CompanyRepository(),
        private readonly DashboardRepository $dashboard = new DashboardRepository(),
        private readonly SubscriptionRepository $subscriptions = new SubscriptionRepository(),
        private readonly SubscriptionPaymentRepository $subscriptionPayments = new SubscriptionPaymentRepository(),
        private readonly SubscriptionPortalService $subscriptionPortal = new SubscriptionPortalService(),
        private readonly SubscriptionGatewayService $gatewayService = new SubscriptionGatewayService(),
        private readonly PlanFeatureCatalogService $featureCatalog = new PlanFeatureCatalogService(),
        private readonly CompanyAccessProvisioningService $accessProvisioning = new CompanyAccessProvisioningService()
    ) {}

    public function signupPage(array $input, array $formData = []): array
    {
        $selection = $this->resolvePlanSelection($input);

        return [
            'title' => 'Cadastro da empresa',
            'seo' => $this->buildSeo(
                'Finalize sua contratação | MesiMenu',
                'Cadastre sua empresa, confirme o plano escolhido e avance para o pagamento para ativar o MesiMenu.'
            ),
            'selectedPlan' => $selection,
            'formData' => $this->normalizeFormData($formData),
        ];
    }

    public function registerCompany(array $input): void
    {
        $selection = $this->resolvePlanSelection($input);
        $payload = $this->normalizeSignupPayload($input, $selection);

        $companyId = $this->companies->transaction(function () use ($payload, $selection): int {
            $companyId = $this->companies->createCompany($payload['company']);

            $this->companies->createSubscription([
                'company_id' => $companyId,
                'plan_id' => $selection['id'],
                'status' => 'trial',
                'billing_cycle' => $selection['billing_cycle'],
                'amount' => $selection['amount'],
                'starts_at' => $payload['subscription_starts_at'],
                'ends_at' => null,
                'canceled_at' => null,
            ]);

            $this->createInitialAdminUser($companyId, $payload['admin_user']);

            return $companyId;
        });

        $this->subscriptionPortal->synchronizeCompanyBilling($companyId);
        $this->rememberCompany($companyId);
    }

    public function paymentPage(): array
    {
        $companyId = $this->requireRememberedCompanyId();
        $this->subscriptionPortal->synchronizeCompanyBilling($companyId);

        if ($this->finalizeAccessIfEligible($companyId)) {
            return [
                'completed' => true,
                'redirect_url' => base_url('/cadastro/confirmado'),
            ];
        }

        $company = $this->companies->findByIdForSaas($companyId);
        $subscription = $this->subscriptions->findCurrentByCompanyId($companyId);
        if ($company === null || $subscription === null) {
            $this->forgetRememberedCompany();
            throw new ValidationException('Não foi possível retomar o cadastro da empresa. Recomece a seleção do plano.');
        }

        $subscriptionId = (int) ($subscription['id'] ?? 0);
        $openPayments = $subscriptionId > 0
            ? $this->subscriptionPayments->listOpenBySubscriptionId($subscriptionId)
            : [];
        $currentPayment = $openPayments[0] ?? null;
        $paymentHistory = $subscriptionId > 0
            ? $this->subscriptionPayments->listBySubscriptionId($subscriptionId, 24)
            : [];

        return [
            'title' => 'Ative sua assinatura',
            'seo' => $this->buildSeo(
                'Ative sua assinatura | MesiMenu',
                'Escolha PIX ou cartão, conclua o pagamento e libere o acesso da sua empresa ao MesiMenu.'
            ),
            'completed' => false,
            'company' => $company,
            'subscription' => $subscription,
            'planSummary' => $this->buildPlanSummaryFromSubscription($subscription),
            'currentPayment' => $currentPayment,
            'paymentHistory' => $paymentHistory,
            'paymentState' => $this->buildPaymentState($subscription, $currentPayment),
            'gateway' => [
                'configured' => $this->gatewayService->isConfigured(),
                'provider' => $this->gatewayService->providerName(),
            ],
        ];
    }

    public function generatePixCharge(): void
    {
        if (!$this->gatewayService->isConfigured()) {
            throw new ValidationException('O serviço de pagamento ainda não está pronto para gerar o PIX.');
        }

        $companyId = $this->requireRememberedCompanyId();
        $payment = $this->requireCurrentOpenPayment($companyId);
        $this->gatewayService->createPixCharge($companyId, (int) ($payment['id'] ?? 0));
        $this->subscriptionPortal->synchronizeCompanyBilling($companyId);
    }

    public function startCardCheckout(): string
    {
        if (!$this->gatewayService->isConfigured()) {
            throw new ValidationException('O serviço de pagamento ainda não está pronto para checkout com cartão.');
        }

        $companyId = $this->requireRememberedCompanyId();
        $checkoutUrl = $this->gatewayService->createRecurringCheckout($companyId, app_url('/cadastro/pagamento/retorno'));
        if (trim($checkoutUrl) !== '') {
            return $checkoutUrl;
        }

        $subscription = $this->subscriptions->findCurrentByCompanyId($companyId);
        $fallbackUrl = trim((string) ($subscription['gateway_checkout_url'] ?? ''));
        if ($fallbackUrl === '') {
            throw new ValidationException('Não foi possível gerar o checkout do cartão neste momento.');
        }

        return $fallbackUrl;
    }

    public function refreshPaymentStatus(): array
    {
        $companyId = $this->requireRememberedCompanyId();
        $subscription = $this->subscriptions->findCurrentByCompanyId($companyId);
        if ($subscription === null) {
            $this->forgetRememberedCompany();
            throw new ValidationException('Assinatura não encontrada para acompanhar o pagamento.');
        }

        if ($this->gatewayService->isConfigured()) {
            $payment = $this->firstOpenPayment((int) ($subscription['id'] ?? 0));
            $paymentId = (int) ($payment['id'] ?? 0);
            $gatewayPaymentId = trim((string) ($payment['gateway_payment_id'] ?? ''));
            if ($paymentId > 0 && $gatewayPaymentId !== '') {
                $this->gatewayService->syncPaymentById($paymentId);
            }

            $subscription = $this->subscriptions->findCurrentByCompanyId($companyId);
            $gatewaySubscriptionId = trim((string) ($subscription['gateway_subscription_id'] ?? ''));
            if ($subscription !== null && $gatewaySubscriptionId !== '') {
                $this->gatewayService->syncSubscriptionByCompany($companyId);
            }
        }

        $this->subscriptionPortal->synchronizeCompanyBilling($companyId);
        $subscription = $this->subscriptions->findCurrentByCompanyId($companyId);
        $payment = $subscription !== null ? $this->firstOpenPayment((int) ($subscription['id'] ?? 0)) : null;

        $accessGranted = $this->finalizeAccessIfEligible($companyId);
        $paymentState = $this->buildPaymentState($subscription ?? [], $payment);

        return [
            'ok' => true,
            'access_granted' => $accessGranted,
            'redirect_url' => $accessGranted ? base_url('/cadastro/confirmado') : '',
            'subscription_status' => (string) ($subscription['status'] ?? ''),
            'company_subscription_status' => (string) ($subscription['company_subscription_status'] ?? ''),
            'payment_status' => (string) ($payment['status'] ?? ''),
            'payment_id' => (int) ($payment['id'] ?? 0),
            'payment_method' => (string) ($payment['payment_method'] ?? ''),
            'gateway_status' => (string) (($payment['gateway_status'] ?? '') ?: ($subscription['gateway_status'] ?? '')),
            'paid_at' => (string) ($payment['paid_at'] ?? ''),
            'state_key' => (string) ($paymentState['key'] ?? 'pending'),
            'state_title' => (string) ($paymentState['title'] ?? ''),
            'state_message' => (string) ($paymentState['message'] ?? ''),
            'state_hint' => (string) ($paymentState['hint'] ?? ''),
        ];
    }

    public function confirmationPage(): array
    {
        Session::start();
        $payload = Session::get(self::CONFIRMATION_SESSION_KEY);
        if (!is_array($payload) || $payload === []) {
            throw new ValidationException('Não existe confirmação recente de assinatura para exibir.');
        }

        return [
            'title' => 'Pagamento confirmado',
            'seo' => $this->buildSeo(
                'Pagamento confirmado | MesiMenu',
                'Pagamento confirmado e acesso liberado para a empresa começar a usar o MesiMenu.'
            ),
            'confirmation' => $payload,
        ];
    }

    private function resolvePlanSelection(array $input): array
    {
        $slug = strtolower(trim((string) ($input['plano'] ?? ($input['plan'] ?? ''))));
        if ($slug === '') {
            throw new ValidationException('Selecione um plano válido antes de seguir para o cadastro.');
        }

        $billingCycle = strtolower(trim((string) ($input['ciclo'] ?? ($input['cycle'] ?? 'mensal'))));
        if (!in_array($billingCycle, self::ALLOWED_BILLING_CYCLES, true)) {
            throw new ValidationException('Selecione um ciclo de cobrança válido para o plano.');
        }

        $plan = $this->plans->findActivePublicBySlug($slug);
        if ($plan === null) {
            throw new ValidationException('O plano selecionado não está disponível para contratação pública.');
        }

        $pricing = $this->featureCatalog->pricingConfigFromJson($plan['features_json'] ?? null);
        $monthly = $pricing['mensal'] !== null
            ? (float) $pricing['mensal']
            : (float) ($plan['price_monthly'] ?? 0);
        $yearly = $pricing['anual'] !== null
            ? (float) $pricing['anual']
            : (($plan['price_yearly'] ?? null) !== null ? (float) $plan['price_yearly'] : null);

        $selectedAmount = $billingCycle === 'anual' ? $yearly : $monthly;
        if ($selectedAmount === null || $selectedAmount <= 0) {
            throw new ValidationException(
                $billingCycle === 'anual'
                    ? 'Este plano não possui precificação anual publicada para contratação.'
                    : 'Este plano não possui precificação mensal válida para contratação.'
            );
        }

        return [
            'id' => (int) ($plan['id'] ?? 0),
            'name' => trim((string) ($plan['name'] ?? 'Plano')),
            'slug' => trim((string) ($plan['slug'] ?? '')),
            'description' => trim((string) ($plan['description'] ?? '')),
            'billing_cycle' => $billingCycle,
            'amount' => round((float) $selectedAmount, 2),
            'price_monthly' => round($monthly, 2),
            'price_yearly' => $yearly !== null ? round((float) $yearly, 2) : null,
            'price_yearly_discount_percent' => round((float) ($pricing['desconto_anual_percentual'] ?? 0), 2),
            'max_users' => ($plan['max_users'] ?? null) !== null ? (int) $plan['max_users'] : null,
            'max_products' => ($plan['max_products'] ?? null) !== null ? (int) $plan['max_products'] : null,
            'max_tables' => ($plan['max_tables'] ?? null) !== null ? (int) $plan['max_tables'] : null,
            'feature_labels' => $this->featureCatalog->enabledLabelsFromJson($plan['features_json'] ?? null),
        ];
    }

    private function normalizeSignupPayload(array $input, array $selection): array
    {
        $name = $this->requireTrim($input['name'] ?? '', 'Informe o nome da empresa.', self::MAX_COMPANY_NAME_LENGTH);
        $legalName = $this->nullableTrim($input['legal_name'] ?? null, self::MAX_LEGAL_NAME_LENGTH);
        $documentNumber = $this->nullableTrim($input['document_number'] ?? null, self::MAX_DOCUMENT_LENGTH);
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException('Informe um e-mail válido para o acesso inicial da empresa.');
        }
        if (strlen($email) > self::MAX_EMAIL_LENGTH) {
            throw new ValidationException('Informe um e-mail válido para o acesso inicial da empresa.');
        }

        $existingByEmail = $this->dashboard->findUserByEmail($email);
        if ($existingByEmail !== null && trim((string) ($existingByEmail['deleted_at'] ?? '')) === '') {
            throw new ValidationException('Já existe usuário ativo cadastrado com esse e-mail.');
        }

        $password = (string) ($input['initial_admin_password'] ?? '');
        $passwordConfirmation = (string) ($input['initial_admin_password_confirmation'] ?? '');
        if (strlen($password) < 6) {
            throw new ValidationException('A senha inicial precisa ter no mínimo 6 caracteres.');
        }
        if ($password !== $passwordConfirmation) {
            throw new ValidationException('A confirmação da senha inicial não confere.');
        }

        $phone = $this->nullableTrim($input['phone'] ?? null, self::MAX_PHONE_LENGTH);
        $whatsapp = $this->nullableTrim($input['whatsapp'] ?? null, self::MAX_PHONE_LENGTH);
        $slugInput = trim((string) ($input['slug'] ?? ''));
        $slug = $this->buildUniqueSlug($slugInput !== '' ? $slugInput : $name);
        $startsAt = date('Y-m-d 00:00:00');

        return [
            'company' => [
                'name' => $name,
                'legal_name' => $legalName,
                'document_number' => $documentNumber,
                'email' => $email,
                'phone' => $phone,
                'whatsapp' => $whatsapp,
                'slug' => $slug,
                'status' => 'teste',
                'plan_id' => $selection['id'],
                'subscription_status' => 'trial',
                'trial_ends_at' => null,
                'subscription_starts_at' => $startsAt,
                'subscription_ends_at' => null,
            ],
            'subscription_starts_at' => $startsAt,
            'admin_user' => [
                'name' => $name,
                'email' => $email,
                'phone' => $phone ?: $whatsapp,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'status' => 'inativo',
            ],
        ];
    }

    private function createInitialAdminUser(int $companyId, array $data): void
    {
        if ($companyId <= 0) {
            throw new ValidationException('Empresa inválida para criar o usuário inicial.');
        }

        $role = $this->dashboard->findCompanyRoleBySlug($companyId, self::INITIAL_ADMIN_ROLE_SLUG);
        if ($role === null) {
            throw new ValidationException('Perfil administrativo padrão não foi encontrado para a empresa.');
        }

        $this->dashboard->createCompanyUser([
            'company_id' => $companyId,
            'role_id' => (int) ($role['id'] ?? 0),
            'name' => trim((string) ($data['name'] ?? 'Empresa')),
            'email' => strtolower(trim((string) ($data['email'] ?? ''))),
            'phone' => $data['phone'] ?? null,
            'password_hash' => (string) ($data['password_hash'] ?? ''),
            'status' => (string) ($data['status'] ?? 'inativo'),
        ]);
    }

    private function firstOpenPayment(int $subscriptionId): ?array
    {
        if ($subscriptionId <= 0) {
            return null;
        }

        $payments = $this->subscriptionPayments->listOpenBySubscriptionId($subscriptionId);
        return $payments[0] ?? null;
    }

    private function requireCurrentOpenPayment(int $companyId): array
    {
        $subscription = $this->subscriptions->findCurrentByCompanyId($companyId);
        if ($subscription === null) {
            throw new ValidationException('Assinatura não encontrada para gerar a cobrança.');
        }

        $payment = $this->firstOpenPayment((int) ($subscription['id'] ?? 0));
        if ($payment === null) {
            $this->subscriptionPortal->synchronizeCompanyBilling($companyId);
            $subscription = $this->subscriptions->findCurrentByCompanyId($companyId);
            $payment = $subscription !== null ? $this->firstOpenPayment((int) ($subscription['id'] ?? 0)) : null;
        }

        if ($payment === null) {
            throw new ValidationException('Não foi possível preparar uma cobrança em aberto para este cadastro.');
        }

        return $payment;
    }

    private function finalizeAccessIfEligible(int $companyId): bool
    {
        $granted = $this->accessProvisioning->activateIfEligible($companyId);
        if (!$granted) {
            return false;
        }

        $company = $this->companies->findByIdForSaas($companyId);
        $subscription = $this->subscriptions->findCurrentByCompanyId($companyId);
        $latestPaid = $subscription !== null
            ? $this->findLatestPaidPayment((int) ($subscription['id'] ?? 0))
            : null;

        Session::start();
        Session::put(self::CONFIRMATION_SESSION_KEY, [
            'company_name' => (string) ($company['name'] ?? 'Empresa'),
            'company_email' => (string) ($company['email'] ?? ''),
            'plan_name' => (string) ($subscription['plan_name'] ?? 'Plano'),
            'billing_cycle' => (string) ($subscription['billing_cycle'] ?? 'mensal'),
            'amount' => round((float) ($subscription['amount'] ?? 0), 2),
            'paid_at' => (string) ($latestPaid['paid_at'] ?? date('Y-m-d H:i:s')),
        ]);
        $this->forgetRememberedCompany();

        return true;
    }

    private function buildPaymentState(array $subscription, ?array $payment): array
    {
        $subscriptionStatus = strtolower(trim((string) ($subscription['status'] ?? '')));
        $companySubscriptionStatus = strtolower(trim((string) ($subscription['company_subscription_status'] ?? '')));
        $paymentStatus = strtolower(trim((string) ($payment['status'] ?? '')));
        $gatewayStatus = strtolower(trim((string) (($payment['gateway_status'] ?? '') ?: ($subscription['gateway_status'] ?? ''))));

        if (in_array($subscriptionStatus, ['ativa'], true) || $companySubscriptionStatus === 'ativa' || $paymentStatus === 'pago') {
            return [
                'key' => 'approved',
                'tone' => 'approved',
                'title' => 'Pagamento aprovado',
                'message' => 'Tudo certo. Sua assinatura foi confirmada e o acesso da empresa está sendo liberado.',
                'hint' => 'Se a página não avançar sozinha em alguns segundos, clique para verificar o pagamento novamente.',
            ];
        }

        if (
            in_array($paymentStatus, ['cancelado', 'vencido'], true)
            || in_array($gatewayStatus, ['rejected', 'cancelled', 'cancelled_by_payer', 'canceled'], true)
            || in_array($subscriptionStatus, ['cancelada', 'vencida'], true)
        ) {
            return [
                'key' => 'rejected',
                'tone' => 'rejected',
                'title' => 'Pagamento não concluído',
                'message' => 'Não conseguimos confirmar essa tentativa. Você pode tentar novamente por PIX ou cartão.',
                'hint' => 'Se usou cartão, confira os dados ou escolha outro meio de pagamento para finalizar a assinatura.',
            ];
        }

        if (
            in_array($gatewayStatus, ['in_process', 'inprocess', 'processing'], true)
            || (trim((string) ($subscription['gateway_subscription_id'] ?? '')) !== '' && $gatewayStatus !== '')
        ) {
            return [
                'key' => 'processing',
                'tone' => 'processing',
                'title' => 'Pagamento em confirmação',
                'message' => 'Recebemos a tentativa de pagamento e estamos aguardando a confirmação para liberar o acesso.',
                'hint' => 'Essa etapa pode levar alguns instantes. A página atualiza o status automaticamente.',
            ];
        }

        return [
            'key' => 'pending',
            'tone' => 'pending',
            'title' => 'Pagamento pendente',
            'message' => 'Escolha PIX ou cartão para finalizar a assinatura e ativar sua empresa.',
            'hint' => 'O acesso ao painel será liberado assim que o pagamento for confirmado.',
        ];
    }

    private function buildPlanSummaryFromSubscription(array $subscription): array
    {
        $features = $this->featureCatalog->enabledLabelsFromJson($subscription['plan_features_json'] ?? null);

        return [
            'name' => trim((string) ($subscription['plan_name'] ?? 'Plano')),
            'description' => trim((string) ($subscription['plan_description'] ?? '')),
            'billing_cycle' => trim((string) ($subscription['billing_cycle'] ?? 'mensal')),
            'amount' => round((float) ($subscription['amount'] ?? 0), 2),
            'max_users' => ($subscription['plan_max_users'] ?? null) !== null ? (int) $subscription['plan_max_users'] : null,
            'max_products' => ($subscription['plan_max_products'] ?? null) !== null ? (int) $subscription['plan_max_products'] : null,
            'max_tables' => ($subscription['plan_max_tables'] ?? null) !== null ? (int) $subscription['plan_max_tables'] : null,
            'feature_labels' => $features,
        ];
    }

    private function normalizeFormData(array $formData): array
    {
        return [
            'name' => trim((string) ($formData['name'] ?? '')),
            'slug' => trim((string) ($formData['slug'] ?? '')),
            'legal_name' => trim((string) ($formData['legal_name'] ?? '')),
            'document_number' => trim((string) ($formData['document_number'] ?? '')),
            'email' => trim((string) ($formData['email'] ?? '')),
            'phone' => trim((string) ($formData['phone'] ?? '')),
            'whatsapp' => trim((string) ($formData['whatsapp'] ?? '')),
        ];
    }

    private function buildUniqueSlug(string $value): string
    {
        $base = $this->slugify($value);
        if ($base === '') {
            $base = 'empresa';
        }

        $base = substr($base, 0, 120);
        $candidate = $base;
        $suffix = 2;

        while ($this->companies->slugExists($candidate)) {
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

    private function requireTrim(mixed $value, string $message, int $maxLength): string
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            throw new ValidationException($message);
        }

        if (strlen($normalized) > $maxLength) {
            throw new ValidationException('O campo excede o limite de ' . $maxLength . ' caracteres.');
        }

        return $normalized;
    }

    private function nullableTrim(mixed $value, int $maxLength = 255): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        if (strlen($normalized) > $maxLength) {
            throw new ValidationException('O campo excede o limite de ' . $maxLength . ' caracteres.');
        }

        return $normalized !== '' ? $normalized : null;
    }

    private function rememberCompany(int $companyId): void
    {
        Session::start();
        Session::put(self::SESSION_KEY, $companyId);
    }

    private function requireRememberedCompanyId(): int
    {
        Session::start();
        $companyId = (int) Session::get(self::SESSION_KEY, 0);
        if ($companyId <= 0) {
            throw new ValidationException('A jornada de contratação não possui contexto ativo. Escolha o plano novamente.');
        }

        return $companyId;
    }

    private function forgetRememberedCompany(): void
    {
        Session::start();
        Session::forget(self::SESSION_KEY);
    }

    private function findLatestPaidPayment(int $subscriptionId): ?array
    {
        if ($subscriptionId <= 0) {
            return null;
        }

        foreach ($this->subscriptionPayments->listBySubscriptionId($subscriptionId, 120) as $payment) {
            if (strtolower(trim((string) ($payment['status'] ?? ''))) === 'pago') {
                return $payment;
            }
        }

        return null;
    }

    private function buildSeo(string $title, string $description): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'keywords' => 'cadastro empresa mesimenu, pagamento pix cartão, assinatura mesimenu',
            'canonical' => app_url((string) ($_SERVER['REQUEST_URI'] ?? '/')),
            'robots' => 'noindex,nofollow',
            'og_image' => asset_url('/img/logo-mesimenu.png'),
            'structured_data' => [],
        ];
    }
}
