<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Exceptions\ValidationException;
use App\Repositories\KitchenPrintLogRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\OrderRepository;
use App\Repositories\OrderStatusHistoryRepository;

final class KitchenService
{
    public function __construct(
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly OrderItemRepository $orderItems = new OrderItemRepository(),
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
        $itemsByOrderId = $this->itemsByOrderId($companyId, $orderIds);

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
            $order['items'] = is_array($itemsByOrderId[$orderId] ?? null) ? $itemsByOrderId[$orderId] : [];

            $status = (string) ($order['status'] ?? '');
            if (!isset($grouped[$status])) {
                continue;
            }

            $grouped[$status][] = $order;
            $grouped['all'][] = $order;
        }

        return $grouped;
    }

    private function itemsByOrderId(int $companyId, array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $orderIds)));
        $orderIds = array_values(array_filter($orderIds, static fn (int $id): bool => $id > 0));
        if ($orderIds === []) {
            return [];
        }

        $itemRows = $this->orderItems->activeItemsByOrderIds($companyId, $orderIds);
        if ($itemRows === []) {
            return [];
        }

        $orderItemIds = array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $itemRows);
        $additionalRows = $this->orderItems->additionalsByOrderItemIds($companyId, $orderItemIds);
        $additionalsByOrderItemId = $this->indexAdditionalsByOrderItemId($additionalRows);

        return $this->indexItemsByOrderId($itemRows, $additionalsByOrderItemId);
    }

    private function indexAdditionalsByOrderItemId(array $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $orderItemId = (int) ($row['order_item_id'] ?? 0);
            if ($orderItemId <= 0) {
                continue;
            }

            if (!isset($indexed[$orderItemId])) {
                $indexed[$orderItemId] = [];
            }

            $indexed[$orderItemId][] = [
                'name' => (string) ($row['additional_name_snapshot'] ?? ''),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
                'line_subtotal' => (float) ($row['line_subtotal'] ?? 0),
            ];
        }

        return $indexed;
    }

    private function indexItemsByOrderId(array $rows, array $additionalsByOrderItemId): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $orderId = (int) ($row['order_id'] ?? 0);
            $orderItemId = (int) ($row['id'] ?? 0);
            if ($orderId <= 0 || $orderItemId <= 0) {
                continue;
            }

            if (!isset($indexed[$orderId])) {
                $indexed[$orderId] = [];
            }

            $notes = trim((string) ($row['notes'] ?? ''));
            $indexed[$orderId][] = [
                'id' => $orderItemId,
                'name' => (string) ($row['product_name_snapshot'] ?? ''),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
                'line_subtotal' => (float) ($row['line_subtotal'] ?? 0),
                'notes' => $notes !== '' ? $notes : null,
                'additionals' => is_array($additionalsByOrderItemId[$orderItemId] ?? null) ? $additionalsByOrderItemId[$orderItemId] : [],
            ];
        }

        return $indexed;
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
        if ($status !== 'ready') {
            throw new ValidationException('A emissao de ticket e permitida apenas para pedidos prontos.');
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
