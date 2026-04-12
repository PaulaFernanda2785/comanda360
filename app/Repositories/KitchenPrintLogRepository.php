<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class KitchenPrintLogRepository extends BaseRepository
{
    public function createKitchenTicket(int $companyId, int $orderId, ?int $userId, ?string $notes = null): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO kitchen_print_logs (
                company_id,
                order_id,
                print_type,
                printed_by_user_id,
                printed_at,
                status,
                notes
            ) VALUES (
                :company_id,
                :order_id,
                'kitchen_ticket',
                :printed_by_user_id,
                NOW(),
                'success',
                :notes
            )
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'order_id' => $orderId,
            'printed_by_user_id' => $userId,
            'notes' => $notes,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function latestKitchenTicketByOrderIds(int $companyId, array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $params = [
            'company_id_sub' => $companyId,
            'company_id_main' => $companyId,
        ];
        $placeholders = [];

        foreach ($orderIds as $index => $orderId) {
            $key = 'order_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $orderId;
        }

        $sql = "
            SELECT
                kpl.order_id,
                kpl.printed_at,
                kpl.status,
                kpl.notes,
                u.name AS printed_by_user_name
            FROM kitchen_print_logs kpl
            LEFT JOIN users u ON u.id = kpl.printed_by_user_id
            INNER JOIN (
                SELECT order_id, MAX(id) AS max_id
                FROM kitchen_print_logs
                WHERE company_id = :company_id_sub
                  AND print_type = 'kitchen_ticket'
                  AND order_id IN (" . implode(', ', $placeholders) . ")
                GROUP BY order_id
            ) latest
                ON latest.max_id = kpl.id
            WHERE kpl.company_id = :company_id_main
              AND kpl.print_type = 'kitchen_ticket'
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row['order_id']] = $row;
        }

        return $map;
    }

    public function recentKitchenTicketsByCompany(int $companyId, int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));

        $sql = "
            SELECT
                kpl.id,
                kpl.order_id,
                o.order_number,
                kpl.printed_at,
                kpl.status,
                kpl.notes,
                u.name AS printed_by_user_name
            FROM kitchen_print_logs kpl
            INNER JOIN orders o
                ON o.id = kpl.order_id
               AND o.company_id = kpl.company_id
            LEFT JOIN users u
                ON u.id = kpl.printed_by_user_id
            WHERE kpl.company_id = :company_id
              AND kpl.print_type = 'kitchen_ticket'
            ORDER BY kpl.printed_at DESC, kpl.id DESC
            LIMIT {$limit}
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

