<?php
declare(strict_types=1);

use App\Controllers\Auth\LoginController;
use App\Controllers\MediaController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\ProductController;
use App\Controllers\Admin\TableController;
use App\Controllers\Admin\CommandController;
use App\Controllers\Admin\OrderController;
use App\Controllers\Admin\PaymentController;
use App\Controllers\Admin\CashRegisterController;
use App\Controllers\Admin\KitchenController;
use App\Controllers\Saas\DashboardController as SaasDashboardController;
use App\Controllers\Saas\CompanyController as SaasCompanyController;
use App\Controllers\Saas\PlanController as SaasPlanController;
use App\Controllers\Saas\SubscriptionController as SaasSubscriptionController;
use App\Controllers\Saas\SubscriptionPaymentController as SaasSubscriptionPaymentController;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\PermissionMiddleware;
use App\Middlewares\RoleContextMiddleware;

/** @var \App\Core\Router $router */

$companyAccess = static fn (string $permissionSlug): array => [
    AuthMiddleware::class,
    [RoleContextMiddleware::class, 'company'],
    [PermissionMiddleware::class, $permissionSlug],
];
$saasAccess = static fn (string $permissionSlug): array => [
    AuthMiddleware::class,
    [RoleContextMiddleware::class, 'saas'],
    [PermissionMiddleware::class, $permissionSlug],
];

$router->get('/', [LoginController::class, 'show']);
$router->get('/login', [LoginController::class, 'show']);
$router->post('/login', [LoginController::class, 'store']);
$router->get('/logout', [LoginController::class, 'logout']);
$router->get('/media/product', [MediaController::class, 'product']);

$router->get('/admin/dashboard', [DashboardController::class, 'index'], $companyAccess('dashboard.view'));

$router->get('/admin/products', [ProductController::class, 'index'], $companyAccess('products.view'));
$router->get('/admin/products/create', [ProductController::class, 'create'], $companyAccess('products.create'));
$router->post('/admin/products/store', [ProductController::class, 'store'], $companyAccess('products.create'));
$router->get('/admin/products/edit', [ProductController::class, 'edit'], $companyAccess('products.edit'));
$router->post('/admin/products/update', [ProductController::class, 'update'], $companyAccess('products.edit'));
$router->post('/admin/products/delete', [ProductController::class, 'delete'], $companyAccess('products.edit'));
$router->post('/admin/products/remove-image', [ProductController::class, 'removeImage'], $companyAccess('products.edit'));
$router->post('/admin/products/categories/store', [ProductController::class, 'storeCategory'], $companyAccess('products.edit'));
$router->post('/admin/products/categories/update', [ProductController::class, 'updateCategory'], $companyAccess('products.edit'));
$router->post('/admin/products/categories/delete', [ProductController::class, 'deleteCategory'], $companyAccess('products.edit'));
$router->get('/admin/products/additionals', [ProductController::class, 'additionals'], $companyAccess('products.edit'));
$router->post('/admin/products/additionals/rules', [ProductController::class, 'updateAdditionalRules'], $companyAccess('products.edit'));
$router->post('/admin/products/additionals/store', [ProductController::class, 'storeAdditionalItem'], $companyAccess('products.edit'));
$router->post('/admin/products/additionals/remove', [ProductController::class, 'removeAdditionalItem'], $companyAccess('products.edit'));

$router->get('/admin/tables', [TableController::class, 'index'], $companyAccess('tables.view'));
$router->get('/admin/tables/create', [TableController::class, 'create'], $companyAccess('tables.manage'));
$router->post('/admin/tables/store', [TableController::class, 'store'], $companyAccess('tables.manage'));

$router->get('/admin/commands', [CommandController::class, 'index'], $companyAccess('commands.view'));
$router->get('/admin/commands/create', [CommandController::class, 'create'], $companyAccess('commands.create'));
$router->post('/admin/commands/store', [CommandController::class, 'store'], $companyAccess('commands.create'));

$router->get('/admin/orders', [OrderController::class, 'index'], $companyAccess('orders.view'));
$router->get('/admin/orders/create', [OrderController::class, 'create'], $companyAccess('orders.create'));
$router->post('/admin/orders/store', [OrderController::class, 'store'], $companyAccess('orders.create'));
$router->post('/admin/orders/status', [OrderController::class, 'updateStatus'], $companyAccess('orders.status'));
$router->post('/admin/orders/send-kitchen', [OrderController::class, 'sendToKitchen'], $companyAccess('orders.status'));

$router->get('/admin/kitchen', [KitchenController::class, 'index'], $companyAccess('orders.view'));
$router->post('/admin/kitchen/status', [KitchenController::class, 'updateStatus'], $companyAccess('orders.status'));
$router->post('/admin/kitchen/emit-ticket', [KitchenController::class, 'emitTicket'], $companyAccess('orders.view'));

$router->get('/admin/payments', [PaymentController::class, 'index'], $companyAccess('payments.view'));
$router->get('/admin/payments/create', [PaymentController::class, 'create'], $companyAccess('payments.create'));
$router->post('/admin/payments/store', [PaymentController::class, 'store'], $companyAccess('payments.create'));

$router->get('/admin/cash-registers', [CashRegisterController::class, 'index'], $companyAccess('cash_registers.open'));
$router->post('/admin/cash-registers/open', [CashRegisterController::class, 'open'], $companyAccess('cash_registers.open'));
$router->post('/admin/cash-registers/close', [CashRegisterController::class, 'close'], $companyAccess('cash_registers.close'));

$router->get('/saas/dashboard', [SaasDashboardController::class, 'index'], $saasAccess('dashboard.view'));
$router->get('/saas/companies', [SaasCompanyController::class, 'index'], $saasAccess('companies.view'));
$router->get('/saas/plans', [SaasPlanController::class, 'index'], $saasAccess('plans.view'));
$router->get('/saas/subscriptions', [SaasSubscriptionController::class, 'index'], $saasAccess('subscriptions.view'));
$router->get('/saas/subscription-payments', [SaasSubscriptionPaymentController::class, 'index'], $saasAccess('subscriptions.view'));
$router->get('/saas/subscription-payments/create', [SaasSubscriptionPaymentController::class, 'create'], $saasAccess('subscriptions.manage'));
$router->post('/saas/subscription-payments/store', [SaasSubscriptionPaymentController::class, 'store'], $saasAccess('subscriptions.manage'));
$router->post('/saas/subscription-payments/mark-paid', [SaasSubscriptionPaymentController::class, 'markPaid'], $saasAccess('subscriptions.manage'));
$router->post('/saas/subscription-payments/mark-overdue', [SaasSubscriptionPaymentController::class, 'markOverdue'], $saasAccess('subscriptions.manage'));
$router->post('/saas/subscription-payments/cancel', [SaasSubscriptionPaymentController::class, 'cancel'], $saasAccess('subscriptions.manage'));
