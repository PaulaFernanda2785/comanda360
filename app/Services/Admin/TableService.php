<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Exceptions\ValidationException;
use App\Repositories\CommandRepository;
use App\Repositories\TableRepository;

final class TableService
{
    private const ALLOWED_STATUSES = [
        'livre',
        'ocupada',
        'aguardando_fechamento',
        'bloqueada',
    ];

    public function __construct(
        private readonly TableRepository $tables = new TableRepository(),
        private readonly CommandRepository $commands = new CommandRepository(),
        private readonly CompanyPlanLimitService $planLimits = new CompanyPlanLimitService()
    ) {}

    public function list(int $companyId): array
    {
        return $this->tables->allByCompany($companyId);
    }

    public function listSelectableForCommand(int $companyId): array
    {
        $tables = $this->list($companyId);

        return array_values(array_filter(
            $tables,
            static fn (array $table): bool => (string) ($table['status'] ?? '') !== 'bloqueada'
        ));
    }

    public function panel(int $companyId): array
    {
        $tables = $this->list($companyId);
        $summary = [
            'total' => count($tables),
            'livre' => 0,
            'ocupada' => 0,
            'aguardando_fechamento' => 0,
            'bloqueada' => 0,
        ];

        foreach ($tables as $table) {
            $status = (string) ($table['status'] ?? '');
            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            }
        }

        return [
            'summary' => $summary,
            'tables' => $tables,
            'plan_limit' => $this->planLimits->usageSummary($companyId, 'tables', (int) ($summary['total'] ?? 0)),
        ];
    }

    public function create(int $companyId, array $input): int
    {
        $this->planLimits->assertCanCreate($companyId, 'tables', $this->tables->countByCompany($companyId));
        $payload = $this->normalizePayload($companyId, $input, null);
        $number = (int) $payload['number'];
        $token = 'mesa-' . $companyId . '-' . $number . '-' . bin2hex(random_bytes(4));

        return $this->tables->create([
            'company_id' => $companyId,
            'name' => $payload['name'],
            'number' => $payload['number'],
            'capacity' => $payload['capacity'],
            'qr_code_token' => $token,
            'status' => $payload['status'],
        ]);
    }

    public function planLimit(int $companyId): array
    {
        return $this->planLimits->usageSummary($companyId, 'tables', $this->tables->countByCompany($companyId));
    }

    public function findForEdit(int $companyId, int $tableId): array
    {
        if ($tableId <= 0) {
            throw new ValidationException('Mesa invalida para edicao.');
        }

        $table = $this->tables->findById($companyId, $tableId);
        if ($table === null) {
            throw new ValidationException('Mesa nao encontrada para a empresa autenticada.');
        }

        return $table;
    }

    public function update(int $companyId, int $tableId, array $input): void
    {
        $this->findForEdit($companyId, $tableId);
        $payload = $this->normalizePayload($companyId, $input, $tableId);

        $this->tables->updateById($companyId, $tableId, $payload);
    }

    public function delete(int $companyId, int $tableId): void
    {
        $table = $this->findForEdit($companyId, $tableId);
        $openCommands = $this->commands->countOpenByTable($companyId, $tableId);
        if ($openCommands > 0) {
            throw new ValidationException('Esta mesa possui comanda aberta e nao pode ser excluida agora.');
        }

        $status = (string) ($table['status'] ?? 'livre');
        if ($status === 'ocupada' || $status === 'aguardando_fechamento') {
            throw new ValidationException('Altere o status da mesa para livre antes de excluir.');
        }

        $this->tables->deleteById($companyId, $tableId);
    }

    public function qrPrintContext(int $companyId, int $tableId): array
    {
        if ($tableId <= 0) {
            throw new ValidationException('Mesa invalida para gerar QR.');
        }

        $table = $this->tables->findWithCompanyContextById($companyId, $tableId);
        if ($table === null) {
            throw new ValidationException('Mesa nao encontrada para a empresa autenticada.');
        }

        $capacity = $table['capacity'] !== null ? (int) $table['capacity'] : null;
        $companyName = trim((string) ($table['company_name'] ?? 'Empresa'));
        $companySlug = trim((string) ($table['company_slug'] ?? 'empresa'));
        $tableNumber = (int) ($table['number'] ?? 0);
        $token = trim((string) ($table['qr_code_token'] ?? ''));
        if ($token === '') {
            throw new ValidationException('Token QR da mesa nao encontrado.');
        }

        $qrPayload = app_url('/menu-digital?empresa=' . rawurlencode($companySlug) .
            '&mesa=' . $tableNumber .
            '&token=' . rawurlencode($token));

        return [
            'table' => $table,
            'company_name' => $companyName !== '' ? $companyName : 'Empresa',
            'company_logo_path' => (string) ($table['company_logo_path'] ?? ''),
            'table_number' => $tableNumber,
            'table_name' => trim((string) ($table['name'] ?? '')),
            'table_capacity' => $capacity,
            'qr_payload' => $qrPayload,
        ];
    }

    private function normalizePayload(int $companyId, array $input, ?int $ignoreTableId): array
    {
        $number = (int) ($input['number'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        $capacityRaw = trim((string) ($input['capacity'] ?? ''));
        $statusRaw = trim((string) ($input['status'] ?? 'livre'));
        $status = $statusRaw !== '' ? $statusRaw : 'livre';

        if ($number <= 0) {
            throw new ValidationException('Informe um numero de mesa valido.');
        }

        $existing = $this->tables->findByNumber($companyId, $number, $ignoreTableId);
        if ($existing !== null) {
            throw new ValidationException('Ja existe uma mesa com esse numero.');
        }

        $capacity = null;
        if ($capacityRaw !== '') {
            $capacity = (int) $capacityRaw;
            if ($capacity <= 0) {
                throw new ValidationException('A capacidade deve ser maior que zero.');
            }
        }

        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new ValidationException('Status de mesa invalido.');
        }

        return [
            'name' => $name !== '' ? $name : 'Mesa ' . str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'number' => $number,
            'capacity' => $capacity,
            'status' => $status,
        ];
    }
}
