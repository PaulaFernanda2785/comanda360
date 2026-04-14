<?php
declare(strict_types=1);

namespace App\Repositories;

final class DeliveryAddressRepository extends BaseRepository
{
    public function create(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO delivery_addresses (
                company_id,
                customer_id,
                label,
                street,
                number,
                complement,
                neighborhood,
                city,
                state,
                zip_code,
                reference,
                delivery_zone_id,
                created_at
            ) VALUES (
                :company_id,
                :customer_id,
                :label,
                :street,
                :number,
                :complement,
                :neighborhood,
                :city,
                :state,
                :zip_code,
                :reference,
                :delivery_zone_id,
                NOW()
            )
        ");
        $stmt->execute($data);

        return (int) $this->db()->lastInsertId();
    }
}

