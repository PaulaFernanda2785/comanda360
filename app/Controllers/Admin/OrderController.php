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
        $panel = $this->service->operationalPanelByTable($companyId);

        return $this->view('admin/orders/index', [
            'title' => 'Pedidos',
            'user' => $user,
            'panelSummary' => $panel['summary'] ?? [],
            'ordersByTable' => $panel['tables'] ?? [],
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
            'products' => $this->productService->listForOrderForm($companyId),
        ]);
    }

    public function printTicket(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $orderId = (int) ($request->input('order_id', 0));
        $orderIdsRaw = trim((string) ($request->input('order_ids', '')));
        $orderIds = [];

        if ($orderIdsRaw !== '') {
            $tokens = preg_split('/[\s,;|]+/', $orderIdsRaw) ?: [];
            foreach ($tokens as $token) {
                $candidate = (int) trim((string) $token);
                if ($candidate > 0) {
                    $orderIds[$candidate] = true;
                }
            }
        }

        try {
            $context = $orderIds !== []
                ? $this->service->ticketPrintContextByOrderIds($companyId, array_keys($orderIds))
                : $this->service->ticketPrintContext($companyId, $orderId);

            return $this->view('admin/orders/print_ticket', [
                'title' => 'Imprimir Ticket do Pedido',
                'user' => $user,
                'context' => $context,
            ]);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/orders');
        }
    }

    public function store(Request $request): Response
    {
        $guard = $this->guardSingleSubmit($request, 'orders.store', '/admin/orders/create');
        if ($guard !== null) {
            return $guard;
        }

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
        $expectsJson = $this->expectsJson($request);

        if ($targetStatus === 'canceled' && !$this->permissions->roleHasPermission($roleId, 'orders.cancel')) {
            if ($expectsJson) {
                return $this->jsonResponse([
                    'ok' => false,
                    'message' => 'Seu perfil nao possui permissao para cancelar pedidos.',
                ], 403);
            }
            return $this->backWithError('Seu perfil nao possui permissao para cancelar pedidos.', '/admin/orders');
        }

        try {
            $this->service->updateStatus($companyId, $userId, $request->all());
            if ($expectsJson) {
                return $this->jsonResponse([
                    'ok' => true,
                    'message' => 'Status do pedido atualizado com sucesso.',
                ]);
            }
            return $this->backWithSuccess('Status do pedido atualizado com sucesso.', '/admin/orders');
        } catch (ValidationException $e) {
            if ($expectsJson) {
                return $this->jsonResponse([
                    'ok' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return $this->backWithError($e->getMessage(), '/admin/orders');
        }
    }

    public function sendToKitchen(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);
        $expectsJson = $this->expectsJson($request);

        try {
            $this->service->sendToKitchen($companyId, $userId, $request->all());
            if ($expectsJson) {
                return $this->jsonResponse([
                    'ok' => true,
                    'message' => 'Pedido enviado para cozinha.',
                ]);
            }
            return $this->backWithSuccess('Pedido enviado para cozinha.', '/admin/orders');
        } catch (ValidationException $e) {
            if ($expectsJson) {
                return $this->jsonResponse([
                    'ok' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return $this->backWithError($e->getMessage(), '/admin/orders');
        }
    }

    private function expectsJson(Request $request): bool
    {
        $requestedWith = strtolower((string) ($request->server['HTTP_X_REQUESTED_WITH'] ?? ''));
        $accept = strtolower((string) ($request->server['HTTP_ACCEPT'] ?? ''));

        return $requestedWith === 'xmlhttprequest' || str_contains($accept, 'application/json');
    }

    private function jsonResponse(array $payload, int $status = 200): Response
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{"ok":false,"message":"Falha ao serializar resposta JSON."}';
            $status = 500;
        }

        return Response::make($json, $status, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
