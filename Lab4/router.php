<?php
declare(strict_types=1);

class Router {
    /**
    * @var array<int, array{
    *     method: string,
    *     path: string,
    *     handler: callable(): void|array{0: class-string, 1: string}
    * }>
    */
    private array $routes = [];

    /**
    * @param string $path
    * @param callable(): void|array{0: class-string, 1: string} $handler
    */
    public function get(string $path, callable $handler): void {
        $this->add_route('GET', $path, $handler);
    }

    /**
    * @param string $path
    * @param callable(): void|array{0: class-string, 1: string} $handler
    */
    public function post(string $path, callable $handler): void {
        $this->add_route('POST', $path, $handler);
    }

    /**
    * @param string $path
    * @param callable(): void|array{0: class-string, 1: string} $handler
    */
    private function add_route(string $method, string $path, callable $handler): void {
        $this->routes[] = [
            'method'  => $method,
            'path'    => $path,
            'handler' => $handler,
        ];
    }

    /**
    * @param string $method
    * @param string $path
    * @param callable(): void|array{0: class-string, 1: string} $handler
    */
    public function route(string $requestMethod, string $requestUri): void {
        $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';

        foreach ($this->routes as $route) {
            if ($route['method'] === $requestMethod && $route['path'] === $path) {
                call_user_func($route['handler']);
                return;
            }
        }

        http_response_code(404);
        echo "<h1>404 | Not Found</h1>";
    }
}
?>
