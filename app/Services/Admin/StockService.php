<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Exceptions\ValidationException;
use App\Repositories\ProductRepository;
use App\Repositories\StockRepository;
use Throwable;

final class StockService
{
    private const ITEMS_PER_PAGE = 10;
    private const MOVEMENTS_PER_PAGE = 10;

    private const ALLOWED_ITEM_STATUS = [
        'ativo',
        'inativo',
    ];

    private const ALLOWED_ALERT_FILTERS = [
        'low',
        'out',
    ];

    private const ALLOWED_MOVEMENT_TYPES = [
        'entry',
        'exit',
        'adjustment',
    ];

    private const ALLOWED_REFERENCE_TYPES = [
        'manual',
        'purchase',
        'consumption',
        'inventory_count',
        'waste',
        'production',
    ];

    private const UNIT_OPTIONS = [
        'un',
        'kg',
        'g',
        'l',
        'ml',
        'cx',
        'pct',
        'fardo',
    ];

    public function __construct(
        private readonly StockRepository $stock = new StockRepository(),
        private readonly ProductRepository $products = new ProductRepository()
    ) {}

    public function panel(int $companyId, array $filters): array
    {
        if ($companyId <= 0) {
            throw new ValidationException('Empresa invalida para carregar o estoque.');
        }

        $normalized = $this->normalizeFilters($filters);
        $itemsPage = $this->stock->listItemsPaginated(
            $companyId,
            [
                'search' => $normalized['search'],
                'status' => $normalized['status'],
                'alert' => $normalized['alert'],
            ],
            $normalized['page'],
            self::ITEMS_PER_PAGE
        );
        $movementsPage = $this->stock->listMovementsPaginated(
            $companyId,
            [
                'search' => $normalized['movement_search'],
                'type' => $normalized['movement_type'],
            ],
            $normalized['movement_page'],
            self::MOVEMENTS_PER_PAGE
        );

        return [
            'items' => is_array($itemsPage['items'] ?? null) ? $itemsPage['items'] : [],
            'movements' => is_array($movementsPage['items'] ?? null) ? $movementsPage['items'] : [],
            'summary' => $this->stock->summary($companyId),
            'filters' => $normalized,
            'item_pagination' => $this->buildPaginationPayload($itemsPage),
            'movement_pagination' => $this->buildPaginationPayload($movementsPage),
            'products' => $this->stock->listProductsForLink($companyId),
            'status_options' => self::ALLOWED_ITEM_STATUS,
            'alert_options' => self::ALLOWED_ALERT_FILTERS,
            'movement_type_options' => self::ALLOWED_MOVEMENT_TYPES,
            'reference_type_options' => self::ALLOWED_REFERENCE_TYPES,
            'unit_options' => self::UNIT_OPTIONS,
        ];
    }

    public function createItem(int $companyId, int $userId, array $input): int
    {
        if ($userId <= 0) {
            throw new ValidationException('Usuario invalido para cadastrar item de estoque.');
        }

        $payload = $this->normalizeItemPayload($companyId, $input, null);
        $initialQuantity = $this->parseDecimal($input['initial_quantity'] ?? 0, 'saldo inicial', true);

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $itemId = $this->stock->createItem([
                'company_id' => $companyId,
                'product_id' => $payload['product_id'],
                'name' => $payload['name'],
                'sku' => $payload['sku'],
                'current_quantity' => round($initialQuantity, 3),
                'minimum_quantity' => $payload['minimum_quantity'],
                'unit_of_measure' => $payload['unit_of_measure'],
                'status' => $payload['status'],
            ]);

            $this->stock->syncItemProducts($companyId, $itemId, $payload['product_ids']);

            if ($initialQuantity > 0) {
                $this->stock->createMovement([
                    'company_id' => $companyId,
                    'stock_item_id' => $itemId,
                    'type' => 'entry',
                    'quantity' => round($initialQuantity, 3),
                    'reason' => 'Saldo inicial do cadastro',
                    'reference_type' => 'manual',
                    'reference_id' => null,
                    'moved_by_user_id' => $userId,
                    'moved_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $db->commit();
            return $itemId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function updateItem(int $companyId, int $itemId, array $input): void
    {
        if ($itemId <= 0) {
            throw new ValidationException('Item de estoque invalido para edicao.');
        }

        $existing = $this->stock->findItemById($companyId, $itemId);
        if ($existing === null) {
            throw new ValidationException('Item de estoque nao encontrado para a empresa autenticada.');
        }

        $payload = $this->normalizeItemPayload($companyId, $input, $existing);

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $this->stock->updateItem($companyId, $itemId, $payload);
            $this->stock->syncItemProducts($companyId, $itemId, $payload['product_ids']);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function recordMovement(int $companyId, int $userId, array $input): void
    {
        if ($userId <= 0) {
            throw new ValidationException('Usuario invalido para movimentar estoque.');
        }

        $itemId = (int) ($input['stock_item_id'] ?? 0);
        if ($itemId <= 0) {
            throw new ValidationException('Selecione um item valido para movimentacao.');
        }

        $item = $this->stock->findItemById($companyId, $itemId);
        if ($item === null) {
            throw new ValidationException('Item de estoque nao encontrado para movimentacao.');
        }

        $type = strtolower(trim((string) ($input['movement_type'] ?? '')));
        if (!in_array($type, self::ALLOWED_MOVEMENT_TYPES, true)) {
            throw new ValidationException('Tipo de movimentacao invalido.');
        }

        $currentQuantity = round((float) ($item['current_quantity'] ?? 0), 3);
        $movedQuantity = 0.0;
        $nextQuantity = $currentQuantity;

        if ($type === 'adjustment') {
            $targetQuantity = $this->parseDecimal($input['target_quantity'] ?? null, 'saldo alvo', true);
            if ($targetQuantity < 0) {
                throw new ValidationException('O saldo alvo do ajuste nao pode ser negativo.');
            }

            $movedQuantity = round(abs($targetQuantity - $currentQuantity), 3);
            if ($movedQuantity <= 0) {
                throw new ValidationException('O ajuste informado nao altera o saldo atual.');
            }

            $nextQuantity = round($targetQuantity, 3);
        } else {
            $quantity = $this->parseDecimal($input['quantity'] ?? null, 'quantidade', false);
            $movedQuantity = round($quantity, 3);

            if ($type === 'entry') {
                $nextQuantity = round($currentQuantity + $movedQuantity, 3);
            } else {
                if ($movedQuantity > $currentQuantity) {
                    throw new ValidationException('A saida informada e maior que o saldo disponivel do item.');
                }

                $nextQuantity = round($currentQuantity - $movedQuantity, 3);
            }
        }

        $referenceType = strtolower(trim((string) ($input['reference_type'] ?? 'manual')));
        if (!in_array($referenceType, self::ALLOWED_REFERENCE_TYPES, true)) {
            $referenceType = 'manual';
        }

        $reason = $this->nullableTrim($input['reason'] ?? null);
        if ($reason !== null && strlen($reason) > 255) {
            throw new ValidationException('O motivo do movimento excede o limite permitido.');
        }

        if ($type === 'adjustment' && $reason === null) {
            $reason = 'Ajuste de inventario para saldo alvo ' . number_format($nextQuantity, 3, ',', '.') . ' ' . (string) ($item['unit_of_measure'] ?? 'un');
        }

        $referenceId = $this->normalizeNullableInteger($input['reference_id'] ?? null);

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $this->stock->updateCurrentQuantity($companyId, $itemId, $nextQuantity);
            $this->stock->createMovement([
                'company_id' => $companyId,
                'stock_item_id' => $itemId,
                'type' => $type,
                'quantity' => $movedQuantity,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'moved_by_user_id' => $userId,
                'moved_at' => date('Y-m-d H:i:s'),
            ]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function normalizeFilters(array $filters): array
    {
        $search = trim((string) ($filters['stock_search'] ?? ''));
        if (strlen($search) > 80) {
            $search = substr($search, 0, 80);
        }

        $status = strtolower(trim((string) ($filters['stock_status'] ?? '')));
        if ($status !== '' && !in_array($status, self::ALLOWED_ITEM_STATUS, true)) {
            $status = '';
        }

        $alert = strtolower(trim((string) ($filters['stock_alert'] ?? '')));
        if ($alert !== '' && !in_array($alert, self::ALLOWED_ALERT_FILTERS, true)) {
            $alert = '';
        }

        $movementSearch = trim((string) ($filters['stock_movement_search'] ?? ''));
        if (strlen($movementSearch) > 80) {
            $movementSearch = substr($movementSearch, 0, 80);
        }

        $movementType = strtolower(trim((string) ($filters['stock_movement_type'] ?? '')));
        if ($movementType !== '' && !in_array($movementType, self::ALLOWED_MOVEMENT_TYPES, true)) {
            $movementType = '';
        }

        $page = max(1, (int) ($filters['stock_page'] ?? 1));
        $movementPage = max(1, (int) ($filters['stock_movement_page'] ?? 1));

        return [
            'search' => $search,
            'status' => $status,
            'alert' => $alert,
            'movement_search' => $movementSearch,
            'movement_type' => $movementType,
            'page' => $page,
            'movement_page' => $movementPage,
        ];
    }

    private function normalizeItemPayload(int $companyId, array $input, ?array $existing): array
    {
        $name = trim((string) ($input['name'] ?? ($existing['name'] ?? '')));
        if ($name === '') {
            throw new ValidationException('Informe o nome do item de estoque.');
        }

        if (strlen($name) > 150) {
            throw new ValidationException('O nome do item de estoque excede o limite permitido.');
        }

        $productIds = $this->normalizeProductIds($companyId, $input['product_ids'] ?? null, $existing);

        $sku = $this->nullableTrim($input['sku'] ?? ($existing['sku'] ?? null));
        if ($sku !== null) {
            $sku = strtoupper($sku);
            if (strlen($sku) > 60) {
                throw new ValidationException('O SKU do item de estoque excede o limite permitido.');
            }

            $owner = $this->stock->findBySku($companyId, $sku, $existing !== null ? (int) ($existing['id'] ?? 0) : null);
            if ($owner !== null) {
                throw new ValidationException('Ja existe item de estoque com este SKU na empresa.');
            }
        }

        $minimumQuantity = $this->parseNullableDecimal($input['minimum_quantity'] ?? ($existing['minimum_quantity'] ?? null), 'estoque minimo');
        if ($minimumQuantity !== null && $minimumQuantity < 0) {
            throw new ValidationException('O estoque minimo nao pode ser negativo.');
        }

        $unit = strtolower(trim((string) ($input['unit_of_measure'] ?? ($existing['unit_of_measure'] ?? 'un'))));
        if (!in_array($unit, self::UNIT_OPTIONS, true)) {
            throw new ValidationException('Informe uma unidade de medida valida para o item.');
        }

        $status = strtolower(trim((string) ($input['status'] ?? ($existing['status'] ?? 'ativo'))));
        if (!in_array($status, self::ALLOWED_ITEM_STATUS, true)) {
            throw new ValidationException('Status invalido para o item de estoque.');
        }

        return [
            'product_id' => $productIds !== [] ? (int) $productIds[0] : null,
            'product_ids' => $productIds,
            'name' => $name,
            'sku' => $sku,
            'minimum_quantity' => $minimumQuantity,
            'unit_of_measure' => $unit,
            'status' => $status,
        ];
    }

    private function normalizeProductIds(int $companyId, mixed $rawProductIds, ?array $existing): array
    {
        if ($rawProductIds === null && $existing !== null) {
            $rawProductIds = $this->productIdsFromExisting($existing);
        }

        if ($rawProductIds === null || $rawProductIds === '') {
            return [];
        }

        $values = is_array($rawProductIds) ? $rawProductIds : [$rawProductIds];
        $productIds = [];

        foreach ($values as $value) {
            $raw = trim((string) $value);
            if ($raw === '' || !ctype_digit($raw)) {
                continue;
            }

            $productId = (int) $raw;
            if ($productId > 0) {
                $productIds[$productId] = true;
            }
        }

        $normalized = array_keys($productIds);
        foreach ($normalized as $productId) {
            if ($this->products->findByIdForCompany($companyId, (int) $productId) === null) {
                throw new ValidationException('Produto vinculado invalido para este item de estoque.');
            }
        }

        sort($normalized);
        return array_map('intval', $normalized);
    }

    private function productIdsFromExisting(array $existing): array
    {
        $raw = trim((string) ($existing['linked_product_ids'] ?? ''));
        if ($raw === '') {
            $legacyProductId = (int) ($existing['product_id'] ?? 0);
            return $legacyProductId > 0 ? [$legacyProductId] : [];
        }

        $ids = [];
        foreach (explode(',', $raw) as $value) {
            $value = trim($value);
            if ($value !== '' && ctype_digit($value)) {
                $ids[(int) $value] = true;
            }
        }

        $result = array_keys($ids);
        sort($result);

        return array_map('intval', $result);
    }

    private function parseDecimal(mixed $value, string $label, bool $allowZero): float
    {
        $raw = str_replace(',', '.', trim((string) ($value ?? '')));
        if ($raw === '' || !is_numeric($raw)) {
            throw new ValidationException('Informe um valor valido para ' . $label . '.');
        }

        $parsed = round((float) $raw, 3);
        if ($allowZero ? $parsed < 0 : $parsed <= 0) {
            throw new ValidationException('O valor informado para ' . $label . ' nao e valido.');
        }

        return $parsed;
    }

    private function parseNullableDecimal(mixed $value, string $label): ?float
    {
        $raw = str_replace(',', '.', trim((string) ($value ?? '')));
        if ($raw === '') {
            return null;
        }

        if (!is_numeric($raw)) {
            throw new ValidationException('Informe um valor valido para ' . $label . '.');
        }

        return round((float) $raw, 3);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        if (!ctype_digit($raw)) {
            return null;
        }

        $parsed = (int) $raw;
        return $parsed > 0 ? $parsed : null;
    }

    private function buildPaginationPayload(array $page): array
    {
        $total = (int) ($page['total'] ?? 0);
        $currentPage = (int) ($page['page'] ?? 1);
        $perPage = (int) ($page['per_page'] ?? 10);
        $lastPage = (int) ($page['last_page'] ?? 1);

        return [
            'total' => $total,
            'page' => $currentPage,
            'per_page' => $perPage,
            'last_page' => $lastPage,
            'from' => $total > 0 ? (($currentPage - 1) * $perPage) + 1 : 0,
            'to' => $total > 0 ? min($total, $currentPage * $perPage) : 0,
            'pages' => $this->buildPaginationPages($currentPage, $lastPage),
        ];
    }

    private function buildPaginationPages(int $currentPage, int $lastPage): array
    {
        $lastPage = max(1, $lastPage);
        $currentPage = max(1, min($currentPage, $lastPage));

        $pages = [1, $lastPage, $currentPage];
        for ($offset = -2; $offset <= 2; $offset++) {
            $pages[] = $currentPage + $offset;
        }

        $normalized = [];
        foreach ($pages as $page) {
            $pageNumber = (int) $page;
            if ($pageNumber >= 1 && $pageNumber <= $lastPage) {
                $normalized[$pageNumber] = true;
            }
        }

        $result = array_keys($normalized);
        sort($result);

        return $result;
    }
}
