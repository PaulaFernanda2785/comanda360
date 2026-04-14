<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DeliveryZoneRepository extends BaseRepository
{
    public function allByCompany(int $companyId): array
    {
        $stmt = $this->db()->prepare("
            SELECT
                id,
                company_id,
                name,
                description,
                fee_amount,
                minimum_order_amount,
                status,
                created_at,
                updated_at
            FROM delivery_zones
            WHERE company_id = :company_id
            ORDER BY status DESC, name ASC, id DESC
        ");
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function activeByCompany(int $companyId): array
    {
        $stmt = $this->db()->prepare("
            SELECT
                id,
                company_id,
                name,
                description,
                fee_amount,
                minimum_order_amount,
                status
            FROM delivery_zones
            WHERE company_id = :company_id
              AND status = 'ativo'
            ORDER BY name ASC, id DESC
        ");
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByIdForCompany(int $companyId, int $zoneId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                id,
                company_id,
                name,
                description,
                fee_amount,
                minimum_order_amount,
                status
            FROM delivery_zones
            WHERE company_id = :company_id
              AND id = :id
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $zoneId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO delivery_zones (
                company_id,
                name,
                description,
                fee_amount,
                minimum_order_amount,
                status,
                created_at
            ) VALUES (
                :company_id,
                :name,
                :description,
                :fee_amount,
                :minimum_order_amount,
                :status,
                NOW()
            )
        ");
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function update(array $data): void
    {
        $stmt = $this->db()->prepare("
            UPDATE delivery_zones
            SET
                name = :name,
                description = :description,
                fee_amount = :fee_amount,
                minimum_order_amount = :minimum_order_amount,
                status = :status,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
        ");
        $stmt->execute($data);
    }

    public function delete(int $companyId, int $zoneId): void
    {
        $stmt = $this->db()->prepare("
            DELETE FROM delivery_zones
            WHERE company_id = :company_id
              AND id = :id
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $zoneId,
        ]);
    }
}

