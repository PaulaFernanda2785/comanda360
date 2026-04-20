<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PlanRepository extends BaseRepository
{
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

    public function listForSaasPaginated(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        [$whereSql, $params] = $this->buildSaasWhere($filters);

        $countStmt = $this->db()->prepare("
            SELECT COUNT(*)
            FROM plans p
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $itemsStmt = $this->db()->prepare("
            SELECT
                p.id,
                p.name,
                p.slug,
                p.description,
                p.price_monthly,
                p.price_yearly,
                p.max_users,
                p.max_products,
                p.max_tables,
                p.features_json,
                p.status,
                p.created_at,
                p.updated_at,
                (
                    SELECT COUNT(*)
                    FROM companies c
                    WHERE c.plan_id = p.id
                ) AS linked_companies_count,
                (
                    SELECT COUNT(*)
                    FROM subscriptions s
                    WHERE s.plan_id = p.id
                ) AS linked_subscriptions_count
            FROM plans p
            WHERE {$whereSql}
            ORDER BY p.price_monthly ASC, p.id ASC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $itemsStmt->execute($params);

        return [
            'items' => $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function allForSaas(): array
    {
        $page = $this->listForSaasPaginated([], 1, 500);
        return is_array($page['items'] ?? null) ? $page['items'] : [];
    }

    public function listActiveForPublicCatalog(): array
    {
        $stmt = $this->db()->prepare("
            SELECT
                p.id,
                p.name,
                p.slug,
                p.description,
                p.price_monthly,
                p.price_yearly,
                p.max_users,
                p.max_products,
                p.max_tables,
                p.features_json,
                p.status,
                p.created_at,
                p.updated_at
            FROM plans p
            WHERE p.status = 'ativo'
            ORDER BY p.price_monthly ASC, p.id ASC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function summary(array $filters = []): array
    {
        [$whereSql, $params] = $this->buildSaasWhere($filters);

        $stmt = $this->db()->prepare("
            SELECT
                COUNT(*) AS total_plans,
                SUM(CASE WHEN p.status = 'ativo' THEN 1 ELSE 0 END) AS active_plans,
                SUM(CASE WHEN p.status = 'inativo' THEN 1 ELSE 0 END) AS inactive_plans,
                SUM(
                    CASE WHEN EXISTS (
                        SELECT 1
                        FROM companies c
                        WHERE c.plan_id = p.id
                    ) THEN 1 ELSE 0 END
                ) AS plans_in_company_use,
                SUM(
                    CASE WHEN EXISTS (
                        SELECT 1
                        FROM subscriptions s
                        WHERE s.plan_id = p.id
                    ) THEN 1 ELSE 0 END
                ) AS plans_with_subscription_history,
                MAX(p.created_at) AS last_created_at
            FROM plans p
            WHERE {$whereSql}
        ");
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    public function findById(int $planId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                p.id,
                p.name,
                p.slug,
                p.description,
                p.price_monthly,
                p.price_yearly,
                p.max_users,
                p.max_products,
                p.max_tables,
                p.features_json,
                p.status,
                p.created_at,
                p.updated_at,
                (
                    SELECT COUNT(*)
                    FROM companies c
                    WHERE c.plan_id = p.id
                ) AS linked_companies_count,
                (
                    SELECT COUNT(*)
                    FROM subscriptions s
                    WHERE s.plan_id = p.id
                ) AS linked_subscriptions_count
            FROM plans p
            WHERE p.id = :plan_id
            LIMIT 1
        ");
        $stmt->execute([
            'plan_id' => $planId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function slugExists(string $slug, ?int $exceptPlanId = null): bool
    {
        $sql = "
            SELECT 1
            FROM plans
            WHERE slug = :slug
        ";
        $params = ['slug' => $slug];

        if (($exceptPlanId ?? 0) > 0) {
            $sql .= " AND id <> :except_plan_id";
            $params['except_plan_id'] = $exceptPlanId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    public function create(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO plans (
                name,
                slug,
                description,
                price_monthly,
                price_yearly,
                max_users,
                max_products,
                max_tables,
                features_json,
                status,
                created_at,
                updated_at
            ) VALUES (
                :name,
                :slug,
                :description,
                :price_monthly,
                :price_yearly,
                :max_users,
                :max_products,
                :max_tables,
                :features_json,
                :status,
                NOW(),
                NOW()
            )
        ");
        $stmt->execute([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'],
            'price_monthly' => $data['price_monthly'],
            'price_yearly' => $data['price_yearly'],
            'max_users' => $data['max_users'],
            'max_products' => $data['max_products'],
            'max_tables' => $data['max_tables'],
            'features_json' => $data['features_json'],
            'status' => $data['status'],
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $planId, array $data): void
    {
        $stmt = $this->db()->prepare("
            UPDATE plans
            SET
                name = :name,
                slug = :slug,
                description = :description,
                price_monthly = :price_monthly,
                price_yearly = :price_yearly,
                max_users = :max_users,
                max_products = :max_products,
                max_tables = :max_tables,
                features_json = :features_json,
                status = :status,
                updated_at = NOW()
            WHERE id = :plan_id
            LIMIT 1
        ");
        $stmt->execute([
            'plan_id' => $planId,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'],
            'price_monthly' => $data['price_monthly'],
            'price_yearly' => $data['price_yearly'],
            'max_users' => $data['max_users'],
            'max_products' => $data['max_products'],
            'max_tables' => $data['max_tables'],
            'features_json' => $data['features_json'],
            'status' => $data['status'],
        ]);
    }

    public function clearRecommendedFlagFromOtherActivePlans(int $exceptPlanId): void
    {
        $stmt = $this->db()->prepare("
            SELECT
                p.id,
                p.features_json
            FROM plans p
            WHERE p.id <> :except_plan_id
              AND p.status = 'ativo'
        ");
        $stmt->execute([
            'except_plan_id' => $exceptPlanId,
        ]);

        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($plans === []) {
            return;
        }

        $update = $this->db()->prepare("
            UPDATE plans
            SET
                features_json = :features_json,
                updated_at = NOW()
            WHERE id = :plan_id
            LIMIT 1
        ");

        foreach ($plans as $plan) {
            $rawFeatures = trim((string) ($plan['features_json'] ?? ''));
            if ($rawFeatures === '') {
                continue;
            }

            $decoded = json_decode($rawFeatures, true);
            if (!is_array($decoded)) {
                continue;
            }

            $publicConfig = is_array($decoded['vitrine_publica'] ?? null)
                ? $decoded['vitrine_publica']
                : [];

            if (empty($publicConfig['recomendado'])) {
                continue;
            }

            $decoded['vitrine_publica'] = array_merge($publicConfig, [
                'recomendado' => false,
            ]);

            $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                continue;
            }

            $update->execute([
                'plan_id' => (int) ($plan['id'] ?? 0),
                'features_json' => $encoded,
            ]);
        }
    }

    public function delete(int $planId): void
    {
        $stmt = $this->db()->prepare("
            DELETE FROM plans
            WHERE id = :plan_id
            LIMIT 1
        ");
        $stmt->execute([
            'plan_id' => $planId,
        ]);
    }

    private function buildSaasWhere(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = "(
                LOWER(COALESCE(p.name, '')) LIKE :plan_search
                OR LOWER(COALESCE(p.slug, '')) LIKE :plan_search
                OR LOWER(COALESCE(p.description, '')) LIKE :plan_search
                OR CAST(p.id AS CHAR) = :plan_id_search
            )";
            $params['plan_search'] = '%' . strtolower($search) . '%';
            $params['plan_id_search'] = $search;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'p.status = :status';
            $params['status'] = $status;
        }

        return [implode(' AND ', $where), $params];
    }
}
