<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Exceptions\ValidationException;
use App\Repositories\CommandRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\DeliveryAddressRepository;
use App\Repositories\DeliveryRepository;
use App\Repositories\DeliveryZoneRepository;
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
        private readonly DeliveryZoneRepository $deliveryZones = new DeliveryZoneRepository(),
        private readonly CustomerRepository $customers = new CustomerRepository(),
        private readonly DeliveryAddressRepository $deliveryAddresses = new DeliveryAddressRepository(),
        private readonly DeliveryRepository $deliveries = new DeliveryRepository(),
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
        $orderIdsWithItems = [];
        foreach ($orders as $orderRow) {
            $status = (string) ($orderRow['status'] ?? '');
            if (!in_array($status, ['finished', 'canceled'], true)) {
                $orderIdsWithItems[] = (int) ($orderRow['id'] ?? 0);
            }
        }

        $itemsByOrderId = [];
        if ($orderIdsWithItems !== []) {
            $orderItemsRows = $this->orderItems->activeItemsByOrderIds($companyId, $orderIdsWithItems);
            $orderItemIds = array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $orderItemsRows);
            $additionalRows = $this->orderItems->additionalsByOrderItemIds($companyId, $orderItemIds);
            $additionalsByOrderItemId = $this->indexAdditionalsByOrderItemId($additionalRows);
            $itemsByOrderId = $this->indexItemsByOrderId($orderItemsRows, $additionalsByOrderItemId);
        }

        foreach ($orders as &$order) {
            $orderId = (int) ($order['id'] ?? 0);
            $history = $latestHistoryByOrderId[$orderId] ?? null;
            $status = (string) ($order['status'] ?? '');
            $paymentStatus = (string) ($order['payment_status'] ?? '');
            $order['latest_status_changed_at'] = $history['changed_at'] ?? null;
            $order['latest_status_changed_by'] = $history['changed_by_user_name'] ?? null;
            $order['latest_status_note'] = $history['notes'] ?? null;
            $order['next_statuses'] = $this->nextStatusesFor($status, $paymentStatus);
            $order['can_send_kitchen'] = $status === 'pending';
            $order['is_paid_waiting_production'] = $this->isPaidWaitingProduction($status, $paymentStatus);
            $order['items'] = is_array($itemsByOrderId[$orderId] ?? null) ? $itemsByOrderId[$orderId] : [];
        }
        unset($order);

        return $orders;
    }

    public function operationalPanelByTable(int $companyId): array
    {
        $orders = $this->list($companyId);
        $activeOrders = array_values(array_filter(
            $orders,
            static fn (array $order): bool => !in_array((string) ($order['status'] ?? ''), ['finished', 'canceled'], true)
        ));

        $summary = [
            'active_orders' => 0,
            'tables_in_service' => 0,
            'items_total' => 0,
            'amount_total' => 0.0,
            'pending' => 0,
            'received' => 0,
            'preparing' => 0,
            'ready' => 0,
            'delivered' => 0,
            'paid_waiting_production' => 0,
        ];

        if ($activeOrders === []) {
            return [
                'summary' => $summary,
                'tables' => [],
            ];
        }

        $groups = [];

        foreach ($activeOrders as $order) {
            $status = (string) ($order['status'] ?? '');
            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            }

            if (!empty($order['is_paid_waiting_production'])) {
                $summary['paid_waiting_production']++;
            }

            $itemsCount = (int) ($order['items_count'] ?? 0);
            $totalAmount = (float) ($order['total_amount'] ?? 0);

            $summary['active_orders']++;
            $summary['items_total'] += $itemsCount;
            $summary['amount_total'] = round($summary['amount_total'] + $totalAmount, 2);

            $tableNumber = $order['table_number'] !== null ? (int) $order['table_number'] : null;
            $groupKey = $tableNumber !== null ? 'table_' . $tableNumber : 'no_table';

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'key' => $groupKey,
                    'label' => $tableNumber !== null ? 'Mesa ' . $tableNumber : 'Pedidos sem mesa',
                    'table_number' => $tableNumber,
                    'orders' => [],
                    'orders_count' => 0,
                    'items_total' => 0,
                    'amount_total' => 0.0,
                ];
            }

            $groups[$groupKey]['orders'][] = $order;
            $groups[$groupKey]['orders_count']++;
            $groups[$groupKey]['items_total'] += $itemsCount;
            $groups[$groupKey]['amount_total'] = round(((float) $groups[$groupKey]['amount_total']) + $totalAmount, 2);
        }

        foreach ($groups as &$group) {
            $tableOrders = is_array($group['orders'] ?? null) ? $group['orders'] : [];
            $group['customer_cards'] = $this->groupOrdersByCustomer($tableOrders);
            $group['customer_cards_count'] = count($group['customer_cards']);
        }
        unset($group);

        $tables = array_values($groups);
        usort($tables, static function (array $left, array $right): int {
            $leftNumber = $left['table_number'];
            $rightNumber = $right['table_number'];

            if ($leftNumber === null && $rightNumber === null) {
                return 0;
            }
            if ($leftNumber === null) {
                return 1;
            }
            if ($rightNumber === null) {
                return -1;
            }

            return (int) $leftNumber <=> (int) $rightNumber;
        });

        $summary['tables_in_service'] = count($tables);

        return [
            'summary' => $summary,
            'tables' => $tables,
        ];
    }

    public function createFromCommand(int $companyId, int $userId, array $input): int
    {
        $input['channel'] = 'table';
        return $this->create($companyId, $userId, $input);
    }

    public function create(int $companyId, int $userId, array $input): int
    {
        $channel = strtolower(trim((string) ($input['channel'] ?? 'table')));
        $validChannels = ['table', 'delivery', 'pickup', 'counter'];
        if (!in_array($channel, $validChannels, true)) {
            throw new ValidationException('Canal de pedido invalido.');
        }

        $discountAmount = $this->parseMoney($input['discount_amount'] ?? 0);
        if ($discountAmount < 0) {
            throw new ValidationException('O desconto nao pode ser negativo.');
        }

        [$items, $subtotal] = $this->normalizeItems($companyId, $input);
        $orderNotes = $this->normalizeNullableText($input['notes'] ?? null);
        $deliveryFee = 0.0;
        $autoSendKitchen = !empty($input['auto_send_kitchen']) && $channel === 'table';
        $placedBy = $channel === 'table'
            ? ($userId > 0 ? 'waiter' : 'customer')
            : 'cashier';
        $commandId = null;
        $tableId = null;
        $customerId = null;
        $customerName = $this->normalizeNullableText($input['customer_name'] ?? null);
        $deliveryPayload = null;
        $initialStatus = $autoSendKitchen ? 'received' : 'pending';

        if ($channel === 'table') {
            $commandIdInput = (int) ($input['command_id'] ?? 0);
            if ($commandIdInput <= 0) {
                throw new ValidationException('Selecione uma comanda aberta valida para o canal mesa.');
            }

            $command = $this->commands->findOpenById($companyId, $commandIdInput);
            if ($command === null) {
                throw new ValidationException('A comanda selecionada nao esta aberta ou nao pertence a empresa.');
            }

            $commandId = (int) $command['id'];
            $tableId = $command['table_id'] !== null ? (int) $command['table_id'] : null;
            $customerId = $command['customer_id'] !== null ? (int) $command['customer_id'] : null;
            $customerName = $command['customer_name'] !== null
                ? $this->normalizeNullableText((string) $command['customer_name'])
                : $customerName;
            $deliveryFee = $this->parseMoney($input['delivery_fee'] ?? 0);
            if ($deliveryFee < 0) {
                throw new ValidationException('A taxa de entrega nao pode ser negativa.');
            }
        } elseif ($channel === 'delivery') {
            $customerName = $this->normalizeRequiredText(
                $input['customer_name'] ?? null,
                'Informe o nome do cliente para pedidos de entrega.'
            );
            $customerPhone = $this->normalizeNullableText($input['customer_phone'] ?? null);
            $zoneId = (int) ($input['delivery_zone_id'] ?? 0);
            if ($zoneId <= 0) {
                throw new ValidationException('Selecione uma zona de entrega valida.');
            }

            $zone = $this->deliveryZones->findByIdForCompany($companyId, $zoneId);
            if ($zone === null || (string) ($zone['status'] ?? '') !== 'ativo') {
                throw new ValidationException('Zona de entrega invalida ou inativa para esta empresa.');
            }

            $deliveryFee = round((float) ($zone['fee_amount'] ?? 0), 2);
            if ($deliveryFee < 0) {
                throw new ValidationException('A taxa da zona de entrega nao pode ser negativa.');
            }

            $baseForMinimum = round($subtotal - $discountAmount, 2);
            $minimumOrderAmount = $zone['minimum_order_amount'] !== null ? (float) $zone['minimum_order_amount'] : null;
            if ($minimumOrderAmount !== null && $baseForMinimum < $minimumOrderAmount) {
                throw new ValidationException(
                    'Pedido abaixo do minimo da zona selecionada: R$ ' . number_format($minimumOrderAmount, 2, ',', '.')
                );
            }

            $address = $this->normalizeDeliveryAddressInput($input);
            $deliveryPayload = [
                'zone' => $zone,
                'customer_phone' => $customerPhone,
                'address' => $address,
                'delivery_notes' => $this->normalizeNullableText($input['delivery_notes'] ?? null),
            ];
        } else {
            $deliveryFee = 0.0;
            if ($channel === 'pickup') {
                $customerName = $this->normalizeRequiredText(
                    $input['customer_name'] ?? null,
                    'Informe o nome do cliente para retirada.'
                );
            }
        }

        $totalAmount = round(($subtotal - $discountAmount) + $deliveryFee, 2);
        if ($totalAmount < 0) {
            throw new ValidationException('O total do pedido nao pode ser negativo.');
        }

        $db = Database::connection();
        $db->beginTransaction();

        try {
            if ($channel === 'delivery' && $deliveryPayload !== null) {
                $customerId = $this->resolveDeliveryCustomerId(
                    $companyId,
                    (string) $customerName,
                    $deliveryPayload['customer_phone']
                );
            }

            $orderId = $this->createOrderWithUniqueNumber([
                'company_id' => $companyId,
                'command_id' => $commandId,
                'table_id' => $tableId,
                'customer_id' => $customerId,
                'channel' => $channel,
                'status' => $initialStatus,
                'payment_status' => 'pending',
                'customer_name' => $customerName,
                'subtotal_amount' => $subtotal,
                'discount_amount' => $discountAmount,
                'delivery_fee' => $deliveryFee,
                'total_amount' => $totalAmount,
                'notes' => $orderNotes,
                'placed_by' => $placedBy,
                'placed_by_user_id' => $userId > 0 ? $userId : null,
            ]);

            $this->orderItems->createBatch($companyId, $orderId, $items);
            $this->statusHistory->create([
                'company_id' => $companyId,
                'order_id' => $orderId,
                'old_status' => null,
                'new_status' => $initialStatus,
                'changed_by_user_id' => $userId > 0 ? $userId : null,
                'notes' => $autoSendKitchen
                    ? 'Pedido criado e enviado automaticamente para a fila de producao (' . $channel . ').'
                    : 'Pedido criado (' . $channel . ').',
            ]);

            if ($channel === 'delivery' && $deliveryPayload !== null && $customerId !== null) {
                $zone = is_array($deliveryPayload['zone'] ?? null) ? $deliveryPayload['zone'] : [];
                $address = is_array($deliveryPayload['address'] ?? null) ? $deliveryPayload['address'] : [];
                $addressId = $this->deliveryAddresses->create([
                    'company_id' => $companyId,
                    'customer_id' => $customerId,
                    'label' => $address['label'] ?? null,
                    'street' => $address['street'] ?? '',
                    'number' => $address['number'] ?? '',
                    'complement' => $address['complement'] ?? null,
                    'neighborhood' => $address['neighborhood'] ?? '',
                    'city' => $address['city'] ?? '',
                    'state' => $address['state'] ?? '',
                    'zip_code' => $address['zip_code'] ?? null,
                    'reference' => $address['reference'] ?? null,
                    'delivery_zone_id' => (int) ($zone['id'] ?? 0),
                ]);

                $this->deliveries->create([
                    'company_id' => $companyId,
                    'order_id' => $orderId,
                    'delivery_address_id' => $addressId,
                    'delivery_user_id' => null,
                    'status' => 'pending',
                    'delivery_fee' => $deliveryFee,
                    'assigned_at' => null,
                    'left_at' => null,
                    'delivered_at' => null,
                    'notes' => $deliveryPayload['delivery_notes'] ?? null,
                ]);
            }

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

    public function ticketPrintContext(int $companyId, int $orderId): array
    {
        return $this->ticketPrintContextByOrderIds($companyId, [$orderId]);
    }

    public function ticketPrintContextByOrderIds(int $companyId, array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $orderIds)));
        $orderIds = array_values(array_filter($orderIds, static fn (int $id): bool => $id > 0));

        if ($orderIds === []) {
            throw new ValidationException('Pedido invalido para impressao.');
        }

        $orders = [];
        foreach ($orderIds as $orderId) {
            $order = $this->orders->findWithContextById($companyId, $orderId);
            if ($order === null) {
                throw new ValidationException('Pedido nao pertence a empresa autenticada.');
            }
            $orders[] = $order;
        }

        $deliveriesByOrderId = $this->deliveries->findByOrderIdsForCompany($companyId, $orderIds);
        foreach ($orders as &$orderRow) {
            $rowOrderId = (int) ($orderRow['id'] ?? 0);
            $orderRow['delivery'] = is_array($deliveriesByOrderId[$rowOrderId] ?? null)
                ? $deliveriesByOrderId[$rowOrderId]
                : null;
        }
        unset($orderRow);

        $itemsRows = $this->orderItems->activeItemsByOrderIds($companyId, $orderIds);
        $orderItemIds = array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $itemsRows);
        $additionalRows = $this->orderItems->additionalsByOrderItemIds($companyId, $orderItemIds);
        $additionalsByOrderItemId = $this->indexAdditionalsByOrderItemId($additionalRows);
        $itemsByOrderId = $this->indexItemsByOrderId($itemsRows, $additionalsByOrderItemId);

        usort($orders, static function (array $left, array $right): int {
            $leftDate = (string) ($left['created_at'] ?? '');
            $rightDate = (string) ($right['created_at'] ?? '');
            if ($leftDate === $rightDate) {
                return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
            }
            return $leftDate <=> $rightDate;
        });

        $ordersWithItems = [];
        foreach ($orders as $order) {
            $currentOrderId = (int) ($order['id'] ?? 0);
            $ordersWithItems[] = [
                'order' => $order,
                'items' => is_array($itemsByOrderId[$currentOrderId] ?? null) ? $itemsByOrderId[$currentOrderId] : [],
            ];
        }

        $firstOrder = $orders[0];
        $isGrouped = count($orders) > 1;
        $groupTotals = [
            'subtotal_amount' => 0.0,
            'discount_amount' => 0.0,
            'delivery_fee' => 0.0,
            'total_amount' => 0.0,
        ];
        foreach ($orders as $order) {
            $groupTotals['subtotal_amount'] = round($groupTotals['subtotal_amount'] + (float) ($order['subtotal_amount'] ?? 0), 2);
            $groupTotals['discount_amount'] = round($groupTotals['discount_amount'] + (float) ($order['discount_amount'] ?? 0), 2);
            $groupTotals['delivery_fee'] = round($groupTotals['delivery_fee'] + (float) ($order['delivery_fee'] ?? 0), 2);
            $groupTotals['total_amount'] = round($groupTotals['total_amount'] + (float) ($order['total_amount'] ?? 0), 2);
        }

        $commandIds = [];
        $customerNames = [];
        $tableNumbers = [];
        $notesList = [];
        $statuses = [];
        $paymentStatuses = [];
        $channels = [];
        $orderNumbers = [];
        foreach ($orders as $order) {
            $commandRaw = $order['command_id'] ?? null;
            if ($commandRaw !== null) {
                $commandIds[(int) $commandRaw] = true;
            }
            $customerName = trim((string) ($order['customer_name'] ?? ''));
            if ($customerName !== '') {
                $customerNames[$customerName] = true;
            }
            $tableRaw = $order['table_number'] ?? null;
            if ($tableRaw !== null) {
                $tableNumbers[(int) $tableRaw] = true;
            }
            $notes = trim((string) ($order['notes'] ?? ''));
            if ($notes !== '') {
                $notesList[] = $notes;
            }
            $statuses[] = (string) ($order['status'] ?? '');
            $paymentStatuses[] = (string) ($order['payment_status'] ?? '');
            $channels[] = (string) ($order['channel'] ?? '');
            $orderNumbers[] = (string) ($order['order_number'] ?? '-');
        }

        $groupCustomerName = count($customerNames) === 1 ? (string) array_key_first($customerNames) : '';
        $groupCommandId = count($commandIds) === 1 ? (int) array_key_first($commandIds) : null;
        $groupTableNumber = count($tableNumbers) === 1 ? (int) array_key_first($tableNumbers) : null;
        $groupStatus = $this->resolveGroupOperationalStatus($statuses);
        $groupPaymentStatus = $this->resolveGroupPaymentStatus($paymentStatuses);
        $groupChannel = $this->resolveGroupChannel($channels);
        $groupNotes = $notesList !== [] ? implode(' | ', $notesList) : null;
        $firstOrderId = (int) ($firstOrder['id'] ?? 0);
        $groupDelivery = is_array($deliveriesByOrderId[$firstOrderId] ?? null) ? $deliveriesByOrderId[$firstOrderId] : null;

        return [
            'order' => $firstOrder,
            'items' => is_array($itemsByOrderId[(int) ($firstOrder['id'] ?? 0)] ?? null) ? $itemsByOrderId[(int) ($firstOrder['id'] ?? 0)] : [],
            'delivery' => $groupDelivery,
            'orders' => $ordersWithItems,
            'is_grouped' => $isGrouped,
            'group' => [
                'order_ids' => $orderIds,
                'order_numbers' => $orderNumbers,
                'orders_count' => count($orders),
                'customer_name' => $groupCustomerName,
                'command_id' => $groupCommandId,
                'table_number' => $groupTableNumber,
                'status' => $groupStatus,
                'payment_status' => $groupPaymentStatus,
                'channel' => $groupChannel,
                'notes' => $groupNotes,
                'subtotal_amount' => $groupTotals['subtotal_amount'],
                'discount_amount' => $groupTotals['discount_amount'],
                'delivery_fee' => $groupTotals['delivery_fee'],
                'total_amount' => $groupTotals['total_amount'],
            ],
            'generated_at' => date('Y-m-d H:i:s'),
        ];
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

            if ($paymentStatus === 'paid' && in_array($newStatus, ['ready', 'delivered'], true)) {
                $this->orders->updateStatus($companyId, $orderId, 'finished');
                $this->statusHistory->create([
                    'company_id' => $companyId,
                    'order_id' => $orderId,
                    'old_status' => $newStatus,
                    'new_status' => 'finished',
                    'changed_by_user_id' => $userId,
                    'notes' => 'Finalizado automaticamente: pedido pago e em etapa final de producao.',
                ]);
            }

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
        $additionalItemIdsList = $input['additional_item_ids'] ?? [];

        if (!is_array($productIds) || !is_array($quantities) || !is_array($notesList) || !is_array($additionalItemIdsList)) {
            throw new ValidationException('Formato de itens invalido.');
        }

        $productIdsForCatalog = [];
        $rowsCount = count($productIds);
        for ($index = 0; $index < $rowsCount; $index++) {
            $productId = (int) ($productIds[$index] ?? 0);
            if ($productId > 0) {
                $productIdsForCatalog[] = $productId;
            }
        }
        $productIdsForCatalog = array_values(array_unique($productIdsForCatalog));

        $additionalCatalogRows = $this->products->activeAdditionalCatalogByProductIds($companyId, $productIdsForCatalog);
        $additionalCatalogByProductId = $this->mapAdditionalCatalogRows($additionalCatalogRows);

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
            $baseSubtotal = round($unitPrice * $quantity, 2);
            $additionalsSubtotal = 0.0;
            $selectedAdditionals = [];

            $selectedAdditionalItemIds = $this->parseAdditionalItemIds($additionalItemIdsList[$index] ?? '');
            $additionalConfig = $additionalCatalogByProductId[(int) $product['id']] ?? null;

            if ($selectedAdditionalItemIds !== []) {
                if ($additionalConfig === null || ($additionalConfig['items'] ?? []) === []) {
                    throw new ValidationException('O produto da linha ' . $rowNumber . ' nao possui adicionais validos para selecao.');
                }
            }

            if ($additionalConfig !== null) {
                $maxSelection = $additionalConfig['max_selection'] ?? null;
                $minSelection = $additionalConfig['min_selection'] ?? null;
                $isRequired = (bool) ($additionalConfig['is_required'] ?? false);
                $selectedCount = count($selectedAdditionalItemIds);

                if ($maxSelection !== null && $selectedCount > $maxSelection) {
                    throw new ValidationException('A linha ' . $rowNumber . ' excedeu o limite maximo de adicionais permitidos para o produto.');
                }

                $requiredMin = $minSelection ?? ($isRequired ? 1 : 0);
                if ($requiredMin > 0 && $selectedCount < $requiredMin) {
                    throw new ValidationException('A linha ' . $rowNumber . ' exige ao menos ' . $requiredMin . ' adicional(is).');
                }

                foreach ($selectedAdditionalItemIds as $additionalItemId) {
                    $additional = $additionalConfig['items'][$additionalItemId] ?? null;
                    if ($additional === null) {
                        throw new ValidationException('Adicional invalido selecionado na linha ' . $rowNumber . '.');
                    }

                    $additionalUnitPrice = (float) ($additional['price'] ?? 0);
                    $additionalLineSubtotal = round($additionalUnitPrice * $quantity, 2);
                    $additionalsSubtotal = round($additionalsSubtotal + $additionalLineSubtotal, 2);

                    $selectedAdditionals[] = [
                        'additional_item_id' => (int) ($additional['id'] ?? $additionalItemId),
                        'additional_name_snapshot' => (string) ($additional['name'] ?? ''),
                        'unit_price' => $additionalUnitPrice,
                        'quantity' => $quantity,
                        'line_subtotal' => $additionalLineSubtotal,
                    ];
                }
            }

            $lineSubtotal = round($baseSubtotal + $additionalsSubtotal, 2);
            $itemNotes = ((int) ($product['allows_notes'] ?? 1)) === 1 ? $itemNote : null;

            $subtotal = round($subtotal + $lineSubtotal, 2);
            $items[] = [
                'product_id' => (int) $product['id'],
                'product_name_snapshot' => (string) $product['name'],
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'notes' => $itemNotes,
                'line_subtotal' => $lineSubtotal,
                'additionals' => $selectedAdditionals,
            ];
        }

        if ($items === []) {
            throw new ValidationException('Adicione ao menos um item valido ao pedido.');
        }

        return [$items, $subtotal];
    }

    private function mapAdditionalCatalogRows(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            if (!isset($map[$productId])) {
                $map[$productId] = [
                    'is_required' => ((int) ($row['is_required'] ?? 0)) === 1,
                    'min_selection' => $row['min_selection'] !== null ? (int) $row['min_selection'] : null,
                    'max_selection' => $row['max_selection'] !== null ? (int) $row['max_selection'] : null,
                    'items' => [],
                ];
            }

            $additionalItemId = (int) ($row['additional_item_id'] ?? 0);
            if ($additionalItemId <= 0) {
                continue;
            }

            $map[$productId]['items'][$additionalItemId] = [
                'id' => $additionalItemId,
                'name' => (string) ($row['additional_name'] ?? ''),
                'price' => (float) ($row['additional_price'] ?? 0),
            ];
        }

        return $map;
    }

    private function parseAdditionalItemIds(mixed $raw): array
    {
        if (is_array($raw)) {
            $values = $raw;
        } else {
            $rawText = trim((string) $raw);
            if ($rawText === '') {
                return [];
            }
            $values = explode(',', $rawText);
        }

        $ids = [];
        foreach ($values as $value) {
            $id = (int) trim((string) $value);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        return array_keys($ids);
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

    private function normalizeRequiredText(mixed $value, string $errorMessage): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            throw new ValidationException($errorMessage);
        }
        return $text;
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text !== '' ? $text : null;
    }

    private function normalizeDeliveryAddressInput(array $input): array
    {
        $stateRaw = strtoupper(trim((string) ($input['delivery_state'] ?? '')));
        $state = preg_replace('/[^A-Z]/', '', $stateRaw) ?? '';
        if (strlen($state) > 2) {
            $state = substr($state, 0, 2);
        }

        $address = [
            'label' => $this->normalizeNullableText($input['delivery_label'] ?? null),
            'street' => $this->normalizeRequiredText($input['delivery_street'] ?? null, 'Informe o logradouro da entrega.'),
            'number' => $this->normalizeRequiredText($input['delivery_number'] ?? null, 'Informe o numero do endereco de entrega.'),
            'complement' => $this->normalizeNullableText($input['delivery_complement'] ?? null),
            'neighborhood' => $this->normalizeRequiredText($input['delivery_neighborhood'] ?? null, 'Informe o bairro da entrega.'),
            'city' => $this->normalizeRequiredText($input['delivery_city'] ?? null, 'Informe a cidade da entrega.'),
            'state' => $state,
            'zip_code' => $this->normalizeNullableText($input['delivery_zip_code'] ?? null),
            'reference' => $this->normalizeNullableText($input['delivery_reference'] ?? null),
        ];

        if (strlen($address['state']) !== 2) {
            throw new ValidationException('Informe uma UF valida (2 letras) para entrega.');
        }

        return $address;
    }

    private function resolveDeliveryCustomerId(int $companyId, string $customerName, ?string $customerPhone): int
    {
        if ($customerPhone !== null) {
            $existing = $this->customers->findByPhoneForCompany($companyId, $customerPhone);
            if ($existing !== null) {
                return (int) ($existing['id'] ?? 0);
            }
        }

        return $this->customers->create([
            'company_id' => $companyId,
            'name' => $customerName,
            'phone' => $customerPhone,
            'email' => null,
            'notes' => null,
        ]);
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

            $indexed[$orderId][] = [
                'id' => $orderItemId,
                'name' => (string) ($row['product_name_snapshot'] ?? ''),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
                'line_subtotal' => (float) ($row['line_subtotal'] ?? 0),
                'notes' => $this->normalizeNullableText($row['notes'] ?? null),
                'additionals' => is_array($additionalsByOrderItemId[$orderItemId] ?? null) ? $additionalsByOrderItemId[$orderItemId] : [],
            ];
        }

        return $indexed;
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

    private function isPaidWaitingProduction(string $status, string $paymentStatus): bool
    {
        return $paymentStatus === 'paid' && in_array($status, ['pending', 'received', 'preparing'], true);
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

    private function groupOrdersByCustomer(array $orders): array
    {
        $cards = [];

        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }

            $orderId = (int) ($order['id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }

            $commandId = $order['command_id'] !== null ? (int) $order['command_id'] : null;
            $customerName = trim((string) ($order['customer_name'] ?? ''));
            $groupKey = $this->customerCardGroupKey($orderId, $commandId, $customerName);

            if (!isset($cards[$groupKey])) {
                $cards[$groupKey] = [
                    'key' => $groupKey,
                    'anchor_order_id' => $orderId,
                    'command_id' => $commandId,
                    'customer_name' => $customerName,
                    'orders' => [],
                    'order_ids' => [],
                    'orders_count' => 0,
                    'items_total' => 0,
                    'amount_total' => 0.0,
                    'latest_created_at' => null,
                    'latest_status_changed_at' => null,
                    'is_paid_waiting_production' => false,
                    'can_send_kitchen' => false,
                ];
            }

            $cards[$groupKey]['orders'][] = $order;
            $cards[$groupKey]['order_ids'][] = $orderId;
            $cards[$groupKey]['orders_count']++;
            $cards[$groupKey]['items_total'] += (int) ($order['items_count'] ?? 0);
            $cards[$groupKey]['amount_total'] = round(((float) $cards[$groupKey]['amount_total']) + (float) ($order['total_amount'] ?? 0), 2);
            $cards[$groupKey]['is_paid_waiting_production'] = $cards[$groupKey]['is_paid_waiting_production'] || !empty($order['is_paid_waiting_production']);
            $cards[$groupKey]['can_send_kitchen'] = $cards[$groupKey]['can_send_kitchen'] || !empty($order['can_send_kitchen']);

            $createdAt = (string) ($order['created_at'] ?? '');
            $latestChangedAt = (string) ($order['latest_status_changed_at'] ?? '');
            if ($createdAt !== '' && (($cards[$groupKey]['latest_created_at'] ?? '') === '' || $createdAt > (string) $cards[$groupKey]['latest_created_at'])) {
                $cards[$groupKey]['latest_created_at'] = $createdAt;
                $cards[$groupKey]['anchor_order_id'] = $orderId;
            }
            if ($latestChangedAt !== '' && (($cards[$groupKey]['latest_status_changed_at'] ?? '') === '' || $latestChangedAt > (string) $cards[$groupKey]['latest_status_changed_at'])) {
                $cards[$groupKey]['latest_status_changed_at'] = $latestChangedAt;
            }
        }

        foreach ($cards as &$card) {
            $cardOrders = is_array($card['orders'] ?? null) ? $card['orders'] : [];
            usort($cardOrders, static function (array $left, array $right): int {
                $leftCreated = (string) ($left['created_at'] ?? '');
                $rightCreated = (string) ($right['created_at'] ?? '');
                if ($leftCreated === $rightCreated) {
                    return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
                }
                return $rightCreated <=> $leftCreated;
            });
            $card['orders'] = $cardOrders;

            $statuses = [];
            $paymentStatuses = [];
            foreach ($cardOrders as $order) {
                $statuses[] = (string) ($order['status'] ?? '');
                $paymentStatuses[] = (string) ($order['payment_status'] ?? '');
            }

            $resolvedStatus = $this->resolveGroupOperationalStatus($statuses);
            $card['status'] = $resolvedStatus;
            $card['payment_status'] = $this->resolveGroupPaymentStatus($paymentStatuses);
            $card['status_border_class'] = in_array($resolvedStatus, ['pending', 'received', 'preparing', 'ready', 'delivered'], true)
                ? 'status-op-' . $resolvedStatus
                : 'status-op-pending';
            $card['customer_label'] = $card['customer_name'] !== '' ? (string) $card['customer_name'] : 'Nao informado';
            $card['command_label'] = $card['command_id'] !== null
                ? 'Comanda ativa #' . (int) $card['command_id']
                : 'Pedido sem comanda';
            $card['order_ids_csv'] = implode(',', array_map(static fn (mixed $id): string => (string) ((int) $id), $card['order_ids']));
        }
        unset($card);

        $cards = array_values($cards);
        usort($cards, static function (array $left, array $right): int {
            $leftCreated = (string) ($left['latest_created_at'] ?? '');
            $rightCreated = (string) ($right['latest_created_at'] ?? '');
            if ($leftCreated === $rightCreated) {
                return ((int) ($right['anchor_order_id'] ?? 0)) <=> ((int) ($left['anchor_order_id'] ?? 0));
            }
            return $rightCreated <=> $leftCreated;
        });

        return $cards;
    }

    private function customerCardGroupKey(int $orderId, ?int $commandId, string $customerName): string
    {
        if ($commandId !== null && $commandId > 0) {
            return 'command_' . $commandId;
        }

        if ($customerName !== '') {
            return 'customer_' . $this->normalizeGroupToken($customerName);
        }

        return 'order_' . $orderId;
    }

    private function normalizeGroupToken(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/\s+/', '_', $normalized) ?? '';
        $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized) ?? '';

        if ($normalized !== '') {
            return $normalized;
        }

        return md5($value);
    }

    private function resolveGroupOperationalStatus(array $statuses): string
    {
        $priority = [
            'pending' => 1,
            'received' => 2,
            'preparing' => 3,
            'ready' => 4,
            'delivered' => 5,
        ];

        $resolvedStatus = 'pending';
        $resolvedPriority = PHP_INT_MAX;

        foreach ($statuses as $statusRaw) {
            $status = (string) $statusRaw;
            $statusPriority = $priority[$status] ?? PHP_INT_MAX;
            if ($statusPriority < $resolvedPriority) {
                $resolvedPriority = $statusPriority;
                $resolvedStatus = $status;
            }
        }

        return in_array($resolvedStatus, ['pending', 'received', 'preparing', 'ready', 'delivered'], true)
            ? $resolvedStatus
            : 'pending';
    }

    private function resolveGroupPaymentStatus(array $paymentStatuses): string
    {
        $hasPending = false;
        $hasPartial = false;
        $hasPaid = false;

        foreach ($paymentStatuses as $paymentStatusRaw) {
            $paymentStatus = (string) $paymentStatusRaw;
            if ($paymentStatus === 'pending') {
                $hasPending = true;
            } elseif ($paymentStatus === 'partial') {
                $hasPartial = true;
            } elseif ($paymentStatus === 'paid') {
                $hasPaid = true;
            }
        }

        if ($hasPending) {
            return 'pending';
        }
        if ($hasPartial) {
            return 'partial';
        }
        if ($hasPaid) {
            return 'paid';
        }

        return 'pending';
    }

    private function resolveGroupChannel(array $channels): string
    {
        $normalized = [];
        foreach ($channels as $channelRaw) {
            $channel = strtolower(trim((string) $channelRaw));
            if (in_array($channel, ['table', 'delivery', 'pickup', 'counter'], true)) {
                $normalized[$channel] = true;
            }
        }

        if (count($normalized) === 1) {
            return (string) array_key_first($normalized);
        }

        return 'table';
    }
}
