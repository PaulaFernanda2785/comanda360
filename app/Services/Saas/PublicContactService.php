<?php
declare(strict_types=1);

namespace App\Services\Saas;

use App\Exceptions\ValidationException;
use App\Repositories\PublicContactRepository;

final class PublicContactService
{
    private const ALLOWED_STATUS = [
        'new',
        'contacted',
        'qualified',
        'converted',
        'archived',
    ];

    private const ALLOWED_RESPONSE_CHANNELS = [
        'email',
        'phone',
        'whatsapp',
    ];

    private const ALLOWED_BILLING_CYCLES = [
        'mensal',
        'anual',
    ];

    private const LIST_PER_PAGE = 10;
    private const MAX_NAME_LENGTH = 120;
    private const MAX_EMAIL_LENGTH = 160;
    private const MAX_COMPANY_LENGTH = 160;
    private const MAX_PHONE_LENGTH = 40;
    private const MAX_PLAN_LENGTH = 120;
    private const MAX_MESSAGE_LENGTH = 2000;
    private const MAX_RESPONSE_NOTES_LENGTH = 2000;

    public function __construct(
        private readonly PublicContactRepository $repository = new PublicContactRepository()
    ) {}

    public function panel(array $filters): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        $page = $this->repository->listPaginated(
            [
                'search' => $normalizedFilters['search'],
                'status' => $normalizedFilters['status'],
                'response_channel' => $normalizedFilters['response_channel'],
            ],
            $normalizedFilters['page'],
            $normalizedFilters['per_page']
        );

        $items = is_array($page['items'] ?? null) ? $page['items'] : [];
        $total = (int) ($page['total'] ?? 0);
        $currentPage = (int) ($page['page'] ?? 1);
        $perPage = (int) ($page['per_page'] ?? $normalizedFilters['per_page']);
        $lastPage = (int) ($page['last_page'] ?? 1);
        $from = $total > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
        $to = $total > 0 ? min($total, $currentPage * $perPage) : 0;

        return [
            'items' => $items,
            'filters' => $normalizedFilters,
            'pagination' => [
                'total' => $total,
                'page' => $currentPage,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
                'pages' => $this->buildPaginationPages($currentPage, $lastPage),
            ],
            'summary' => $this->repository->metrics([
                'search' => $normalizedFilters['search'],
                'status' => $normalizedFilters['status'],
                'response_channel' => $normalizedFilters['response_channel'],
            ]),
        ];
    }

    public function update(int $userId, array $input): void
    {
        if ($userId <= 0) {
            throw new ValidationException('Usuario SaaS invalido para atualizar o contato.');
        }

        $contactId = (int) ($input['contact_id'] ?? 0);
        if ($contactId <= 0) {
            throw new ValidationException('Contato comercial invalido para atualizacao.');
        }

        $contact = $this->repository->findById($contactId);
        if ($contact === null) {
            throw new ValidationException('Contato comercial nao encontrado.');
        }

        $name = $this->normalizeRequiredText($input['contact_name'] ?? '', 'Informe o nome do contato.', self::MAX_NAME_LENGTH);
        $email = $this->normalizeEmail($input['contact_email'] ?? '');
        $companyName = $this->normalizeOptionalText($input['company_name'] ?? null, self::MAX_COMPANY_LENGTH);
        $phone = $this->normalizeRequiredText($input['phone'] ?? '', 'Informe telefone ou WhatsApp para retorno.', self::MAX_PHONE_LENGTH);
        $planInterest = $this->normalizeOptionalText($input['plan_interest'] ?? null, self::MAX_PLAN_LENGTH);
        $billingCycle = $this->normalizeBillingCycle($input['billing_cycle_interest'] ?? null);
        $message = $this->normalizeRequiredText($input['message'] ?? '', 'A mensagem nao pode ficar vazia.', self::MAX_MESSAGE_LENGTH);
        $status = strtolower(trim((string) ($input['status'] ?? 'new')));
        $responseChannel = strtolower(trim((string) ($input['response_channel'] ?? '')));
        $responseNotes = $this->normalizeOptionalText($input['response_notes'] ?? null, self::MAX_RESPONSE_NOTES_LENGTH);

        if (!in_array($status, self::ALLOWED_STATUS, true)) {
            throw new ValidationException('Status invalido para o contato comercial.');
        }

        if ($responseChannel !== '' && !in_array($responseChannel, self::ALLOWED_RESPONSE_CHANNELS, true)) {
            throw new ValidationException('Canal de retorno invalido.');
        }

        $respondedAt = '';
        $respondedByUserId = 0;
        if ($status !== 'new') {
            $respondedAt = trim((string) ($contact['responded_at'] ?? ''));
            if ($respondedAt === '') {
                $respondedAt = date('Y-m-d H:i:s');
            }
            $respondedByUserId = $userId;
        }

        $this->repository->updateLead($contactId, [
            'contact_name' => $name,
            'contact_email' => $email,
            'company_name' => $companyName,
            'phone' => $phone,
            'plan_interest' => $planInterest,
            'billing_cycle_interest' => $billingCycle,
            'message' => $message,
            'status' => $status,
            'response_channel' => $responseChannel,
            'response_notes' => $responseNotes,
            'responded_by_user_id' => $respondedByUserId,
            'responded_at' => $respondedAt,
        ]);
    }

    public function delete(int $contactId): void
    {
        if ($contactId <= 0) {
            throw new ValidationException('Contato comercial invalido para exclusao.');
        }

        $contact = $this->repository->findById($contactId);
        if ($contact === null) {
            throw new ValidationException('Contato comercial nao encontrado para exclusao.');
        }

        $this->repository->delete($contactId);
    }

    private function normalizeFilters(array $filters): array
    {
        $search = trim((string) ($filters['contact_search'] ?? ''));
        if (strlen($search) > 80) {
            $search = substr($search, 0, 80);
        }

        $status = strtolower(trim((string) ($filters['contact_status'] ?? '')));
        if ($status !== '' && !in_array($status, self::ALLOWED_STATUS, true)) {
            $status = '';
        }

        $responseChannel = strtolower(trim((string) ($filters['contact_channel'] ?? '')));
        if ($responseChannel !== '' && !in_array($responseChannel, self::ALLOWED_RESPONSE_CHANNELS, true)) {
            $responseChannel = '';
        }

        $page = (int) ($filters['contact_page'] ?? 1);
        if ($page <= 0) {
            $page = 1;
        }

        return [
            'search' => $search,
            'status' => $status,
            'response_channel' => $responseChannel,
            'page' => $page,
            'per_page' => self::LIST_PER_PAGE,
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
            $value = (int) $page;
            if ($value >= 1 && $value <= $lastPage) {
                $normalized[$value] = true;
            }
        }

        $result = array_keys($normalized);
        sort($result);
        return $result;
    }

    private function normalizeRequiredText(mixed $value, string $message, int $maxLength): string
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            throw new ValidationException($message);
        }

        if (strlen($normalized) > $maxLength) {
            throw new ValidationException('O campo informado excede o limite permitido.');
        }

        return $normalized;
    }

    private function normalizeOptionalText(mixed $value, int $maxLength): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized === '') {
            return null;
        }

        if (strlen($normalized) > $maxLength) {
            throw new ValidationException('Um dos campos excede o limite permitido.');
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
            throw new ValidationException('O e-mail excede o limite permitido.');
        }

        return $email;
    }

    private function normalizeBillingCycle(mixed $value): ?string
    {
        $normalized = strtolower(trim((string) ($value ?? '')));
        if ($normalized === '') {
            return null;
        }

        if (!in_array($normalized, self::ALLOWED_BILLING_CYCLES, true)) {
            throw new ValidationException('Ciclo comercial invalido.');
        }

        return $normalized;
    }
}
