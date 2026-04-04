<?php
/**
 * 申诉提交处理
 */

// 启动会话
session_start();

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 检查是否已安装
if (!file_exists(__DIR__ . '/config/config.php')) {
    header('Location: install/index.php');
    exit;
}

// 加载配置和函数
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mail_functions.php';
require_once __DIR__ . '/includes/mail_functions_appeal.php';

// 检查安装状态和闭站模式
checkInstall();

// 检查是否已登录
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => '请先登录']));
}

// 处理申诉提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appeal_type = $_POST['appeal_type'] ?? '';
    $target_id = $_POST['target_id'] ?? 0;
    $reason = $_POST['reason'] ?? '';
    
    // 验证输入
    if (empty($appeal_type) || !in_array($appeal_type, ['user', 'topic'])) {
        die(json_encode(['success' => false, 'message' => '无效的申诉类型']));
    }
    
    if (empty($target_id) || !is_numeric($target_id)) {
        die(json_encode(['success' => false, 'message' => '无效的目标ID']));
    }
    
    if (empty(trim($reason))) {
        die(json_encode(['success' => false, 'message' => '请填写申诉原因']));
    }
    
    if (strlen(trim($reason)) < 20) {
        die(json_encode(['success' => false, 'message' => '申诉原因至少需要20个字符']));
    }
    
    // 验证目标是否存在且状态正确
    $db = getDB();
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    
    $target = null;
    switch ($appeal_type) {
        case 'user':
            // 检查用户是否被限制
            $target = $db->fetch("SELECT * FROM `{$prefix}users` WHERE `id` = :id AND `status` = 'restricted'", ['id' => $target_id]);
            break;
        case 'topic':
            // 检查主题是否被隐藏
            $target = $db->fetch("SELECT * FROM `{$prefix}topics` WHERE `id` = :id AND `status` = 'hidden'", ['id' => $target_id]);
            break;
    }
    
    if (!$target) {
        die(json_encode(['success' => false, 'message' => '申诉目标不存在或状态正常，无需申诉']));
    }
    
    // 检查是否是自己的主题或账号
    if ($appeal_type === 'user' && $target_id != $_SESSION['user_id']) {
        die(json_encode(['success' => false, 'message' => '只能申诉自己的账号']));
    }
    if ($appeal_type === 'topic' && $target['user_id'] != $_SESSION['user_id']) {
        die(json_encode(['success' => false, 'message' => '只能申诉自己发布的主题']));
    }
    
    // 检查是否已经提交过申诉
    $existing_appeal = $db->fetch(
        "SELECT * FROM `{$prefix}appeals` WHERE `user_id` = :user_id AND `appeal_type` = :appeal_type AND `target_id` = :target_id AND `status` = 'pending'",
        ['user_id' => $_SESSION['user_id'], 'appeal_type' => $appeal_type, 'target_id' => $target_id]
    );
    
    if ($existing_appeal) {
        die(json_encode(['success' => false, 'message' => '您已经提交过申诉，请等待管理员审核']));
    }
    
    // 保存申诉记录
    try {
        $result = $db->insert("{$prefix}appeals", [
            'user_id' => $_SESSION['user_id'],
            'appeal_type' => $appeal_type,
            'target_id' => $target_id,
            'reason' => trim($reason),
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if (!$result) {
            throw new Exception('保存申诉记录失败');
        }
        
        $appeal_id = $db->lastInsertId();
        
        // 记录申诉日志
        logAction('用户提交申诉', 'appeal', $appeal_id, [
            'user_id' => $_SESSION['user_id'],
            'appeal_type' => $appeal_type,
            'target_id' => $target_id,
            'reason' => trim($reason),
            'appeal_time' => date('Y-m-d H:i:s'),
            'appeal_ip' => getClientIp()
        ]);
        
        // 发送邮件通知管理员
        sendAppealNotificationToAdmin($appeal_id, $_SESSION['user_id'], $appeal_type, $target_id, $reason);
        
        die(json_encode(['success' => true, 'message' => '申诉提交成功，管理员会尽快审核']));
        
    } catch (Exception $e) {
        die(json_encode(['success' => false, 'message' => '提交失败: ' . $e->getMessage()]));
    }
} else {
    die(json_encode(['success' => false, 'message' => '无效的请求方式']));
}

