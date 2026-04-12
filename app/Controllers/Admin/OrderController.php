<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Repositories\PermissionRepository;
use App\Services\Admin\CommandService;
use App\Services\Admin\OrderService;
use App\Services\Admin\ProductService;

final class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $service = new OrderService(),
        private readonly CommandService $commandService = new CommandService(),
        private readonly ProductService $productService = new ProductService(),
        private readonly PermissionRepository $permissions = new PermissionRepository()
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);

        return $this->view('admin/orders/index', [
            'title' => 'Pedidos',
            'user' => $user,
            'orders' => $this->service->list($companyId),
            'canUpdateStatus' => $this->permissions->roleHasPermission((int) ($user['role_id'] ?? 0), 'orders.status'),
            'canCancelOrder' => $this->permissions->roleHasPermission((int) ($user['role_id'] ?? 0), 'orders.cancel'),
            'canSendKitchen' => $this->permissions->roleHasPermission((int) ($user['role_id'] ?? 0), 'orders.status'),
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

    public function updateStatus(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);
        $roleId = (int) ($user['role_id'] ?? 0);
        $targetStatus = trim((string) ($request->input('new_status', '')));

        if ($targetStatus === 'canceled' && !$this->permissions->roleHasPermission($roleId, 'orders.cancel')) {
            return $this->backWithError('Seu perfil nao possui permissao para cancelar pedidos.', '/admin/orders');
        }

        try {
            $this->service->updateStatus($companyId, $userId, $request->all());
            return $this->backWithSuccess('Status do pedido atualizado com sucesso.', '/admin/orders');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/orders');
        }
    }

    public function sendToKitchen(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);

        try {
            $this->service->sendToKitchen($companyId, $userId, $request->all());
            return $this->backWithSuccess('Pedido enviado para cozinha.', '/admin/orders');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/orders');
        }
    }
}
