<?php

namespace App\Services;

class Router {
    private $routes = [];
    private $container;
    
    public function __construct(Container $container) {
        $this->container = $container;
    }
    
    public function get($path, $handler) {
        $this->addRoute('GET', $path, $handler);
    }
    
    public function post($path, $handler) {
        $this->addRoute('POST', $path, $handler);
    }
    
    public function put($path, $handler) {
        $this->addRoute('PUT', $path, $handler);
    }
    
    public function delete($path, $handler) {
        $this->addRoute('DELETE', $path, $handler);
    }
    
    public function any($path, $handler) {
        $this->addRoute('GET', $path, $handler);
        $this->addRoute('POST', $path, $handler);
        $this->addRoute('PUT', $path, $handler);
        $this->addRoute('DELETE', $path, $handler);
    }
    
    private function addRoute($method, $path, $handler) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }
    
    public function dispatch($uri, $method) {
        foreach ($this->routes as $route) {
            if ($route['method'] === $method) {
                $pattern = $this->pathToRegex($route['path']);
                if (preg_match($pattern, $uri, $matches)) {
                    array_shift($matches);
                    return $this->executeHandler($route['handler'], $matches);
                }
            }
        }
        
        return $this->handle404();
    }
    
    private function pathToRegex($path) {
        $path = preg_replace('/\{([^}]+)\}/', '(?P<\1>[^/]+)', $path);
        return '#^' . $path . '$#';
    }
    
    private function executeHandler($handler, $params) {
        if (is_callable($handler)) {
            return call_user_func_array($handler, $params);
        } elseif (is_array($handler)) {
            list($controller, $action) = $handler;
            $controllerInstance = $this->container->make($controller);
            return call_user_func_array([$controllerInstance, $action], $params);
        }
        
        return $this->handle500();
    }
    
    private function handle404() {
        http_response_code(404);
        return [
            'error' => '页面不存在',
            'view' => 'errors/404'
        ];
    }
    
    private function handle500() {
        http_response_code(500);
        return [
            'error' => '服务器内部错误',
            'view' => 'errors/500'
        ];
    }
}