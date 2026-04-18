<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CommandRepository extends BaseRepository
{
    public function openCommandsByCompany(int $companyId): array
    {
        $sql = "
            SELECT
                c.id,
                c.company_id,
                c.table_id,
                c.customer_id,
                c.opened_by_user_id,
                c.customer_name,
                c.status,
                c.opened_at,
                c.closed_at,
                c.notes,
                t.number AS table_number,
                t.status AS table_status,
                u.name AS opened_by_user_name
            FROM commands c
            LEFT JOIN tables t ON t.id = c.table_id
            LEFT JOIN users u ON u.id = c.opened_by_user_id
            WHERE c.company_id = :company_id
              AND c.status = 'aberta'
            ORDER BY c.opened_at DESC
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function openCommandsByTable(int $companyId, int $tableId): array
    {
        $stmt = $this->db()->prepare("
            SELECT
                c.id,
                c.company_id,
                c.table_id,
                c.customer_id,
                c.opened_by_user_id,
                c.customer_name,
                c.status,
                c.opened_at,
                c.closed_at,
                c.notes,
                t.number AS table_number,
                t.status AS table_status,
                u.name AS opened_by_user_name
            FROM commands c
            LEFT JOIN tables t ON t.id = c.table_id
            LEFT JOIN users u ON u.id = c.opened_by_user_id
            WHERE c.company_id = :company_id
              AND c.table_id = :table_id
              AND c.status = 'aberta'
            ORDER BY c.opened_at ASC, c.id ASC
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'table_id' => $tableId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): int
    {
        $sql = "
            INSERT INTO commands (
                company_id, table_id, customer_id, opened_by_user_id,
                customer_name, status, opened_at, notes, created_at
            ) VALUES (
                :company_id, :table_id, :customer_id, :opened_by_user_id,
                :customer_name, 'aberta', NOW(), :notes, NOW()
            )
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function updateOpenCommand(int $companyId, int $commandId, array $data): void
    {
        $stmt = $this->db()->prepare("
            UPDATE commands
            SET customer_name = :customer_name,
                notes = :notes,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
              AND status = 'aberta'
            LIMIT 1
        ");
        $stmt->execute([
            'customer_name' => $data['customer_name'],
            'notes' => $data['notes'],
            'company_id' => $companyId,
            'id' => $commandId,
        ]);
    }

    public function findOpenByTable(int $companyId, int $tableId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT id, company_id, table_id, customer_name, status
            FROM commands
            WHERE company_id = :company_id
              AND table_id = :table_id
              AND status = 'aberta'
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'table_id' => $tableId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findOpenById(int $companyId, int $commandId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                id,
                company_id,
                table_id,
                customer_id,
                customer_name,
                status
            FROM commands
            WHERE company_id = :company_id
              AND id = :id
              AND status = 'aberta'
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $commandId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByIdForCompany(int $companyId, int $commandId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                id,
                company_id,
                table_id,
                customer_id,
                customer_name,
                status,
                opened_at,
                closed_at
            FROM commands
            WHERE company_id = :company_id
              AND id = :id
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $commandId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function close(int $companyId, int $commandId): void
    {
        $stmt = $this->db()->prepare("
            UPDATE commands
            SET status = 'fechada',
                closed_at = NOW(),
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
              AND status = 'aberta'
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $commandId,
        ]);
    }

    public function cancel(int $companyId, int $commandId): void
    {
        $stmt = $this->db()->prepare("
            UPDATE commands
            SET status = 'cancelada',
                closed_at = NOW(),
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
              AND status = 'aberta'
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $commandId,
        ]);
    }

    public function countOpenByTable(int $companyId, int $tableId): int
    {
        $stmt = $this->db()->prepare("
            SELECT COUNT(*) AS total
            FROM commands
            WHERE company_id = :company_id
              AND table_id = :table_id
              AND status = 'aberta'
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'table_id' => $tableId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['total'] ?? 0);
    }
}
