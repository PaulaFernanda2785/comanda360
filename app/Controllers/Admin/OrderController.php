<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Services\Admin\CommandService;
use App\Services\Admin\OrderService;
use App\Services\Admin\ProductService;

final class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $service = new OrderService(),
        private readonly CommandService $commandService = new CommandService(),
        private readonly ProductService $productService = new ProductService()
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);

        return $this->view('admin/orders/index', [
            'title' => 'Pedidos',
            'user' => $user,
            'orders' => $this->service->list($companyId),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);

        return $this->view('admin/orders/create', [
            'title' => 'Novo Pedido',
            'user' => $user,
            'commands' => $this->commandService->listOpen($companyId),
            'products' => $this->productService->list($companyId),
        ]);
    }

    public function store(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);

        try {
            $this->service->createFromCommand($companyId, $userId, $request->all());
            return $this->backWithSuccess('Pedido criado com sucesso.', '/admin/orders');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/orders/create');
        }
    }
}
