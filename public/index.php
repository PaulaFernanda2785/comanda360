<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Helpers/helpers.php';
require BASE_PATH . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register(BASE_PATH . '/app');
\App\Core\Env::load(BASE_PATH . '/.env');

$config = require BASE_PATH . '/config/app.php';
date_default_timezone_set($config['timezone']);

$router = new \App\Core\Router();
require BASE_PATH . '/routes/web.php';

$request = \App\Core\Request::capture();

try {
    $response = $router->dispatch($request);

    if ($response instanceof \App\Core\Response) {
        $response->send();
        exit;
    }

    echo (string) $response;
} catch (Throwable $e) {
    \App\Core\ExceptionHandler::render($e)->send();
}
