<?php
declare(strict_types=1);

return [
    'mercado_pago' => [
        'env' => getenv('MERCADO_PAGO_ENV') ?: 'sandbox',
        'public_key' => trim((string) (getenv('MERCADO_PAGO_PUBLIC_KEY') ?: '')),
        'access_token' => trim((string) (getenv('MERCADO_PAGO_ACCESS_TOKEN') ?: '')),
        'webhook_secret' => trim((string) (getenv('MERCADO_PAGO_WEBHOOK_SECRET') ?: '')),
        'webhook_max_age_seconds' => (int) (getenv('MERCADO_PAGO_WEBHOOK_MAX_AGE_SECONDS') ?: 86400),
        'base_url' => 'https://api.mercadopago.com',
        'ssl_cafile' => trim((string) (getenv('MERCADO_PAGO_SSL_CAFILE') ?: '')),
    ],
];
