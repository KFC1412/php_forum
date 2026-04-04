<?php
/**
 * 处理主题收藏
 */

// 启动会话
session_start();

// 检查是否已安装
if (!file_exists(__DIR__ . '/config/config.php')) {
    header('Location: install/index.php');
    exit;
}

// 加载配置和函数
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/content_functions.php';

// 设置JSON响应头
header('Content-Type: application/json');

// 检查是否登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

// 获取参数
$topic_id = isset($_POST['topic_id']) ? (int)$_POST['topic_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($topic_id <= 0 || $action !== 'bookmark') {
    echo json_encode(['success' => false, 'message' => '无效的参数']);
    exit;
}

// 处理收藏
try {
    $result = bookmarkTopic($_SESSION['user_id'], $topic_id);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => '收藏成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '操作失败']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}