<?php

namespace App\Storage;

use App\Storage\JsonStorage;
use App\Storage\DatabaseStorage;

class StorageFactory {
    public static function create($type = null) {
        $config_file = __DIR__ . '/../../config/config.php';
        
        if ($type === null) {
            if (file_exists($config_file)) {
                require_once $config_file;
                $type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
            } else {
                $type = isset($_SESSION['storage_type']) ? $_SESSION['storage_type'] : 'mysql';
            }
        }
        
        switch (strtolower($type)) {
            case 'json':
            case 'file':
                return JsonStorage::getInstance();
            
            case 'mysql':
            case 'database':
            default:
                return DatabaseStorage::getInstance();
        }
    }
    
    public static function getAvailableDrivers() {
        return [
            'mysql' => [
                'name' => 'MySQL/MariaDB',
                'description' => '传统关系型数据库，适合大型论坛',
                'icon' => 'database',
                'requirements' => [
                    'PDO MySQL扩展',
                    'MySQL 5.7+ 或 MariaDB 10.2+'
                ]
            ],
            'json' => [
                'name' => 'JSON文件存储',
                'description' => '轻量级文件存储，无需数据库，适合小型论坛',
                'icon' => 'file-text',
                'requirements' => [
                    'PHP 7.0+',
                    '可写目录权限'
                ]
            ]
        ];
    }
}