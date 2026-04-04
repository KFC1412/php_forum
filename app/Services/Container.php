<?php

namespace App\Services;

class Container {
    private $bindings = [];
    private $instances = [];
    
    public function bind($abstract, $concrete = null, $shared = false) {
        if (is_null($concrete)) {
            $concrete = $abstract;
        }
        
        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared
        ];
    }
    
    public function singleton($abstract, $concrete = null) {
        $this->bind($abstract, $concrete, true);
    }
    
    public function make($abstract, $parameters = []) {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }
        
        if (!isset($this->bindings[$abstract])) {
            if (class_exists($abstract)) {
                $this->bind($abstract);
            } else {
                throw new \Exception("Class {$abstract} not found");
            }
        }
        
        $binding = $this->bindings[$abstract];
        $concrete = $binding['concrete'];
        
        if ($concrete instanceof \Closure) {
            $instance = $concrete($this, $parameters);
        } else {
            $reflection = new \ReflectionClass($concrete);
            $constructor = $reflection->getConstructor();
            
            if (is_null($constructor)) {
                $instance = new $concrete();
            } else {
                $parameters = $this->resolveDependencies($constructor, $parameters);
                $instance = $reflection->newInstanceArgs($parameters);
            }
        }
        
        if ($binding['shared']) {
            $this->instances[$abstract] = $instance;
        }
        
        return $instance;
    }
    
    private function resolveDependencies($constructor, $parameters) {
        $dependencies = [];
        $params = $constructor->getParameters();
        
        foreach ($params as $param) {
            $type = $param->getType();
            if ($type && !$type->isBuiltin()) {
                $class = $type->getName();
                $dependencies[] = $this->make($class);
            } else {
                $name = $param->getName();
                $dependencies[] = $parameters[$name] ?? $param->getDefaultValue();
            }
        }
        
        return $dependencies;
    }
    
    public function instance($abstract, $instance) {
        $this->instances[$abstract] = $instance;
    }
    
    public function has($abstract) {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }
}