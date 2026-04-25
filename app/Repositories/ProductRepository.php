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
                p.image_path,
                p.price,
                p.promotional_price,
                p.is_featured,
                p.is_active,
                p.is_paused,
                p.allows_notes,
                p.has_additionals,
                p.display_order,
                p.created_at,
                c.name AS category_name,
                c.slug AS category_slug,
                c.status AS category_status,
                (
                    SELECT COUNT(*)
                    FROM product_additional_groups pag
                    INNER JOIN additional_items ai
                        ON ai.additional_group_id = pag.additional_group_id
                       AND ai.company_id = pag.company_id
                       AND ai.status = 'ativo'
                    WHERE pag.company_id = p.company_id
                      AND pag.product_id = p.id
                ) AS additionals_count,
                (
                    SELECT MAX(ag.max_selection)
                    FROM product_additional_groups pag
                    INNER JOIN additional_groups ag
                        ON ag.id = pag.additional_group_id
                       AND ag.company_id = pag.company_id
                       AND ag.status = 'ativo'
                    WHERE pag.company_id = p.company_id
                      AND pag.product_id = p.id
                ) AS additionals_max_selection
            FROM products p
            INNER JOIN categories c
                ON c.id = p.category_id
               AND c.company_id = p.company_id
               AND c.deleted_at IS NULL
            WHERE p.company_id = :company_id
              AND p.deleted_at IS NULL
            ORDER BY c.display_order ASC, p.display_order ASC, p.name ASC
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function activeForOrderByCompany(int $companyId): array
    {
        $stmt = $this->db()->prepare("
            SELECT
                p.id,
                p.company_id,
                p.category_id,
                p.name,
                p.slug,
                p.description,
                p.sku,
                p.image_path,
                p.price,
                p.promotional_price,
                p.allows_notes,
                p.has_additionals,
                p.display_order,
                c.name AS category_name,
                c.slug AS category_slug
            FROM products p
            INNER JOIN categories c
                ON c.id = p.category_id
               AND c.company_id = p.company_id
               AND c.deleted_at IS NULL
               AND c.status = 'ativo'
            WHERE p.company_id = :company_id
              AND p.deleted_at IS NULL
              AND p.is_active = 1
              AND p.is_paused = 0
            ORDER BY c.display_order ASC, p.display_order ASC, p.name ASC
        ");
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countByCompany(int $companyId): int
    {
        $stmt = $this->db()->prepare("
            SELECT COUNT(*)
            FROM products
            WHERE company_id = :company_id
              AND deleted_at IS NULL
        ");
        $stmt->execute(['company_id' => $companyId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function categoriesByCompany(int $companyId): array
    {
        $stmt = $this->db()->prepare("
            SELECT id, name, slug, description, status, display_order
            FROM categories
            WHERE company_id = :company_id
              AND deleted_at IS NULL
            ORDER BY display_order ASC, name ASC
        ");
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function activeCategoriesByCompany(int $companyId): array
    {
        $stmt = $this->db()->prepare("
            SELECT id, name, slug
            FROM categories
            WHERE company_id = :company_id
              AND deleted_at IS NULL
              AND status = 'ativo'
            ORDER BY display_order ASC, name ASC
        ");
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function categoriesWithProductCountByCompany(int $companyId): array
    {
        $stmt = $this->db()->prepare("
            SELECT
                c.id,
                c.company_id,
                c.name,
                c.slug,
                c.description,
                c.status,
                c.display_order,
                (
                    SELECT COUNT(*)
                    FROM products p
                    WHERE p.company_id = c.company_id
                      AND p.category_id = c.id
                      AND p.deleted_at IS NULL
                ) AS products_count
            FROM categories c
            WHERE c.company_id = :company_id
              AND c.deleted_at IS NULL
            ORDER BY c.display_order ASC, c.name ASC
        ");
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findCategoryById(int $companyId, int $categoryId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                id,
                company_id,
                name,
                slug,
                description,
                status,
                display_order
            FROM categories
            WHERE company_id = :company_id
              AND id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $categoryId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findCategoryBySlug(int $companyId, string $slug): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT id, company_id, slug
            FROM categories
            WHERE company_id = :company_id
              AND slug = :slug
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'slug' => $slug,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createCategory(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO categories (
                company_id,
                name,
                slug,
                description,
                display_order,
                status,
                created_at
            ) VALUES (
                :company_id,
                :name,
                :slug,
                :description,
                :display_order,
                :status,
                NOW()
            )
        ");
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function updateCategory(int $companyId, int $categoryId, array $data): void
    {
        $stmt = $this->db()->prepare("
            UPDATE categories
            SET name = :name,
                slug = :slug,
                description = :description,
                display_order = :display_order,
                status = :status,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
              AND deleted_at IS NULL
        ");
        $stmt->execute($data + [
            'company_id' => $companyId,
            'id' => $categoryId,
        ]);
    }

    public function softDeleteCategory(int $companyId, int $categoryId): void
    {
        $stmt = $this->db()->prepare("
            UPDATE categories
            SET deleted_at = NOW(),
                status = 'inativo',
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
              AND deleted_at IS NULL
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $categoryId,
        ]);
    }

    public function countProductsByCategory(int $companyId, int $categoryId): int
    {
        $stmt = $this->db()->prepare("
            SELECT COUNT(*) AS total
            FROM products
            WHERE company_id = :company_id
              AND category_id = :category_id
              AND deleted_at IS NULL
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'category_id' => $categoryId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['total'] ?? 0);
    }

    public function create(array $data): int
    {
        $sql = "
            INSERT INTO products (
                company_id, category_id, name, slug, description, sku, image_path,
                price, promotional_price, is_featured, is_active, is_paused,
                allows_notes, has_additionals, display_order, created_at
            ) VALUES (
                :company_id, :category_id, :name, :slug, :description, :sku, :image_path,
                :price, :promotional_price, :is_featured, :is_active, :is_paused,
                :allows_notes, :has_additionals, :display_order, NOW()
            )
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function updateById(int $companyId, int $productId, array $data): void
    {
        $sql = "
            UPDATE products
            SET category_id = :category_id,
                name = :name,
                slug = :slug,
                description = :description,
                sku = :sku,
                image_path = :image_path,
                price = :price,
                promotional_price = :promotional_price,
                is_featured = :is_featured,
                is_active = :is_active,
                is_paused = :is_paused,
                allows_notes = :allows_notes,
                has_additionals = :has_additionals,
                display_order = :display_order,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
              AND deleted_at IS NULL
        ";

        $params = $data + [
            'company_id' => $companyId,
            'id' => $productId,
        ];

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
    }

    public function setImagePathById(int $companyId, int $productId, ?string $imagePath): void
    {
        $stmt = $this->db()->prepare("
            UPDATE products
            SET image_path = :image_path,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
              AND deleted_at IS NULL
        ");
        $stmt->execute([
            'image_path' => $imagePath,
            'company_id' => $companyId,
            'id' => $productId,
        ]);
    }

    public function softDeleteById(int $companyId, int $productId): void
    {
        $product = $this->findByIdForCompany($companyId, $productId);
        if ($product === null) {
            return;
        }

        $stmt = $this->db()->prepare("
            UPDATE products
            SET deleted_at = NOW(),
                is_active = 0,
                slug = :archived_slug,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
              AND deleted_at IS NULL
        ");
        $stmt->execute([
            'archived_slug' => $this->deletedProductArchiveSlug($companyId, (string) ($product['slug'] ?? ''), $productId),
            'company_id' => $companyId,
            'id' => $productId,
        ]);
    }

    public function setHasAdditionals(int $companyId, int $productId, bool $hasAdditionals): void
    {
        $stmt = $this->db()->prepare("
            UPDATE products
            SET has_additionals = :has_additionals,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
              AND deleted_at IS NULL
        ");
        $stmt->execute([
            'has_additionals' => $hasAdditionals ? 1 : 0,
            'company_id' => $companyId,
            'id' => $productId,
        ]);
    }

    public function findByIdForCompany(int $companyId, int $productId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                id,
                company_id,
                category_id,
                name,
                slug,
                description,
                sku,
                image_path,
                price,
                promotional_price,
                is_featured,
                is_active,
                is_paused,
                allows_notes,
                has_additionals,
                display_order
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

    public function findProductBySlug(int $companyId, string $slug): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT id, company_id, slug
            FROM products
            WHERE company_id = :company_id
              AND slug = :slug
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'slug' => $slug,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findAnyProductBySlug(int $companyId, string $slug): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT id, company_id, slug, deleted_at
            FROM products
            WHERE company_id = :company_id
              AND slug = :slug
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'slug' => $slug,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findDeletedProductBySlug(int $companyId, string $slug): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT id, company_id, slug, deleted_at
            FROM products
            WHERE company_id = :company_id
              AND slug = :slug
              AND deleted_at IS NOT NULL
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'slug' => $slug,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function archiveDeletedProductSlug(int $companyId, int $productId, string $archivedSlug): void
    {
        $stmt = $this->db()->prepare("
            UPDATE products
            SET slug = :slug,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
              AND deleted_at IS NOT NULL
        ");
        $stmt->execute([
            'slug' => $archivedSlug,
            'company_id' => $companyId,
            'id' => $productId,
        ]);
    }

    private function deletedProductArchiveSlug(int $companyId, string $slug, int $productId): string
    {
        $baseSlug = trim($slug) !== '' ? trim($slug) : 'produto';
        $attempt = 0;
        do {
            $suffix = '-excluido-' . $productId . ($attempt > 0 ? '-' . $attempt : '');
            $base = substr($baseSlug, 0, max(1, 150 - strlen($suffix)));
            $candidate = rtrim($base, '-') . $suffix;
            $owner = $this->findAnyProductBySlug($companyId, $candidate);
            $attempt++;
        } while ($owner !== null && (int) ($owner['id'] ?? 0) !== $productId && $attempt < 25);

        return $candidate;
    }

    public function findProductAdditionalGroup(int $companyId, int $productId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                ag.id,
                ag.company_id,
                ag.name,
                ag.description,
                ag.is_required,
                ag.min_selection,
                ag.max_selection,
                ag.status
            FROM product_additional_groups pag
            INNER JOIN additional_groups ag
                ON ag.id = pag.additional_group_id
               AND ag.company_id = pag.company_id
            WHERE pag.company_id = :company_id
              AND pag.product_id = :product_id
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'product_id' => $productId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createAdditionalGroup(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO additional_groups (
                company_id,
                name,
                description,
                is_required,
                min_selection,
                max_selection,
                status,
                created_at
            ) VALUES (
                :company_id,
                :name,
                :description,
                :is_required,
                :min_selection,
                :max_selection,
                'ativo',
                NOW()
            )
        ");
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function linkProductToAdditionalGroup(int $companyId, int $productId, int $additionalGroupId): void
    {
        $stmt = $this->db()->prepare("
            INSERT INTO product_additional_groups (
                company_id,
                product_id,
                additional_group_id
            ) VALUES (
                :company_id,
                :product_id,
                :additional_group_id
            )
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'product_id' => $productId,
            'additional_group_id' => $additionalGroupId,
        ]);
    }

    public function updateAdditionalGroupRules(
        int $companyId,
        int $additionalGroupId,
        bool $isRequired,
        ?int $minSelection,
        ?int $maxSelection
    ): void {
        $stmt = $this->db()->prepare("
            UPDATE additional_groups
            SET is_required = :is_required,
                min_selection = :min_selection,
                max_selection = :max_selection,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
        ");
        $stmt->execute([
            'is_required' => $isRequired ? 1 : 0,
            'min_selection' => $minSelection,
            'max_selection' => $maxSelection,
            'company_id' => $companyId,
            'id' => $additionalGroupId,
        ]);
    }

    public function additionalItemsByGroup(int $companyId, int $additionalGroupId): array
    {
        $stmt = $this->db()->prepare("
            SELECT
                id,
                company_id,
                additional_group_id,
                name,
                description,
                price,
                status,
                display_order
            FROM additional_items
            WHERE company_id = :company_id
              AND additional_group_id = :additional_group_id
            ORDER BY display_order ASC, name ASC
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'additional_group_id' => $additionalGroupId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createAdditionalItem(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO additional_items (
                company_id,
                additional_group_id,
                name,
                description,
                price,
                status,
                display_order,
                created_at
            ) VALUES (
                :company_id,
                :additional_group_id,
                :name,
                :description,
                :price,
                'ativo',
                :display_order,
                NOW()
            )
        ");
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function updateAdditionalItem(int $companyId, int $additionalItemId, array $data): void
    {
        $stmt = $this->db()->prepare("
            UPDATE additional_items
            SET name = :name,
                description = :description,
                price = :price,
                display_order = :display_order,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
        ");
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'],
            'price' => $data['price'],
            'display_order' => $data['display_order'],
            'company_id' => $companyId,
            'id' => $additionalItemId,
        ]);
    }

    public function deactivateAdditionalItem(int $companyId, int $additionalItemId): void
    {
        $this->updateAdditionalItemStatus($companyId, $additionalItemId, 'inativo');
    }

    public function updateAdditionalItemStatus(int $companyId, int $additionalItemId, string $status): void
    {
        $stmt = $this->db()->prepare("
            UPDATE additional_items
            SET status = :status,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
        ");
        $stmt->execute([
            'status' => $status,
            'company_id' => $companyId,
            'id' => $additionalItemId,
        ]);
    }

    public function deleteAdditionalItem(int $companyId, int $additionalItemId): void
    {
        $stmt = $this->db()->prepare("
            DELETE FROM additional_items
            WHERE company_id = :company_id
              AND id = :id
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $additionalItemId,
        ]);
    }

    public function countAdditionalItemsByGroup(int $companyId, int $additionalGroupId): int
    {
        $stmt = $this->db()->prepare("
            SELECT COUNT(*)
            FROM additional_items
            WHERE company_id = :company_id
              AND additional_group_id = :additional_group_id
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'additional_group_id' => $additionalGroupId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function countOrderUsageByAdditionalItem(int $companyId, int $additionalItemId): int
    {
        $stmt = $this->db()->prepare("
            SELECT COUNT(*)
            FROM order_item_additionals
            WHERE company_id = :company_id
              AND additional_item_id = :additional_item_id
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'additional_item_id' => $additionalItemId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function findAdditionalItemByIdForProduct(int $companyId, int $productId, int $additionalItemId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                ai.id,
                ai.company_id,
                ai.additional_group_id,
                ai.name,
                ai.description,
                ai.price,
                ai.status,
                ai.display_order
            FROM additional_items ai
            INNER JOIN product_additional_groups pag
                ON pag.additional_group_id = ai.additional_group_id
               AND pag.company_id = ai.company_id
            WHERE ai.company_id = :company_id
              AND pag.product_id = :product_id
              AND ai.id = :id
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'product_id' => $productId,
            'id' => $additionalItemId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function activeAdditionalCatalogByProductIds(int $companyId, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $params = ['company_id' => $companyId];
        $placeholders = [];

        foreach ($productIds as $index => $productId) {
            $key = 'product_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $productId;
        }

        $sql = "
            SELECT
                pag.product_id,
                ag.id AS additional_group_id,
                ag.is_required,
                ag.min_selection,
                ag.max_selection,
                ai.id AS additional_item_id,
                ai.name AS additional_name,
                ai.price AS additional_price
            FROM products p
            INNER JOIN product_additional_groups pag
                ON pag.product_id = p.id
               AND pag.company_id = p.company_id
            INNER JOIN additional_groups ag
                ON ag.id = pag.additional_group_id
               AND ag.company_id = pag.company_id
               AND ag.status = 'ativo'
            INNER JOIN additional_items ai
                ON ai.additional_group_id = ag.id
               AND ai.company_id = ag.company_id
               AND ai.status = 'ativo'
            WHERE p.company_id = :company_id
              AND p.deleted_at IS NULL
              AND p.has_additionals = 1
              AND pag.product_id IN (" . implode(', ', $placeholders) . ")
            ORDER BY pag.product_id ASC, ai.display_order ASC, ai.name ASC
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
