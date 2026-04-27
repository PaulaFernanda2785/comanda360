<?php
declare(strict_types=1);

namespace App\Services\Shared;

use App\Repositories\CompanyRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\SubscriptionPaymentRepository;
use App\Repositories\SubscriptionRepository;

final class CompanyAccessProvisioningService
{
    private const INITIAL_ADMIN_ROLE_SLUG = 'admin_establishment';

    public function __construct(
        private readonly CompanyRepository $companies = new CompanyRepository(),
        private readonly DashboardRepository $dashboard = new DashboardRepository(),
        private readonly SubscriptionRepository $subscriptions = new SubscriptionRepository(),
        private readonly SubscriptionPaymentRepository $subscriptionPayments = new SubscriptionPaymentRepository()
    ) {}

    public function activateIfEligible(int $companyId): bool
    {
        if ($companyId <= 0) {
            return false;
        }

        $company = $this->companies->findByIdForSaas($companyId);
        $subscription = $this->subscriptions->findCurrentByCompanyId($companyId);
        if ($company === null || $subscription === null) {
            return false;
        }

        $subscriptionId = (int) ($subscription['id'] ?? 0);
        if ($subscriptionId <= 0 || !$this->isSubscriptionEligible($subscription, $subscriptionId)) {
            return false;
        }

        $this->companies->transaction(function () use ($companyId, $company, $subscription): void {
            if (strtolower(trim((string) ($company['status'] ?? ''))) !== 'ativa') {
                $this->companies->updateStatus($companyId, 'ativa');
            }

            if (strtolower(trim((string) ($company['subscription_status'] ?? ''))) !== 'ativa') {
                $this->companies->updateSubscriptionSnapshot($companyId, [
                    'plan_id' => $subscription['plan_id'] ?? ($company['plan_id'] ?? null),
                    'subscription_status' => 'ativa',
                    'trial_ends_at' => null,
                    'subscription_starts_at' => $company['subscription_starts_at']
                        ?? ($subscription['starts_at'] ?? null),
                    'subscription_ends_at' => $company['subscription_ends_at']
                        ?? ($subscription['ends_at'] ?? null),
                ]);
            }

            $this->activateInitialAdminUsers($companyId);
        });

        return true;
    }

    private function isSubscriptionEligible(array $subscription, int $subscriptionId): bool
    {
        $subscriptionStatus = strtolower(trim((string) ($subscription['status'] ?? '')));
        $companySubscriptionStatus = strtolower(trim((string) ($subscription['company_subscription_status'] ?? '')));

        if ($subscriptionStatus === 'cancelada' || $companySubscriptionStatus === 'cancelada') {
            return false;
        }

        if ($subscriptionStatus === 'ativa' || $companySubscriptionStatus === 'ativa') {
            return true;
        }

        $today = date('Y-m-d');
        foreach ($this->subscriptionPayments->listOpenBySubscriptionId($subscriptionId) as $payment) {
            $status = strtolower(trim((string) ($payment['status'] ?? '')));
            $dueDate = trim((string) ($payment['due_date'] ?? ''));
            if ($status === 'vencido' || ($status === 'pendente' && $dueDate !== '' && $dueDate < $today)) {
                return false;
            }
        }

        foreach ($this->subscriptionPayments->listBySubscriptionId($subscriptionId, 120) as $payment) {
            if (strtolower(trim((string) ($payment['status'] ?? ''))) === 'pago') {
                return true;
            }
        }

        return false;
    }

    private function activateInitialAdminUsers(int $companyId): void
    {
        $users = $this->dashboard->listUsersByCompany($companyId);
        foreach ($users as $user) {
            if (!is_array($user)) {
                continue;
            }

            $userId = (int) ($user['id'] ?? 0);
            $roleSlug = strtolower(trim((string) ($user['role_slug'] ?? '')));
            $status = strtolower(trim((string) ($user['status'] ?? '')));
            if ($userId <= 0 || $roleSlug !== self::INITIAL_ADMIN_ROLE_SLUG || $status === 'ativo') {
                continue;
            }

            $this->dashboard->updateCompanyUserStatus($companyId, $userId, 'ativo');
        }
    }
}
