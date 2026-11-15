<?php
declare(strict_types=1);

class Router {

    private array $routes = [];
    /**
     * @param callable(): mixed $handler
     */
    public function get(string $path, callable $handler): void {
        $this->routes['GET'][$path] = $handler;
    }
    /**
     * @param callable(): mixed $handler
     */
    public function post(string $path, callable $handler): void {
        $this->routes['POST'][$path] = $handler;
    }

    public function route(string $method, string $uri): void {
        $path    = (string)(parse_url($uri, PHP_URL_PATH) ?: '/');
        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            http_response_code(404);
            echo '<h1>404 | Not Found</h1>';
            return;
        }
        $handler();
    }
}
