<?php
declare(strict_types=1);

namespace App\Services\Saas;

use App\Repositories\CompanyRepository;
use App\Repositories\PlanRepository;
use App\Repositories\SubscriptionRepository;

final class DashboardService
{
    public function __construct(
        private readonly CompanyRepository $companies = new CompanyRepository(),
        private readonly PlanRepository $plans = new PlanRepository(),
        private readonly SubscriptionRepository $subscriptions = new SubscriptionRepository()
    ) {}

    public function summary(): array
    {
        return [
            'companies' => $this->companies->summary(),
            'plans' => $this->plans->summary(),
            'subscriptions' => $this->subscriptions->summary(),
        ];
    }
}
