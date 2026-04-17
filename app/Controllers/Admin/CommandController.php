<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ValidationException;
use App\Services\Admin\CommandService;
use App\Services\Admin\OrderService;
use App\Services\Admin\TableService;

final class CommandController extends Controller
{
    public function __construct(
        private readonly CommandService $service = new CommandService(),
        private readonly TableService $tableService = new TableService(),
        private readonly OrderService $orderService = new OrderService()
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $commands = $this->service->listOpen($companyId);
        $commandOperational = $this->buildCommandOperationalMap($companyId, $commands);

        return $this->view('admin/commands/index', [
            'title' => 'Comandas Abertas',
            'user' => $user,
            'commands' => $commands,
            'commandOperational' => $commandOperational,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);

        return $this->view('admin/commands/create', [
            'title' => 'Abrir Comanda',
            'user' => $user,
            'tables' => $this->tableService->listSelectableForCommand($companyId),
        ]);
    }

    public function store(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);

        try {
            $this->service->open($companyId, $userId, $request->all());
            return $this->backWithSuccess('Comanda aberta com sucesso.', '/admin/commands');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/commands/create');
        }
    }

    public function update(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $commandId = (int) ($request->input('command_id', 0));

        try {
            $this->service->update($companyId, $commandId, $request->all());
            return $this->backWithSuccess('Comanda atualizada com sucesso.', '/admin/commands');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/commands');
        }
    }

    public function cancel(Request $request): Response
    {
        $user = Auth::user();
        $companyId = (int) ($user['company_id'] ?? 0);
        $commandId = (int) ($request->input('command_id', 0));

        try {
            $this->service->cancel($companyId, $commandId);
            return $this->backWithSuccess('Comanda cancelada com sucesso.', '/admin/commands');
        } catch (ValidationException $e) {
            return $this->backWithError($e->getMessage(), '/admin/commands');
        }
    }

    private function buildCommandOperationalMap(int $companyId, array $commands): array
    {
        $map = [];
        foreach ($commands as $command) {
            if (!is_array($command)) {
                continue;
            }

            $commandId = (int) ($command['id'] ?? 0);
            if ($commandId <= 0) {
                continue;
            }

            $map[$commandId] = [
                'orders_count' => 0,
                'items_total' => 0,
                'amount_total' => 0.0,
                'status_counts' => [
                    'pending' => 0,
                    'received' => 0,
                    'preparing' => 0,
                    'ready' => 0,
                    'delivered' => 0,
                ],
                'payment_status_counts' => [
                    'pending' => 0,
                    'partial' => 0,
                    'paid' => 0,
                ],
                'pending_payment_total' => 0,
                'can_cancel_command' => false,
            ];
        }

        if ($map === []) {
            return [];
        }

        $orders = $this->orderService->list($companyId);
        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }

            $commandId = $order['command_id'] !== null ? (int) $order['command_id'] : 0;
            if ($commandId <= 0 || !isset($map[$commandId])) {
                continue;
            }

            $paymentStatus = (string) ($order['payment_status'] ?? '');
            if ((string) ($order['status'] ?? '') !== 'canceled' && isset($map[$commandId]['payment_status_counts'][$paymentStatus])) {
                $map[$commandId]['payment_status_counts'][$paymentStatus]++;
                if (in_array($paymentStatus, ['pending', 'partial'], true)) {
                    $map[$commandId]['pending_payment_total']++;
                }
            }

            $orderStatus = (string) ($order['status'] ?? '');
            if (in_array($orderStatus, ['finished', 'canceled'], true)) {
                continue;
            }

            $map[$commandId]['orders_count']++;
            $map[$commandId]['items_total'] += (int) ($order['items_count'] ?? 0);
            $map[$commandId]['amount_total'] = round(
                (float) $map[$commandId]['amount_total'] + (float) ($order['total_amount'] ?? 0),
                2
            );

            if (isset($map[$commandId]['status_counts'][$orderStatus])) {
                $map[$commandId]['status_counts'][$orderStatus]++;
            }

        }

        foreach ($map as $commandId => $operational) {
            $ordersCount = (int) ($operational['orders_count'] ?? 0);
            $pendingPaymentTotal = (int) ($operational['pending_payment_total'] ?? 0);
            $map[$commandId]['can_cancel_command'] = $ordersCount <= 0 && $pendingPaymentTotal <= 0;
        }

        return $map;
    }
}
