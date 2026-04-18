<?php
declare(strict_types=1);

namespace App\Services\Saas;

use App\Repositories\SubscriptionRepository;

final class SubscriptionService
{
    private const SUBSCRIPTION_LIST_PER_PAGE = 10;

    public function __construct(
        private readonly SubscriptionRepository $subscriptions = new SubscriptionRepository()
    ) {}

    public function panel(array $filters = []): array
    {
        $normalizedFilters = $this->normalizeFilters($filters);
        $items = $this->applyFilters($this->subscriptions->allForSaas(), $normalizedFilters);

        $total = count($items);
        $page = min($normalizedFilters['page'], max(1, (int) ceil($total / $normalizedFilters['per_page'])));
        $offset = ($page - 1) * $normalizedFilters['per_page'];
        $pagedItems = array_slice($items, $offset, $normalizedFilters['per_page']);

        return [
            'subscriptions' => $pagedItems,
            'filters' => [
                'search' => $normalizedFilters['search'],
                'status' => $normalizedFilters['status'],
                'page' => $page,
                'per_page' => $normalizedFilters['per_page'],
            ],
            'summary' => $this->buildSummary($items),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $normalizedFilters['per_page'],
                'last_page' => max(1, (int) ceil($total / $normalizedFilters['per_page'])),
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => $total > 0 ? min($total, $offset + $normalizedFilters['per_page']) : 0,
                'pages' => $this->buildPaginationPages($page, max(1, (int) ceil($total / $normalizedFilters['per_page']))),
            ],
        ];
    }

    public function list(): array
    {
        return $this->subscriptions->allForSaas();
    }

    private function normalizeFilters(array $input): array
    {
        $search = trim((string) ($input['search'] ?? ''));
        if (strlen($search) > 80) {
            $search = substr($search, 0, 80);
        }

        $status = strtolower(trim((string) ($input['status'] ?? '')));
        if ($status !== '' && !in_array($status, ['ativa', 'trial', 'vencida', 'cancelada'], true)) {
            $status = '';
        }

        $page = (int) ($input['subscription_page'] ?? 1);
        if ($page <= 0) {
            $page = 1;
        }

        return [
            'search' => $search,
            'status' => $status,
            'page' => $page,
            'per_page' => self::SUBSCRIPTION_LIST_PER_PAGE,
        ];
    }

    private function applyFilters(array $items, array $filters): array
    {
        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        $status = trim((string) ($filters['status'] ?? ''));

        return array_values(array_filter($items, static function (array $item) use ($search, $status): bool {
            if ($status !== '' && strtolower(trim((string) ($item['status'] ?? ''))) !== $status) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = strtolower(implode(' ', array_filter([
                (string) ($item['company_name'] ?? ''),
                (string) ($item['company_slug'] ?? ''),
                (string) ($item['company_email'] ?? ''),
                (string) ($item['plan_name'] ?? ''),
                (string) ($item['plan_slug'] ?? ''),
                (string) ($item['gateway_provider'] ?? ''),
                (string) ($item['gateway_subscription_id'] ?? ''),
                (string) ($item['gateway_status'] ?? ''),
            ])));

            return str_contains($haystack, $search);
        }));
    }

    private function buildSummary(array $items): array
    {
        $summary = [
            'total_subscriptions' => count($items),
            'active_subscriptions' => 0,
            'trial_subscriptions' => 0,
            'expired_subscriptions' => 0,
            'canceled_subscriptions' => 0,
            'active_monthly_mrr' => 0.0,
            'auto_charge_enabled' => 0,
            'gateway_bound' => 0,
        ];

        foreach ($items as $item) {
            $status = strtolower(trim((string) ($item['status'] ?? '')));
            $billingCycle = strtolower(trim((string) ($item['billing_cycle'] ?? '')));
            $amount = (float) ($item['amount'] ?? 0);
            $hasGatewayBinding = trim((string) ($item['gateway_subscription_id'] ?? '')) !== ''
                || trim((string) ($item['gateway_checkout_url'] ?? '')) !== '';

            if ($status === 'ativa') {
                $summary['active_subscriptions']++;
                if ($billingCycle === 'mensal') {
                    $summary['active_monthly_mrr'] += $amount;
                }
            } elseif ($status === 'trial') {
                $summary['trial_subscriptions']++;
            } elseif ($status === 'vencida') {
                $summary['expired_subscriptions']++;
            } elseif ($status === 'cancelada') {
                $summary['canceled_subscriptions']++;
            }

            if ((int) ($item['auto_charge_enabled'] ?? 0) === 1) {
                $summary['auto_charge_enabled']++;
            }

            if ($hasGatewayBinding) {
                $summary['gateway_bound']++;
            }
        }

        return $summary;
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
