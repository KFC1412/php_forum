<?php
    date_default_timezone_set('Asia/Shanghai');
/**
 * 用户个人资料页面
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
require_once __DIR__ . '/includes/user_verification.php';
require_once __DIR__ . '/includes/user_points.php';
require_once __DIR__ . '/includes/social_functions.php';

// 检查安装状态和闭站模式
checkInstall();

// 检查是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 获取用户ID
$user_id = $_GET['id'] ?? $_SESSION['user_id'];

// 检查是否为系统用户
if ($user_id == 'system' || $user_id == 'info') {
    header('Location: index.php');
    exit;
}

// 获取用户信息
try {
    $db = getDB();
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
    
    $user = $db->fetch(
        "SELECT * FROM `{$prefix}users` WHERE `id` = :id",
        ['id' => $user_id]
    );
    
    if (!$user) {
        header('Location: index.php');
        exit;
    }
    
    // 获取用户统计信息
    $topic_count = $db->fetchColumn(
        "SELECT COUNT(*) FROM `{$prefix}topics` WHERE `user_id` = :user_id AND `status` = 'published'",
        ['user_id' => $user_id]
    );
    
    $post_count = $db->fetchColumn(
        "SELECT COUNT(*) FROM `{$prefix}posts` WHERE `user_id` = :user_id AND `status` = 'published'",
        ['user_id' => $user_id]
    );
    
    // 获取用户最近的主题
    $recent_topics = $db->fetchAll(
        "SELECT * FROM `{$prefix}topics` WHERE `user_id` = :user_id AND `status` = 'published' ORDER BY `created_at` DESC LIMIT 5",
        ['user_id' => $user_id]
    );
    
    // 获取用户最近的回复
    if ($storage_type === 'json') {
        // JSON存储：使用简单查询
        $recent_posts = $db->select('posts', ['user_id' => $user_id, 'status' => 'published'], 'created_at DESC', 5);
        
        // 获取主题信息
        $topics = [];
        $all_topics = $db->select('topics');
        foreach ($all_topics as $t) {
            $topics[$t['id']] = $t;
        }
        
        // 关联主题数据
        foreach ($recent_posts as &$post) {
            $post['topic_title'] = isset($topics[$post['topic_id']]) ? $topics[$post['topic_id']]['title'] : '未知主题';
        }
        unset($post);
    } else {
        // MySQL存储：使用JOIN查询
        $recent_posts = $db->fetchAll(
            "SELECT p.*, t.title as topic_title FROM `{$prefix}posts` p 
            JOIN `{$prefix}topics` t ON p.topic_id = t.id 
            WHERE p.user_id = :user_id AND p.status = 'published' 
            ORDER BY p.created_at DESC LIMIT 5",
            ['user_id' => $user_id]
        );
    }
    
    // 获取用户的最新被驳回的申诉记录
    $rejected_appeal = null;
    if ($storage_type === 'json') {
        $appeals = $db->select('appeals', ['user_id' => $user_id, 'status' => 'rejected'], 'updated_at DESC', 1);
        $rejected_appeal = $appeals[0] ?? null;
    } else {
        $rejected_appeal = $db->fetch(
            "SELECT * FROM `{$prefix}appeals` 
            WHERE `user_id` = :user_id AND `status` = 'rejected' 
            ORDER BY `updated_at` DESC LIMIT 1",
            ['user_id' => $user_id]
        );
    }
    
} catch (Exception $e) {
    $error = '加载用户信息失败: ' . $e->getMessage();
}

// 处理个人资料更新
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id == $_SESSION['user_id']) {
        $email = $_POST['email'] ?? '';
        $mobile = $_POST['mobile'] ?? '';
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // 验证输入
        if (empty($email)) {
            $error = '请填写电子邮件地址';
        } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '请输入有效的电子邮件地址';
        } else if (!empty($new_password) && strlen($new_password) < 6) {
            $error = '新密码长度必须至少为6个字符';
        } else if (!empty($new_password) && $new_password !== $confirm_password) {
            $error = '两次输入的新密码不一致';
        } else if (!empty($new_password) && empty($current_password)) {
            $error = '请输入当前密码';
        } else if (!empty($new_password) && !password_verify($current_password, $user['password'])) {
            $error = '当前密码错误';
        } else {
            try {
                // 检查邮箱是否已被其他用户使用
                if ($email !== $user['email']) {
                    $exists = $db->fetchColumn(
                        "SELECT COUNT(*) FROM `{$prefix}users` WHERE `email` = :email AND `id` != :id",
                        ['email' => $email, 'id' => $user_id]
                    );
                    
                    if ($exists) {
                        $error = '电子邮件地址已被其他用户使用';
                    }
                }
                
                if (empty($error)) {
                    // 更新用户信息
                    $update_data = ['email' => $email];
                    
                    $log_details = [
                        'username' => $user['username'],
                        'old_email' => $user['email'],
                        'new_email' => $email,
                        'update_time' => date('Y-m-d H:i:s'),
                        'update_ip' => getClientIp()
                    ];
                    
                    if (!empty($new_password)) {
                        $update_data['password'] = password_hash($new_password, PASSWORD_DEFAULT);
                        $log_details['password_changed'] = true;
                    } else {
                        $log_details['password_changed'] = false;
                    }
                    
                    $db->update(
                        "{$prefix}users",
                        $update_data,
                        "`id` = :id",
                        ['id' => $user_id]
                    );
                    
                    // 记录更新日志
                    logAction('用户更新个人资料', 'user', $user_id, $log_details);
                    
                    $success = '个人资料已更新';
                    
                    // 重新获取用户信息
                    $user = $db->fetch(
                        "SELECT * FROM `{$prefix}users` WHERE `id` = :id",
                        ['id' => $user_id]
                    );
                }
            } catch (Exception $e) {
                $error = '更新个人资料失败: ' . $e->getMessage();
            }
        }
    }

// 设置页面标题
$page_title = '用户资料: ' . $user['username'];

// 加载页面头部
include __DIR__ . '/templates/header.php';
?>

<table border="1" width="100%" cellspacing="0" cellpadding="10">
    <?php if (isset($error) && !isset($user)): ?>
        <tr>
            <td><?php echo $error; ?></td>
        </tr>
    <?php else: ?>
        <tr>
            <td colspan="2">
                <table width="100%" cellspacing="0" cellpadding="0">
                    <tr>
                        <td><h1><?php echo htmlspecialchars($user['username']); ?> 的个人资料</h1></td>
                        <td align="right">
                            <a href="<?php echo getHomeUrl(); ?>">首页</a> &gt; 用户资料
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        
        <tr>
            <td width="30%" valign="top">
                <table border="1" width="100%" cellspacing="0" cellpadding="5">
                    <tr>
                        <td colspan="2" align="center">
                            <h5>基本信息</h5>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $user_id): ?>
                                <a href="javascript:void(0);" onclick="openReportModal('user', <?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>')" style="display: inline-block; margin-top: 5px; padding: 1px 5px; background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 3px; text-decoration: none; color: #333;">举报用户</a>
                                <a href="javascript:void(0);" onclick="sendMessageToUser(<?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>')" style="display: inline-block; margin-top: 5px; margin-left: 10px; padding: 1px 5px; background-color: #4A90E2; border: 1px solid #4A90E2; border-radius: 3px; text-decoration: none; color: white;">发送私信</a>
                                <?php if (isFollowing($_SESSION['user_id'], $user_id)): ?>
                                    <a href="javascript:void(0);" onclick="unfollowUser(<?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>')" style="display: inline-block; margin-top: 5px; margin-left: 10px; padding: 1px 5px; background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 3px; text-decoration: none; color: #333;">取消关注</a>
                                <?php else: ?>
                                    <a href="javascript:void(0);" onclick="followUser(<?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>')" style="display: inline-block; margin-top: 5px; margin-left: 10px; padding: 1px 5px; background-color: #4A90E2; border: 1px solid #4A90E2; border-radius: 3px; text-decoration: none; color: white;">关注</a>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id && isset($user['status']) && $user['status'] === 'restricted'): ?>
                                <a href="javascript:void(0);" onclick="openAppealModal('user', <?php echo $user['id']; ?>, '<?php echo addslashes($user['username']); ?>')" style="display: inline-block; margin-top: 10px; margin-left: 10px; padding: 5px 10px; background-color: #ff9800; border: 1px solid #ff9800; border-radius: 3px; text-decoration: none; color: white;">申诉</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" align="center">
                            <div style="margin-top: 10px;">
                                <?php 
                                $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
                                $follower_count = $db->fetchColumn(
                                    "SELECT COUNT(*) FROM `{$prefix}user_follows` WHERE `followed_id` = :user_id",
                                    ['user_id' => $user_id]
                                );
                                $following_count = $db->fetchColumn(
                                    "SELECT COUNT(*) FROM `{$prefix}user_follows` WHERE `follower_id` = :user_id",
                                    ['user_id' => $user_id]
                                );
                                ?>
                                <a href="#" onclick="showFollowers(<?php echo $user_id; ?>)" style="margin: 0 5px; text-decoration: none; color: #333;">粉丝: <strong><?php echo $follower_count; ?></strong></a>
                                <a href="#" onclick="showFollowing(<?php echo $user_id; ?>)" style="margin: 0 5px; text-decoration: none; color: #333;">关注: <strong><?php echo $following_count; ?></strong></a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" align="center">
                            <img src="<?php echo htmlspecialchars(getUserAvatar($user, 100)); ?>" alt="<?php echo htmlspecialchars($user['username']); ?>" style="width: 100px; height: 100px;" onerror="this.src='/icon.png'; this.onerror=null;">
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id): ?>
                                <br><button type="button" onclick="openAvatarUploadModal()" style="margin-top: 10px; padding: 5px 10px; background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 3px; cursor: pointer;">修改头像</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>用户ID</td>
                        <td><?php echo $user['id']; ?></td>
                    </tr>
                    <tr>
                        <td>用户名</td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                    </tr>
                    <tr>
                        <td>角色</td>
                        <td>
                            <?php if ($user['role'] === 'admin'): ?>
                                <span style="color: red;">管理员</span>
                            <?php elseif ($user['role'] === 'moderator'): ?>
                                <span style="color: blue;">版主</span>
                            <?php else: ?>
                                普通用户
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>状态</td>
                        <td>
                            <?php if (isset($user['status']) && $user['status'] === 'restricted'): ?>
                                <span style="color: white; background-color: red; padding: 2px 5px; border-radius: 3px;">已限制</span>
                                <?php if ($rejected_appeal): ?>
                                    <br><small style="color: red; margin-top: 5px; display: block;">驳回原因: <?php echo htmlspecialchars($rejected_appeal['process_note']); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                正常
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>注册时间</td>
                        <td><?php echo formatDateTime($user['created_at'], 'Y-m-d'); ?></td>
                    </tr>
                    <tr>
                        <td>主题数</td>
                        <td><?php echo $topic_count; ?></td>
                    </tr>
                    <tr>
                        <td>回复数</td>
                        <td><?php echo $post_count; ?></td>
                    </tr>
                    <tr>
                        <td>积分</td>
                        <td><?php echo getUserPoints($user_id); ?></td>
                    </tr>
                    <tr>
                        <td>经验值</td>
                        <td><?php echo getUserExperience($user_id); ?></td>
                    </tr>
                    <tr>
                        <td>等级</td>
                        <td><?php echo getUserLevel($user_id); ?></td>
                    </tr>
                    <tr>
                        <td>徽章</td>
                        <td>
                            <?php 
                            if (empty($user['badge'])) {
                                echo '暂无徽章';
                            } else {
                                echo htmlspecialchars($user['badge']);
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>实名认证</td>
                        <td>
                            <?php 
                            $verification_status = getUserVerificationStatus($user_id);
                            echo $verification_status['real_name_verified'] ? 
                                '<span style="color: green;">已认证</span>' : 
                                '<span style="color: gray;">未认证</span>';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>专业认证</td>
                        <td>
                            <?php 
                            echo $verification_status['professional_verified'] ? 
                                '<span style="color: green;">已认证</span> (' . htmlspecialchars($verification_status['profession'] ?? '') . ')' : 
                                '<span style="color: gray;">未认证</span>';
                            ?>
                        </td>
                    </tr>
                </table>
            </td>
            
            <td width="70%" valign="top">
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id): ?>
                    <table border="1" width="100%" cellspacing="0" cellpadding="5" style="margin-bottom: 10px;">
                        <tr>
                            <td colspan="2"><h5>编辑个人资料</h5></td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <?php if (!empty($error)): ?>
                            <div class="error"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if (!empty($success)): ?>
                            <div class="success"><?php echo $success; ?></div>
                        <?php endif; ?>
                                
                                <form method="post" action="<?php echo getUserProfileUrl($user_id); ?>">
                                    <table width="100%" cellspacing="0" cellpadding="5">
                                        <tr>
                                            <td>电子邮箱</td>
                                            <td><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required style="width: 100%;"></td>
                                        </tr>
                                        <tr>
                                            <td>手机号</td>
                                            <td><?php echo htmlspecialchars($user['mobile'] ?? ''); ?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2"><h6>修改密码（如不修改请留空）</h6></td>
                                        </tr>
                                        <tr>
                                            <td>当前密码</td>
                                            <td><input type="password" name="current_password" style="width: 100%;"></td>
                                        </tr>
                                        <tr>
                                            <td>新密码</td>
                                            <td><input type="password" name="new_password" minlength="6" style="width: 100%;"></td>
                                        </tr>
                                        <tr>
                                            <td>确认新密码</td>
                                            <td><input type="password" name="confirm_password" minlength="6" style="width: 100%;"></td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <button type="submit">保存修改</button>
                                            </td>
                                        </tr>
                                    </table>
                                </form>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- 认证申请 -->
                    <?php if (!$verification_status['real_name_verified'] || !$verification_status['professional_verified']): ?>
                    <table border="1" width="100%" cellspacing="0" cellpadding="5" style="margin-bottom: 10px;">
                        <tr>
                            <td colspan="2"><h5>认证申请</h5></td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding: 10px;">
                                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                    <?php if (!$verification_status['real_name_verified']): ?>
                                        <button type="button" onclick="openRealNameVerificationModal()" style="padding: 8px 15px; background-color: #4A90E2; color: white; border: none; border-radius: 3px; cursor: pointer; white-space: nowrap;">提交实名认证</button>
                                    <?php endif; ?>
                                    
                                    <?php if (!$verification_status['professional_verified']): ?>
                                        <button type="button" onclick="openProfessionalVerificationModal()" style="padding: 8px 15px; background-color: #4A90E2; color: white; border: none; border-radius: 3px; cursor: pointer; white-space: nowrap;">提交专业认证</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    </table>
                    <?php endif; ?>
                <?php endif; ?>
            
            <table border="1" width="100%" cellspacing="0" cellpadding="5" style="margin-bottom: 10px;">
                <tr>
                    <td colspan="2"><h5>最近的主题</h5></td>
                </tr>
                <tr>
                    <td colspan="2">
                        <?php if (count($recent_topics) > 0): ?>
                            <?php foreach ($recent_topics as $topic): ?>
                                <table border="1" width="100%" cellspacing="0" cellpadding="5" style="margin-bottom: 5px;">
                                    <tr>
                                        <td colspan="2">
                                            <a href="<?php echo getTopicUrl($topic['id'], null, $topic['title']); ?>"><?php echo htmlspecialchars($topic['title']); ?></a>
                                            <br>
                                            <small><?php echo formatDateTime($topic['created_at']); ?></small>
                                            <?php if ($topic['user_id'] == $_SESSION['user_id'] ||$_SESSION['role'] == 'admin'): ?>
                                                <a href="delete.php?type=topic&id=<?php echo $topic['id']; ?>&redirect=user.php" 
                                                   class="confirm-action" 
                                                   data-confirm-message="确定要删除这个主题吗？这将删除所有相关回复。">
                                                    删除
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2"><?php echo mb_substr(strip_tags($topic['content']), 0, 100) . '...'; ?></td>
                                    </tr>
                                </table>
                            <?php endforeach; ?>
                        <?php else: ?>
                            暂无主题
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                <tr>
                    <td colspan="2"><h5>最近的回复</h5></td>
                </tr>
                <tr>
                    <td colspan="2">
                        <?php if (count($recent_posts) > 0): ?>
                            <?php foreach ($recent_posts as $post): ?>
                                <table border="1" width="100%" cellspacing="0" cellpadding="5" style="margin-bottom: 5px;">
                                    <tr>
                                        <td colspan="2">
                                            <a href="<?php echo getTopicUrl($post['topic_id'], null, $post['topic_title']); ?>#post-<?php echo $post['id']; ?>">回复: <?php echo htmlspecialchars($post['topic_title']); ?></a>
                                            <br>
                                            <small><?php echo formatDateTime($post['created_at']); ?></small>
                                            <?php if ($post['user_id'] == $_SESSION['user_id'] ||$_SESSION['role'] == 'admin'): ?>
                                                <a href="delete.php?type=post&id=<?php echo $post['id']; ?>&redirect=user.php" 
                                                   class="confirm-action" 
                                                   data-confirm-message="确定要删除这个回复吗？">
                                                    删除
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="2"><?php echo mb_substr(strip_tags($post['content']), 0, 100) . '...'; ?></td>
                                    </tr>
                                </table>
                            <?php endforeach; ?>
                        <?php else: ?>
                            暂无回复
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <?php endif; ?>
</table>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 删除确认对话框
    const confirmButtons = document.querySelectorAll('.confirm-action');
    confirmButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const confirmMessage = this.getAttribute('data-confirm-message') || '确定要执行此操作吗？';
            
            if (confirm(confirmMessage)) {
                window.location.href = this.href;
            }
        });
    });
});
</script>

<!-- 头像上传弹窗 -->
<div id="avatarUploadModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: white; padding: 20px; border-radius: 5px; width: 400px; max-width: 90%;">
        <h3 style="margin-top: 0; margin-bottom: 20px;">修改头像</h3>
        
        <div style="margin-bottom: 20px;">
            <h4>选择上传方式：</h4>
            <div style="margin-top: 10px;">
                <label><input type="radio" name="avatarUploadType" value="file" checked> 文件上传</label>
                <label style="margin-left: 20px;"><input type="radio" name="avatarUploadType" value="url"> URL链接</label>
            </div>
        </div>
        
        <!-- 文件上传选项 -->
        <div id="fileUploadOption" style="margin-bottom: 20px;">
            <input type="file" id="avatarFile" accept="image/*" style="width: 100%;">
            <p style="font-size: 12px; color: #666; margin-top: 5px;">支持JPG、PNG、GIF格式，建议尺寸100x100</p>
        </div>
        
        <!-- URL上传选项 -->
        <div id="urlUploadOption" style="margin-bottom: 20px; display: none;">
            <input type="text" id="avatarUrl" placeholder="输入头像URL或相对路径" style="width: 100%; padding: 5px;">
            <p style="font-size: 12px; color: #666; margin-top: 5px;">支持完整URL或相对路径，如：/upload/avatar.jpg</p>
        </div>
        
        <div style="text-align: right;">
            <button type="button" onclick="closeAvatarUploadModal()" style="padding: 5px 15px; margin-right: 10px; background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 3px; cursor: pointer;">取消</button>
            <button type="button" onclick="uploadAvatar()" style="padding: 5px 15px; background-color: #4A90E2; color: white; border: none; border-radius: 3px; cursor: pointer;">确定</button>
        </div>
    </div>
</div>

<script>
// 打开头像上传弹窗
function openAvatarUploadModal() {
    document.getElementById('avatarUploadModal').style.display = 'block';
}

// 关闭头像上传弹窗
function closeAvatarUploadModal() {
    document.getElementById('avatarUploadModal').style.display = 'none';
    // 重置表单
    document.getElementById('avatarFile').value = '';
    document.getElementById('avatarUrl').value = '';
    document.querySelector('input[name="avatarUploadType"][value="file"]').checked = true;
    document.getElementById('fileUploadOption').style.display = 'block';
    document.getElementById('urlUploadOption').style.display = 'none';
}

// 切换上传方式
document.addEventListener('DOMContentLoaded', function() {
    const radioButtons = document.querySelectorAll('input[name="avatarUploadType"]');
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'file') {
                document.getElementById('fileUploadOption').style.display = 'block';
                document.getElementById('urlUploadOption').style.display = 'none';
            } else {
                document.getElementById('fileUploadOption').style.display = 'none';
                document.getElementById('urlUploadOption').style.display = 'block';
            }
        });
    });
});

// 上传头像
function uploadAvatar() {
    const uploadType = document.querySelector('input[name="avatarUploadType"]:checked').value;
    
    if (uploadType === 'file') {
        const fileInput = document.getElementById('avatarFile');
        const file = fileInput.files[0];
        
        if (!file) {
            alert('请选择一个图片文件');
            return;
        }
        
        // 验证文件类型
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            alert('只支持JPG、PNG、GIF格式的图片');
            return;
        }
        
        // 创建FormData对象
        const formData = new FormData();
        formData.append('avatar', file);
        formData.append('upload_type', 'file');
        
        // 发送AJAX请求
        fetch('upload_avatar.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('头像上传成功');
                // 刷新页面
                location.reload();
            } else {
                alert('头像上传失败：' + data.message);
            }
        })
        .catch(error => {
            alert('上传失败：' + error.message);
        });
    } else {
        const avatarUrl = document.getElementById('avatarUrl').value.trim();
        
        if (!avatarUrl) {
            alert('请输入头像URL或相对路径');
            return;
        }
        
        // 发送AJAX请求
        fetch('upload_avatar.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'avatar_url=' + encodeURIComponent(avatarUrl) + '&upload_type=url'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('头像设置成功');
                // 刷新页面
                location.reload();
            } else {
                alert('头像设置失败：' + data.message);
            }
        })
        .catch(error => {
            alert('设置失败：' + error.message);
        });
    }
}
</script>

<!-- 举报弹窗 -->
<div id="reportModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: white; padding: 20px; border-radius: 5px; width: 400px; max-width: 90%;">
        <h3 style="margin-top: 0; margin-bottom: 20px;">举报</h3>
        <form id="reportForm" method="post" action="report.php">
            <input type="hidden" name="report_type" id="reportType">
            <input type="hidden" name="target_id" id="targetId">
            <div style="margin-bottom: 15px;">
                <label for="reportReason" style="display: block; margin-bottom: 5px;">举报原因：</label>
                <textarea id="reportReason" name="reason" rows="4" style="width: 100%; padding: 5px; resize: vertical;" required></textarea>
            </div>
            <div style="text-align: right;">
                <button type="button" onclick="closeReportModal()" style="padding: 5px 15px; margin-right: 10px; background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 3px; cursor: pointer;">取消</button>
                <button type="submit" style="padding: 5px 15px; background-color: #4A90E2; color: white; border: none; border-radius: 3px; cursor: pointer;">提交举报</button>
            </div>
        </form>
    </div>
</div>

<!-- 申诉弹窗 -->
<div id="appealModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: white; padding: 20px; border-radius: 5px; width: 400px; max-width: 90%;">
        <h3 style="margin-top: 0; margin-bottom: 20px;">申诉</h3>
        <form id="appealForm" method="post" action="appeal.php">
            <input type="hidden" name="appeal_type" id="appealType">
            <input type="hidden" name="target_id" id="appealTargetId">
            <div style="margin-bottom: 15px;">
                <label for="appealReason" style="display: block; margin-bottom: 5px;">申诉原因：</label>
                <textarea id="appealReason" name="reason" rows="4" style="width: 100%; padding: 5px; resize: vertical;" placeholder="请详细说明申诉原因..." required></textarea>
            </div>
            <div style="text-align: right;">
                <button type="button" onclick="closeAppealModal()" style="padding: 5px 15px; margin-right: 10px; background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 3px; cursor: pointer;">取消</button>
                <button type="submit" style="padding: 5px 15px; background-color: #ff9800; color: white; border: none; border-radius: 3px; cursor: pointer;">提交申诉</button>
            </div>
        </form>
    </div>
</div>

<script>
// 打开举报弹窗
function openReportModal(type, id, name) {
    document.getElementById('reportType').value = type;
    document.getElementById('targetId').value = id;
    document.getElementById('reportModal').style.display = 'block';
}

// 关闭举报弹窗
function closeReportModal() {
    document.getElementById('reportModal').style.display = 'none';
    document.getElementById('reportForm').reset();
}

// 处理举报表单提交
document.getElementById('reportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('report.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('举报成功，我们会尽快处理');
            closeReportModal();
        } else {
            alert('举报失败：' + data.message);
        }
    })
    .catch(error => {
        alert('提交失败：' + error.message);
    });
});

// 打开申诉弹窗
function openAppealModal(type, id, name) {
    document.getElementById('appealType').value = type;
    document.getElementById('appealTargetId').value = id;
    document.getElementById('appealModal').style.display = 'block';
}

// 关闭申诉弹窗
function closeAppealModal() {
    document.getElementById('appealModal').style.display = 'none';
    document.getElementById('appealForm').reset();
}

// 处理申诉表单提交
document.getElementById('appealForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('appeal.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('申诉提交成功，管理员会尽快审核');
            closeAppealModal();
        } else {
            alert('申诉失败：' + data.message);
        }
    })
    .catch(error => {
        alert('提交失败：' + error.message);
    });
});
</script>

<!-- 实名认证弹窗 -->
<div id="realNameVerificationModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: white; padding: 20px; border-radius: 5px; width: 500px; max-width: 90%;">
        <h3 style="margin-top: 0; margin-bottom: 20px;">实名认证</h3>
        <form id="realNameVerificationForm" method="post" action="verification.php" enctype="multipart/form-data">
            <input type="hidden" name="type" value="real_name">
            <div style="margin-bottom: 15px;">
                <label for="realName" style="display: block; margin-bottom: 5px;">真实姓名：</label>
                <input type="text" id="realName" name="real_name" style="width: 100%; padding: 5px;" required>
            </div>
            <div style="margin-bottom: 15px;">
                <label for="idCard" style="display: block; margin-bottom: 5px;">身份证号：</label>
                <input type="text" id="idCard" name="id_card" style="width: 100%; padding: 5px;" required>
            </div>
            <div style="margin-bottom: 15px;">
                <label for="idCardFront" style="display: block; margin-bottom: 5px;">身份证正面照片：</label>
                <input type="file" id="idCardFront" name="id_card_front" accept="image/*" style="width: 100%;" required>
            </div>
            <div style="margin-bottom: 15px;">
                <label for="idCardBack" style="display: block; margin-bottom: 5px;">身份证反面照片：</label>
                <input type="file" id="idCardBack" name="id_card_back" accept="image/*" style="width: 100%;" required>
            </div>
            <div style="text-align: right;">
                <button type="button" onclick="closeRealNameVerificationModal()" style="padding: 5px 15px; margin-right: 10px; background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 3px; cursor: pointer;">取消</button>
                <button type="submit" style="padding: 5px 15px; background-color: #4A90E2; color: white; border: none; border-radius: 3px; cursor: pointer;">提交认证</button>
            </div>
        </form>
    </div>
</div>

<!-- 专业认证弹窗 -->
<div id="professionalVerificationModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: white; padding: 20px; border-radius: 5px; width: 500px; max-width: 90%;">
        <h3 style="margin-top: 0; margin-bottom: 20px;">专业认证</h3>
        <form id="professionalVerificationForm" method="post" action="verification.php" enctype="multipart/form-data">
            <input type="hidden" name="type" value="professional">
            <div style="margin-bottom: 15px;">
                <label for="profession" style="display: block; margin-bottom: 5px;">专业领域：</label>
                <input type="text" id="profession" name="profession" style="width: 100%; padding: 5px;" required>
            </div>
            <div style="margin-bottom: 15px;">
                <label for="qualification" style="display: block; margin-bottom: 5px;">资质证明：</label>
                <input type="text" id="qualification" name="qualification" style="width: 100%; padding: 5px;" required>
            </div>
            <div style="margin-bottom: 15px;">
                <label for="certificate" style="display: block; margin-bottom: 5px;">证书照片：</label>
                <input type="file" id="certificate" name="certificate" accept="image/*" style="width: 100%;" required>
            </div>
            <div style="margin-bottom: 15px;">
                <label for="description" style="display: block; margin-bottom: 5px;">个人简介：</label>
                <textarea id="description" name="description" rows="4" style="width: 100%; padding: 5px; resize: vertical;"></textarea>
            </div>
            <div style="text-align: right;">
                <button type="button" onclick="closeProfessionalVerificationModal()" style="padding: 5px 15px; margin-right: 10px; background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 3px; cursor: pointer;">取消</button>
                <button type="submit" style="padding: 5px 15px; background-color: #4A90E2; color: white; border: none; border-radius: 3px; cursor: pointer;">提交认证</button>
            </div>
        </form>
    </div>
</div>

<script>
// 打开实名认证弹窗
function openRealNameVerificationModal() {
    document.getElementById('realNameVerificationModal').style.display = 'block';
}

// 关闭实名认证弹窗
function closeRealNameVerificationModal() {
    document.getElementById('realNameVerificationModal').style.display = 'none';
    document.getElementById('realNameVerificationForm').reset();
}

// 打开专业认证弹窗
function openProfessionalVerificationModal() {
    document.getElementById('professionalVerificationModal').style.display = 'block';
}

// 关闭专业认证弹窗
function closeProfessionalVerificationModal() {
    document.getElementById('professionalVerificationModal').style.display = 'none';
    document.getElementById('professionalVerificationForm').reset();
}

// 处理实名认证表单提交
document.getElementById('realNameVerificationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('verification.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('实名认证申请提交成功，管理员会尽快审核');
            closeRealNameVerificationModal();
            // 刷新页面
            location.reload();
        } else {
            alert('提交失败：' + data.message);
        }
    })
    .catch(error => {
        alert('提交失败：' + error.message);
    });
});

// 处理专业认证表单提交
document.getElementById('professionalVerificationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('verification.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('专业认证申请提交成功，管理员会尽快审核');
            closeProfessionalVerificationModal();
            // 刷新页面
            location.reload();
        } else {
            alert('提交失败：' + data.message);
        }
    })
    .catch(error => {
        alert('提交失败：' + error.message);
    });
});

// 关注用户
function followUser(userId, username) {
    if (confirm('确定要关注 ' + username + ' 吗？')) {
        fetch('social.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'action=follow&user_id=' + userId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('关注成功');
                location.reload();
            } else {
                alert('关注失败：' + data.message);
            }
        })
        .catch(error => {
            alert('操作失败：' + error.message);
        });
    }
}

// 取消关注用户
function unfollowUser(userId, username) {
    if (confirm('确定要取消关注 ' + username + ' 吗？')) {
        fetch('social.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'action=unfollow&user_id=' + userId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('取消关注成功');
                location.reload();
            } else {
                alert('取消关注失败：' + data.message);
            }
        })
        .catch(error => {
            alert('操作失败：' + error.message);
        });
    }
}

// 发送私信
function sendMessageToUser(userId, username) {
    const content = prompt('请输入私信内容：');
    if (content && content.trim()) {
        fetch('social.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'action=send_message&receiver_id=' + userId + '&content=' + encodeURIComponent(content)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('私信发送成功');
            } else {
                alert('私信发送失败：' + data.message);
            }
        })
        .catch(error => {
            alert('操作失败：' + error.message);
        });
    }
}

// 显示关注列表
function showFollowing(userId) {
    fetch('social.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=get_following&user_id=' + userId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let html = '<h3>关注列表</h3>';
            if (data.users.length > 0) {
                html += '<ul style="list-style: none; padding: 0;">';
                data.users.forEach(user => {
                    html += '<li style="margin-bottom: 10px;"><a href="profile.php?id=' + user.id + '">' + user.username + ' (ID: ' + user.id + ')</a></li>';
                });
                html += '</ul>';
            } else {
                html += '<p>暂无关注用户</p>';
            }
            alert(html.replace(/<[^>]*>/g, ''));
        } else {
            alert('获取关注列表失败：' + data.message);
        }
    })
    .catch(error => {
        alert('操作失败：' + error.message);
    });
}

// 显示粉丝列表
function showFollowers(userId) {
    fetch('social.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=get_followers&user_id=' + userId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let html = '<h3>粉丝列表</h3>';
            if (data.users.length > 0) {
                html += '<ul style="list-style: none; padding: 0;">';
                data.users.forEach(user => {
                    html += '<li style="margin-bottom: 10px;"><a href="profile.php?id=' + user.id + '">' + user.username + ' (ID: ' + user.id + ')</a></li>';
                });
                html += '</ul>';
            } else {
                html += '<p>暂无粉丝</p>';
            }
            alert(html.replace(/<[^>]*>/g, ''));
        } else {
            alert('获取粉丝列表失败：' + data.message);
        }
    })
    .catch(error => {
        alert('操作失败：' + error.message);
    });
}
</script>

<?php
// 加载页面底部
include __DIR__ . '/templates/footer.php';
?>
</body>