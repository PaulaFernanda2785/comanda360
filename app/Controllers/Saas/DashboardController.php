<?php
declare(strict_types=1);

namespace App\Controllers\Saas;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Saas\DashboardService;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $service = new DashboardService()
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();

        return $this->view('saas/dashboard/index', [
            'title' => 'Dashboard SaaS',
            'user' => $user,
            'summary' => $this->service->summary(),
        ], 'layouts/saas');
    }
}
