<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Helpers/helpers.php';
require BASE_PATH . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register(BASE_PATH . '/app');
\App\Core\Env::load(BASE_PATH . '/.env');

$config = require BASE_PATH . '/config/app.php';
date_default_timezone_set($config['timezone']);

$service = new \App\Services\Admin\SubscriptionPortalService();
$subscriptions = new \App\Repositories\SubscriptionRepository();
$subscriptionPayments = new \App\Repositories\SubscriptionPaymentRepository();
$gateway = new \App\Services\Admin\SubscriptionGatewayService();

if (!$gateway->isConfigured()) {
    fwrite(STDERR, "Mercado Pago nao configurado no ambiente.\n");
    exit(1);
}

$targetCompanyId = null;
foreach (array_slice($argv, 1) as $argument) {
    $raw = trim((string) $argument);
    if ($raw === '') {
        continue;
    }

    if (str_starts_with($raw, '--company=')) {
        $targetCompanyId = (int) substr($raw, strlen('--company='));
        continue;
    }

    if (preg_match('/^\d+$/', $raw) === 1) {
        $targetCompanyId = (int) $raw;
    }
}

$companyIds = [];
foreach ($subscriptions->listForGatewaySync($targetCompanyId) as $item) {
    $companyId = (int) ($item['company_id'] ?? 0);
    if ($companyId > 0) {
        $companyIds[$companyId] = true;
    }
}
foreach ($subscriptionPayments->listCompanyIdsForGatewaySync($targetCompanyId) as $companyId) {
    if ($companyId > 0) {
        $companyIds[$companyId] = true;
    }
}

if ($companyIds === []) {
    $scope = $targetCompanyId !== null && $targetCompanyId > 0
        ? 'empresa ' . $targetCompanyId
        : 'assinaturas/cobrancas elegiveis';
    fwrite(STDOUT, "Nenhuma assinatura ou cobranca encontrada para sincronizar ({$scope}).\n");
    exit(0);
}

$processed = 0;
$failed = 0;

foreach (array_keys($companyIds) as $companyId) {
    try {
        $service->refreshGatewayStatus($companyId);
        $processed++;
        fwrite(STDOUT, "Sincronizada empresa {$companyId}\n");
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDERR, "Falha ao sincronizar empresa {$companyId}: {$e->getMessage()}\n");
    }
}

fwrite(STDOUT, "Total sincronizado: {$processed}\n");
if ($failed > 0) {
    fwrite(STDOUT, "Total com falha: {$failed}\n");
}
