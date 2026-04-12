<?php
declare(strict_types=1);

namespace App\Controllers\Saas;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Saas\PlanService;

final class PlanController extends Controller
{
    public function __construct(
        private readonly PlanService $service = new PlanService()
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();

        return $this->view('saas/plans/index', [
            'title' => 'Planos',
            'user' => $user,
            'plans' => $this->service->list(),
        ], 'layouts/saas');
    }
}
