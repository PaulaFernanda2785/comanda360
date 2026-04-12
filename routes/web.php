<?php
declare(strict_types=1);

use App\Controllers\Auth\LoginController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\ProductController;
use App\Controllers\Admin\TableController;
use App\Controllers\Admin\CommandController;
use App\Controllers\Admin\OrderController;
use App\Controllers\Saas\DashboardController as SaasDashboardController;
use App\Controllers\Saas\CompanyController as SaasCompanyController;
use App\Controllers\Saas\PlanController as SaasPlanController;
use App\Controllers\Saas\SubscriptionController as SaasSubscriptionController;
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

$router->get('/admin/dashboard', [DashboardController::class, 'index'], $companyAccess('dashboard.view'));

$router->get('/admin/products', [ProductController::class, 'index'], $companyAccess('products.view'));
$router->get('/admin/products/create', [ProductController::class, 'create'], $companyAccess('products.create'));
$router->post('/admin/products/store', [ProductController::class, 'store'], $companyAccess('products.create'));

$router->get('/admin/tables', [TableController::class, 'index'], $companyAccess('tables.view'));
$router->get('/admin/tables/create', [TableController::class, 'create'], $companyAccess('tables.manage'));
$router->post('/admin/tables/store', [TableController::class, 'store'], $companyAccess('tables.manage'));

$router->get('/admin/commands', [CommandController::class, 'index'], $companyAccess('commands.view'));
$router->get('/admin/commands/create', [CommandController::class, 'create'], $companyAccess('commands.create'));
$router->post('/admin/commands/store', [CommandController::class, 'store'], $companyAccess('commands.create'));

$router->get('/admin/orders', [OrderController::class, 'index'], $companyAccess('orders.view'));
$router->get('/admin/orders/create', [OrderController::class, 'create'], $companyAccess('orders.create'));
$router->post('/admin/orders/store', [OrderController::class, 'store'], $companyAccess('orders.create'));

$router->get('/saas/dashboard', [SaasDashboardController::class, 'index'], $saasAccess('dashboard.view'));
$router->get('/saas/companies', [SaasCompanyController::class, 'index'], $saasAccess('companies.view'));
$router->get('/saas/plans', [SaasPlanController::class, 'index'], $saasAccess('plans.view'));
$router->get('/saas/subscriptions', [SaasSubscriptionController::class, 'index'], $saasAccess('subscriptions.view'));
