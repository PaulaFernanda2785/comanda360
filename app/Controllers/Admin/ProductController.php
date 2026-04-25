<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Repositories\PermissionRepository;
use App\Services\Admin\ProductService;

final class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $service = new ProductService(),
        private readonly PermissionRepository $permissions = new PermissionRepository()
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $panel = $this->service->panel($companyId);
        $roleId = (int) ($user['role_id'] ?? 0);

        return $this->view('admin/products/index', [
            'title' => 'Produtos',
            'user' => $user,
            'summary' => $panel['summary'] ?? [],
            'productTabs' => $panel['tabs'] ?? [],
            'categories' => $panel['categories'] ?? [],
            'productPlanLimit' => $panel['plan_limit'] ?? [],
            'canManageProducts' => $this->permissions->roleHasPermission($roleId, 'products.edit'),
            'canCreateProducts' => $this->permissions->roleHasPermission($roleId, 'products.create'),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);

        return $this->view('admin/products/create', [
            'title' => 'Novo Produto',
            'user' => $user,
            'categories' => $this->service->categories($companyId),
            'productPlanLimit' => $this->service->planLimit($companyId),
            'product' => null,
            'formAction' => base_url('/admin/products/store'),
            'submitLabel' => 'Salvar produto',
            'mode' => 'create',
        ]);
    }

    public function store(Request $request): Response
    {
        $guard = $this->guardSingleSubmit($request, 'products.store', '/admin/products/create');
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);

        $payload = $request->all();
        $payload['image_file'] = $request->files['image_file'] ?? null;

        try {
            $productId = $this->service->create($companyId, $payload);
            $nextAction = strtolower(trim((string) ($request->input('next_action', ''))));
            if ($nextAction === 'additionals') {
                return $this->backWithSuccess('Produto cadastrado com sucesso. Agora finalize os adicionais.', '/admin/products/additionals?product_id=' . $productId);
            }

            return $this->backWithSuccess('Produto cadastrado com sucesso.', '/admin/products');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/products/create');
        }
    }

    public function edit(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $productId = (int) ($request->input('product_id', 0));

        try {
            return $this->view('admin/products/create', [
                'title' => 'Editar Produto',
                'user' => $user,
                'categories' => $this->service->categories($companyId),
                'productPlanLimit' => $this->service->planLimit($companyId),
                'product' => $this->service->findForEdit($companyId, $productId),
                'formAction' => base_url('/admin/products/update'),
                'submitLabel' => 'Salvar alteracoes',
                'mode' => 'edit',
            ]);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/products');
        }
    }

    public function update(Request $request): Response
    {
        $productId = (int) ($request->input('product_id', 0));
        $guard = $this->guardSingleSubmit($request, 'products.update', '/admin/products/edit?product_id=' . $productId);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);

        $payload = $request->all();
        $payload['image_file'] = $request->files['image_file'] ?? null;

        try {
            $this->service->update($companyId, $productId, $payload);
            return $this->backWithSuccess('Produto atualizado com sucesso.', '/admin/products');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/products/edit?product_id=' . $productId);
        }
    }

    public function delete(Request $request): Response
    {
        $guard = $this->guardSingleSubmit($request, 'products.delete', '/admin/products');
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $productId = (int) ($request->input('product_id', 0));

        try {
            $this->service->delete($companyId, $productId);
            return $this->backWithSuccess('Produto removido com sucesso.', '/admin/products');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/products');
        }
    }

    public function removeImage(Request $request): Response
    {
        $productId = (int) ($request->input('product_id', 0));
        $guard = $this->guardSingleSubmit($request, 'products.image.remove', '/admin/products/edit?product_id=' . $productId);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->service->removeImage($companyId, $productId);
            return $this->backWithSuccess('Imagem removida com sucesso.', '/admin/products/edit?product_id=' . $productId);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/products/edit?product_id=' . $productId);
        }
    }

    public function storeCategory(Request $request): Response
    {
        $guard = $this->guardSingleSubmit($request, 'products.category.store', '/admin/products');
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->service->createCategory($companyId, $request->all());
            return $this->backWithSuccess('Categoria cadastrada com sucesso.', '/admin/products');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/products');
        }
    }

    public function updateCategory(Request $request): Response
    {
        $guard = $this->guardSingleSubmit($request, 'products.category.update', '/admin/products');
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $categoryId = (int) ($request->input('category_id', 0));

        try {
            $this->service->updateCategory($companyId, $categoryId, $request->all());
            return $this->backWithSuccess('Categoria atualizada com sucesso.', '/admin/products');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/products');
        }
    }

    public function deleteCategory(Request $request): Response
    {
        $guard = $this->guardSingleSubmit($request, 'products.category.delete', '/admin/products');
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $categoryId = (int) ($request->input('category_id', 0));

        try {
            $this->service->deleteCategory($companyId, $categoryId);
            return $this->backWithSuccess('Categoria removida com sucesso.', '/admin/products');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/products');
        }
    }

    public function additionals(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $productId = (int) ($request->input('product_id', 0));

        try {
            $context = $this->service->productAdditionalsContext($companyId, $productId);
            return $this->view('admin/products/additionals', [
                'title' => 'Adicionais do Produto',
                'user' => $user,
                'product' => $context['product'],
                'additionalGroup' => $context['group'],
                'additionalItems' => $context['items'],
            ]);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/products');
        }
    }

    public function updateAdditionalRules(Request $request): Response
    {
        $productId = (int) ($request->input('product_id', 0));
        $guard = $this->guardSingleSubmit($request, 'products.additionals.rules', '/admin/products/additionals?product_id=' . $productId);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->service->updateAdditionalRules($companyId, $productId, $request->all());
            return $this->backWithSuccess('Regras de adicionais atualizadas.', '/admin/products/additionals?product_id=' . $productId);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/products/additionals?product_id=' . $productId);
        }
    }

    public function storeAdditionalItem(Request $request): Response
    {
        $productId = (int) ($request->input('product_id', 0));
        $guard = $this->guardSingleSubmit($request, 'products.additionals.store', '/admin/products/additionals?product_id=' . $productId);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->service->addAdditionalItem($companyId, $productId, $request->all());
            return $this->backWithSuccess('Adicional cadastrado com sucesso.', '/admin/products/additionals?product_id=' . $productId);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/products/additionals?product_id=' . $productId);
        }
    }

    public function removeAdditionalItem(Request $request): Response
    {
        $productId = (int) ($request->input('product_id', 0));
        $guard = $this->guardSingleSubmit($request, 'products.additionals.remove', '/admin/products/additionals?product_id=' . $productId);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $additionalItemId = (int) ($request->input('additional_item_id', 0));

        try {
            $this->service->removeAdditionalItem($companyId, $productId, $additionalItemId);
            return $this->backWithSuccess('Adicional inativado com sucesso.', '/admin/products/additionals?product_id=' . $productId);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/products/additionals?product_id=' . $productId);
        }
    }

    public function updateAdditionalItem(Request $request): Response
    {
        $productId = (int) ($request->input('product_id', 0));
        $additionalItemId = (int) ($request->input('additional_item_id', 0));
        $guard = $this->guardSingleSubmit($request, 'products.additionals.update.' . $additionalItemId, '/admin/products/additionals?product_id=' . $productId);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->service->updateAdditionalItem($companyId, $productId, $additionalItemId, $request->all());
            return $this->backWithSuccess('Adicional atualizado com sucesso.', '/admin/products/additionals?product_id=' . $productId);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/products/additionals?product_id=' . $productId);
        }
    }

    public function updateAdditionalItemStatus(Request $request): Response
    {
        $productId = (int) ($request->input('product_id', 0));
        $additionalItemId = (int) ($request->input('additional_item_id', 0));
        $guard = $this->guardSingleSubmit($request, 'products.additionals.status.' . $additionalItemId, '/admin/products/additionals?product_id=' . $productId);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $status = (string) ($request->input('status', ''));

        try {
            $this->service->updateAdditionalItemStatus($companyId, $productId, $additionalItemId, $status);
            return $this->backWithSuccess(
                $status === 'ativo' ? 'Adicional ativado com sucesso.' : 'Adicional inativado com sucesso.',
                '/admin/products/additionals?product_id=' . $productId
            );
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/products/additionals?product_id=' . $productId);
        }
    }

    public function deleteAdditionalItem(Request $request): Response
    {
        $productId = (int) ($request->input('product_id', 0));
        $additionalItemId = (int) ($request->input('additional_item_id', 0));
        $guard = $this->guardSingleSubmit($request, 'products.additionals.delete.' . $additionalItemId, '/admin/products/additionals?product_id=' . $productId);
        if ($guard !== null) {
            return $guard;
        }

        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);

        try {
            $this->service->deleteAdditionalItem($companyId, $productId, $additionalItemId);
            return $this->backWithSuccess('Adicional excluido do banco de dados com sucesso.', '/admin/products/additionals?product_id=' . $productId);
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/products/additionals?product_id=' . $productId);
        }
    }
}
