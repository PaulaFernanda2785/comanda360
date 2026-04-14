<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PaymentRepository extends BaseRepository
{
    public function allByCompany(int $companyId): array
    {
        $sql = "
            SELECT
                p.id,
                p.company_id,
                p.order_id,
                p.command_id,
                p.payment_method_id,
                p.amount,
                p.status,
                p.transaction_reference,
                p.paid_at,
                p.created_at,
                p.received_by_user_id,
                pm.name AS payment_method_name,
                o.order_number,
                o.status AS order_status,
                o.payment_status AS order_payment_status,
                o.customer_name AS order_customer_name,
                o.subtotal_amount AS order_subtotal_amount,
                o.discount_amount AS order_discount_amount,
                o.delivery_fee AS order_delivery_fee,
                o.total_amount AS order_total_amount,
                o.notes AS order_notes,
                o.created_at AS order_created_at,
                t.number AS order_table_number,
                (
                    SELECT cm.description
                    FROM cash_movements cm
                    WHERE cm.company_id = p.company_id
                      AND cm.payment_id = p.id
                    ORDER BY cm.id DESC
                    LIMIT 1
                ) AS cash_movement_description,
                u.name AS received_by_user_name
            FROM payments p
            INNER JOIN payment_methods pm ON pm.id = p.payment_method_id
            LEFT JOIN orders o ON o.id = p.order_id
            LEFT JOIN tables t ON t.id = o.table_id
            LEFT JOIN users u ON u.id = p.received_by_user_id
            WHERE p.company_id = :company_id
            ORDER BY p.created_at DESC, p.id DESC
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): int
    {
        $sql = "
            INSERT INTO payments (
                company_id,
                order_id,
                command_id,
                payment_method_id,
                amount,
                status,
                transaction_reference,
                paid_at,
                received_by_user_id,
                created_at
            ) VALUES (
                :company_id,
                :order_id,
                :command_id,
                :payment_method_id,
                :amount,
                :status,
                :transaction_reference,
                :paid_at,
                :received_by_user_id,
                NOW()
            )
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function sumPaidAmountByOrder(int $companyId, int $orderId): float
    {
        $stmt = $this->db()->prepare("
            SELECT COALESCE(SUM(amount), 0) AS total_paid
            FROM payments
            WHERE company_id = :company_id
              AND order_id = :order_id
              AND status = 'paid'
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'order_id' => $orderId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return round((float) ($row['total_paid'] ?? 0), 2);
    }
}
