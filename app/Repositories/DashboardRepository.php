<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DashboardRepository extends BaseRepository
{
    private const SYSTEM_COMPANY_ROLE_SLUGS = [
        'admin_establishment',
        'manager',
        'cashier',
        'waiter',
        'kitchen',
        'delivery',
    ];

    private const COMPANY_PERMISSION_MODULES = [
        'dashboard',
        'products',
        'categories',
        'additionals',
        'tables',
        'commands',
        'orders',
        'payments',
        'cash_registers',
        'reports',
        'users',
        'settings',
        'themes',
    ];

    public function transaction(callable $callback): mixed
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            $result = $callback();
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function findCompanyProfileWithTheme(int $companyId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                c.id,
                c.name,
                c.email,
                c.phone,
                ct.primary_color,
                ct.secondary_color,
                ct.accent_color,
                ct.logo_path,
                ct.banner_path,
                ct.title,
                ct.description,
                ct.footer_text
            FROM companies c
            LEFT JOIN company_themes ct
                ON ct.company_id = c.id
            WHERE c.id = :company_id
            LIMIT 1
        ");
        $stmt->execute(['company_id' => $companyId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateCompanyName(int $companyId, string $name): void
    {
        $stmt = $this->db()->prepare("
            UPDATE companies
            SET name = :name,
                updated_at = NOW()
            WHERE id = :company_id
        ");
        $stmt->execute([
            'name' => $name,
            'company_id' => $companyId,
        ]);
    }

    public function upsertCompanyTheme(int $companyId, array $theme): void
    {
        $stmt = $this->db()->prepare("
            INSERT INTO company_themes (
                company_id,
                primary_color,
                secondary_color,
                accent_color,
                logo_path,
                banner_path,
                title,
                description,
                footer_text,
                created_at,
                updated_at
            ) VALUES (
                :company_id,
                :primary_color,
                :secondary_color,
                :accent_color,
                :logo_path,
                :banner_path,
                :title,
                :description,
                :footer_text,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                primary_color = VALUES(primary_color),
                secondary_color = VALUES(secondary_color),
                accent_color = VALUES(accent_color),
                logo_path = VALUES(logo_path),
                banner_path = VALUES(banner_path),
                title = VALUES(title),
                description = VALUES(description),
                footer_text = VALUES(footer_text),
                updated_at = NOW()
        ");

        $stmt->execute([
            'company_id' => $companyId,
            'primary_color' => $theme['primary_color'] ?? null,
            'secondary_color' => $theme['secondary_color'] ?? null,
            'accent_color' => $theme['accent_color'] ?? null,
            'logo_path' => $theme['logo_path'] ?? null,
            'banner_path' => $theme['banner_path'] ?? null,
            'title' => $theme['title'] ?? null,
            'description' => $theme['description'] ?? null,
            'footer_text' => $theme['footer_text'] ?? null,
        ]);
    }

    public function findExistingReportViews(array $viewNames): array
    {
        if ($viewNames === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($viewNames as $index => $viewName) {
            $key = 'view_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = strtolower(trim((string) $viewName));
        }

        $sql = "
            SELECT LOWER(table_name) AS view_name
            FROM information_schema.views
            WHERE table_schema = DATABASE()
              AND LOWER(table_name) IN (" . implode(', ', $placeholders) . ")
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $existing = [];
        foreach ($rows as $row) {
            $name = strtolower((string) ($row['view_name'] ?? ''));
            if ($name !== '') {
                $existing[$name] = true;
            }
        }

        return array_keys($existing);
    }

    public function salesKpis(
        int $companyId,
        string $startDate,
        string $endDate,
        ?string $status,
        ?string $channel,
        ?string $paymentStatus,
        ?float $minAmount,
        ?float $maxAmount,
        ?string $search
    ): array
    {
        [$whereSql, $params] = $this->buildSalesWhere(
            $companyId,
            $startDate,
            $endDate,
            $status,
            $channel,
            $paymentStatus,
            $minAmount,
            $maxAmount,
            $search
        );

        $stmt = $this->db()->prepare("
            SELECT
                COUNT(*) AS total_orders,
                COALESCE(SUM(CASE WHEN status <> 'canceled' THEN total_amount ELSE 0 END), 0) AS total_sales,
                COALESCE(AVG(CASE WHEN status <> 'canceled' THEN total_amount END), 0) AS avg_ticket,
                COALESCE(SUM(CASE WHEN status = 'canceled' THEN 1 ELSE 0 END), 0) AS canceled_orders
            FROM vw_relatorio_vendas_pedidos
            WHERE {$whereSql}
        ");
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    public function salesByDay(
        int $companyId,
        string $startDate,
        string $endDate,
        ?string $status,
        ?string $channel,
        ?string $paymentStatus,
        ?float $minAmount,
        ?float $maxAmount,
        ?string $search,
        int $limit = 14
    ): array
    {
        $limit = max(1, min(60, $limit));
        [$whereSql, $params] = $this->buildSalesWhere(
            $companyId,
            $startDate,
            $endDate,
            $status,
            $channel,
            $paymentStatus,
            $minAmount,
            $maxAmount,
            $search
        );

        $stmt = $this->db()->prepare("
            SELECT
                DATE(created_at) AS report_date,
                COUNT(*) AS total_orders,
                COALESCE(SUM(CASE WHEN status <> 'canceled' THEN total_amount ELSE 0 END), 0) AS total_sales
            FROM vw_relatorio_vendas_pedidos
            WHERE {$whereSql}
            GROUP BY DATE(created_at)
            ORDER BY report_date DESC
            LIMIT {$limit}
        ");
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_reverse($rows);
    }

    public function ordersByStatus(
        int $companyId,
        string $startDate,
        string $endDate,
        ?string $status,
        ?string $channel,
        ?string $paymentStatus,
        ?float $minAmount,
        ?float $maxAmount,
        ?string $search
    ): array
    {
        [$whereSql, $params] = $this->buildSalesWhere(
            $companyId,
            $startDate,
            $endDate,
            $status,
            $channel,
            $paymentStatus,
            $minAmount,
            $maxAmount,
            $search
        );

        $stmt = $this->db()->prepare("
            SELECT
                status,
                COUNT(*) AS total_orders,
                COALESCE(SUM(total_amount), 0) AS total_amount
            FROM vw_relatorio_vendas_pedidos
            WHERE {$whereSql}
            GROUP BY status
            ORDER BY total_orders DESC, status ASC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function salesByChannel(
        int $companyId,
        string $startDate,
        string $endDate,
        ?string $status,
        ?string $channel,
        ?string $paymentStatus,
        ?float $minAmount,
        ?float $maxAmount,
        ?string $search
    ): array
    {
        [$whereSql, $params] = $this->buildSalesWhere(
            $companyId,
            $startDate,
            $endDate,
            $status,
            $channel,
            $paymentStatus,
            $minAmount,
            $maxAmount,
            $search
        );

        $stmt = $this->db()->prepare("
            SELECT
                channel,
                COUNT(*) AS total_orders,
                COALESCE(SUM(CASE WHEN status <> 'canceled' THEN total_amount ELSE 0 END), 0) AS total_sales
            FROM vw_relatorio_vendas_pedidos
            WHERE {$whereSql}
            GROUP BY channel
            ORDER BY total_sales DESC, channel ASC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function cashClosingKpis(int $companyId, string $startDate, string $endDate): array
    {
        $stmt = $this->db()->prepare("
            SELECT
                COUNT(*) AS total_closings,
                COALESCE(SUM(closing_amount_calculated), 0) AS total_closed_amount,
                COALESCE(SUM(ABS(COALESCE(closing_amount_reported, 0) - COALESCE(closing_amount_calculated, 0))), 0) AS total_difference
            FROM vw_fechamento_caixa_resumo
            WHERE company_id = :company_id
              AND DATE(opened_at) BETWEEN :start_date AND :end_date
              AND cash_register_status = 'closed'
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    public function cashClosingHistory(int $companyId, string $startDate, string $endDate, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));

        $stmt = $this->db()->prepare("
            SELECT
                cash_register_id,
                opened_at,
                closed_at,
                opened_by_user_name,
                closed_by_user_name,
                opening_amount,
                closing_amount_calculated,
                closing_amount_reported,
                cash_register_status
            FROM vw_fechamento_caixa_resumo
            WHERE company_id = :company_id
              AND DATE(opened_at) BETWEEN :start_date AND :end_date
            ORDER BY opened_at DESC, cash_register_id DESC
            LIMIT {$limit}
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function topProductsByCompany(int $companyId, int $limit = 6): array
    {
        $limit = max(1, min(20, $limit));

        $stmt = $this->db()->prepare("
            SELECT
                product_name,
                category_name,
                total_quantidade_vendida,
                valor_total_vendido
            FROM vw_produtos_mais_vendidos
            WHERE company_id = :company_id
            ORDER BY valor_total_vendido DESC, total_quantidade_vendida DESC
            LIMIT {$limit}
        ");
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function paymentStatusSummary(
        int $companyId,
        string $startDate,
        string $endDate,
        ?string $status,
        ?string $channel,
        ?string $paymentStatus,
        ?float $minAmount,
        ?float $maxAmount,
        ?string $search
    ): array
    {
        [$whereSql, $params] = $this->buildSalesWhere(
            $companyId,
            $startDate,
            $endDate,
            $status,
            $channel,
            $paymentStatus,
            $minAmount,
            $maxAmount,
            $search
        );

        $stmt = $this->db()->prepare("
            SELECT
                payment_status,
                COUNT(*) AS total_orders,
                COALESCE(SUM(total_amount), 0) AS total_amount
            FROM vw_relatorio_vendas_pedidos
            WHERE {$whereSql}
            GROUP BY payment_status
            ORDER BY total_orders DESC, payment_status ASC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function detailedOrdersForReport(
        int $companyId,
        string $startDate,
        string $endDate,
        ?string $status,
        ?string $channel,
        ?string $paymentStatus,
        ?float $minAmount,
        ?float $maxAmount,
        ?string $search,
        int $limit = 250
    ): array
    {
        $limit = max(10, min(500, $limit));
        [$whereSql, $params] = $this->buildSalesWhere(
            $companyId,
            $startDate,
            $endDate,
            $status,
            $channel,
            $paymentStatus,
            $minAmount,
            $maxAmount,
            $search
        );

        $stmt = $this->db()->prepare("
            SELECT
                order_id,
                order_number,
                channel,
                status,
                payment_status,
                customer_name,
                subtotal_amount,
                discount_amount,
                delivery_fee,
                total_amount,
                placed_by_user_name,
                created_at,
                canceled_at
            FROM vw_relatorio_vendas_pedidos
            WHERE {$whereSql}
            ORDER BY created_at DESC, order_id DESC
            LIMIT {$limit}
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function categorySalesByCompany(int $companyId, int $limit = 6): array
    {
        $limit = max(1, min(20, $limit));

        $stmt = $this->db()->prepare("
            SELECT
                category_name,
                total_quantidade_vendida,
                valor_total_vendido,
                total_pedidos_com_categoria
            FROM vw_vendas_por_categoria
            WHERE company_id = :company_id
            ORDER BY valor_total_vendido DESC, total_quantidade_vendida DESC
            LIMIT {$limit}
        ");
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function companyRoles(int $companyId): array
    {
        $params = [
            'company_id' => $companyId,
            'custom_prefix' => $this->customCompanyRolePrefix($companyId),
        ];
        $systemSlugsSql = $this->systemCompanyRoleSlugSql($params);

        $stmt = $this->db()->prepare("
            SELECT
                r.id,
                r.name,
                r.slug,
                r.description,
                CASE WHEN r.slug LIKE :custom_prefix THEN 1 ELSE 0 END AS is_custom,
                COUNT(DISTINCT rp.permission_id) AS permissions_count,
                COUNT(DISTINCT u.id) AS users_count
            FROM roles r
            LEFT JOIN role_permissions rp
                ON rp.role_id = r.id
            LEFT JOIN users u
                ON u.role_id = r.id
               AND u.company_id = :company_id
               AND u.deleted_at IS NULL
               AND u.is_saas_user = 0
            WHERE r.context = 'company'
              AND (r.slug IN ({$systemSlugsSql}) OR r.slug LIKE :custom_prefix)
            GROUP BY r.id, r.name, r.slug, r.description
            ORDER BY
                CASE r.slug
                    WHEN 'admin_establishment' THEN 1
                    WHEN 'manager' THEN 2
                    WHEN 'cashier' THEN 3
                    WHEN 'waiter' THEN 4
                    WHEN 'kitchen' THEN 5
                    WHEN 'delivery' THEN 6
                    ELSE 100
                END,
                r.name ASC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findCompanyRoleById(int $companyId, int $roleId): ?array
    {
        $params = [
            'id' => $roleId,
            'custom_prefix' => $this->customCompanyRolePrefix($companyId),
        ];
        $systemSlugsSql = $this->systemCompanyRoleSlugSql($params);

        $stmt = $this->db()->prepare("
            SELECT
                r.id,
                r.name,
                r.slug,
                r.description,
                CASE WHEN r.slug LIKE :custom_prefix THEN 1 ELSE 0 END AS is_custom
            FROM roles r
            WHERE r.id = :id
              AND r.context = 'company'
              AND (r.slug IN ({$systemSlugsSql}) OR r.slug LIKE :custom_prefix)
            LIMIT 1
        ");
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findCompanyRoleBySlug(int $companyId, string $slug): ?array
    {
        $normalizedSlug = strtolower(trim($slug));
        if ($normalizedSlug === '') {
            return null;
        }

        $params = [
            'slug' => $normalizedSlug,
            'custom_prefix' => $this->customCompanyRolePrefix($companyId),
        ];
        $systemSlugsSql = $this->systemCompanyRoleSlugSql($params);

        $stmt = $this->db()->prepare("
            SELECT
                r.id,
                r.name,
                r.slug,
                r.description,
                CASE WHEN r.slug LIKE :custom_prefix THEN 1 ELSE 0 END AS is_custom
            FROM roles r
            WHERE r.slug = :slug
              AND r.context = 'company'
              AND (r.slug IN ({$systemSlugsSql}) OR r.slug LIKE :custom_prefix)
            LIMIT 1
        ");
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createCompanyRole(string $name, string $slug, ?string $description): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO roles (
                name,
                slug,
                context,
                description,
                created_at,
                updated_at
            ) VALUES (
                :name,
                :slug,
                'company',
                :description,
                NOW(),
                NOW()
            )
        ");
        $stmt->execute([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function updateCompanyRole(int $roleId, string $name, ?string $description): void
    {
        $stmt = $this->db()->prepare("
            UPDATE roles
            SET name = :name,
                description = :description,
                updated_at = NOW()
            WHERE id = :id
              AND context = 'company'
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $roleId,
            'name' => $name,
            'description' => $description,
        ]);
    }

    public function deleteCompanyRole(int $roleId): void
    {
        $stmt = $this->db()->prepare("
            DELETE FROM roles
            WHERE id = :id
              AND context = 'company'
            LIMIT 1
        ");
        $stmt->execute(['id' => $roleId]);
    }

    public function roleSlugExists(string $slug): bool
    {
        $stmt = $this->db()->prepare("
            SELECT 1
            FROM roles
            WHERE slug = :slug
            LIMIT 1
        ");
        $stmt->execute(['slug' => $slug]);

        return $stmt->fetchColumn() !== false;
    }

    public function listCompanyPermissionsCatalog(): array
    {
        $params = [];
        $moduleSql = $this->companyPermissionModulesSql($params);
        $moduleOrder = "'" . implode("','", self::COMPANY_PERMISSION_MODULES) . "'";

        $stmt = $this->db()->prepare("
            SELECT
                p.id,
                p.module,
                p.action,
                p.slug,
                p.description
            FROM permissions p
            WHERE p.module IN ({$moduleSql})
            ORDER BY FIELD(p.module, {$moduleOrder}), p.action ASC, p.slug ASC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listPermissionIdsByRoleIds(array $roleIds): array
    {
        $normalizedRoleIds = [];
        foreach ($roleIds as $roleId) {
            $value = (int) $roleId;
            if ($value > 0) {
                $normalizedRoleIds[] = $value;
            }
        }

        if ($normalizedRoleIds === []) {
            return [];
        }

        $params = [];
        $roleSql = $this->dynamicPlaceholdersSql('role_id_', array_values(array_unique($normalizedRoleIds)), $params);

        $stmt = $this->db()->prepare("
            SELECT
                role_id,
                permission_id
            FROM role_permissions
            WHERE role_id IN ({$roleSql})
        ");
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $roleId = (int) ($row['role_id'] ?? 0);
            $permissionId = (int) ($row['permission_id'] ?? 0);
            if ($roleId <= 0 || $permissionId <= 0) {
                continue;
            }

            if (!isset($map[$roleId])) {
                $map[$roleId] = [];
            }
            $map[$roleId][] = $permissionId;
        }

        foreach ($map as $roleId => $permissionIds) {
            $map[$roleId] = array_values(array_unique(array_map('intval', $permissionIds)));
            sort($map[$roleId]);
        }

        return $map;
    }

    public function syncRolePermissions(int $roleId, array $permissionIds): void
    {
        $normalized = [];
        foreach ($permissionIds as $permissionId) {
            $value = (int) $permissionId;
            if ($value > 0) {
                $normalized[] = $value;
            }
        }
        $normalized = array_values(array_unique($normalized));

        $deleteStmt = $this->db()->prepare("
            DELETE FROM role_permissions
            WHERE role_id = :role_id
        ");
        $deleteStmt->execute(['role_id' => $roleId]);

        if ($normalized === []) {
            return;
        }

        $valuesSql = [];
        $params = ['role_id' => $roleId];
        foreach ($normalized as $index => $permissionId) {
            $key = 'permission_id_' . $index;
            $valuesSql[] = "(:role_id, :{$key})";
            $params[$key] = $permissionId;
        }

        $insertStmt = $this->db()->prepare("
            INSERT INTO role_permissions (role_id, permission_id)
            VALUES " . implode(', ', $valuesSql)
        );
        $insertStmt->execute($params);
    }

    public function listUsersByCompanyPaginated(int $companyId, array $filters, int $page, int $perPage): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        $roleId = (int) ($filters['role_id'] ?? 0);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $where = [
            'u.company_id = :company_id',
            'u.deleted_at IS NULL',
            'u.is_saas_user = 0',
        ];
        $params = ['company_id' => $companyId];

        if ($status !== '') {
            $where[] = 'u.status = :status';
            $params['status'] = $status;
        }

        if ($roleId > 0) {
            $where[] = 'u.role_id = :role_id';
            $params['role_id'] = $roleId;
        }

        if ($search !== '') {
            $where[] = "(
                LOWER(COALESCE(u.name, '')) LIKE :search
                OR LOWER(COALESCE(u.email, '')) LIKE :search
                OR LOWER(COALESCE(u.phone, '')) LIKE :search
                OR LOWER(COALESCE(r.name, '')) LIKE :search
            )";
            $params['search'] = '%' . strtolower($search) . '%';
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->db()->prepare("
            SELECT COUNT(*) AS total
            FROM users u
            INNER JOIN roles r
                ON r.id = u.role_id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetchColumn() ?: 0);

        $lastPage = max(1, (int) ceil($total / $perPage));
        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $offset = ($page - 1) * $perPage;

        $listStmt = $this->db()->prepare("
            SELECT
                u.id,
                u.company_id,
                u.role_id,
                u.name,
                u.email,
                u.phone,
                u.status,
                u.last_login_at,
                u.created_at,
                u.updated_at,
                r.name AS role_name,
                r.slug AS role_slug
            FROM users u
            INNER JOIN roles r
                ON r.id = u.role_id
            WHERE {$whereSql}
            ORDER BY
                CASE u.status
                    WHEN 'ativo' THEN 1
                    WHEN 'inativo' THEN 2
                    WHEN 'bloqueado' THEN 3
                    ELSE 9
                END,
                u.created_at DESC,
                u.id DESC
            LIMIT {$perPage}
            OFFSET {$offset}
        ");
        $listStmt->execute($params);

        return [
            'items' => $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ];
    }

    public function listUsersByCompany(int $companyId): array
    {
        $result = $this->listUsersByCompanyPaginated($companyId, [], 1, 200);
        return is_array($result['items'] ?? null) ? $result['items'] : [];
    }

    public function findUserByIdForCompany(int $companyId, int $userId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                id,
                company_id,
                role_id,
                name,
                email,
                phone,
                status
            FROM users
            WHERE company_id = :company_id
              AND id = :id
              AND deleted_at IS NULL
              AND is_saas_user = 0
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findUserByEmail(string $email): ?array
    {
        $normalizedEmail = strtolower(trim($email));

        $stmt = $this->db()->prepare("
            SELECT id, company_id, deleted_at
            FROM users
            WHERE LOWER(email) = :email
            LIMIT 1
        ");
        $stmt->execute(['email' => $normalizedEmail]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createCompanyUser(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO users (
                company_id,
                role_id,
                name,
                email,
                phone,
                password_hash,
                status,
                is_saas_user,
                created_at,
                updated_at
            ) VALUES (
                :company_id,
                :role_id,
                :name,
                :email,
                :phone,
                :password_hash,
                :status,
                0,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'company_id' => $data['company_id'],
            'role_id' => $data['role_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password_hash' => $data['password_hash'],
            'status' => $data['status'],
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function updateCompanyUserProfile(int $companyId, int $userId, array $data): void
    {
        $stmt = $this->db()->prepare("
            UPDATE users
            SET role_id = :role_id,
                name = :name,
                email = :email,
                phone = :phone,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
              AND deleted_at IS NULL
              AND is_saas_user = 0
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $userId,
            'role_id' => $data['role_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
        ]);
    }

    public function updateCompanyUserStatus(int $companyId, int $userId, string $status): void
    {
        $stmt = $this->db()->prepare("
            UPDATE users
            SET status = :status,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
              AND deleted_at IS NULL
              AND is_saas_user = 0
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $userId,
            'status' => $status,
        ]);
    }

    public function updateCompanyUserPassword(int $companyId, int $userId, string $passwordHash): void
    {
        $stmt = $this->db()->prepare("
            UPDATE users
            SET password_hash = :password_hash,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
              AND deleted_at IS NULL
              AND is_saas_user = 0
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'id' => $userId,
            'password_hash' => $passwordHash,
        ]);
    }

    public function updateCompanyUser(int $companyId, int $userId, array $data): void
    {
        $this->updateCompanyUserProfile($companyId, $userId, $data);

        if (isset($data['status']) && is_string($data['status']) && $data['status'] !== '') {
            $this->updateCompanyUserStatus($companyId, $userId, $data['status']);
        }

        if (!empty($data['password_hash'])) {
            $this->updateCompanyUserPassword($companyId, $userId, (string) $data['password_hash']);
        }
    }

    public function countUsersByRoleForCompany(int $companyId, int $roleId): int
    {
        $stmt = $this->db()->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE company_id = :company_id
              AND role_id = :role_id
              AND deleted_at IS NULL
              AND is_saas_user = 0
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'role_id' => $roleId,
        ]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function listSupportTicketsByCompanyPaginated(int $companyId, array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(10, $perPage));

        ['where_sql' => $whereSql, 'params' => $params] = $this->buildSupportTicketsWhereClause($companyId, $filters);

        $countStmt = $this->db()->prepare("
            SELECT COUNT(*) AS total
            FROM support_tickets st
            INNER JOIN companies c
                ON c.id = st.company_id
            INNER JOIN users uo
                ON uo.id = st.opened_by_user_id
            LEFT JOIN users ua
                ON ua.id = st.assigned_to_user_id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetchColumn() ?: 0);

        $lastPage = max(1, (int) ceil($total / $perPage));
        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $offset = ($page - 1) * $perPage;

        $listStmt = $this->db()->prepare("
            SELECT
                st.id,
                st.company_id,
                st.opened_by_user_id,
                st.assigned_to_user_id,
                st.subject,
                st.description,
                st.status,
                st.priority,
                st.created_at,
                st.updated_at,
                st.closed_at,
                c.name AS company_name,
                c.slug AS company_slug,
                uo.name AS opened_by_user_name,
                ua.name AS assigned_to_user_name
            FROM support_tickets st
            INNER JOIN companies c
                ON c.id = st.company_id
            INNER JOIN users uo
                ON uo.id = st.opened_by_user_id
            LEFT JOIN users ua
                ON ua.id = st.assigned_to_user_id
            WHERE {$whereSql}
            ORDER BY
                CASE st.status
                    WHEN 'open' THEN 1
                    WHEN 'in_progress' THEN 2
                    WHEN 'resolved' THEN 3
                    WHEN 'closed' THEN 4
                    ELSE 9
                END,
                CASE st.priority
                    WHEN 'urgent' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    WHEN 'low' THEN 4
                    ELSE 9
                END,
                COALESCE(st.updated_at, st.created_at) DESC,
                st.id DESC
            LIMIT {$perPage}
            OFFSET {$offset}
        ");
        $listStmt->execute($params);

        return [
            'items' => $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ];
    }

    public function listSupportTicketsForSaasPaginated(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(10, $perPage));

        ['where_sql' => $whereSql, 'params' => $params] = $this->buildSupportTicketsWhereClause(null, $filters, true);

        $countStmt = $this->db()->prepare("
            SELECT COUNT(*) AS total
            FROM support_tickets st
            INNER JOIN companies c
                ON c.id = st.company_id
            INNER JOIN users uo
                ON uo.id = st.opened_by_user_id
            LEFT JOIN users ua
                ON ua.id = st.assigned_to_user_id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetchColumn() ?: 0);

        $lastPage = max(1, (int) ceil($total / $perPage));
        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $offset = ($page - 1) * $perPage;

        $listStmt = $this->db()->prepare("
            SELECT
                st.id,
                st.company_id,
                st.opened_by_user_id,
                st.assigned_to_user_id,
                st.subject,
                st.description,
                st.status,
                st.priority,
                st.created_at,
                st.updated_at,
                st.closed_at,
                c.name AS company_name,
                c.slug AS company_slug,
                c.email AS company_email,
                uo.name AS opened_by_user_name,
                ua.name AS assigned_to_user_name
            FROM support_tickets st
            INNER JOIN companies c
                ON c.id = st.company_id
            INNER JOIN users uo
                ON uo.id = st.opened_by_user_id
            LEFT JOIN users ua
                ON ua.id = st.assigned_to_user_id
            WHERE {$whereSql}
            ORDER BY
                CASE st.status
                    WHEN 'open' THEN 1
                    WHEN 'in_progress' THEN 2
                    WHEN 'resolved' THEN 3
                    WHEN 'closed' THEN 4
                    ELSE 9
                END,
                CASE st.priority
                    WHEN 'urgent' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    WHEN 'low' THEN 4
                    ELSE 9
                END,
                COALESCE(st.updated_at, st.created_at) DESC,
                st.id DESC
            LIMIT {$perPage}
            OFFSET {$offset}
        ");
        $listStmt->execute($params);

        return [
            'items' => $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ];
    }

    public function supportTicketMetricsByCompany(int $companyId, array $filters = []): array
    {
        ['where_sql' => $whereSql, 'params' => $params] = $this->buildSupportTicketsWhereClause($companyId, $filters);
        return $this->supportTicketMetricsByWhereClause($whereSql, $params);
    }

    public function supportTicketMetricsForSaas(array $filters = []): array
    {
        ['where_sql' => $whereSql, 'params' => $params] = $this->buildSupportTicketsWhereClause(null, $filters, true);
        return $this->supportTicketMetricsByWhereClause($whereSql, $params);
    }

    public function supportTicketsByCompany(int $companyId, int $limit = 30): array
    {
        $result = $this->listSupportTicketsByCompanyPaginated($companyId, [], 1, $limit);
        return is_array($result['items'] ?? null) ? $result['items'] : [];
    }

    public function findDefaultSupportAssignee(): ?int
    {
        $stmt = $this->db()->prepare("
            SELECT u.id
            FROM users u
            INNER JOIN roles r
                ON r.id = u.role_id
            WHERE u.deleted_at IS NULL
              AND u.status = 'ativo'
              AND u.is_saas_user = 1
              AND r.slug IN ('saas_support', 'saas_admin')
            ORDER BY
                CASE r.slug
                    WHEN 'saas_support' THEN 1
                    WHEN 'saas_admin' THEN 2
                    ELSE 3
                END,
                u.id ASC
            LIMIT 1
        ");
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $id = (int) ($row['id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    public function findSupportTicketByIdForCompany(int $companyId, int $ticketId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                st.id,
                st.company_id,
                st.opened_by_user_id,
                st.assigned_to_user_id,
                st.subject,
                st.description,
                st.status,
                st.priority,
                st.created_at,
                st.updated_at,
                st.closed_at,
                c.name AS company_name,
                c.slug AS company_slug,
                uo.name AS opened_by_user_name,
                ua.name AS assigned_to_user_name
            FROM support_tickets st
            INNER JOIN companies c
                ON c.id = st.company_id
            INNER JOIN users uo
                ON uo.id = st.opened_by_user_id
            LEFT JOIN users ua
                ON ua.id = st.assigned_to_user_id
            WHERE st.company_id = :company_id
              AND st.id = :ticket_id
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'ticket_id' => $ticketId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findSupportTicketById(int $ticketId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                st.id,
                st.company_id,
                st.opened_by_user_id,
                st.assigned_to_user_id,
                st.subject,
                st.description,
                st.status,
                st.priority,
                st.created_at,
                st.updated_at,
                st.closed_at,
                c.name AS company_name,
                c.slug AS company_slug,
                c.email AS company_email,
                uo.name AS opened_by_user_name,
                ua.name AS assigned_to_user_name
            FROM support_tickets st
            INNER JOIN companies c
                ON c.id = st.company_id
            INNER JOIN users uo
                ON uo.id = st.opened_by_user_id
            LEFT JOIN users ua
                ON ua.id = st.assigned_to_user_id
            WHERE st.id = :ticket_id
            LIMIT 1
        ");
        $stmt->execute(['ticket_id' => $ticketId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createSupportTicket(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO support_tickets (
                company_id,
                opened_by_user_id,
                assigned_to_user_id,
                subject,
                description,
                status,
                priority,
                created_at,
                updated_at
            ) VALUES (
                :company_id,
                :opened_by_user_id,
                :assigned_to_user_id,
                :subject,
                :description,
                'open',
                :priority,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'company_id' => $data['company_id'],
            'opened_by_user_id' => $data['opened_by_user_id'],
            'assigned_to_user_id' => $data['assigned_to_user_id'],
            'subject' => $data['subject'],
            'description' => $data['description'],
            'priority' => $data['priority'],
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function createSupportTicketMessage(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO support_ticket_messages (
                ticket_id,
                sender_user_id,
                sender_context,
                message,
                created_at,
                updated_at
            ) VALUES (
                :ticket_id,
                :sender_user_id,
                :sender_context,
                :message,
                NOW(),
                NOW()
            )
        ");
        $stmt->execute([
            'ticket_id' => $data['ticket_id'],
            'sender_user_id' => $data['sender_user_id'],
            'sender_context' => $data['sender_context'],
            'message' => $data['message'],
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function listSupportTicketMessagesByTicketIds(array $ticketIds): array
    {
        $normalizedIds = [];
        foreach ($ticketIds as $ticketId) {
            $value = (int) $ticketId;
            if ($value > 0) {
                $normalizedIds[$value] = true;
            }
        }

        $ids = array_keys($normalizedIds);
        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $ticketId) {
            $key = 'ticket_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $ticketId;
        }

        $stmt = $this->db()->prepare("
            SELECT
                stm.id,
                stm.ticket_id,
                stm.sender_user_id,
                stm.sender_context,
                stm.message,
                stm.created_at,
                stm.updated_at,
                u.name AS sender_user_name,
                r.name AS sender_role_name
            FROM support_ticket_messages stm
            INNER JOIN users u
                ON u.id = stm.sender_user_id
            LEFT JOIN roles r
                ON r.id = u.role_id
            WHERE stm.ticket_id IN (" . implode(', ', $placeholders) . ")
            ORDER BY stm.created_at ASC, stm.id ASC
        ");
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $grouped = [];
        foreach ($rows as $row) {
            $ticketId = (int) ($row['ticket_id'] ?? 0);
            if ($ticketId <= 0) {
                continue;
            }

            if (!isset($grouped[$ticketId])) {
                $grouped[$ticketId] = [];
            }

            $grouped[$ticketId][] = $row;
        }

        return $grouped;
    }

    public function updateSupportTicketConversationState(int $ticketId, array $data): void
    {
        $status = strtolower(trim((string) ($data['status'] ?? 'open')));
        $assignedToUserId = isset($data['assigned_to_user_id']) && (int) $data['assigned_to_user_id'] > 0
            ? (int) $data['assigned_to_user_id']
            : null;
        $closedAt = $data['closed_at'] ?? null;

        $stmt = $this->db()->prepare("
            UPDATE support_tickets
            SET assigned_to_user_id = :assigned_to_user_id,
                status = :status,
                closed_at = :closed_at,
                updated_at = NOW()
            WHERE id = :ticket_id
            LIMIT 1
        ");
        $stmt->bindValue(':ticket_id', $ticketId, PDO::PARAM_INT);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        if ($assignedToUserId === null) {
            $stmt->bindValue(':assigned_to_user_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':assigned_to_user_id', $assignedToUserId, PDO::PARAM_INT);
        }
        if ($closedAt === null) {
            $stmt->bindValue(':closed_at', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':closed_at', (string) $closedAt, PDO::PARAM_STR);
        }
        $stmt->execute();
    }

    private function buildSupportTicketsWhereClause(?int $companyId, array $filters, bool $includeCompanySearch = false): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        $priority = strtolower(trim((string) ($filters['priority'] ?? '')));
        $assignment = strtolower(trim((string) ($filters['assignment'] ?? '')));
        $companySearch = trim((string) ($filters['company_search'] ?? ''));

        $where = [
            '1 = 1',
        ];
        $params = [];

        if ($companyId !== null && $companyId > 0) {
            $where[] = 'st.company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        if ($status !== '') {
            $where[] = 'st.status = :support_status';
            $params['support_status'] = $status;
        }

        if ($priority !== '') {
            $where[] = 'st.priority = :support_priority';
            $params['support_priority'] = $priority;
        }

        if ($assignment === 'assigned') {
            $where[] = 'st.assigned_to_user_id IS NOT NULL';
        } elseif ($assignment === 'unassigned') {
            $where[] = 'st.assigned_to_user_id IS NULL';
        }

        if ($search !== '') {
            $normalizedSearch = '%' . strtolower($search) . '%';
            $where[] = "(
                LOWER(COALESCE(st.subject, '')) LIKE :support_search
                OR LOWER(COALESCE(st.description, '')) LIKE :support_search
                OR LOWER(COALESCE(uo.name, '')) LIKE :support_search
                OR LOWER(COALESCE(ua.name, '')) LIKE :support_search
                OR EXISTS (
                    SELECT 1
                    FROM support_ticket_messages stm
                    INNER JOIN users su
                        ON su.id = stm.sender_user_id
                    WHERE stm.ticket_id = st.id
                      AND (
                          LOWER(COALESCE(stm.message, '')) LIKE :support_search
                          OR LOWER(COALESCE(su.name, '')) LIKE :support_search
                      )
                )
                OR CAST(st.id AS CHAR) = :support_id_search
            )";
            $params['support_search'] = $normalizedSearch;
            $params['support_id_search'] = $search;
        }

        if ($includeCompanySearch && $companySearch !== '') {
            $normalizedCompanySearch = '%' . strtolower($companySearch) . '%';
            $where[] = "(
                LOWER(COALESCE(c.name, '')) LIKE :support_company_search
                OR LOWER(COALESCE(c.slug, '')) LIKE :support_company_search
                OR LOWER(COALESCE(c.email, '')) LIKE :support_company_search
                OR CAST(c.id AS CHAR) = :support_company_id_search
            )";
            $params['support_company_search'] = $normalizedCompanySearch;
            $params['support_company_id_search'] = $companySearch;
        }

        return [
            'where_sql' => implode(' AND ', $where),
            'params' => $params,
        ];
    }

    private function supportTicketMetricsByWhereClause(string $whereSql, array $params): array
    {
        $stmt = $this->db()->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN st.status = 'open' THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN st.status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count,
                SUM(CASE WHEN st.status IN ('resolved', 'closed') THEN 1 ELSE 0 END) AS resolved_count,
                SUM(CASE WHEN st.priority = 'urgent' THEN 1 ELSE 0 END) AS urgent_count,
                SUM(CASE WHEN st.assigned_to_user_id IS NOT NULL THEN 1 ELSE 0 END) AS assigned_count,
                MAX(COALESCE(st.updated_at, st.created_at)) AS last_created_at
            FROM support_tickets st
            INNER JOIN companies c
                ON c.id = st.company_id
            INNER JOIN users uo
                ON uo.id = st.opened_by_user_id
            LEFT JOIN users ua
                ON ua.id = st.assigned_to_user_id
            WHERE {$whereSql}
        ");
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'open_count' => (int) ($row['open_count'] ?? 0),
            'in_progress_count' => (int) ($row['in_progress_count'] ?? 0),
            'resolved_count' => (int) ($row['resolved_count'] ?? 0),
            'urgent_count' => (int) ($row['urgent_count'] ?? 0),
            'assigned_count' => (int) ($row['assigned_count'] ?? 0),
            'last_created_at' => (string) ($row['last_created_at'] ?? ''),
        ];
    }

    private function customCompanyRolePrefix(int $companyId): string
    {
        return 'cmp' . $companyId . '-%';
    }

    private function systemCompanyRoleSlugSql(array &$params): string
    {
        return $this->dynamicPlaceholdersSql('system_role_slug_', self::SYSTEM_COMPANY_ROLE_SLUGS, $params);
    }

    private function companyPermissionModulesSql(array &$params): string
    {
        return $this->dynamicPlaceholdersSql('permission_module_', self::COMPANY_PERMISSION_MODULES, $params);
    }

    private function dynamicPlaceholdersSql(string $prefix, array $values, array &$params): string
    {
        $placeholders = [];
        foreach ($values as $index => $value) {
            $key = $prefix . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $value;
        }

        if ($placeholders === []) {
            return "''";
        }

        return implode(', ', $placeholders);
    }

    private function buildSalesWhere(
        int $companyId,
        string $startDate,
        string $endDate,
        ?string $status,
        ?string $channel,
        ?string $paymentStatus,
        ?float $minAmount,
        ?float $maxAmount,
        ?string $search
    ): array
    {
        $where = [
            'company_id = :company_id',
            'DATE(created_at) BETWEEN :start_date AND :end_date',
        ];

        $params = [
            'company_id' => $companyId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        if ($status !== null) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        if ($channel !== null) {
            $where[] = 'channel = :channel';
            $params['channel'] = $channel;
        }

        if ($paymentStatus !== null) {
            $where[] = 'payment_status = :payment_status';
            $params['payment_status'] = $paymentStatus;
        }

        if ($minAmount !== null) {
            $where[] = 'total_amount >= :min_amount';
            $params['min_amount'] = $minAmount;
        }

        if ($maxAmount !== null) {
            $where[] = 'total_amount <= :max_amount';
            $params['max_amount'] = $maxAmount;
        }

        if ($search !== null && $search !== '') {
            $where[] = "(
                LOWER(COALESCE(order_number, '')) LIKE :search
                OR LOWER(COALESCE(customer_name, '')) LIKE :search
                OR LOWER(COALESCE(channel, '')) LIKE :search
                OR LOWER(COALESCE(status, '')) LIKE :search
                OR LOWER(COALESCE(payment_status, '')) LIKE :search
            )";
            $params['search'] = '%' . strtolower($search) . '%';
        }

        return [implode(' AND ', $where), $params];
    }
}
