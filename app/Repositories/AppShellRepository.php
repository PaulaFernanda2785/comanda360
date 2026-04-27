<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AppShellRepository extends BaseRepository
{
    public function findCompanyShellConfig(int $companyId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                c.id,
                c.name,
                ct.primary_color,
                ct.secondary_color,
                ct.accent_color,
                ct.main_card_color,
                ct.logo_path,
                ct.banner_path,
                ct.title,
                ct.description,
                ct.footer_text,
                ct.show_public_totals,
                ct.show_public_tickets
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
}
