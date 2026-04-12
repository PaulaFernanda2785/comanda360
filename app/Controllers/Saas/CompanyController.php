<?php
declare(strict_types=1);

namespace App\Controllers\Saas;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Saas\CompanyService;

final class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $service = new CompanyService()
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();

        return $this->view('saas/companies/index', [
            'title' => 'Empresas',
            'user' => $user,
            'companies' => $this->service->list(),
        ], 'layouts/saas');
    }
}
