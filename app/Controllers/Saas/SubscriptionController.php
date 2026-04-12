<?php
declare(strict_types=1);

namespace App\Controllers\Saas;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Saas\SubscriptionService;

final class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $service = new SubscriptionService()
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();

        return $this->view('saas/subscriptions/index', [
            'title' => 'Assinaturas',
            'user' => $user,
            'subscriptions' => $this->service->list(),
        ], 'layouts/saas');
    }
}
