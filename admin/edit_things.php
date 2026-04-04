<?php
date_default_timezone_set('Asia/Shanghai');
/**
 * 用户管理 & 公告管理（整合提示栏修改功能）
 */

// 启动会话
session_start();

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 检查是否已安装
if (!file_exists(__DIR__ . '/../config/config.php')) {
    header('Location: ../install/index.php');
    exit;
}

// 加载配置和函数
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail_functions.php';
require_once __DIR__ . '/includes/admin_functions.php';

// 检查是否已登录且是管理员
checkAdminAccess();

// ---------------------- 核心：提示栏修改逻辑（前置处理，无HTML输出） ----------------------
// 定义目标文件路径（修正路径：根据实际文件位置调整，此处假设 site_functions.php 在 admin/includes/ 下）
$targetFile = __DIR__ . '/includes/site_functions.php';

// 初始化提示信息
$message = '';
$messageType = ''; // success / error

// 处理提示栏修改表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['site_alert_content'])) {
    // 获取表单提交的内容，过滤首尾空白字符
    $newContent = trim($_POST['site_alert_content']);
    
    // 简单验证：内容不能为空
    if (empty($newContent)) {
        $message = '错误：提示栏内容不能为空！';
        $messageType = 'error';
    } else {
        // 写入内容到目标文件
        $writeResult = file_put_contents($targetFile, $newContent);
        
        if ($writeResult !== false) {
            $message = '成功：提示栏内容已保存并更新！';
            $messageType = 'success';
        } else {
            $message = '错误：无法写入文件，请检查 ' . $targetFile . ' 的写入权限！';
            $messageType = 'error';
        }
    }
}

// 处理通知管理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $sendEmailNotification = isset($_POST['send_email_notification']) ? true : false;
    $notificationScope = $_POST['notification_scope'] ?? 'all';
    $selectedUserIds = isset($_POST['selected_user_ids']) ? $_POST['selected_user_ids'] : [];
    $emailSubject = isset($_POST['email_subject']) ? trim($_POST['email_subject']) : '';
    $emailContent = isset($_POST['email_content']) ? trim($_POST['email_content']) : '';
    $sendSiteNotification = isset($_POST['send_site_notification']) ? true : false;
    $siteNotificationContent = isset($_POST['site_notification_content']) ? trim($_POST['site_notification_content']) : '';
    
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        $site_name = getSetting('site_name', 'PHP轻论坛');
        
        // 确保系统用户存在（使用特殊ID 'system'）
        $systemUserId = 'system';
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
            } catch (Exception $e) {
                // 忽略插入错误，可能是因为已经存在
            }
        }
        
        // 处理邮件通知
        if ($sendEmailNotification && !empty($emailSubject) && !empty($emailContent)) {
            // 获取目标用户列表
            if ($notificationScope === 'selected' && !empty($selectedUserIds)) {
                $users = $db->fetchAll(
                    "SELECT id, username, email FROM `{$prefix}users` WHERE `id` IN (" . implode(',', array_map('intval', $selectedUserIds)) . ") AND `status` = 'active' AND `email` IS NOT NULL AND `email` != ''"
                );
            } else {
                // 全体用户
                $users = $db->fetchAll(
                    "SELECT id, username, email FROM `{$prefix}users` WHERE `status` = 'active' AND `email` IS NOT NULL AND `email` != ''"
                );
            }
            
            $sentCount = 0;
            $failCount = 0;
            
            foreach ($users as $user) {
                $content = <<<HTML
<p>您好，{$user['username']}：</p>
<p>{$emailContent}</p>
HTML;
                
                $result = sendMail($user['email'], "【{$site_name}】{$emailSubject}", $content, 'system');
                
                if ($result) {
                    $sentCount++;
                } else {
                    $failCount++;
                }
                
                usleep(50000);
            }
            
            $message = "邮件通知已发送：成功 {$sentCount} 封，失败 {$failCount} 封。";
            $messageType = 'success';
            
            // 记录操作日志
            logAdminAction('管理员发送邮件通知', 'system', 0, [
                'subject' => $emailSubject,
                'scope' => $notificationScope,
                'recipient_count' => count($users),
                'content_length' => mb_strlen($emailContent),
                'admin_id' => $_SESSION['user_id'],
                'admin_username' => $_SESSION['username'],
                'action_time' => date('Y-m-d H:i:s'),
                'ip_address' => getClientIp()
            ]);
        }
        
        // 处理站内消息通知
        if ($sendSiteNotification && !empty($siteNotificationContent)) {
            // 获取目标用户列表
            if ($notificationScope === 'selected' && !empty($selectedUserIds)) {
                $users = $db->fetchAll(
                    "SELECT id, username FROM `{$prefix}users` WHERE `id` IN (" . implode(',', array_map('intval', $selectedUserIds)) . ") AND `status` = 'active'"
                );
            } else {
                // 全体用户
                $users = $db->fetchAll(
                    "SELECT id, username FROM `{$prefix}users` WHERE `status` = 'active'"
                );
            }
            
            $sentCount = 0;
            
            foreach ($users as $user) {
                $db->insert("{$prefix}messages", [
                    'sender_id' => $systemUserId,
                    'receiver_id' => $user['id'],
                    'content' => $siteNotificationContent,
                    'status' => 'unread',
                    'ip' => '127.0.0.1',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $sentCount++;
            }
            
            $message = "站内消息通知已发送：{$sentCount} 条。";
            $messageType = 'success';
            
            // 记录操作日志
            logAdminAction('管理员发送站内消息通知', 'system', 0, [
                'scope' => $notificationScope,
                'recipient_count' => $sentCount,
                'content_length' => mb_strlen($siteNotificationContent),
                'admin_id' => $_SESSION['user_id'],
                'admin_username' => $_SESSION['username'],
                'action_time' => date('Y-m-d H:i:s'),
                'ip_address' => getClientIp()
            ]);
        }
        
        if (!$sendEmailNotification && !$sendSiteNotification) {
            $message = '请至少选择一种通知方式（邮件通知或站内消息通知）！';
            $messageType = 'error';
        }
        
    } catch (Exception $e) {
        $message = '发送失败：' . $e->getMessage();
        $messageType = 'error';
    }
}

// 读取目标文件的现有内容（用于表单回显）
$currentContent = '';
if (file_exists($targetFile) && is_readable($targetFile)) {
    $currentContent = file_get_contents($targetFile);
} else {
    $message = '警告：无法读取目标文件 ' . $targetFile . '，请检查文件是否存在或有读取权限！';
    $messageType = 'error';
}

// 获取所有活跃用户列表（排除系统用户）
$allUsers = [];
try {
    $db = getDB();
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    $allUsers = $db->fetchAll(
        "SELECT id, username, email FROM `{$prefix}users` WHERE `status` = 'active' AND `id` != 'system' AND `id` != 'info' ORDER BY id ASC"
    );
} catch (Exception $e) {
    $message .= ' 获取用户列表失败：' . $e->getMessage();
}

// ---------------------- 后台页面渲染（复用后台模板） ----------------------
// 设置页面标题
$page_title = '公告管理';

// 加载页面头部（包含后台的CSS、JS、导航栏，无需重新写 <!DOCTYPE html>）
include __DIR__ . '/templates/admin_header.php';
?>

<style>
    .tab-container {
        margin-bottom: 20px;
        border-bottom: 1px solid #ddd;
        padding-bottom: 0;
    }
    .tab-button {
        display: inline-block;
        padding: 5px 20px;
        background-color: #f0f0f0;
        border: 1px solid #ddd;
        border-bottom: 1px solid #ddd;
        cursor: pointer;
        margin-right: 5px;
        border-radius: 5px 5px 0 0;
        position: relative;
        top: 0px;
    }
    .tab-button.active {
        background-color: white;
        color: #4A90E2;
        border-bottom: 1px solid white;
    }
    .tab-content {
        display: none;
        border: 1px solid #ddd;
        border-top: none;
        padding: 20px;
        border-radius: 0 5px 5px 5px;
        background-color: white;
    }
    .tab-content.active {
        display: block;
    }
</style>

<!-- 关闭PHP标签，开始写HTML内容（嵌入后台模板中，复用后台样式） -->
<table border="1" width="100%" cellspacing="0" cellpadding="10">
    <tr>
        <!-- 侧边栏 -->
        <td width="200" valign="top">
            <?php include __DIR__ . '/templates/admin_sidebar.php'; ?>
        </td>
        
        <!-- 主内容区 -->
        <td valign="top">
            <table width="100%" cellspacing="0" cellpadding="5">
                <tr>
                    <td colspan="2">
                        <h1><?php echo $page_title; ?></h1>
                    </td>
                </tr>
                
                <!-- 操作提示信息 -->
                <?php if (!empty($message)): ?>
                    <tr>
                        <td colspan="2">
                            <strong><?php echo $messageType === 'success' ? '成功：' : '错误：'; ?></strong><?php echo htmlspecialchars($message); ?>
                        </td>
                    </tr>
                <?php endif; ?>
                
                <!-- 标签页导航 -->
                <tr>
                    <td colspan="2">
                        <div class="tab-container">
                            <button class="tab-button active" onclick="switchTab('alert')">首页修改</button>
                            <button class="tab-button" onclick="switchTab('notification')">通知管理</button>
                        </div>
                    </td>
                </tr>
                
                <!-- 首页修改标签页 -->
                <tr>
                    <td colspan="2">
                        <div id="alert-tab" class="tab-content active">
                            <h5>修改站点首页内容</h5>
                            <form method="post" action="">
                                <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                    <tr>
                                        <td width="20%">首页 HTML 内容</td>
                                        <td>
                                            <textarea name="site_alert_content" rows="20" style="width: 100%; box-sizing: border-box;" required><?php echo htmlspecialchars($currentContent); ?></textarea>
                                            <div>提示：可直接修改 HTML 代码（如链接、文字），修改后点击「保存修改」即可生效。</div>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td colspan="2" align="center">
                                            <button type="submit">保存修改</button>
                                        </td>
                                    </tr>
                                </table>
                            </form>
                        </div>
                        
                        <!-- 通知管理标签页 -->
                        <div id="notification-tab" class="tab-content">
                            <h5>通知管理</h5>
                            <form method="post" action="">
                                <input type="hidden" name="send_notification" value="1">
                                <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                    <tr>
                                        <td colspan="2">
                                            <strong>通知范围</strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>通知范围</td>
                                        <td>
                                            <input type="radio" name="notification_scope" value="all" id="scope_all" checked onchange="toggleUserSelection()">
                                            <label for="scope_all">全体用户</label>
                                            <input type="radio" name="notification_scope" value="selected" id="scope_selected" onchange="toggleUserSelection()" style="margin-left: 20px;">
                                            <label for="scope_selected">指定用户</label>
                                        </td>
                                    </tr>
                                    <tr id="user_selection_row" style="display: none;">
                                        <td>选择用户</td>
                                        <td>
                                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ccc; padding: 10px;">
                                                <?php foreach ($allUsers as $user): ?>
                                                    <div style="margin-bottom: 5px;">
                                                        <input type="checkbox" name="selected_user_ids[]" value="<?php echo $user['id']; ?>" id="user_<?php echo $user['id']; ?>">
                                                        <label for="user_<?php echo $user['id']; ?>">
                                                            <?php echo htmlspecialchars($user['username']); ?> (ID: <?php echo $user['id']; ?>)
                                                            <?php if (!empty($user['email'])): ?>
                                                                - <?php echo htmlspecialchars($user['email']); ?>
                                                            <?php endif; ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div style="margin-top: 5px;">
                                                <a href="javascript:void(0);" onclick="selectAllUsers()">全选</a>
                                                <a href="javascript:void(0);" onclick="deselectAllUsers()" style="margin-left: 10px;">取消全选</a>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">
                                            <strong>邮件通知</strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>发送邮件通知</td>
                                        <td>
                                            <input type="checkbox" name="send_email_notification" id="send_email_notification">
                                            <label for="send_email_notification">勾选后发送邮件通知</label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>邮件主题</td>
                                        <td>
                                            <input type="text" name="email_subject" placeholder="请输入邮件主题" style="width: 100%;">
                                            <div>邮件主题将自动加上站点名称前缀</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>邮件内容</td>
                                        <td>
                                            <textarea name="email_content" rows="5" placeholder="请输入邮件内容（支持HTML）" style="width: 100%;"></textarea>
                                            <div>提示：邮件内容将显示在用户名之后，可以使用HTML标签</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">
                                            <strong>站内消息通知</strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>发送站内消息</td>
                                        <td>
                                            <input type="checkbox" name="send_site_notification" id="send_site_notification">
                                            <label for="send_site_notification">勾选后以【系统通知】名义发送站内消息通知</label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>站内消息内容</td>
                                        <td>
                                            <textarea name="site_notification_content" rows="5" placeholder="请输入站内消息内容（支持HTML）" style="width: 100%;"></textarea>
                                            <div>提示：消息将发送到用户的消息列表，用户可以回复</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" align="center">
                                            <button type="submit">发送通知</button>
                                        </td>
                                    </tr>
                                </table>
                            </form>
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<script>
function switchTab(tabName) {
    // 隐藏所有标签页
    document.getElementById('alert-tab').classList.remove('active');
    document.getElementById('notification-tab').classList.remove('active');
    
    // 移除所有标签按钮的活动状态
    var buttons = document.querySelectorAll('.tab-button');
    buttons.forEach(function(button) {
        button.classList.remove('active');
    });
    
    // 显示选中的标签页
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // 激活选中的标签按钮
    event.currentTarget.classList.add('active');
}

function toggleUserSelection() {
    var scopeAll = document.getElementById('scope_all');
    var scopeSelected = document.getElementById('scope_selected');
    var userSelectionRow = document.getElementById('user_selection_row');
    
    if (scopeSelected.checked) {
        userSelectionRow.style.display = '';
    } else {
        userSelectionRow.style.display = 'none';
    }
}

function selectAllUsers() {
    var checkboxes = document.querySelectorAll('input[name="selected_user_ids[]"]');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = true;
    });
}

function deselectAllUsers() {
    var checkboxes = document.querySelectorAll('input[name="selected_user_ids[]"]');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = false;
    });
}
</script>

<?php
// 加载页面底部（包含后台的JS、页脚，完成页面渲染）
include __DIR__ . '/templates/admin_footer.php';
?>