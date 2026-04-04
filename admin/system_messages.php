<?php
date_default_timezone_set('Asia/Shanghai');
/**
 * 后台消息管理 - 查看【系统】消息及用户回复
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
require_once __DIR__ . '/../includes/mail_functions.php';
require_once __DIR__ . '/includes/admin_functions.php';

checkAdminAccess();

$error = '';
$success = '';

// 处理回复消息
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $receiver_id = $_POST['receiver_id'] ?? 0;
    $content = $_POST['content'] ?? '';
    
    if (empty($receiver_id) || empty(trim($content))) {
        $error = '请填写完整信息';
    } else {
        try {
            $db = getDB();
            $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
            $dbType = getDBType();
            
            // 确保系统用户存在（使用特殊ID 'system'）
            $systemUserId = 'system';
            
            // 根据数据库类型选择查询方式
            if ($dbType === 'mysql') {
                // MySQL使用?占位符
                $systemUser = $db->fetch(
                    "SELECT id FROM `{$prefix}users` WHERE `id` = ?",
                    [$systemUserId]
                );
            } else {
                // JSON存储使用命名参数
                $systemUser = $db->fetch(
                    "SELECT id FROM `{$prefix}users` WHERE `id` = :id",
                    ['id' => $systemUserId]
                );
            }
            
            if (!$systemUser) {
                // 尝试插入系统用户，使用特殊ID
                try {
                    // 对于MySQL，直接指定ID
                    if ($dbType === 'mysql') {
                        $db->query(
                            "INSERT INTO `{$prefix}users` (`id`, `username`, `password`, `email`, `status`, `role`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?)",
                            [$systemUserId, '【系统通知】', password_hash('system_user_' . time(), PASSWORD_DEFAULT), 'system@localhost', 'active', 'system', date('Y-m-d H:i:s')]
                        );
                    } else {
                        // 对于JSON存储，使用普通插入
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
                } catch (Exception $e) {
                    // 忽略插入错误，可能是因为已经存在
                }
            }
            
            // 发送回复
            $db->insert("{$prefix}messages", [
                'sender_id' => $systemUserId,
                'receiver_id' => $receiver_id,
                'content' => trim($content),
                'status' => 'unread',
                'ip' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $success = '回复已发送';
            
            // 记录操作日志
            logAdminAction('管理员回复系统消息', 'system', $receiver_id, [
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

// 处理查看对话
$view_conversation = $_GET['view_conversation'] ?? '';
$conversation_messages = [];
$conversation_user = null;

if ($view_conversation) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        $dbType = getDBType();
        
        // 获取用户信息 - 根据数据库类型选择查询方式
        if ($dbType === 'mysql') {
            // MySQL使用?占位符
            $conversation_user = $db->fetch(
                "SELECT id, username, email FROM `{$prefix}users` WHERE id = ?",
                [(string)$view_conversation]
            );
        } else {
            // JSON存储使用命名参数
            $conversation_user = $db->fetch(
                "SELECT id, username, email FROM `{$prefix}users` WHERE id = :id",
                ['id' => (string)$view_conversation]
            );
        }
        
        if (!$conversation_user) {
            $error = '用户不存在';
        } else {
            // 获取对话消息 - 根据数据库类型选择查询方式
            if ($dbType === 'mysql') {
                // MySQL使用?占位符
                $conversation_messages = $db->fetchAll(
                    "SELECT * FROM `{$prefix}messages` WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC",
                    [$systemUserId, (string)$view_conversation, (string)$view_conversation, $systemUserId]
                );
                
                // 标记消息为已读
                $db->query(
                    "UPDATE `{$prefix}messages` SET status = 'read' WHERE receiver_id = ? AND sender_id = ? AND status = 'unread'",
                    [(string)$systemUserId, (string)$view_conversation]
                );
            } else {
                // JSON存储使用命名参数
                $conversation_messages = $db->fetchAll(
                    "SELECT * FROM `{$prefix}messages` WHERE (sender_id = :system_id1 AND receiver_id = :user_id1) OR (sender_id = :user_id2 AND receiver_id = :system_id2) ORDER BY created_at ASC",
                    [
                        'system_id1' => $systemUserId,
                        'user_id1' => (string)$view_conversation,
                        'user_id2' => (string)$view_conversation,
                        'system_id2' => $systemUserId
                    ]
                );
                
                // 标记消息为已读
                $db->query(
                    "UPDATE `{$prefix}messages` SET status = 'read' WHERE receiver_id = :receiver_id AND sender_id = :sender_id AND status = 'unread'",
                    [
                        'receiver_id' => (string)$systemUserId,
                        'sender_id' => (string)$view_conversation
                    ]
                );
            }
        }
        
    } catch (Exception $e) {
        $error .= ' 获取对话消息失败: ' . $e->getMessage();
    }
}

// 确保系统用户存在
$systemUser = null;
try {
    $db = getDB();
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    $dbType = getDBType();
    
    // 根据数据库类型选择查询方式
    if ($dbType === 'mysql') {
        // MySQL使用?占位符
        $systemUser = $db->fetch(
            "SELECT id FROM `{$prefix}users` WHERE `id` = ?",
            [$systemUserId]
        );
    } else {
        // JSON存储使用命名参数
        $systemUser = $db->fetch(
            "SELECT id FROM `{$prefix}users` WHERE `id` = :id",
            ['id' => $systemUserId]
        );
    }
    
    if (!$systemUser) {
        // 尝试插入系统用户，使用特殊ID
        try {
            // 对于MySQL，直接指定ID
            if ($dbType === 'mysql') {
                $db->query(
                    "INSERT INTO `{$prefix}users` (`id`, `username`, `password`, `email`, `status`, `role`, `created_at`) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$systemUserId, '【系统通知】', password_hash('system_user_' . time(), PASSWORD_DEFAULT), 'system@localhost', 'active', 'system', date('Y-m-d H:i:s')]
                );
            } else {
                // 对于JSON存储，使用普通插入
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

// 获取所有与系统用户相关的消息
$systemMessages = [];
try {
    // 根据数据库类型选择查询方式
    if ($dbType === 'mysql') {
        // MySQL使用?占位符
        $allMessages = $db->fetchAll(
            "SELECT * FROM `{$prefix}messages` WHERE sender_id = ? OR receiver_id = ? ORDER BY created_at DESC",
            [(string)$systemUserId, (string)$systemUserId]
        );
    } else {
        // JSON存储使用命名参数
        $allMessages = $db->fetchAll(
            "SELECT * FROM `{$prefix}messages` WHERE sender_id = :sender_id OR receiver_id = :receiver_id ORDER BY created_at DESC",
            ['sender_id' => (string)$systemUserId, 'receiver_id' => (string)$systemUserId]
        );
    }
    
    // 按对话分组
    $conversations = [];
    foreach ($allMessages as $msg) {
        $other_id = ($msg['sender_id'] == $systemUserId) ? $msg['receiver_id'] : $msg['sender_id'];
        if (!isset($conversations[$other_id])) {
            $conversations[$other_id] = [
                'user_id' => $other_id,
                'last_message' => $msg,
                'message_count' => 1,
                'unread_count' => ($msg['receiver_id'] == $systemUserId && $msg['status'] == 'unread') ? 1 : 0
            ];
        } else {
            $conversations[$other_id]['message_count']++;
            if ($msg['receiver_id'] == $systemUserId && $msg['status'] == 'unread') {
                $conversations[$other_id]['unread_count']++;
            }
        }
    }
    
    // 获取用户信息 - 根据数据库类型选择查询方式
    foreach ($conversations as $user_id => $conv) {
        // 跳过系统用户
        if ($user_id == 'system' || $user_id == 'info') {
            continue;
        }
        
        if ($dbType === 'mysql') {
            // MySQL使用?占位符
            $user = $db->fetch(
                "SELECT id, username, email FROM `{$prefix}users` WHERE id = ?",
                [(string)$user_id]
            );
        } else {
            // JSON存储使用命名参数
            $user = $db->fetch(
                "SELECT id, username, email FROM `{$prefix}users` WHERE id = :id",
                ['id' => (string)$user_id]
            );
        }
        
        if ($user) {
            $conversations[$user_id]['user'] = $user;
            $systemMessages[] = $conversations[$user_id];
        }
    }
} catch (Exception $e) {
    $error .= ' 获取消息失败: ' . $e->getMessage();
}

$page_title = '后台消息管理';
include __DIR__ . '/templates/admin_header.php';
?>

<style>

    .message-container {
        max-height: 400px;
        overflow-y: auto;
        margin-bottom: 20px;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
    }

    .ip-location {
        font-size: 11px;
        color: #999;
        margin-left: 10px;
    }
    /* 图片弹窗样式 */
    .modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.9);
    }
    .modal-content {
        margin: 10% auto;
        display: block;
        max-width: 80%;
        max-height: 80%;
    }
    .close {
        position: absolute;
        top: 15px;
        right: 35px;
        color: #f1f1f1;
        font-size: 40px;
        font-weight: bold;
        transition: 0.3s;
    }
    .close:hover,
    .close:focus {
        color: #bbb;
        text-decoration: none;
        cursor: pointer;
    }
</style>

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
                
                <?php if (!empty($message)): ?>
                    <tr>
                        <td colspan="2" class="<?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                            <strong><?php echo $messageType === 'success' ? '成功：' : '错误：'; ?></strong><?php echo htmlspecialchars($message); ?>
                        </td>
                    </tr>
                <?php endif; ?>
                
                <!-- 系统消息 -->
                <tr>
                    <td colspan="2">
                        <?php if ($view_conversation && $conversation_user): ?>
                            <!-- 对话查看页面 -->
                            <div style="margin-bottom: 20px;">
                                <a href="system_messages.php" style="display: inline-block; padding: 5px 15px; background-color: #f0f0f0; text-decoration: none; border-radius: 3px;">返回消息列表</a>
                                <h3>与 <?php echo htmlspecialchars($conversation_user['username']); ?> (ID: <?php echo $conversation_user['id']; ?>) 的对话</h3>
                            </div>
                            
                            <div class="message-container">
                                <?php if (count($conversation_messages) > 0): ?>
                                    <?php foreach ($conversation_messages as $msg): ?>
                                        <div style="margin-bottom: 15px; <?php echo $msg['sender_id'] == $systemUserId ? 'text-align: right;' : ''; ?>">
                                                    <div style="display: inline-block; max-width: 70%; padding: 10px; border-radius: 10px; <?php echo $msg['sender_id'] == $systemUserId ? 'background-color: #4A90E2; color: white;' : 'background-color: #e9e9e9; color: #333;'; ?>">
                                                        <div style="margin: 0; word-wrap: break-word;">
                                                            <?php 
                                                                // 解析消息内容，处理图片标签
                                                                $content = htmlspecialchars($msg['content']);
                                                                // 将[img]url[/img]替换为图片标签（使用弹窗显示）
                                                                $content = preg_replace('/\[img\](.*?)\[\/img\]/', '<img src="$1" style="max-width: 200px; max-height: 150px; border-radius: 5px; margin: 5px 0; display: block; cursor: pointer; object-fit: cover;" onclick="openImageModal(this.src)" title="点击查看大图">', $content);
                                                                echo nl2br($content); 
                                                            ?>
                                                        </div>
                                                        <small style="display: block; margin-top: 5px; opacity: 0.8;">
                                                            <?php echo $msg['sender_id'] == $systemUserId ? '【系统通知】' : htmlspecialchars($conversation_user['username']); ?> - <?php echo formatDateTime($msg['created_at']); ?>
                                                            <?php if (!empty($msg['ip'])): ?>
                                                                <span style="margin-left: 10px; font-size: 11px;"><?php echo getIPLocation($msg['ip']); ?></span>
                                                            <?php endif; ?>
                                                            <span style="margin-left: 10px; font-size: 11px; font-weight: bold; color: <?php echo $msg['status'] == 'unread' ? 'red' : '#999'; ?>">
                                                                [<?php echo $msg['status'] == 'unread' ? '未读' : '已读'; ?>]
                                                            </span>
                                                        </small>
                                                    </div>
                                                </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="text-align: center; color: #999; padding: 20px;">
                                        暂无对话记录
                                    </div>
                                <?php endif; ?>
                                <script>
                                    // 页面加载完成后滚动到底部
                                    document.addEventListener('DOMContentLoaded', function() {
                                        var messageContainer = document.querySelector('.message-container');
                                        if (messageContainer) {
                                            messageContainer.scrollTop = messageContainer.scrollHeight;
                                        }
                                    });
                                </script>
                            </div>
                            
                            <!-- 回复表单 -->
                            <div>
                                <h4>回复消息</h4>
                                <form method="POST" action="system_messages.php?view_conversation=<?php echo $view_conversation; ?>">
                                    <input type="hidden" name="receiver_id" value="<?php echo $view_conversation; ?>">
                                    <textarea name="content" rows="5" cols="80" placeholder="请输入回复内容，支持粘贴图片" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 3px;"></textarea>
                                    <br>
                                    <button type="submit" name="send_reply" style="margin-top: 10px; padding: 8px 20px; background-color: #4A90E2; color: white; border: none; border-radius: 3px; cursor: pointer;">发送回复</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <!-- 消息列表页面 -->
                            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                <tr>
                                    <td colspan="2"><h5>系统消息列表</h5></td>
                                </tr>
                                
                                <?php if (count($systemMessages) > 0): ?>
                                    <?php foreach ($systemMessages as $conv): ?>
                                        <tr>
                                            <td colspan="2">
                                                <div style="background-color: #f5f5f5; padding: 10px; border-radius: 5px;">
                                                    <strong>用户：</strong>
                                                    <a href="../profile.php?id=<?php echo $conv['user']['id']; ?>" target="_blank">
                                                        <?php echo htmlspecialchars($conv['user']['username']); ?> (ID: <?php echo $conv['user']['id']; ?>)
                                                    </a>
                                                    <?php if (!empty($conv['user']['email'])): ?>
                                                        - <?php echo htmlspecialchars($conv['user']['email']); ?>
                                                    <?php endif; ?>
                                                    <br>
                                                    <strong>消息数：</strong><?php echo $conv['message_count']; ?> 条<?php if (isset($conv['unread_count']) && $conv['unread_count'] > 0): ?> <span style="color: red; font-weight: bold;">(未读：<?php echo $conv['unread_count']; ?> 条)</span><?php endif; ?>
                                                    <br>
                                                    <strong>最后消息：</strong>
                                                    <?php 
                                                        $last_content = mb_substr(strip_tags($conv['last_message']['content']), 0, 100);
                                                        if (strlen($conv['last_message']['content']) > 100) {
                                                            $last_content .= '...';
                                                        }
                                                        echo $last_content;
                                                    ?>
                                                    <br>
                                                    <strong>时间：</strong><?php echo formatDateTime($conv['last_message']['created_at']); ?>
                                                    <?php if (!empty($conv['last_message']['ip'])): ?>
                                                        <span class="ip-location"><?php echo getIPLocation($conv['last_message']['ip']); ?></span>
                                                    <?php endif; ?>
                                                    <br>
                                                    <a href="system_messages.php?view_conversation=<?php echo $conv['user']['id']; ?>" style="display: inline-block; margin-top: 10px; padding: 5px 15px; background-color: #4A90E2; color: white; text-decoration: none; border-radius: 3px;">查看对话</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="2" align="center">暂无系统消息</td>
                                    </tr>
                                <?php endif; ?>
                            </table>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>



<!-- 图片弹窗 -->
<div id="imageModal" class="modal">
    <span class="close" onclick="closeImageModal()">&times;</span>
    <img class="modal-content" id="modalImage">
</div>

<script>
// 图片弹窗功能
function openImageModal(imgSrc) {
    var modal = document.getElementById("imageModal");
    var modalImg = document.getElementById("modalImage");
    modal.style.display = "block";
    modalImg.src = imgSrc;
}

function closeImageModal() {
    document.getElementById("imageModal").style.display = "none";
}

// 点击弹窗外部关闭
window.onclick = function(event) {
    var modal = document.getElementById("imageModal");
    if (event.target == modal) {
        modal.style.display = "none";
    }
}

// 粘贴上传图片功能
var textarea = document.querySelector('textarea[name="content"]');
if (textarea) {
    textarea.addEventListener('paste', function(e) {
        var items = e.clipboardData.items;
        for (var i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                var file = items[i].getAsFile();
                var formData = new FormData();
                formData.append('file', file);
                formData.append('action', 'upload_image');
                formData.append('type', 'message');
                
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '../upload.php', true);
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                textarea.value += '[img]' + response.url + '[/img]';
                            } else {
                                alert('上传失败：' + (response.message || '未知错误'));
                            }
                        } catch (e) {
                            alert('上传失败：解析响应失败');
                        }
                    } else {
                        alert('上传失败：网络错误（' + xhr.status + '）');
                    }
                };
                xhr.onerror = function() {
                    alert('上传失败：网络错误');
                };
                xhr.send(formData);
                e.preventDefault();
            }
        }
    });
}
</script>

<?php
include __DIR__ . '/templates/admin_footer.php';
?>