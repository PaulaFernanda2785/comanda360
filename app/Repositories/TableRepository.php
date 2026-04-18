<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class TableRepository extends BaseRepository
{
    public function allByCompany(int $companyId): array
    {
        $sql = "
            SELECT
                t.id,
                t.company_id,
                t.name,
                t.number,
                t.capacity,
                t.qr_code_token,
                t.status,
                t.created_at,
                c.name AS company_name,
                ct.logo_path AS company_logo_path
            FROM tables t
            INNER JOIN companies c ON c.id = t.company_id
            LEFT JOIN company_themes ct ON ct.company_id = t.company_id
            WHERE t.company_id = :company_id
            ORDER BY t.number ASC
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): int
    {
        $sql = "
            INSERT INTO tables (
                company_id, name, number, capacity, qr_code_token, status, created_at
            ) VALUES (
                :company_id, :name, :number, :capacity, :qr_code_token, :status, NOW()
            )
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function countByCompany(int $companyId): int
    {
        $stmt = $this->db()->prepare("
            SELECT COUNT(*)
            FROM tables
            WHERE company_id = :company_id
        ");
        $stmt->execute(['company_id' => $companyId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function findByNumber(int $companyId, int $number, ?int $ignoreTableId = null): ?array
    {
        $sql = "
            SELECT id, company_id, name, number, capacity, qr_code_token, status
            FROM tables
            WHERE company_id = :company_id
              AND number = :number
        ";
        $params = [
            'company_id' => $companyId,
            'number' => $number,
        ];

        if ($ignoreTableId !== null && $ignoreTableId > 0) {
            $sql .= " AND id <> :ignore_table_id";
            $params['ignore_table_id'] = $ignoreTableId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $companyId, int $id): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT id, company_id, name, number, capacity, qr_code_token, status
            FROM tables
            WHERE company_id = :company_id
              AND id = :id
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $id,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByNumberAndTokenForPublic(int $companyId, int $number, string $token): ?array
    {
        $normalizedToken = trim($token);
        if ($companyId <= 0 || $number <= 0 || $normalizedToken === '') {
            return null;
        }

        $stmt = $this->db()->prepare("
            SELECT
                id,
                company_id,
                name,
                number,
                capacity,
                qr_code_token,
                status
            FROM tables
            WHERE company_id = :company_id
              AND number = :number
              AND qr_code_token = :qr_code_token
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'number' => $number,
            'qr_code_token' => $normalizedToken,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateStatus(int $tableId, string $status): void
    {
        $stmt = $this->db()->prepare("
            UPDATE tables
            SET status = :status,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'status' => $status,
            'id' => $tableId,
        ]);
    }

    public function updateStatusForCompany(int $companyId, int $tableId, string $status): void
    {
        $stmt = $this->db()->prepare("
            UPDATE tables
            SET status = :status,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
        ");
        $stmt->execute([
            'status' => $status,
            'company_id' => $companyId,
            'id' => $tableId,
        ]);
    }

    public function updateById(int $companyId, int $tableId, array $data): void
    {
        $stmt = $this->db()->prepare("
            UPDATE tables
            SET name = :name,
                number = :number,
                capacity = :capacity,
                status = :status,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
        ");
        $stmt->execute([
            'name' => $data['name'],
            'number' => $data['number'],
            'capacity' => $data['capacity'],
            'status' => $data['status'],
            'company_id' => $companyId,
            'id' => $tableId,
        ]);
    }

    public function deleteById(int $companyId, int $tableId): void
    {
        $stmt = $this->db()->prepare("
            DELETE FROM tables
            WHERE company_id = :company_id
              AND id = :id
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $tableId,
        ]);
    }

    public function findWithCompanyContextById(int $companyId, int $tableId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                t.id,
                t.company_id,
                t.name,
                t.number,
                t.capacity,
                t.qr_code_token,
                t.status,
                c.name AS company_name,
                c.slug AS company_slug,
                ct.logo_path AS company_logo_path
            FROM tables t
            INNER JOIN companies c ON c.id = t.company_id
            LEFT JOIN company_themes ct ON ct.company_id = t.company_id
            WHERE t.company_id = :company_id
              AND t.id = :id
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $tableId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
