<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PaymentMethodRepository extends BaseRepository
{
    private const DEFAULT_METHODS = [
        ['name' => 'Pix', 'code' => 'pix'],
        ['name' => 'Dinheiro', 'code' => 'cash'],
        ['name' => 'Cartão de Crédito', 'code' => 'credit_card'],
        ['name' => 'Cartão de Débito', 'code' => 'debit_card'],
        ['name' => 'Pagamento Online', 'code' => 'online'],
    ];

    public function activeByCompany(int $companyId): array
    {
        $this->seedDefaultsForCompany($companyId);
        return $this->findActiveByCompany($companyId);
    }

    public function seedDefaultsForCompany(int $companyId): void
    {
        if ($companyId <= 0) {
            return;
        }

        $stmt = $this->db()->prepare("
            INSERT INTO payment_methods (
                company_id,
                name,
                code,
                status,
                created_at,
                updated_at
            ) VALUES (
                :company_id,
                :name,
                :code,
                'ativo',
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                status = 'ativo',
                updated_at = NOW()
        ");

        foreach (self::DEFAULT_METHODS as $method) {
            $stmt->execute([
                'company_id' => $companyId,
                'name' => $method['name'],
                'code' => $method['code'],
            ]);
        }
    }

    private function findActiveByCompany(int $companyId): array
    {
        $stmt = $this->db()->prepare("
            SELECT id, company_id, name, code, status
            FROM payment_methods
            WHERE company_id = :company_id
              AND status = 'ativo'
            ORDER BY name ASC
        ");
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findActiveById(int $companyId, int $paymentMethodId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT id, company_id, name, code, status
            FROM payment_methods
            WHERE company_id = :company_id
              AND id = :id
              AND status = 'ativo'
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $paymentMethodId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
