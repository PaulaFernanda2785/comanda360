<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Database;
use App\Exceptions\ValidationException;
use App\Repositories\CommandRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use PDOException;
use RuntimeException;
use Throwable;

final class OrderService
{
    public function __construct(
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly OrderItemRepository $orderItems = new OrderItemRepository(),
        private readonly CommandRepository $commands = new CommandRepository(),
        private readonly ProductRepository $products = new ProductRepository()
    ) {}

    public function list(int $companyId): array
    {
        return $this->orders->allByCompany($companyId);
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

            $db->commit();
            return $orderId;
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
}
