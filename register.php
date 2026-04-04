<?php
    date_default_timezone_set('Asia/Shanghai');
/**
 * 注册页面 - 支持伪静态URL
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

// 检查是否已登录
if (isset($_SESSION['user_id'])) {
    header('Location: ' . getHomeUrl());
    exit;
}

// 加载配置和函数
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mail_functions.php';

// 检查安装状态和闭站模式
checkInstall();

// 检查是否允许注册
$allow_registration = getSetting('allow_registration', '1');
if ($allow_registration !== '1') {
    $error = '当前不允许注册新用户';
} else {
    $error = '';
}

// 处理注册表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allow_registration === '1') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $mobile = $_POST['mobile'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // 验证输入
    if (empty($username) || empty($email) || empty($mobile) || empty($password) || empty($confirm_password)) {
        $error = '请填写所有必填字段';
    } else if (mb_strlen($username, 'UTF-8') < 2 || mb_strlen($username, 'UTF-8') > 20) {
        $error = '用户名长度必须在2-20个字符之间';
    } else if (!preg_match('/^[\x{4e00}-\x{9fa5}a-zA-Z0-9_]+$/u', $username)) {
        $error = '用户名只能包含中文、字母、数字和下划线';
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '电子邮箱格式不正确';
    } else if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
        $error = '手机号格式不正确';
    } else if (strlen($password) < 6) {
        $error = '密码长度必须至少为6个字符';
    } else if ($password !== $confirm_password) {
        $error = '两次输入的密码不一致';
    } else {
        try {
            $db = getDB();
            $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
            
            // 检查用户名是否已存在
            $exists = $db->fetchColumn(
                "SELECT COUNT(*) FROM `{$prefix}users` WHERE `username` = :username",
                ['username' => $username]
            );
            
            if ($exists) {
                $error = '该用户名已被使用';
            } else {
                // 检查邮箱是否已存在
                $exists = $db->fetchColumn(
                    "SELECT COUNT(*) FROM `{$prefix}users` WHERE `email` = :email",
                    ['email' => $email]
                );
                
                if ($exists) {
                    $error = '该电子邮箱已被使用';
                } else {
                    // 检查手机号是否已存在
                    $exists = $db->fetchColumn(
                        "SELECT COUNT(*) FROM `{$prefix}users` WHERE `mobile` = :mobile",
                        ['mobile' => $mobile]
                    );
                    
                    if ($exists) {
                        $error = '该手机号已被使用';
                    } else {
                        // 获取IP地址和详细信息
                        $ip_address = getClientIp();
                        $ip_info = getIpInfo($ip_address);
                        
                        // 创建用户
                        $db->insert("{$prefix}users", [
                            'username' => $username,
                            'email' => $email,
                            'mobile' => $mobile,
                            'password' => password_hash($password, PASSWORD_DEFAULT),
                            'role' => 'user',
                            'status' => 'active',
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                            'created_ip' => $ip_address,
                            'ip_info' => $ip_info ? json_encode($ip_info) : null
                        ]);
                        
                        $user_id = $db->lastInsertId();
                        
                        // 记录注册日志
                        logAction('用户注册', 'user', $user_id, [
                            'username' => $username,
                            'email' => $email,
                            'mobile' => $mobile,
                            'register_ip' => getClientIp(),
                            'register_time' => date('Y-m-d H:i:s')
                        ]);
                        
                        // 发送注册成功邮件通知
                        $mailResult = sendRegisterNotification($email, $username);
                        
                        // 自动登录
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['username'] = $username;
                        $_SESSION['role'] = 'user';
                        
                        // 重定向到首页
                        header('Location: ' . getHomeUrl());
                        exit;
                    }
                }
            }
        } catch (Exception $e) {
            $error = '注册失败: ' . $e->getMessage();
        }
    }
}

// 设置页面标题
$page_title = '用户注册';

// 加载页面头部
include __DIR__ . '/templates/header.php';
?>

<table border="1" width="100%" cellspacing="0" cellpadding="10">
    <tr>
        <td align="center">
            <table border="1" width="50%" cellspacing="0" cellpadding="10">
                <tr>
                    <td align="center"><h5>用户注册</h5></td>
                </tr>
                <tr>
                    <td>
                        <?php if ($allow_registration !== '1'): ?>
                            <div class="info">当前不允许注册新用户</div>
                        <?php else: ?>
                            <?php if (!empty($error)): ?>
                                <div class="error"><?php echo $error; ?></div>
                            <?php endif; ?>
                            
                            <form method="post" action="<?php echo getRegisterUrl(); ?>">
                                <table width="100%" cellspacing="0" cellpadding="5">
                                    <tr>
                                        <td>用户名</td>
                                        <td><input type="text" name="username" required minlength="2" maxlength="20" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" style="width: 100%;"></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2"><small>用户名可包含中文、字母、数字和下划线，长度在2-20个字符之间</small></td>
                                    </tr>
                                    <tr>
                                        <td>电子邮箱</td>
                                        <td><input type="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" style="width: 100%;"></td>
                                    </tr>
                                    <tr>
                                        <td>手机号</td>
                                        <td><input type="tel" name="mobile" required value="<?php echo isset($_POST['mobile']) ? htmlspecialchars($_POST['mobile']) : ''; ?>" style="width: 100%;"></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2"><small>请输入11位手机号码</small></td>
                                    </tr>
                                    <tr>
                                        <td>密码</td>
                                        <td><input type="password" name="password" required minlength="6" style="width: 100%;"></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2"><small>密码长度必须至少为6个字符</small></td>
                                    </tr>
                                    <tr>
                                        <td>确认密码</td>
                                        <td><input type="password" name="confirm_password" required minlength="6" style="width: 100%;"></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" align="center">
                                            <button type="submit">注册</button>
                                        </td>
                                    </tr>
                                </table>
                            </form>
                            
                            <table width="100%" cellspacing="0" cellpadding="5" style="margin-top: 10px;">
                                <tr>
                                    <td align="center">
                                        已有账号？ <a href="<?php echo getLoginUrl(); ?>">立即登录</a>
                                    </td>
                                </tr>
                            </table>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<?php
// 加载页面底部
include __DIR__ . '/templates/footer.php';
?>
