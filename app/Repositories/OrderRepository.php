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
                o.channel,
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

    public function findByIdForCompany(int $companyId, int $orderId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                id,
                company_id,
                command_id,
                order_number,
                channel,
                status,
                payment_status,
                discount_amount,
                total_amount
            FROM orders
            WHERE company_id = :company_id
              AND id = :id
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $orderId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateFinancialTotals(int $companyId, int $orderId, float $discountAmount, float $totalAmount): void
    {
        $stmt = $this->db()->prepare("
            UPDATE orders
            SET discount_amount = :discount_amount,
                total_amount = :total_amount,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
        ");
        $stmt->execute([
            'discount_amount' => round($discountAmount, 2),
            'total_amount' => round($totalAmount, 2),
            'company_id' => $companyId,
            'id' => $orderId,
        ]);
    }

    public function findWithContextById(int $companyId, int $orderId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                o.id,
                o.company_id,
                o.command_id,
                o.table_id,
                o.order_number,
                o.channel,
                o.status,
                o.payment_status,
                o.customer_name,
                o.subtotal_amount,
                o.discount_amount,
                o.delivery_fee,
                o.total_amount,
                o.notes,
                o.created_at,
                t.number AS table_number,
                t.name AS table_name,
                c.name AS company_name,
                ct.logo_path AS company_logo_path
            FROM orders o
            INNER JOIN companies c
                ON c.id = o.company_id
            LEFT JOIN tables t
                ON t.id = o.table_id
            LEFT JOIN company_themes ct
                ON ct.company_id = o.company_id
            WHERE o.company_id = :company_id
              AND o.id = :id
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $orderId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
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

    public function allPendingPaymentByCompany(int $companyId): array
    {
        $sql = "
            SELECT
                o.id,
                o.order_number,
                o.command_id,
                o.table_id,
                o.customer_name,
                o.channel,
                o.status,
                o.payment_status,
                o.total_amount,
                COALESCE((
                    SELECT SUM(p.amount)
                    FROM payments p
                    WHERE p.company_id = o.company_id
                      AND p.order_id = o.id
                      AND p.status = 'paid'
                ), 0) AS paid_amount,
                t.number AS table_number
            FROM orders o
            LEFT JOIN tables t ON t.id = o.table_id
            WHERE o.company_id = :company_id
              AND o.status <> 'canceled'
            ORDER BY o.created_at DESC, o.id DESC
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function kitchenQueueByCompany(int $companyId): array
    {
        $sql = "
            SELECT
                o.id,
                o.company_id,
                o.command_id,
                o.table_id,
                o.order_number,
                o.channel,
                o.status,
                o.payment_status,
                o.customer_name,
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
              AND o.status IN ('received', 'preparing', 'ready')
            ORDER BY
                CASE o.status
                    WHEN 'received' THEN 1
                    WHEN 'preparing' THEN 2
                    WHEN 'ready' THEN 3
                    ELSE 4
                END,
                o.created_at ASC,
                o.id ASC
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function updateStatus(int $companyId, int $orderId, string $status): void
    {
        $stmt = $this->db()->prepare("
            UPDATE orders
            SET status = :status,
                updated_at = NOW(),
                canceled_at = CASE
                    WHEN :status = 'canceled' THEN NOW()
                    ELSE canceled_at
                END
            WHERE company_id = :company_id
              AND id = :id
        ");
        $stmt->execute([
            'status' => $status,
            'company_id' => $companyId,
            'id' => $orderId,
        ]);
    }

    public function updatePaymentStatus(int $companyId, int $orderId, string $paymentStatus): void
    {
        $stmt = $this->db()->prepare("
            UPDATE orders
            SET payment_status = :payment_status,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
        ");
        $stmt->execute([
            'payment_status' => $paymentStatus,
            'company_id' => $companyId,
            'id' => $orderId,
        ]);
    }

    public function countByCommand(int $companyId, int $commandId): int
    {
        $stmt = $this->db()->prepare("
            SELECT COUNT(*) AS total
            FROM orders
            WHERE company_id = :company_id
              AND command_id = :command_id
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'command_id' => $commandId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['total'] ?? 0);
    }

    public function countUnsettledByCommand(int $companyId, int $commandId): int
    {
        $stmt = $this->db()->prepare("
            SELECT COUNT(*) AS total
            FROM orders
            WHERE company_id = :company_id
              AND command_id = :command_id
              AND NOT (
                  (status = 'finished' AND payment_status = 'paid')
                  OR status = 'canceled'
              )
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'command_id' => $commandId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['total'] ?? 0);
    }

    public function countByCommandAndStatus(int $companyId, int $commandId, string $status): int
    {
        $stmt = $this->db()->prepare("
            SELECT COUNT(*) AS total
            FROM orders
            WHERE company_id = :company_id
              AND command_id = :command_id
              AND status = :status
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'command_id' => $commandId,
            'status' => $status,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['total'] ?? 0);
    }
}
