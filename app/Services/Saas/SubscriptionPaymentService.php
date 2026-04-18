<?php
declare(strict_types=1);

namespace App\Services\Saas;

use App\Exceptions\ValidationException;
use App\Repositories\SubscriptionPaymentRepository;
use App\Repositories\SubscriptionRepository;

final class SubscriptionPaymentService
{
    public function __construct(
        private readonly SubscriptionPaymentRepository $subscriptionPayments = new SubscriptionPaymentRepository(),
        private readonly SubscriptionRepository $subscriptions = new SubscriptionRepository()
    ) {}

    public function list(array $filters = []): array
    {
        return $this->subscriptionPayments->allForSaas($this->normalizeFilters($filters));
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
        return [
            'search' => trim((string) ($input['search'] ?? '')),
            'status' => trim((string) ($input['status'] ?? '')),
        ];
    }
}
