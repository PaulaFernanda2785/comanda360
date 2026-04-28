<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class OrderItemRepository extends BaseRepository
{
    public function createBatch(int $companyId, int $orderId, array $items): void
    {
        $itemSql = "
            INSERT INTO order_items (
                company_id,
                order_id,
                product_id,
                product_name_snapshot,
                unit_price,
                quantity,
                notes,
                line_subtotal,
                status,
                created_at
            ) VALUES (
                :company_id,
                :order_id,
                :product_id,
                :product_name_snapshot,
                :unit_price,
                :quantity,
                :notes,
                :line_subtotal,
                'active',
                NOW()
            )
        ";

        $itemStmt = $this->db()->prepare($itemSql);
        $additionalStmt = $this->db()->prepare("
            INSERT INTO order_item_additionals (
                company_id,
                order_item_id,
                additional_item_id,
                additional_name_snapshot,
                unit_price,
                quantity,
                line_subtotal,
                created_at
            ) VALUES (
                :company_id,
                :order_item_id,
                :additional_item_id,
                :additional_name_snapshot,
                :unit_price,
                :quantity,
                :line_subtotal,
                NOW()
            )
        ");

        foreach ($items as $item) {
            $itemStmt->execute([
                'company_id' => $companyId,
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'product_name_snapshot' => $item['product_name_snapshot'],
                'unit_price' => $item['unit_price'],
                'quantity' => $item['quantity'],
                'notes' => $item['notes'],
                'line_subtotal' => $item['line_subtotal'],
            ]);

            $orderItemId = (int) $this->db()->lastInsertId();
            $additionals = $item['additionals'] ?? [];
            if (!is_array($additionals) || $additionals === []) {
                continue;
            }

            foreach ($additionals as $additional) {
                $additionalStmt->execute([
                    'company_id' => $companyId,
                    'order_item_id' => $orderItemId,
                    'additional_item_id' => (int) ($additional['additional_item_id'] ?? 0),
                    'additional_name_snapshot' => (string) ($additional['additional_name_snapshot'] ?? ''),
                    'unit_price' => (float) ($additional['unit_price'] ?? 0),
                    'quantity' => (int) ($additional['quantity'] ?? 1),
                    'line_subtotal' => (float) ($additional['line_subtotal'] ?? 0),
                ]);
            }
        }
    }

    public function activeItemsByOrderIds(int $companyId, array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $orderIds)));
        $orderIds = array_values(array_filter($orderIds, static fn (int $id): bool => $id > 0));
        if ($orderIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $sql = "
            SELECT
                oi.id,
                oi.order_id,
                oi.product_id,
                oi.product_name_snapshot,
                oi.unit_price,
                oi.quantity,
                oi.notes,
                oi.line_subtotal
            FROM order_items oi
            WHERE oi.company_id = ?
              AND oi.status = 'active'
              AND oi.order_id IN ($placeholders)
            ORDER BY oi.order_id ASC, oi.id ASC
        ";

        $params = array_merge([$companyId], $orderIds);
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function additionalsByOrderItemIds(int $companyId, array $orderItemIds): array
    {
        $orderItemIds = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $orderItemIds)));
        $orderItemIds = array_values(array_filter($orderItemIds, static fn (int $id): bool => $id > 0));
        if ($orderItemIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($orderItemIds), '?'));
        $sql = "
            SELECT
                oia.id,
                oia.order_item_id,
                oia.additional_name_snapshot,
                oia.unit_price,
                oia.quantity,
                oia.line_subtotal
            FROM order_item_additionals oia
            WHERE oia.company_id = ?
              AND oia.order_item_id IN ($placeholders)
            ORDER BY oia.order_item_id ASC, oia.id ASC
        ";

        $params = array_merge([$companyId], $orderItemIds);
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
