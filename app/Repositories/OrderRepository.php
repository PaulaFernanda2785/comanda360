<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class OrderRepository extends BaseRepository
{
    public function allByCompany(int $companyId): array
    {
        $sql = "
            SELECT
                o.id,
                o.company_id,
                o.command_id,
                o.table_id,
                o.order_number,
                o.status,
                o.payment_status,
                o.customer_name,
                o.subtotal_amount,
                o.discount_amount,
                o.delivery_fee,
                o.total_amount,
                o.created_at,
                t.number AS table_number,
                (
                    SELECT COUNT(*)
                    FROM order_items oi
                    WHERE oi.order_id = o.id
                      AND oi.company_id = o.company_id
                      AND oi.status = 'active'
                ) AS items_count
            FROM orders o
            LEFT JOIN tables t ON t.id = o.table_id
            WHERE o.company_id = :company_id
            ORDER BY o.created_at DESC, o.id DESC
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findLastOrderNumberByPrefix(int $companyId, string $prefix): ?string
    {
        $stmt = $this->db()->prepare("
            SELECT order_number
            FROM orders
            WHERE company_id = :company_id
              AND order_number LIKE :prefix
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'prefix' => $prefix . '-%',
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['order_number'] ?? null;
    }

    public function create(array $data): int
    {
        $sql = "
            INSERT INTO orders (
                company_id,
                command_id,
                table_id,
                customer_id,
                order_number,
                channel,
                status,
                payment_status,
                customer_name,
                subtotal_amount,
                discount_amount,
                delivery_fee,
                total_amount,
                notes,
                placed_by,
                placed_by_user_id,
                created_at
            ) VALUES (
                :company_id,
                :command_id,
                :table_id,
                :customer_id,
                :order_number,
                :channel,
                :status,
                :payment_status,
                :customer_name,
                :subtotal_amount,
                :discount_amount,
                :delivery_fee,
                :total_amount,
                :notes,
                :placed_by,
                :placed_by_user_id,
                NOW()
            )
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }
}
