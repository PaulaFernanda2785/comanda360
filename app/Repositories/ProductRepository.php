<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ProductRepository extends BaseRepository
{
    public function allByCompany(int $companyId): array
    {
        $sql = "
            SELECT
                p.id,
                p.company_id,
                p.category_id,
                p.name,
                p.slug,
                p.description,
                p.sku,
                p.price,
                p.promotional_price,
                p.is_featured,
                p.is_active,
                p.is_paused,
                p.display_order,
                c.name AS category_name
            FROM products p
            INNER JOIN categories c ON c.id = p.category_id
            WHERE p.company_id = :company_id
              AND p.deleted_at IS NULL
            ORDER BY p.display_order ASC, p.name ASC
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function categoriesByCompany(int $companyId): array
    {
        $stmt = $this->db()->prepare("
            SELECT id, name
            FROM categories
            WHERE company_id = :company_id
              AND deleted_at IS NULL
              AND status = 'ativo'
            ORDER BY display_order ASC, name ASC
        ");
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): int
    {
        $sql = "
            INSERT INTO products (
                company_id, category_id, name, slug, description, sku,
                price, promotional_price, is_featured, is_active, is_paused,
                allows_notes, has_additionals, display_order, created_at
            ) VALUES (
                :company_id, :category_id, :name, :slug, :description, :sku,
                :price, :promotional_price, :is_featured, :is_active, :is_paused,
                :allows_notes, :has_additionals, :display_order, NOW()
            )
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function findByIdForCompany(int $companyId, int $productId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                id,
                company_id,
                name,
                price,
                promotional_price,
                is_active,
                is_paused
            FROM products
            WHERE company_id = :company_id
              AND id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $productId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
