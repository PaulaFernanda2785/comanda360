<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Exceptions\ValidationException;
use App\Repositories\CashMovementRepository;
use App\Repositories\CashRegisterRepository;
use Throwable;

final class CashRegisterService
{
    public function __construct(
        private readonly CashRegisterRepository $cashRegisters = new CashRegisterRepository(),
        private readonly CashMovementRepository $cashMovements = new CashMovementRepository()
    ) {}

    public function list(int $companyId): array
    {
        return $this->cashRegisters->allByCompany($companyId);
    }

    public function currentOpen(int $companyId): ?array
    {
        $cashRegister = $this->cashRegisters->findOpenByCompany($companyId);
        if ($cashRegister === null) {
            return null;
        }

        $totals = $this->cashMovements->totalsByCashRegister($companyId, (int) $cashRegister['id']);
        $cashRegister['total_income'] = $totals['total_income'];
        $cashRegister['total_expense'] = $totals['total_expense'];
        $cashRegister['total_adjustment'] = $totals['total_adjustment'];
        $cashRegister['current_calculated_amount'] = round(
            (float) $cashRegister['opening_amount']
            + $totals['total_income']
            - $totals['total_expense']
            + $totals['total_adjustment'],
            2
        );

        return $cashRegister;
    }

    public function open(int $companyId, int $userId, array $input): int
    {
        if ($userId <= 0) {
            throw new ValidationException('Usuario autenticado invalido para abrir caixa.');
        }

        $openingAmount = $this->parseMoney($input['opening_amount'] ?? 0);
        $notes = $this->normalizeNullableText($input['notes'] ?? null);

        if ($openingAmount < 0) {
            throw new ValidationException('O valor de abertura nao pode ser negativo.');
        }

        if ($this->cashRegisters->findOpenByCompany($companyId) !== null) {
            throw new ValidationException('Ja existe um caixa aberto para esta empresa.');
        }

        return $this->cashRegisters->createOpen([
            'company_id' => $companyId,
            'opened_by_user_id' => $userId,
            'opening_amount' => $openingAmount,
            'notes' => $notes,
        ]);
    }

    public function close(int $companyId, int $userId, array $input): int
    {
        if ($userId <= 0) {
            throw new ValidationException('Usuario autenticado invalido para fechar caixa.');
        }

        $closingAmountReported = $this->parseMoney($input['closing_amount_reported'] ?? 0);
        $notes = $this->normalizeNullableText($input['notes'] ?? null);

        if ($closingAmountReported < 0) {
            throw new ValidationException('O valor informado no fechamento nao pode ser negativo.');
        }

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $openCashRegister = $this->cashRegisters->findOpenByCompanyForUpdate($companyId);
            if ($openCashRegister === null) {
                throw new ValidationException('Nao existe caixa aberto para fechamento.');
            }

            $cashRegisterId = (int) $openCashRegister['id'];
            $totals = $this->cashMovements->totalsByCashRegister($companyId, $cashRegisterId);

            $closingAmountCalculated = round(
                (float) $openCashRegister['opening_amount']
                + $totals['total_income']
                - $totals['total_expense']
                + $totals['total_adjustment'],
                2
            );

            $this->cashRegisters->close(
                $companyId,
                $cashRegisterId,
                $userId,
                $closingAmountReported,
                $closingAmountCalculated,
                $notes
            );

            $db->commit();
            return $cashRegisterId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function ticketPrintContext(int $companyId, int $cashRegisterId = 0): array
    {
        $cashRegister = null;

        if ($cashRegisterId > 0) {
            $cashRegister = $this->cashRegisters->findDetailedByIdForCompany($companyId, $cashRegisterId);
            if ($cashRegister === null) {
                throw new ValidationException('Caixa nao encontrado para impressao.');
            }
        } else {
            $open = $this->cashRegisters->findOpenByCompany($companyId);
            if ($open !== null) {
                $cashRegister = $this->cashRegisters->findDetailedByIdForCompany($companyId, (int) ($open['id'] ?? 0));
            }

            if ($cashRegister === null) {
                $cashRegister = $this->cashRegisters->latestDetailedByCompany($companyId);
            }

            if ($cashRegister === null) {
                throw new ValidationException('Nenhum caixa disponivel para gerar ticket.');
            }
        }

        $openingAmount = (float) ($cashRegister['opening_amount'] ?? 0);
        $income = (float) ($cashRegister['total_income'] ?? 0);
        $expense = (float) ($cashRegister['total_expense'] ?? 0);
        $adjustment = (float) ($cashRegister['total_adjustment'] ?? 0);
        $calculated = $cashRegister['closing_amount_calculated'] !== null
            ? (float) $cashRegister['closing_amount_calculated']
            : round($openingAmount + $income - $expense + $adjustment, 2);
        $reported = $cashRegister['closing_amount_reported'] !== null
            ? (float) $cashRegister['closing_amount_reported']
            : null;
        $difference = $reported !== null ? round($reported - $calculated, 2) : null;

        $cashRegister['current_calculated_amount'] = $calculated;
        $cashRegister['difference_amount'] = $difference;

        return [
            'cash_register' => $cashRegister,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function parseMoney(mixed $value): float
    {
        if (is_float($value) || is_int($value)) {
            return round((float) $value, 2);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }

        $normalized = str_replace(',', '.', $raw);
        if (!is_numeric($normalized)) {
            throw new ValidationException('Valor monetario invalido informado.');
        }

        return round((float) $normalized, 2);
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text !== '' ? $text : null;
    }
}
