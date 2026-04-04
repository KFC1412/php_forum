<?php
    date_default_timezone_set('Asia/Shanghai');
/**
 * 登录页面 - 支持伪静态URL
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

// 检查安装状态和闭站模式
checkInstall();

// 检查是否已登录
if (isset($_SESSION['user_id'])) {
    header('Location: ' . getHomeUrl());
    exit;
}

// 处理登录表单提交
$error = '';
$success = '';

// 检查是否有成功提示
if (isset($_GET['success']) && $_GET['success'] === 'password_reset') {
    $success = '新密码已发送到您的邮箱，请使用新密码登录。';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;
    
    // 验证输入
    if (empty($username) || empty($password)) {
        $error = '请填写账号和密码';
    } else {
        try {
            $db = getDB();
            $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
            
            // 查询用户
            $user = $db->fetch(
                "SELECT * FROM `{$prefix}users` WHERE `username` = :username OR `email` = :email OR `mobile` = :mobile",
                [
                    'username' => $username,
                    'email' => $username,
                    'mobile' => $username
                ]
            );
            
            if (!$user || !password_verify($password, $user['password'])) {
                $error = '账号或密码不正确';
                logAction('用户登录失败：用户名或密码错误', 'user', 0, [
                    'username' => $username,
                    'login_ip' => getClientIp(),
                    'login_time' => date('Y-m-d H:i:s')
                ], 'failed');
            } else if ($user['status'] === 'banned') {
                $error = '该账号已被禁用';
                logAction('用户登录失败：账号已被禁用', 'user', $user['id'], [
                    'username' => $user['username'],
                    'status' => $user['status'],
                    'login_ip' => getClientIp(),
                    'login_time' => date('Y-m-d H:i:s')
                ], 'failed');
            } else if ($user['status'] === 'restricted') {
                // 允许被限制的用户登录，但会限制其发布权限
                // 登录成功
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['status'] = $user['status'];
                
                // 记录登录日志
                logAction('被限制用户登录', 'user', $user['id'], [
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'login_ip' => getClientIp(),
                    'login_time' => date('Y-m-d H:i:s')
                ]);
                
                // 发送登录成功邮件通知
                if (!empty($user['email'])) {
                    sendLoginNotification($user['email'], $user['username'], getClientIp());
                }
                
                // 更新最后登录时间和IP
                $db->update("{$prefix}users", [
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_ip' => getClientIp()
                ], '`id` = :id', ['id' => $user['id']]);
                
                // 重定向到首页或之前的页面
                $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : getHomeUrl();
                unset($_SESSION['redirect_after_login']);
                
                header('Location: ' . $redirect);
                exit;
            } else if (getSetting('site_closed', '0') === '1' && $user['role'] !== 'admin') {
                $error = '网站正在维护中，只有管理员可以登录';
                logAction('用户登录失败：网站维护中，非管理员账号无法登录', 'user', $user['id'], [
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'login_ip' => getClientIp(),
                    'login_time' => date('Y-m-d H:i:s')
                ], 'failed');
            } else {
                // 登录成功
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['status'] = $user['status'] ?? 'active';
                
                // 记录登录日志
                logAction('用户登录', 'user', $user['id'], [
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'login_ip' => getClientIp(),
                    'login_time' => date('Y-m-d H:i:s')
                ]);
                
                // 发送登录成功邮件通知
                if (!empty($user['email'])) {
                    sendLoginNotification($user['email'], $user['username'], getClientIp());
                }
                
                // 更新最后登录时间和IP
                $db->update("{$prefix}users", [
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_ip' => getClientIp()
                ], '`id` = :id', ['id' => $user['id']]);
                
                // 如果勾选了"记住我"，设置Cookie
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expires = time() + 30 * 24 * 60 * 60; // 30天
                    
                    // 存储令牌到数据库
                    $db->insert("{$prefix}user_tokens", [
                        'user_id' => $user['id'],
                        'token' => password_hash($token, PASSWORD_DEFAULT),
                        'expires_at' => date('Y-m-d H:i:s', $expires),
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    // 设置Cookie
                    setcookie('remember_token', $user['id'] . ':' . $token, $expires, '/', '', false, true);
                }
                
                // 检查是否需要强制修改密码
                if ($user['force_password_change'] == 1) {
                    // 保存用户信息到会话
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    // 重定向到修改密码页面
                    header('Location: change_password.php');
                    exit;
                } else {
                    // 重定向到首页或之前的页面
                    $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : getHomeUrl();
                    unset($_SESSION['redirect_after_login']);
                    
                    header('Location: ' . $redirect);
                    exit;
                }
            }
        } catch (Exception $e) {
            $error = '登录失败: ' . $e->getMessage();
        }
    }
}

// 设置页面标题
$page_title = '用户登录';

// 加载页面头部
include __DIR__ . '/templates/header.php';
?>

<table border="1" width="100%" cellspacing="0" cellpadding="10">
    <tr>
        <td align="center">
            <table border="1" width="50%" cellspacing="0" cellpadding="10">
                <tr>
                    <td align="center"><h5>用户登录</h5></td>
                </tr>
                <tr>
                    <td>
                        <?php if (!empty($error)): ?>
                            <div class="error"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="post" action="<?php echo getLoginUrl(); ?>">
                            <table width="100%" cellspacing="0" cellpadding="5">
                                <tr>
                                    <td>用户名//邮箱/手机号</td>
                                    <td><input type="text" name="username" required style="width: 100%;"></td>
                                </tr>
                                <tr>
                                    <td>密码</td>
                                    <td><input type="password" name="password" required style="width: 100%;"></td>
                                </tr>
                                <tr>
                                    <td colspan="2" align="center">
                                        <button type="submit">登录</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" align="center">
                                        <a href="<?php echo getForgotPasswordUrl(); ?>">忘记密码？</a>
                                    </td>
                                </tr>
                            </table>
                        </form>
                        
                        <table width="100%" cellspacing="0" cellpadding="5" style="margin-top: 10px;">
                            <tr>
                                <td align="center">
                                    还没有账号？ <a href="<?php echo getRegisterUrl(); ?>">立即注册</a>
                                </td>
                            </tr>
                        </table>
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
