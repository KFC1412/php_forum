<?php
/**
 * 忘记密码页面
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
    header('Location: index.php');
    exit;
}

// 加载配置和函数
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/smtp.php';

// 检查安装状态和闭站模式
checkInstall();

// 获取数据库连接和表前缀
$db = getDB();
$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';

// 生成随机6位字符密码
function generateRandomPassword($length = 6) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $charactersLength = strlen($characters);
    $randomPassword = '';
    for ($i = 0; $i < $length; $i++) {
        $randomPassword .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomPassword;
}

// 处理表单提交
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    // 验证输入
    if (empty($email)) {
        $error = '请输入您的邮箱地址';
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '请输入有效的邮箱地址';
    } else {
        try {
            // 检查邮箱是否存在
            $user = $db->fetch(
                "SELECT * FROM `{$prefix}users` WHERE `email` = :email",
                ['email' => $email]
            );
            
            if (!$user) {
                $error = '该邮箱地址未注册';
            } else {
                // 生成随机6位字符密码
                $newPassword = generateRandomPassword(6);
                
                // 更新用户密码和强制修改密码标志
                $db->update(
                    "{$prefix}users",
                    [
                        'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                        'force_password_change' => 1
                    ],
                    'id = :id',
                    ['id' => $user['id']]
                );
                
                // 构建邮件内容
                $emailSubject = '您的密码已重置';
                $emailMessage = '
                    <p>您好，' . htmlspecialchars($user['username']) . '</p>
                    <p>您的账户密码已被重置为：</p>
                    <p style="font-size: 18px; font-weight: bold; color: #4a6fa5;">' . $newPassword . '</p>
                    <p>账户相关信息：</p>
                    <p>用户名：' . htmlspecialchars($user['username']) . '</p>
                    <p>邮箱：<a href="mailto:' . htmlspecialchars($user['email']) . '" style="color: #4a6fa5; text-decoration: none;">' . htmlspecialchars($user['email']) . '</a></p>
                    <p>注册时间：' . htmlspecialchars($user['created_at']) . '</p>
                    <p>请使用此密码登录，登录后系统将强制您修改密码。</p>
                    <p>如果您没有请求重置密码，请立即联系管理员。</p>
                    <p>此致</p>
                    <p>论坛管理员</p>
                ';
                
                $success = '新密码已发送到您的邮箱，请检查您的邮件。';
                
                // 记录日志
                logAction('reset_password', 'user', $user['id'], ['email' => $email]);
                
                // 发送邮件
                $mailResult = sendEmail($email, $emailSubject, $emailMessage);
                
                if ($mailResult) {
                    // 跳转到登录页面，并传递成功提示
                    header('Location: ' . getLoginUrl() . '?success=password_reset');
                    exit;
                } else {
                    // 邮件发送失败，显示错误信息
                    $error = '邮件发送失败，请稍后重试或联系管理员';
                }
            }
        } catch (Exception $e) {
            $error = '处理请求失败: ' . $e->getMessage();
        }
    }
}

// 设置页面标题
$page_title = '忘记密码';

// 加载页面头部
include __DIR__ . '/templates/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>忘记密码</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <?php if (empty($success)): ?>
                        <form method="post" action="<?php echo getForgotPasswordUrl(); ?>">
                            <div class="mb-3">
                                <label for="email" class="form-label">邮箱地址</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                                <div class="form-text">我们将生成新密码并发送到这个邮箱</div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">发送新密码</button>
                            <a href="<?php echo getLoginUrl(); ?>" class="btn btn-secondary">返回登录</a>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// 加载页面底部
include __DIR__ . '/templates/footer.php';
?>