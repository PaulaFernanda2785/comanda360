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
            WHERE u.email = :email
              AND u.deleted_at IS NULL
            LIMIT 1
        ";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['email' => $email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }
}
