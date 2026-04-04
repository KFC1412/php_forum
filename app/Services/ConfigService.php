<?php

namespace App\Services;

class ConfigService {
    private $config = [];
    
    public function __construct() {
        $this->loadEnv();
        $this->loadConfigFiles();
    }
    
    private function loadEnv() {
        $envFile = __DIR__ . '/../../.env';
        
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                if (!empty($key)) {
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                }
            }
        }
    }
    
    private function loadConfigFiles() {
        $configDir = __DIR__ . '/../../config';
        
        if (is_dir($configDir)) {
            $files = glob($configDir . '/*.php');
            
            foreach ($files as $file) {
                $key = basename($file, '.php');
                $this->config[$key] = require $file;
            }
        }
    }
    
    public function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $this->getEnv($key, $default);
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    public function getEnv($key, $default = null) {
        return getenv($key) ?: $_ENV[$key] ?? $default;
    }
    
    public function set($key, $value) {
        $keys = explode('.', $key);
        $config = &$this->config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
    }
    
    public function has($key) {
        return $this->get($key) !== null;
    }
    
    public function all() {
        return $this->config;
    }
}