<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CompanyRepository extends BaseRepository
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

    public function updateSubscriptionSnapshot(int $companyId, array $data): void
    {
        $stmt = $this->db()->prepare("
            UPDATE companies
            SET
                plan_id = :plan_id,
                subscription_status = :subscription_status,
                trial_ends_at = :trial_ends_at,
                subscription_starts_at = :subscription_starts_at,
                subscription_ends_at = :subscription_ends_at,
                updated_at = NOW()
            WHERE id = :company_id
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'plan_id' => $data['plan_id'] ?? null,
            'subscription_status' => $data['subscription_status'],
            'trial_ends_at' => $data['trial_ends_at'] ?? null,
            'subscription_starts_at' => $data['subscription_starts_at'] ?? null,
            'subscription_ends_at' => $data['subscription_ends_at'] ?? null,
        ]);
    }

    public function listForSaasPaginated(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        [$whereSql, $params] = $this->buildSaasWhere($filters);
        $subscriptionJoin = $this->latestSubscriptionJoinSql();

        $countStmt = $this->db()->prepare("
            SELECT COUNT(*)
            FROM companies c
            LEFT JOIN plans p ON p.id = c.plan_id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $itemsStmt = $this->db()->prepare("
            SELECT
                c.id,
                c.name,
                c.legal_name,
                c.document_number,
                c.email,
                c.phone,
                c.whatsapp,
                c.slug,
                c.status,
                c.plan_id,
                c.subscription_status,
                c.trial_ends_at,
                c.subscription_starts_at,
                c.subscription_ends_at,
                c.created_at,
                c.updated_at,
                (
                    SELECT MIN(sp_open.due_date)
                    FROM subscription_payments sp_open
                    WHERE sp_open.company_id = c.id
                      AND sp_open.subscription_id = s.id
                      AND sp_open.status IN ('pendente', 'vencido')
                ) AS next_charge_due_date,
                p.name AS plan_name,
                p.slug AS plan_slug,
                s.id AS subscription_id,
                s.status AS subscription_record_status,
                s.billing_cycle,
                s.amount,
                s.starts_at AS subscription_record_starts_at,
                s.ends_at AS subscription_record_ends_at,
                s.canceled_at AS subscription_record_canceled_at
            FROM companies c
            LEFT JOIN plans p ON p.id = c.plan_id
            {$subscriptionJoin}
            WHERE {$whereSql}
            ORDER BY c.created_at DESC, c.id DESC
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

    public function summary(array $filters = []): array
    {
        [$whereSql, $params] = $this->buildSaasWhere($filters);

        $stmt = $this->db()->prepare("
            SELECT
                COUNT(*) AS total_companies,
                SUM(CASE WHEN c.status = 'ativa' THEN 1 ELSE 0 END) AS active_companies,
                SUM(CASE WHEN c.status = 'teste' THEN 1 ELSE 0 END) AS testing_companies,
                SUM(CASE WHEN c.status = 'suspensa' THEN 1 ELSE 0 END) AS suspended_companies,
                SUM(CASE WHEN c.status = 'cancelada' THEN 1 ELSE 0 END) AS canceled_companies,
                SUM(CASE WHEN c.subscription_status = 'trial' THEN 1 ELSE 0 END) AS trial_companies,
                SUM(CASE WHEN c.subscription_status = 'inadimplente' THEN 1 ELSE 0 END) AS delinquent_companies,
                SUM(CASE WHEN c.subscription_status = 'ativa' THEN 1 ELSE 0 END) AS active_subscription_companies,
                MAX(c.created_at) AS last_created_at
            FROM companies c
            LEFT JOIN plans p ON p.id = c.plan_id
            WHERE {$whereSql}
        ");
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    public function findByIdForSaas(int $companyId): ?array
    {
        $subscriptionJoin = $this->latestSubscriptionJoinSql();

        $stmt = $this->db()->prepare("
            SELECT
                c.id,
                c.name,
                c.legal_name,
                c.document_number,
                c.email,
                c.phone,
                c.whatsapp,
                c.slug,
                c.status,
                c.plan_id,
                c.subscription_status,
                c.trial_ends_at,
                c.subscription_starts_at,
                c.subscription_ends_at,
                c.created_at,
                c.updated_at,
                (
                    SELECT MIN(sp_open.due_date)
                    FROM subscription_payments sp_open
                    WHERE sp_open.company_id = c.id
                      AND sp_open.subscription_id = s.id
                      AND sp_open.status IN ('pendente', 'vencido')
                ) AS next_charge_due_date,
                p.name AS plan_name,
                p.slug AS plan_slug,
                s.id AS subscription_id,
                s.status AS subscription_record_status,
                s.billing_cycle,
                s.amount,
                s.starts_at AS subscription_record_starts_at,
                s.ends_at AS subscription_record_ends_at,
                s.canceled_at AS subscription_record_canceled_at
            FROM companies c
            LEFT JOIN plans p ON p.id = c.plan_id
            {$subscriptionJoin}
            WHERE c.id = :company_id
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findCurrentSubscriptionByCompanyId(int $companyId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                id,
                company_id,
                plan_id,
                status,
                billing_cycle,
                amount,
                starts_at,
                ends_at,
                canceled_at,
                created_at,
                updated_at
            FROM subscriptions
            WHERE company_id = :company_id
            ORDER BY
                CASE status
                    WHEN 'ativa' THEN 0
                    WHEN 'trial' THEN 1
                    WHEN 'vencida' THEN 2
                    WHEN 'cancelada' THEN 3
                    ELSE 4
                END,
                created_at DESC,
                id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function slugExists(string $slug, ?int $exceptCompanyId = null): bool
    {
        $sql = "
            SELECT 1
            FROM companies
            WHERE slug = :slug
        ";
        $params = ['slug' => $slug];

        if (($exceptCompanyId ?? 0) > 0) {
            $sql .= " AND id <> :except_company_id";
            $params['except_company_id'] = $exceptCompanyId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    public function createCompany(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO companies (
                name,
                legal_name,
                document_number,
                email,
                phone,
                whatsapp,
                slug,
                status,
                plan_id,
                subscription_status,
                trial_ends_at,
                subscription_starts_at,
                subscription_ends_at,
                created_at,
                updated_at
            ) VALUES (
                :name,
                :legal_name,
                :document_number,
                :email,
                :phone,
                :whatsapp,
                :slug,
                :status,
                :plan_id,
                :subscription_status,
                :trial_ends_at,
                :subscription_starts_at,
                :subscription_ends_at,
                NOW(),
                NOW()
            )
        ");
        $stmt->execute([
            'name' => $data['name'],
            'legal_name' => $data['legal_name'],
            'document_number' => $data['document_number'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'whatsapp' => $data['whatsapp'],
            'slug' => $data['slug'],
            'status' => $data['status'],
            'plan_id' => $data['plan_id'],
            'subscription_status' => $data['subscription_status'],
            'trial_ends_at' => $data['trial_ends_at'],
            'subscription_starts_at' => $data['subscription_starts_at'],
            'subscription_ends_at' => $data['subscription_ends_at'],
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function updateCompany(int $companyId, array $data): void
    {
        $stmt = $this->db()->prepare("
            UPDATE companies
            SET
                name = :name,
                legal_name = :legal_name,
                document_number = :document_number,
                email = :email,
                phone = :phone,
                whatsapp = :whatsapp,
                slug = :slug,
                status = :status,
                plan_id = :plan_id,
                subscription_status = :subscription_status,
                trial_ends_at = :trial_ends_at,
                subscription_starts_at = :subscription_starts_at,
                subscription_ends_at = :subscription_ends_at,
                updated_at = NOW()
            WHERE id = :company_id
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'name' => $data['name'],
            'legal_name' => $data['legal_name'],
            'document_number' => $data['document_number'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'whatsapp' => $data['whatsapp'],
            'slug' => $data['slug'],
            'status' => $data['status'],
            'plan_id' => $data['plan_id'],
            'subscription_status' => $data['subscription_status'],
            'trial_ends_at' => $data['trial_ends_at'],
            'subscription_starts_at' => $data['subscription_starts_at'],
            'subscription_ends_at' => $data['subscription_ends_at'],
        ]);
    }

    public function createSubscription(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO subscriptions (
                company_id,
                plan_id,
                status,
                billing_cycle,
                amount,
                starts_at,
                ends_at,
                canceled_at,
                created_at,
                updated_at
            ) VALUES (
                :company_id,
                :plan_id,
                :status,
                :billing_cycle,
                :amount,
                :starts_at,
                :ends_at,
                :canceled_at,
                NOW(),
                NOW()
            )
        ");
        $stmt->execute([
            'company_id' => $data['company_id'],
            'plan_id' => $data['plan_id'],
            'status' => $data['status'],
            'billing_cycle' => $data['billing_cycle'],
            'amount' => $data['amount'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'canceled_at' => $data['canceled_at'],
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function updateSubscription(int $subscriptionId, array $data): void
    {
        $stmt = $this->db()->prepare("
            UPDATE subscriptions
            SET
                plan_id = :plan_id,
                status = :status,
                billing_cycle = :billing_cycle,
                amount = :amount,
                starts_at = :starts_at,
                ends_at = :ends_at,
                canceled_at = :canceled_at,
                updated_at = NOW()
            WHERE id = :subscription_id
            LIMIT 1
        ");
        $stmt->execute([
            'subscription_id' => $subscriptionId,
            'plan_id' => $data['plan_id'],
            'status' => $data['status'],
            'billing_cycle' => $data['billing_cycle'],
            'amount' => $data['amount'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'canceled_at' => $data['canceled_at'],
        ]);
    }

    public function cancelLatestSubscriptionByCompanyId(int $companyId, string $canceledAt): void
    {
        $stmt = $this->db()->prepare("
            UPDATE subscriptions
            SET
                status = 'cancelada',
                canceled_at = :canceled_at,
                ends_at = COALESCE(ends_at, :canceled_at_end),
                updated_at = NOW()
            WHERE company_id = :company_id
              AND status <> 'cancelada'
            ORDER BY created_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'canceled_at' => $canceledAt,
            'canceled_at_end' => $canceledAt,
        ]);
    }

    private function buildSaasWhere(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = "(
                LOWER(COALESCE(c.name, '')) LIKE :company_search
                OR LOWER(COALESCE(c.slug, '')) LIKE :company_search
                OR LOWER(COALESCE(c.email, '')) LIKE :company_search
                OR LOWER(COALESCE(c.phone, '')) LIKE :company_search
                OR LOWER(COALESCE(c.whatsapp, '')) LIKE :company_search
                OR LOWER(COALESCE(c.document_number, '')) LIKE :company_search
                OR CAST(c.id AS CHAR) = :company_id_search
            )";
            $params['company_search'] = '%' . strtolower($search) . '%';
            $params['company_id_search'] = $search;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'c.status = :status';
            $params['status'] = $status;
        }

        $subscriptionStatus = trim((string) ($filters['subscription_status'] ?? ''));
        if ($subscriptionStatus !== '') {
            $where[] = 'c.subscription_status = :subscription_status';
            $params['subscription_status'] = $subscriptionStatus;
        }

        $planId = (int) ($filters['plan_id'] ?? 0);
        if ($planId > 0) {
            $where[] = 'c.plan_id = :plan_id';
            $params['plan_id'] = $planId;
        }

        return [implode(' AND ', $where), $params];
    }

    private function latestSubscriptionJoinSql(): string
    {
        return "
            LEFT JOIN subscriptions s
                ON s.id = (
                    SELECT s2.id
                    FROM subscriptions s2
                    WHERE s2.company_id = c.id
                    ORDER BY
                        CASE s2.status
                            WHEN 'ativa' THEN 0
                            WHEN 'trial' THEN 1
                            WHEN 'vencida' THEN 2
                            WHEN 'cancelada' THEN 3
                            ELSE 4
                        END,
                        s2.created_at DESC,
                        s2.id DESC
                    LIMIT 1
                )
        ";
    }
}
