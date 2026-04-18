<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Exceptions\ValidationException;
use App\Repositories\CompanyRepository;
use App\Repositories\SubscriptionPaymentRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\Billing\MercadoPagoGateway;

final class SubscriptionGatewayService
{
    public function __construct(
        private readonly MercadoPagoGateway $gateway = new MercadoPagoGateway(),
        private readonly SubscriptionRepository $subscriptions = new SubscriptionRepository(),
        private readonly SubscriptionPaymentRepository $subscriptionPayments = new SubscriptionPaymentRepository(),
        private readonly CompanyRepository $companies = new CompanyRepository()
    ) {}

    public function isConfigured(): bool
    {
        return $this->gateway->isConfigured();
    }

    public function providerName(): string
    {
        return 'Mercado Pago';
    }

    public function createPixCharge(int $companyId, int $paymentId): void
    {
        $payment = $this->subscriptionPayments->findByIdForCompany($companyId, $paymentId);
        if ($payment === null) {
            throw new ValidationException('Cobranca nao encontrada para gerar PIX no gateway.');
        }

        $subscription = $this->subscriptions->findCurrentByCompanyId($companyId);
        if ($subscription === null || (int) ($subscription['id'] ?? 0) !== (int) ($payment['subscription_id'] ?? 0)) {
            throw new ValidationException('Assinatura nao encontrada para a cobranca selecionada.');
        }

        if ((string) ($payment['status'] ?? '') === 'pago') {
            throw new ValidationException('A cobranca ja foi quitada e nao precisa de novo QR PIX.');
        }

        if (trim((string) ($payment['gateway_payment_id'] ?? '')) !== ''
            && trim((string) ($payment['pix_qr_image_base64'] ?? '')) !== '') {
            return;
        }

        $companyEmail = $this->resolveCompanyEmail($subscription, 'gerar PIX no gateway');

        $reference = sprintf(
            'SUB-%d-%02d%04d-%d',
            $companyId,
            (int) ($payment['reference_month'] ?? 0),
            (int) ($payment['reference_year'] ?? 0),
            $paymentId
        );

        $response = $this->gateway->createPixPayment([
            'transaction_amount' => round((float) ($payment['amount'] ?? 0), 2),
            'description' => $this->buildChargeDescription($subscription, $payment),
            'payment_method_id' => 'pix',
            'external_reference' => $reference,
            'notification_url' => app_url('/webhooks/mercado-pago'),
            'payer' => $this->buildPayer($subscription, $companyEmail),
        ]);

        $transactionData = $response['point_of_interaction']['transaction_data'] ?? [];
        $this->subscriptionPayments->updateRecord($paymentId, [
            'status' => 'pendente',
            'payment_method' => 'pix',
            'paid_at' => null,
            'due_date' => (string) ($payment['due_date'] ?? date('Y-m-d')),
            'transaction_reference' => $reference,
            'charge_origin' => 'pix',
            'pix_code' => $transactionData['qr_code'] ?? ($payment['pix_code'] ?? null),
            'pix_qr_payload' => $transactionData['qr_code'] ?? ($payment['pix_qr_payload'] ?? null),
            'pix_qr_image_base64' => $transactionData['qr_code_base64'] ?? null,
            'pix_ticket_url' => $transactionData['ticket_url'] ?? null,
            'payment_details_json' => $payment['payment_details_json'] ?? null,
            'gateway_payment_id' => isset($response['id']) ? (string) $response['id'] : null,
            'gateway_payment_url' => $transactionData['ticket_url'] ?? null,
            'gateway_status' => $response['status'] ?? null,
            'gateway_webhook_payload_json' => json_encode($response, JSON_UNESCAPED_SLASHES),
            'gateway_last_synced_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function createRecurringCheckout(int $companyId): void
    {
        $subscription = $this->subscriptions->findCurrentByCompanyId($companyId);
        if ($subscription === null) {
            throw new ValidationException('Assinatura nao encontrada para gerar checkout recorrente.');
        }

        $companyEmail = $this->resolveCompanyEmail($subscription, 'aderir a recorrencia do gateway');

        $gatewaySubscriptionId = trim((string) ($subscription['gateway_subscription_id'] ?? ''));
        $gatewayStatus = strtolower(trim((string) ($subscription['gateway_status'] ?? '')));
        $gatewayCheckoutUrl = trim((string) ($subscription['gateway_checkout_url'] ?? ''));
        if ($gatewaySubscriptionId !== '' && !in_array($gatewayStatus, ['cancelled', 'cancelled_by_payer', 'canceled'], true)) {
            if ($gatewayCheckoutUrl !== '') {
                return;
            }

            $response = $this->gateway->getSubscription($gatewaySubscriptionId);
            $this->subscriptions->updateGatewayProfile((int) $subscription['id'], [
                'gateway_provider' => 'mercado_pago',
                'gateway_subscription_id' => $response['id'] ?? $gatewaySubscriptionId,
                'gateway_checkout_url' => $response['init_point'] ?? null,
                'gateway_status' => $response['status'] ?? null,
                'gateway_webhook_payload_json' => json_encode($response, JSON_UNESCAPED_SLASHES),
                'gateway_last_synced_at' => date('Y-m-d H:i:s'),
            ]);

            if (trim((string) ($response['init_point'] ?? '')) !== '') {
                return;
            }
        }

        $frequencyType = ((string) ($subscription['billing_cycle'] ?? 'mensal')) === 'anual' ? 'months' : 'months';
        $frequency = ((string) ($subscription['billing_cycle'] ?? 'mensal')) === 'anual' ? 12 : 1;
        $startsAt = trim((string) ($subscription['starts_at'] ?? ''));
        $startDate = $startsAt !== '' ? date('c', strtotime($startsAt)) : date('c');

        $response = $this->gateway->createSubscription([
            'reason' => 'Assinatura ' . trim((string) ($subscription['plan_name'] ?? 'Comanda360')),
            'external_reference' => 'subscription:' . (int) ($subscription['id'] ?? 0),
            'payer_email' => $companyEmail,
            'back_url' => app_url('/admin/dashboard?section=subscription'),
            'status' => 'pending',
            'auto_recurring' => [
                'frequency' => $frequency,
                'frequency_type' => $frequencyType,
                'start_date' => $startDate,
                'transaction_amount' => round((float) ($subscription['amount'] ?? 0), 2),
                'currency_id' => 'BRL',
            ],
        ]);

        $this->subscriptions->updateGatewayProfile((int) $subscription['id'], [
            'gateway_provider' => 'mercado_pago',
            'gateway_subscription_id' => $response['id'] ?? null,
            'gateway_checkout_url' => $response['init_point'] ?? null,
            'gateway_status' => $response['status'] ?? null,
            'gateway_webhook_payload_json' => json_encode($response, JSON_UNESCAPED_SLASHES),
            'gateway_last_synced_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function syncSubscriptionByCompany(int $companyId): void
    {
        $subscription = $this->subscriptions->findCurrentByCompanyId($companyId);
        if ($subscription === null) {
            return;
        }

        if (!$this->isConfigured()) {
            return;
        }

        $gatewaySubscriptionId = trim((string) ($subscription['gateway_subscription_id'] ?? ''));
        if ($gatewaySubscriptionId !== '') {
            $response = $this->gateway->getSubscription($gatewaySubscriptionId);
            $this->subscriptions->updateGatewayProfile((int) $subscription['id'], [
                'gateway_provider' => 'mercado_pago',
                'gateway_subscription_id' => $response['id'] ?? $gatewaySubscriptionId,
                'gateway_checkout_url' => $response['init_point'] ?? ($subscription['gateway_checkout_url'] ?? null),
                'gateway_status' => $response['status'] ?? null,
                'gateway_webhook_payload_json' => json_encode($response, JSON_UNESCAPED_SLASHES),
                'gateway_last_synced_at' => date('Y-m-d H:i:s'),
            ]);
        }

        foreach ($this->subscriptionPayments->listOpenBySubscriptionId((int) $subscription['id']) as $payment) {
            $gatewayPaymentId = trim((string) ($payment['gateway_payment_id'] ?? ''));
            if ($gatewayPaymentId === '') {
                continue;
            }

            $paymentResponse = $this->gateway->getPayment($gatewayPaymentId);
            $transactionData = $paymentResponse['point_of_interaction']['transaction_data'] ?? [];
            $status = $this->mapGatewayPaymentStatus((string) ($paymentResponse['status'] ?? ''));

            $this->subscriptionPayments->updateRecord((int) ($payment['id'] ?? 0), [
                'status' => $status,
                'payment_method' => $this->mapGatewayMethod((string) ($paymentResponse['payment_method_id'] ?? 'pix')),
                'paid_at' => $status === 'pago' ? date('Y-m-d H:i:s') : null,
                'due_date' => (string) ($payment['due_date'] ?? date('Y-m-d')),
                'transaction_reference' => (string) ($paymentResponse['external_reference'] ?? $payment['transaction_reference'] ?? ''),
                'charge_origin' => (string) ($payment['charge_origin'] ?? 'pix'),
                'pix_code' => $transactionData['qr_code'] ?? ($payment['pix_code'] ?? null),
                'pix_qr_payload' => $transactionData['qr_code'] ?? ($payment['pix_qr_payload'] ?? null),
                'pix_qr_image_base64' => $transactionData['qr_code_base64'] ?? ($payment['pix_qr_image_base64'] ?? null),
                'pix_ticket_url' => $transactionData['ticket_url'] ?? ($payment['pix_ticket_url'] ?? null),
                'payment_details_json' => $payment['payment_details_json'] ?? null,
                'gateway_payment_id' => (string) ($paymentResponse['id'] ?? $gatewayPaymentId),
                'gateway_payment_url' => $transactionData['ticket_url'] ?? ($payment['gateway_payment_url'] ?? null),
                'gateway_status' => (string) ($paymentResponse['status'] ?? ''),
                'gateway_webhook_payload_json' => json_encode($paymentResponse, JSON_UNESCAPED_SLASHES),
                'gateway_last_synced_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function syncPaymentById(int $paymentId): void
    {
        if ($paymentId <= 0) {
            throw new ValidationException('Cobranca invalida para sincronizacao com o gateway.');
        }

        if (!$this->isConfigured()) {
            throw new ValidationException('Gateway nao configurado para sincronizacao desta cobranca.');
        }

        $payment = $this->subscriptionPayments->findById($paymentId);
        if ($payment === null) {
            throw new ValidationException('Cobranca nao encontrada para sincronizacao com o gateway.');
        }

        $gatewayPaymentId = trim((string) ($payment['gateway_payment_id'] ?? ''));
        if ($gatewayPaymentId === '') {
            throw new ValidationException('Esta cobranca foi registrada sem identificador do gateway. Nao e possivel consultar o pagamento automaticamente.');
        }

        $paymentResponse = $this->gateway->getPayment($gatewayPaymentId);
        $transactionData = $paymentResponse['point_of_interaction']['transaction_data'] ?? [];
        $status = $this->mapGatewayPaymentStatus((string) ($paymentResponse['status'] ?? ''));

        $this->subscriptionPayments->updateRecord($paymentId, [
            'status' => $status,
            'payment_method' => $this->mapGatewayMethod((string) ($paymentResponse['payment_method_id'] ?? ($payment['payment_method'] ?? 'pix'))),
            'paid_at' => $status === 'pago' ? ((string) ($payment['paid_at'] ?? '') !== '' ? (string) $payment['paid_at'] : date('Y-m-d H:i:s')) : null,
            'due_date' => (string) ($payment['due_date'] ?? date('Y-m-d')),
            'transaction_reference' => (string) ($paymentResponse['external_reference'] ?? $payment['transaction_reference'] ?? ''),
            'charge_origin' => (string) ($payment['charge_origin'] ?? 'pix'),
            'pix_code' => $transactionData['qr_code'] ?? ($payment['pix_code'] ?? null),
            'pix_qr_payload' => $transactionData['qr_code'] ?? ($payment['pix_qr_payload'] ?? null),
            'pix_qr_image_base64' => $transactionData['qr_code_base64'] ?? ($payment['pix_qr_image_base64'] ?? null),
            'pix_ticket_url' => $transactionData['ticket_url'] ?? ($payment['pix_ticket_url'] ?? null),
            'payment_details_json' => $payment['payment_details_json'] ?? null,
            'gateway_payment_id' => (string) ($paymentResponse['id'] ?? $gatewayPaymentId),
            'gateway_payment_url' => $transactionData['ticket_url'] ?? ($payment['gateway_payment_url'] ?? null),
            'gateway_status' => (string) ($paymentResponse['status'] ?? ''),
            'gateway_webhook_payload_json' => json_encode($paymentResponse, JSON_UNESCAPED_SLASHES),
            'gateway_last_synced_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function processWebhook(array $query, array $server): array
    {
        if (!$this->isConfigured()) {
            throw new ValidationException('Gateway nao configurado para processar webhook.');
        }

        if (!$this->gateway->validateWebhookSignature($server, $query)) {
            throw new ValidationException('Webhook do Mercado Pago rejeitado por assinatura invalida.');
        }

        $type = trim((string) ($query['type'] ?? ''));
        $dataId = trim((string) ($query['data.id'] ?? ''));
        if ($type === '' || $dataId === '') {
            throw new ValidationException('Webhook recebido sem tipo ou identificador da entidade.');
        }

        if ($type === 'payment') {
            $paymentResponse = $this->gateway->getPayment($dataId);
            $payment = $this->subscriptionPayments->findByGatewayPaymentId($dataId);
            if ($payment !== null) {
                $status = $this->mapGatewayPaymentStatus((string) ($paymentResponse['status'] ?? ''));
                $transactionData = $paymentResponse['point_of_interaction']['transaction_data'] ?? [];
                $this->subscriptionPayments->updateRecord((int) $payment['id'], [
                    'status' => $status,
                    'payment_method' => $this->mapGatewayMethod((string) ($paymentResponse['payment_method_id'] ?? '')),
                    'paid_at' => $status === 'pago' ? date('Y-m-d H:i:s') : null,
                    'due_date' => (string) ($payment['due_date'] ?? date('Y-m-d')),
                    'transaction_reference' => (string) ($paymentResponse['external_reference'] ?? $payment['transaction_reference'] ?? ''),
                    'charge_origin' => 'pix',
                    'pix_code' => $transactionData['qr_code'] ?? ($payment['pix_code'] ?? null),
                    'pix_qr_payload' => $transactionData['qr_code'] ?? ($payment['pix_qr_payload'] ?? null),
                    'pix_qr_image_base64' => $transactionData['qr_code_base64'] ?? ($payment['pix_qr_image_base64'] ?? null),
                    'pix_ticket_url' => $transactionData['ticket_url'] ?? ($payment['pix_ticket_url'] ?? null),
                    'payment_details_json' => $payment['payment_details_json'] ?? null,
                    'gateway_payment_id' => (string) ($paymentResponse['id'] ?? $dataId),
                    'gateway_payment_url' => $transactionData['ticket_url'] ?? ($payment['gateway_payment_url'] ?? null),
                    'gateway_status' => (string) ($paymentResponse['status'] ?? ''),
                    'gateway_webhook_payload_json' => json_encode($paymentResponse, JSON_UNESCAPED_SLASHES),
                    'gateway_last_synced_at' => date('Y-m-d H:i:s'),
                ]);
            }

            return ['type' => 'payment', 'data_id' => $dataId];
        }

        if ($type === 'subscription_preapproval' || $type === 'preapproval') {
            $subscriptionResponse = $this->gateway->getSubscription($dataId);
            $subscription = $this->subscriptions->findByGatewaySubscriptionId($dataId);
            if ($subscription !== null) {
                $status = trim((string) ($subscriptionResponse['status'] ?? ''));
                $billingStatus = in_array($status, ['authorized', 'active'], true) ? 'ativa' : (in_array($status, ['cancelled', 'cancelled_by_payer'], true) ? 'cancelada' : 'trial');

                $this->subscriptions->updateGatewayProfile((int) $subscription['id'], [
                    'gateway_provider' => 'mercado_pago',
                    'gateway_subscription_id' => (string) ($subscriptionResponse['id'] ?? $dataId),
                    'gateway_checkout_url' => $subscriptionResponse['init_point'] ?? ($subscription['gateway_checkout_url'] ?? null),
                    'gateway_status' => $status,
                    'gateway_webhook_payload_json' => json_encode($subscriptionResponse, JSON_UNESCAPED_SLASHES),
                    'gateway_last_synced_at' => date('Y-m-d H:i:s'),
                ]);

                $this->subscriptions->updateStatus((int) $subscription['id'], $billingStatus);
                $this->companies->updateSubscriptionSnapshot((int) ($subscription['company_id'] ?? 0), [
                    'plan_id' => $subscription['plan_id'] ?? null,
                    'subscription_status' => $billingStatus === 'ativa' ? 'ativa' : ($billingStatus === 'cancelada' ? 'cancelada' : 'trial'),
                    'trial_ends_at' => null,
                    'subscription_starts_at' => $subscription['starts_at'] ?? null,
                    'subscription_ends_at' => $subscription['ends_at'] ?? null,
                ]);

                if ($billingStatus === 'ativa') {
                    $this->subscriptions->updateBillingProfile((int) $subscription['id'], [
                        'preferred_payment_method' => 'credito',
                        'auto_charge_enabled' => 1,
                        'card_brand' => $subscription['card_brand'] ?? null,
                        'card_last_digits' => $subscription['card_last_digits'] ?? null,
                    ]);
                }
            }

            return ['type' => 'preapproval', 'data_id' => $dataId];
        }

        return ['type' => $type, 'data_id' => $dataId];
    }

    private function buildChargeDescription(array $subscription, array $payment): string
    {
        return sprintf(
            'Assinatura %s - %02d/%04d',
            trim((string) ($subscription['plan_name'] ?? 'Comanda360')),
            (int) ($payment['reference_month'] ?? 0),
            (int) ($payment['reference_year'] ?? 0)
        );
    }

    private function mapGatewayPaymentStatus(string $gatewayStatus): string
    {
        $gatewayStatus = strtolower(trim($gatewayStatus));
        return match ($gatewayStatus) {
            'approved' => 'pago',
            'cancelled', 'canceled' => 'cancelado',
            'rejected' => 'vencido',
            default => 'pendente',
        };
    }

    private function mapGatewayMethod(string $gatewayMethod): string
    {
        $gatewayMethod = strtolower(trim($gatewayMethod));
        return match ($gatewayMethod) {
            'pix' => 'pix',
            'debit_card' => 'debito',
            'credit_card' => 'credito',
            default => $gatewayMethod,
        };
    }

    private function buildPayer(array $subscription, ?string $companyEmail = null): array
    {
        $payer = [
            'email' => $companyEmail ?? $this->resolveCompanyEmail($subscription, 'enviar dados do pagador ao gateway'),
        ];

        $document = $this->normalizeGatewayDocument((string) ($subscription['company_document_number'] ?? ''));
        if ($document !== null) {
            $payer['identification'] = [
                'type' => strlen($document) > 11 ? 'CNPJ' : 'CPF',
                'number' => $document,
            ];
        }

        return $payer;
    }

    private function resolveCompanyEmail(array $subscription, string $context): string
    {
        $email = trim((string) ($subscription['company_email'] ?? ''));
        if ($email === '') {
            throw new ValidationException('A empresa precisa ter um e-mail principal cadastrado para ' . $context . '.');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException('O e-mail principal da empresa esta invalido. Atualize o cadastro da empresa no SaaS antes de tentar novamente.');
        }

        return $email;
    }

    private function normalizeGatewayDocument(string $value): ?string
    {
        $document = preg_replace('/\D+/', '', $value) ?? '';
        if ($document === '') {
            return null;
        }

        if (strlen($document) === 11 && $this->isValidCpf($document)) {
            return $document;
        }

        if (strlen($document) === 14 && $this->isValidCnpj($document)) {
            return $document;
        }

        return null;
    }

    private function isValidCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\\d)\\1{10}$/', $cpf) === 1) {
            return false;
        }

        for ($digit = 9; $digit < 11; $digit++) {
            $sum = 0;
            for ($index = 0; $index < $digit; $index++) {
                $sum += ((int) $cpf[$index]) * (($digit + 1) - $index);
            }

            $check = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$digit] !== $check) {
                return false;
            }
        }

        return true;
    }

    private function isValidCnpj(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/^(\\d)\\1{13}$/', $cnpj) === 1) {
            return false;
        }

        $firstWeights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $secondWeights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $sum = 0;
        foreach ($firstWeights as $index => $weight) {
            $sum += ((int) $cnpj[$index]) * $weight;
        }

        $remainder = $sum % 11;
        $firstDigit = $remainder < 2 ? 0 : 11 - $remainder;
        if ((int) $cnpj[12] !== $firstDigit) {
            return false;
        }

        $sum = 0;
        foreach ($secondWeights as $index => $weight) {
            $sum += ((int) $cnpj[$index]) * $weight;
        }

        $remainder = $sum % 11;
        $secondDigit = $remainder < 2 ? 0 : 11 - $remainder;

        return (int) $cnpj[13] === $secondDigit;
    }
}
