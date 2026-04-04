<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\Container;
use App\Services\ConfigService;
use App\Storage\StorageFactory;
use App\Services\CacheService;

// 初始化容器
$container = new Container();

// 绑定核心服务
$container->singleton('container', function () use ($container) {
    return $container;
});

// 绑定配置服务
$container->singleton('config', function () {
    return new ConfigService();
});

// 绑定存储服务
$container->singleton('storage', function ($container) {
    $config = $container->make('config');
    $storageType = $config->get('STORAGE_TYPE', 'mysql');
    return StorageFactory::create($storageType);
});

// 绑定缓存服务
$container->singleton('cache', function () {
    return new CacheService();
});

// 绑定密码服务
$container->singleton('password', function () {
    return new App\Services\PasswordService();
});

// 绑定邮件服务
$container->singleton('mail', function () {
    return new App\Services\MailService();
});

// 绑定日志服务
$container->bind('logger', function () {
    return function ($message, $level = 'info') {
        error_log("[$level] $message");
    };
});

// 存储容器到全局变量
$GLOBALS['container'] = $container;

return $container;