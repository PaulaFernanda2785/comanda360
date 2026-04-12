<?php
declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;

final class Router
{
    private array $routes = [];

    public function get(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    private function addRoute(string $method, string $path, array|callable $handler, array $middlewares = []): void
    {
        $this->routes[$method][$path] = [
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch(Request $request): Response|string
    {
        Session::start();

        $route = $this->routes[$request->method][$request->uri] ?? null;

        if ($route === null) {
            throw new HttpException('404 - Página não encontrada', 404);
        }

        foreach ($route['middlewares'] as $middlewareDefinition) {
            [$middlewareClass, $args] = $this->resolveMiddleware($middlewareDefinition);
            $middleware = new $middlewareClass(...$args);
            $result = $middleware->handle($request);

            if ($result instanceof Response) {
                return $result;
            }
        }

        $handler = $route['handler'];

        if (is_callable($handler)) {
            return $handler($request);
        }

        [$controllerClass, $method] = $handler;
        $controller = new $controllerClass();

        return $controller->$method($request);
    }

    private function resolveMiddleware(mixed $middlewareDefinition): array
    {
        if (is_string($middlewareDefinition)) {
            return [$middlewareDefinition, []];
        }

        if (is_array($middlewareDefinition) && isset($middlewareDefinition[0]) && is_string($middlewareDefinition[0])) {
            $args = array_slice($middlewareDefinition, 1);
            return [$middlewareDefinition[0], $args];
        }

        throw new HttpException('500 - Middleware inválido na rota.', 500);
    }
}
