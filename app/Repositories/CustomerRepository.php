<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CustomerRepository extends BaseRepository
{
    public function findByPhoneForCompany(int $companyId, string $phone): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                id,
                company_id,
                name,
                phone,
                email,
                status
            FROM customers
            WHERE company_id = :company_id
              AND phone = :phone
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'phone' => $phone,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO customers (
                company_id,
                name,
                phone,
                email,
                notes,
                status,
                created_at
            ) VALUES (
                :company_id,
                :name,
                :phone,
                :email,
                :notes,
                'ativo',
                NOW()
            )
        ");
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }
}

