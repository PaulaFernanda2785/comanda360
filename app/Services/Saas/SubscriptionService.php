<?php
declare(strict_types=1);

namespace App\Services\Saas;

use App\Repositories\SubscriptionRepository;

final class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions = new SubscriptionRepository()
    ) {}

    public function list(): array
    {
        return $this->subscriptions->allForSaas();
    }
}
