<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Helpers/helpers.php';
require BASE_PATH . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register(BASE_PATH . '/app');
\App\Core\Env::load(BASE_PATH . '/.env');

$config = require BASE_PATH . '/config/app.php';
date_default_timezone_set($config['timezone']);

$service = new \App\Services\Admin\SubscriptionGatewayService();
$subscriptions = new \App\Repositories\SubscriptionRepository();

if (!$service->isConfigured()) {
    fwrite(STDERR, "Mercado Pago nao configurado no ambiente.\n");
    exit(1);
}

$items = $subscriptions->activeForBilling();
$processed = 0;

foreach ($items as $item) {
    $companyId = (int) ($item['company_id'] ?? 0);
    if ($companyId <= 0) {
        continue;
    }

    try {
        $service->syncSubscriptionByCompany($companyId);
        $processed++;
        fwrite(STDOUT, "Sincronizada empresa {$companyId}\n");
    } catch (Throwable $e) {
        fwrite(STDERR, "Falha ao sincronizar empresa {$companyId}: {$e->getMessage()}\n");
    }
}

fwrite(STDOUT, "Total sincronizado: {$processed}\n");
