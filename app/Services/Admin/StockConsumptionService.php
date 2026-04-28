<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Exceptions\ValidationException;
use App\Repositories\OrderItemRepository;
use App\Repositories\StockRepository;

final class StockConsumptionService
{
    public function __construct(
        private readonly StockRepository $stock = new StockRepository(),
        private readonly OrderItemRepository $orderItems = new OrderItemRepository(),
        private readonly CompanyPlanFeatureService $companyFeatures = new CompanyPlanFeatureService()
    ) {}

    public function consumePaidFinishedOrder(int $companyId, int $orderId, int $userId): void
    {
        if (!$this->companyFeatures->isEnabledForCompany($companyId, 'estoque')) {
            return;
        }

        if (!$this->stock->tableExists('stock_recipe_items') || !$this->stock->tableExists('stock_consumptions')) {
            return;
        }

        if ($this->stock->hasConsumptionForOrder($companyId, $orderId)) {
            return;
        }

        $items = $this->orderItems->activeItemsByOrderIds($companyId, [$orderId]);
        if ($items === []) {
            return;
        }

        $productIds = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId > 0) {
                $productIds[$productId] = true;
            }
        }

        $recipeRows = $this->stock->listActiveRecipeRowsForProducts($companyId, array_keys($productIds));
        if ($recipeRows === []) {
            return;
        }

        $recipesByProduct = [];
        foreach ($recipeRows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $recipesByProduct[$productId][] = $row;
        }

        $consumptionLines = [];
        $totalsByStockItem = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $orderItemId = (int) ($item['id'] ?? 0);
            $orderQuantity = max(0, (int) ($item['quantity'] ?? 0));
            if ($productId <= 0 || $orderItemId <= 0 || $orderQuantity <= 0 || empty($recipesByProduct[$productId])) {
                continue;
            }

            foreach ($recipesByProduct[$productId] as $recipe) {
                $stockItemId = (int) ($recipe['stock_item_id'] ?? 0);
                $quantityPerUnit = round((float) ($recipe['quantity_per_unit'] ?? 0), 3);
                $wastePercent = round((float) ($recipe['waste_percent'] ?? 0), 2);
                if ($stockItemId <= 0 || $quantityPerUnit <= 0) {
                    continue;
                }

                $quantityInStockUnit = $this->convertQuantityToStockUnit(
                    $quantityPerUnit,
                    (string) ($recipe['consumption_unit'] ?? $recipe['unit_of_measure'] ?? 'un'),
                    (string) ($recipe['unit_of_measure'] ?? 'un')
                );
                $quantity = round(($quantityInStockUnit * $orderQuantity) * (1 + ($wastePercent / 100)), 3);
                if ($quantity <= 0) {
                    continue;
                }

                $consumptionLines[] = [
                    'order_item_id' => $orderItemId,
                    'product_id' => $productId,
                    'stock_item_id' => $stockItemId,
                    'quantity' => $quantity,
                ];
                $totalsByStockItem[$stockItemId] = round(($totalsByStockItem[$stockItemId] ?? 0) + $quantity, 3);
            }
        }

        if ($totalsByStockItem === []) {
            return;
        }

        $movementIdsByStockItem = [];
        foreach ($totalsByStockItem as $stockItemId => $quantity) {
            $item = $this->stock->findItemByIdForUpdate($companyId, (int) $stockItemId);
            if ($item === null || (string) ($item['status'] ?? '') !== 'ativo') {
                throw new ValidationException('Item de estoque da ficha tecnica nao esta ativo para baixa automatica.');
            }

            $currentQuantity = round((float) ($item['current_quantity'] ?? 0), 3);
            if ($quantity > $currentQuantity) {
                throw new ValidationException(
                    'Estoque insuficiente para finalizar o pedido. Item "' .
                    (string) ($item['name'] ?? ('#' . $stockItemId)) .
                    '" precisa de ' . $this->formatQuantity($quantity) . ' ' . (string) ($item['unit_of_measure'] ?? 'un') .
                    ', mas possui ' . $this->formatQuantity($currentQuantity) . '.'
                );
            }

            $nextQuantity = round($currentQuantity - $quantity, 3);
            $this->stock->updateCurrentQuantity($companyId, (int) $stockItemId, $nextQuantity);
            $movementIdsByStockItem[$stockItemId] = $this->stock->createMovement([
                'company_id' => $companyId,
                'stock_item_id' => (int) $stockItemId,
                'type' => 'exit',
                'quantity' => $quantity,
                'reason' => 'Baixa automatica por pedido pago/finalizado #' . $orderId,
                'reference_type' => 'order',
                'reference_id' => $orderId,
                'moved_by_user_id' => $userId > 0 ? $userId : null,
                'moved_at' => date('Y-m-d H:i:s'),
            ]);
        }

        foreach ($consumptionLines as $line) {
            $stockItemId = (int) $line['stock_item_id'];
            $movementId = (int) ($movementIdsByStockItem[$stockItemId] ?? 0);
            if ($movementId <= 0) {
                continue;
            }

            $this->stock->createConsumption([
                'company_id' => $companyId,
                'order_id' => $orderId,
                'order_item_id' => (int) $line['order_item_id'],
                'product_id' => (int) $line['product_id'],
                'stock_item_id' => $stockItemId,
                'stock_movement_id' => $movementId,
                'quantity' => round((float) $line['quantity'], 3),
            ]);
        }
    }

    private function formatQuantity(float $value): string
    {
        $formatted = number_format($value, 3, ',', '.');
        return rtrim(rtrim($formatted, '0'), ',');
    }

    private function convertQuantityToStockUnit(float $quantity, string $consumptionUnit, string $stockUnit): float
    {
        $consumptionUnit = strtolower(trim($consumptionUnit));
        $stockUnit = strtolower(trim($stockUnit));
        if ($consumptionUnit === $stockUnit) {
            return round($quantity, 3);
        }

        if ($stockUnit === 'kg' && $consumptionUnit === 'g') {
            return round($quantity / 1000, 3);
        }
        if ($stockUnit === 'g' && $consumptionUnit === 'kg') {
            return round($quantity * 1000, 3);
        }
        if ($stockUnit === 'l' && $consumptionUnit === 'ml') {
            return round($quantity / 1000, 3);
        }
        if ($stockUnit === 'ml' && $consumptionUnit === 'l') {
            return round($quantity * 1000, 3);
        }

        throw new ValidationException('Unidade da ficha tecnica incompatível com a unidade do item de estoque.');
    }
}
