<?php
    date_default_timezone_set('Asia/Shanghai');
/**
 * 修改密码页面
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
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 加载配置和函数
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mail_functions.php';

// 检查安装状态和闭站模式
checkInstall();

// 获取数据库连接和表前缀
$db = getDB();
$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';

// 获取用户信息
$user = $db->fetch(
    "SELECT * FROM `{$prefix}users` WHERE `id` = :id",
    ['id' => $_SESSION['user_id']]
);

if (!$user) {
    header('Location: login.php');
    exit;
}

// 处理表单提交
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // 验证输入
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = '请填写所有字段';
    } else if (!password_verify($currentPassword, $user['password'])) {
        $error = '当前密码不正确';
    } else if (strlen($newPassword) < 6) {
        $error = '新密码长度必须至少为6个字符';
    } else if ($newPassword !== $confirmPassword) {
        $error = '两次输入的新密码不一致';
    } else {
        try {
            // 更新用户密码
            $db->update(
                "{$prefix}users",
                [
                    'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                    'force_password_change' => 0
                ],
                'id = :id',
                ['id' => $user['id']]
            );
            
            $success = '密码修改成功，请使用新密码登录。';
            
            // 记录日志
            logAction('change_password', 'user', $user['id']);
            
            // 发送密码修改邮件通知
            if (!empty($user['email'])) {
                sendPasswordChangeNotification($user['email'], $user['username'], getClientIp());
            }
            
            // 退出登录，让用户重新登录
            session_destroy();
            header('Location: login.php');
            exit;
            
        } catch (Exception $e) {
            $error = '修改密码失败: ' . $e->getMessage();
        }
    }
}

// 设置页面标题
$page_title = '修改密码';

// 加载页面头部
include __DIR__ . '/templates/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><?php echo $user['force_password_change'] == 1 ? '强制修改密码' : '修改密码'; ?></h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="success"><?php echo $success; ?></div>
                        <a href="<?php echo getLoginUrl(); ?>" class="btn btn-primary">登录</a>
                    <?php else: ?>
                        <?php if ($user['force_password_change'] == 1): ?>
                            <div class="alert alert-warning" role="alert">
                                您的密码已被系统重置，请设置新的密码。
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" action="change_password.php">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">当前密码</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">新密码</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                <div class="form-text">密码长度必须至少为6个字符</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">确认新密码</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <div class="form-text">请再次输入您的新密码</div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">修改密码</button>
                            <?php if ($user['force_password_change'] != 1): ?>
                                <a href="<?php echo getHomeUrl(); ?>" class="btn btn-secondary">取消</a>
                            <?php endif; ?>
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