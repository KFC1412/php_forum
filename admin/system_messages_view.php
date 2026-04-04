<?php
date_default_timezone_set('Asia/Shanghai');
/**
 * 后台消息管理 - 查看与用户的对话
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!file_exists(__DIR__ . '/../config/config.php')) {
    header('Location: ../install/index.php');
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/system_account_functions.php';
require_once __DIR__ . '/includes/admin_functions.php';

checkAdminAccess();

$user_id = $_GET['view_conversation'] ?? 0;
$error = '';
$success = '';

if (empty($user_id)) {
    header('Location: system_messages.php');
    exit;
}

// 处理回复消息
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $content = $_POST['content'] ?? '';
    
    if (empty(trim($content))) {
        $error = '请填写回复内容';
    } else {
        try {
            $db = getDB();
            $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
            
            // 确保系统账户存在
            $systemUserId = 'system';
            ensureCoreSystemAccounts();
            
            // 发送回复
            $db->insert("{$prefix}messages", [
                'sender_id' => $systemUserId,
                'receiver_id' => $user_id,
                'content' => trim($content),
                'status' => 'unread',
                'ip' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $success = '回复已发送';
            
            // 标记该用户的所有消息为已读
            $db->update("{$prefix}messages", 
                ['status' => 'read'],
                "sender_id = :user_id AND receiver_id = :system_id",
                ['user_id' => (string)$user_id, 'system_id' => (string)$systemUserId]
            );
            
            // 记录操作日志
            logAdminAction('管理员回复系统消息', 'system', $user_id, [
                'content_length' => mb_strlen($content),
                'admin_id' => $_SESSION['user_id'],
                'admin_username' => $_SESSION['username'],
                'action_time' => date('Y-m-d H:i:s'),
                'ip_address' => getClientIp()
            ]);
            
        } catch (Exception $e) {
            $error = '发送失败: ' . $e->getMessage();
        }
    }
}

// 获取系统用户ID（固定为'system'）
$systemUserId = 'system';

// 确保系统用户存在
$systemUser = null;
try {
    $db = getDB();
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    $systemUser = $db->fetch(
        "SELECT id FROM `{$prefix}users` WHERE `id` = ?",
        [$systemUserId]
    );
    if (!$systemUser) {
        // 尝试插入系统用户，使用特殊ID
        try {
            // 对于MySQL，直接指定ID
            if (getDBType() === 'mysql') {
                $db->query(
                    "INSERT INTO `{$prefix}users` (`id`, `username`, `password`, `email`, `status`, `role`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$systemUserId, '【系统通知】', password_hash('system_user_' . time(), PASSWORD_DEFAULT), 'system@localhost', 'active', 'system', date('Y-m-d H:i:s')]
                );
            } else {
                // 对于其他数据库，使用普通插入
                $db->insert("{$prefix}users", [
                    'id' => $systemUserId,
                    'username' => '【系统通知】',
                    'password' => password_hash('system_user_' . time(), PASSWORD_DEFAULT),
                    'email' => 'system@localhost',
                    'status' => 'active',
                    'role' => 'system',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            $systemUser = ['id' => $systemUserId];
        } catch (Exception $e) {
            // 忽略插入错误，可能是因为已经存在
        }
    }
} catch (Exception $e) {
    $error .= ' 获取系统用户失败: ' . $e->getMessage();
}

// 获取对话用户信息
$targetUser = null;
if (true) {
    try {
        $targetUser = $db->fetch(
            "SELECT id, username, email FROM `{$prefix}users` WHERE id = :user_id",
            ['user_id' => (string)$user_id]
        );
    } catch (Exception $e) {
        $error .= ' 获取用户信息失败: ' . $e->getMessage();
    }
}

// 获取对话消息
$conversation = [];
if ($targetUser) {
    try {
        $allMessages = $db->fetchAll(
            "SELECT * FROM `{$prefix}messages` WHERE 
            (sender_id = :system_id AND receiver_id = :user_id) OR 
            (sender_id = :user_id AND receiver_id = :system_id) 
            ORDER BY created_at ASC",
            ['system_id' => (string)$systemUserId, 'user_id' => (string)$user_id]
        );
        
        // 标记系统发送的消息为已读
        foreach ($allMessages as $msg) {
            if ($msg['sender_id'] == $user_id && $msg['status'] == 'unread') {
                $db->update("{$prefix}messages", 
                    ['status' => 'read'],
                    "id = :msg_id",
                    ['msg_id' => $msg['id']]
                );
            }
        }
        
        $conversation = $allMessages;
    } catch (Exception $e) {
        $error .= ' 获取对话失败: ' . $e->getMessage();
    }
}

$page_title = '查看对话 - ' . ($targetUser ? htmlspecialchars($targetUser['username']) : '未知用户');
include __DIR__ . '/templates/admin_header.php';
?>

<table border="1" width="100%" cellspacing="0" cellpadding="10">
    <tr>
        <td width="200" valign="top">
            <?php include __DIR__ . '/templates/admin_sidebar.php'; ?>
        </td>
        
        <td valign="top">
            <table width="100%" cellspacing="0" cellpadding="5">
                <tr>
                    <td colspan="2">
                        <h1><?php echo $page_title; ?></h1>
                        <a href="system_messages.php" style="display: inline-block; margin-bottom: 10px; padding: 5px 15px; background-color: #666; color: white; text-decoration: none; border-radius: 3px;">返回列表</a>
                    </td>
                </tr>
                
                <?php if (!empty($error)): ?>
                    <tr>
                        <td colspan="2" class="error"><?php echo $error; ?></td>
                    </tr>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <tr>
                        <td colspan="2" class="success"><?php echo $success; ?></td>
                    </tr>
                <?php endif; ?>
                
                <?php if ($targetUser): ?>
                    <tr>
                        <td colspan="2">
                            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                <tr>
                                    <td colspan="2"><h5>对话详情</h5></td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="background-color: #f5f5f5; padding: 10px;">
                                        <strong>用户：</strong><?php echo htmlspecialchars($targetUser['username']); ?> (ID: <?php echo $targetUser['id']; ?>)
                                        <?php if (!empty($targetUser['email'])): ?>
                                            - <?php echo htmlspecialchars($targetUser['email']); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <td colspan="2" style="height: 400px; overflow-y: auto; padding: 10px; background-color: #f9f9f9;">
                                    <?php if (count($conversation) > 0): ?>
                                        <?php foreach ($conversation as $msg): ?>
                                            <div style="margin-bottom: 15px; <?php echo $msg['sender_id'] == $systemUserId ? 'text-align: right;' : ''; ?>">
                                                <div style="display: inline-block; max-width: 70%; padding: 10px; border-radius: 10px; <?php echo $msg['sender_id'] == $systemUserId ? 'background-color: #4A90E2; color: white;' : 'background-color: #e9e9e9; color: #333;'; ?>">
                                                    <div style="margin: 0; word-wrap: break-word;">
                                                        <?php 
                                                        $content = htmlspecialchars($msg['content']);
                                                        $content = preg_replace('/\[img\](.*?)\[\/img\]/', '<img src="$1" style="max-width: 200px; max-height: 150px; border-radius: 5px; margin: 5px 0; display: block; cursor: pointer; object-fit: cover;" onclick="window.open(this.src)" title="点击查看大图">', $content);
                                                        echo nl2br($content); 
                                                        ?>
                                                    </div>
                                                    <small style="display: block; margin-top: 5px; opacity: 0.8;">
                                                        <?php echo formatDateTime($msg['created_at']); ?>
                                                        <?php if (!empty($msg['ip']) && $msg['sender_id'] != $systemUserId): ?>
                                                            <span style="margin-left: 10px; font-size: 11px;"><?php echo getIPLocation($msg['ip']); ?></span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div style="text-align: center; color: #999; margin-top: 50px;">
                                            暂无对话记录
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <tr>
                                <td colspan="2">
                                    <form method="post" action="">
                                        <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                            <tr>
                                                <td width="20%">回复内容</td>
                                                <td>
                                                    <textarea name="content" rows="4" style="width: 100%; padding: 10px; resize: vertical;" placeholder="请输入回复内容..." required></textarea>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="2" align="center">
                                                    <button type="submit" name="send_reply">发送回复</button>
                                                </td>
                                            </tr>
                                        </table>
                                    </form>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="2" align="center">用户不存在</td>
                    </tr>
                <?php endif; ?>
            </table>
        </td>
    </tr>
</table>

<?php
include __DIR__ . '/templates/admin_footer.php';
?>