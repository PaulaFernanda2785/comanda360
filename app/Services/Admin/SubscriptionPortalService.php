<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Exceptions\ValidationException;
use App\Repositories\CompanyRepository;
use App\Repositories\PlanRepository;
use App\Repositories\SubscriptionPaymentRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\Shared\PlanFeatureCatalogService;
use App\Services\Shared\SubscriptionPlanMigrationService;
use DateTimeImmutable;

final class SubscriptionPortalService
{
    private const CARD_METHODS = ['credito', 'debito'];

    public function __construct(
        private readonly SubscriptionRepository $subscriptions = new SubscriptionRepository(),
        private readonly SubscriptionPaymentRepository $subscriptionPayments = new SubscriptionPaymentRepository(),
        private readonly CompanyRepository $companies = new CompanyRepository(),
        private readonly PlanRepository $plans = new PlanRepository(),
        private readonly SubscriptionGatewayService $gatewayService = new SubscriptionGatewayService(),
        private readonly PlanFeatureCatalogService $featureCatalog = new PlanFeatureCatalogService(),
        private readonly SubscriptionPlanMigrationService $planMigration = new SubscriptionPlanMigrationService()
    ) {}

    public function panel(int $companyId, array $filters = []): array
    {
        if ($companyId <= 0) {
            throw new ValidationException('Empresa invalida para acompanhar a assinatura.');
        }

        $subscription = $this->subscriptions->findCurrentByCompanyId($companyId);
        if ($subscription === null) {
            return $this->emptyPanel();
        }

        $this->synchronizeByCompany($companyId);

        $subscription = $this->subscriptions->findCurrentByCompanyId($companyId);
        if ($subscription === null) {
            return $this->emptyPanel();
        }

        $normalizedFilters = $this->normalizeHistoryFilters($filters);
        $historyPagination = $this->subscriptionPayments->listBySubscriptionIdPaginated(
            (int) $subscription['id'],
            $normalizedFilters,
            (int) $normalizedFilters['page'],
            10
        );
        $history = is_array($historyPagination['items'] ?? null) ? $historyPagination['items'] : [];
        $openPayments = array_values(array_filter(
            $this->subscriptionPayments->listBySubscriptionId((int) $subscription['id'], 120),
            static fn (array $payment): bool => in_array((string) ($payment['status'] ?? ''), ['pendente', 'vencido'], true)
        ));
        usort($openPayments, static function (array $left, array $right): int {
            return strcmp((string) ($left['due_date'] ?? ''), (string) ($right['due_date'] ?? ''));
        });

        return [
            'subscription' => $subscription,
            'open_payments' => $openPayments,
            'payment_history' => $history,
            'history_filters' => $normalizedFilters,
            'history_pagination' => $this->buildPaginationPayload($historyPagination),
            'features' => $this->extractFeatureSummary($subscription),
            'summary' => $this->buildSummary($subscription, $this->subscriptionPayments->listBySubscriptionId((int) $subscription['id'], 120), $openPayments),
            'plan_migration' => $this->buildPlanMigrationContext($subscription, $openPayments),
            'billing_access' => $this->resolveAccessState($companyId),
            'gateway' => [
                'configured' => $this->gatewayService->isConfigured(),
                'provider' => $this->gatewayService->providerName(),
            ],
        ];
    }

    public function previewPlanMigration(int $companyId, array $input): array
    {
        [$subscription, $plan, $targetSubscription, $preparedMigration] = $this->preparePlanMigration($companyId, $input);

        return $this->buildPlanMigrationPreviewPayload($subscription, $plan, $targetSubscription, $preparedMigration);
    }

    public function applyPlanMigration(int $companyId, array $input): array
    {
        [$subscription, $plan, $targetSubscription, $preparedMigration] = $this->preparePlanMigration($companyId, $input);

        $this->companies->transaction(function () use ($companyId, $subscription, $preparedMigration): void {
            $preparedSubscription = is_array($preparedMigration['subscription'] ?? null)
                ? $preparedMigration['subscription']
                : [];

            $this->planMigration->applyMigration($subscription, $preparedMigration);
            $this->companies->updateSubscriptionSnapshot($companyId, [
                'plan_id' => $preparedSubscription['plan_id'] ?? null,
                'subscription_status' => 'ativa',
                'trial_ends_at' => null,
                'subscription_starts_at' => $preparedSubscription['starts_at'] ?? null,
                'subscription_ends_at' => $preparedSubscription['ends_at'] ?? null,
            ]);
        });

        $this->synchronizeByCompany($companyId);

        return $this->buildPlanMigrationPreviewPayload($subscription, $plan, $targetSubscription, $preparedMigration);
    }

    public function receiptContext(int $companyId, int $paymentId): array
    {
        if ($companyId <= 0 || $paymentId <= 0) {
            throw new ValidationException('Pagamento invalido para emitir recibo.');
        }

        $payment = $this->subscriptionPayments->findReceiptContextByIdForCompany($companyId, $paymentId);
        if ($payment === null) {
            throw new ValidationException('Pagamento nao encontrado para a empresa autenticada.');
        }

        if (trim((string) ($payment['status'] ?? '')) !== 'pago') {
            throw new ValidationException('Somente pagamentos com status pago podem emitir recibo.');
        }

        $paymentDetails = null;
        $paymentDetailsRaw = trim((string) ($payment['payment_details_json'] ?? ''));
        if ($paymentDetailsRaw !== '') {
            $decoded = json_decode($paymentDetailsRaw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $paymentDetails = $decoded;
            }
        }

        $referenceMonth = max(1, min(12, (int) ($payment['reference_month'] ?? 0)));
        $referenceYear = max(2000, (int) ($payment['reference_year'] ?? date('Y')));
        $referenceLabel = sprintf('%02d/%04d', $referenceMonth, $referenceYear);
        $paymentMethod = trim((string) ($payment['payment_method'] ?? ''));
        $receiptNumber = sprintf('REC-%06d-%02d%04d', $paymentId, $referenceMonth, $referenceYear);
        $saasSignature = $this->resolveReceiptSaasSignature($paymentDetails);

        return [
            'payment' => $payment,
            'payment_details' => $paymentDetails,
            'reference_label' => $referenceLabel,
            'receipt_number' => $receiptNumber,
            'status_label' => status_label('subscription_payment_status', (string) ($payment['status'] ?? '')),
            'payment_method_label' => $this->paymentMethodLabel($paymentMethod),
            'billing_cycle_label' => status_label('billing_cycle', (string) ($payment['billing_cycle'] ?? '')),
            'charge_origin_label' => $this->chargeOriginLabel((string) ($payment['charge_origin'] ?? '')),
            'generated_at' => date('Y-m-d H:i:s'),
            'saas_signature' => $saasSignature,
        ];
    }

    public function payChargeWithCard(int $companyId, array $input): void
    {
        throw new ValidationException('Pagamento com cartao deve ser confirmado pelo checkout seguro do gateway. Nao e permitido registrar baixa manual pelo ambiente da empresa.');
    }

    public function confirmPixPayment(int $companyId, array $input): void
    {
        $paymentId = (int) ($input['subscription_payment_id'] ?? 0);
        $payment = $this->loadCompanyPayment($companyId, $paymentId);
        $this->assertPayableCharge($payment);

        if (trim((string) ($payment['gateway_payment_id'] ?? '')) !== '') {
            $this->gatewayService->syncPaymentById($paymentId);
            $this->synchronizeByCompany($companyId);
            return;
        }

        throw new ValidationException('Pagamento PIX deve ser confirmado pelo gateway ou baixado manualmente pelo administrador SaaS. Gere um QR PIX real antes de tentar sincronizar.');
    }

    public function disableAutoCharge(int $companyId): void
    {
        $subscription = $this->subscriptions->findCurrentByCompanyId($companyId);
        if ($subscription === null) {
            throw new ValidationException('Assinatura nao encontrada para a empresa autenticada.');
        }

        $this->subscriptions->updateBillingProfile((int) $subscription['id'], [
            'preferred_payment_method' => 'pix',
            'auto_charge_enabled' => 0,
            'card_brand' => null,
            'card_last_digits' => null,
        ]);

        $this->synchronizeByCompany($companyId);
    }

    public function generatePixGatewayCharge(int $companyId, array $input): void
    {
        $paymentId = (int) ($input['subscription_payment_id'] ?? 0);
        if ($paymentId <= 0) {
            throw new ValidationException('Cobranca invalida para gerar o QR PIX.');
        }

        $this->gatewayService->createPixCharge($companyId, $paymentId);
        $this->synchronizeByCompany($companyId);
    }

    public function generateRecurringGatewayCheckout(int $companyId, ?string $backUrl = null): string
    {
        return $this->gatewayService->createRecurringCheckout($companyId, $backUrl);
    }

    public function refreshGatewayStatus(int $companyId): void
    {
        if ($companyId <= 0) {
            throw new ValidationException('Empresa invalida para atualizar o status da assinatura.');
        }

        $subscription = $this->subscriptions->findCurrentByCompanyId($companyId);
        if ($subscription === null) {
            throw new ValidationException('Assinatura nao encontrada para atualizar o status.');
        }

        $this->gatewayService->syncSubscriptionByCompany($companyId);
        $this->synchronizeByCompany($companyId);
    }

    public function refreshPaymentGatewayStatus(int $companyId, int $paymentId): array
    {
        if ($companyId <= 0 || $paymentId <= 0) {
            throw new ValidationException('Cobranca invalida para atualizar o status no gateway.');
        }

        $payment = $this->loadCompanyPayment($companyId, $paymentId);
        $gatewayPaymentId = trim((string) ($payment['gateway_payment_id'] ?? ''));
        if ($gatewayPaymentId === '') {
            throw new ValidationException('Esta cobranca ainda nao possui vinculo real com o gateway.');
        }

        $this->gatewayService->syncPaymentById($paymentId);
        $this->synchronizeByCompany($companyId);

        $updatedPayment = $this->loadCompanyPayment($companyId, $paymentId);
        $updatedPayment['status_label'] = status_label('subscription_payment_status', (string) ($updatedPayment['status'] ?? ''));

        return $updatedPayment;
    }

    public function synchronizeCompanyBilling(int $companyId): void
    {
        $this->synchronizeByCompany($companyId);
    }

    public function setNextChargeDueDate(int $companyId, string $dueDate): void
    {
        if ($companyId <= 0) {
            throw new ValidationException('Empresa invalida para ajustar vencimento.');
        }

        $normalizedDueDate = $this->normalizeDueDateInput($dueDate);
        $this->synchronizeByCompany($companyId);

        $subscription = $this->subscriptions->findCurrentByCompanyId($companyId);
        if ($subscription === null) {
            throw new ValidationException('Assinatura nao encontrada para ajustar vencimento.');
        }

        $openPayments = $this->subscriptionPayments->listOpenBySubscriptionId((int) ($subscription['id'] ?? 0));
        if ($openPayments === []) {
            $this->ensureNextOpenCharge($subscription, false);
            $openPayments = $this->subscriptionPayments->listOpenBySubscriptionId((int) ($subscription['id'] ?? 0));
        }

        $payment = $openPayments[0] ?? null;
        if (!is_array($payment)) {
            throw new ValidationException('Nao foi possivel localizar a proxima cobranca para ajustar o vencimento.');
        }

        $this->updatePaymentDueDate($payment, $normalizedDueDate);
        $this->synchronizeByCompany($companyId);
    }

    public function resolveAccessState(int $companyId): array
    {
        if ($companyId <= 0) {
            return $this->defaultAccessState();
        }

        return $this->buildAccessState($companyId, true);
    }

    public function resolveAccessStateForMiddleware(int $companyId): array
    {
        if ($companyId <= 0) {
            return $this->defaultAccessState();
        }

        return $this->buildAccessState($companyId, false);
    }

    private function buildAccessState(int $companyId, bool $synchronize): array
    {
        if ($synchronize) {
            $this->synchronizeByCompany($companyId);
        }

        $subscription = $this->subscriptions->findCurrentByCompanyId($companyId);
        if ($subscription === null) {
            return $this->defaultAccessState();
        }

        if ($this->isActiveGatewayCardSubscription($subscription)) {
            $state = $this->defaultAccessState();
            $openPayments = $this->subscriptionPayments->listOpenBySubscriptionId((int) ($subscription['id'] ?? 0));
            $nextPayment = $openPayments[0] ?? null;
            if (is_array($nextPayment)) {
                $state['next_due_date'] = (string) ($nextPayment['due_date'] ?? '');
            }
            return $state;
        }

        $openPayments = $this->subscriptionPayments->listOpenBySubscriptionId((int) ($subscription['id'] ?? 0));
        $nextPayment = $openPayments[0] ?? null;
        if (!is_array($nextPayment)) {
            return $this->defaultAccessState();
        }

        $today = new DateTimeImmutable('today');
        $dueDate = $this->dateOrToday((string) ($nextPayment['due_date'] ?? ''));
        $overdueDays = $dueDate < $today ? (int) $dueDate->diff($today)->days : 0;
        $isBlocked = $overdueDays >= 4;
        $isWarning = $overdueDays >= 1 && $overdueDays <= 3;

        $headline = '';
        $message = '';
        if ($isBlocked) {
            $headline = 'Sistema bloqueado por falta de pagamento';
            $message = 'O vencimento da assinatura passou do prazo de carencia. Regularize a cobranca para liberar novamente o acesso completo ao sistema.';
        } elseif ($isWarning) {
            $daysUntilBlock = max(0, 4 - $overdueDays);
            $headline = 'Assinatura em atraso';
            $message = $daysUntilBlock === 1
                ? 'A cobranca venceu e o sistema sera bloqueado amanha se o pagamento nao for regularizado.'
                : 'A cobranca venceu e o sistema sera bloqueado em ' . $daysUntilBlock . ' dia(s) se o pagamento nao for regularizado.';
        }

        return [
            'restricted' => $isWarning || $isBlocked,
            'is_warning' => $isWarning,
            'is_blocked' => $isBlocked,
            'overdue_days' => $overdueDays,
            'next_due_date' => (string) ($nextPayment['due_date'] ?? ''),
            'allowed_sections' => ['support', 'subscription'],
            'headline' => $headline,
            'message' => $message,
        ];
    }

    private function synchronizeByCompany(int $companyId): void
    {
        $subscription = $this->subscriptions->findCurrentByCompanyId($companyId);
        if ($subscription === null) {
            return;
        }

        $this->synchronizeSubscription($subscription);
    }

    private function synchronizeSubscription(array $subscription): void
    {
        $subscriptionId = (int) ($subscription['id'] ?? 0);
        $companyId = (int) ($subscription['company_id'] ?? 0);
        if ($subscriptionId <= 0 || $companyId <= 0) {
            return;
        }

        $status = trim((string) ($subscription['status'] ?? ''));
        $preferredMethod = trim((string) ($subscription['preferred_payment_method'] ?? ''));
        $autoChargeEnabled = !empty($subscription['auto_charge_enabled']);
        $usesAutoCard = $autoChargeEnabled && in_array($preferredMethod, self::CARD_METHODS, true);
        $activeGatewayCard = $this->isActiveGatewayCardSubscription($subscription);

        $payments = $this->subscriptionPayments->listBySubscriptionId($subscriptionId, 240);
        $today = new DateTimeImmutable('today');

        foreach ($payments as $payment) {
            $paymentId = (int) ($payment['id'] ?? 0);
            if ($paymentId <= 0) {
                continue;
            }

            $paymentStatus = trim((string) ($payment['status'] ?? ''));
            if ($paymentStatus === 'cancelado' || $paymentStatus === 'pago') {
                continue;
            }

            $dueDate = $this->dateOrToday((string) ($payment['due_date'] ?? ''));
            $targetStatus = $paymentStatus;
            if ($activeGatewayCard && $paymentStatus === 'vencido') {
                $targetStatus = 'pendente';
            } elseif (!$activeGatewayCard && $paymentStatus === 'pendente' && $dueDate < $today) {
                $targetStatus = 'vencido';
            }

            $pixPayload = [
                'pix_code' => $payment['pix_code'] ?? null,
                'pix_qr_payload' => $payment['pix_qr_payload'] ?? null,
            ];
            if (!$usesAutoCard
                && trim((string) ($pixPayload['pix_code'] ?? '')) === ''
                && trim((string) ($pixPayload['pix_qr_payload'] ?? '')) === '') {
                $pixPayload = $this->ensurePixPayload($payment);
            }

            $this->subscriptionPayments->updateRecord($paymentId, [
                'status' => $targetStatus,
                'payment_method' => $this->normalizeNullableText($payment['payment_method'] ?? null) ?? ($usesAutoCard ? $preferredMethod : 'pix'),
                'paid_at' => null,
                'due_date' => (string) $payment['due_date'],
                'transaction_reference' => $this->normalizeNullableText($payment['transaction_reference'] ?? null),
                'charge_origin' => $this->normalizeNullableText($payment['charge_origin'] ?? null) ?? ($usesAutoCard ? 'auto' : 'pix'),
                'pix_code' => $pixPayload['pix_code'],
                'pix_qr_payload' => $pixPayload['pix_qr_payload'],
                'pix_qr_image_base64' => $payment['pix_qr_image_base64'] ?? null,
                'pix_ticket_url' => $payment['pix_ticket_url'] ?? null,
                'payment_details_json' => $payment['payment_details_json'] ?? null,
                'gateway_payment_id' => $payment['gateway_payment_id'] ?? null,
                'gateway_payment_url' => $payment['gateway_payment_url'] ?? null,
                'gateway_status' => $payment['gateway_status'] ?? null,
                'gateway_webhook_payload_json' => $payment['gateway_webhook_payload_json'] ?? null,
                'gateway_last_synced_at' => $payment['gateway_last_synced_at'] ?? null,
            ]);
        }

        $this->applyCreditBalanceToOpenPayments($subscriptionId);
        $this->ensureNextOpenCharge($subscription, $usesAutoCard);

        $payments = $this->subscriptionPayments->listBySubscriptionId($subscriptionId, 240);
        $hasOverdue = false;
        $hasPaid = false;
        foreach ($payments as $payment) {
            $paymentStatus = trim((string) ($payment['status'] ?? ''));
            if ($paymentStatus === 'vencido' && !$activeGatewayCard) {
                $hasOverdue = true;
            }
            if ($paymentStatus === 'pago') {
                $hasPaid = true;
            }
        }

        $targetSubscriptionStatus = $status;
        $targetCompanyStatus = trim((string) ($subscription['company_subscription_status'] ?? ''));

        if ($status === 'cancelada') {
            $targetSubscriptionStatus = 'cancelada';
            $targetCompanyStatus = 'cancelada';
        } elseif ($activeGatewayCard) {
            $targetSubscriptionStatus = 'ativa';
            $targetCompanyStatus = 'ativa';
        } elseif ($hasOverdue) {
            $targetSubscriptionStatus = 'vencida';
            $targetCompanyStatus = 'inadimplente';
        } elseif ($status === 'trial' && !$hasPaid) {
            $targetSubscriptionStatus = 'trial';
            $targetCompanyStatus = 'trial';
        } else {
            $targetSubscriptionStatus = 'ativa';
            $targetCompanyStatus = 'ativa';
        }

        if ($targetSubscriptionStatus !== $status) {
            $this->subscriptions->updateStatus($subscriptionId, $targetSubscriptionStatus);
        }

        $this->companies->updateSubscriptionSnapshot($companyId, [
            'plan_id' => $subscription['plan_id'] ?? null,
            'subscription_status' => $targetCompanyStatus,
            'trial_ends_at' => $targetSubscriptionStatus === 'trial'
                ? ($subscription['company_trial_ends_at'] ?? null)
                : null,
            'subscription_starts_at' => $subscription['company_subscription_starts_at'] ?? $subscription['starts_at'] ?? null,
            'subscription_ends_at' => $subscription['ends_at'] ?? $subscription['company_subscription_ends_at'] ?? null,
        ]);
    }

    private function ensureNextOpenCharge(array $subscription, bool $usesAutoCard): void
    {
        $subscriptionId = (int) ($subscription['id'] ?? 0);
        $companyId = (int) ($subscription['company_id'] ?? 0);
        $status = trim((string) ($subscription['status'] ?? ''));
        if ($subscriptionId <= 0 || $companyId <= 0 || $status === 'cancelada') {
            return;
        }

        if ($this->subscriptionPayments->listOpenBySubscriptionId($subscriptionId) !== []) {
            return;
        }

        $history = $this->subscriptionPayments->listBySubscriptionId($subscriptionId, 240);
        $lastCharge = $history[0] ?? null;

        $billingCycle = trim((string) ($subscription['billing_cycle'] ?? 'mensal'));
        $amount = round((float) ($subscription['amount'] ?? 0), 2);
        $baseDueDate = $this->dateOrToday((string) ($lastCharge['due_date'] ?? ($subscription['starts_at'] ?? date('Y-m-d'))));
        $baseReferenceMonth = (int) ($lastCharge['reference_month'] ?? $baseDueDate->format('n'));
        $baseReferenceYear = (int) ($lastCharge['reference_year'] ?? $baseDueDate->format('Y'));

        if ($lastCharge === null) {
            $targetReferenceMonth = $baseReferenceMonth;
            $targetReferenceYear = $baseReferenceYear;
        } elseif ($billingCycle === 'anual') {
            $targetReferenceMonth = $baseReferenceMonth;
            $targetReferenceYear = $baseReferenceYear + 1;
        } else {
            $targetReferenceMonth = $baseReferenceMonth + 1;
            $targetReferenceYear = $baseReferenceYear;
            if ($targetReferenceMonth > 12) {
                $targetReferenceMonth = 1;
                $targetReferenceYear++;
            }
        }

        if ($this->subscriptionPayments->findByReference($subscriptionId, $targetReferenceMonth, $targetReferenceYear) !== null) {
            return;
        }

        $targetDueDate = $this->buildDueDate(
            $targetReferenceYear,
            $targetReferenceMonth,
            (int) $baseDueDate->format('j')
        )->format('Y-m-d');

        $pixPayload = $usesAutoCard
            ? ['pix_code' => null, 'pix_qr_payload' => null]
            : $this->buildPixPayload($companyId, $subscriptionId, $targetReferenceYear, $targetReferenceMonth, $amount);
        $creditConsumption = $this->consumeCreditBalance($subscriptionId, $amount);
        $chargeAmount = $creditConsumption['amount'];
        $paymentStatus = $chargeAmount <= 0 ? 'pago' : 'pendente';
        $paymentMethod = $paymentStatus === 'pago'
            ? 'pix'
            : ($usesAutoCard ? trim((string) ($subscription['preferred_payment_method'] ?? '')) : 'pix');
        $paymentDetails = $creditConsumption['applied'] > 0
            ? json_encode([
                'source' => 'billing_credit_balance',
                'credit_applied' => round($creditConsumption['applied'], 2),
                'credit_applied_at' => date('c'),
            ], JSON_UNESCAPED_SLASHES)
            : null;

        $this->subscriptionPayments->create([
            'subscription_id' => $subscriptionId,
            'company_id' => $companyId,
            'reference_month' => $targetReferenceMonth,
            'reference_year' => $targetReferenceYear,
            'amount' => $chargeAmount,
            'status' => $paymentStatus,
            'payment_method' => $paymentMethod,
            'paid_at' => $paymentStatus === 'pago' ? date('Y-m-d H:i:s') : null,
            'due_date' => $targetDueDate,
            'transaction_reference' => $paymentStatus === 'pago' ? $this->buildChargeReference('CREDIT', $subscriptionId, 'saldo') : null,
            'charge_origin' => $creditConsumption['applied'] > 0 ? 'migracao' : ($usesAutoCard ? 'auto' : 'pix'),
            'pix_code' => $pixPayload['pix_code'],
            'pix_qr_payload' => $pixPayload['pix_qr_payload'],
            'payment_details_json' => $paymentDetails,
        ]);
    }

    private function updatePaymentDueDate(array $payment, string $dueDate): void
    {
        $currentDueDate = $this->dateOrToday($dueDate);
        $today = new DateTimeImmutable('today');
        $targetStatus = $currentDueDate < $today ? 'vencido' : 'pendente';

        $this->subscriptionPayments->updateRecord((int) ($payment['id'] ?? 0), [
            'status' => $targetStatus,
            'payment_method' => $payment['payment_method'] ?? null,
            'paid_at' => null,
            'due_date' => $dueDate,
            'transaction_reference' => null,
            'charge_origin' => $payment['charge_origin'] ?? 'manual',
            'pix_code' => $payment['pix_code'] ?? null,
            'pix_qr_payload' => $payment['pix_qr_payload'] ?? null,
            'pix_qr_image_base64' => $payment['pix_qr_image_base64'] ?? null,
            'pix_ticket_url' => $payment['pix_ticket_url'] ?? null,
            'payment_details_json' => $payment['payment_details_json'] ?? null,
            'gateway_payment_id' => $payment['gateway_payment_id'] ?? null,
            'gateway_payment_url' => $payment['gateway_payment_url'] ?? null,
            'gateway_status' => $payment['gateway_status'] ?? null,
            'gateway_webhook_payload_json' => $payment['gateway_webhook_payload_json'] ?? null,
            'gateway_last_synced_at' => $payment['gateway_last_synced_at'] ?? null,
        ]);
    }

    private function expectedCharges(array $subscription): array
    {
        $startsAt = $this->dateOrToday((string) ($subscription['starts_at'] ?? date('Y-m-d')));
        $limit = $this->generationLimit($subscription);
        if ($limit < $startsAt) {
            return [];
        }

        $billingCycle = trim((string) ($subscription['billing_cycle'] ?? 'mensal'));
        $amount = round((float) ($subscription['amount'] ?? 0), 2);
        $charges = [];

        if ($billingCycle === 'anual') {
            $startYear = (int) $startsAt->format('Y');
            $limitYear = (int) $limit->format('Y');
            $referenceMonth = (int) $startsAt->format('n');
            $referenceDay = (int) $startsAt->format('j');

            for ($year = $startYear; $year <= $limitYear; $year++) {
                if ($year === $limitYear && (int) $limit->format('n') < $referenceMonth) {
                    break;
                }

                $charges[] = [
                    'reference_month' => $referenceMonth,
                    'reference_year' => $year,
                    'amount' => $amount,
                    'due_date' => $this->buildDueDate($year, $referenceMonth, $referenceDay)->format('Y-m-d'),
                ];
            }

            return $charges;
        }

        $cursorYear = (int) $startsAt->format('Y');
        $cursorMonth = (int) $startsAt->format('n');
        $limitYear = (int) $limit->format('Y');
        $limitMonth = (int) $limit->format('n');
        $referenceDay = (int) $startsAt->format('j');

        while ($cursorYear < $limitYear || ($cursorYear === $limitYear && $cursorMonth <= $limitMonth)) {
            $charges[] = [
                'reference_month' => $cursorMonth,
                'reference_year' => $cursorYear,
                'amount' => $amount,
                'due_date' => $this->buildDueDate($cursorYear, $cursorMonth, $referenceDay)->format('Y-m-d'),
            ];

            $cursorMonth++;
            if ($cursorMonth > 12) {
                $cursorMonth = 1;
                $cursorYear++;
            }
        }

        return $charges;
    }

    private function generationLimit(array $subscription): DateTimeImmutable
    {
        $today = new DateTimeImmutable('today');
        $status = trim((string) ($subscription['status'] ?? ''));

        if ($status === 'cancelada') {
            $canceledAt = trim((string) ($subscription['canceled_at'] ?? ''));
            if ($canceledAt !== '') {
                $canceledDate = $this->dateOrToday($canceledAt);
                return $canceledDate < $today ? $canceledDate : $today;
            }
        }

        return $today;
    }

    private function buildSummary(array $subscription, array $history, array $openPayments): array
    {
        $openAmount = 0.0;
        $paidAmount = 0.0;
        $overdueCount = 0;
        $nextDueDate = null;

        foreach ($history as $payment) {
            $amount = (float) ($payment['amount'] ?? 0);
            $status = trim((string) ($payment['status'] ?? ''));
            if ($status === 'pago') {
                $paidAmount += $amount;
            }
            if ($status === 'vencido') {
                $overdueCount++;
            }
        }

        foreach ($openPayments as $payment) {
            $openAmount += (float) ($payment['amount'] ?? 0);
            $dueDate = trim((string) ($payment['due_date'] ?? ''));
            if ($dueDate === '') {
                continue;
            }

            if ($nextDueDate === null || strcmp($dueDate, $nextDueDate) < 0) {
                $nextDueDate = $dueDate;
            }
        }

        return [
            'total_history' => count($history),
            'open_count' => count($openPayments),
            'overdue_count' => $overdueCount,
            'open_amount' => round($openAmount, 2),
            'paid_amount' => round($paidAmount, 2),
            'next_due_date' => $nextDueDate,
            'plan_name' => $subscription['plan_name'] ?? null,
            'billing_cycle' => $subscription['billing_cycle'] ?? null,
            'credit_balance' => round((float) ($subscription['billing_credit_balance'] ?? 0), 2),
            'preferred_payment_method' => $subscription['preferred_payment_method'] ?? null,
            'auto_charge_enabled' => !empty($subscription['auto_charge_enabled']),
        ];
    }

    private function paymentMethodLabel(string $method): string
    {
        return match ($method) {
            'pix' => 'Pix',
            'credito' => 'Cartao de credito',
            'debito' => 'Cartao de debito',
            default => 'Nao definido',
        };
    }

    private function chargeOriginLabel(string $origin): string
    {
        return match (trim($origin)) {
            'auto' => 'Recorrencia automatica',
            'pix' => 'Cobranca PIX',
            'manual' => 'Baixa manual',
            'migracao' => 'Migracao de plano',
            default => trim($origin) !== '' ? trim($origin) : 'Nao informado',
        };
    }

    private function buildPlanMigrationOffers(array $subscription): array
    {
        $currentPlanId = (int) ($subscription['plan_id'] ?? 0);
        $offers = [];

        foreach ($this->plans->listActiveForPublicCatalog() as $plan) {
            $planId = (int) ($plan['id'] ?? 0);
            if ($planId <= 0) {
                continue;
            }

            $features = $this->featureCatalog->summaryFromJson($plan['features_json'] ?? null);
            $offers[] = [
                'id' => $planId,
                'name' => (string) ($plan['name'] ?? 'Plano'),
                'description' => (string) ($plan['description'] ?? ''),
                'price_monthly' => round((float) ($plan['price_monthly'] ?? 0), 2),
                'price_yearly' => round((float) ($plan['price_yearly'] ?? 0), 2),
                'supports_monthly' => round((float) ($plan['price_monthly'] ?? 0), 2) > 0,
                'supports_yearly' => round((float) ($plan['price_yearly'] ?? 0), 2) > 0,
                'is_current_plan' => $planId === $currentPlanId,
                'features' => array_slice($features, 0, 8),
            ];
        }

        return $offers;
    }

    private function buildPlanMigrationContext(array $subscription, array $openPayments): array
    {
        $offers = $this->buildPlanMigrationOffers($subscription);
        $alternativeOffers = array_values(array_filter(
            $offers,
            static fn (array $offer): bool => empty($offer['is_current_plan'])
        ));

        $today = new DateTimeImmutable('today');
        $blockers = [];
        if (!$this->isActiveGatewayCardSubscription($subscription)) {
            foreach ($openPayments as $payment) {
                $dueDate = $this->dateOrToday((string) ($payment['due_date'] ?? ''));
                if ($dueDate <= $today) {
                    $blockers[] = 'A migracao esta bloqueada porque existe cobranca do ciclo atual em aberto ou vencida.';
                    break;
                }
            }
        }

        if ($alternativeOffers === []) {
            $blockers[] = 'Nao existe outro plano ativo disponivel para autoatendimento neste momento.';
        }

        $notes = [];
        if (!empty($subscription['auto_charge_enabled']) && in_array((string) ($subscription['preferred_payment_method'] ?? ''), self::CARD_METHODS, true)) {
            $notes[] = 'Se a empresa usa cartao recorrente, a nova recorrencia precisara de nova autorizacao no valor atualizado.';
        }

        $creditBalance = round((float) ($subscription['billing_credit_balance'] ?? 0), 2);
        if ($creditBalance > 0) {
            $notes[] = 'A assinatura ja possui saldo de credito acumulado de R$ ' . number_format($creditBalance, 2, ',', '.') . ' para abatimento.';
        }

        return [
            'offers' => $offers,
            'can_self_migrate' => $blockers === [],
            'blockers' => $blockers,
            'notes' => $notes,
            'current_credit_balance' => $creditBalance,
        ];
    }

    private function applyCreditBalanceToOpenPayments(int $subscriptionId): void
    {
        $subscription = $this->subscriptions->findById($subscriptionId);
        if ($subscription === null) {
            return;
        }

        $remainingCredit = round((float) ($subscription['billing_credit_balance'] ?? 0), 2);
        if ($remainingCredit <= 0) {
            return;
        }

        foreach ($this->subscriptionPayments->listOpenBySubscriptionId($subscriptionId) as $payment) {
            if ($remainingCredit <= 0) {
                break;
            }

            $paymentId = (int) ($payment['id'] ?? 0);
            $amount = round((float) ($payment['amount'] ?? 0), 2);
            if ($paymentId <= 0 || $amount <= 0) {
                continue;
            }

            $applied = round(min($amount, $remainingCredit), 2);
            $newAmount = round(max(0, $amount - $applied), 2);
            $details = $this->mergePaymentDetails($payment, [
                'credit_balance_application' => [
                    'applied' => $applied,
                    'applied_at' => date('c'),
                ],
            ]);

            $this->subscriptionPayments->updateAmount($paymentId, $newAmount, json_encode($details, JSON_UNESCAPED_SLASHES));
            if ($newAmount <= 0) {
                $this->subscriptionPayments->updateRecord($paymentId, [
                    'status' => 'pago',
                    'payment_method' => 'pix',
                    'paid_at' => date('Y-m-d H:i:s'),
                    'due_date' => (string) ($payment['due_date'] ?? date('Y-m-d')),
                    'transaction_reference' => $this->buildChargeReference('CREDIT', $paymentId, 'saldo'),
                    'charge_origin' => 'migracao',
                    'pix_code' => null,
                    'pix_qr_payload' => null,
                    'pix_qr_image_base64' => null,
                    'pix_ticket_url' => null,
                    'payment_details_json' => json_encode($details, JSON_UNESCAPED_SLASHES),
                    'gateway_payment_id' => null,
                    'gateway_payment_url' => null,
                    'gateway_status' => null,
                    'gateway_webhook_payload_json' => null,
                    'gateway_last_synced_at' => null,
                ]);
            }

            $remainingCredit = round(max(0, $remainingCredit - $applied), 2);
        }

        $this->subscriptions->updateBillingCreditBalance($subscriptionId, $remainingCredit);
    }

    private function consumeCreditBalance(int $subscriptionId, float $amount): array
    {
        $subscription = $this->subscriptions->findById($subscriptionId);
        if ($subscription === null) {
            return ['amount' => round($amount, 2), 'applied' => 0.0];
        }

        $creditBalance = round((float) ($subscription['billing_credit_balance'] ?? 0), 2);
        if ($creditBalance <= 0 || $amount <= 0) {
            return ['amount' => round($amount, 2), 'applied' => 0.0];
        }

        $applied = round(min($creditBalance, $amount), 2);
        $remainingCredit = round(max(0, $creditBalance - $applied), 2);
        $this->subscriptions->updateBillingCreditBalance($subscriptionId, $remainingCredit);

        return [
            'amount' => round(max(0, $amount - $applied), 2),
            'applied' => $applied,
        ];
    }

    private function preparePlanMigration(int $companyId, array $input): array
    {
        if ($companyId <= 0) {
            throw new ValidationException('Empresa invalida para trocar o plano.');
        }

        $subscription = $this->subscriptions->findCurrentByCompanyId($companyId);
        if ($subscription === null) {
            throw new ValidationException('Assinatura nao encontrada para troca de plano.');
        }

        $planId = (int) ($input['plan_id'] ?? 0);
        if ($planId <= 0) {
            throw new ValidationException('Selecione um plano valido para visualizar a migracao.');
        }

        $billingCycle = strtolower(trim((string) ($input['billing_cycle'] ?? '')));
        if (!in_array($billingCycle, ['mensal', 'anual'], true)) {
            throw new ValidationException('Selecione um ciclo valido para a migracao do plano.');
        }

        $plan = $this->plans->findById($planId);
        if ($plan === null || strtolower(trim((string) ($plan['status'] ?? ''))) !== 'ativo') {
            throw new ValidationException('O plano selecionado nao esta disponivel para migracao no momento.');
        }

        $targetAmount = $billingCycle === 'anual'
            ? round((float) ($plan['price_yearly'] ?? 0), 2)
            : round((float) ($plan['price_monthly'] ?? 0), 2);
        if ($targetAmount <= 0) {
            throw new ValidationException('O ciclo selecionado ainda nao esta precificado para autoatendimento. Escolha outro ciclo ou fale com o comercial.');
        }

        $currentPlanId = (int) ($subscription['plan_id'] ?? 0);
        $currentBillingCycle = strtolower(trim((string) ($subscription['billing_cycle'] ?? '')));
        $currentAmount = round((float) ($subscription['amount'] ?? 0), 2);
        if ($currentPlanId === $planId && $currentBillingCycle === $billingCycle && $currentAmount === $targetAmount) {
            throw new ValidationException('Esse ja e o plano atual da empresa. Escolha outro plano ou outro ciclo.');
        }

        $targetSubscription = [
            'plan_id' => $planId,
            'status' => 'ativa',
            'billing_cycle' => $billingCycle,
            'amount' => $targetAmount,
            'starts_at' => (string) ($subscription['starts_at'] ?? date('Y-m-d H:i:s')),
            'ends_at' => (string) ($subscription['ends_at'] ?? date('Y-m-d H:i:s')),
            'canceled_at' => null,
        ];

        $preparedMigration = $this->planMigration->previewMigration(
            $subscription,
            $targetSubscription,
            isset($input['charge_due_date']) ? (string) $input['charge_due_date'] : null
        );

        return [$subscription, $plan, $targetSubscription, $preparedMigration];
    }

    private function buildPlanMigrationPreviewPayload(
        array $currentSubscription,
        array $targetPlan,
        array $targetSubscription,
        array $preparedMigration
    ): array {
        $migrationMeta = is_array($preparedMigration['migration_meta'] ?? null)
            ? $preparedMigration['migration_meta']
            : [];
        $preparedSubscription = is_array($preparedMigration['subscription'] ?? null)
            ? $preparedMigration['subscription']
            : [];
        $targetFeatures = $this->featureCatalog->summaryFromJson($targetPlan['features_json'] ?? null);
        $chargeAmount = round((float) ($preparedMigration['charge_amount'] ?? 0), 2);
        $carryCredit = round((float) ($migrationMeta['carry_credit'] ?? 0), 2);
        $unusedCredit = round((float) ($migrationMeta['unused_credit'] ?? 0), 2);
        $creditBalanceAfter = round((float) ($preparedSubscription['billing_credit_balance'] ?? 0), 2);

        return [
            'current' => [
                'plan_name' => (string) ($currentSubscription['plan_name'] ?? 'Plano atual'),
                'billing_cycle' => (string) ($currentSubscription['billing_cycle'] ?? ''),
                'billing_cycle_label' => status_label('billing_cycle', (string) ($currentSubscription['billing_cycle'] ?? '')),
                'amount' => round((float) ($currentSubscription['amount'] ?? 0), 2),
            ],
            'target' => [
                'plan_id' => (int) ($targetPlan['id'] ?? 0),
                'plan_name' => (string) ($targetPlan['name'] ?? 'Novo plano'),
                'billing_cycle' => (string) ($targetSubscription['billing_cycle'] ?? ''),
                'billing_cycle_label' => status_label('billing_cycle', (string) ($targetSubscription['billing_cycle'] ?? '')),
                'amount' => round((float) ($targetSubscription['amount'] ?? 0), 2),
                'features' => array_slice($targetFeatures, 0, 8),
            ],
            'proration' => [
                'unused_credit' => $unusedCredit,
                'charge_amount' => $chargeAmount,
                'carry_credit' => $carryCredit,
                'credit_balance_after' => $creditBalanceAfter,
                'charge_due_date' => (string) ($preparedMigration['charge_due_date'] ?? date('Y-m-d')),
                'new_cycle_start' => (string) ($preparedSubscription['starts_at'] ?? ''),
                'new_cycle_end' => (string) ($preparedSubscription['ends_at'] ?? ''),
            ],
            'rules' => [
                'requires_payment' => $chargeAmount > 0,
                'requires_gateway_reauthorization' => !empty($migrationMeta['gateway_reauthorization_required']),
            ],
        ];
    }

    private function resolveReceiptSaasSignature(?array $paymentDetails): array
    {
        $paymentDetails = is_array($paymentDetails) ? $paymentDetails : [];
        $signature = is_array($paymentDetails['saas_admin_signature'] ?? null)
            ? $paymentDetails['saas_admin_signature']
            : [];

        $signedAt = trim((string) ($signature['signed_at'] ?? ''));
        if ($signedAt === '') {
            $signedAt = trim((string) ($paymentDetails['marked_paid_at'] ?? ''));
        }

        $name = trim((string) ($signature['name'] ?? ''));
        $email = trim((string) ($signature['email'] ?? ''));
        $roleName = trim((string) ($signature['role_name'] ?? 'Administrador SaaS'));

        return [
            'mode' => ($name !== '' || $email !== '') ? 'named' : 'institutional',
            'name' => $name !== '' ? $name : 'MesiMenu SaaS',
            'email' => $email,
            'role_name' => $roleName !== '' ? $roleName : 'Administrador SaaS',
            'signed_at' => $signedAt,
            'type' => trim((string) ($signature['type'] ?? 'system_issued')),
        ];
    }

    private function extractFeatureSummary(array $subscription): array
    {
        return $this->featureCatalog->summaryFromJson($subscription['plan_features_json'] ?? null);
    }

    private function ensurePixPayload(array $payment): array
    {
        $pixCode = trim((string) ($payment['pix_code'] ?? ''));
        $pixQrPayload = trim((string) ($payment['pix_qr_payload'] ?? ''));
        if ($pixCode !== '' && $pixQrPayload !== '') {
            return [
                'pix_code' => $pixCode,
                'pix_qr_payload' => $pixQrPayload,
            ];
        }

        return $this->buildPixPayload(
            (int) ($payment['company_id'] ?? 0),
            (int) ($payment['subscription_id'] ?? 0),
            (int) ($payment['reference_year'] ?? 0),
            (int) ($payment['reference_month'] ?? 0),
            (float) ($payment['amount'] ?? 0)
        );
    }

    private function buildPixPayload(
        int $companyId,
        int $subscriptionId,
        int $referenceYear,
        int $referenceMonth,
        float $amount
    ): array {
        $pixCode = sprintf(
            'PIX-C%05d-S%05d-%04d%02d',
            max(0, $companyId),
            max(0, $subscriptionId),
            max(0, $referenceYear),
            max(0, $referenceMonth)
        );

        $payload = sprintf(
            'pix://subscription/%d/%d/%04d/%02d?amount=%s',
            max(0, $companyId),
            max(0, $subscriptionId),
            max(0, $referenceYear),
            max(0, $referenceMonth),
            number_format(max(0, $amount), 2, '.', '')
        );

        return [
            'pix_code' => $pixCode,
            'pix_qr_payload' => $payload,
        ];
    }

    private function buildDueDate(int $year, int $month, int $day): DateTimeImmutable
    {
        $maxDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $safeDay = max(1, min($day, $maxDay));
        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $safeDay));
    }

    private function loadCompanyPayment(int $companyId, int $paymentId): array
    {
        if ($companyId <= 0 || $paymentId <= 0) {
            throw new ValidationException('Cobranca invalida para a empresa autenticada.');
        }

        $payment = $this->subscriptionPayments->findByIdForCompany($companyId, $paymentId);
        if ($payment === null) {
            throw new ValidationException('Cobranca nao encontrada para esta empresa.');
        }

        return $payment;
    }

    private function assertPayableCharge(array $payment): void
    {
        $status = trim((string) ($payment['status'] ?? ''));
        if ($status === 'pago') {
            throw new ValidationException('Esta cobranca ja foi quitada.');
        }

        if ($status === 'cancelado') {
            throw new ValidationException('Nao e possivel pagar uma cobranca cancelada.');
        }
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text !== '' ? $text : null;
    }

    private function isActiveGatewayCardSubscription(array $subscription): bool
    {
        $preferredMethod = strtolower(trim((string) ($subscription['preferred_payment_method'] ?? '')));
        if (empty($subscription['auto_charge_enabled']) || !in_array($preferredMethod, self::CARD_METHODS, true)) {
            return false;
        }

        $gatewaySubscriptionId = trim((string) ($subscription['gateway_subscription_id'] ?? ''));
        if ($gatewaySubscriptionId === '') {
            return false;
        }

        $gatewayStatus = strtolower(trim((string) ($subscription['gateway_status'] ?? '')));
        return in_array($gatewayStatus, ['authorized', 'active'], true);
    }

    private function mergePaymentDetails(array $payment, array $merge): array
    {
        $details = [];
        $raw = trim((string) ($payment['payment_details_json'] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $details = $decoded;
            }
        }

        return array_replace_recursive($details, $merge);
    }

    private function buildChargeReference(string $prefix, int $paymentId, string $method): string
    {
        return sprintf('%s-%s-%06d', strtoupper($prefix), strtoupper($method), max(0, $paymentId));
    }

    private function dateOrToday(string $value): DateTimeImmutable
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return new DateTimeImmutable('today');
        }

        $timestamp = strtotime($normalized);
        if ($timestamp === false) {
            return new DateTimeImmutable('today');
        }

        return new DateTimeImmutable(date('Y-m-d', $timestamp));
    }

    private function emptyPanel(): array
    {
        return [
            'subscription' => null,
            'open_payments' => [],
            'payment_history' => [],
            'history_filters' => [
                'search' => '',
                'status' => '',
                'method' => '',
                'page' => 1,
            ],
            'history_pagination' => [
                'page' => 1,
                'last_page' => 1,
                'total' => 0,
                'from' => 0,
                'to' => 0,
                'pages' => [1],
            ],
            'features' => [],
            'summary' => [
                'total_history' => 0,
                'open_count' => 0,
                'overdue_count' => 0,
                'open_amount' => 0.0,
                'paid_amount' => 0.0,
                'next_due_date' => null,
                'plan_name' => null,
                'billing_cycle' => null,
                'credit_balance' => 0.0,
                'preferred_payment_method' => null,
                'auto_charge_enabled' => false,
            ],
            'billing_access' => $this->defaultAccessState(),
            'gateway' => [
                'configured' => $this->gatewayService->isConfigured(),
                'provider' => $this->gatewayService->providerName(),
            ],
        ];
    }

    private function normalizeHistoryFilters(array $filters): array
    {
        return [
            'search' => trim((string) ($filters['subscription_history_search'] ?? '')),
            'status' => trim((string) ($filters['subscription_history_status'] ?? '')),
            'method' => trim((string) ($filters['subscription_history_method'] ?? '')),
            'page' => max(1, (int) ($filters['subscription_history_page'] ?? 1)),
        ];
    }

    private function buildPaginationPayload(array $pagination): array
    {
        $page = max(1, (int) ($pagination['page'] ?? 1));
        $perPage = max(1, (int) ($pagination['per_page'] ?? 10));
        $total = max(0, (int) ($pagination['total'] ?? 0));
        $lastPage = max(1, (int) ($pagination['last_page'] ?? 1));
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to = $total > 0 ? min($total, $page * $perPage) : 0;

        $start = max(1, $page - 2);
        $end = min($lastPage, $page + 2);
        $pages = [];
        for ($cursor = $start; $cursor <= $end; $cursor++) {
            $pages[] = $cursor;
        }

        return [
            'page' => $page,
            'last_page' => $lastPage,
            'total' => $total,
            'per_page' => $perPage,
            'from' => $from,
            'to' => $to,
            'pages' => $pages,
        ];
    }

    private function normalizeDueDateInput(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            throw new ValidationException('Informe a data de vencimento da proxima cobranca.');
        }

        $date = date_create_immutable($raw);
        if (!$date instanceof DateTimeImmutable) {
            throw new ValidationException('Data invalida para o vencimento da proxima cobranca.');
        }

        return $date->format('Y-m-d');
    }

    private function defaultAccessState(): array
    {
        return [
            'restricted' => false,
            'is_warning' => false,
            'is_blocked' => false,
            'overdue_days' => 0,
            'next_due_date' => '',
            'allowed_sections' => ['support', 'subscription'],
            'headline' => '',
            'message' => '',
        ];
    }
}
