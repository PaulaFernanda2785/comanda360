<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SubscriptionRepository extends BaseRepository
{
    public function allForSaas(): array
    {
        $sql = "
            SELECT
                s.id,
                s.company_id,
                s.plan_id,
                s.status,
                s.billing_cycle,
                s.amount,
                s.starts_at,
                s.ends_at,
                s.canceled_at,
                s.preferred_payment_method,
                s.auto_charge_enabled,
                s.card_brand,
                s.card_last_digits,
                s.gateway_provider,
                s.gateway_subscription_id,
                s.gateway_checkout_url,
                s.gateway_status,
                s.gateway_webhook_payload_json,
                s.gateway_last_synced_at,
                s.created_at,
                c.name AS company_name,
                c.slug AS company_slug,
                c.email AS company_email,
                c.document_number AS company_document_number,
                p.name AS plan_name,
                p.slug AS plan_slug
            FROM subscriptions s
            INNER JOIN companies c ON c.id = s.company_id
            INNER JOIN plans p ON p.id = s.plan_id
            ORDER BY s.created_at DESC, s.id DESC
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function summary(): array
    {
        $stmt = $this->db()->prepare("
            SELECT
                COUNT(*) AS total_subscriptions,
                SUM(CASE WHEN status = 'ativa' THEN 1 ELSE 0 END) AS active_subscriptions,
                SUM(CASE WHEN status = 'trial' THEN 1 ELSE 0 END) AS trial_subscriptions,
                SUM(CASE WHEN status = 'vencida' THEN 1 ELSE 0 END) AS expired_subscriptions,
                SUM(CASE WHEN status = 'ativa' AND billing_cycle = 'mensal' THEN amount ELSE 0 END) AS active_monthly_mrr
            FROM subscriptions
        ");
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                s.id,
                s.company_id,
                s.plan_id,
                s.status,
                s.billing_cycle,
                s.amount,
                s.starts_at,
                s.ends_at,
                s.canceled_at,
                s.preferred_payment_method,
                s.auto_charge_enabled,
                s.card_brand,
                s.card_last_digits,
                s.gateway_provider,
                s.gateway_subscription_id,
                s.gateway_checkout_url,
                s.gateway_status,
                s.gateway_webhook_payload_json,
                s.gateway_last_synced_at,
                c.name AS company_name,
                c.slug AS company_slug,
                c.email AS company_email,
                c.document_number AS company_document_number,
                p.name AS plan_name,
                p.slug AS plan_slug,
                p.description AS plan_description,
                p.features_json AS plan_features_json
            FROM subscriptions s
            INNER JOIN companies c ON c.id = s.company_id
            INNER JOIN plans p ON p.id = s.plan_id
            WHERE s.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findCurrentByCompanyId(int $companyId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT
                s.id,
                s.company_id,
                s.plan_id,
                s.status,
                s.billing_cycle,
                s.amount,
                s.starts_at,
                s.ends_at,
                s.canceled_at,
                s.preferred_payment_method,
                s.auto_charge_enabled,
                s.card_brand,
                s.card_last_digits,
                s.gateway_provider,
                s.gateway_subscription_id,
                s.gateway_checkout_url,
                s.gateway_status,
                s.gateway_webhook_payload_json,
                s.gateway_last_synced_at,
                s.created_at,
                s.updated_at,
                c.name AS company_name,
                c.slug AS company_slug,
                c.email AS company_email,
                c.document_number AS company_document_number,
                c.subscription_status AS company_subscription_status,
                c.subscription_starts_at AS company_subscription_starts_at,
                c.subscription_ends_at AS company_subscription_ends_at,
                c.trial_ends_at AS company_trial_ends_at,
                p.name AS plan_name,
                p.slug AS plan_slug,
                p.description AS plan_description,
                p.features_json AS plan_features_json
            FROM subscriptions s
            INNER JOIN companies c ON c.id = s.company_id
            INNER JOIN plans p ON p.id = s.plan_id
            WHERE s.company_id = :company_id
            ORDER BY
                CASE s.status
                    WHEN 'ativa' THEN 0
                    WHEN 'trial' THEN 1
                    WHEN 'vencida' THEN 2
                    WHEN 'cancelada' THEN 3
                    ELSE 4
                END,
                s.created_at DESC,
                s.id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'company_id' => $companyId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByGatewaySubscriptionId(string $gatewaySubscriptionId): ?array
    {
        $gatewaySubscriptionId = trim($gatewaySubscriptionId);
        if ($gatewaySubscriptionId === '') {
            return null;
        }

        $stmt = $this->db()->prepare("
            SELECT
                s.id,
                s.company_id,
                s.plan_id,
                s.status,
                s.billing_cycle,
                s.amount,
                s.starts_at,
                s.ends_at,
                s.canceled_at,
                s.preferred_payment_method,
                s.auto_charge_enabled,
                s.card_brand,
                s.card_last_digits,
                s.gateway_provider,
                s.gateway_subscription_id,
                s.gateway_checkout_url,
                s.gateway_status,
                s.gateway_webhook_payload_json,
                s.gateway_last_synced_at,
                s.created_at,
                s.updated_at
            FROM subscriptions s
            WHERE s.gateway_subscription_id = :gateway_subscription_id
            LIMIT 1
        ");
        $stmt->execute([
            'gateway_subscription_id' => $gatewaySubscriptionId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function activeForBilling(): array
    {
        $stmt = $this->db()->prepare("
            SELECT
                s.id,
                s.company_id,
                s.plan_id,
                s.status,
                s.billing_cycle,
                s.amount,
                s.starts_at,
                s.ends_at,
                s.preferred_payment_method,
                s.auto_charge_enabled,
                s.gateway_provider,
                s.gateway_subscription_id,
                s.gateway_checkout_url,
                s.gateway_status,
                c.name AS company_name,
                c.slug AS company_slug,
                p.name AS plan_name
            FROM subscriptions s
            INNER JOIN companies c ON c.id = s.company_id
            INNER JOIN plans p ON p.id = s.plan_id
            WHERE s.status IN ('ativa', 'trial')
            ORDER BY c.name ASC, s.id DESC
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listForGatewaySync(?int $companyId = null): array
    {
        $where = [
            "s.status IN ('ativa', 'trial', 'vencida')",
            "(COALESCE(s.gateway_subscription_id, '') <> '' OR COALESCE(s.gateway_checkout_url, '') <> '')",
        ];
        $params = [];

        if ($companyId !== null && $companyId > 0) {
            $where[] = 's.company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        $sql = "
            SELECT
                s.id,
                s.company_id,
                s.plan_id,
                s.status,
                s.billing_cycle,
                s.amount,
                s.starts_at,
                s.ends_at,
                s.preferred_payment_method,
                s.auto_charge_enabled,
                s.gateway_provider,
                s.gateway_subscription_id,
                s.gateway_checkout_url,
                s.gateway_status,
                c.name AS company_name,
                c.slug AS company_slug,
                p.name AS plan_name
            FROM subscriptions s
            INNER JOIN companies c ON c.id = s.company_id
            INNER JOIN plans p ON p.id = s.plan_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.name ASC, s.id DESC
        ";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function updateBillingProfile(int $subscriptionId, array $data): void
    {
        $stmt = $this->db()->prepare("
            UPDATE subscriptions
            SET
                preferred_payment_method = :preferred_payment_method,
                auto_charge_enabled = :auto_charge_enabled,
                card_brand = :card_brand,
                card_last_digits = :card_last_digits,
                updated_at = NOW()
            WHERE id = :subscription_id
            LIMIT 1
        ");
        $stmt->execute([
            'subscription_id' => $subscriptionId,
            'preferred_payment_method' => $data['preferred_payment_method'] ?? null,
            'auto_charge_enabled' => !empty($data['auto_charge_enabled']) ? 1 : 0,
            'card_brand' => $data['card_brand'] ?? null,
            'card_last_digits' => $data['card_last_digits'] ?? null,
        ]);
    }

    public function updateGatewayProfile(int $subscriptionId, array $data): void
    {
        $stmt = $this->db()->prepare("
            UPDATE subscriptions
            SET
                gateway_provider = :gateway_provider,
                gateway_subscription_id = :gateway_subscription_id,
                gateway_checkout_url = :gateway_checkout_url,
                gateway_status = :gateway_status,
                gateway_webhook_payload_json = :gateway_webhook_payload_json,
                gateway_last_synced_at = :gateway_last_synced_at,
                updated_at = NOW()
            WHERE id = :subscription_id
            LIMIT 1
        ");
        $stmt->execute([
            'subscription_id' => $subscriptionId,
            'gateway_provider' => $data['gateway_provider'] ?? null,
            'gateway_subscription_id' => $data['gateway_subscription_id'] ?? null,
            'gateway_checkout_url' => $data['gateway_checkout_url'] ?? null,
            'gateway_status' => $data['gateway_status'] ?? null,
            'gateway_webhook_payload_json' => $data['gateway_webhook_payload_json'] ?? null,
            'gateway_last_synced_at' => $data['gateway_last_synced_at'] ?? null,
        ]);
    }

    public function updateStatus(int $subscriptionId, string $status): void
    {
        $stmt = $this->db()->prepare("
            UPDATE subscriptions
            SET
                status = :status,
                updated_at = NOW()
            WHERE id = :subscription_id
            LIMIT 1
        ");
        $stmt->execute([
            'subscription_id' => $subscriptionId,
            'status' => $status,
        ]);
    }
}
