<?php
declare(strict_types=1);

namespace App\Services\Saas;

use App\Services\Admin\SubscriptionPortalService;
use App\Exceptions\ValidationException;
use App\Repositories\CompanyRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\PlanRepository;
use DateInterval;
use DateTimeImmutable;

final class CompanyService
{
    private const COMPANY_LIST_PER_PAGE = 10;
    private const INITIAL_ADMIN_ROLE_SLUG = 'admin_establishment';

    private const ALLOWED_COMPANY_STATUS = [
        'ativa',
        'teste',
        'suspensa',
        'cancelada',
    ];

    private const ALLOWED_COMPANY_SUBSCRIPTION_STATUS = [
        'ativa',
        'trial',
        'inadimplente',
        'suspensa',
        'cancelada',
    ];

    private const ALLOWED_BILLING_CYCLE = [
        'mensal',
        'anual',
    ];

    public function __construct(
        private readonly CompanyRepository $companies = new CompanyRepository(),
        private readonly DashboardRepository $dashboard = new DashboardRepository(),
        private readonly PlanRepository $plans = new PlanRepository(),
        private readonly SubscriptionPortalService $subscriptionPortal = new SubscriptionPortalService()
    ) {}

    public function panel(array $filters): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        $page = $this->companies->listForSaasPaginated(
            [
                'search' => $normalizedFilters['search'],
                'status' => $normalizedFilters['status'],
                'subscription_status' => $normalizedFilters['subscription_status'],
                'plan_id' => $normalizedFilters['plan_id'],
            ],
            $normalizedFilters['page'],
            $normalizedFilters['per_page']
        );

        $items = is_array($page['items'] ?? null) ? $page['items'] : [];
        $total = (int) ($page['total'] ?? 0);
        $currentPage = (int) ($page['page'] ?? 1);
        $perPage = (int) ($page['per_page'] ?? self::COMPANY_LIST_PER_PAGE);
        $lastPage = (int) ($page['last_page'] ?? 1);

        return [
            'companies' => $items,
            'filters' => $normalizedFilters,
            'summary' => $this->companies->summary([
                'search' => $normalizedFilters['search'],
                'status' => $normalizedFilters['status'],
                'subscription_status' => $normalizedFilters['subscription_status'],
                'plan_id' => $normalizedFilters['plan_id'],
            ]),
            'pagination' => [
                'total' => $total,
                'page' => $currentPage,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'from' => $total > 0 ? (($currentPage - 1) * $perPage) + 1 : 0,
                'to' => $total > 0 ? min($total, $currentPage * $perPage) : 0,
                'pages' => $this->buildPaginationPages($currentPage, $lastPage),
            ],
            'plans' => $this->plans->allForSaas(),
            'status_options' => self::ALLOWED_COMPANY_STATUS,
            'subscription_status_options' => self::ALLOWED_COMPANY_SUBSCRIPTION_STATUS,
            'billing_cycle_options' => self::ALLOWED_BILLING_CYCLE,
        ];
    }

    public function createCompany(array $input): int
    {
        $payload = $this->normalizePayload($input, null);
        $companyId = $this->companies->transaction(function () use ($payload): int {
            $companyId = $this->companies->createCompany($payload['company']);
            $subscription = $payload['subscription'];
            if ($subscription !== null) {
                $subscription['company_id'] = $companyId;
                $this->companies->createSubscription($subscription);
            }

            $this->createInitialAdminUser($companyId, $payload['initial_admin_user'] ?? []);

            return $companyId;
        });

        $this->syncBillingSchedule($companyId, $payload['next_charge_due_date']);

        return $companyId;
    }

    public function updateCompany(int $companyId, array $input): void
    {
        if ($companyId <= 0) {
            throw new ValidationException('Empresa invalida para edicao.');
        }

        $existing = $this->companies->findByIdForSaas($companyId);
        if ($existing === null) {
            throw new ValidationException('Empresa nao encontrada para edicao.');
        }

        $currentSubscription = $this->companies->findCurrentSubscriptionByCompanyId($companyId);
        $payload = $this->normalizePayload($input, $existing);

        $this->companies->transaction(function () use ($companyId, $payload, $currentSubscription, $existing): void {
            $this->companies->updateCompany($companyId, $payload['company']);

            $subscription = $payload['subscription'];
            if ($subscription === null) {
                return;
            }

            if ($currentSubscription !== null) {
                $this->companies->updateSubscription((int) $currentSubscription['id'], $subscription);
                $this->syncInitialAdminUser($companyId, $existing, $payload['company']);
                return;
            }

            $subscription['company_id'] = $companyId;
            $this->companies->createSubscription($subscription);
            $this->syncInitialAdminUser($companyId, $existing, $payload['company']);
        });

        $this->syncBillingSchedule($companyId, $payload['next_charge_due_date']);
    }

    public function cancelCompany(int $companyId): void
    {
        if ($companyId <= 0) {
            throw new ValidationException('Empresa invalida para cancelamento.');
        }

        $company = $this->companies->findByIdForSaas($companyId);
        if ($company === null) {
            throw new ValidationException('Empresa nao encontrada para cancelamento.');
        }

        if (
            strtolower(trim((string) ($company['status'] ?? ''))) === 'cancelada'
            && strtolower(trim((string) ($company['subscription_status'] ?? ''))) === 'cancelada'
        ) {
            throw new ValidationException('A empresa ja esta cancelada.');
        }

        $timestamp = date('Y-m-d H:i:s');

        $this->companies->transaction(function () use ($companyId, $company, $timestamp): void {
            $this->companies->updateCompany($companyId, [
                'name' => (string) ($company['name'] ?? ''),
                'legal_name' => $this->nullableTrim($company['legal_name'] ?? null),
                'document_number' => $this->nullableTrim($company['document_number'] ?? null),
                'email' => (string) ($company['email'] ?? ''),
                'phone' => $this->nullableTrim($company['phone'] ?? null),
                'whatsapp' => $this->nullableTrim($company['whatsapp'] ?? null),
                'slug' => (string) ($company['slug'] ?? ''),
                'status' => 'cancelada',
                'plan_id' => ($company['plan_id'] ?? null) !== null ? (int) $company['plan_id'] : null,
                'subscription_status' => 'cancelada',
                'trial_ends_at' => null,
                'subscription_starts_at' => $this->nullableTrim($company['subscription_starts_at'] ?? null),
                'subscription_ends_at' => $timestamp,
            ]);

            $this->companies->cancelLatestSubscriptionByCompanyId($companyId, $timestamp);
        });
    }

    private function normalizeFilters(array $filters): array
    {
        $search = trim((string) ($filters['company_search'] ?? ''));
        if (strlen($search) > 80) {
            $search = substr($search, 0, 80);
        }

        $status = strtolower(trim((string) ($filters['company_status'] ?? '')));
        if ($status !== '' && !in_array($status, self::ALLOWED_COMPANY_STATUS, true)) {
            $status = '';
        }

        $subscriptionStatus = strtolower(trim((string) ($filters['company_subscription_status'] ?? '')));
        if ($subscriptionStatus !== '' && !in_array($subscriptionStatus, self::ALLOWED_COMPANY_SUBSCRIPTION_STATUS, true)) {
            $subscriptionStatus = '';
        }

        $planId = (int) ($filters['company_plan_id'] ?? 0);
        if ($planId < 0) {
            $planId = 0;
        }

        $page = (int) ($filters['company_page'] ?? 1);
        if ($page <= 0) {
            $page = 1;
        }

        return [
            'search' => $search,
            'status' => $status,
            'subscription_status' => $subscriptionStatus,
            'plan_id' => $planId,
            'page' => $page,
            'per_page' => self::COMPANY_LIST_PER_PAGE,
        ];
    }

    private function normalizePayload(array $input, ?array $existing): array
    {
        $plans = $this->plans->allForSaas();
        $plansById = [];
        foreach ($plans as $plan) {
            if (!is_array($plan)) {
                continue;
            }

            $planId = (int) ($plan['id'] ?? 0);
            if ($planId > 0) {
                $plansById[$planId] = $plan;
            }
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new ValidationException('Informe o nome da empresa.');
        }

        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException('Informe um e-mail valido para a empresa.');
        }

        $initialAdminPassword = trim((string) ($input['initial_admin_password'] ?? ''));
        if ($existing === null) {
            if ($initialAdminPassword === '') {
                throw new ValidationException('Informe a senha inicial do administrador da empresa.');
            }

            if (strlen($initialAdminPassword) < 6) {
                throw new ValidationException('A senha inicial do administrador deve ter no minimo 6 caracteres.');
            }

            $existingByEmail = $this->dashboard->findUserByEmail($email);
            if ($existingByEmail !== null && trim((string) ($existingByEmail['deleted_at'] ?? '')) === '') {
                throw new ValidationException('Ja existe usuario cadastrado com o e-mail principal informado para a empresa.');
            }
        }

        $planId = (int) ($input['plan_id'] ?? ($existing['plan_id'] ?? 0));
        if ($planId <= 0 || !isset($plansById[$planId])) {
            throw new ValidationException('Selecione um plano valido para a empresa.');
        }

        $companyStatus = strtolower(trim((string) ($input['status'] ?? ($existing['status'] ?? 'teste'))));
        if (!in_array($companyStatus, self::ALLOWED_COMPANY_STATUS, true)) {
            throw new ValidationException('Status operacional invalido para a empresa.');
        }

        $companySubscriptionStatus = strtolower(trim((string) ($input['subscription_status'] ?? ($existing['subscription_status'] ?? 'trial'))));
        if (!in_array($companySubscriptionStatus, self::ALLOWED_COMPANY_SUBSCRIPTION_STATUS, true)) {
            throw new ValidationException('Status da assinatura invalido para a empresa.');
        }

        $billingCycle = strtolower(trim((string) ($input['billing_cycle'] ?? ($existing['billing_cycle'] ?? 'mensal'))));
        if (!in_array($billingCycle, self::ALLOWED_BILLING_CYCLE, true)) {
            throw new ValidationException('Ciclo de cobranca invalido para a empresa.');
        }

        $plan = $plansById[$planId];
        $defaultAmount = $billingCycle === 'anual'
            ? (float) ($plan['price_yearly'] ?? 0)
            : (float) ($plan['price_monthly'] ?? 0);

        $amountRaw = str_replace(',', '.', trim((string) ($input['amount'] ?? '')));
        $amount = $amountRaw !== '' ? (float) $amountRaw : $defaultAmount;
        if ($amount < 0) {
            throw new ValidationException('O valor contratado nao pode ser negativo.');
        }

        $slugInput = trim((string) ($input['slug'] ?? ''));
        $slug = $this->buildUniqueSlug(
            $slugInput !== '' ? $slugInput : $name,
            $existing !== null ? (int) ($existing['id'] ?? 0) : null
        );

        $subscriptionStartsAt = $this->normalizeDateInput(
            (string) ($input['subscription_starts_at'] ?? ($existing['subscription_starts_at'] ?? $existing['subscription_record_starts_at'] ?? '')),
            false
        );
        if ($subscriptionStartsAt === null) {
            $subscriptionStartsAt = date('Y-m-d 00:00:00');
        }

        $subscriptionEndsAt = $this->normalizeDateInput(
            (string) ($input['subscription_ends_at'] ?? ($existing['subscription_ends_at'] ?? $existing['subscription_record_ends_at'] ?? '')),
            true
        );
        $trialEndsAt = $this->normalizeDateInput(
            (string) ($input['trial_ends_at'] ?? ($existing['trial_ends_at'] ?? '')),
            true
        );
        $nextChargeDueDate = $this->normalizeOptionalDueDateInput(
            (string) ($input['next_charge_due_date'] ?? ($existing['next_charge_due_date'] ?? ''))
        );

        if ($companySubscriptionStatus === 'trial') {
            if ($trialEndsAt === null) {
                $trialEndsAt = $this->defaultEndDate($subscriptionStartsAt, 'mensal', 7);
            }
            $subscriptionEndsAt = null;
        } elseif ($companySubscriptionStatus === 'cancelada') {
            if ($subscriptionEndsAt === null) {
                $subscriptionEndsAt = date('Y-m-d H:i:s');
            }
            $trialEndsAt = null;
        } else {
            if ($subscriptionEndsAt === null) {
                $subscriptionEndsAt = $this->defaultEndDate($subscriptionStartsAt, $billingCycle);
            }
            $trialEndsAt = null;
        }

        $legalName = $this->nullableTrim($input['legal_name'] ?? ($existing['legal_name'] ?? null));
        $documentNumber = $this->nullableTrim($input['document_number'] ?? ($existing['document_number'] ?? null));
        $phone = $this->nullableTrim($input['phone'] ?? ($existing['phone'] ?? null));
        $whatsapp = $this->nullableTrim($input['whatsapp'] ?? ($existing['whatsapp'] ?? null));

        return [
            'company' => [
                'name' => $name,
                'legal_name' => $legalName,
                'document_number' => $documentNumber,
                'email' => $email,
                'phone' => $phone,
                'whatsapp' => $whatsapp,
                'slug' => $slug,
                'status' => $companyStatus,
                'plan_id' => $planId,
                'subscription_status' => $companySubscriptionStatus,
                'trial_ends_at' => $trialEndsAt,
                'subscription_starts_at' => $subscriptionStartsAt,
                'subscription_ends_at' => $subscriptionEndsAt,
            ],
            'subscription' => [
                'plan_id' => $planId,
                'status' => $this->mapCompanySubscriptionStatusToSubscriptionStatus($companySubscriptionStatus),
                'billing_cycle' => $billingCycle,
                'amount' => round($amount, 2),
                'starts_at' => $subscriptionStartsAt,
                'ends_at' => $companySubscriptionStatus === 'trial' ? $trialEndsAt : $subscriptionEndsAt,
                'canceled_at' => $companySubscriptionStatus === 'cancelada' ? ($subscriptionEndsAt ?? date('Y-m-d H:i:s')) : null,
            ],
            'next_charge_due_date' => $nextChargeDueDate,
            'initial_admin_user' => $existing === null ? [
                'name' => $name,
                'email' => $email,
                'phone' => $phone ?: $whatsapp,
                'password_hash' => password_hash($initialAdminPassword, PASSWORD_DEFAULT),
                'status' => 'ativo',
            ] : null,
        ];
    }

    private function buildUniqueSlug(string $value, ?int $exceptCompanyId = null): string
    {
        $base = $this->slugify($value);
        if ($base === '') {
            $base = 'empresa';
        }

        $base = substr($base, 0, 120);
        $candidate = $base;
        $suffix = 2;

        while ($this->companies->slugExists($candidate, $exceptCompanyId)) {
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

    private function normalizeDateInput(string $value, bool $endOfDay): ?string
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        $normalized = str_replace('T', ' ', $raw);
        $date = date_create_immutable($normalized);
        if (!$date instanceof DateTimeImmutable) {
            throw new ValidationException('Data invalida informada para a empresa.');
        }

        if (strlen($normalized) <= 10) {
            $time = $endOfDay ? '23:59:59' : '00:00:00';
            return $date->format('Y-m-d') . ' ' . $time;
        }

        return $date->format('Y-m-d H:i:s');
    }

    private function defaultEndDate(string $startAt, string $billingCycle, int $extraDays = 0): string
    {
        $start = date_create_immutable($startAt);
        if (!$start instanceof DateTimeImmutable) {
            return date('Y-m-d 23:59:59');
        }

        $end = $billingCycle === 'anual'
            ? $start->add(new DateInterval('P1Y'))
            : $start->add(new DateInterval('P1M'));

        if ($extraDays > 0) {
            $end = $start->add(new DateInterval('P' . $extraDays . 'D'));
        }

        return $end->setTime(23, 59, 59)->format('Y-m-d H:i:s');
    }

    private function normalizeOptionalDueDateInput(string $value): ?string
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        $date = date_create_immutable($raw);
        if (!$date instanceof DateTimeImmutable) {
            throw new ValidationException('Data invalida para o vencimento da proxima cobranca.');
        }

        return $date->format('Y-m-d');
    }

    private function mapCompanySubscriptionStatusToSubscriptionStatus(string $status): string
    {
        return match ($status) {
            'ativa' => 'ativa',
            'trial' => 'trial',
            'cancelada' => 'cancelada',
            'inadimplente', 'suspensa' => 'vencida',
            default => 'trial',
        };
    }

    private function createInitialAdminUser(int $companyId, array $data): void
    {
        if ($companyId <= 0) {
            throw new ValidationException('Empresa invalida para criacao do usuario administrador.');
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $passwordHash = trim((string) ($data['password_hash'] ?? ''));
        if ($email === '' || $passwordHash === '') {
            throw new ValidationException('Dados incompletos para criar o usuario administrador da empresa.');
        }

        $role = $this->dashboard->findCompanyRoleBySlug($companyId, self::INITIAL_ADMIN_ROLE_SLUG);
        if ($role === null) {
            throw new ValidationException('Perfil administrativo padrao da empresa nao foi encontrado.');
        }

        $existingByEmail = $this->dashboard->findUserByEmail($email);
        if ($existingByEmail !== null && trim((string) ($existingByEmail['deleted_at'] ?? '')) === '') {
            throw new ValidationException('Ja existe usuario cadastrado com o e-mail principal informado para a empresa.');
        }

        $this->dashboard->createCompanyUser([
            'company_id' => $companyId,
            'role_id' => (int) ($role['id'] ?? 0),
            'name' => trim((string) ($data['name'] ?? 'Empresa')),
            'email' => $email,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'password_hash' => $passwordHash,
            'status' => 'ativo',
        ]);
    }

    private function syncInitialAdminUser(int $companyId, array $existingCompany, array $companyData): void
    {
        if ($companyId <= 0) {
            return;
        }

        $previousEmail = strtolower(trim((string) ($existingCompany['email'] ?? '')));
        $nextEmail = strtolower(trim((string) ($companyData['email'] ?? '')));
        $nextName = trim((string) ($companyData['name'] ?? ''));
        $nextPhone = trim((string) ($companyData['phone'] ?? $companyData['whatsapp'] ?? ''));

        if ($previousEmail === '' || $nextEmail === '') {
            return;
        }

        if ($previousEmail === $nextEmail && $nextName === trim((string) ($existingCompany['name'] ?? '')) && $nextPhone === trim((string) ($existingCompany['phone'] ?? ''))) {
            return;
        }

        $companyUsers = $this->dashboard->listUsersByCompany($companyId);
        $matches = array_values(array_filter(
            $companyUsers,
            static fn (array $user): bool => strtolower(trim((string) ($user['role_slug'] ?? ''))) === self::INITIAL_ADMIN_ROLE_SLUG
                && strtolower(trim((string) ($user['email'] ?? ''))) === $previousEmail
        ));

        if (count($matches) !== 1) {
            return;
        }

        $adminUser = $matches[0];
        $adminUserId = (int) ($adminUser['id'] ?? 0);
        if ($adminUserId <= 0) {
            return;
        }

        $existingByEmail = $this->dashboard->findUserByEmail($nextEmail);
        if ($existingByEmail !== null) {
            $existingUserId = (int) ($existingByEmail['id'] ?? 0);
            $deletedAt = trim((string) ($existingByEmail['deleted_at'] ?? ''));
            if ($deletedAt === '' && $existingUserId !== $adminUserId) {
                throw new ValidationException('Nao foi possivel atualizar o usuario administrador automaticamente porque o novo e-mail da empresa ja esta em uso por outro usuario.');
            }
        }

        $this->dashboard->updateCompanyUser($companyId, $adminUserId, [
            'role_id' => (int) ($adminUser['role_id'] ?? 0),
            'name' => $nextName !== '' ? $nextName : (string) ($adminUser['name'] ?? 'Empresa'),
            'email' => $nextEmail,
            'phone' => $nextPhone !== '' ? $nextPhone : null,
        ]);
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

    private function syncBillingSchedule(int $companyId, ?string $nextChargeDueDate): void
    {
        if ($companyId <= 0) {
            return;
        }

        $this->subscriptionPortal->synchronizeCompanyBilling($companyId);

        if ($nextChargeDueDate !== null) {
            $this->subscriptionPortal->setNextChargeDueDate($companyId, $nextChargeDueDate);
        }
    }

    private function nullableTrim(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        return $normalized !== '' ? $normalized : null;
    }
}
