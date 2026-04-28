<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Exceptions\ValidationException;
use App\Repositories\ProductRepository;
use PDOException;

final class ProductService
{
    private const MAX_IMAGE_SIZE_BYTES = 10485760; // 10MB

    public function __construct(
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly CompanyPlanLimitService $planLimits = new CompanyPlanLimitService(),
        private readonly StockAvailabilityService $stockAvailability = new StockAvailabilityService()
    ) {}

    public function list(int $companyId): array
    {
        return $this->products->allByCompany($companyId);
    }

    public function panel(int $companyId): array
    {
        $products = $this->list($companyId);
        $categories = $this->products->categoriesWithProductCountByCompany($companyId);

        $summary = [
            'total' => 0,
            'active' => 0,
            'paused' => 0,
            'featured' => 0,
            'with_additionals' => 0,
            'categories_total' => count($categories),
            'categories_active' => 0,
        ];

        $productsByCategoryId = [];
        foreach ($categories as $category) {
            $categoryId = (int) ($category['id'] ?? 0);
            if ($categoryId <= 0) {
                continue;
            }
            $productsByCategoryId[$categoryId] = [];
            if ((string) ($category['status'] ?? '') === 'ativo') {
                $summary['categories_active']++;
            }
        }

        foreach ($products as $product) {
            $summary['total']++;
            if ((int) ($product['is_active'] ?? 0) === 1 && (int) ($product['is_paused'] ?? 0) === 0) {
                $summary['active']++;
            }
            if ((int) ($product['is_paused'] ?? 0) === 1) {
                $summary['paused']++;
            }
            if ((int) ($product['is_featured'] ?? 0) === 1) {
                $summary['featured']++;
            }
            if ((int) ($product['has_additionals'] ?? 0) === 1) {
                $summary['with_additionals']++;
            }

            $categoryId = (int) ($product['category_id'] ?? 0);
            if (!isset($productsByCategoryId[$categoryId])) {
                $productsByCategoryId[$categoryId] = [];
            }
            $productsByCategoryId[$categoryId][] = $product;
        }

        $tabs = [];
        foreach ($categories as $category) {
            $categoryId = (int) ($category['id'] ?? 0);
            if ($categoryId <= 0) {
                continue;
            }

            $tabs[] = [
                'id' => $categoryId,
                'name' => (string) ($category['name'] ?? ''),
                'slug' => (string) ($category['slug'] ?? ''),
                'status' => (string) ($category['status'] ?? 'inativo'),
                'description' => (string) ($category['description'] ?? ''),
                'display_order' => (int) ($category['display_order'] ?? 0),
                'products_count' => (int) ($category['products_count'] ?? 0),
                'products' => $productsByCategoryId[$categoryId] ?? [],
            ];
        }

        return [
            'summary' => $summary,
            'tabs' => $tabs,
            'categories' => $categories,
            'plan_limit' => $this->planLimits->usageSummary($companyId, 'products', (int) ($summary['total'] ?? 0)),
        ];
    }

    public function categories(int $companyId): array
    {
        return $this->products->activeCategoriesByCompany($companyId);
    }

    public function categoriesForManagement(int $companyId): array
    {
        return $this->products->categoriesWithProductCountByCompany($companyId);
    }

    public function planLimit(int $companyId): array
    {
        return $this->planLimits->usageSummary($companyId, 'products', $this->products->countByCompany($companyId));
    }

    public function create(int $companyId, array $input): int
    {
        $this->planLimits->assertCanCreate($companyId, 'products', $this->products->countByCompany($companyId));
        $imageFile = $this->extractImageFileFromInput($input);
        $imagePath = $this->storeProductImage($companyId, $imageFile, $input);
        $data = $this->normalizeBaseProductInput($companyId, $input, $imagePath, null);
        $this->releaseDeletedProductSlugForCreation($companyId, (string) $data['slug']);

        try {
            return $this->products->create($data);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new ValidationException('Ja existe um produto usando este slug. Altere o slug e tente novamente.');
            }

            throw $e;
        }
    }

    public function findForEdit(int $companyId, int $productId): array
    {
        if ($productId <= 0) {
            throw new ValidationException('Produto invalido para edicao.');
        }

        $product = $this->products->findByIdForCompany($companyId, $productId);
        if ($product === null) {
            throw new ValidationException('Produto nao encontrado para a empresa autenticada.');
        }

        return $product;
    }

    public function update(int $companyId, int $productId, array $input): void
    {
        $existing = $this->findForEdit($companyId, $productId);
        $imageFile = $this->extractImageFileFromInput($input);

        $imagePath = $existing['image_path'] !== null ? (string) $existing['image_path'] : null;
        $newUploadedImagePath = null;

        if ($imageFile !== null) {
            $newUploadedImagePath = $this->storeProductImage($companyId, $imageFile, $input);
            $imagePath = $newUploadedImagePath;
        } else {
            $newUploadedImagePath = $this->storeProductImage($companyId, null, $input);
            if ($newUploadedImagePath !== null) {
                $imagePath = $newUploadedImagePath;
            }
        }

        $data = $this->normalizeBaseProductInput($companyId, $input, $imagePath, $productId);
        $this->releaseDeletedProductSlugForCreation($companyId, (string) $data['slug']);

        try {
            $this->products->updateById($companyId, $productId, $data);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new ValidationException('Ja existe um produto usando este slug. Altere o slug e tente novamente.');
            }

            throw $e;
        }

        $oldImagePath = $existing['image_path'] !== null ? (string) $existing['image_path'] : null;
        if ($oldImagePath !== null && $newUploadedImagePath !== null && $newUploadedImagePath !== $oldImagePath) {
            $this->deleteLocalUploadedImage($oldImagePath);
        }
    }

    public function delete(int $companyId, int $productId): void
    {
        $product = $this->findForEdit($companyId, $productId);
        $this->products->softDeleteById($companyId, $productId);

        $imagePath = $product['image_path'] !== null ? (string) $product['image_path'] : null;
        if ($imagePath !== null) {
            $this->deleteLocalUploadedImage($imagePath);
        }
    }

    public function removeImage(int $companyId, int $productId): void
    {
        $product = $this->findForEdit($companyId, $productId);
        $currentImagePath = $product['image_path'] !== null ? (string) $product['image_path'] : null;

        if ($currentImagePath === null || trim($currentImagePath) === '') {
            throw new ValidationException('Este produto nao possui imagem para remover.');
        }

        $this->products->setImagePathById($companyId, $productId, null);
        $this->deleteLocalUploadedImage($currentImagePath);
    }

    public function createCategory(int $companyId, array $input): int
    {
        $name = trim((string) ($input['name'] ?? ''));
        $slugRaw = trim((string) ($input['slug'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $displayOrder = max(0, (int) ($input['display_order'] ?? 0));
        $status = isset($input['status']) && (string) $input['status'] === 'inativo' ? 'inativo' : 'ativo';

        if ($name === '') {
            throw new ValidationException('Informe o nome da categoria.');
        }

        $slug = $this->slugify($slugRaw !== '' ? $slugRaw : $name);
        if ($slug === '') {
            throw new ValidationException('Slug da categoria invalido.');
        }

        if ($this->products->findCategoryBySlug($companyId, $slug) !== null) {
            throw new ValidationException('Ja existe categoria com este slug.');
        }

        return $this->products->createCategory([
            'company_id' => $companyId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description !== '' ? $description : null,
            'display_order' => $displayOrder,
            'status' => $status,
        ]);
    }

    public function updateCategory(int $companyId, int $categoryId, array $input): void
    {
        if ($categoryId <= 0) {
            throw new ValidationException('Categoria invalida para atualizacao.');
        }

        $existing = $this->products->findCategoryById($companyId, $categoryId);
        if ($existing === null) {
            throw new ValidationException('Categoria nao encontrada para a empresa autenticada.');
        }

        $name = trim((string) ($input['name'] ?? ''));
        $slugRaw = trim((string) ($input['slug'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $displayOrder = max(0, (int) ($input['display_order'] ?? 0));
        $status = isset($input['status']) && (string) $input['status'] === 'inativo' ? 'inativo' : 'ativo';

        if ($name === '') {
            throw new ValidationException('Informe o nome da categoria.');
        }

        $slug = $this->slugify($slugRaw !== '' ? $slugRaw : $name);
        if ($slug === '') {
            throw new ValidationException('Slug da categoria invalido.');
        }

        $slugOwner = $this->products->findCategoryBySlug($companyId, $slug);
        if ($slugOwner !== null && (int) ($slugOwner['id'] ?? 0) !== $categoryId) {
            throw new ValidationException('Ja existe categoria com este slug.');
        }

        $this->products->updateCategory($companyId, $categoryId, [
            'name' => $name,
            'slug' => $slug,
            'description' => $description !== '' ? $description : null,
            'display_order' => $displayOrder,
            'status' => $status,
        ]);
    }

    public function deleteCategory(int $companyId, int $categoryId): void
    {
        if ($categoryId <= 0) {
            throw new ValidationException('Categoria invalida para exclusao.');
        }

        $existing = $this->products->findCategoryById($companyId, $categoryId);
        if ($existing === null) {
            throw new ValidationException('Categoria nao encontrada para a empresa autenticada.');
        }

        $productsCount = $this->products->countProductsByCategory($companyId, $categoryId);
        if ($productsCount > 0) {
            throw new ValidationException('Nao e possivel excluir categoria com produtos vinculados. Edite os produtos antes.');
        }

        $this->products->softDeleteCategory($companyId, $categoryId);
    }

    public function productAdditionalsContext(int $companyId, int $productId): array
    {
        $product = $this->findForEdit($companyId, $productId);
        $group = $this->products->findProductAdditionalGroup($companyId, $productId);

        $items = [];
        if ($group !== null) {
            $items = $this->products->additionalItemsByGroup($companyId, (int) $group['id']);
        }

        return [
            'product' => $product,
            'group' => $group,
            'items' => $items,
        ];
    }

    public function updateAdditionalRules(int $companyId, int $productId, array $input): void
    {
        $product = $this->findForEdit($companyId, $productId);
        $group = $this->ensureProductAdditionalGroup($companyId, $product);

        $maxSelection = (int) ($input['max_selection'] ?? 0);
        if ($maxSelection < 1) {
            throw new ValidationException('Informe um limite maximo de adicionais por item maior ou igual a 1.');
        }

        $requiredMode = trim((string) ($input['rule_required_mode'] ?? ''));
        if ($requiredMode !== '') {
            $isRequired = $requiredMode === 'obrigatorio';
        } else {
            $isRequired = isset($input['is_required']);
        }

        $minSelection = $isRequired ? 1 : 0;
        if (array_key_exists('min_selection', $input) && trim((string) ($input['min_selection'] ?? '')) !== '') {
            $minSelection = (int) $input['min_selection'];
        }
        if ($minSelection < 0) {
            throw new ValidationException('A quantidade minima de adicionais nao pode ser negativa.');
        }
        if ($isRequired && $minSelection < 1) {
            $minSelection = 1;
        }
        if ($minSelection > $maxSelection) {
            throw new ValidationException('A quantidade minima nao pode ser maior que a maxima.');
        }

        $this->products->updateAdditionalGroupRules(
            $companyId,
            (int) $group['id'],
            $isRequired,
            $minSelection,
            $maxSelection
        );

        $this->products->setHasAdditionals($companyId, $productId, true);
    }

    public function addAdditionalItem(int $companyId, int $productId, array $input): int
    {
        $product = $this->findForEdit($companyId, $productId);
        $group = $this->ensureProductAdditionalGroup($companyId, $product);
        $data = $this->normalizeAdditionalItemInput($input);

        $additionalItemId = $this->products->createAdditionalItem([
            'company_id' => $companyId,
            'additional_group_id' => (int) $group['id'],
            'name' => $data['name'],
            'description' => $data['description'],
            'price' => $data['price'],
            'display_order' => $data['display_order'],
        ]);

        $this->products->setHasAdditionals($companyId, $productId, true);
        return $additionalItemId;
    }

    public function removeAdditionalItem(int $companyId, int $productId, int $additionalItemId): void
    {
        $this->updateAdditionalItemStatus($companyId, $productId, $additionalItemId, 'inativo');
    }

    public function updateAdditionalItemStatus(int $companyId, int $productId, int $additionalItemId, string $status): void
    {
        $this->assertAdditionalItemBelongsToProduct($companyId, $productId, $additionalItemId);

        $normalizedStatus = strtolower(trim($status));
        if (!in_array($normalizedStatus, ['ativo', 'inativo'], true)) {
            throw new ValidationException('Status invalido para o adicional.');
        }

        $this->products->updateAdditionalItemStatus($companyId, $additionalItemId, $normalizedStatus);
        $this->syncProductHasAdditionalsFlag($companyId, $productId);
    }

    public function deleteAdditionalItem(int $companyId, int $productId, int $additionalItemId): void
    {
        $this->assertAdditionalItemBelongsToProduct($companyId, $productId, $additionalItemId);

        if ($this->products->countOrderUsageByAdditionalItem($companyId, $additionalItemId) > 0) {
            throw new ValidationException('Este adicional ja foi usado em pedidos. Para preservar o historico, inative o adicional em vez de excluir.');
        }

        $this->products->deleteAdditionalItem($companyId, $additionalItemId);
        $this->syncProductHasAdditionalsFlag($companyId, $productId);
    }

    public function updateAdditionalItem(int $companyId, int $productId, int $additionalItemId, array $input): void
    {
        $this->assertAdditionalItemBelongsToProduct($companyId, $productId, $additionalItemId);
        $data = $this->normalizeAdditionalItemInput($input);

        $this->products->updateAdditionalItem($companyId, $additionalItemId, $data);
        $this->syncProductHasAdditionalsFlag($companyId, $productId);
    }

    public function listForOrderForm(int $companyId): array
    {
        $products = $this->products->activeForOrderByCompany($companyId);
        if ($products === []) {
            return [];
        }

        $productIds = array_values(array_unique(array_map(
            static fn (array $product): int => (int) ($product['id'] ?? 0),
            $products
        )));
        $catalogRows = $this->products->activeAdditionalCatalogByProductIds($companyId, $productIds);

        $additionalsByProduct = [];
        foreach ($catalogRows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            if (!isset($additionalsByProduct[$productId])) {
                $maxSelection = $row['max_selection'] !== null ? (int) $row['max_selection'] : null;
                $minSelection = $row['min_selection'] !== null ? (int) $row['min_selection'] : null;

                $additionalsByProduct[$productId] = [
                    'is_required' => ((int) ($row['is_required'] ?? 0)) === 1,
                    'min_selection' => $minSelection,
                    'max_selection' => $maxSelection,
                    'items' => [],
                ];
            }

            $additionalItemId = (int) ($row['additional_item_id'] ?? 0);
            if ($additionalItemId <= 0) {
                continue;
            }

            $additionalsByProduct[$productId]['items'][] = [
                'id' => $additionalItemId,
                'name' => (string) ($row['additional_name'] ?? ''),
                'price' => (float) ($row['additional_price'] ?? 0),
            ];
        }

        foreach ($products as &$product) {
            $productId = (int) ($product['id'] ?? 0);
            $additionalConfig = $additionalsByProduct[$productId] ?? null;

            $product['additionals'] = $additionalConfig['items'] ?? [];
            $product['additionals_is_required'] = $additionalConfig['is_required'] ?? false;
            $product['additionals_min_selection'] = $additionalConfig['min_selection'] ?? null;
            $product['additionals_max_selection'] = $additionalConfig['max_selection'] ?? null;
            $product['has_additionals'] = !empty($product['additionals']) ? 1 : (int) ($product['has_additionals'] ?? 0);
        }
        unset($product);

        return $this->stockAvailability->enrichProducts($companyId, $products);
    }

    private function ensureProductAdditionalGroup(int $companyId, array $product): array
    {
        $productId = (int) ($product['id'] ?? 0);
        $existing = $this->products->findProductAdditionalGroup($companyId, $productId);
        if ($existing !== null) {
            return $existing;
        }

        $groupName = substr('Adicionais - ' . trim((string) ($product['name'] ?? 'Produto')), 0, 120);
        $additionalGroupId = $this->products->createAdditionalGroup([
            'company_id' => $companyId,
            'name' => $groupName,
            'description' => null,
            'is_required' => 0,
            'min_selection' => 0,
            'max_selection' => 1,
        ]);

        $this->products->linkProductToAdditionalGroup($companyId, $productId, $additionalGroupId);

        $created = $this->products->findProductAdditionalGroup($companyId, $productId);
        if ($created === null) {
            throw new ValidationException('Nao foi possivel inicializar o grupo de adicionais do produto.');
        }

        return $created;
    }

    private function assertAdditionalItemBelongsToProduct(int $companyId, int $productId, int $additionalItemId): array
    {
        $this->findForEdit($companyId, $productId);

        if ($additionalItemId <= 0) {
            throw new ValidationException('Adicional invalido.');
        }

        $additional = $this->products->findAdditionalItemByIdForProduct($companyId, $productId, $additionalItemId);
        if ($additional === null) {
            throw new ValidationException('Adicional nao encontrado para este produto.');
        }

        return $additional;
    }

    private function normalizeAdditionalItemInput(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new ValidationException('Informe o nome do adicional.');
        }

        $price = $this->parseMoney($input['price'] ?? 0);
        if ($price < 0) {
            throw new ValidationException('O valor do adicional nao pode ser negativo.');
        }

        $description = trim((string) ($input['description'] ?? ''));
        $displayOrder = (int) ($input['display_order'] ?? 0);
        if ($displayOrder < 0) {
            $displayOrder = 0;
        }

        return [
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'price' => $price,
            'display_order' => $displayOrder,
        ];
    }

    private function syncProductHasAdditionalsFlag(int $companyId, int $productId): void
    {
        $group = $this->products->findProductAdditionalGroup($companyId, $productId);
        $hasItems = $group !== null && $this->products->countAdditionalItemsByGroup($companyId, (int) $group['id']) > 0;
        $this->products->setHasAdditionals($companyId, $productId, $hasItems);
    }

    private function releaseDeletedProductSlugForCreation(int $companyId, string $slug): void
    {
        $deletedProduct = $this->products->findDeletedProductBySlug($companyId, $slug);
        if ($deletedProduct === null) {
            return;
        }

        $deletedProductId = (int) ($deletedProduct['id'] ?? 0);
        if ($deletedProductId <= 0) {
            return;
        }

        $this->products->archiveDeletedProductSlug(
            $companyId,
            $deletedProductId,
            $this->archivedDeletedProductSlug($companyId, $slug, $deletedProductId)
        );
    }

    private function archivedDeletedProductSlug(int $companyId, string $slug, int $productId): string
    {
        $attempt = 0;
        do {
            $suffix = '-excluido-' . $productId . ($attempt > 0 ? '-' . $attempt : '');
            $base = substr($slug, 0, max(1, 150 - strlen($suffix)));
            $candidate = rtrim($base, '-') . $suffix;
            $owner = $this->products->findAnyProductBySlug($companyId, $candidate);
            $attempt++;
        } while ($owner !== null && (int) ($owner['id'] ?? 0) !== $productId && $attempt < 25);

        if ($owner !== null && (int) ($owner['id'] ?? 0) !== $productId) {
            throw new ValidationException('Nao foi possivel liberar o slug do produto excluido. Altere o slug e tente novamente.');
        }

        return $candidate;
    }

    private function normalizeBaseProductInput(int $companyId, array $input, ?string $imagePath, ?int $currentProductId): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $slug = trim((string) ($input['slug'] ?? ''));
        $categoryId = (int) ($input['category_id'] ?? 0);
        $price = $this->parseMoney($input['price'] ?? 0);
        $promotionalPriceRaw = trim((string) ($input['promotional_price'] ?? ''));

        if ($name === '') {
            throw new ValidationException('Informe o nome do produto.');
        }

        if ($slug === '') {
            throw new ValidationException('Informe o slug do produto.');
        }

        if ($categoryId <= 0) {
            throw new ValidationException('Selecione uma categoria valida.');
        }

        $categoryExists = false;
        foreach ($this->products->activeCategoriesByCompany($companyId) as $category) {
            if ((int) ($category['id'] ?? 0) === $categoryId) {
                $categoryExists = true;
                break;
            }
        }
        if (!$categoryExists) {
            throw new ValidationException('Categoria invalida para a empresa autenticada.');
        }

        if ($price < 0) {
            throw new ValidationException('O preco nao pode ser negativo.');
        }

        $promotionalPrice = null;
        if ($promotionalPriceRaw !== '') {
            $promotionalPrice = $this->parseMoney($promotionalPriceRaw);
            if ($promotionalPrice < 0) {
                throw new ValidationException('O preco promocional nao pode ser negativo.');
            }
        }

        $displayOrder = (int) ($input['display_order'] ?? 0);
        if ($displayOrder < 0) {
            $displayOrder = 0;
        }

        $description = trim((string) ($input['description'] ?? ''));
        $sku = trim((string) ($input['sku'] ?? ''));
        $operationalStatus = trim((string) ($input['operational_status'] ?? ''));

        $isActive = isset($input['is_active']) ? 1 : 0;
        $isPaused = isset($input['is_paused']) ? 1 : 0;
        if ($operationalStatus === 'ativo') {
            $isActive = 1;
            $isPaused = 0;
        } elseif ($operationalStatus === 'pausado') {
            $isActive = 1;
            $isPaused = 1;
        } elseif ($operationalStatus === 'inativo') {
            $isActive = 0;
            $isPaused = 0;
        }

        $normalizedSlug = $this->slugify($slug);
        if ($normalizedSlug === '') {
            throw new ValidationException('Slug do produto invalido.');
        }

        $slugOwner = $this->products->findProductBySlug($companyId, $normalizedSlug);
        if ($slugOwner !== null && (int) ($slugOwner['id'] ?? 0) !== (int) ($currentProductId ?? 0)) {
            throw new ValidationException('Ja existe produto com este slug.');
        }

        return [
            'company_id' => $companyId,
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => $normalizedSlug,
            'description' => $description !== '' ? $description : null,
            'sku' => $sku !== '' ? $sku : null,
            'image_path' => $this->normalizeImagePathForStorage($imagePath),
            'price' => $price,
            'promotional_price' => $promotionalPrice,
            'is_featured' => isset($input['is_featured']) ? 1 : 0,
            'is_active' => $isActive,
            'is_paused' => $isPaused,
            'allows_notes' => isset($input['allows_notes']) ? 1 : 0,
            'has_additionals' => isset($input['has_additionals']) ? 1 : 0,
            'display_order' => $displayOrder,
        ];
    }

    private function extractImageFileFromInput(array $input): ?array
    {
        $file = $input['image_file'] ?? null;
        if (!is_array($file)) {
            return null;
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    private function storeProductImage(int $companyId, ?array $file, array $input = []): ?string
    {
        if ($file === null) {
            return $this->storeProductImageFromBase64($companyId, $input);
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new ValidationException($this->uploadErrorMessage($error));
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $isUploadedFile = $tmpName !== '' && is_uploaded_file($tmpName);
        $isLocalEnv = strtolower((string) getenv('APP_ENV')) === 'local';
        if (!$isUploadedFile && !($isLocalEnv && $tmpName !== '' && is_file($tmpName))) {
            throw new ValidationException('Arquivo de imagem invalido.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_IMAGE_SIZE_BYTES) {
            throw new ValidationException('A imagem deve ter ate 10MB.');
        }

        $imageInfo = @getimagesize($tmpName);
        if (!is_array($imageInfo) || empty($imageInfo['mime'])) {
            throw new ValidationException('Arquivo enviado nao e uma imagem valida.');
        }

        $extension = $this->imageExtensionFromMime((string) $imageInfo['mime']);
        $target = $this->prepareProductImageTargetPath($companyId, $extension);

        $moved = move_uploaded_file($tmpName, $target['absolute']);
        if (!$moved && $isLocalEnv && is_file($tmpName)) {
            $moved = @rename($tmpName, $target['absolute']);
            if (!$moved) {
                $moved = @copy($tmpName, $target['absolute']);
                if ($moved && is_file($tmpName)) {
                    @unlink($tmpName);
                }
            }
        }

        if (!$moved) {
            throw new ValidationException('Nao foi possivel salvar a imagem enviada.');
        }

        return $target['relative'];
    }

    private function storeProductImageFromBase64(int $companyId, array $input): ?string
    {
        $base64 = trim((string) ($input['image_data_base64'] ?? ''));
        if ($base64 === '') {
            return null;
        }

        if (!preg_match('#^data:image/([a-zA-Z0-9.+-]+);base64,#', $base64, $matches)) {
            throw new ValidationException('Formato de imagem invalido enviado pelo formulario.');
        }

        $payload = substr($base64, (int) strpos($base64, ',') + 1);
        $binary = base64_decode($payload, true);
        if ($binary === false || $binary === '') {
            throw new ValidationException('Falha ao decodificar a imagem enviada.');
        }

        $size = strlen($binary);
        if ($size <= 0 || $size > self::MAX_IMAGE_SIZE_BYTES) {
            throw new ValidationException('A imagem deve ter ate 10MB.');
        }

        $imageInfo = @getimagesizefromstring($binary);
        if (!is_array($imageInfo) || empty($imageInfo['mime'])) {
            throw new ValidationException('Conteudo enviado nao e uma imagem valida.');
        }

        $extension = $this->imageExtensionFromMime((string) $imageInfo['mime']);
        $target = $this->prepareProductImageTargetPath($companyId, $extension);
        if (@file_put_contents($target['absolute'], $binary) === false) {
            throw new ValidationException('Nao foi possivel salvar a imagem enviada.');
        }

        return $target['relative'];
    }

    private function deleteLocalUploadedImage(string $imagePath): void
    {
        $normalizedPath = '/' . ltrim(str_replace('\\', '/', trim($imagePath)), '/');
        if (str_starts_with($normalizedPath, '/public/')) {
            $normalizedPath = '/' . ltrim(substr($normalizedPath, strlen('/public/')), '/');
        }

        if (!$this->isAllowedProductImagePath($normalizedPath)) {
            return;
        }

        $absolutePath = BASE_PATH . '/public' . $normalizedPath;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function imageExtensionFromMime(string $mime): string
    {
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        $normalizedMime = strtolower(trim($mime));
        if (!isset($extensions[$normalizedMime])) {
            throw new ValidationException('Formato de imagem nao suportado. Use JPG, PNG, WEBP ou GIF.');
        }

        return $extensions[$normalizedMime];
    }

    private function prepareProductImageTargetPath(int $companyId, string $extension): array
    {
        $baseDir = BASE_PATH . '/public/uploads/company/' . $companyId . '/products';
        if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
            throw new ValidationException('Nao foi possivel preparar pasta de upload.');
        }

        $filename = 'prd_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $extension;

        return [
            'absolute' => $baseDir . '/' . $filename,
            'relative' => '/uploads/company/' . $companyId . '/products/' . $filename,
        ];
    }

    private function isAllowedProductImagePath(string $path): bool
    {
        if (str_starts_with($path, '/uploads/products/')) {
            return true;
        }

        return preg_match('#^/uploads/company/\d+/products/#', $path) === 1;
    }

    private function normalizeImagePathForStorage(?string $path): ?string
    {
        $value = trim((string) ($path ?? ''));
        if ($value === '') {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $value) === 1) {
            return $value;
        }

        $normalized = '/' . ltrim(str_replace('\\', '/', $value), '/');
        if (str_starts_with($normalized, '/public/')) {
            $normalized = '/' . ltrim(substr($normalized, strlen('/public/')), '/');
        }

        return $normalized;
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A imagem excede o limite permitido pelo servidor. Tente um arquivo menor que 10MB.',
            UPLOAD_ERR_PARTIAL => 'O upload da imagem foi interrompido. Tente novamente.',
            UPLOAD_ERR_NO_TMP_DIR => 'Servidor sem pasta temporaria para upload de imagem.',
            UPLOAD_ERR_CANT_WRITE => 'Servidor sem permissao para gravar o upload da imagem.',
            UPLOAD_ERR_EXTENSION => 'Uma extensao do PHP bloqueou o upload da imagem.',
            default => 'Falha no envio da imagem do produto.',
        };
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        $search = ['á', 'à', 'â', 'ã', 'ä', 'å', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'ô', 'õ', 'ö', 'ú', 'ù', 'û', 'ü', 'ç', 'ñ'];
        $replace = ['a', 'a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c', 'n'];
        $value = str_replace($search, $replace, $value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value;
    }

    private function parseMoney(mixed $value): float
    {
        if (is_float($value) || is_int($value)) {
            return round((float) $value, 2);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }

        $normalized = str_replace(',', '.', $raw);
        if (!is_numeric($normalized)) {
            throw new ValidationException('Valor monetario invalido informado.');
        }

        return round((float) $normalized, 2);
    }
}
