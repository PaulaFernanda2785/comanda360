<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class UserRepository
{
    public function findByEmail(string $email): ?array
    {
        $sql = "
            SELECT
                u.id,
                u.company_id,
                u.role_id,
                u.name,
                u.email,
                u.password_hash,
                u.status,
                u.is_saas_user,
                r.slug AS role_slug,
                r.name AS role_name,
                r.context AS role_context
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE LOWER(u.email) = :email
              AND u.deleted_at IS NULL
            LIMIT 1
        ";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['email' => strtolower(trim($email))]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public function deliveryUsersByCompany(int $companyId): array
    {
        $sql = "
            SELECT
                u.id,
                u.company_id,
                u.name,
                u.email,
                u.status,
                r.slug AS role_slug,
                r.name AS role_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.company_id = :company_id
              AND u.deleted_at IS NULL
              AND u.status = 'ativo'
              AND r.context = 'company'
              AND r.slug = 'delivery'
            ORDER BY u.name ASC
        ";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findActiveByIdForCompany(int $companyId, int $userId): ?array
    {
        $sql = "
            SELECT
                u.id,
                u.company_id,
                u.name,
                u.status,
                r.slug AS role_slug
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.company_id = :company_id
              AND u.id = :id
              AND u.deleted_at IS NULL
              AND u.status = 'ativo'
            LIMIT 1
        ";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
