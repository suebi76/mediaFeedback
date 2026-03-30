<?php

declare(strict_types=1);

namespace MediaFeedbackV2\Core;

class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable|array $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => rtrim($path, '/') ?: '/',
            'handler' => $handler,
        ];
    }

    public function run(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');

        if ($scriptDir !== '/' && $scriptDir !== '\\' && $scriptDir !== '.') {
            $uriPath = str_replace(str_replace('\\', '/', $scriptDir), '', str_replace('\\', '/', $uriPath));
        }

        $path = str_replace('/index.php', '', $uriPath);
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method || $route['path'] !== $path) {
                continue;
            }

            $handler = $route['handler'];
            if (is_array($handler)) {
                $controller = new $handler[0]();
                $methodName = $handler[1];
                $controller->$methodName();
                return;
            }

            $handler();
            return;
        }

        http_response_code(404);
        echo '404 Not Found';
    }
}