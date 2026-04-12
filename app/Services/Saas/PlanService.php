<?php
declare(strict_types=1);

namespace App\Services\Saas;

use App\Repositories\PlanRepository;

final class PlanService
{
    public function __construct(
        private readonly PlanRepository $plans = new PlanRepository()
    ) {}

    public function list(): array
    {
        return $this->plans->allForSaas();
    }
}
