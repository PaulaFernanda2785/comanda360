<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PublicContactRepository extends BaseRepository
{
    public function create(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO public_contact_messages (
                contact_name,
                contact_email,
                company_name,
                phone,
                plan_interest,
                billing_cycle_interest,
                message,
                status,
                source_page,
                utm_source,
                utm_medium,
                utm_campaign,
                utm_term,
                utm_content,
                submitted_ip,
                user_agent,
                created_at,
                updated_at
            ) VALUES (
                :contact_name,
                :contact_email,
                :company_name,
                :phone,
                :plan_interest,
                :billing_cycle_interest,
                :message,
                'new',
                :source_page,
                :utm_source,
                :utm_medium,
                :utm_campaign,
                :utm_term,
                :utm_content,
                :submitted_ip,
                :user_agent,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'],
            'company_name' => $data['company_name'] ?? null,
            'phone' => $data['phone'],
            'plan_interest' => $data['plan_interest'] ?? null,
            'billing_cycle_interest' => $data['billing_cycle_interest'] ?? null,
            'message' => $data['message'],
            'source_page' => $data['source_page'] ?? null,
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'utm_term' => $data['utm_term'] ?? null,
            'utm_content' => $data['utm_content'] ?? null,
            'submitted_ip' => $data['submitted_ip'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function listPaginated(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(20, $perPage));

        ['where_sql' => $whereSql, 'params' => $params] = $this->buildWhereClause($filters);

        $countStmt = $this->db()->prepare("
            SELECT COUNT(*)
            FROM public_contact_messages pcm
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
                pcm.id,
                pcm.contact_name,
                pcm.contact_email,
                pcm.company_name,
                pcm.phone,
                pcm.plan_interest,
                pcm.billing_cycle_interest,
                pcm.message,
                pcm.status,
                pcm.response_channel,
                pcm.response_notes,
                pcm.source_page,
                pcm.utm_source,
                pcm.utm_medium,
                pcm.utm_campaign,
                pcm.utm_term,
                pcm.utm_content,
                pcm.submitted_ip,
                pcm.user_agent,
                pcm.responded_by_user_id,
                pcm.responded_at,
                pcm.created_at,
                pcm.updated_at,
                u.name AS responded_by_user_name
            FROM public_contact_messages pcm
            LEFT JOIN users u
                ON u.id = pcm.responded_by_user_id
            WHERE {$whereSql}
            ORDER BY
                CASE pcm.status
                    WHEN 'new' THEN 1
                    WHEN 'contacted' THEN 2
                    WHEN 'qualified' THEN 3
                    WHEN 'converted' THEN 4
                    WHEN 'archived' THEN 5
                    ELSE 9
                END,
                COALESCE(pcm.responded_at, pcm.updated_at, pcm.created_at) DESC,
                pcm.id DESC
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

    public function metrics(array $filters = []): array
    {
        ['where_sql' => $whereSql, 'params' => $params] = $this->buildWhereClause($filters);

        $stmt = $this->db()->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN pcm.status = 'new' THEN 1 ELSE 0 END) AS new_count,
                SUM(CASE WHEN pcm.status = 'contacted' THEN 1 ELSE 0 END) AS contacted_count,
                SUM(CASE WHEN pcm.status = 'qualified' THEN 1 ELSE 0 END) AS qualified_count,
                SUM(CASE WHEN pcm.status = 'converted' THEN 1 ELSE 0 END) AS converted_count,
                SUM(CASE WHEN pcm.status = 'archived' THEN 1 ELSE 0 END) AS archived_count,
                MAX(pcm.created_at) AS last_created_at
            FROM public_contact_messages pcm
            WHERE {$whereSql}
        ");
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'new_count' => (int) ($row['new_count'] ?? 0),
            'contacted_count' => (int) ($row['contacted_count'] ?? 0),
            'qualified_count' => (int) ($row['qualified_count'] ?? 0),
            'converted_count' => (int) ($row['converted_count'] ?? 0),
            'archived_count' => (int) ($row['archived_count'] ?? 0),
            'last_created_at' => (string) ($row['last_created_at'] ?? ''),
        ];
    }

    public function findById(int $contactId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                pcm.*,
                u.name AS responded_by_user_name
            FROM public_contact_messages pcm
            LEFT JOIN users u
                ON u.id = pcm.responded_by_user_id
            WHERE pcm.id = :contact_id
            LIMIT 1
        ");
        $stmt->execute(['contact_id' => $contactId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateLead(int $contactId, array $data): void
    {
        $stmt = $this->db()->prepare("
            UPDATE public_contact_messages
            SET contact_name = :contact_name,
                contact_email = :contact_email,
                company_name = :company_name,
                phone = :phone,
                plan_interest = :plan_interest,
                billing_cycle_interest = :billing_cycle_interest,
                message = :message,
                status = :status,
                response_channel = :response_channel,
                response_notes = :response_notes,
                responded_by_user_id = :responded_by_user_id,
                responded_at = :responded_at,
                updated_at = NOW()
            WHERE id = :contact_id
            LIMIT 1
        ");

        $stmt->bindValue(':contact_id', $contactId, PDO::PARAM_INT);
        $stmt->bindValue(':contact_name', (string) $data['contact_name'], PDO::PARAM_STR);
        $stmt->bindValue(':contact_email', (string) $data['contact_email'], PDO::PARAM_STR);

        $companyName = trim((string) ($data['company_name'] ?? ''));
        $stmt->bindValue(':company_name', $companyName !== '' ? $companyName : null, $companyName !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':phone', (string) $data['phone'], PDO::PARAM_STR);

        $planInterest = trim((string) ($data['plan_interest'] ?? ''));
        $stmt->bindValue(':plan_interest', $planInterest !== '' ? $planInterest : null, $planInterest !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);

        $billingCycle = trim((string) ($data['billing_cycle_interest'] ?? ''));
        $stmt->bindValue(':billing_cycle_interest', $billingCycle !== '' ? $billingCycle : null, $billingCycle !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);

        $stmt->bindValue(':message', (string) $data['message'], PDO::PARAM_STR);
        $stmt->bindValue(':status', (string) $data['status'], PDO::PARAM_STR);

        $responseChannel = trim((string) ($data['response_channel'] ?? ''));
        $stmt->bindValue(':response_channel', $responseChannel !== '' ? $responseChannel : null, $responseChannel !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);

        $responseNotes = trim((string) ($data['response_notes'] ?? ''));
        $stmt->bindValue(':response_notes', $responseNotes !== '' ? $responseNotes : null, $responseNotes !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);

        $respondedByUserId = (int) ($data['responded_by_user_id'] ?? 0);
        $stmt->bindValue(':responded_by_user_id', $respondedByUserId > 0 ? $respondedByUserId : null, $respondedByUserId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);

        $respondedAt = trim((string) ($data['responded_at'] ?? ''));
        $stmt->bindValue(':responded_at', $respondedAt !== '' ? $respondedAt : null, $respondedAt !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);

        $stmt->execute();
    }

    public function delete(int $contactId): void
    {
        $stmt = $this->db()->prepare("
            DELETE FROM public_contact_messages
            WHERE id = :contact_id
            LIMIT 1
        ");
        $stmt->execute(['contact_id' => $contactId]);
    }

    private function buildWhereClause(array $filters): array
    {
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        $responseChannel = strtolower(trim((string) ($filters['response_channel'] ?? '')));
        $search = trim((string) ($filters['search'] ?? ''));

        $where = ['1 = 1'];
        $params = [];

        if ($status !== '') {
            $where[] = 'pcm.status = :status';
            $params['status'] = $status;
        }

        if ($responseChannel !== '') {
            $where[] = 'pcm.response_channel = :response_channel';
            $params['response_channel'] = $responseChannel;
        }

        if ($search !== '') {
            $where[] = "(
                LOWER(COALESCE(pcm.contact_name, '')) LIKE :search
                OR LOWER(COALESCE(pcm.contact_email, '')) LIKE :search
                OR LOWER(COALESCE(pcm.company_name, '')) LIKE :search
                OR LOWER(COALESCE(pcm.phone, '')) LIKE :search
                OR LOWER(COALESCE(pcm.message, '')) LIKE :search
                OR CAST(pcm.id AS CHAR) = :id_search
            )";
            $params['search'] = '%' . strtolower($search) . '%';
            $params['id_search'] = $search;
        }

        return [
            'where_sql' => implode(' AND ', $where),
            'params' => $params,
        ];
    }
}
