<?php
    date_default_timezone_set('Asia/Shanghai');
/**
 * 系统设置页面
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
require_once __DIR__ . '/includes/admin_functions.php';

// 检查是否已登录且是管理员
checkAdminAccess();

// 处理表单提交
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $site_name = $_POST['site_name'] ?? '';
        $site_title = $_POST['site_title'] ?? '';
        $site_description = $_POST['site_description'] ?? '';
        $allow_registration = isset($_POST['allow_registration']) ? '1' : '0';
        $site_closed = isset($_POST['site_closed']) ? '1' : '0';
        $site_closed_message = $_POST['site_closed_message'] ?? '网站正在维护中，请稍后访问';
        $topics_per_page = (int)$_POST['topics_per_page'];
        $posts_per_page = (int)$_POST['posts_per_page'];
        $password_reset_expires = (int)$_POST['password_reset_expires'];
        $account_activation_expires = (int)$_POST['account_activation_expires'];
        $email_notification_enabled = isset($_POST['email_notification_enabled']) ? '1' : '0';
        $email_notification_reply = isset($_POST['email_notification_reply']) ? '1' : '0';
        $email_notification_topic = isset($_POST['email_notification_topic']) ? '1' : '0';
        $email_notification_register = isset($_POST['email_notification_register']) ? '1' : '0';
        $email_notification_login = isset($_POST['email_notification_login']) ? '1' : '0';
    
    // 验证输入
    if (empty($site_name)) {
        $error = '站点名称不能为空';
    } elseif (empty($site_title)) {
        $error = '首页标题不能为空';
    } else if ($topics_per_page < 1 || $topics_per_page > 100) {
        $error = '每页主题数必须在1-100之间';
    } else if ($posts_per_page < 1 || $posts_per_page > 100) {
        $error = '每页回复数必须在1-100之间';
    } else {
        try {
            // 更新设置
            setSetting('site_name', $site_name);
            setSetting('site_title', $site_title);
            setSetting('site_description', $site_description);
            setSetting('allow_registration', $allow_registration);
            setSetting('site_closed', $site_closed);
            setSetting('site_closed_message', $site_closed_message);
            setSetting('topics_per_page', $topics_per_page);
            setSetting('posts_per_page', $posts_per_page);
            setSetting('password_reset_expires', $password_reset_expires);
            setSetting('account_activation_expires', $account_activation_expires);
            setSetting('email_notification_enabled', $email_notification_enabled);
            setSetting('email_notification_reply', $email_notification_reply);
            setSetting('email_notification_topic', $email_notification_topic);
            setSetting('email_notification_register', $email_notification_register);
            setSetting('email_notification_login', $email_notification_login);
            
            // 举报设置
            setSetting('report_yellow_threshold', (int)$_POST['report_yellow_threshold']);
            setSetting('report_red_threshold', (int)$_POST['report_red_threshold']);
            setSetting('report_ban_threshold', (int)$_POST['report_ban_threshold']);
            
            // 记录操作日志
            logAdminAction('管理员更新系统设置', 'system', 0, [
                'updated_settings' => array_keys($_POST),
                'admin_id' => $_SESSION['user_id'],
                'admin_username' => $_SESSION['username'],
                'action_time' => date('Y-m-d H:i:s'),
                'ip_address' => getClientIp()
            ]);
            
            $success = '设置已更新';
        } catch (Exception $e) {
            $error = '更新设置失败: ' . $e->getMessage();
        }
    }
}

// 获取当前设置
$site_name = getSetting('site_name', '');
$site_title = getSetting('site_title', '');
$site_description = getSetting('site_description', '');
$allow_registration = getSetting('allow_registration', '1');
$site_closed = getSetting('site_closed', '0');
$site_closed_message = getSetting('site_closed_message', '网站正在维护中，请稍后访问');
$topics_per_page = (int)getSetting('topics_per_page', 20);
$posts_per_page = (int)getSetting('posts_per_page', 15);
$password_reset_expires = (int)getSetting('password_reset_expires', 60);
$account_activation_expires = (int)getSetting('account_activation_expires', 24);
$email_notification_enabled = getSetting('email_notification_enabled', '0');
$email_notification_reply = getSetting('email_notification_reply', '1');
$email_notification_topic = getSetting('email_notification_topic', '1');
$email_notification_register = getSetting('email_notification_register', '1');
$email_notification_login = getSetting('email_notification_login', '1');

// 设置页面标题
$page_title = '系统设置';

// 加载页面头部
include __DIR__ . '/templates/admin_header.php';
?>

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
                        <h1>系统设置</h1>
                    </td>
                </tr>
                
                <?php if (!empty($error)): ?>
                    <tr>
                        <td colspan="2">
                            <strong>错误：</strong><?php echo $error; ?>
                        </td>
                    </tr>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <tr>
                        <td colspan="2">
                            <strong>成功：</strong><?php echo $success; ?>
                        </td>
                    </tr>
                <?php endif; ?>
                
                <tr>
                    <td colspan="2">
                        <form method="post" action="settings.php">
                            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                <tr>
                                    <td width="20%">站点名称</td>
                                    <td>
                                        <input type="text" name="site_name" value="<?php echo htmlspecialchars($site_name); ?>" required>
                                    </td>
                                </tr>
                                <tr>
                                    <td>首页标题</td>
                                    <td>
                                        <input type="text" name="site_title" value="<?php echo htmlspecialchars($site_title); ?>" required>
                                    </td>
                                </tr>
                                <tr>
                                    <td>站点描述</td>
                                    <td>
                                        <textarea name="site_description" rows="3" style="width: 100%; box-sizing: border-box;"><?php echo htmlspecialchars($site_description); ?></textarea>
                                        <div>用于SEO优化，显示在首页标题下方</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>注册设置</td>
                                    <td>
                                        <input type="checkbox" name="allow_registration" <?php echo $allow_registration === '1' ? 'checked' : ''; ?>>
                                        允许新用户注册
                                    </td>
                                </tr>
                                <tr>
                                    <td>闭站设置</td>
                                    <td>
                                        <div style="margin-bottom: 10px;">
                                            <input type="checkbox" name="site_closed" <?php echo $site_closed === '1' ? 'checked' : ''; ?>>
                                            <strong>启用闭站模式</strong>
                                            <div>开启后，只有管理员可以访问网站</div>
                                        </div>
                                        <div style="margin-left: 20px; padding: 10px; border-left: 3px solid #337ab7;">
                                            <div style="margin-bottom: 5px;"><strong>闭站提示信息：</strong></div>
                                            <textarea name="site_closed_message" rows="3" style="width: 100%; box-sizing: border-box;"><?php echo htmlspecialchars($site_closed_message); ?></textarea>
                                            <div>显示给访问者的闭站提示信息</div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>每页显示的主题数</td>
                                    <td>
                                        <input type="number" name="topics_per_page" value="<?php echo $topics_per_page; ?>" min="1" max="100" required>
                                    </td>
                                </tr>
                                <tr>
                                    <td>每页显示的回复数</td>
                                    <td>
                                        <input type="number" name="posts_per_page" value="<?php echo $posts_per_page; ?>" min="1" max="100" required>
                                    </td>
                                </tr>
                                <tr>
                                    <td>密码重置链接有效期</td>
                                    <td>
                                        <input type="number" name="password_reset_expires" 
                                               value="<?php echo $password_reset_expires; ?>" required>
                                        分钟
                                        <div>密码重置链接的有效时间</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>账户激活链接有效期</td>
                                    <td>
                                        <input type="number" name="account_activation_expires" 
                                               value="<?php echo $account_activation_expires; ?>" required>
                                        小时
                                        <div>账户激活链接的有效时间</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>邮件通知设置</td>
                                    <td>
                                        <div style="margin-bottom: 10px;">
                                            <input type="checkbox" name="email_notification_enabled" <?php echo $email_notification_enabled === '1' ? 'checked' : ''; ?>>
                                            <strong>启用邮件通知</strong>
                                            <div>SMTP配置请在 <a href="smtp_settings.php">邮箱配置</a> 页面设置</div>
                                        </div>
                                        <div style="margin-left: 20px; padding: 10px; border-left: 3px solid #337ab7;">
                                            <div style="margin-bottom: 8px;"><strong>通知类型设置：</strong></div>
                                            <div style="margin-bottom: 5px;">
                                                <input type="checkbox" name="email_notification_reply" <?php echo $email_notification_reply === '1' ? 'checked' : ''; ?>>
                                                主题被回复时通知
                                            </div>
                                            <div style="margin-bottom: 5px;">
                                                <input type="checkbox" name="email_notification_topic" <?php echo $email_notification_topic === '1' ? 'checked' : ''; ?>>
                                                主题被编辑时通知
                                            </div>
                                            <div style="margin-bottom: 5px;">
                                                <input type="checkbox" name="email_notification_register" <?php echo $email_notification_register === '1' ? 'checked' : ''; ?>>
                                                用户注册时通知
                                            </div>
                                            <div>
                                                <input type="checkbox" name="email_notification_login" <?php echo $email_notification_login === '1' ? 'checked' : ''; ?>>
                                                用户登录时通知
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>举报设置</td>
                                    <td>
                                        <div style="margin-bottom: 10px;">
                                            <strong>举报阈值设置：</strong>
                                            <div>当举报达到一定数量时，系统会自动标记或处理</div>
                                        </div>
                                        <div style="margin-left: 20px; padding: 10px; border-left: 3px solid #337ab7;">
                                            <div style="margin-bottom: 8px;">
                                                <label>黄标阈值（显示警告标记）：</label>
                                                <input type="number" name="report_yellow_threshold" value="<?php echo (int)getSetting('report_yellow_threshold', 3); ?>" min="1" max="100" required>
                                                <span style="margin-left: 10px; color: #666;">举报数</span>
                                            </div>
                                            <div style="margin-bottom: 8px;">
                                                <label>红标阈值（显示严重警告）：</label>
                                                <input type="number" name="report_red_threshold" value="<?php echo (int)getSetting('report_red_threshold', 5); ?>" min="1" max="100" required>
                                                <span style="margin-left: 10px; color: #666;">举报数</span>
                                            </div>
                                            <div style="margin-bottom: 8px;">
                                                <label>自动封禁阈值：</label>
                                                <input type="number" name="report_ban_threshold" value="<?php echo (int)getSetting('report_ban_threshold', 10); ?>" min="1" max="100" required>
                                                <span style="margin-left: 10px; color: #666;">举报数</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" align="center">
                                        <button type="submit">保存设置</button>
                                    </td>
                                </tr>
                            </table>
                        </form>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<?php
// 加载页面底部
include __DIR__ . '/templates/admin_footer.php';
?>

