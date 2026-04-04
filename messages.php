<?php
/**
 * 消息管理页面
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
require_once __DIR__ . '/includes/social_functions.php';

// 检查安装状态和闭站模式
checkInstall();

// 检查是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 获取用户ID
$user_id = $_SESSION['user_id'];

// 获取参数
$action = $_GET['action'] ?? 'list';
$other_user_id = $_GET['user_id'] ?? 0;

// 处理发送消息
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_id = $_POST['receiver_id'] ?? 0;
    $content = $_POST['content'] ?? '';
    $image_data = $_POST['image_data'] ?? '';
    
    // 禁止回复互动消息
    if ($receiver_id == 'info') {
        $error = '互动消息不可回复';
    } else if (!empty($receiver_id) && (!empty(trim($content)) || !empty($image_data))) {
        // 处理粘贴的图片
        if (!empty($image_data) && strpos($image_data, 'data:image') === 0) {
            $upload_dir = __DIR__ . '/uploads/messages/';
            
            // 使用压缩函数处理图片（最大宽度1200px，质量85%）
            $file_path = processBase64Image($image_data, $upload_dir, 1200, 1200, 85);
            
            if ($file_path) {
                $image_url = str_replace(__DIR__, '', $file_path);
                $image_url = str_replace('\\', '/', $image_url);
                // 将图片添加到消息内容
                $content = trim($content) . "\n[img]" . $image_url . "[/img]";
            }
        }
        
        if (!empty(trim($content))) {
            if (sendMessage($user_id, $receiver_id, trim($content))) {
                $success = '消息发送成功';
            } else {
                $error = '消息发送失败';
            }
        } else {
            $error = '请填写消息内容';
        }
    } else {
        $error = '请填写完整信息';
    }
}

// 设置页面标题
$page_title = '消息管理';

// 加载页面头部
include __DIR__ . '/templates/header.php';
?>

<table border="1" width="100%" cellspacing="0" cellpadding="10">
    <tr>
        <td colspan="2">
            <table width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <td><h1>消息管理</h1></td>
                    <td align="right">
                        <a href="<?php echo getHomeUrl(); ?>">首页</a> &gt; 消息管理
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    
    <?php if (isset($error)): ?>
        <tr>
            <td colspan="2" class="error"><?php echo $error; ?></td>
        </tr>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <tr>
            <td colspan="2" class="success"><?php echo $success; ?></td>
        </tr>
    <?php endif; ?>
    
    <tr>
        <td width="30%" valign="top">
            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                <tr>
                    <td colspan="2"><h5>消息列表</h5></td>
                </tr>
                <?php
                try {
                    $db = getDB();
                    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
                    
                    // 获取与用户相关的消息（在SQL查询中筛选，确保只获取当前用户的消息）
                    $allMessages = $db->fetchAll(
                        "SELECT * FROM `{$prefix}messages` WHERE 
                        `sender_id` = :user_id OR `receiver_id` = :user_id 
                        ORDER BY created_at DESC",
                        ['user_id' => (string)$user_id]
                    );
                    
                    // 按对话分组，获取每个对话的最新消息
                    $conversations = [];
                    foreach ($allMessages as $msg) {
                        $other_id = ($msg['sender_id'] == $user_id) ? $msg['receiver_id'] : $msg['sender_id'];
                        if (!isset($conversations[$other_id])) {
                            $conversations[$other_id] = $msg;
                        }
                    }
                    
                    // 获取对方用户信息
                    $messages = [];
                    // 确保系统通知和互动消息始终显示
                    $special_users = [
                        'system' => '【系统通知】',
                        'info' => '【互动消息】'
                    ];
                    
                    // 先添加特殊用户
                    foreach ($special_users as $id => $name) {
                        if (!isset($conversations[$id])) {
                            // 如果没有对话记录，创建一个默认消息
                            $conversations[$id] = [
                                'id' => 0,
                                'sender_id' => $id,
                                'receiver_id' => $user_id,
                                'content' => '欢迎使用消息系统',
                                'status' => 'read',
                                'ip' => '127.0.0.1',
                                'created_at' => date('Y-m-d H:i:s')
                            ];
                        }
                    }
                    
                    foreach ($conversations as $other_id => $msg) {
                        // 特殊处理系统用户和互动消息用户
                        if ($other_id == 'system') {
                            $user = [
                                'id' => 'system',
                                'username' => '【系统通知】',
                                'avatar' => ''
                            ];
                        } else if ($other_id == 'info') {
                            $user = [
                                'id' => 'info',
                                'username' => '【互动消息】',
                                'avatar' => ''
                            ];
                        } else {
                            $user = $db->fetch(
                                "SELECT id, username, avatar FROM `{$prefix}users` WHERE id = :user_id",
                                ['user_id' => (string)$other_id]
                            );
                        }
                        if ($user) {
                            $msg['other_user_id'] = $user['id'];
                            $msg['other_username'] = $user['username'];
                            $msg['other_avatar'] = $user['avatar'] ?? '';
                            $messages[] = $msg;
                        }
                    }
                    
                    if (count($messages) > 0) {
                        foreach ($messages as $msg) {
                            $is_unread = ($msg['receiver_id'] == $user_id && $msg['status'] == 'unread');
                            ?>
                            <tr>
                                <td width="20%" align="center">
                                    <img src="<?php echo htmlspecialchars(getUserAvatar(['avatar' => $msg['other_avatar'] ?? ''], 50)); ?>" alt="<?php echo htmlspecialchars($msg['other_username'] ?? '未知用户'); ?>" style="width: 40px; height: 40px;" onerror="this.src='/icon.png'; this.onerror=null;">
                                </td>
                                <td>
                                    <a href="messages.php?action=conversation&user_id=<?php echo $msg['other_user_id']; ?>" style="text-decoration: none; color: #333;">
                                        <strong><?php echo htmlspecialchars($msg['other_username'] ?? '未知用户'); ?> (ID: <?php echo $msg['other_user_id']; ?>)</strong>
                                        <?php if ($is_unread): ?>
                                            <span style="color: red; font-weight: bold;"> (未读)</span>
                                        <?php endif; ?>
                                        <br>
                                        <small style="color: #999;"><?php echo formatDateTime($msg['created_at']); ?></small>
                                    </a>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="2" align="center">暂无消息</td>
                        </tr>
                        <?php
                    }
                } catch (Exception $e) {
                    ?>
                    <tr>
                        <td colspan="2" class="error">加载消息列表失败: <?php echo $e->getMessage(); ?></td>
                    </tr>
                    <?php
                }
                ?>
            </table>
        </td>
        
        <td width="70%" valign="top">
            <?php if ($action == 'conversation' && !empty($other_user_id)): ?>
                <?php
                try {
                    $db = getDB();
                    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
                    
                    // 获取对方用户信息
                    $other_user = $db->fetch(
                        "SELECT * FROM `{$prefix}users` WHERE `id` = :id",
                        ['id' => $other_user_id]
                    );
                    
                    if ($other_user) {
                        // 检查是否为互动消息用户，禁止回复
                    $is_info_user = $other_user_id == 'info';
                    
                    // 标记消息为已读
                    try {
                        $db->query(
                            "UPDATE `{$prefix}messages` SET status = 'read' WHERE receiver_id = :user_id AND sender_id = :other_user_id",
                            [
                                'user_id' => (string)$user_id,
                                'other_user_id' => (string)$other_user_id
                            ]
                        );
                    } catch (Exception $e) {
                        // 标记失败不影响其他功能
                    }
                    
                    // 获取对话消息
                    $conversation = getMessageThread($user_id, $other_user_id);
                    ?>
                        <table border="1" width="100%" cellspacing="0" cellpadding="5">
                            <tr>
                                <td colspan="2">
                                    <h5>与 <a href="profile.php?id=<?php echo $other_user['id']; ?>"><?php echo htmlspecialchars($other_user['username']); ?></a> 的对话</h5>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <div class="message-container">
                                        <?php if (count($conversation) > 0): ?>
                                            <?php foreach ($conversation as $msg): ?>
                                                <div style="margin-bottom: 15px; <?php echo $msg['sender_id'] == $user_id ? 'text-align: right;' : ''; ?>">
                                                    <div style="display: inline-block; max-width: 70%; padding: 10px; border-radius: 10px; <?php echo $msg['sender_id'] == $user_id ? 'background-color: #4A90E2; color: white;' : 'background-color: #e9e9e9; color: #333;'; ?>">
                                                        <div style="margin: 0; word-wrap: break-word;">
                                                            <?php 
                                                            // 解析消息内容，处理图片标签
                                                            $content = htmlspecialchars($msg['content']);
                                                            // 将[img]url[/img]替换为图片标签（使用弹窗显示）
                                                            $content = preg_replace('/\[img\](.*?)\[\/img\]/', '<img src="$1" style="max-width: 200px; max-height: 150px; border-radius: 5px; margin: 5px 0; display: block; cursor: pointer; object-fit: cover;" onclick="openImageModal(this.src)" title="点击查看大图">', $content);
                                                            
                                                            // 处理互动消息中的用户名显示
                                                            if ($msg['sender_id'] == 'info') {
                                                                // 尝试从消息内容中提取回复者ID
                                                                if (preg_match('/回复者：用户\s*\(ID:\s*(\d*)\)/', $content, $matches)) {
                                                                    $replier_id = $matches[1];
                                                                    // 获取回复者信息
                                                                    if (!empty($replier_id)) {
                                                                        // 尝试用字符串ID查询
                                                                        $replier = $db->fetch(
                                                                            "SELECT id, username FROM `{$prefix}users` WHERE `id` = ?",
                                                                            [(string)$replier_id]
                                                                        );
                                                                        // 如果失败，尝试用整数ID查询
                                                                        if (!$replier) {
                                                                            $replier = $db->fetch(
                                                                                "SELECT id, username FROM `{$prefix}users` WHERE `id` = ?",
                                                                                [(int)$replier_id]
                                                                            );
                                                                        }
                                                                        if ($replier) {
                                                                            $content = str_replace('回复者：用户 (ID: ' . $replier_id . ')', '回复者：' . htmlspecialchars($replier['username']) . ' (ID: ' . $replier['id'] . ')', $content);
                                                                        }
                                                                    }
                                                                } elseif (preg_match('/回复者：用户/', $content)) {
                                                                    // 对于没有ID的消息，尝试从内容中提取其他信息
                                                                    // 检查是否有回复者ID的其他格式
                                                                    if (preg_match('/回复者：用户\s*\(ID:\s*\)/', $content)) {
                                                                        // 尝试从消息内容中推断回复者ID
                                                                        // 这里可以根据实际情况进行更复杂的处理
                                                                    }
                                                                }
                                                                 
                                                                // 处理点赞和收藏通知中的用户名
                                                                if (preg_match('/点赞者：用户\s*\(ID:\s*(\d+)\)/', $content, $matches)) {
                                                                    $liker_id = $matches[1];
                                                                    // 尝试用字符串ID查询
                                                                    $liker = $db->fetch(
                                                                        "SELECT id, username FROM `{$prefix}users` WHERE `id` = ?",
                                                                        [(string)$liker_id]
                                                                    );
                                                                    // 如果失败，尝试用整数ID查询
                                                                    if (!$liker) {
                                                                        $liker = $db->fetch(
                                                                            "SELECT id, username FROM `{$prefix}users` WHERE `id` = ?",
                                                                            [(int)$liker_id]
                                                                        );
                                                                    }
                                                                    if ($liker) {
                                                                        $content = str_replace('点赞者：用户 (ID: ' . $liker_id . ')', '点赞者：' . htmlspecialchars($liker['username']) . ' (ID: ' . $liker['id'] . ')', $content);
                                                                    }
                                                                }
                                                                 
                                                                if (preg_match('/收藏者：用户\s*\(ID:\s*(\d+)\)/', $content, $matches)) {
                                                                    $bookmarker_id = $matches[1];
                                                                    // 尝试用字符串ID查询
                                                                    $bookmarker = $db->fetch(
                                                                        "SELECT id, username FROM `{$prefix}users` WHERE `id` = ?",
                                                                        [(string)$bookmarker_id]
                                                                    );
                                                                    // 如果失败，尝试用整数ID查询
                                                                    if (!$bookmarker) {
                                                                        $bookmarker = $db->fetch(
                                                                            "SELECT id, username FROM `{$prefix}users` WHERE `id` = ?",
                                                                            [(int)$bookmarker_id]
                                                                        );
                                                                    }
                                                                    if ($bookmarker) {
                                                                        $content = str_replace('收藏者：用户 (ID: ' . $bookmarker_id . ')', '收藏者：' . htmlspecialchars($bookmarker['username']) . ' (ID: ' . $bookmarker['id'] . ')', $content);
                                                                    }
                                                                }
                                                            }
                                                            
                                                            echo nl2br($content); 
                                                            ?>
                                                        </div>
                                                        <small style="display: block; margin-top: 5px; opacity: 0.8;">
                                                            <?php echo formatDateTime($msg['created_at']); ?>
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
                                    </div>
                                    <style>
                                        /* 消息容器样式 */
                                        .message-container {
                                            max-height: 400px;
                                            overflow-y: auto;
                                            margin-bottom: 20px;
                                            padding: 15px;
                                            border: 1px solid #ddd;
                                            border-radius: 5px;
                                            background-color: #f9f9f9;
                                        }
                                        /* 自定义滚动条样式 */
                                        .message-container::-webkit-scrollbar {
                                            width: 8px;
                                        }
                                        .message-container::-webkit-scrollbar-track {
                                            background: #f1f1f1;
                                            border-radius: 4px;
                                        }
                                        .message-container::-webkit-scrollbar-thumb {
                                            background: #c1c1c1;
                                            border-radius: 4px;
                                        }
                                        .message-container::-webkit-scrollbar-thumb:hover {
                                            background: #a1a1a1;
                                        }
                                    </style>
                                    <script>
                                        // 页面加载完成后滚动到底部
                                        document.addEventListener('DOMContentLoaded', function() {
                                            var messageContainer = document.querySelector('.message-container');
                                            if (messageContainer) {
                                                messageContainer.scrollTop = messageContainer.scrollHeight;
                                            }
                                        });
                                    </script>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <?php if (!$is_info_user): ?>
                                    <form method="post" action="messages.php?action=conversation&user_id=<?php echo $other_user_id; ?>" id="messageForm">
                                        <input type="hidden" name="receiver_id" value="<?php echo $other_user_id; ?>">
                                        <div style="position: relative;">
                                            <textarea name="content" id="messageContent" rows="4" style="width: 100%; padding: 10px; resize: vertical;" placeholder="请输入消息内容...支持粘贴上传图片" required></textarea>
                                            <div id="pasteStatus" style="position: absolute; bottom: 5px; left: 10px; font-size: 12px; color: #666; display: none;">
                                                <span style="color: #4A90E2;">📎 已粘贴图片，发送时将自动上传</span>
                                            </div>
                                        </div>
                                        <input type="hidden" name="image_data" id="imageData">
                                        <div style="text-align: right; margin-top: 10px;">
                                            <button type="submit" name="send_message" style="padding: 5px 15px; background-color: #4A90E2; color: white; border: none; border-radius: 3px; cursor: pointer;">发送</button>
                                        </div>
                                    </form>
                                    <script>
                                    document.getElementById('messageContent').addEventListener('paste', function(e) {
                                        var items = e.clipboardData.items;
                                        for (var i = 0; i < items.length; i++) {
                                            if (items[i].type.indexOf('image') !== -1) {
                                                e.preventDefault();
                                                var blob = items[i].getAsFile();
                                                var reader = new FileReader();
                                                reader.onload = function(event) {
                                                    document.getElementById('imageData').value = event.target.result;
                                                    document.getElementById('pasteStatus').style.display = 'block';
                                                    // 在文本框中显示提示
                                                    var textarea = document.getElementById('messageContent');
                                                    if (textarea.value && !textarea.value.includes('[图片]')) {
                                                        textarea.value += '\n[图片]';
                                                    } else if (!textarea.value) {
                                                        textarea.value = '[图片]';
                                                    }
                                                };
                                                reader.readAsDataURL(blob);
                                                break;
                                            }
                                        }
                                    });
                                    </script>
                                    <?php else: ?>
                                    <div style="padding: 10px; background-color: #f5f5f5; border-radius: 5px; text-align: center;">
                                        <p style="color: #666; font-size: 14px;">互动消息为系统自动发送，不可回复</p>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                        <?php
                    } else {
                        ?>
                        <table border="1" width="100%" cellspacing="0" cellpadding="5">
                            <tr>
                                <td align="center">用户不存在</td>
                            </tr>
                        </table>
                        <?php
                    }
                } catch (Exception $e) {
                    ?>
                    <table border="1" width="100%" cellspacing="0" cellpadding="5">
                        <tr>
                            <td class="error">加载对话失败: <?php echo $e->getMessage(); ?></td>
                        </tr>
                    </table>
                    <?php
                }
                ?>
            <?php else: ?>
                <table border="1" width="100%" cellspacing="0" cellpadding="5">
                    <tr>
                        <td align="center">
                            <h5>请选择一个对话</h5>
                            <p>从左侧列表中选择一个用户开始对话</p>
                        </td>
                    </tr>
                </table>
            <?php endif; ?>
        </td>
    </tr>
</table>

<!-- 图片查看弹窗 -->
<div id="imageModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.9); z-index: 9999;" onclick="closeImageModal(event)">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); max-width: 95%; max-height: 95%; text-align: center;">
        <img id="modalImage" src="" style="max-width: 100%; max-height: 90vh; border-radius: 5px; box-shadow: 0 0 20px rgba(0,0,0,0.5);" onclick="event.stopPropagation();">
        <div style="margin-top: 10px; color: white; font-size: 14px;">
            <span id="modalImageInfo"></span>
            <button onclick="downloadImage()" style="margin-left: 15px; padding: 5px 15px; background-color: #4A90E2; color: white; border: none; border-radius: 3px; cursor: pointer;">下载原图</button>
            <button onclick="closeImageModal()" style="margin-left: 10px; padding: 5px 15px; background-color: #666; color: white; border: none; border-radius: 3px; cursor: pointer;">关闭</button>
        </div>
    </div>
</div>

<script>
// 打开图片弹窗
function openImageModal(src) {
    var modal = document.getElementById('imageModal');
    var img = document.getElementById('modalImage');
    var info = document.getElementById('modalImageInfo');
    
    img.src = src;
    modal.style.display = 'block';
    
    // 获取图片信息
    var tempImg = new Image();
    tempImg.onload = function() {
        info.textContent = '尺寸: ' + this.naturalWidth + ' x ' + this.naturalHeight;
    };
    tempImg.src = src;
    
    // 保存当前图片URL用于下载
    window.currentImageUrl = src;
}

// 关闭图片弹窗
function closeImageModal(event) {
    if (!event || event.target.id === 'imageModal' || event.target.tagName === 'BUTTON') {
        document.getElementById('imageModal').style.display = 'none';
        document.getElementById('modalImage').src = '';
    }
}

// 下载图片
function downloadImage() {
    if (window.currentImageUrl) {
        var link = document.createElement('a');
        link.href = window.currentImageUrl;
        link.download = 'image_' + new Date().getTime() + '.png';
        link.click();
    }
}

// ESC键关闭弹窗
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImageModal();
    }
});
</script>

<?php
// 加载页面底部
include __DIR__ . '/templates/footer.php';
?>