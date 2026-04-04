<?php

// 简单的自动加载器
function autoload($className) {
    // 将命名空间转换为文件路径
    $classPath = str_replace('\\', '/', $className);
    $filePath = __DIR__ . '/../' . $classPath . '.php';
    
    if (file_exists($filePath)) {
        require_once $filePath;
    }
}

// 注册自动加载器
spl_autoload_register('autoload');

// 定义一些常量
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__DIR__));
