<?php
declare(strict_types=1);

use App\Controllers\Auth\LoginController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\ProductController;
use App\Controllers\Admin\TableController;
use App\Controllers\Admin\CommandController;
use App\Controllers\Admin\OrderController;
use App\Middlewares\AuthMiddleware;

/** @var \App\Core\Router $router */

$router->get('/', [LoginController::class, 'show']);
$router->get('/login', [LoginController::class, 'show']);
$router->post('/login', [LoginController::class, 'store']);
$router->get('/logout', [LoginController::class, 'logout']);

$router->get('/admin/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::class]);

$router->get('/admin/products', [ProductController::class, 'index'], [AuthMiddleware::class]);
$router->get('/admin/products/create', [ProductController::class, 'create'], [AuthMiddleware::class]);
$router->post('/admin/products/store', [ProductController::class, 'store'], [AuthMiddleware::class]);

$router->get('/admin/tables', [TableController::class, 'index'], [AuthMiddleware::class]);
$router->get('/admin/tables/create', [TableController::class, 'create'], [AuthMiddleware::class]);
$router->post('/admin/tables/store', [TableController::class, 'store'], [AuthMiddleware::class]);

$router->get('/admin/commands', [CommandController::class, 'index'], [AuthMiddleware::class]);
$router->get('/admin/commands/create', [CommandController::class, 'create'], [AuthMiddleware::class]);
$router->post('/admin/commands/store', [CommandController::class, 'store'], [AuthMiddleware::class]);

$router->get('/admin/orders', [OrderController::class, 'index'], [AuthMiddleware::class]);
$router->get('/admin/orders/create', [OrderController::class, 'create'], [AuthMiddleware::class]);
$router->post('/admin/orders/store', [OrderController::class, 'store'], [AuthMiddleware::class]);
