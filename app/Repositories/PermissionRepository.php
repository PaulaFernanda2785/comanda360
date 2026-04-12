<?php
declare(strict_types=1);

namespace App\Repositories;

final class PermissionRepository extends BaseRepository
{
    public function roleHasPermission(int $roleId, string $permissionSlug): bool
    {
        $stmt = $this->db()->prepare("
            SELECT 1
            FROM role_permissions rp
            INNER JOIN permissions p ON p.id = rp.permission_id
            WHERE rp.role_id = :role_id
              AND p.slug = :slug
            LIMIT 1
        ");
        $stmt->execute([
            'role_id' => $roleId,
            'slug' => $permissionSlug,
        ]);

        return $stmt->fetchColumn() !== false;
    }
}
