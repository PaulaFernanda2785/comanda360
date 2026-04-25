<?php
declare(strict_types=1);

function mesimenu_resolve_base_path(string $publicPath): ?string
{
    $candidates = [
        dirname($publicPath),
        dirname($publicPath) . '/mesimenu_app',
        dirname($publicPath, 2) . '/mesimenu_app',
        dirname($publicPath, 3) . '/mesimenu_app',
    ];

    foreach (array_unique($candidates) as $candidate) {
        $realPath = realpath($candidate);
        if ($realPath === false) {
            continue;
        }

        if (
            is_dir($realPath . '/app')
            && is_dir($realPath . '/config')
            && is_file($realPath . '/routes/web.php')
        ) {
            return $realPath;
        }
    }

    return null;
}

$basePath = mesimenu_resolve_base_path(__DIR__);
if ($basePath === null) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Pasta mesimenu_app nao encontrada. Confira se ela foi extraida fora do public_html.';
    exit;
}

define('BASE_PATH', $basePath);

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
