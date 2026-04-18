<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Exceptions\ValidationException;
use App\Repositories\CompanyRepository;
use App\Repositories\SubscriptionPaymentRepository;
use App\Repositories\SubscriptionRepository;
use DateTimeImmutable;
use Throwable;

final class SubscriptionPortalService
{
    private const CARD_METHODS = ['credito', 'debito'];

    public function __construct(
        private readonly SubscriptionRepository $subscriptions = new SubscriptionRepository(),
        private readonly SubscriptionPaymentRepository $subscriptionPayments = new SubscriptionPaymentRepository(),
        private readonly CompanyRepository $companies = new CompanyRepository(),
        private readonly SubscriptionGatewayService $gatewayService = new SubscriptionGatewayService()
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
            'billing_access' => $this->resolveAccessState($companyId),
            'gateway' => [
                'configured' => $this->gatewayService->isConfigured(),
                'provider' => $this->gatewayService->providerName(),
            ],
        ];
    }

    public function payChargeWithCard(int $companyId, array $input): void
    {
        $paymentId = (int) ($input['subscription_payment_id'] ?? 0);
        $paymentMethod = $this->normalizeCardMethod($input['payment_method'] ?? '');
        $cardBrand = $this->normalizeCardBrand($input['card_brand'] ?? '');
        $cardLastDigits = $this->normalizeCardLastDigits($input['card_last_digits'] ?? '');

        $payment = $this->loadCompanyPayment($companyId, $paymentId);
        $this->assertPayableCharge($payment);

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $this->subscriptions->updateBillingProfile((int) $payment['subscription_id'], [
                'preferred_payment_method' => $paymentMethod,
                'auto_charge_enabled' => 1,
                'card_brand' => $cardBrand,
                'card_last_digits' => $cardLastDigits,
            ]);

            $details = [
                'source' => 'company_dashboard',
                'mode' => 'manual_card',
                'card_brand' => $cardBrand,
                'card_last_digits' => $cardLastDigits,
                'captured_at' => date('c'),
            ];

            $this->subscriptionPayments->updateRecord($paymentId, [
                'status' => 'pago',
                'payment_method' => $paymentMethod,
                'paid_at' => date('Y-m-d H:i:s'),
                'due_date' => (string) $payment['due_date'],
                'transaction_reference' => $this->buildChargeReference('MANUAL', $paymentId, $paymentMethod),
                'charge_origin' => $payment['charge_origin'] ?? 'manual',
                'pix_code' => null,
                'pix_qr_payload' => null,
                'pix_qr_image_base64' => $payment['pix_qr_image_base64'] ?? null,
                'pix_ticket_url' => $payment['pix_ticket_url'] ?? null,
                'payment_details_json' => json_encode($details, JSON_UNESCAPED_SLASHES),
                'gateway_payment_id' => $payment['gateway_payment_id'] ?? null,
                'gateway_payment_url' => $payment['gateway_payment_url'] ?? null,
                'gateway_status' => $payment['gateway_status'] ?? null,
                'gateway_webhook_payload_json' => $payment['gateway_webhook_payload_json'] ?? null,
                'gateway_last_synced_at' => $payment['gateway_last_synced_at'] ?? null,
            ]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        $this->synchronizeByCompany($companyId);
    }

    public function confirmPixPayment(int $companyId, array $input): void
    {
        $paymentId = (int) ($input['subscription_payment_id'] ?? 0);
        $transactionReference = $this->normalizeNullableText($input['transaction_reference'] ?? null);

        $payment = $this->loadCompanyPayment($companyId, $paymentId);
        $this->assertPayableCharge($payment);

        $pixPayload = $this->ensurePixPayload($payment);
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $this->subscriptions->updateBillingProfile((int) $payment['subscription_id'], [
                'preferred_payment_method' => 'pix',
                'auto_charge_enabled' => 0,
                'card_brand' => null,
                'card_last_digits' => null,
            ]);

            $details = [
                'source' => 'company_dashboard',
                'mode' => 'manual_pix',
                'confirmed_at' => date('c'),
            ];

            $this->subscriptionPayments->updateRecord($paymentId, [
                'status' => 'pago',
                'payment_method' => 'pix',
                'paid_at' => date('Y-m-d H:i:s'),
                'due_date' => (string) $payment['due_date'],
                'transaction_reference' => $transactionReference ?? $this->buildChargeReference('PIX', $paymentId, 'pix'),
                'charge_origin' => 'pix',
                'pix_code' => $pixPayload['pix_code'],
                'pix_qr_payload' => $pixPayload['pix_qr_payload'],
                'pix_qr_image_base64' => $payment['pix_qr_image_base64'] ?? null,
                'pix_ticket_url' => $payment['pix_ticket_url'] ?? null,
                'payment_details_json' => json_encode($details, JSON_UNESCAPED_SLASHES),
                'gateway_payment_id' => $payment['gateway_payment_id'] ?? null,
                'gateway_payment_url' => $payment['gateway_payment_url'] ?? null,
                'gateway_status' => $payment['gateway_status'] ?? null,
                'gateway_webhook_payload_json' => $payment['gateway_webhook_payload_json'] ?? null,
                'gateway_last_synced_at' => $payment['gateway_last_synced_at'] ?? null,
            ]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        $this->synchronizeByCompany($companyId);
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

    public function generateRecurringGatewayCheckout(int $companyId): void
    {
        $this->gatewayService->createRecurringCheckout($companyId);
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

        if ($status !== 'cancelada') {
            foreach ($this->expectedCharges($subscription) as $charge) {
                $existing = $this->subscriptionPayments->findByReference(
                    $subscriptionId,
                    (int) $charge['reference_month'],
                    (int) $charge['reference_year']
                );
                if ($existing !== null) {
                    continue;
                }

                $pixPayload = $usesAutoCard ? ['pix_code' => null, 'pix_qr_payload' => null] : $this->buildPixPayload(
                    $companyId,
                    $subscriptionId,
                    (int) $charge['reference_year'],
                    (int) $charge['reference_month'],
                    (float) $charge['amount']
                );

                $this->subscriptionPayments->create([
                    'subscription_id' => $subscriptionId,
                    'company_id' => $companyId,
                    'reference_month' => $charge['reference_month'],
                    'reference_year' => $charge['reference_year'],
                    'amount' => $charge['amount'],
                    'status' => 'pendente',
                    'payment_method' => $usesAutoCard ? $preferredMethod : 'pix',
                    'paid_at' => null,
                    'due_date' => $charge['due_date'],
                    'transaction_reference' => null,
                    'charge_origin' => $usesAutoCard ? 'auto' : 'pix',
                    'pix_code' => $pixPayload['pix_code'],
                    'pix_qr_payload' => $pixPayload['pix_qr_payload'],
                    'payment_details_json' => null,
                ]);
            }
        }

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
            if ($usesAutoCard && $dueDate <= $today) {
                $details = [
                    'source' => 'billing_sync',
                    'mode' => 'auto_card',
                    'card_brand' => $this->normalizeNullableText($subscription['card_brand'] ?? null),
                    'card_last_digits' => $this->normalizeNullableText($subscription['card_last_digits'] ?? null),
                    'captured_at' => date('c'),
                ];

                $this->subscriptionPayments->updateRecord($paymentId, [
                    'status' => 'pago',
                    'payment_method' => $preferredMethod,
                    'paid_at' => date('Y-m-d H:i:s'),
                    'due_date' => (string) $payment['due_date'],
                    'transaction_reference' => $this->buildChargeReference('AUTO', $paymentId, $preferredMethod),
                    'charge_origin' => 'auto',
                    'pix_code' => null,
                    'pix_qr_payload' => null,
                    'pix_qr_image_base64' => $payment['pix_qr_image_base64'] ?? null,
                    'pix_ticket_url' => $payment['pix_ticket_url'] ?? null,
                    'payment_details_json' => json_encode($details, JSON_UNESCAPED_SLASHES),
                    'gateway_payment_id' => $payment['gateway_payment_id'] ?? null,
                    'gateway_payment_url' => $payment['gateway_payment_url'] ?? null,
                    'gateway_status' => $payment['gateway_status'] ?? null,
                    'gateway_webhook_payload_json' => $payment['gateway_webhook_payload_json'] ?? null,
                    'gateway_last_synced_at' => $payment['gateway_last_synced_at'] ?? null,
                ]);
                continue;
            }

            if (!$usesAutoCard) {
                $pixPayload = $this->ensurePixPayload($payment);
                $targetStatus = $paymentStatus;
                if ($paymentStatus === 'pendente' && $dueDate < $today) {
                    $targetStatus = 'vencido';
                }

                $this->subscriptionPayments->updateRecord($paymentId, [
                    'status' => $targetStatus,
                    'payment_method' => 'pix',
                    'paid_at' => null,
                    'due_date' => (string) $payment['due_date'],
                    'transaction_reference' => null,
                    'charge_origin' => 'pix',
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
        }

        $this->ensureNextOpenCharge($subscription, $usesAutoCard);

        $payments = $this->subscriptionPayments->listBySubscriptionId($subscriptionId, 240);
        $hasOverdue = false;
        $hasPaid = false;
        foreach ($payments as $payment) {
            $paymentStatus = trim((string) ($payment['status'] ?? ''));
            if ($paymentStatus === 'vencido') {
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

        if ($billingCycle === 'anual') {
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

        $this->subscriptionPayments->create([
            'subscription_id' => $subscriptionId,
            'company_id' => $companyId,
            'reference_month' => $targetReferenceMonth,
            'reference_year' => $targetReferenceYear,
            'amount' => $amount,
            'status' => 'pendente',
            'payment_method' => $usesAutoCard ? trim((string) ($subscription['preferred_payment_method'] ?? '')) : 'pix',
            'paid_at' => null,
            'due_date' => $targetDueDate,
            'transaction_reference' => null,
            'charge_origin' => $usesAutoCard ? 'auto' : 'pix',
            'pix_code' => $pixPayload['pix_code'],
            'pix_qr_payload' => $pixPayload['pix_qr_payload'],
            'payment_details_json' => null,
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
            'preferred_payment_method' => $subscription['preferred_payment_method'] ?? null,
            'auto_charge_enabled' => !empty($subscription['auto_charge_enabled']),
        ];
    }

    private function extractFeatureSummary(array $subscription): array
    {
        $raw = trim((string) ($subscription['plan_features_json'] ?? ''));
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $featureLabels = [
            'cardapio_digital' => 'Cardapio digital',
            'qrcode_mesa' => 'QR Code de mesa',
            'caixa' => 'Caixa',
            'delivery' => 'Delivery',
            'relatorios' => 'Relatorios',
        ];

        $features = [];
        foreach ($featureLabels as $key => $label) {
            if (!empty($decoded[$key])) {
                $features[] = $label;
            }
        }

        $limitLabels = [
            'usuarios_ilimitados' => 'Usuarios ilimitados',
            'produtos_ilimitados' => 'Produtos ilimitados',
            'mesas_ilimitadas' => 'Mesas ilimitadas',
        ];

        foreach ($limitLabels as $key => $label) {
            if (!empty($decoded[$key])) {
                $features[] = $label;
            }
        }

        return $features;
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

    private function normalizeCardMethod(mixed $value): string
    {
        $method = strtolower(trim((string) $value));
        if (!in_array($method, self::CARD_METHODS, true)) {
            throw new ValidationException('Selecione credito ou debito para registrar o pagamento com cartao.');
        }

        return $method;
    }

    private function normalizeCardBrand(mixed $value): string
    {
        $brand = trim((string) $value);
        if ($brand === '') {
            throw new ValidationException('Informe a bandeira do cartao utilizada na cobranca.');
        }

        if (strlen($brand) > 30) {
            throw new ValidationException('A bandeira do cartao deve ter no maximo 30 caracteres.');
        }

        return $brand;
    }

    private function normalizeCardLastDigits(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        if (strlen($digits) !== 4) {
            throw new ValidationException('Informe apenas os 4 ultimos digitos do cartao.');
        }

        return $digits;
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text !== '' ? $text : null;
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
