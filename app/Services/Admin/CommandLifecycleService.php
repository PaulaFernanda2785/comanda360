<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\CommandRepository;
use App\Repositories\OrderRepository;
use App\Repositories\TableRepository;

final class CommandLifecycleService
{
    public function __construct(
        private readonly CommandRepository $commands = new CommandRepository(),
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly TableRepository $tables = new TableRepository()
    ) {}

    public function tryCloseWhenOrdersSettled(int $companyId, ?int $commandId): bool
    {
        if ($commandId === null || $commandId <= 0) {
            return false;
        }

        $command = $this->commands->findByIdForCompany($companyId, $commandId);
        if ($command === null || (string) ($command['status'] ?? '') !== 'aberta') {
            return false;
        }

        $totalOrders = $this->orders->countByCommand($companyId, $commandId);
        if ($totalOrders <= 0) {
            return false;
        }

        $unsettledOrders = $this->orders->countUnsettledByCommand($companyId, $commandId);
        if ($unsettledOrders > 0) {
            return false;
        }

        $canceledOrders = $this->orders->countByCommandAndStatus($companyId, $commandId, 'canceled');
        if ($canceledOrders === $totalOrders) {
            $this->commands->cancel($companyId, $commandId);
        } else {
            $this->commands->close($companyId, $commandId);
        }

        if ($command['table_id'] !== null) {
            $tableId = (int) $command['table_id'];
            $openCommandsForTable = $this->commands->countOpenByTable($companyId, $tableId);

            if ($openCommandsForTable <= 0) {
                $this->tables->updateStatusForCompany($companyId, $tableId, 'livre');
            } else {
                $this->tables->updateStatusForCompany($companyId, $tableId, 'ocupada');
            }
        }

        return true;
    }
}
