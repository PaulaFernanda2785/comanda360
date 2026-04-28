<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Repositories\PermissionRepository;
use App\Services\Admin\StockService;

final class StockController extends Controller
{
    public function __construct(
        private readonly StockService $service = new StockService(),
        private readonly PermissionRepository $permissions = new PermissionRepository()
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user() ?? [];
        $companyId = (int) ($user['company_id'] ?? 0);
        $roleId = (int) ($user['role_id'] ?? 0);

        return $this->view('admin/stock/index', [
            'title' => 'Estoque',
            'user' => $user,
            'stockPanel' => $this->service->panel($companyId, $request->query),
            'canManageStock' => $this->permissions->roleHasPermission($roleId, 'stock.manage'),
        ]);
    }

    public function storeItem(Request $request): Response
    {
        $redirectTo = '/admin/stock';
        $guard = $this->guardSingleSubmit($request, 'stock.items.store', $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $companyId = (int) ($user['company_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);

        try {
            $this->service->createItem($companyId, $userId, $request->all());
            return $this->backWithSuccess('Item de estoque cadastrado com sucesso.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function updateItem(Request $request): Response
    {
        $itemId = (int) ($request->input('stock_item_id', 0));
        $redirectTo = $this->resolveStockRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'stock.items.update.' . $itemId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->service->updateItem($companyId, $itemId, $request->all());
            return $this->backWithSuccess('Item de estoque atualizado com sucesso.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function storeMovement(Request $request): Response
    {
        $itemId = (int) ($request->input('stock_item_id', 0));
        $redirectTo = $this->resolveStockRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'stock.movements.store.' . $itemId, $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $companyId = (int) ($user['company_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);

        try {
            $this->service->recordMovement($companyId, $userId, $request->all());
            return $this->backWithSuccess('Movimentacao registrada com sucesso no estoque.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    public function updateRecipe(Request $request): Response
    {
        $redirectTo = $this->resolveStockRedirect($request);
        $guard = $this->guardSingleSubmit($request, 'stock.recipes.update', $redirectTo);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user() ?? [];
        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->service->updateRecipe($companyId, $request->all());
            return $this->backWithSuccess('Ficha tecnica atualizada com sucesso.', $redirectTo);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), $redirectTo);
        }
    }

    private function resolveStockRedirect(Request $request): string
    {
        $default = '/admin/stock';
        $queryRaw = trim((string) ($request->input('return_query', '')));
        if ($queryRaw === '') {
            return $default;
        }

        parse_str($queryRaw, $params);
        if (!is_array($params)) {
            return $default;
        }

        $allowedKeys = [
            'stock_search',
            'stock_status',
            'stock_alert',
            'stock_page',
            'stock_movement_search',
            'stock_movement_type',
            'stock_movement_page',
            'stock_auto_search',
            'stock_auto_issue',
            'stock_auto_page',
        ];

        $safe = [];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $params)) {
                $safe[$key] = (string) $params[$key];
            }
        }

        $query = http_build_query($safe);
        return '/admin/stock' . ($query !== '' ? '?' . $query : '');
    }
}
