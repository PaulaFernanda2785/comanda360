<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Exceptions\ValidationException;
use App\Repositories\CashMovementRepository;
use App\Repositories\CashRegisterRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\OrderRepository;
use App\Repositories\OrderStatusHistoryRepository;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\PaymentRepository;
use Throwable;

final class PaymentService
{
    public function __construct(
        private readonly PaymentRepository $payments = new PaymentRepository(),
        private readonly PaymentMethodRepository $paymentMethods = new PaymentMethodRepository(),
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly OrderItemRepository $orderItems = new OrderItemRepository(),
        private readonly OrderStatusHistoryRepository $statusHistory = new OrderStatusHistoryRepository(),
        private readonly CashRegisterRepository $cashRegisters = new CashRegisterRepository(),
        private readonly CashMovementRepository $cashMovements = new CashMovementRepository(),
        private readonly CommandLifecycleService $commandLifecycle = new CommandLifecycleService()
    ) {}

    public function list(int $companyId): array
    {
        $payments = $this->payments->allByCompany($companyId);
        if ($payments === []) {
            return [];
        }

        $orderIds = [];
        foreach ($payments as $payment) {
            $orderId = (int) ($payment['order_id'] ?? 0);
            if ($orderId > 0) {
                $orderIds[$orderId] = true;
            }
        }

        $itemsByOrderId = [];
        if ($orderIds !== []) {
            $orderItemsRows = $this->orderItems->activeItemsByOrderIds($companyId, array_keys($orderIds));
            $orderItemIds = array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $orderItemsRows);
            $additionalRows = $this->orderItems->additionalsByOrderItemIds($companyId, $orderItemIds);
            $additionalsByOrderItemId = $this->indexAdditionalsByOrderItemId($additionalRows);
            $itemsByOrderId = $this->indexItemsByOrderId($orderItemsRows, $additionalsByOrderItemId);
        }

        foreach ($payments as &$payment) {
            $orderId = (int) ($payment['order_id'] ?? 0);
            $payment['order_items'] = $orderId > 0
                ? (is_array($itemsByOrderId[$orderId] ?? null) ? $itemsByOrderId[$orderId] : [])
                : [];
            $payment['payment_history_note'] = $this->normalizeNullableText($payment['cash_movement_description'] ?? null);
        }
        unset($payment);

        return $payments;
    }

    public function paymentMethods(int $companyId): array
    {
        return $this->paymentMethods->activeByCompany($companyId);
    }

    public function payableOrders(int $companyId): array
    {
        $orders = $this->orders->allPendingPaymentByCompany($companyId);
        $result = [];

        foreach ($orders as $order) {
            $status = strtolower(trim((string) ($order['status'] ?? '')));
            if ($status !== 'delivered') {
                continue;
            }

            $totalAmount = round((float) ($order['total_amount'] ?? 0), 2);
            $paidAmount = round((float) ($order['paid_amount'] ?? 0), 2);
            $remainingAmount = round($totalAmount - $paidAmount, 2);

            if ($remainingAmount <= 0) {
                continue;
            }

            $order['remaining_amount'] = $remainingAmount;
            $order['status_label'] = 'Entregue';
            $result[] = $order;
        }

        return $result;
    }

    public function hasOpenCashRegister(int $companyId): bool
    {
        return $this->cashRegisters->findOpenByCompany($companyId) !== null;
    }

    public function create(int $companyId, int $userId, array $input): int
    {
        $orderId = (int) ($input['order_id'] ?? 0);
        $paymentMethodId = (int) ($input['payment_method_id'] ?? 0);
        $amount = $this->parseMoney($input['amount'] ?? 0);
        $rawTransactionReference = $this->normalizeNullableText($input['transaction_reference'] ?? null);
        $discountAmount = $this->parseMoney($input['discount_amount'] ?? 0);
        $discountReason = $this->normalizeNullableText($input['discount_reason'] ?? null);

        if ($orderId <= 0) {
            throw new ValidationException('Selecione um pedido valido para pagamento.');
        }

        if ($paymentMethodId <= 0) {
            throw new ValidationException('Selecione um metodo de pagamento valido.');
        }

        if ($amount <= 0) {
            throw new ValidationException('O valor do pagamento deve ser maior que zero.');
        }

        if ($discountAmount < 0) {
            throw new ValidationException('O desconto informado nao pode ser negativo.');
        }

        if ($discountAmount > 0 && $discountReason === null) {
            throw new ValidationException('Informe o motivo do desconto para manter o historico financeiro auditavel.');
        }

        if ($userId <= 0) {
            throw new ValidationException('Usuario autenticado invalido para registrar pagamento.');
        }

        $db = Database::connection();
        $db->beginTransaction();

        try {
            $openCashRegister = $this->cashRegisters->findOpenByCompanyForUpdate($companyId);
            if ($openCashRegister === null) {
                throw new ValidationException('Nao existe caixa aberto para registrar pagamentos.');
            }

            $order = $this->orders->findByIdForCompany($companyId, $orderId);
            if ($order === null) {
                throw new ValidationException('Pedido nao encontrado para a empresa autenticada.');
            }

            if ((string) ($order['status'] ?? '') === 'canceled') {
                throw new ValidationException('Nao e permitido registrar pagamento para pedido cancelado.');
            }

            if (strtolower(trim((string) ($order['status'] ?? ''))) !== 'delivered') {
                throw new ValidationException('Somente pedidos entregues podem seguir para o recebimento no caixa.');
            }

            $paymentMethod = $this->paymentMethods->findActiveById($companyId, $paymentMethodId);
            if ($paymentMethod === null) {
                throw new ValidationException('Metodo de pagamento invalido para a empresa autenticada.');
            }

            $totalAmount = round((float) ($order['total_amount'] ?? 0), 2);
            $currentDiscountAmount = round((float) ($order['discount_amount'] ?? 0), 2);
            $paidBefore = $this->payments->sumPaidAmountByOrder($companyId, $orderId);
            $remainingAmount = round($totalAmount - $paidBefore, 2);

            if ($remainingAmount <= 0) {
                throw new ValidationException('Este pedido ja esta totalmente pago.');
            }

            if ($discountAmount > $remainingAmount) {
                throw new ValidationException('Desconto maior que o saldo restante do pedido.');
            }

            if ($discountAmount > 0) {
                $nextDiscountAmount = round($currentDiscountAmount + $discountAmount, 2);
                $nextTotalAmount = round($totalAmount - $discountAmount, 2);
                if ($nextTotalAmount <= $paidBefore) {
                    throw new ValidationException('Desconto invalido para o saldo atual do pedido.');
                }

                $this->orders->updateFinancialTotals($companyId, $orderId, $nextDiscountAmount, $nextTotalAmount);
                $totalAmount = $nextTotalAmount;
                $remainingAmount = round($totalAmount - $paidBefore, 2);
            }

            if ($amount > $remainingAmount) {
                throw new ValidationException('Valor do pagamento maior que o saldo restante do pedido.');
            }

            $transactionReference = $this->composeTransactionReference(
                $rawTransactionReference,
                $discountAmount,
                $discountReason
            );
            $paymentId = $this->payments->create([
                'company_id' => $companyId,
                'order_id' => $orderId,
                'command_id' => $order['command_id'] !== null ? (int) $order['command_id'] : null,
                'payment_method_id' => $paymentMethodId,
                'amount' => $amount,
                'status' => 'paid',
                'transaction_reference' => $transactionReference,
                'paid_at' => date('Y-m-d H:i:s'),
                'received_by_user_id' => $userId,
            ]);

            $this->cashMovements->create([
                'company_id' => $companyId,
                'cash_register_id' => (int) $openCashRegister['id'],
                'payment_id' => $paymentId,
                'type' => 'income',
                'description' => $this->buildCashMovementDescription(
                    (string) ($order['order_number'] ?? ('#' . $orderId)),
                    $discountAmount,
                    $discountReason
                ),
                'amount' => $amount,
                'created_by_user_id' => $userId,
            ]);

            $paidAfter = round($paidBefore + $amount, 2);
            $nextPaymentStatus = $this->resolveOrderPaymentStatus($paidAfter, $totalAmount);
            $this->orders->updatePaymentStatus($companyId, $orderId, $nextPaymentStatus);

            if ($nextPaymentStatus === 'paid') {
                $currentStatus = (string) ($order['status'] ?? '');
                if (in_array($currentStatus, ['ready', 'delivered'], true)) {
                    $this->orders->updateStatus($companyId, $orderId, 'finished');
                    $this->statusHistory->create([
                        'company_id' => $companyId,
                        'order_id' => $orderId,
                        'old_status' => $currentStatus,
                        'new_status' => 'finished',
                        'changed_by_user_id' => $userId,
                        'notes' => 'Finalizado automaticamente apos pagamento total em etapa final de producao.',
                    ]);
                }
            }

            $commandId = $order['command_id'] !== null ? (int) $order['command_id'] : null;
            $this->commandLifecycle->tryCloseWhenOrdersSettled($companyId, $commandId);

            $db->commit();
            return $paymentId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function resolveOrderPaymentStatus(float $paidAmount, float $orderTotal): string
    {
        if ($paidAmount <= 0) {
            return 'pending';
        }

        if ($paidAmount < $orderTotal) {
            return 'partial';
        }

        return 'paid';
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

    private function composeTransactionReference(?string $reference, float $discountAmount, ?string $discountReason): ?string
    {
        $parts = [];
        if ($reference !== null) {
            $parts[] = 'Ref: ' . $reference;
        }

        if ($discountAmount > 0) {
            $parts[] = 'Desconto: R$ ' . number_format($discountAmount, 2, ',', '.');
            if ($discountReason !== null) {
                $parts[] = 'Motivo: ' . $discountReason;
            }
        }

        if ($parts === []) {
            return null;
        }

        return substr(implode(' | ', $parts), 0, 120);
    }

    private function buildCashMovementDescription(string $orderNumber, float $discountAmount, ?string $discountReason): string
    {
        $description = 'Pagamento do pedido ' . $orderNumber;
        if ($discountAmount > 0) {
            $description .= ' | Desconto aplicado: R$ ' . number_format($discountAmount, 2, ',', '.');
            if ($discountReason !== null) {
                $description .= ' | Motivo: ' . $discountReason;
            }
        }

        return substr($description, 0, 255);
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
}
