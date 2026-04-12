<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SubscriptionRepository extends BaseRepository
{
    public function allForSaas(): array
    {
        $sql = "
            SELECT
                s.id,
                s.company_id,
                s.plan_id,
                s.status,
                s.billing_cycle,
                s.amount,
                s.starts_at,
                s.ends_at,
                s.canceled_at,
                s.created_at,
                c.name AS company_name,
                c.slug AS company_slug,
                p.name AS plan_name,
                p.slug AS plan_slug
            FROM subscriptions s
            INNER JOIN companies c ON c.id = s.company_id
            INNER JOIN plans p ON p.id = s.plan_id
            ORDER BY s.created_at DESC, s.id DESC
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function summary(): array
    {
        $stmt = $this->db()->prepare("
            SELECT
                COUNT(*) AS total_subscriptions,
                SUM(CASE WHEN status = 'ativa' THEN 1 ELSE 0 END) AS active_subscriptions,
                SUM(CASE WHEN status = 'trial' THEN 1 ELSE 0 END) AS trial_subscriptions,
                SUM(CASE WHEN status = 'vencida' THEN 1 ELSE 0 END) AS expired_subscriptions,
                SUM(CASE WHEN status = 'ativa' AND billing_cycle = 'mensal' THEN amount ELSE 0 END) AS active_monthly_mrr
            FROM subscriptions
        ");
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }
}
