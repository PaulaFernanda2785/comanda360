<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Exceptions\ValidationException;
use App\Repositories\DeliveryZoneRepository;

final class DeliveryZoneService
{
    public function __construct(
        private readonly DeliveryZoneRepository $zones = new DeliveryZoneRepository()
    ) {}

    public function listAll(int $companyId): array
    {
        return $this->zones->allByCompany($companyId);
    }

    public function listActive(int $companyId): array
    {
        return $this->zones->activeByCompany($companyId);
    }

    public function create(int $companyId, array $input): int
    {
        $name = $this->normalizeRequiredText($input['name'] ?? null, 'Informe o nome da zona de entrega.');
        $description = $this->normalizeNullableText($input['description'] ?? null);
        $feeAmount = $this->parseMoney($input['fee_amount'] ?? 0);
        $minimumOrderAmount = $this->parseNullableMoney($input['minimum_order_amount'] ?? null);
        $status = $this->normalizeStatus($input['status'] ?? 'ativo');

        if ($feeAmount < 0) {
            throw new ValidationException('A taxa da zona nao pode ser negativa.');
        }
        if ($minimumOrderAmount !== null && $minimumOrderAmount < 0) {
            throw new ValidationException('O pedido minimo da zona nao pode ser negativo.');
        }

        return $this->zones->create([
            'company_id' => $companyId,
            'name' => $name,
            'description' => $description,
            'fee_amount' => $feeAmount,
            'minimum_order_amount' => $minimumOrderAmount,
            'status' => $status,
        ]);
    }

    public function update(int $companyId, int $zoneId, array $input): void
    {
        if ($zoneId <= 0) {
            throw new ValidationException('Zona de entrega invalida para atualizacao.');
        }

        $zone = $this->zones->findByIdForCompany($companyId, $zoneId);
        if ($zone === null) {
            throw new ValidationException('Zona de entrega nao encontrada para esta empresa.');
        }

        $name = $this->normalizeRequiredText($input['name'] ?? null, 'Informe o nome da zona de entrega.');
        $description = $this->normalizeNullableText($input['description'] ?? null);
        $feeAmount = $this->parseMoney($input['fee_amount'] ?? 0);
        $minimumOrderAmount = $this->parseNullableMoney($input['minimum_order_amount'] ?? null);
        $status = $this->normalizeStatus($input['status'] ?? 'ativo');

        if ($feeAmount < 0) {
            throw new ValidationException('A taxa da zona nao pode ser negativa.');
        }
        if ($minimumOrderAmount !== null && $minimumOrderAmount < 0) {
            throw new ValidationException('O pedido minimo da zona nao pode ser negativo.');
        }

        $this->zones->update([
            'id' => $zoneId,
            'company_id' => $companyId,
            'name' => $name,
            'description' => $description,
            'fee_amount' => $feeAmount,
            'minimum_order_amount' => $minimumOrderAmount,
            'status' => $status,
        ]);
    }

    public function delete(int $companyId, int $zoneId): void
    {
        if ($zoneId <= 0) {
            throw new ValidationException('Zona de entrega invalida para exclusao.');
        }

        $zone = $this->zones->findByIdForCompany($companyId, $zoneId);
        if ($zone === null) {
            throw new ValidationException('Zona de entrega nao encontrada para esta empresa.');
        }

        $this->zones->delete($companyId, $zoneId);
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

    private function parseNullableMoney(mixed $value): ?float
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        return $this->parseMoney($raw);
    }

    private function normalizeStatus(mixed $value): string
    {
        $status = strtolower(trim((string) ($value ?? 'ativo')));
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            throw new ValidationException('Status de zona invalido.');
        }
        return $status;
    }
}

