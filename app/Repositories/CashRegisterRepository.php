<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CashRegisterRepository extends BaseRepository
{
    public function allByCompany(int $companyId): array
    {
        $sql = "
            SELECT
                cr.id,
                cr.company_id,
                cr.opened_by_user_id,
                cr.closed_by_user_id,
                cr.opened_at,
                cr.closed_at,
                cr.opening_amount,
                cr.closing_amount_reported,
                cr.closing_amount_calculated,
                cr.status,
                cr.notes,
                uo.name AS opened_by_user_name,
                uc.name AS closed_by_user_name,
                COALESCE((
                    SELECT SUM(cm.amount)
                    FROM cash_movements cm
                    WHERE cm.cash_register_id = cr.id
                      AND cm.type = 'income'
                ), 0) AS total_income,
                COALESCE((
                    SELECT SUM(cm.amount)
                    FROM cash_movements cm
                    WHERE cm.cash_register_id = cr.id
                      AND cm.type = 'expense'
                ), 0) AS total_expense,
                COALESCE((
                    SELECT SUM(cm.amount)
                    FROM cash_movements cm
                    WHERE cm.cash_register_id = cr.id
                      AND cm.type = 'adjustment'
                ), 0) AS total_adjustment
            FROM cash_registers cr
            INNER JOIN users uo ON uo.id = cr.opened_by_user_id
            LEFT JOIN users uc ON uc.id = cr.closed_by_user_id
            WHERE cr.company_id = :company_id
            ORDER BY cr.opened_at DESC, cr.id DESC
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findOpenByCompany(int $companyId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                cr.id,
                cr.company_id,
                cr.opened_by_user_id,
                cr.opened_at,
                cr.opening_amount,
                cr.notes,
                cr.status,
                uo.name AS opened_by_user_name
            FROM cash_registers cr
            LEFT JOIN users uo ON uo.id = cr.opened_by_user_id
            WHERE cr.company_id = :company_id
              AND cr.status = 'open'
            ORDER BY cr.opened_at DESC, cr.id DESC
            LIMIT 1
        ");
        $stmt->execute(['company_id' => $companyId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findOpenByCompanyForUpdate(int $companyId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                id,
                company_id,
                opened_by_user_id,
                opened_at,
                opening_amount,
                notes,
                status
            FROM cash_registers
            WHERE company_id = :company_id
              AND status = 'open'
            ORDER BY opened_at DESC, id DESC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute(['company_id' => $companyId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findDetailedByIdForCompany(int $companyId, int $cashRegisterId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                cr.id,
                cr.company_id,
                cr.opened_by_user_id,
                cr.closed_by_user_id,
                cr.opened_at,
                cr.closed_at,
                cr.opening_amount,
                cr.closing_amount_reported,
                cr.closing_amount_calculated,
                cr.status,
                cr.notes,
                uo.name AS opened_by_user_name,
                uc.name AS closed_by_user_name,
                c.name AS company_name,
                ct.logo_path AS company_logo_path,
                COALESCE((
                    SELECT SUM(cm.amount)
                    FROM cash_movements cm
                    WHERE cm.cash_register_id = cr.id
                      AND cm.type = 'income'
                ), 0) AS total_income,
                COALESCE((
                    SELECT SUM(cm.amount)
                    FROM cash_movements cm
                    WHERE cm.cash_register_id = cr.id
                      AND cm.type = 'expense'
                ), 0) AS total_expense,
                COALESCE((
                    SELECT SUM(cm.amount)
                    FROM cash_movements cm
                    WHERE cm.cash_register_id = cr.id
                      AND cm.type = 'adjustment'
                ), 0) AS total_adjustment
            FROM cash_registers cr
            INNER JOIN companies c ON c.id = cr.company_id
            LEFT JOIN company_themes ct ON ct.company_id = c.id
            INNER JOIN users uo ON uo.id = cr.opened_by_user_id
            LEFT JOIN users uc ON uc.id = cr.closed_by_user_id
            WHERE cr.company_id = :company_id
              AND cr.id = :cash_register_id
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'cash_register_id' => $cashRegisterId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function latestDetailedByCompany(int $companyId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                cr.id,
                cr.company_id,
                cr.opened_by_user_id,
                cr.closed_by_user_id,
                cr.opened_at,
                cr.closed_at,
                cr.opening_amount,
                cr.closing_amount_reported,
                cr.closing_amount_calculated,
                cr.status,
                cr.notes,
                uo.name AS opened_by_user_name,
                uc.name AS closed_by_user_name,
                c.name AS company_name,
                ct.logo_path AS company_logo_path,
                COALESCE((
                    SELECT SUM(cm.amount)
                    FROM cash_movements cm
                    WHERE cm.cash_register_id = cr.id
                      AND cm.type = 'income'
                ), 0) AS total_income,
                COALESCE((
                    SELECT SUM(cm.amount)
                    FROM cash_movements cm
                    WHERE cm.cash_register_id = cr.id
                      AND cm.type = 'expense'
                ), 0) AS total_expense,
                COALESCE((
                    SELECT SUM(cm.amount)
                    FROM cash_movements cm
                    WHERE cm.cash_register_id = cr.id
                      AND cm.type = 'adjustment'
                ), 0) AS total_adjustment
            FROM cash_registers cr
            INNER JOIN companies c ON c.id = cr.company_id
            LEFT JOIN company_themes ct ON ct.company_id = c.id
            INNER JOIN users uo ON uo.id = cr.opened_by_user_id
            LEFT JOIN users uc ON uc.id = cr.closed_by_user_id
            WHERE cr.company_id = :company_id
            ORDER BY cr.opened_at DESC, cr.id DESC
            LIMIT 1
        ");
        $stmt->execute(['company_id' => $companyId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createOpen(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO cash_registers (
                company_id,
                opened_by_user_id,
                opened_at,
                opening_amount,
                status,
                notes,
                created_at
            ) VALUES (
                :company_id,
                :opened_by_user_id,
                NOW(),
                :opening_amount,
                'open',
                :notes,
                NOW()
            )
        ");
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function close(
        int $companyId,
        int $cashRegisterId,
        int $closedByUserId,
        float $closingAmountReported,
        float $closingAmountCalculated,
        ?string $notes
    ): void {
        $stmt = $this->db()->prepare("
            UPDATE cash_registers
            SET
                closed_by_user_id = :closed_by_user_id,
                closed_at = NOW(),
                closing_amount_reported = :closing_amount_reported,
                closing_amount_calculated = :closing_amount_calculated,
                status = 'closed',
                notes = :notes,
                updated_at = NOW()
            WHERE company_id = :company_id
              AND id = :id
              AND status = 'open'
        ");
        $stmt->execute([
            'closed_by_user_id' => $closedByUserId,
            'closing_amount_reported' => $closingAmountReported,
            'closing_amount_calculated' => $closingAmountCalculated,
            'notes' => $notes,
            'company_id' => $companyId,
            'id' => $cashRegisterId,
        ]);
    }
}
