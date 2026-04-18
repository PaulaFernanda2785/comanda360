<?php
declare(strict_types=1);

namespace App\Services\Saas;

use App\Repositories\CompanyRepository;
use App\Repositories\PlanRepository;

final class DashboardService
{
    public function __construct(
        private readonly CompanyRepository $companies = new CompanyRepository(),
        private readonly PlanRepository $plans = new PlanRepository(),
        private readonly SubscriptionService $subscriptions = new SubscriptionService(),
        private readonly SubscriptionPaymentService $subscriptionPayments = new SubscriptionPaymentService(),
        private readonly SupportService $support = new SupportService()
    ) {}

    public function summary(): array
    {
        $subscriptionPanel = $this->subscriptions->panel([]);

        return [
            'companies' => $this->companies->summary(),
            'plans' => $this->plans->summary(),
            'subscriptions' => $subscriptionPanel['summary'] ?? [],
            'subscription_payments' => $this->subscriptionPayments->summary(),
            'support' => ($this->support->panel([])['summary'] ?? []),
        ];
    }

    public function panel(array $filters = []): array
    {
        $paymentFilters = $this->normalizeDashboardPaymentFilters($filters);
        $companySummary = $this->companies->summary();
        $companyPage = $this->companies->listForSaasPaginated([
            'search' => '',
            'status' => '',
            'subscription_status' => '',
            'plan_id' => 0,
        ], 1, 6);

        $planSummary = $this->plans->summary();
        $planItems = $this->plans->allForSaas();
        usort($planItems, static function (array $left, array $right): int {
            $leftStatus = (string) ($left['status'] ?? '');
            $rightStatus = (string) ($right['status'] ?? '');

            if ($leftStatus !== $rightStatus) {
                return $leftStatus === 'ativo' ? -1 : 1;
            }

            $leftCompanies = (int) ($left['linked_companies_count'] ?? 0);
            $rightCompanies = (int) ($right['linked_companies_count'] ?? 0);
            if ($leftCompanies !== $rightCompanies) {
                return $rightCompanies <=> $leftCompanies;
            }

            return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
        });

        $subscriptionPanel = $this->subscriptions->panel([]);
        $subscriptionSummary = is_array($subscriptionPanel['summary'] ?? null) ? $subscriptionPanel['summary'] : [];

        $paymentPanel = $this->subscriptionPayments->panel($paymentFilters);
        $paymentSummary = is_array($paymentPanel['summary'] ?? null) ? $paymentPanel['summary'] : [];

        $supportPanel = $this->support->panel([]);
        $supportSummary = is_array($supportPanel['summary'] ?? null) ? $supportPanel['summary'] : [];

        return [
            'overview' => [
                'total_companies' => (int) ($companySummary['total_companies'] ?? 0),
                'active_subscriptions' => (int) ($subscriptionSummary['active_subscriptions'] ?? 0),
                'active_monthly_mrr' => (float) ($subscriptionSummary['active_monthly_mrr'] ?? 0),
                'pending_charges' => (int) ($paymentSummary['pending_charges'] ?? 0),
                'overdue_charges' => (int) ($paymentSummary['overdue_charges'] ?? 0),
                'urgent_tickets' => (int) ($supportSummary['urgent_count'] ?? 0),
                'delinquent_companies' => (int) ($companySummary['delinquent_companies'] ?? 0),
                'gateway_bound_subscriptions' => (int) ($subscriptionSummary['gateway_bound'] ?? 0),
                'auto_charge_enabled' => (int) ($subscriptionSummary['auto_charge_enabled'] ?? 0),
            ],
            'companies' => [
                'summary' => $companySummary,
                'items' => array_slice(is_array($companyPage['items'] ?? null) ? $companyPage['items'] : [], 0, 6),
            ],
            'plans' => [
                'summary' => $planSummary,
                'items' => array_slice($planItems, 0, 6),
            ],
            'subscriptions' => [
                'summary' => $subscriptionSummary,
                'items' => array_slice(is_array($subscriptionPanel['subscriptions'] ?? null) ? $subscriptionPanel['subscriptions'] : [], 0, 6),
            ],
            'subscription_payments' => [
                'summary' => $paymentSummary,
                'items' => is_array($paymentPanel['payments'] ?? null) ? $paymentPanel['payments'] : [],
                'filters' => [
                    'search' => $paymentFilters['search'],
                    'status' => $paymentFilters['status'],
                ],
                'pagination' => is_array($paymentPanel['pagination'] ?? null) ? $paymentPanel['pagination'] : [],
            ],
            'support' => [
                'summary' => $supportSummary,
                'items' => array_slice(is_array($supportPanel['tickets'] ?? null) ? $supportPanel['tickets'] : [], 0, 6),
            ],
        ];
    }

    private function normalizeDashboardPaymentFilters(array $filters): array
    {
        $page = (int) ($filters['dashboard_payment_page'] ?? 1);
        if ($page <= 0) {
            $page = 1;
        }

        return [
            'search' => trim((string) ($filters['dashboard_payment_search'] ?? '')),
            'status' => trim((string) ($filters['dashboard_payment_status'] ?? '')),
            'payment_page' => $page,
        ];
    }
}
