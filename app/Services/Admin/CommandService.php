<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Exceptions\ValidationException;
use App\Repositories\CommandRepository;
use App\Repositories\OrderRepository;
use App\Repositories\TableRepository;

final class CommandService
{
    public function __construct(
        private readonly CommandRepository $commands = new CommandRepository(),
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly TableRepository $tables = new TableRepository()
    ) {}

    public function listOpen(int $companyId): array
    {
        return $this->commands->openCommandsByCompany($companyId);
    }

    public function open(int $companyId, int $userId, array $input): int
    {
        $tableId = (int) ($input['table_id'] ?? 0);
        $customerName = trim((string) ($input['customer_name'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));

        if ($tableId <= 0) {
            throw new ValidationException('Selecione uma mesa valida.');
        }

        $table = $this->tables->findById($companyId, $tableId);
        if ($table === null) {
            throw new ValidationException('Mesa nao encontrada para esta empresa.');
        }

        if ((string) ($table['status'] ?? '') === 'bloqueada') {
            throw new ValidationException('Mesa bloqueada nao pode receber abertura de comanda.');
        }

        if ($customerName === '') {
            throw new ValidationException('Informe o nome do cliente.');
        }

        $commandId = $this->commands->create([
            'company_id' => $companyId,
            'table_id' => $tableId,
            'customer_id' => null,
            'opened_by_user_id' => $userId > 0 ? $userId : null,
            'customer_name' => $customerName,
            'notes' => $notes !== '' ? $notes : null,
        ]);

        $this->tables->updateStatusForCompany($companyId, $tableId, 'ocupada');

        return $commandId;
    }

    public function update(int $companyId, int $commandId, array $input): void
    {
        if ($companyId <= 0 || $commandId <= 0) {
            throw new ValidationException('Comanda invalida para edicao.');
        }

        $command = $this->commands->findOpenById($companyId, $commandId);
        if ($command === null) {
            throw new ValidationException('Comanda aberta nao encontrada para edicao.');
        }

        $customerName = trim((string) ($input['customer_name'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));

        if ($customerName === '') {
            throw new ValidationException('Informe o nome do cliente para editar a comanda.');
        }

        $this->commands->updateOpenCommand($companyId, $commandId, [
            'customer_name' => $customerName,
            'notes' => $notes !== '' ? $notes : null,
        ]);
    }

    public function cancel(int $companyId, int $commandId): void
    {
        if ($companyId <= 0 || $commandId <= 0) {
            throw new ValidationException('Comanda invalida para cancelamento.');
        }

        $command = $this->commands->findOpenById($companyId, $commandId);
        if ($command === null) {
            throw new ValidationException('Comanda aberta nao encontrada para cancelamento.');
        }

        $unsettledOrders = $this->orders->countUnsettledByCommand($companyId, $commandId);
        if ($unsettledOrders > 0) {
            throw new ValidationException('A comanda so pode ser cancelada quando estiver sem pedidos ativos e sem pagamentos pendentes.');
        }

        $this->commands->cancel($companyId, $commandId);

        if (($command['table_id'] ?? null) !== null) {
            $tableId = (int) $command['table_id'];
            if ($tableId > 0) {
                $openCommandsForTable = $this->commands->countOpenByTable($companyId, $tableId);
                $this->tables->updateStatusForCompany($companyId, $tableId, $openCommandsForTable > 0 ? 'ocupada' : 'livre');
            }
        }
    }
}
