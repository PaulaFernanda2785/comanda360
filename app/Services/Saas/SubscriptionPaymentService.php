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

    public function markPaid(array $input): void
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

        $this->subscriptionPayments->updateStatus(
            $paymentId,
            'pago',
            $paymentMethod,
            $transactionReference,
            date('Y-m-d H:i:s')
        );
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
}
