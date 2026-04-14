<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Exceptions\ValidationException;
use App\Repositories\DeliveryRepository;
use App\Repositories\OrderRepository;
use App\Repositories\UserRepository;

final class DeliveryService
{
    public function __construct(
        private readonly DeliveryRepository $deliveries = new DeliveryRepository(),
        private readonly UserRepository $users = new UserRepository(),
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly OrderService $orderService = new OrderService()
    ) {}

    public function panel(int $companyId, ?int $deliveryUserId = null): array
    {
        $rows = $this->deliveries->allByCompany($companyId, null);
        if ($deliveryUserId !== null && $deliveryUserId > 0) {
            $rows = array_values(array_filter($rows, static function (array $row) use ($deliveryUserId): bool {
                $currentUserId = $row['delivery_user_id'] !== null ? (int) $row['delivery_user_id'] : null;
                $status = (string) ($row['status'] ?? 'pending');
                return $status === 'pending' || $currentUserId === $deliveryUserId;
            }));
        }
        $grouped = [
            'pending' => [],
            'assigned' => [],
            'in_route' => [],
            'delivered' => [],
            'failed' => [],
            'canceled' => [],
            'all' => [],
        ];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $status = (string) ($row['status'] ?? 'pending');
            if (!isset($grouped[$status])) {
                $status = 'pending';
            }

            $grouped[$status][] = $row;
            $grouped['all'][] = $row;
        }

        return [
            'rows' => $rows,
            'grouped' => $grouped,
            'summary' => [
                'total' => count($rows),
                'pending' => count($grouped['pending']),
                'assigned' => count($grouped['assigned']),
                'in_route' => count($grouped['in_route']),
                'delivered' => count($grouped['delivered']),
                'failed' => count($grouped['failed']),
                'canceled' => count($grouped['canceled']),
            ],
        ];
    }

    public function deliveryUsers(int $companyId): array
    {
        return $this->users->deliveryUsersByCompany($companyId);
    }

    public function updateProgress(int $companyId, int $actingUserId, array $input): void
    {
        $deliveryId = (int) ($input['delivery_id'] ?? 0);
        $newStatus = strtolower(trim((string) ($input['new_status'] ?? '')));
        $deliveryUserIdRaw = (int) ($input['delivery_user_id'] ?? 0);
        $notes = $this->normalizeNullableText($input['notes'] ?? null);

        if ($deliveryUserIdRaw > 0 && $newStatus === 'pending') {
            $newStatus = 'assigned';
        }

        if ($deliveryId <= 0) {
            throw new ValidationException('Entrega invalida para atualizacao.');
        }

        if (!in_array($newStatus, ['pending', 'assigned', 'in_route', 'delivered', 'failed', 'canceled'], true)) {
            throw new ValidationException('Status de entrega invalido.');
        }

        $delivery = $this->deliveries->findByIdForCompany($companyId, $deliveryId);
        if ($delivery === null) {
            throw new ValidationException('Entrega nao encontrada para esta empresa.');
        }

        $currentStatus = (string) ($delivery['status'] ?? 'pending');
        if (!$this->isAllowedTransition($currentStatus, $newStatus)) {
            throw new ValidationException('Transicao de status da entrega nao permitida.');
        }

        $nextDeliveryUserId = $delivery['delivery_user_id'] !== null ? (int) $delivery['delivery_user_id'] : null;
        if ($deliveryUserIdRaw > 0) {
            $deliveryUser = $this->users->findActiveByIdForCompany($companyId, $deliveryUserIdRaw);
            if ($deliveryUser === null) {
                throw new ValidationException('Entregador invalido para esta empresa.');
            }
            $nextDeliveryUserId = $deliveryUserIdRaw;
        }

        if ($newStatus === 'assigned' && ($nextDeliveryUserId === null || $nextDeliveryUserId <= 0)) {
            throw new ValidationException('Selecione um entregador para atribuir a entrega.');
        }

        $assignedAt = $delivery['assigned_at'] ?? null;
        $leftAt = $delivery['left_at'] ?? null;
        $deliveredAt = $delivery['delivered_at'] ?? null;
        $now = date('Y-m-d H:i:s');

        if ($newStatus === 'assigned' && $assignedAt === null) {
            $assignedAt = $now;
        }
        if ($newStatus === 'in_route') {
            if ($assignedAt === null) {
                $assignedAt = $now;
            }
            if ($leftAt === null) {
                $leftAt = $now;
            }
        }
        if ($newStatus === 'delivered') {
            if ($assignedAt === null) {
                $assignedAt = $now;
            }
            if ($leftAt === null) {
                $leftAt = $now;
            }
            if ($deliveredAt === null) {
                $deliveredAt = $now;
            }
        }

        $this->deliveries->updateProgress([
            'id' => $deliveryId,
            'company_id' => $companyId,
            'delivery_user_id' => $nextDeliveryUserId,
            'status' => $newStatus,
            'assigned_at' => $assignedAt,
            'left_at' => $leftAt,
            'delivered_at' => $deliveredAt,
            'notes' => $notes,
        ]);

        if ($newStatus === 'delivered') {
            $orderId = (int) ($delivery['order_id'] ?? 0);
            if ($orderId > 0) {
                $order = $this->orders->findByIdForCompany($companyId, $orderId);
                $orderStatus = (string) ($order['status'] ?? '');
                if ($order !== null && in_array($orderStatus, ['ready'], true)) {
                    $this->orderService->updateStatus($companyId, $actingUserId, [
                        'order_id' => $orderId,
                        'new_status' => 'delivered',
                        'status_notes' => 'Entrega concluida no painel de entregas.',
                    ]);
                }
            }
        }
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text !== '' ? $text : null;
    }

    private function isAllowedTransition(string $oldStatus, string $newStatus): bool
    {
        if ($oldStatus === $newStatus) {
            return true;
        }

        $transitions = [
            'pending' => ['assigned', 'canceled'],
            'assigned' => ['in_route', 'failed', 'canceled'],
            'in_route' => ['delivered', 'failed', 'canceled'],
            'delivered' => [],
            'failed' => [],
            'canceled' => [],
        ];

        $allowed = $transitions[$oldStatus] ?? [];
        return in_array($newStatus, $allowed, true);
    }
}
