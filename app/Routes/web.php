<?php

use App\Services\Router;
use App\Controllers\UserController;
use App\Controllers\ContentController;

// 加载bootstrap文件
require_once __DIR__ . '/../../bootstrap/app.php';

// 获取容器
$container = $GLOBALS['container'] ?? null;

// 确保容器存在
if (!$container) {
    die('Container not initialized');
}

// 创建路由实例
$router = new Router($container);

// 首页
$router->get('/', function() {
    $contentController = $GLOBALS['container']->make(ContentController::class);
    return $contentController->index([]);
});

// 用户相关路由
$router->get('/login', [UserController::class, 'login']);
$router->post('/login', [UserController::class, 'login']);
$router->get('/register', [UserController::class, 'register']);
$router->post('/register', [UserController::class, 'register']);
$router->get('/logout', [UserController::class, 'logout']);
$router->get('/profile', [UserController::class, 'profile']);
$router->post('/profile', [UserController::class, 'profile']);
$router->get('/user/{id}', [UserController::class, 'show']);

// 内容相关路由
$router->get('/topic/create', [ContentController::class, 'createTopic']);
$router->post('/topic/create', [ContentController::class, 'createTopic']);
$router->get('/topic/{id}', [ContentController::class, 'showTopic']);
$router->post('/topic/{id}/reply', [ContentController::class, 'replyTopic']);
$router->get('/topic/{id}/edit', [ContentController::class, 'editTopic']);
$router->post('/topic/{id}/edit', [ContentController::class, 'editTopic']);
$router->get('/topic/{id}/delete', [ContentController::class, 'deleteTopic']);
$router->get('/post/{id}/edit', [ContentController::class, 'editPost']);
$router->post('/post/{id}/edit', [ContentController::class, 'editPost']);
$router->get('/post/{id}/delete', [ContentController::class, 'deletePost']);
$router->get('/60s', [ContentController::class, 'dailyNews']);

// 社交相关路由
$router->get('/messages', [App\Controllers\SocialController::class, 'messages']);
$router->get('/message/send', [App\Controllers\SocialController::class, 'sendMessage']);
$router->post('/message/send', [App\Controllers\SocialController::class, 'sendMessage']);
$router->get('/message/thread/{id}', [App\Controllers\SocialController::class, 'messageThread']);
$router->post('/message/thread/{id}', [App\Controllers\SocialController::class, 'messageThread']);
$router->get('/notifications', [App\Controllers\SocialController::class, 'notifications']);
$router->get('/notification/{id}/read', [App\Controllers\SocialController::class, 'markNotificationAsRead']);
$router->get('/notification/{id}/delete', [App\Controllers\SocialController::class, 'deleteNotification']);
$router->get('/interaction-messages', [App\Controllers\SocialController::class, 'interactionMessages']);

// API路由
$router->get('/api/users', function() {
    // API实现
    return [
        'users' => []
    ];
});

$router->get('/api/topics', function() {
    // API实现
    return [
        'topics' => []
    ];
});

$router->get('/api/posts', function() {
    // API实现
    return [
        'posts' => []
    ];
});

$router->get('/api/messages', function() {
    // API实现
    return [
        'messages' => []
    ];
});

$router->get('/api/notifications', function() {
    // API实现
    return [
        'notifications' => []
    ];
});

// 管理后台路由
$router->get('/admin', [App\Controllers\Admin\AdminController::class, 'dashboard']);
$router->get('/admin/users', [App\Controllers\Admin\AdminController::class, 'users']);
$router->get('/admin/user/{id}/edit', [App\Controllers\Admin\AdminController::class, 'editUser']);
$router->post('/admin/user/{id}/edit', [App\Controllers\Admin\AdminController::class, 'editUser']);
$router->get('/admin/user/{id}/delete', [App\Controllers\Admin\AdminController::class, 'deleteUser']);
$router->get('/admin/topics', [App\Controllers\Admin\AdminController::class, 'topics']);
$router->get('/admin/topic/{id}/delete', [App\Controllers\Admin\AdminController::class, 'deleteTopic']);
$router->get('/admin/posts', [App\Controllers\Admin\AdminController::class, 'posts']);
$router->get('/admin/post/{id}/delete', [App\Controllers\Admin\AdminController::class, 'deletePost']);
$router->get('/admin/settings', [App\Controllers\Admin\AdminController::class, 'settings']);

// 全局变量，供index.php使用
$GLOBALS['router'] = $router;