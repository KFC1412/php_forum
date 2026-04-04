<?php
/**
 * 举报提交处理
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

// 检查安装状态和闭站模式
checkInstall();

// 检查是否已登录
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => '请先登录']));
}

// 处理举报提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_type = $_POST['report_type'] ?? '';
    $target_id = $_POST['target_id'] ?? 0;
    $reason = $_POST['reason'] ?? '';
    
    // 验证输入
    if (empty($report_type) || !in_array($report_type, ['user', 'topic', 'post'])) {
        die(json_encode(['success' => false, 'message' => '无效的举报类型']));
    }
    
    if (empty($target_id) || !is_numeric($target_id)) {
        die(json_encode(['success' => false, 'message' => '无效的目标ID']));
    }
    
    if (empty(trim($reason))) {
        die(json_encode(['success' => false, 'message' => '请填写举报原因']));
    }
    
    if (strlen(trim($reason)) < 10) {
        die(json_encode(['success' => false, 'message' => '举报原因至少需要10个字符']));
    }
    
    // 验证目标是否存在
    $db = getDB();
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    
    switch ($report_type) {
        case 'user':
            $target = $db->fetch("SELECT * FROM `{$prefix}users` WHERE `id` = :id", ['id' => $target_id]);
            break;
        case 'topic':
            $target = $db->fetch("SELECT * FROM `{$prefix}topics` WHERE `id` = :id AND `status` = 'published'", ['id' => $target_id]);
            break;
        case 'post':
            $target = $db->fetch("SELECT * FROM `{$prefix}posts` WHERE `id` = :id AND `status` = 'published'", ['id' => $target_id]);
            break;
        default:
            $target = null;
    }
    
    if (!$target) {
        die(json_encode(['success' => false, 'message' => '举报目标不存在']));
    }
    
    // 检查是否已经举报过
    $existing_report = $db->fetch(
        "SELECT * FROM `{$prefix}reports` WHERE `reporter_id` = :reporter_id AND `report_type` = :report_type AND `target_id` = :target_id",
        ['reporter_id' => $_SESSION['user_id'], 'report_type' => $report_type, 'target_id' => $target_id]
    );
    
    if ($existing_report) {
        die(json_encode(['success' => false, 'message' => '您已经举报过该内容']));
    }
    
    // 保存举报记录
    try {
        $result = $db->insert("{$prefix}reports", [
            'reporter_id' => $_SESSION['user_id'],
            'report_type' => $report_type,
            'target_id' => $target_id,
            'reason' => trim($reason),
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if (!$result) {
            throw new Exception('保存举报记录失败');
        }
        
        // 记录举报日志
        logAction('用户提交举报', 'report', $db->lastInsertId(), [
            'reporter_id' => $_SESSION['user_id'],
            'report_type' => $report_type,
            'target_id' => $target_id,
            'reason' => trim($reason),
            'report_time' => date('Y-m-d H:i:s'),
            'report_ip' => getClientIp()
        ]);
        
        // 检查举报数量是否达到自动处理阈值
        $ban_threshold = (int)getSetting('report_ban_threshold', 10);
        $report_count = getReportCount($report_type, $target_id);
        
        if ($report_count >= $ban_threshold) {
            if ($report_type === 'user') {
                // 用户达到封禁阈值：禁用发布功能，设置状态为 restricted
                $db->update("{$prefix}users", [
                    'status' => 'restricted',
                    'can_post_topic' => 0,
                    'can_post_reply' => 0
                ], '`id` = :id', ['id' => $target_id]);
                
                // 如果被限制的用户就是当前登录用户，更新会话中的状态信息
                if ($target_id == $_SESSION['user_id']) {
                    $_SESSION['status'] = 'restricted';
                }
                
                // 记录自动处理日志
                logAction('用户被自动限制', 'user', $target_id, [
                    'reason' => '举报数量达到封禁阈值',
                    'report_count' => $report_count,
                    'threshold' => $ban_threshold
                ]);
            } elseif ($report_type === 'topic') {
                // 主题达到封禁阈值：自动隐藏主题
                $db->update("{$prefix}topics", [
                    'status' => 'hidden',
                    'hidden_reason' => '举报数量达到封禁阈值',
                    'hidden_at' => date('Y-m-d H:i:s')
                ], '`id` = :id', ['id' => $target_id]);
                
                // 记录自动处理日志
                logAction('主题被自动隐藏', 'topic', $target_id, [
                    'reason' => '举报数量达到封禁阈值',
                    'report_count' => $report_count,
                    'threshold' => $ban_threshold
                ]);
            }
        }
        
        die(json_encode(['success' => true, 'message' => '举报成功']));
        
    } catch (Exception $e) {
        die(json_encode(['success' => false, 'message' => '提交失败: ' . $e->getMessage()]));
    }
} else {
    die(json_encode(['success' => false, 'message' => '无效的请求方式']));
}
?>