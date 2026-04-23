<?php
declare(strict_types=1);

namespace App\Services\Marketing;

use App\Exceptions\ValidationException;
use App\Repositories\PublicContactRepository;

final class LeadCaptureService
{
    private const MAX_NAME_LENGTH = 120;
    private const MAX_EMAIL_LENGTH = 160;
    private const MAX_COMPANY_LENGTH = 160;
    private const MAX_PHONE_LENGTH = 40;
    private const MAX_PLAN_LENGTH = 120;
    private const MAX_SOURCE_URL_LENGTH = 255;
    private const MAX_USER_AGENT_LENGTH = 255;
    private const MAX_UTM_LENGTH = 160;
    private const MAX_MESSAGE_LENGTH = 2000;

    public function __construct(
        private readonly PublicContactRepository $repository = new PublicContactRepository()
    ) {}

    public function store(array $input, array $server = []): void
    {
        $name = $this->requireText($input['name'] ?? '', 'Informe seu nome.', self::MAX_NAME_LENGTH);
        $email = $this->normalizeEmail($input['email'] ?? '');
        $company = $this->nullableText($input['company'] ?? null, self::MAX_COMPANY_LENGTH);
        $phone = $this->requireText($input['phone'] ?? '', 'Informe um telefone ou WhatsApp para retorno.', self::MAX_PHONE_LENGTH);
        $message = $this->requireText($input['message'] ?? '', 'Escreva sua mensagem antes de enviar.', self::MAX_MESSAGE_LENGTH);
        $planInterest = $this->nullableText($input['plan_interest'] ?? null, self::MAX_PLAN_LENGTH);
        $billingCycle = $this->normalizeBillingCycle($input['billing_cycle_interest'] ?? null);

        $sourceUrl = $this->nullableText($input['source_url'] ?? null, self::MAX_SOURCE_URL_LENGTH);
        $submittedIp = $this->nullableText($server['REMOTE_ADDR'] ?? null, 45);
        $userAgent = $this->nullableText($server['HTTP_USER_AGENT'] ?? null, self::MAX_USER_AGENT_LENGTH);

        $this->repository->create([
            'contact_name' => $name,
            'contact_email' => $email,
            'company_name' => $company,
            'phone' => $phone,
            'plan_interest' => $planInterest,
            'billing_cycle_interest' => $billingCycle,
            'message' => $message,
            'source_page' => $sourceUrl,
            'utm_source' => $this->nullableText($input['utm_source'] ?? null, self::MAX_UTM_LENGTH),
            'utm_medium' => $this->nullableText($input['utm_medium'] ?? null, self::MAX_UTM_LENGTH),
            'utm_campaign' => $this->nullableText($input['utm_campaign'] ?? null, self::MAX_UTM_LENGTH),
            'utm_term' => $this->nullableText($input['utm_term'] ?? null, self::MAX_UTM_LENGTH),
            'utm_content' => $this->nullableText($input['utm_content'] ?? null, self::MAX_UTM_LENGTH),
            'submitted_ip' => $submittedIp,
            'user_agent' => $userAgent,
        ]);
    }

    private function requireText(mixed $value, string $message, int $maxLength): string
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            throw new ValidationException($message);
        }

        if (strlen($normalized) > $maxLength) {
            return substr($normalized, 0, $maxLength);
        }

        return $normalized;
    }

    private function normalizeEmail(mixed $value): string
    {
        $email = strtolower(trim((string) ($value ?? '')));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException('Informe um e-mail valido.');
        }

        if (strlen($email) > self::MAX_EMAIL_LENGTH) {
            throw new ValidationException('Informe um e-mail valido.');
        }

        return $email;
    }

    private function normalizeBillingCycle(mixed $value): ?string
    {
        $normalized = strtolower(trim((string) ($value ?? '')));
        if ($normalized === '') {
            return null;
        }

        if (!in_array($normalized, ['mensal', 'anual'], true)) {
            throw new ValidationException('Ciclo de cobranca invalido.');
        }

        return $normalized;
    }

    private function nullableText(mixed $value, int $maxLength): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            return null;
        }

        if (strlen($normalized) > $maxLength) {
            return substr($normalized, 0, $maxLength);
        }

        return $normalized;
    }
}
