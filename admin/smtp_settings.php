<?php
    date_default_timezone_set('Asia/Shanghai');
/**
 * SMTP邮箱设置页面
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
require_once __DIR__ . '/../includes/smtp.php';
require_once __DIR__ . '/includes/admin_functions.php';

// 检查是否已登录且是管理员
checkAdminAccess();

// 获取数据库实例和表前缀
$db = getDB();
$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';

// 获取当前SMTP设置
$smtpSettings = [];
try {
    // 测试数据库连接
    if (!$db) {
        throw new Exception('数据库连接失败');
    }
    
    // 直接获取所有设置，然后筛选SMTP相关的
    $all_settings = $db->fetchAll("SELECT `setting_key`, `setting_value` FROM `{$prefix}settings`");
    foreach ($all_settings as $setting) {
        if (strpos($setting['setting_key'], 'smtp_') === 0) {
            $smtpSettings[$setting['setting_key']] = $setting['setting_value'];
        }
    }
    
    // 验证是否获取到设置
    if (empty($smtpSettings)) {
        // 尝试另一种查询方式
        $smtp_settings = $db->fetchAll("SELECT * FROM `{$prefix}settings` WHERE setting_key LIKE 'smtp_%'");
        foreach ($smtp_settings as $setting) {
            $smtpSettings[$setting['setting_key']] = $setting['setting_value'];
        }
    }
} catch (Exception $e) {
    $error = '加载设置失败: ' . $e->getMessage();
    // 记录错误
    error_log('SMTP设置加载失败: ' . $e->getMessage());
}

// 处理表单提交
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $smtpHost = $_POST['smtp_host'] ?? '';
    $smtpPort = $_POST['smtp_port'] ?? '';
    $smtpSecure = $_POST['smtp_secure'] ?? '';
    $smtpUsername = $_POST['smtp_username'] ?? '';
    $smtpPassword = $_POST['smtp_password'] ?? '';
    $smtpFrom = $_POST['smtp_from'] ?? '';
    $smtpFromName = $_POST['smtp_from_name'] ?? '';
    
    // 验证输入
    if (empty($smtpHost) || empty($smtpPort) || empty($smtpUsername) || empty($smtpFrom)) {
        $error = '请填写所有必填字段';
    } else {
        try {
            // 更新SMTP设置
            $settings = [
                ['setting_key' => 'smtp_host', 'setting_value' => $smtpHost],
                ['setting_key' => 'smtp_port', 'setting_value' => $smtpPort],
                ['setting_key' => 'smtp_secure', 'setting_value' => $smtpSecure],
                ['setting_key' => 'smtp_username', 'setting_value' => $smtpUsername],
                ['setting_key' => 'smtp_from', 'setting_value' => $smtpFrom],
                ['setting_key' => 'smtp_from_name', 'setting_value' => $smtpFromName]
            ];
            
            // **关键修改：密码不进行哈希处理，直接存储**
            if (!empty($smtpPassword)) {
                $settings[] = ['setting_key' => 'smtp_password', 'setting_value' => $smtpPassword]; // 原始密码
            }
            
            foreach ($settings as $setting) {
                $exists = $db->fetch(
                    "SELECT * FROM `{$prefix}settings` WHERE `setting_key` = :setting_key",
                    ['setting_key' => $setting['setting_key']]
                );
                
                if ($exists) {
                    $db->update(
                        "{$prefix}settings",
                        ['setting_value' => $setting['setting_value']],
                        'setting_key = :setting_key',
                        ['setting_key' => $setting['setting_key']]
                    );
                } else {
                    $db->insert(
                        "{$prefix}settings",
                        [
                            'setting_key' => $setting['setting_key'],
                            'setting_value' => $setting['setting_value'],
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]
                    );
                }
            }
            
            // 记录操作日志
            logAction('update_smtp_settings', 'system', 0);
            
            $success = 'SMTP设置更新成功';
            
        } catch (Exception $e) {
            $error = '更新设置失败: ' . $e->getMessage();
        }
    }
}

// 设置页面标题
$page_title = 'SMTP邮箱配置';

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
                        <h1>邮箱配置</h1>
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
                        <form method="post" action="smtp_settings.php">
                            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                <tr>
                                    <td width="20%">SMTP服务器</td>
                                    <td>
                                        <input type="text" name="smtp_host" 
                                               value="<?php echo htmlspecialchars($smtpSettings['smtp_host'] ?? ''); ?>" required>
                                        <div>例如：smtp.gmail.com</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>SMTP端口</td>
                                    <td>
                                        <input type="number" name="smtp_port" 
                                               value="<?php echo htmlspecialchars($smtpSettings['smtp_port'] ?? '587'); ?>" required>
                                        <div>常见端口：587 (TLS), 465 (SSL), 25 (未加密)</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>加密方式</td>
                                    <td>
                                        <select name="smtp_secure">
                                            <option value="" <?php echo (isset($smtpSettings['smtp_secure']) && $smtpSettings['smtp_secure'] == '') ? 'selected' : ''; ?>>无</option>
                                            <option value="tls" <?php echo (isset($smtpSettings['smtp_secure']) && $smtpSettings['smtp_secure'] == 'tls') ? 'selected' : ''; ?>>TLS</option>
                                            <option value="ssl" <?php echo (isset($smtpSettings['smtp_secure']) && $smtpSettings['smtp_secure'] == 'ssl') ? 'selected' : ''; ?>>SSL</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td>邮箱账号</td>
                                    <td>
                                        <input type="email" name="smtp_username" 
                                               value="<?php echo htmlspecialchars($smtpSettings['smtp_username'] ?? ''); ?>" required>
                                        <div>用于发送邮件的邮箱地址</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>邮箱密码</td>
                                    <td>
                                        <input type="password" name="smtp_password">
                                        <div>留空则保持现有密码不变</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>发件人邮箱</td>
                                    <td>
                                        <input type="email" name="smtp_from" 
                                               value="<?php echo htmlspecialchars($smtpSettings['smtp_from'] ?? ''); ?>" required>
                                        <div>显示在收件人邮箱中的发件人地址</div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>发件人名称</td>
                                    <td>
                                        <input type="text" name="smtp_from_name" 
                                               value="<?php echo htmlspecialchars($smtpSettings['smtp_from_name'] ?? ''); ?>">
                                        <div>显示在收件人邮箱中的发件人名称</div>
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