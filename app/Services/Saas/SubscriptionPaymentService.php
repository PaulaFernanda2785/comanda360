<?php
declare(strict_types=1);

namespace App\Services\Saas;

use App\Exceptions\ValidationException;
use App\Repositories\SubscriptionPaymentRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\Admin\SubscriptionGatewayService;
use App\Services\Admin\SubscriptionPortalService;

final class SubscriptionPaymentService
{
    private const PAYMENT_LIST_PER_PAGE = 10;

    public function __construct(
        private readonly SubscriptionPaymentRepository $subscriptionPayments = new SubscriptionPaymentRepository(),
        private readonly SubscriptionRepository $subscriptions = new SubscriptionRepository(),
        private readonly SubscriptionPortalService $subscriptionPortal = new SubscriptionPortalService(),
        private readonly SubscriptionGatewayService $subscriptionGateway = new SubscriptionGatewayService()
    ) {}

    public function list(array $filters = []): array
    {
        return $this->subscriptionPayments->allForSaas($this->normalizeFilters($filters));
    }

    public function panel(array $filters = []): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        $page = $this->subscriptionPayments->listForSaasPaginated(
            [
                'search' => $normalizedFilters['search'],
                'status' => $normalizedFilters['status'],
            ],
            $normalizedFilters['page'],
            $normalizedFilters['per_page']
        );

        $items = is_array($page['items'] ?? null) ? $page['items'] : [];
        if ($this->normalizeExpiredGatewayPixCharges($items)) {
            $page = $this->subscriptionPayments->listForSaasPaginated(
                [
                    'search' => $normalizedFilters['search'],
                    'status' => $normalizedFilters['status'],
                ],
                $normalizedFilters['page'],
                $normalizedFilters['per_page']
            );
            $items = is_array($page['items'] ?? null) ? $page['items'] : [];
        }

        $total = (int) ($page['total'] ?? 0);
        $currentPage = (int) ($page['page'] ?? 1);
        $perPage = (int) ($page['per_page'] ?? self::PAYMENT_LIST_PER_PAGE);
        $lastPage = (int) ($page['last_page'] ?? 1);

        return [
            'payments' => $items,
            'filters' => $normalizedFilters,
            'summary' => $this->subscriptionPayments->summary(),
            'pagination' => [
                'total' => $total,
                'page' => $currentPage,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'from' => $total > 0 ? (($currentPage - 1) * $perPage) + 1 : 0,
                'to' => $total > 0 ? min($total, $currentPage * $perPage) : 0,
                'pages' => $this->buildPaginationPages($currentPage, $lastPage),
            ],
        ];
    }

    public function subscriptionsForBilling(): array
    {
        return $this->subscriptions->activeForBilling();
    }

    public function summary(): array
    {
        return $this->subscriptionPayments->summary();
    }

    public function filters(array $input): array
    {
        return $this->normalizeFilters($input);
    }

    public function createCharge(array $input): int
    {
        $subscriptionId = (int) ($input['subscription_id'] ?? 0);
        $referenceMonth = (int) ($input['reference_month'] ?? 0);
        $referenceYear = (int) ($input['reference_year'] ?? 0);
        $amount = $this->parseMoney($input['amount'] ?? 0);
        $dueDate = trim((string) ($input['due_date'] ?? ''));
        $transactionReference = $this->normalizeNullableText($input['transaction_reference'] ?? null);

        if ($subscriptionId <= 0) {
            throw new ValidationException('Selecione uma assinatura valida.');
        }

        $subscription = $this->subscriptions->findById($subscriptionId);
        if ($subscription === null) {
            throw new ValidationException('Assinatura nao encontrada.');
        }

        if ($referenceMonth < 1 || $referenceMonth > 12) {
            throw new ValidationException('Mes de referencia invalido.');
        }

        if ($referenceYear < 2020 || $referenceYear > 2100) {
            throw new ValidationException('Ano de referencia invalido.');
        }

        if ($amount < 0) {
            throw new ValidationException('Valor da cobranca nao pode ser negativo.');
        }

        if (!$this->isValidDate($dueDate)) {
            throw new ValidationException('Data de vencimento invalida.');
        }

        $existing = $this->subscriptionPayments->findByReference($subscriptionId, $referenceMonth, $referenceYear);
        if ($existing !== null) {
            throw new ValidationException('Ja existe cobranca para este mes/ano na assinatura selecionada.');
        }

        return $this->subscriptionPayments->create([
            'subscription_id' => $subscriptionId,
            'company_id' => (int) $subscription['company_id'],
            'reference_month' => $referenceMonth,
            'reference_year' => $referenceYear,
            'amount' => $amount,
            'status' => 'pendente',
            'payment_method' => null,
            'paid_at' => null,
            'due_date' => $dueDate,
            'transaction_reference' => $transactionReference,
        ]);
    }

    public function updateStatus(array $input, array $actor = []): void
    {
        $paymentId = (int) ($input['subscription_payment_id'] ?? 0);
        $status = strtolower(trim((string) ($input['status'] ?? '')));
        $paymentMethod = $this->normalizeNullableText($input['payment_method'] ?? null);
        $transactionReference = $this->normalizeNullableText($input['transaction_reference'] ?? null);

        if ($paymentId <= 0) {
            throw new ValidationException('Cobranca invalida para edicao de status.');
        }

        if (!in_array($status, ['pendente', 'pago', 'vencido', 'cancelado'], true)) {
            throw new ValidationException('Selecione um status valido para a cobranca.');
        }

        $payment = $this->subscriptionPayments->findById($paymentId);
        if ($payment === null) {
            throw new ValidationException('Cobranca nao encontrada.');
        }

        $details = $this->mergePaymentDetails($payment, [
            'last_status_edit' => [
                'source' => 'saas_manual',
                'previous_status' => trim((string) ($payment['status'] ?? '')),
                'new_status' => $status,
                'edited_at' => date('c'),
            ],
        ]);

        if (!empty($actor) && (int) ($actor['is_saas_user'] ?? 0) === 1) {
            $details['last_status_edit']['actor'] = [
                'user_id' => (int) ($actor['id'] ?? 0),
                'name' => trim((string) ($actor['name'] ?? '')),
                'email' => trim((string) ($actor['email'] ?? '')),
                'role_name' => trim((string) ($actor['role_name'] ?? 'Administrador SaaS')),
            ];
        }

        if ($status === 'pago') {
            $details['source'] = 'saas_manual';
            $details['mode'] = 'manual_status_edit_paid';
            $details['marked_paid_at'] = date('c');

            if (!empty($actor) && (int) ($actor['is_saas_user'] ?? 0) === 1) {
                $details['saas_admin_signature'] = [
                    'user_id' => (int) ($actor['id'] ?? 0),
                    'name' => trim((string) ($actor['name'] ?? '')),
                    'email' => trim((string) ($actor['email'] ?? '')),
                    'role_name' => trim((string) ($actor['role_name'] ?? 'Administrador SaaS')),
                    'signed_at' => date('c'),
                    'type' => 'manual_status_edit_paid',
                ];
            }
        }

        $this->subscriptionPayments->updateRecord($paymentId, [
            'status' => $status,
            'payment_method' => $status === 'pago'
                ? ($paymentMethod ?? $this->normalizeNullableText($payment['payment_method'] ?? null) ?? 'pix')
                : ($paymentMethod ?? $this->normalizeNullableText($payment['payment_method'] ?? null)),
            'paid_at' => $status === 'pago'
                ? (((string) ($payment['paid_at'] ?? '') !== '') ? (string) $payment['paid_at'] : date('Y-m-d H:i:s'))
                : null,
            'due_date' => (string) ($payment['due_date'] ?? date('Y-m-d')),
            'transaction_reference' => $transactionReference ?? $this->normalizeNullableText($payment['transaction_reference'] ?? null),
            'charge_origin' => $payment['charge_origin'] ?? 'manual',
            'pix_code' => $payment['pix_code'] ?? null,
            'pix_qr_payload' => $payment['pix_qr_payload'] ?? null,
            'pix_qr_image_base64' => $payment['pix_qr_image_base64'] ?? null,
            'pix_ticket_url' => $payment['pix_ticket_url'] ?? null,
            'payment_details_json' => json_encode($details, JSON_UNESCAPED_SLASHES),
            'gateway_payment_id' => $payment['gateway_payment_id'] ?? null,
            'gateway_payment_url' => $payment['gateway_payment_url'] ?? null,
            'gateway_status' => $payment['gateway_status'] ?? null,
            'gateway_webhook_payload_json' => $payment['gateway_webhook_payload_json'] ?? null,
            'gateway_last_synced_at' => $payment['gateway_last_synced_at'] ?? null,
        ]);

        $this->synchronizePaymentCompany($payment);
    }

    public function markPaid(array $input, array $actor = []): void
    {
        $paymentId = (int) ($input['subscription_payment_id'] ?? 0);
        $paymentMethod = $this->normalizeNullableText($input['payment_method'] ?? null);
        $transactionReference = $this->normalizeNullableText($input['transaction_reference'] ?? null);

        if ($paymentId <= 0) {
            throw new ValidationException('Cobranca invalida para baixa de pagamento.');
        }

        $payment = $this->subscriptionPayments->findById($paymentId);
        if ($payment === null) {
            throw new ValidationException('Cobranca nao encontrada.');
        }

        if ((string) ($payment['status'] ?? '') === 'cancelado') {
            throw new ValidationException('Nao e permitido dar baixa em cobranca cancelada.');
        }

        if ((string) ($payment['status'] ?? '') === 'pago') {
            throw new ValidationException('Esta cobranca ja esta marcada como paga.');
        }

        $details = $this->mergePaymentDetails($payment, [
            'source' => 'saas_manual',
            'mode' => 'manual_mark_paid',
            'marked_paid_at' => date('c'),
        ]);

        if (!empty($actor) && (int) ($actor['is_saas_user'] ?? 0) === 1) {
            $details['saas_admin_signature'] = [
                'user_id' => (int) ($actor['id'] ?? 0),
                'name' => trim((string) ($actor['name'] ?? '')),
                'email' => trim((string) ($actor['email'] ?? '')),
                'role_name' => trim((string) ($actor['role_name'] ?? 'Administrador SaaS')),
                'signed_at' => date('c'),
                'type' => 'manual_mark_paid',
            ];
        }

        $this->subscriptionPayments->updateRecord($paymentId, [
            'status' => 'pago',
            'payment_method' => $paymentMethod,
            'paid_at' => date('Y-m-d H:i:s'),
            'due_date' => (string) ($payment['due_date'] ?? ''),
            'transaction_reference' => $transactionReference,
            'charge_origin' => $payment['charge_origin'] ?? 'manual',
            'pix_code' => $payment['pix_code'] ?? null,
            'pix_qr_payload' => $payment['pix_qr_payload'] ?? null,
            'pix_qr_image_base64' => $payment['pix_qr_image_base64'] ?? null,
            'pix_ticket_url' => $payment['pix_ticket_url'] ?? null,
            'payment_details_json' => json_encode($details, JSON_UNESCAPED_SLASHES),
            'gateway_payment_id' => $payment['gateway_payment_id'] ?? null,
            'gateway_payment_url' => $payment['gateway_payment_url'] ?? null,
            'gateway_status' => $payment['gateway_status'] ?? null,
            'gateway_webhook_payload_json' => $payment['gateway_webhook_payload_json'] ?? null,
            'gateway_last_synced_at' => $payment['gateway_last_synced_at'] ?? null,
        ]);

        $this->synchronizePaymentCompany($payment);
    }

    public function markOverdue(array $input): void
    {
        $paymentId = (int) ($input['subscription_payment_id'] ?? 0);
        if ($paymentId <= 0) {
            throw new ValidationException('Cobranca invalida para marcar vencida.');
        }

        $payment = $this->subscriptionPayments->findById($paymentId);
        if ($payment === null) {
            throw new ValidationException('Cobranca nao encontrada.');
        }

        if ((string) ($payment['status'] ?? '') === 'pago') {
            throw new ValidationException('Cobranca paga nao pode ser marcada como vencida.');
        }

        $this->subscriptionPayments->updateStatus(
            $paymentId,
            'vencido',
            $this->normalizeNullableText($payment['payment_method'] ?? null),
            $this->normalizeNullableText($payment['transaction_reference'] ?? null),
            null
        );

        $this->synchronizePaymentCompany($payment);
    }

    public function cancel(array $input): void
    {
        $paymentId = (int) ($input['subscription_payment_id'] ?? 0);
        if ($paymentId <= 0) {
            throw new ValidationException('Cobranca invalida para cancelamento.');
        }

        $payment = $this->subscriptionPayments->findById($paymentId);
        if ($payment === null) {
            throw new ValidationException('Cobranca nao encontrada.');
        }

        if ((string) ($payment['status'] ?? '') === 'pago') {
            throw new ValidationException('Cobranca paga nao pode ser cancelada.');
        }

        $this->subscriptionPayments->updateStatus(
            $paymentId,
            'cancelado',
            $this->normalizeNullableText($payment['payment_method'] ?? null),
            $this->normalizeNullableText($payment['transaction_reference'] ?? null),
            null
        );

        $this->synchronizePaymentCompany($payment);
    }

    public function syncGateway(array $input): void
    {
        $paymentId = (int) ($input['subscription_payment_id'] ?? 0);
        if ($paymentId <= 0) {
            throw new ValidationException('Cobranca invalida para sincronizacao com o gateway.');
        }

        $payment = $this->subscriptionPayments->findById($paymentId);
        if ($payment === null) {
            throw new ValidationException('Cobranca nao encontrada para sincronizacao.');
        }

        $subscription = $this->subscriptions->findById((int) ($payment['subscription_id'] ?? 0));
        if ($subscription === null) {
            throw new ValidationException('Assinatura nao encontrada para sincronizacao.');
        }

        $hasGatewayBinding = trim((string) ($payment['gateway_payment_id'] ?? '')) !== ''
            || trim((string) ($subscription['gateway_subscription_id'] ?? '')) !== ''
            || trim((string) ($subscription['gateway_checkout_url'] ?? '')) !== '';

        if (!$hasGatewayBinding) {
            throw new ValidationException('Esta cobranca foi baixada sem vinculo real com o gateway. Sem gateway_payment_id, a consulta automatica nao e possivel.');
        }

        $companyId = (int) ($payment['company_id'] ?? 0);
        if ($companyId <= 0) {
            throw new ValidationException('Empresa invalida para sincronizacao desta cobranca.');
        }

        if (trim((string) ($payment['gateway_payment_id'] ?? '')) !== '') {
            $this->subscriptionGateway->syncPaymentById($paymentId);
        }

        $this->subscriptionPortal->refreshGatewayStatus($companyId);
    }

    public function generateGatewayPix(array $input): void
    {
        $paymentId = (int) ($input['subscription_payment_id'] ?? 0);
        if ($paymentId <= 0) {
            throw new ValidationException('Cobranca invalida para gerar PIX no gateway.');
        }

        $payment = $this->subscriptionPayments->findById($paymentId);
        if ($payment === null) {
            throw new ValidationException('Cobranca nao encontrada para gerar PIX no gateway.');
        }

        $status = trim((string) ($payment['status'] ?? ''));
        if (in_array($status, ['pago', 'cancelado'], true)) {
            throw new ValidationException('Nao e possivel gerar PIX real para uma cobranca paga ou cancelada.');
        }

        $companyId = (int) ($payment['company_id'] ?? 0);
        if ($companyId <= 0) {
            throw new ValidationException('Empresa invalida para gerar PIX no gateway.');
        }

        $this->subscriptionGateway->createPixCharge($companyId, $paymentId);
        $this->subscriptionPortal->synchronizeCompanyBilling($companyId);
    }

    private function parseMoney(mixed $value): float
    {
        if (is_float($value) || is_int($value)) {
            return round((float) $value, 2);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }

        $normalized = str_replace(',', '.', $raw);
        if (!is_numeric($normalized)) {
            throw new ValidationException('Valor monetario invalido informado.');
        }

        return round((float) $normalized, 2);
    }

    private function isValidDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return false;
        }

        return date('Y-m-d', $timestamp) === $value;
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text !== '' ? $text : null;
    }

    private function normalizeFilters(array $input): array
    {
        $page = (int) ($input['payment_page'] ?? 1);
        if ($page <= 0) {
            $page = 1;
        }

        $search = trim((string) ($input['search'] ?? ''));
        if (strlen($search) > 80) {
            $search = substr($search, 0, 80);
        }

        $status = strtolower(trim((string) ($input['status'] ?? '')));
        if ($status !== '' && !in_array($status, ['pendente', 'pago', 'vencido', 'cancelado'], true)) {
            $status = '';
        }

        return [
            'search' => $search,
            'status' => $status,
            'page' => $page,
            'per_page' => self::PAYMENT_LIST_PER_PAGE,
        ];
    }

    private function buildPaginationPages(int $currentPage, int $lastPage): array
    {
        $lastPage = max(1, $lastPage);
        $currentPage = max(1, min($currentPage, $lastPage));

        $pages = [1, $lastPage, $currentPage];
        for ($offset = -2; $offset <= 2; $offset++) {
            $pages[] = $currentPage + $offset;
        }

        $normalized = [];
        foreach ($pages as $page) {
            $pageNumber = (int) $page;
            if ($pageNumber >= 1 && $pageNumber <= $lastPage) {
                $normalized[$pageNumber] = true;
            }
        }

        $result = array_keys($normalized);
        sort($result);

        return $result;
    }

    private function mergePaymentDetails(array $payment, array $defaults): array
    {
        $details = [];
        $raw = trim((string) ($payment['payment_details_json'] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $details = $decoded;
            }
        }

        foreach ($defaults as $key => $value) {
            $details[$key] = $value;
        }

        return $details;
    }

    private function normalizeExpiredGatewayPixCharges(array $payments): bool
    {
        $companyIds = [];
        foreach ($payments as $payment) {
            if (!is_array($payment) || !$this->isUnpaidGatewayPixCanceled($payment)) {
                continue;
            }

            $companyId = (int) ($payment['company_id'] ?? 0);
            if ($companyId > 0) {
                $companyIds[$companyId] = true;
            }
        }

        foreach (array_keys($companyIds) as $companyId) {
            $this->subscriptionPortal->synchronizeCompanyBilling((int) $companyId);
        }

        return $companyIds !== [];
    }

    private function isUnpaidGatewayPixCanceled(array $payment): bool
    {
        if (strtolower(trim((string) ($payment['status'] ?? ''))) !== 'cancelado') {
            return false;
        }

        if (trim((string) ($payment['gateway_payment_id'] ?? '')) === '') {
            return false;
        }

        if (trim((string) ($payment['paid_at'] ?? '')) !== '') {
            return false;
        }

        $method = strtolower(trim((string) ($payment['payment_method'] ?? '')));
        $origin = strtolower(trim((string) ($payment['charge_origin'] ?? '')));
        return $method === 'pix' || $origin === 'pix';
    }

    private function synchronizePaymentCompany(array $payment): void
    {
        $companyId = (int) ($payment['company_id'] ?? 0);
        if ($companyId <= 0) {
            return;
        }

        $this->subscriptionPortal->synchronizeCompanyBilling($companyId);
    }
}
