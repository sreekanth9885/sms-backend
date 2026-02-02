<?php

class Router
{
    private array $routes = [];

    public function post(string $path, callable $handler)
    {
        $this->routes['POST'][$path] = $handler;
    }
    public function get(string $path, callable $handler)
    {
        $this->routes['GET'][$path] = $handler;
    }
    
    public function dispatch()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if (!isset($this->routes[$method][$uri])) {
            Response::json(["error" => "Route not found"], 404);
        }

        call_user_func($this->routes[$method][$uri]);
    }
}
