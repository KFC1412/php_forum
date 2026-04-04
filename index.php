<?php

// 加载bootstrap文件
$container = require_once __DIR__ . '/bootstrap/app.php';

// 加载路由
require_once __DIR__ . '/app/Routes/web.php';

// 处理请求
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 移除查询字符串
$uri = explode('?', $uri)[0];

// 确保URI以斜杠开头
if (substr($uri, 0, 1) !== '/') {
    $uri = '/' . $uri;
}

// 路由分发
$router = $GLOBALS['router'];
$result = $router->dispatch($uri, $method);

// 处理响应
if (isset($result['view'])) {
    // 渲染视图
    $viewFile = __DIR__ . '/app/Views/' . $result['view'] . '.php';
    if (file_exists($viewFile)) {
        extract($result);
        require $viewFile;
    } else {
        echo "View file not found: {$result['view']}";
    }
} elseif (isset($result['redirect'])) {
    // 重定向
    header('Location: ' . $result['redirect']);
    exit;
} elseif (isset($result['error'])) {
    // 错误信息
    echo $result['error'];
} else {
    // JSON响应
    header('Content-Type: application/json');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}