<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Exceptions\ValidationException;
use App\Repositories\StockRepository;

final class StockAvailabilityService
{
    public function __construct(
        private readonly StockRepository $stock = new StockRepository()
    ) {}

    public function enrichProducts(int $companyId, array $products): array
    {
        if ($products === []) {
            return [];
        }

        $productIds = array_values(array_unique(array_filter(array_map(
            static fn (array $product): int => (int) ($product['id'] ?? 0),
            $products
        ))));
        $availability = $this->availabilityByProduct($companyId, $productIds);

        foreach ($products as &$product) {
            $productId = (int) ($product['id'] ?? 0);
            $state = $availability[$productId] ?? [
                'has_recipe' => false,
                'stock_available' => true,
                'stock_status' => 'not_controlled',
                'stock_note' => 'Produto sem ficha tecnica de estoque.',
                'insufficient_items' => [],
            ];

            $product['stock_has_recipe'] = $state['has_recipe'];
            $product['stock_available'] = $state['stock_available'];
            $product['stock_status'] = $state['stock_status'];
            $product['stock_note'] = $state['stock_note'];
            $product['stock_insufficient_items'] = $state['insufficient_items'];
        }
        unset($product);

        return $products;
    }

    public function assertItemsAvailable(int $companyId, array $items): void
    {
        $quantitiesByProduct = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $quantitiesByProduct[$productId] = ($quantitiesByProduct[$productId] ?? 0) + $quantity;
        }

        if ($quantitiesByProduct === []) {
            return;
        }

        $availability = $this->availabilityByProduct($companyId, array_keys($quantitiesByProduct), $quantitiesByProduct);
        foreach ($availability as $state) {
            if (!empty($state['has_recipe']) && empty($state['stock_available'])) {
                throw new ValidationException((string) ($state['stock_note'] ?? 'Produto indisponivel por estoque insuficiente.'));
            }
        }
    }

    public function availabilityByProduct(int $companyId, array $productIds, array $orderQuantitiesByProduct = []): array
    {
        $productIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): int => (int) $value,
            $productIds
        ), static fn (int $value): bool => $value > 0)));

        if ($productIds === [] || !$this->stock->tableExists('stock_recipe_items')) {
            return [];
        }

        $rows = $this->stock->listActiveRecipeRowsForProducts($companyId, $productIds);
        if ($rows === []) {
            return [];
        }

        $recipesByProduct = [];
        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            if (!isset($recipesByProduct[$productId])) {
                $recipesByProduct[$productId] = [];
            }
            $recipesByProduct[$productId][] = $row;
        }

        $result = [];
        foreach ($recipesByProduct as $productId => $recipeRows) {
            $orderQuantity = max(1, (int) ($orderQuantitiesByProduct[$productId] ?? 1));
            $insufficient = [];
            $maxProduction = null;

            foreach ($recipeRows as $recipe) {
                $quantityPerUnit = round((float) ($recipe['quantity_per_unit'] ?? 0), 3);
                $wastePercent = round((float) ($recipe['waste_percent'] ?? 0), 2);
                if ($quantityPerUnit <= 0) {
                    continue;
                }

                $requiredPerUnit = round($quantityPerUnit * (1 + ($wastePercent / 100)), 3);
                $required = round($requiredPerUnit * $orderQuantity, 3);
                $stockItemActive = (string) ($recipe['stock_item_status'] ?? 'ativo') === 'ativo';
                $available = $stockItemActive ? round((float) ($recipe['current_quantity'] ?? 0), 3) : 0.0;
                $possible = $requiredPerUnit > 0 ? (int) floor($available / $requiredPerUnit) : 0;
                $maxProduction = $maxProduction === null ? $possible : min($maxProduction, $possible);

                if ($available < $required) {
                    $insufficient[] = [
                        'stock_item_id' => (int) ($recipe['stock_item_id'] ?? 0),
                        'name' => (string) ($recipe['stock_item_name'] ?? 'Insumo'),
                        'unit' => (string) ($recipe['unit_of_measure'] ?? 'un'),
                        'required' => $required,
                        'available' => $available,
                    ];
                }
            }

            $available = $insufficient === [];
            $result[$productId] = [
                'has_recipe' => true,
                'stock_available' => $available,
                'stock_status' => $available ? 'available' : 'insufficient',
                'stock_note' => $available
                    ? 'Produto disponivel para producao.'
                    : $this->buildInsufficientMessage($productId, $insufficient),
                'max_production_quantity' => max(0, (int) ($maxProduction ?? 0)),
                'insufficient_items' => $insufficient,
            ];
        }

        return $result;
    }

    private function buildInsufficientMessage(int $productId, array $items): string
    {
        $first = $items[0] ?? null;
        if (!is_array($first)) {
            return 'Produto #' . $productId . ' indisponivel por estoque insuficiente.';
        }

        return 'Produto indisponivel: insumo "' . (string) ($first['name'] ?? 'Insumo') .
            '" precisa de ' . $this->formatQuantity((float) ($first['required'] ?? 0)) . ' ' . (string) ($first['unit'] ?? 'un') .
            ', mas possui ' . $this->formatQuantity((float) ($first['available'] ?? 0)) . '.';
    }

    private function formatQuantity(float $value): string
    {
        $formatted = number_format($value, 3, ',', '.');
        return rtrim(rtrim($formatted, '0'), ',');
    }
}
