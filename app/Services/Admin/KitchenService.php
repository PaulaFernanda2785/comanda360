<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Exceptions\ValidationException;
use App\Repositories\KitchenPrintLogRepository;
use App\Repositories\OrderRepository;
use App\Repositories\OrderStatusHistoryRepository;

final class KitchenService
{
    public function __construct(
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly OrderStatusHistoryRepository $statusHistory = new OrderStatusHistoryRepository(),
        private readonly KitchenPrintLogRepository $printLogs = new KitchenPrintLogRepository(),
        private readonly OrderService $orderService = new OrderService()
    ) {}

    public function queue(int $companyId): array
    {
        $orders = $this->orders->kitchenQueueByCompany($companyId);
        if ($orders === []) {
            return [
                'received' => [],
                'preparing' => [],
                'ready' => [],
                'all' => [],
            ];
        }

        $orderIds = array_map(static fn (array $order): int => (int) $order['id'], $orders);
        $latestHistoryByOrderId = $this->statusHistory->latestByOrderIds($companyId, $orderIds);
        $latestPrintByOrderId = $this->printLogs->latestKitchenTicketByOrderIds($companyId, $orderIds);

        $grouped = [
            'received' => [],
            'preparing' => [],
            'ready' => [],
            'all' => [],
        ];

        foreach ($orders as $order) {
            $orderId = (int) ($order['id'] ?? 0);
            $history = $latestHistoryByOrderId[$orderId] ?? null;
            $print = $latestPrintByOrderId[$orderId] ?? null;

            $order['latest_status_changed_at'] = $history['changed_at'] ?? null;
            $order['latest_status_changed_by'] = $history['changed_by_user_name'] ?? null;
            $order['last_printed_at'] = $print['printed_at'] ?? null;
            $order['last_printed_by'] = $print['printed_by_user_name'] ?? null;

            $status = (string) ($order['status'] ?? '');
            if (!isset($grouped[$status])) {
                continue;
            }

            $grouped[$status][] = $order;
            $grouped['all'][] = $order;
        }

        return $grouped;
    }

    public function recentPrints(int $companyId, int $limit = 20): array
    {
        return $this->printLogs->recentKitchenTicketsByCompany($companyId, $limit);
    }

    public function updateQueueStatus(int $companyId, int $userId, array $input): void
    {
        $orderId = (int) ($input['order_id'] ?? 0);
        $newStatus = trim((string) ($input['new_status'] ?? ''));

        if ($orderId <= 0) {
            throw new ValidationException('Pedido invalido para alteracao de status.');
        }

        if ($userId <= 0) {
            throw new ValidationException('Usuario autenticado invalido para alteracao de status.');
        }

        $order = $this->orders->findByIdForCompany($companyId, $orderId);
        if ($order === null) {
            throw new ValidationException('Pedido nao pertence a empresa autenticada.');
        }

        $oldStatus = (string) ($order['status'] ?? '');
        if (!$this->isAllowedKitchenTransition($oldStatus, $newStatus)) {
            throw new ValidationException('Transicao invalida para o fluxo da cozinha.');
        }

        $this->orderService->updateStatus($companyId, $userId, [
            'order_id' => $orderId,
            'new_status' => $newStatus,
            'status_notes' => 'Atualizacao no painel de cozinha.',
        ]);
    }

    public function emitKitchenTicket(int $companyId, int $userId, array $input): int
    {
        $orderId = (int) ($input['order_id'] ?? 0);
        $notes = trim((string) ($input['print_notes'] ?? ''));

        if ($orderId <= 0) {
            throw new ValidationException('Pedido invalido para emissao de ticket.');
        }

        $order = $this->orders->findByIdForCompany($companyId, $orderId);
        if ($order === null) {
            throw new ValidationException('Pedido nao pertence a empresa autenticada.');
        }

        $status = (string) ($order['status'] ?? '');
        if (!in_array($status, ['received', 'preparing', 'ready'], true)) {
            throw new ValidationException('A emissao de ticket e permitida apenas para pedidos na fila da cozinha.');
        }

        return $this->printLogs->createKitchenTicket(
            $companyId,
            $orderId,
            $userId > 0 ? $userId : null,
            $notes !== '' ? $notes : null
        );
    }

    private function isAllowedKitchenTransition(string $oldStatus, string $newStatus): bool
    {
        $allowedTransitions = [
            'received' => ['preparing'],
            'preparing' => ['ready'],
            'ready' => [],
        ];

        $allowed = $allowedTransitions[$oldStatus] ?? [];
        return in_array($newStatus, $allowed, true);
    }
}

