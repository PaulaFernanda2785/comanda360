<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Exceptions\ValidationException;
use App\Repositories\CommandRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\OrderRepository;
use App\Repositories\OrderStatusHistoryRepository;
use App\Repositories\ProductRepository;
use PDOException;
use RuntimeException;
use Throwable;

final class OrderService
{
    public function __construct(
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly OrderItemRepository $orderItems = new OrderItemRepository(),
        private readonly OrderStatusHistoryRepository $statusHistory = new OrderStatusHistoryRepository(),
        private readonly CommandRepository $commands = new CommandRepository(),
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly CommandLifecycleService $commandLifecycle = new CommandLifecycleService()
    ) {}

    public function list(int $companyId): array
    {
        $orders = $this->orders->allByCompany($companyId);
        if ($orders === []) {
            return [];
        }

        $orderIds = array_map(static fn (array $order): int => (int) $order['id'], $orders);
        $latestHistoryByOrderId = $this->statusHistory->latestByOrderIds($companyId, $orderIds);

        foreach ($orders as &$order) {
            $orderId = (int) ($order['id'] ?? 0);
            $history = $latestHistoryByOrderId[$orderId] ?? null;
            $order['latest_status_changed_at'] = $history['changed_at'] ?? null;
            $order['latest_status_changed_by'] = $history['changed_by_user_name'] ?? null;
            $order['latest_status_note'] = $history['notes'] ?? null;
            $order['next_statuses'] = $this->nextStatusesFor(
                (string) ($order['status'] ?? ''),
                (string) ($order['payment_status'] ?? '')
            );
            $order['can_send_kitchen'] = ((string) ($order['status'] ?? '')) === 'pending';
        }
        unset($order);

        return $orders;
    }

    public function createFromCommand(int $companyId, int $userId, array $input): int
    {
        $commandId = (int) ($input['command_id'] ?? 0);
        if ($commandId <= 0) {
            throw new ValidationException('Selecione uma comanda aberta valida.');
        }

        $command = $this->commands->findOpenById($companyId, $commandId);
        if ($command === null) {
            throw new ValidationException('A comanda selecionada nao esta aberta ou nao pertence a empresa.');
        }

        $discountAmount = $this->parseMoney($input['discount_amount'] ?? 0);
        $deliveryFee = $this->parseMoney($input['delivery_fee'] ?? 0);

        if ($discountAmount < 0) {
            throw new ValidationException('O desconto nao pode ser negativo.');
        }

        if ($deliveryFee < 0) {
            throw new ValidationException('A taxa de entrega nao pode ser negativa.');
        }

        [$items, $subtotal] = $this->normalizeItems($companyId, $input);

        $totalAmount = round(($subtotal - $discountAmount) + $deliveryFee, 2);
        if ($totalAmount < 0) {
            throw new ValidationException('O total do pedido nao pode ser negativo.');
        }

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $orderId = $this->createOrderWithUniqueNumber([
                'company_id' => $companyId,
                'command_id' => (int) $command['id'],
                'table_id' => $command['table_id'] !== null ? (int) $command['table_id'] : null,
                'customer_id' => $command['customer_id'] !== null ? (int) $command['customer_id'] : null,
                'channel' => 'table',
                'status' => 'pending',
                'payment_status' => 'pending',
                'customer_name' => $command['customer_name'] !== null ? trim((string) $command['customer_name']) : null,
                'subtotal_amount' => $subtotal,
                'discount_amount' => $discountAmount,
                'delivery_fee' => $deliveryFee,
                'total_amount' => $totalAmount,
                'notes' => $this->normalizeNullableText($input['notes'] ?? null),
                'placed_by' => 'waiter',
                'placed_by_user_id' => $userId > 0 ? $userId : null,
            ]);

            $this->orderItems->createBatch($companyId, $orderId, $items);
            $this->statusHistory->create([
                'company_id' => $companyId,
                'order_id' => $orderId,
                'old_status' => null,
                'new_status' => 'pending',
                'changed_by_user_id' => $userId > 0 ? $userId : null,
                'notes' => 'Pedido criado.',
            ]);

            $db->commit();
            return $orderId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function availableStatuses(): array
    {
        return ['pending', 'received', 'preparing', 'ready', 'delivered', 'finished', 'canceled'];
    }

    public function sendToKitchen(int $companyId, int $userId, array $input): void
    {
        $orderId = (int) ($input['order_id'] ?? 0);

        if ($orderId <= 0) {
            throw new ValidationException('Pedido invalido para envio a cozinha.');
        }

        $order = $this->orders->findByIdForCompany($companyId, $orderId);
        if ($order === null) {
            throw new ValidationException('Pedido nao pertence a empresa autenticada.');
        }

        if ((string) ($order['status'] ?? '') !== 'pending') {
            throw new ValidationException('Somente pedidos pendentes podem ser enviados para cozinha.');
        }

        $this->updateStatus($companyId, $userId, [
            'order_id' => $orderId,
            'new_status' => 'received',
            'status_notes' => 'Enviado para cozinha.',
        ]);
    }

    public function updateStatus(int $companyId, int $userId, array $input): void
    {
        $orderId = (int) ($input['order_id'] ?? 0);
        $newStatus = trim((string) ($input['new_status'] ?? ''));
        $notes = $this->normalizeNullableText($input['status_notes'] ?? null);

        if ($orderId <= 0) {
            throw new ValidationException('Pedido invalido para alteracao de status.');
        }

        if ($userId <= 0) {
            throw new ValidationException('Usuario autenticado invalido para alteracao de status.');
        }

        if (!in_array($newStatus, $this->availableStatuses(), true)) {
            throw new ValidationException('Status informado e invalido.');
        }

        $order = $this->orders->findByIdForCompany($companyId, $orderId);
        if ($order === null) {
            throw new ValidationException('Pedido nao pertence a empresa autenticada.');
        }

        $oldStatus = (string) ($order['status'] ?? '');
        $paymentStatus = (string) ($order['payment_status'] ?? '');
        if ($oldStatus === $newStatus) {
            throw new ValidationException('O pedido ja esta com esse status.');
        }

        if (!$this->isAllowedTransition($oldStatus, $newStatus, $paymentStatus)) {
            throw new ValidationException('Transicao de status nao permitida para este pedido.');
        }

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $this->orders->updateStatus($companyId, $orderId, $newStatus);

            if ($newStatus === 'canceled') {
                $nextPaymentStatus = $this->resolvePaymentStatusOnCancellation((string) ($order['payment_status'] ?? 'pending'));
                if ($nextPaymentStatus !== (string) ($order['payment_status'] ?? '')) {
                    $this->orders->updatePaymentStatus($companyId, $orderId, $nextPaymentStatus);
                }
            }

            $this->statusHistory->create([
                'company_id' => $companyId,
                'order_id' => $orderId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by_user_id' => $userId,
                'notes' => $notes,
            ]);

            $commandId = $order['command_id'] !== null ? (int) $order['command_id'] : null;
            $this->commandLifecycle->tryCloseWhenOrdersSettled($companyId, $commandId);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function createOrderWithUniqueNumber(array $data): int
    {
        $maxAttempts = 5;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $data['order_number'] = $this->generateOrderNumber((int) $data['company_id']);

            try {
                return $this->orders->create($data);
            } catch (PDOException $e) {
                if (!$this->isOrderNumberConflict($e)) {
                    throw $e;
                }
            }
        }

        throw new RuntimeException('Nao foi possivel gerar um numero de pedido unico.');
    }

    private function generateOrderNumber(int $companyId): string
    {
        $prefix = 'PED-' . $companyId . '-' . date('Ymd');
        $lastOrderNumber = $this->orders->findLastOrderNumberByPrefix($companyId, $prefix);
        $nextSequence = 1;

        if ($lastOrderNumber !== null) {
            $pattern = '/^' . preg_quote($prefix, '/') . '-(\d+)$/';
            if (preg_match($pattern, $lastOrderNumber, $matches) === 1) {
                $nextSequence = ((int) $matches[1]) + 1;
            }
        }

        return sprintf('%s-%04d', $prefix, $nextSequence);
    }

    private function isOrderNumberConflict(PDOException $e): bool
    {
        $code = (string) $e->getCode();
        $message = strtolower($e->getMessage());

        if ($code !== '23000') {
            return false;
        }

        return str_contains($message, 'order_number') || str_contains($message, 'uq_orders_company_order_number');
    }

    private function normalizeItems(int $companyId, array $input): array
    {
        $productIds = $input['product_id'] ?? [];
        $quantities = $input['quantity'] ?? [];
        $notesList = $input['item_notes'] ?? [];

        if (!is_array($productIds) || !is_array($quantities) || !is_array($notesList)) {
            throw new ValidationException('Formato de itens invalido.');
        }

        $items = [];
        $subtotal = 0.0;
        $totalRows = count($productIds);

        for ($index = 0; $index < $totalRows; $index++) {
            $rawProductId = $productIds[$index] ?? '';
            $rawQuantity = $quantities[$index] ?? '';
            $itemNote = $this->normalizeNullableText($notesList[$index] ?? null);

            $productId = (int) $rawProductId;
            $quantity = (int) $rawQuantity;

            if ($productId <= 0 && $quantity <= 0 && $itemNote === null) {
                continue;
            }

            $rowNumber = $index + 1;

            if ($productId <= 0) {
                throw new ValidationException('Selecione um produto valido na linha ' . $rowNumber . '.');
            }

            if ($quantity < 1) {
                throw new ValidationException('A quantidade do item da linha ' . $rowNumber . ' deve ser maior ou igual a 1.');
            }

            $product = $this->products->findByIdForCompany($companyId, $productId);
            if ($product === null) {
                throw new ValidationException('O produto da linha ' . $rowNumber . ' nao pertence a empresa autenticada.');
            }

            $unitPrice = $product['promotional_price'] !== null
                ? (float) $product['promotional_price']
                : (float) $product['price'];
            $lineSubtotal = round($unitPrice * $quantity, 2);

            $subtotal = round($subtotal + $lineSubtotal, 2);
            $items[] = [
                'product_id' => (int) $product['id'],
                'product_name_snapshot' => (string) $product['name'],
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'notes' => $itemNote,
                'line_subtotal' => $lineSubtotal,
            ];
        }

        if ($items === []) {
            throw new ValidationException('Adicione ao menos um item valido ao pedido.');
        }

        return [$items, $subtotal];
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

    private function nextStatusesFor(string $status, string $paymentStatus): array
    {
        $transitions = $this->statusTransitions();
        $next = $transitions[$status] ?? [];

        // Avoid manual finalization before full payment confirmation.
        if ($paymentStatus !== 'paid') {
            $next = array_values(array_filter(
                $next,
                static fn (string $candidate): bool => $candidate !== 'finished'
            ));
        }

        return $next;
    }

    private function isAllowedTransition(string $oldStatus, string $newStatus, string $paymentStatus): bool
    {
        $allowed = $this->nextStatusesFor($oldStatus, $paymentStatus);
        return in_array($newStatus, $allowed, true);
    }

    private function statusTransitions(): array
    {
        return [
            'pending' => ['received', 'canceled'],
            'received' => ['preparing', 'canceled'],
            'preparing' => ['ready', 'canceled'],
            'ready' => ['delivered', 'finished', 'canceled'],
            'delivered' => ['finished', 'canceled'],
            'paid' => ['finished', 'canceled'],
            'finished' => [],
            'canceled' => [],
        ];
    }

    private function resolvePaymentStatusOnCancellation(string $currentPaymentStatus): string
    {
        // Keep paid/partial information when there was financial movement already.
        // Only pending transitions to canceled to avoid "pedido cancelado com pagamento pendente".
        return match ($currentPaymentStatus) {
            'paid' => 'paid',
            'partial' => 'partial',
            default => 'canceled',
        };
    }
}
