<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CompanyRepository extends BaseRepository
{
    public function allForSaas(): array
    {
        $sql = "
            SELECT
                c.id,
                c.name,
                c.slug,
                c.email,
                c.phone,
                c.status,
                c.subscription_status,
                c.subscription_starts_at,
                c.subscription_ends_at,
                c.trial_ends_at,
                c.created_at,
                p.name AS plan_name,
                p.slug AS plan_slug
            FROM companies c
            LEFT JOIN plans p ON p.id = c.plan_id
            ORDER BY c.created_at DESC, c.id DESC
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function summary(): array
    {
        $stmt = $this->db()->prepare("
            SELECT
                COUNT(*) AS total_companies,
                SUM(CASE WHEN status = 'ativa' THEN 1 ELSE 0 END) AS active_companies,
                SUM(CASE WHEN status = 'suspensa' THEN 1 ELSE 0 END) AS suspended_companies,
                SUM(CASE WHEN subscription_status = 'trial' THEN 1 ELSE 0 END) AS trial_companies,
                SUM(CASE WHEN subscription_status = 'inadimplente' THEN 1 ELSE 0 END) AS delinquent_companies
            FROM companies
        ");
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }
}
