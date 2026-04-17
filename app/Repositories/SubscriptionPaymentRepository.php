<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SubscriptionPaymentRepository extends BaseRepository
{
    public function allForSaas(): array
    {
        $sql = "
            SELECT
                sp.id,
                sp.subscription_id,
                sp.company_id,
                sp.reference_month,
                sp.reference_year,
                sp.amount,
                sp.status,
                sp.payment_method,
                sp.paid_at,
                sp.due_date,
                sp.transaction_reference,
                sp.charge_origin,
                sp.pix_code,
                sp.pix_qr_payload,
                sp.pix_qr_image_base64,
                sp.pix_ticket_url,
                sp.payment_details_json,
                sp.gateway_payment_id,
                sp.gateway_payment_url,
                sp.gateway_status,
                sp.gateway_webhook_payload_json,
                sp.gateway_last_synced_at,
                sp.created_at,
                c.name AS company_name,
                c.slug AS company_slug,
                s.status AS subscription_status,
                s.billing_cycle,
                p.name AS plan_name
            FROM subscription_payments sp
            INNER JOIN companies c ON c.id = sp.company_id
            INNER JOIN subscriptions s ON s.id = sp.subscription_id
            INNER JOIN plans p ON p.id = s.plan_id
            ORDER BY sp.due_date DESC, sp.id DESC
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listByCompany(int $companyId, int $limit = 24): array
    {
        $limit = max(1, min(120, $limit));

        $stmt = $this->db()->prepare("
            SELECT
                sp.id,
                sp.subscription_id,
                sp.company_id,
                sp.reference_month,
                sp.reference_year,
                sp.amount,
                sp.status,
                sp.payment_method,
                sp.paid_at,
                sp.due_date,
                sp.transaction_reference,
                sp.charge_origin,
                sp.pix_code,
                sp.pix_qr_payload,
                sp.pix_qr_image_base64,
                sp.pix_ticket_url,
                sp.payment_details_json,
                sp.gateway_payment_id,
                sp.gateway_payment_url,
                sp.gateway_status,
                sp.gateway_webhook_payload_json,
                sp.gateway_last_synced_at,
                sp.created_at,
                sp.updated_at,
                s.status AS subscription_status,
                s.billing_cycle,
                s.amount AS subscription_amount,
                p.name AS plan_name,
                p.slug AS plan_slug
            FROM subscription_payments sp
            INNER JOIN subscriptions s ON s.id = sp.subscription_id
            INNER JOIN plans p ON p.id = s.plan_id
            WHERE sp.company_id = :company_id
            ORDER BY sp.due_date DESC, sp.id DESC
            LIMIT {$limit}
        ");
        $stmt->execute([
            'company_id' => $companyId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listOpenBySubscriptionId(int $subscriptionId): array
    {
        $stmt = $this->db()->prepare("
            SELECT
                id,
                subscription_id,
                company_id,
                reference_month,
                reference_year,
                amount,
                status,
                payment_method,
                paid_at,
                due_date,
                transaction_reference,
                charge_origin,
                pix_code,
                pix_qr_payload,
                pix_qr_image_base64,
                pix_ticket_url,
                payment_details_json,
                gateway_payment_id,
                gateway_payment_url,
                gateway_status,
                gateway_webhook_payload_json,
                gateway_last_synced_at,
                created_at,
                updated_at
            FROM subscription_payments
            WHERE subscription_id = :subscription_id
              AND status IN ('pendente', 'vencido')
            ORDER BY due_date ASC, id ASC
        ");
        $stmt->execute([
            'subscription_id' => $subscriptionId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listBySubscriptionId(int $subscriptionId, int $limit = 240): array
    {
        $limit = max(1, min(500, $limit));

        $stmt = $this->db()->prepare("
            SELECT
                id,
                subscription_id,
                company_id,
                reference_month,
                reference_year,
                amount,
                status,
                payment_method,
                paid_at,
                due_date,
                transaction_reference,
                charge_origin,
                pix_code,
                pix_qr_payload,
                pix_qr_image_base64,
                pix_ticket_url,
                payment_details_json,
                gateway_payment_id,
                gateway_payment_url,
                gateway_status,
                gateway_webhook_payload_json,
                gateway_last_synced_at,
                created_at,
                updated_at
            FROM subscription_payments
            WHERE subscription_id = :subscription_id
            ORDER BY reference_year DESC, reference_month DESC, id DESC
            LIMIT {$limit}
        ");
        $stmt->execute([
            'subscription_id' => $subscriptionId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data): int
    {
        $stmt = $this->db()->prepare("
            INSERT INTO subscription_payments (
                subscription_id,
                company_id,
                reference_month,
                reference_year,
                amount,
                status,
                payment_method,
                paid_at,
                due_date,
                transaction_reference,
                charge_origin,
                pix_code,
                pix_qr_payload,
                pix_qr_image_base64,
                pix_ticket_url,
                payment_details_json,
                gateway_payment_id,
                gateway_payment_url,
                gateway_status,
                gateway_webhook_payload_json,
                gateway_last_synced_at,
                created_at,
                updated_at
            ) VALUES (
                :subscription_id,
                :company_id,
                :reference_month,
                :reference_year,
                :amount,
                :status,
                :payment_method,
                :paid_at,
                :due_date,
                :transaction_reference,
                :charge_origin,
                :pix_code,
                :pix_qr_payload,
                :pix_qr_image_base64,
                :pix_ticket_url,
                :payment_details_json,
                :gateway_payment_id,
                :gateway_payment_url,
                :gateway_status,
                :gateway_webhook_payload_json,
                :gateway_last_synced_at,
                NOW(),
                NOW()
            )
        ");
        $stmt->execute([
            'subscription_id' => $data['subscription_id'],
            'company_id' => $data['company_id'],
            'reference_month' => $data['reference_month'],
            'reference_year' => $data['reference_year'],
            'amount' => $data['amount'],
            'status' => $data['status'],
            'payment_method' => $data['payment_method'],
            'paid_at' => $data['paid_at'],
            'due_date' => $data['due_date'],
            'transaction_reference' => $data['transaction_reference'],
            'charge_origin' => $data['charge_origin'] ?? 'manual',
            'pix_code' => $data['pix_code'] ?? null,
            'pix_qr_payload' => $data['pix_qr_payload'] ?? null,
            'pix_qr_image_base64' => $data['pix_qr_image_base64'] ?? null,
            'pix_ticket_url' => $data['pix_ticket_url'] ?? null,
            'payment_details_json' => $data['payment_details_json'] ?? null,
            'gateway_payment_id' => $data['gateway_payment_id'] ?? null,
            'gateway_payment_url' => $data['gateway_payment_url'] ?? null,
            'gateway_status' => $data['gateway_status'] ?? null,
            'gateway_webhook_payload_json' => $data['gateway_webhook_payload_json'] ?? null,
            'gateway_last_synced_at' => $data['gateway_last_synced_at'] ?? null,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                id,
                subscription_id,
                company_id,
                reference_month,
                reference_year,
                amount,
                status,
                payment_method,
                paid_at,
                due_date,
                transaction_reference,
                charge_origin,
                pix_code,
                pix_qr_payload,
                pix_qr_image_base64,
                pix_ticket_url,
                payment_details_json,
                gateway_payment_id,
                gateway_payment_url,
                gateway_status,
                gateway_webhook_payload_json,
                gateway_last_synced_at,
                created_at,
                updated_at
            FROM subscription_payments
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByIdForCompany(int $companyId, int $paymentId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                sp.id,
                sp.subscription_id,
                sp.company_id,
                sp.reference_month,
                sp.reference_year,
                sp.amount,
                sp.status,
                sp.payment_method,
                sp.paid_at,
                sp.due_date,
                sp.transaction_reference,
                sp.charge_origin,
                sp.pix_code,
                sp.pix_qr_payload,
                sp.pix_qr_image_base64,
                sp.pix_ticket_url,
                sp.payment_details_json,
                sp.gateway_payment_id,
                sp.gateway_payment_url,
                sp.gateway_status,
                sp.gateway_webhook_payload_json,
                sp.gateway_last_synced_at,
                sp.created_at,
                sp.updated_at,
                s.status AS subscription_status,
                s.billing_cycle,
                s.plan_id,
                p.name AS plan_name
            FROM subscription_payments sp
            INNER JOIN subscriptions s ON s.id = sp.subscription_id
            INNER JOIN plans p ON p.id = s.plan_id
            WHERE sp.company_id = :company_id
              AND sp.id = :payment_id
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'payment_id' => $paymentId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByReference(int $subscriptionId, int $referenceMonth, int $referenceYear): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                id,
                subscription_id,
                company_id,
                reference_month,
                reference_year,
                amount,
                status,
                payment_method,
                paid_at,
                due_date,
                transaction_reference,
                charge_origin,
                pix_code,
                pix_qr_payload,
                pix_qr_image_base64,
                pix_ticket_url,
                payment_details_json,
                gateway_payment_id,
                gateway_payment_url,
                gateway_status,
                gateway_webhook_payload_json,
                gateway_last_synced_at,
                created_at,
                updated_at
            FROM subscription_payments
            WHERE subscription_id = :subscription_id
              AND reference_month = :reference_month
              AND reference_year = :reference_year
            LIMIT 1
        ");
        $stmt->execute([
            'subscription_id' => $subscriptionId,
            'reference_month' => $referenceMonth,
            'reference_year' => $referenceYear,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByGatewayPaymentId(string $gatewayPaymentId): ?array
    {
        $gatewayPaymentId = trim($gatewayPaymentId);
        if ($gatewayPaymentId === '') {
            return null;
        }

        $stmt = $this->db()->prepare("
            SELECT
                id,
                subscription_id,
                company_id,
                reference_month,
                reference_year,
                amount,
                status,
                payment_method,
                paid_at,
                due_date,
                transaction_reference,
                charge_origin,
                pix_code,
                pix_qr_payload,
                pix_qr_image_base64,
                pix_ticket_url,
                payment_details_json,
                gateway_payment_id,
                gateway_payment_url,
                gateway_status,
                gateway_webhook_payload_json,
                gateway_last_synced_at,
                created_at,
                updated_at
            FROM subscription_payments
            WHERE gateway_payment_id = :gateway_payment_id
            LIMIT 1
        ");
        $stmt->execute([
            'gateway_payment_id' => $gatewayPaymentId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateRecord(int $paymentId, array $data): void
    {
        $stmt = $this->db()->prepare("
            UPDATE subscription_payments
            SET
                status = :status,
                payment_method = :payment_method,
                paid_at = :paid_at,
                due_date = :due_date,
                transaction_reference = :transaction_reference,
                charge_origin = :charge_origin,
                pix_code = :pix_code,
                pix_qr_payload = :pix_qr_payload,
                pix_qr_image_base64 = :pix_qr_image_base64,
                pix_ticket_url = :pix_ticket_url,
                payment_details_json = :payment_details_json,
                gateway_payment_id = :gateway_payment_id,
                gateway_payment_url = :gateway_payment_url,
                gateway_status = :gateway_status,
                gateway_webhook_payload_json = :gateway_webhook_payload_json,
                gateway_last_synced_at = :gateway_last_synced_at,
                updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $paymentId,
            'status' => $data['status'],
            'payment_method' => $data['payment_method'] ?? null,
            'paid_at' => $data['paid_at'] ?? null,
            'due_date' => $data['due_date'],
            'transaction_reference' => $data['transaction_reference'] ?? null,
            'charge_origin' => $data['charge_origin'] ?? 'manual',
            'pix_code' => $data['pix_code'] ?? null,
            'pix_qr_payload' => $data['pix_qr_payload'] ?? null,
            'pix_qr_image_base64' => $data['pix_qr_image_base64'] ?? null,
            'pix_ticket_url' => $data['pix_ticket_url'] ?? null,
            'payment_details_json' => $data['payment_details_json'] ?? null,
            'gateway_payment_id' => $data['gateway_payment_id'] ?? null,
            'gateway_payment_url' => $data['gateway_payment_url'] ?? null,
            'gateway_status' => $data['gateway_status'] ?? null,
            'gateway_webhook_payload_json' => $data['gateway_webhook_payload_json'] ?? null,
            'gateway_last_synced_at' => $data['gateway_last_synced_at'] ?? null,
        ]);
    }

    public function updateStatus(
        int $paymentId,
        string $status,
        ?string $paymentMethod,
        ?string $transactionReference,
        ?string $paidAt
    ): void {
        $payment = $this->findById($paymentId);
        if ($payment === null) {
            return;
        }

        $this->updateRecord($paymentId, [
            'status' => $status,
            'payment_method' => $paymentMethod,
            'paid_at' => $paidAt,
            'due_date' => $payment['due_date'],
            'transaction_reference' => $transactionReference,
            'charge_origin' => $payment['charge_origin'] ?? 'manual',
            'pix_code' => $payment['pix_code'] ?? null,
            'pix_qr_payload' => $payment['pix_qr_payload'] ?? null,
            'pix_qr_image_base64' => $payment['pix_qr_image_base64'] ?? null,
            'pix_ticket_url' => $payment['pix_ticket_url'] ?? null,
            'payment_details_json' => $payment['payment_details_json'] ?? null,
            'gateway_payment_id' => $payment['gateway_payment_id'] ?? null,
            'gateway_payment_url' => $payment['gateway_payment_url'] ?? null,
            'gateway_status' => $payment['gateway_status'] ?? null,
            'gateway_webhook_payload_json' => $payment['gateway_webhook_payload_json'] ?? null,
            'gateway_last_synced_at' => $payment['gateway_last_synced_at'] ?? null,
        ]);
    }

    public function listBySubscriptionIdPaginated(int $subscriptionId, array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ['subscription_id = :subscription_id'];
        $params = ['subscription_id' => $subscriptionId];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(
                transaction_reference LIKE :search
                OR charge_origin LIKE :search
                OR payment_method LIKE :search
                OR gateway_payment_id LIKE :search
                OR pix_code LIKE :search
            )';
            $params['search'] = '%' . $search . '%';
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        $method = trim((string) ($filters['method'] ?? ''));
        if ($method !== '') {
            if ($method === 'none') {
                $where[] = '(payment_method IS NULL OR payment_method = \'\')';
            } else {
                $where[] = 'payment_method = :payment_method';
                $params['payment_method'] = $method;
            }
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->db()->prepare("
            SELECT COUNT(*)
            FROM subscription_payments
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $itemsStmt = $this->db()->prepare("
            SELECT
                id,
                subscription_id,
                company_id,
                reference_month,
                reference_year,
                amount,
                status,
                payment_method,
                paid_at,
                due_date,
                transaction_reference,
                charge_origin,
                pix_code,
                pix_qr_payload,
                pix_qr_image_base64,
                pix_ticket_url,
                payment_details_json,
                gateway_payment_id,
                gateway_payment_url,
                gateway_status,
                gateway_webhook_payload_json,
                gateway_last_synced_at,
                created_at,
                updated_at
            FROM subscription_payments
            WHERE {$whereSql}
            ORDER BY due_date DESC, id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $itemsStmt->execute($params);

        return [
            'items' => $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function summary(): array
    {
        $stmt = $this->db()->prepare("
            SELECT
                COUNT(*) AS total_charges,
                SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) AS pending_charges,
                SUM(CASE WHEN status = 'pago' THEN 1 ELSE 0 END) AS paid_charges,
                SUM(CASE WHEN status = 'vencido' THEN 1 ELSE 0 END) AS overdue_charges,
                SUM(CASE WHEN status = 'pago' THEN amount ELSE 0 END) AS total_paid_amount
            FROM subscription_payments
        ");
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }
}
