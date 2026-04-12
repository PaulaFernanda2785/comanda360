<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Exceptions\ValidationException;
use App\Repositories\CashMovementRepository;
use App\Repositories\CashRegisterRepository;
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
        private readonly OrderStatusHistoryRepository $statusHistory = new OrderStatusHistoryRepository(),
        private readonly CashRegisterRepository $cashRegisters = new CashRegisterRepository(),
        private readonly CashMovementRepository $cashMovements = new CashMovementRepository(),
        private readonly CommandLifecycleService $commandLifecycle = new CommandLifecycleService()
    ) {}

    public function list(int $companyId): array
    {
        return $this->payments->allByCompany($companyId);
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
            $totalAmount = round((float) ($order['total_amount'] ?? 0), 2);
            $paidAmount = round((float) ($order['paid_amount'] ?? 0), 2);
            $remainingAmount = round($totalAmount - $paidAmount, 2);

            if ($remainingAmount <= 0) {
                continue;
            }

            $order['remaining_amount'] = $remainingAmount;
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
        $transactionReference = $this->normalizeNullableText($input['transaction_reference'] ?? null);

        if ($orderId <= 0) {
            throw new ValidationException('Selecione um pedido valido para pagamento.');
        }

        if ($paymentMethodId <= 0) {
            throw new ValidationException('Selecione um metodo de pagamento valido.');
        }

        if ($amount <= 0) {
            throw new ValidationException('O valor do pagamento deve ser maior que zero.');
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

            $paymentMethod = $this->paymentMethods->findActiveById($companyId, $paymentMethodId);
            if ($paymentMethod === null) {
                throw new ValidationException('Metodo de pagamento invalido para a empresa autenticada.');
            }

            $totalAmount = round((float) ($order['total_amount'] ?? 0), 2);
            $paidBefore = $this->payments->sumPaidAmountByOrder($companyId, $orderId);
            $remainingAmount = round($totalAmount - $paidBefore, 2);

            if ($remainingAmount <= 0) {
                throw new ValidationException('Este pedido ja esta totalmente pago.');
            }

            if ($amount > $remainingAmount) {
                throw new ValidationException('Valor do pagamento maior que o saldo restante do pedido.');
            }

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
                'description' => 'Pagamento do pedido ' . (string) ($order['order_number'] ?? ('#' . $orderId)),
                'amount' => $amount,
                'created_by_user_id' => $userId,
            ]);

            $paidAfter = round($paidBefore + $amount, 2);
            $nextPaymentStatus = $this->resolveOrderPaymentStatus($paidAfter, $totalAmount);
            $this->orders->updatePaymentStatus($companyId, $orderId, $nextPaymentStatus);

            if ($nextPaymentStatus === 'paid') {
                $currentStatus = (string) ($order['status'] ?? '');
                if ($currentStatus !== 'finished' && $currentStatus !== 'canceled') {
                    $this->orders->updateStatus($companyId, $orderId, 'finished');
                    $this->statusHistory->create([
                        'company_id' => $companyId,
                        'order_id' => $orderId,
                        'old_status' => $currentStatus,
                        'new_status' => 'finished',
                        'changed_by_user_id' => $userId,
                        'notes' => 'Finalizado automaticamente apos pagamento total.',
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
}
