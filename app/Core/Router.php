<?php

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler)
    {
        $this->routes['GET'][] = [$path, $handler];
    }

    public function post(string $path, callable $handler)
    {
        $this->routes['POST'][] = [$path, $handler];
    }

    public function delete(string $path, callable $handler)
    {
        $this->routes['DELETE'][] = [$path, $handler];
    }

    public function dispatch()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if (!isset($this->routes[$method])) {
            Response::json(["error" => "Route not found"], 404);
        }

        foreach ($this->routes[$method] as [$route, $handler]) {

            // Convert /sections/{id} → regex
            $pattern = preg_replace('#\{[^/]+\}#', '([^/]+)', $route);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches); // remove full match
                call_user_func_array($handler, $matches);
                return;
            }
        }

        Response::json(["error" => "Route not found"], 404);
    }
}
