<?php

namespace App\Services;

class CacheService {
    private $cacheDir;
    
    public function __construct() {
        $this->cacheDir = __DIR__ . '/../../storage/cache';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public function get($key) {
        $cacheFile = $this->getCacheFile($key);
        
        if (file_exists($cacheFile)) {
            $content = file_get_contents($cacheFile);
            $data = json_decode($content, true);
            
            if (isset($data['expire']) && $data['expire'] > time()) {
                return $data['data'];
            } else {
                $this->delete($key);
            }
        }
        
        return false;
    }
    
    public function set($key, $data, $expire = 3600) {
        $cacheFile = $this->getCacheFile($key);
        $content = json_encode([
            'data' => $data,
            'expire' => time() + $expire
        ]);
        
        return file_put_contents($cacheFile, $content);
    }
    
    public function delete($key) {
        $cacheFile = $this->getCacheFile($key);
        
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        
        return false;
    }
    
    public function clear() {
        $files = glob($this->cacheDir . '/*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        return true;
    }
    
    public function has($key) {
        $cacheFile = $this->getCacheFile($key);
        
        if (file_exists($cacheFile)) {
            $content = file_get_contents($cacheFile);
            $data = json_decode($content, true);
            
            return isset($data['expire']) && $data['expire'] > time();
        }
        
        return false;
    }
    
    private function getCacheFile($key) {
        $key = md5($key);
        return $this->cacheDir . '/' . $key . '.json';
    }
}