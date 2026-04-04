<?php
/**
 * 申诉相关邮件发送功能
 */

// 加载邮件发送函数
require_once __DIR__ . '/mail_functions.php';

/**
 * 发送申诉通知邮件给管理员
 * @param int $appeal_id 申诉ID
 * @param int $user_id 申诉用户ID
 * @param string $appeal_type 申诉类型
 * @param int $target_id 目标ID
 * @param string $reason 申诉原因
 * @return bool 是否发送成功
 */
function sendAppealNotificationToAdmin($appeal_id, $user_id, $appeal_type, $target_id, $reason) {
    $db = getDB();
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    
    // 获取申诉用户信息
    $user = $db->fetch("SELECT * FROM `{$prefix}users` WHERE `id` = :id", ['id' => $user_id]);
    if (!$user) return false;
    
    // 获取管理员列表
    $admins = $db->fetchAll("SELECT * FROM `{$prefix}users` WHERE `role` IN ('admin', 'moderator')");
    if (empty($admins)) return false;
    
    // 构建邮件内容
    $appeal_type_text = $appeal_type === 'user' ? '账号限制' : '主题隐藏';
    $site_name = getSetting('site_name', 'PHP轻论坛');
    $site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $subject = '【申诉通知】有新的申诉需要处理 - ' . $site_name;
    
    $body = "<h2>新的申诉通知</h2>";
    $body .= "<p><strong>申诉ID：</strong>{$appeal_id}</p>";
    $body .= "<p><strong>申诉类型：</strong>{$appeal_type_text}</p>";
    $body .= "<p><strong>申诉用户：</strong>" . htmlspecialchars($user['username']) . " (ID: {$user_id})</p>";
    $body .= "<p><strong>目标ID：</strong>{$target_id}</p>";
    $body .= "<p><strong>申诉原因：</strong></p>";
    $body .= "<blockquote style='border-left: 3px solid #4A90E2; padding-left: 15px; margin: 15px 0; color: #555; background-color: #f9f9f9; padding: 10px 15px;'>" . nl2br(htmlspecialchars($reason)) . "</blockquote>";
    $body .= "<p><strong>申诉时间：</strong>" . date('Y-m-d H:i:s') . "</p>";
    $body .= "<hr>";
    $body .= "<p>请登录后台管理系统处理此申诉：</p>";
    $body .= "<p><a href='{$site_url}/admin/reports.php?tab=appeals' style='padding: 10px 20px; background-color: #4A90E2; color: white; text-decoration: none; border-radius: 5px;'>前往处理</a></p>";
    
    // 发送邮件给所有管理员
    $success = true;
    foreach ($admins as $admin) {
        if (!empty($admin['email'])) {
            $result = sendMail($admin['email'], $subject, $body, 'appeal');
            if (!$result) {
                $success = false;
            }
        }
    }
    
    return $success;
}

/**
 * 发送申诉结果通知邮件给用户
 * @param int $appeal_id 申诉ID
 * @param string $result 审核结果 (approved/rejected)
 * @param string $process_note 处理备注
 * @return bool 是否发送成功
 */
function sendAppealResultNotification($appeal_id, $result, $process_note = '') {
    $db = getDB();
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    
    // 获取申诉信息
    $appeal = $db->fetch("SELECT * FROM `{$prefix}appeals` WHERE `id` = :id", ['id' => $appeal_id]);
    if (!$appeal) return false;
    
    // 获取用户信息
    $user = $db->fetch("SELECT * FROM `{$prefix}users` WHERE `id` = :id", ['id' => $appeal['user_id']]);
    if (!$user || empty($user['email'])) return false;
    
    // 构建邮件内容
    $appeal_type_text = $appeal['appeal_type'] === 'user' ? '账号限制' : '主题隐藏';
    $result_text = $result === 'approved' ? '已通过' : '已驳回';
    $result_color = $result === 'approved' ? '#4CAF50' : '#f44336';
    $site_name = getSetting('site_name', 'PHP轻论坛');
    $site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $subject = "【申诉结果】您的申诉{$result_text} - {$site_name}";
    
    $body = "<h2>申诉结果通知</h2>";
    $body .= "<p>您好，" . htmlspecialchars($user['username']) . "：</p>";
    $body .= "<p>您提交的<strong>{$appeal_type_text}</strong>申诉（ID: {$appeal_id}）已有审核结果。</p>";
    $body .= "<div style='background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    $body .= "<p><strong>审核结果：</strong><span style='color: {$result_color}; font-weight: bold;'>{$result_text}</span></p>";
    if (!empty($process_note)) {
        $body .= "<p><strong>处理备注：</strong></p>";
        $body .= "<blockquote style='border-left: 3px solid #4A90E2; padding-left: 15px; margin: 15px 0; color: #555; background-color: #f9f9f9; padding: 10px 15px;'>" . nl2br(htmlspecialchars($process_note)) . "</blockquote>";
    }
    $body .= "</div>";
    
    if ($result === 'approved') {
        $body .= "<p>您的{$appeal_type_text}已被解除，现在可以正常使用相关功能。</p>";
    } else {
        $body .= "<p>很抱歉，您的申诉未被通过。如有疑问，请联系管理员。</p>";
    }
    
    $body .= "<p style='margin-top: 20px;'><a href='{$site_url}' style='padding: 10px 20px; background-color: #4A90E2; color: white; text-decoration: none; border-radius: 5px;'>访问网站</a></p>";
    
    return sendMail($user['email'], $subject, $body, 'appeal');
}
