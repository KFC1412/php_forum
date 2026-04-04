<?php

/**
 * 缓存类
 */
class Cache {
    private static $instance = null;
    private $cache_dir;
    
    private function __construct() {
        $this->cache_dir = __DIR__ . '/../storage/cache/';
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 设置缓存
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $expire 过期时间（秒）
     * @return bool
     */
    public function set($key, $value, $expire = 3600) {
        $cache_file = $this->getCacheFile($key);
        $data = [
            'value' => $value,
            'expire' => time() + $expire
        ];
        return file_put_contents($cache_file, json_encode($data));
    }
    
    /**
     * 获取缓存
     * @param string $key 缓存键
     * @return mixed
     */
    public function get($key) {
        $cache_file = $this->getCacheFile($key);
        if (!file_exists($cache_file)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($cache_file), true);
        if (time() > $data['expire']) {
            unlink($cache_file);
            return false;
        }
        
        return $data['value'];
    }
    
    /**
     * 删除缓存
     * @param string $key 缓存键
     * @return bool
     */
    public function delete($key) {
        $cache_file = $this->getCacheFile($key);
        if (file_exists($cache_file)) {
            return unlink($cache_file);
        }
        return true;
    }
    
    /**
     * 清空所有缓存
     * @return bool
     */
    public function clear() {
        $files = glob($this->cache_dir . '*.json');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }
    
    /**
     * 获取缓存文件路径
     * @param string $key 缓存键
     * @return string
     */
    private function getCacheFile($key) {
        $key = md5($key);
        return $this->cache_dir . $key . '.json';
    }
}

function getCache() {
    return Cache::getInstance();
}
?>