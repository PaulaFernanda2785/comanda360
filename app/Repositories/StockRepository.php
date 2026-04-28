<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOException;

final class StockRepository extends BaseRepository
{
    private array $tableExistsCache = [];
    private array $columnExistsCache = [];

    public function tableExists(string $table): bool
    {
        $table = trim($table);
        if ($table === '') {
            return false;
        }

        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        try {
            $stmt = $this->db()->prepare("
                SELECT COUNT(*)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
            ");
            $stmt->execute(['table' => $table]);
            $exists = ((int) $stmt->fetchColumn()) > 0;
        } catch (PDOException) {
            $exists = false;
        }

        $this->tableExistsCache[$table] = $exists;
        return $exists;
    }

    public function columnExists(string $table, string $column): bool
    {
        $table = trim($table);
        $column = trim($column);
        if ($table === '' || $column === '') {
            return false;
        }

        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnExistsCache)) {
            return $this->columnExistsCache[$key];
        }

        try {
            $stmt = $this->db()->prepare("
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
                  AND COLUMN_NAME = :column
            ");
            $stmt->execute([
                'table' => $table,
                'column' => $column,
            ]);
            $exists = ((int) $stmt->fetchColumn()) > 0;
        } catch (PDOException) {
            $exists = false;
        }

        $this->columnExistsCache[$key] = $exists;
        return $exists;
    }

    public function listItemsPaginated(int $companyId, array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        [$whereSql, $params] = $this->buildItemWhere($companyId, $filters);

        $countStmt = $this->db()->prepare("
            SELECT COUNT(DISTINCT si.id)
            FROM stock_items si
            LEFT JOIN stock_item_products sip
                ON sip.stock_item_id = si.id
               AND sip.company_id = si.company_id
            LEFT JOIN products p
                ON p.id = sip.product_id
               AND p.company_id = si.company_id
               AND p.deleted_at IS NULL
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db()->prepare("
            SELECT
                si.id,
                si.company_id,
                si.product_id,
                si.name,
                si.sku,
                si.current_quantity,
                si.minimum_quantity,
                si.unit_of_measure,
                si.status,
                si.created_at,
                si.updated_at,
                GROUP_CONCAT(DISTINCT CAST(p.id AS CHAR) ORDER BY p.name ASC SEPARATOR ',') AS linked_product_ids,
                GROUP_CONCAT(DISTINCT p.name ORDER BY p.name ASC SEPARATOR '||') AS linked_product_names,
                COUNT(DISTINCT p.id) AS linked_products_count,
                CASE
                    WHEN si.current_quantity <= 0 THEN 'out'
                    WHEN si.minimum_quantity IS NOT NULL AND si.current_quantity <= si.minimum_quantity THEN 'low'
                    ELSE 'normal'
                END AS stock_alert
            FROM stock_items si
            LEFT JOIN stock_item_products sip
                ON sip.stock_item_id = si.id
               AND sip.company_id = si.company_id
            LEFT JOIN products p
                ON p.id = sip.product_id
               AND p.company_id = si.company_id
               AND p.deleted_at IS NULL
            WHERE {$whereSql}
            GROUP BY
                si.id,
                si.company_id,
                si.product_id,
                si.name,
                si.sku,
                si.current_quantity,
                si.minimum_quantity,
                si.unit_of_measure,
                si.status,
                si.created_at,
                si.updated_at
            ORDER BY
                CASE
                    WHEN si.current_quantity <= 0 THEN 0
                    WHEN si.minimum_quantity IS NOT NULL AND si.current_quantity <= si.minimum_quantity THEN 1
                    ELSE 2
                END,
                CASE WHEN si.status = 'ativo' THEN 0 ELSE 1 END,
                si.name ASC,
                si.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function listMovementsPaginated(int $companyId, array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        [$whereSql, $params] = $this->buildMovementWhere($companyId, $filters);

        $countStmt = $this->db()->prepare("
            SELECT COUNT(*)
            FROM stock_movements sm
            INNER JOIN stock_items si ON si.id = sm.stock_item_id
            LEFT JOIN users u ON u.id = sm.moved_by_user_id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db()->prepare("
            SELECT
                sm.id,
                sm.company_id,
                sm.stock_item_id,
                sm.type,
                sm.quantity,
                sm.reason,
                sm.reference_type,
                sm.reference_id,
                sm.moved_by_user_id,
                sm.moved_at,
                sm.created_at,
                si.name AS stock_item_name,
                si.sku AS stock_item_sku,
                si.unit_of_measure,
                u.name AS moved_by_user_name
            FROM stock_movements sm
            INNER JOIN stock_items si ON si.id = sm.stock_item_id
            LEFT JOIN users u ON u.id = sm.moved_by_user_id
            WHERE {$whereSql}
            ORDER BY sm.moved_at DESC, sm.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function summary(int $companyId): array
    {
        $itemsStmt = $this->db()->prepare("
            SELECT
                COUNT(*) AS total_items,
                SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) AS active_items,
                SUM(CASE WHEN status = 'inativo' THEN 1 ELSE 0 END) AS inactive_items,
                SUM(CASE WHEN current_quantity <= 0 THEN 1 ELSE 0 END) AS out_of_stock_items,
                SUM(CASE WHEN minimum_quantity IS NOT NULL AND current_quantity > 0 AND current_quantity <= minimum_quantity THEN 1 ELSE 0 END) AS low_stock_items,
                MAX(COALESCE(updated_at, created_at)) AS last_item_update_at,
                (
                    SELECT COUNT(DISTINCT sip.product_id)
                    FROM stock_item_products sip
                    INNER JOIN products p
                        ON p.id = sip.product_id
                       AND p.company_id = sip.company_id
                       AND p.deleted_at IS NULL
                    WHERE sip.company_id = :company_id_links
                ) AS linked_products,
                (
                    SELECT COUNT(DISTINCT sip.stock_item_id)
                    FROM stock_item_products sip
                    INNER JOIN products p
                        ON p.id = sip.product_id
                       AND p.company_id = sip.company_id
                       AND p.deleted_at IS NULL
                    WHERE sip.company_id = :company_id_linked_items
                ) AS linked_items
            FROM stock_items
            WHERE company_id = :company_id_items
        ");
        $itemsStmt->execute([
            'company_id_items' => $companyId,
            'company_id_links' => $companyId,
            'company_id_linked_items' => $companyId,
        ]);
        $items = $itemsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $movementsStmt = $this->db()->prepare("
            SELECT
                COUNT(*) AS total_movements,
                SUM(CASE WHEN type = 'entry' THEN 1 ELSE 0 END) AS entry_count,
                SUM(CASE WHEN type = 'exit' THEN 1 ELSE 0 END) AS exit_count,
                SUM(CASE WHEN type = 'adjustment' THEN 1 ELSE 0 END) AS adjustment_count,
                MAX(moved_at) AS last_moved_at
            FROM stock_movements
            WHERE company_id = :company_id
        ");
        $movementsStmt->execute(['company_id' => $companyId]);
        $movements = $movementsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return array_merge($items, $movements);
    }

    public function listProductsForLink(int $companyId): array
    {
        $stmt = $this->db()->prepare("
            SELECT
                p.id,
                p.name,
                p.sku,
                c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.company_id = :company_id
              AND p.deleted_at IS NULL
            ORDER BY p.name ASC, p.id ASC
        ");
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listActiveItemsForRecipe(int $companyId): array
    {
        $stmt = $this->db()->prepare("
            SELECT
                id,
                name,
                sku,
                unit_of_measure,
                current_quantity
            FROM stock_items
            WHERE company_id = :company_id
              AND status = 'ativo'
            ORDER BY name ASC, id ASC
        ");
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listRecipeRows(int $companyId): array
    {
        if (!$this->tableExists('stock_recipe_items')) {
            return [];
        }

        $consumptionUnitSelect = $this->columnExists('stock_recipe_items', 'consumption_unit')
            ? 'sri.consumption_unit'
            : 'si.unit_of_measure AS consumption_unit';

        $stmt = $this->db()->prepare("
            SELECT
                sri.id,
                sri.company_id,
                sri.product_id,
                p.name AS product_name,
                sri.stock_item_id,
                si.name AS stock_item_name,
                si.unit_of_measure,
                si.current_quantity,
                si.minimum_quantity,
                si.status AS stock_item_status,
                {$consumptionUnitSelect},
                sri.quantity_per_unit,
                sri.waste_percent,
                sri.status,
                sri.updated_at
            FROM stock_recipe_items sri
            INNER JOIN products p
                ON p.id = sri.product_id
               AND p.company_id = sri.company_id
               AND p.deleted_at IS NULL
            INNER JOIN stock_items si
                ON si.id = sri.stock_item_id
               AND si.company_id = sri.company_id
            WHERE sri.company_id = :company_id
            ORDER BY p.name ASC, si.name ASC, sri.id ASC
        ");
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listActiveRecipeRowsForProducts(int $companyId, array $productIds): array
    {
        if (!$this->tableExists('stock_recipe_items')) {
            return [];
        }

        $ids = array_values(array_unique(array_map(static fn (mixed $value): int => (int) $value, $productIds)));
        $ids = array_values(array_filter($ids, static fn (int $value): bool => $value > 0));
        if ($ids === []) {
            return [];
        }

        $params = ['company_id' => $companyId];
        $placeholders = [];
        foreach ($ids as $index => $productId) {
            $key = 'product_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $productId;
        }

        $consumptionUnitSelect = $this->columnExists('stock_recipe_items', 'consumption_unit')
            ? 'sri.consumption_unit'
            : 'si.unit_of_measure AS consumption_unit';

        $stmt = $this->db()->prepare("
            SELECT
                sri.product_id,
                sri.stock_item_id,
                sri.quantity_per_unit,
                sri.waste_percent,
                {$consumptionUnitSelect},
                si.name AS stock_item_name,
                si.unit_of_measure,
                si.current_quantity,
                si.status AS stock_item_status
            FROM stock_recipe_items sri
            INNER JOIN stock_items si
                ON si.id = sri.stock_item_id
               AND si.company_id = sri.company_id
            WHERE sri.company_id = :company_id
              AND sri.status = 'ativo'
              AND sri.product_id IN (" . implode(', ', $placeholders) . ")
            ORDER BY sri.product_id ASC, sri.id ASC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listSoldProductsWithoutAutomaticConsumption(int $companyId, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        if (!$this->tableExists('stock_recipe_items') || !$this->tableExists('stock_consumptions')) {
            return [];
        }

        $stmt = $this->db()->prepare("
            SELECT
                oi.product_id,
                oi.product_name_snapshot AS product_name,
                COUNT(DISTINCT oi.id) AS sold_lines,
                SUM(oi.quantity) AS sold_quantity,
                MAX(o.created_at) AS last_sold_at,
                CASE
                    WHEN COUNT(DISTINCT sri.id) = 0 THEN 'missing_recipe'
                    ELSE 'missing_consumption'
                END AS issue_type
            FROM order_items oi
            INNER JOIN orders o
                ON o.id = oi.order_id
               AND o.company_id = oi.company_id
            LEFT JOIN stock_recipe_items sri
                ON sri.company_id = oi.company_id
               AND sri.product_id = oi.product_id
               AND sri.status = 'ativo'
            LEFT JOIN stock_consumptions sc
                ON sc.company_id = oi.company_id
               AND sc.order_item_id = oi.id
            WHERE oi.company_id = :company_id
              AND oi.status = 'active'
              AND o.status = 'finished'
              AND o.payment_status = 'paid'
              AND sc.id IS NULL
            GROUP BY oi.product_id, oi.product_name_snapshot
            ORDER BY last_sold_at DESC, sold_quantity DESC
            LIMIT {$limit}
        ");
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listAutomaticStockProductsPaginated(int $companyId, array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        if (!$this->tableExists('stock_recipe_items') || !$this->tableExists('stock_consumptions')) {
            return [
                'items' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => 1,
            ];
        }

        [$whereSql, $params] = $this->buildAutomaticStockWhere($companyId, $filters);
        $baseSql = "
            FROM products p
            LEFT JOIN categories c
                ON c.id = p.category_id
            LEFT JOIN (
                SELECT
                    company_id,
                    product_id,
                    COUNT(*) AS recipe_rows_count
                FROM stock_recipe_items
                WHERE company_id = :recipe_company_id
                  AND status = 'ativo'
                GROUP BY company_id, product_id
            ) recipe
                ON recipe.company_id = p.company_id
               AND recipe.product_id = p.id
            LEFT JOIN (
                SELECT
                    oi.company_id,
                    oi.product_id,
                    COUNT(DISTINCT oi.id) AS sold_lines,
                    SUM(oi.quantity) AS sold_quantity,
                    MAX(o.created_at) AS last_sold_at,
                    COUNT(DISTINCT CASE WHEN sc.id IS NULL THEN oi.id END) AS pending_consumption_lines
                FROM order_items oi
                INNER JOIN orders o
                    ON o.id = oi.order_id
                   AND o.company_id = oi.company_id
                   AND o.status = 'finished'
                   AND o.payment_status = 'paid'
                LEFT JOIN stock_consumptions sc
                    ON sc.company_id = oi.company_id
                   AND sc.order_item_id = oi.id
                WHERE oi.company_id = :sales_company_id
                  AND oi.status = 'active'
                GROUP BY oi.company_id, oi.product_id
            ) sales
                ON sales.company_id = p.company_id
               AND sales.product_id = p.id
            WHERE {$whereSql}
        ";

        $countStmt = $this->db()->prepare("
            SELECT COUNT(*)
            FROM (
                SELECT p.id
                {$baseSql}
            ) automatic_stock_rows
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db()->prepare("
            SELECT
                p.id AS product_id,
                p.name AS product_name,
                p.sku,
                c.name AS category_name,
                COALESCE(recipe.recipe_rows_count, 0) AS recipe_rows_count,
                COALESCE(sales.sold_lines, 0) AS sold_lines,
                COALESCE(sales.sold_quantity, 0) AS sold_quantity,
                sales.last_sold_at,
                COALESCE(sales.pending_consumption_lines, 0) AS pending_consumption_lines,
                CASE
                    WHEN COALESCE(recipe.recipe_rows_count, 0) = 0 THEN 'missing_recipe'
                    WHEN COALESCE(sales.pending_consumption_lines, 0) > 0 THEN 'missing_consumption'
                    ELSE 'configured'
                END AS issue_type
            {$baseSql}
            ORDER BY
                CASE WHEN COALESCE(sales.pending_consumption_lines, 0) > 0 THEN 0 ELSE 1 END ASC,
                sales.last_sold_at DESC,
                p.name ASC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function syncProductRecipe(int $companyId, int $productId, array $rows): void
    {
        if (!$this->tableExists('stock_recipe_items')) {
            return;
        }

        $deleteStmt = $this->db()->prepare("
            DELETE FROM stock_recipe_items
            WHERE company_id = :company_id
              AND product_id = :product_id
        ");
        $deleteStmt->execute([
            'company_id' => $companyId,
            'product_id' => $productId,
        ]);

        if ($rows === []) {
            return;
        }

        $hasConsumptionUnit = $this->columnExists('stock_recipe_items', 'consumption_unit');
        $unitColumnSql = $hasConsumptionUnit ? "consumption_unit,\n                " : '';
        $unitValueSql = $hasConsumptionUnit ? ":consumption_unit,\n                " : '';

        $insertStmt = $this->db()->prepare("
            INSERT INTO stock_recipe_items (
                company_id,
                product_id,
                stock_item_id,
                quantity_per_unit,
                {$unitColumnSql}
                waste_percent,
                status,
                created_at,
                updated_at
            ) VALUES (
                :company_id,
                :product_id,
                :stock_item_id,
                :quantity_per_unit,
                {$unitValueSql}
                :waste_percent,
                'ativo',
                NOW(),
                NOW()
            )
        ");

        foreach ($rows as $row) {
            $params = [
                'company_id' => $companyId,
                'product_id' => $productId,
                'stock_item_id' => (int) $row['stock_item_id'],
                'quantity_per_unit' => round((float) $row['quantity_per_unit'], 3),
                'waste_percent' => round((float) ($row['waste_percent'] ?? 0), 2),
            ];
            if ($hasConsumptionUnit) {
                $params['consumption_unit'] = (string) ($row['consumption_unit'] ?? 'un');
            }

            $insertStmt->execute($params);
        }
    }

    public function findItemById(int $companyId, int $itemId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                si.id,
                si.company_id,
                si.product_id,
                si.name,
                si.sku,
                si.current_quantity,
                si.minimum_quantity,
                si.unit_of_measure,
                si.status,
                si.created_at,
                si.updated_at,
                GROUP_CONCAT(DISTINCT CAST(p.id AS CHAR) ORDER BY p.name ASC SEPARATOR ',') AS linked_product_ids,
                GROUP_CONCAT(DISTINCT p.name ORDER BY p.name ASC SEPARATOR '||') AS linked_product_names,
                COUNT(DISTINCT p.id) AS linked_products_count
            FROM stock_items si
            LEFT JOIN stock_item_products sip
                ON sip.stock_item_id = si.id
               AND sip.company_id = si.company_id
            LEFT JOIN products p
                ON p.id = sip.product_id
               AND p.company_id = si.company_id
               AND p.deleted_at IS NULL
            WHERE si.company_id = :company_id
              AND si.id = :item_id
            GROUP BY
                si.id,
                si.company_id,
                si.product_id,
                si.name,
                si.sku,
                si.current_quantity,
                si.minimum_quantity,
                si.unit_of_measure,
                si.status,
                si.created_at,
                si.updated_at
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'item_id' => $itemId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findItemByIdForUpdate(int $companyId, int $itemId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                id,
                company_id,
                name,
                current_quantity,
                unit_of_measure,
                status
            FROM stock_items
            WHERE company_id = :company_id
              AND id = :item_id
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'item_id' => $itemId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findBySku(int $companyId, string $sku, ?int $exceptItemId = null): ?array
    {
        $sql = "
            SELECT id, company_id, sku
            FROM stock_items
            WHERE company_id = :company_id
              AND sku = :sku
        ";
        $params = [
            'company_id' => $companyId,
            'sku' => $sku,
        ];

        if (($exceptItemId ?? 0) > 0) {
            $sql .= ' AND id <> :except_item_id';
            $params['except_item_id'] = $exceptItemId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function createItem(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO stock_items (
                company_id,
                product_id,
                name,
                sku,
                current_quantity,
                minimum_quantity,
                unit_of_measure,
                status,
                created_at,
                updated_at
            ) VALUES (
                :company_id,
                :product_id,
                :name,
                :sku,
                :current_quantity,
                :minimum_quantity,
                :unit_of_measure,
                :status,
                NOW(),
                NOW()
            )
        ");
        $stmt->execute([
            'company_id' => $data['company_id'],
            'product_id' => $data['product_id'],
            'name' => $data['name'],
            'sku' => $data['sku'],
            'current_quantity' => $data['current_quantity'],
            'minimum_quantity' => $data['minimum_quantity'],
            'unit_of_measure' => $data['unit_of_measure'],
            'status' => $data['status'],
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function updateItem(int $companyId, int $itemId, array $data): void
    {
        $stmt = $this->db()->prepare("
            UPDATE stock_items
            SET
                product_id = :product_id,
                name = :name,
                sku = :sku,
                minimum_quantity = :minimum_quantity,
                unit_of_measure = :unit_of_measure,
                status = :status,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :item_id
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'item_id' => $itemId,
            'product_id' => $data['product_id'],
            'name' => $data['name'],
            'sku' => $data['sku'],
            'minimum_quantity' => $data['minimum_quantity'],
            'unit_of_measure' => $data['unit_of_measure'],
            'status' => $data['status'],
        ]);
    }

    public function syncItemProducts(int $companyId, int $itemId, array $productIds): void
    {
        $deleteStmt = $this->db()->prepare("
            DELETE FROM stock_item_products
            WHERE company_id = :company_id
              AND stock_item_id = :item_id
        ");
        $deleteStmt->execute([
            'company_id' => $companyId,
            'item_id' => $itemId,
        ]);

        if ($productIds !== []) {
            $insertStmt = $this->db()->prepare("
                INSERT INTO stock_item_products (
                    company_id,
                    stock_item_id,
                    product_id,
                    created_at
                ) VALUES (
                    :company_id,
                    :stock_item_id,
                    :product_id,
                    NOW()
                )
            ");

            foreach ($productIds as $productId) {
                $insertStmt->execute([
                    'company_id' => $companyId,
                    'stock_item_id' => $itemId,
                    'product_id' => (int) $productId,
                ]);
            }
        }

        $legacyProductId = $productIds !== [] ? (int) reset($productIds) : null;
        $legacyStmt = $this->db()->prepare("
            UPDATE stock_items
            SET product_id = :product_id,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :item_id
            LIMIT 1
        ");
        $legacyStmt->execute([
            'company_id' => $companyId,
            'item_id' => $itemId,
            'product_id' => $legacyProductId,
        ]);
    }

    public function updateCurrentQuantity(int $companyId, int $itemId, float $quantity): void
    {
        $stmt = $this->db()->prepare("
            UPDATE stock_items
            SET
                current_quantity = :current_quantity,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :item_id
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'item_id' => $itemId,
            'current_quantity' => round($quantity, 3),
        ]);
    }

    public function createMovement(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO stock_movements (
                company_id,
                stock_item_id,
                type,
                quantity,
                reason,
                reference_type,
                reference_id,
                moved_by_user_id,
                moved_at,
                created_at
            ) VALUES (
                :company_id,
                :stock_item_id,
                :type,
                :quantity,
                :reason,
                :reference_type,
                :reference_id,
                :moved_by_user_id,
                :moved_at,
                NOW()
            )
        ");
        $stmt->execute([
            'company_id' => $data['company_id'],
            'stock_item_id' => $data['stock_item_id'],
            'type' => $data['type'],
            'quantity' => $data['quantity'],
            'reason' => $data['reason'],
            'reference_type' => $data['reference_type'],
            'reference_id' => $data['reference_id'],
            'moved_by_user_id' => $data['moved_by_user_id'],
            'moved_at' => $data['moved_at'],
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function hasConsumptionForOrder(int $companyId, int $orderId): bool
    {
        if (!$this->tableExists('stock_consumptions')) {
            return false;
        }

        $stmt = $this->db()->prepare("
            SELECT 1
            FROM stock_consumptions
            WHERE company_id = :company_id
              AND order_id = :order_id
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'order_id' => $orderId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function createConsumption(array $data): int
    {
        if (!$this->tableExists('stock_consumptions')) {
            return 0;
        }

        $stmt = $this->db()->prepare("
            INSERT INTO stock_consumptions (
                company_id,
                order_id,
                order_item_id,
                product_id,
                stock_item_id,
                stock_movement_id,
                quantity,
                created_at
            ) VALUES (
                :company_id,
                :order_id,
                :order_item_id,
                :product_id,
                :stock_item_id,
                :stock_movement_id,
                :quantity,
                NOW()
            )
        ");
        $stmt->execute([
            'company_id' => $data['company_id'],
            'order_id' => $data['order_id'],
            'order_item_id' => $data['order_item_id'],
            'product_id' => $data['product_id'],
            'stock_item_id' => $data['stock_item_id'],
            'stock_movement_id' => $data['stock_movement_id'],
            'quantity' => $data['quantity'],
        ]);

        return (int) $this->db()->lastInsertId();
    }

    private function buildItemWhere(int $companyId, array $filters): array
    {
        $where = ['si.company_id = :company_id'];
        $params = ['company_id' => $companyId];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = "(
                LOWER(COALESCE(si.name, '')) LIKE :search
                OR LOWER(COALESCE(si.sku, '')) LIKE :search
                OR LOWER(COALESCE(p.name, '')) LIKE :search
                OR CAST(si.id AS CHAR) = :item_id_search
            )";
            $params['search'] = '%' . strtolower($search) . '%';
            $params['item_id_search'] = $search;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'si.status = :status';
            $params['status'] = $status;
        }

        $alert = trim((string) ($filters['alert'] ?? ''));
        if ($alert === 'low') {
            $where[] = 'si.minimum_quantity IS NOT NULL AND si.current_quantity > 0 AND si.current_quantity <= si.minimum_quantity';
        } elseif ($alert === 'out') {
            $where[] = 'si.current_quantity <= 0';
        }

        return [implode(' AND ', $where), $params];
    }

    private function buildMovementWhere(int $companyId, array $filters): array
    {
        $where = ['sm.company_id = :company_id'];
        $params = ['company_id' => $companyId];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = "(
                LOWER(COALESCE(si.name, '')) LIKE :search
                OR LOWER(COALESCE(si.sku, '')) LIKE :search
                OR LOWER(COALESCE(sm.reason, '')) LIKE :search
                OR CAST(sm.id AS CHAR) = :movement_id_search
            )";
            $params['search'] = '%' . strtolower($search) . '%';
            $params['movement_id_search'] = $search;
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $where[] = 'sm.type = :type';
            $params['type'] = $type;
        }

        return [implode(' AND ', $where), $params];
    }

    private function buildAutomaticStockWhere(int $companyId, array $filters): array
    {
        $where = [
            'p.company_id = :company_id',
            'p.deleted_at IS NULL',
            '(COALESCE(recipe.recipe_rows_count, 0) > 0 OR COALESCE(sales.sold_lines, 0) > 0)',
        ];
        $params = [
            'company_id' => $companyId,
            'recipe_company_id' => $companyId,
            'sales_company_id' => $companyId,
        ];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = "(
                LOWER(COALESCE(p.name, '')) LIKE :search
                OR LOWER(COALESCE(p.sku, '')) LIKE :search
                OR CAST(p.id AS CHAR) = :product_id_search
            )";
            $params['search'] = '%' . strtolower($search) . '%';
            $params['product_id_search'] = $search;
        }

        $issue = trim((string) ($filters['issue'] ?? ''));
        if ($issue === 'missing_recipe') {
            $where[] = 'COALESCE(recipe.recipe_rows_count, 0) = 0';
            $where[] = 'COALESCE(sales.sold_lines, 0) > 0';
        } elseif ($issue === 'missing_consumption') {
            $where[] = 'COALESCE(recipe.recipe_rows_count, 0) > 0';
            $where[] = 'COALESCE(sales.pending_consumption_lines, 0) > 0';
        } elseif ($issue === 'with_recipe') {
            $where[] = 'COALESCE(recipe.recipe_rows_count, 0) > 0';
        } elseif ($issue === 'configured') {
            $where[] = 'COALESCE(recipe.recipe_rows_count, 0) > 0';
            $where[] = 'COALESCE(sales.pending_consumption_lines, 0) = 0';
        }

        return [
            implode(' AND ', $where),
            $params,
        ];
    }
}
