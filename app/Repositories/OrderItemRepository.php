<?php
declare(strict_types=1);

namespace App\Repositories;

final class OrderItemRepository extends BaseRepository
{
    public function createBatch(int $companyId, int $orderId, array $items): void
    {
        $sql = "
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

        $stmt = $this->db()->prepare($sql);

        foreach ($items as $item) {
            $stmt->execute([
                'company_id' => $companyId,
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'product_name_snapshot' => $item['product_name_snapshot'],
                'unit_price' => $item['unit_price'],
                'quantity' => $item['quantity'],
                'notes' => $item['notes'],
                'line_subtotal' => $item['line_subtotal'],
            ]);
        }
    }
}
