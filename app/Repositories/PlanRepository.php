<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PlanRepository extends BaseRepository
{
    public function allForSaas(): array
    {
        $sql = "
            SELECT
                id,
                name,
                slug,
                description,
                price_monthly,
                price_yearly,
                max_users,
                max_products,
                max_tables,
                status,
                created_at
            FROM plans
            ORDER BY price_monthly ASC, id ASC
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function summary(): array
    {
        $stmt = $this->db()->prepare("
            SELECT
                COUNT(*) AS total_plans,
                SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) AS active_plans
            FROM plans
        ");
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }
}
