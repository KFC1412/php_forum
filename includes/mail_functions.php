<?php
/**
 * 邮件发送功能 - 优化版
 */

// 加载SMTP发送函数
require_once __DIR__ . '/smtp.php';

/**
 * 邮件发送配置
 */
define('MAIL_QUEUE_ENABLED', false);          // 禁用邮件队列，使用同步发送
define('MAIL_RATE_LIMIT_INTERVAL', 60);       // 频率限制间隔（秒）
define('MAIL_MAX_PER_INTERVAL', 10);          // 每个间隔最大发送数量
define('MAIL_QUEUE_DIR', __DIR__ . '/../storage/mail_queue/');  // 邮件队列目录

/**
 * 初始化邮件队列目录
 */
function initMailQueue() {
    if (!is_dir(MAIL_QUEUE_DIR)) {
        mkdir(MAIL_QUEUE_DIR, 0755, true);
    }
}

/**
 * 检查邮件发送频率限制
 * @param string $email 收件人邮箱
 * @return bool 是否允许发送
 */
function checkMailRateLimit($email) {
    // 确保邮件队列目录存在
    initMailQueue();
    
    $rateFile = MAIL_QUEUE_DIR . 'rate_limit.json';
    $now = time();
    
    $rates = [];
    if (file_exists($rateFile)) {
        $content = file_get_contents($rateFile);
        $rates = json_decode($content, true) ?: [];
    }
    
    // 清理过期的记录
    foreach ($rates as $key => $timestamp) {
        if ($now - $timestamp > MAIL_RATE_LIMIT_INTERVAL) {
            unset($rates[$key]);
        }
    }
    
    // 检查当前邮箱的发送次数
    $emailKey = md5($email);
    $count = 0;
    foreach ($rates as $key => $timestamp) {
        if (strpos($key, $emailKey) === 0) {
            $count++;
        }
    }
    
    if ($count >= MAIL_MAX_PER_INTERVAL) {
        return false;
    }
    
    // 记录本次发送
    $rates[$emailKey . '_' . $now] = $now;
    file_put_contents($rateFile, json_encode($rates));
    
    return true;
}

/**
 * 发送邮件
 * @param string $to 收件人邮箱
 * @param string $subject 邮件主题
 * @param string $content 邮件内容（HTML）
 * @param string $type 邮件类型
 * @return bool 是否发送成功
 */
function sendMail($to, $subject, $content, $type = 'system') {
    // 检查频率限制
    if (!checkMailRateLimit($to)) {
        logMail('邮件发送频率限制', $to, $type, 'error');
        return false;
    }
    
    // 获取邮件配置
    $smtp_host = getSetting('smtp_host', '');
    $smtp_port = getSetting('smtp_port', '587');
    $smtp_user = getSetting('smtp_username', '');
    $smtp_pass = getSetting('smtp_password', '');
    $smtp_from = getSetting('smtp_from', $smtp_user);
    $smtp_from_name = getSetting('smtp_from_name', getSetting('site_name', 'PHP轻论坛'));
    
    // 如果没有配置SMTP，尝试使用PHP mail函数
    if (empty($smtp_host) || empty($smtp_user) || empty($smtp_pass)) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: {$smtp_from_name} <{$smtp_from}>" . "\r\n";
        
        $result = mail($to, $subject, buildEmailTemplate($subject, $content, $smtp_from_name), $headers);
        
        if ($result) {
            logMail('邮件发送成功（mail函数）', $to, $type, 'success');
        } else {
            logMail('邮件发送失败（mail函数）', $to, $type, 'error');
        }
        
        return $result;
    }
    
    // 使用PHPMailer发送
    try {
        // 加载PHPMailer
        require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/PHPMailer/src/SMTP.php';
        require_once __DIR__ . '/PHPMailer/src/Exception.php';
        
        // 创建邮件实例
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // 服务器设置
        $mail->SMTPDebug = 0;  // 调试模式（0=关闭，2=详细）
        $mail->isSMTP();       // 使用SMTP
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_user;
        $mail->Password = $smtp_pass;
        
        $smtp_secure = getSetting('smtp_secure', 'ssl');
        if (!empty($smtp_secure)) {
            $mail->SMTPSecure = $smtp_secure;
        }
        
        $mail->Port = (int)$smtp_port;
        
        // 发件人
        $mail->setFrom($smtp_from, $smtp_from_name);
        
        // 收件人
        $mail->addAddress($to);
        
        // 设置字符编码
        $mail->CharSet = 'UTF-8';
        
        // 内容
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = buildEmailTemplate($subject, $content, $smtp_from_name);
        $mail->AltBody = strip_tags(buildEmailTemplate($subject, $content, $smtp_from_name));
        
        // 发送邮件
        $mail->send();
        
        logMail('邮件发送成功（PHPMailer）', $to, $type, 'success');
        return true;
    } catch (Exception $e) {
        logMail('邮件发送异常：' . $e->getMessage(), $to, $type, 'error');
        return false;
    }
}

/**
 * 发送邮件（队列方式）
 * @param string $to 收件人邮箱
 * @param string $subject 邮件主题
 * @param string $content 邮件内容（HTML）
 * @param string $type 邮件类型
 * @return bool 是否成功加入队列
 */
function queueMail($to, $subject, $content, $type = 'system') {
    // 确保邮件队列目录存在
    initMailQueue();
    
    // 构建邮件数据
    $mailData = [
        'to' => $to,
        'subject' => $subject,
        'content' => $content,
        'type' => $type,
        'attempts' => 0,
        'created_at' => time()
    ];
    
    // 生成唯一文件名
    $filename = MAIL_QUEUE_DIR . uniqid('mail_', true) . '.json';
    
    // 保存到队列
    if (file_put_contents($filename, json_encode($mailData, JSON_UNESCAPED_UNICODE))) {
        logMail('邮件已加入队列', $to, $type, 'queued');
        return true;
    }
    
    return false;
}

/**
 * 处理邮件队列
 * @param int $limit 最大处理数量
 * @return array 处理结果
 */
function processMailQueue($limit = 10) {
    $result = [
        'processed' => 0,
        'success' => 0,
        'failed' => 0,
        'errors' => []
    ];
    
    // 确保邮件队列目录存在
    initMailQueue();
    
    // 获取队列文件列表
    $queueFiles = glob(MAIL_QUEUE_DIR . 'mail_*.json');
    
    if (empty($queueFiles)) {
        return $result;
    }
    
    // 按创建时间排序
    usort($queueFiles, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    
    $processed = 0;
    foreach ($queueFiles as $file) {
        if ($processed >= $limit) {
            break;
        }
        
        $content = file_get_contents($file);
        $item = json_decode($content, true);
        
        if (!$item) {
            unlink($file);
            continue;
        }
        
        // 检查重试次数
        if ($item['attempts'] >= 3) {
            logMail('邮件发送失败：超过最大重试次数', $item['to'], $item['type'], 'error');
            unlink($file);
            $result['failed']++;
            continue;
        }
        
        // 尝试发送
        $item['attempts']++;
        $success = sendMail($item['to'], $item['subject'], $item['content'], $item['type']);
        
        if ($success) {
            logMail('邮件发送成功（队列）', $item['to'], $item['type'], 'success');
            unlink($file);
            $result['success']++;
        } else {
            // 更新重试次数
            file_put_contents($file, json_encode($item, JSON_UNESCAPED_UNICODE));
            $result['errors'][] = "发送到 {$item['to']} 失败";
        }
        
        $result['processed']++;
        $processed++;
        
        // 稍微延迟，避免发送过快
        usleep(100000); // 100ms
    }
    
    return $result;
}

/**
 * 构建邮件模板
 * @param string $title 邮件标题
 * @param string $content 邮件内容
 * @param string $site_name 站点名称
 * @return string HTML邮件内容
 */
function buildEmailTemplate($title, $content, $site_name) {
    $current_time = date('Y-m-d H:i:s');
    $site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; 
            line-height: 1.5; 
            color: #333; 
            margin: 0; 
            padding: 0; 
            background-color: #f5f5f5; 
        }
        .email-wrapper { 
            max-width: 600px; 
            margin: 0 auto; 
            background-color: #ffffff; 
        }
        .email-header { 
            padding: 30px 20px; 
            text-align: center; 
            border-bottom: 1px solid #f0f0f0;
        }
        .email-header h2 { 
            margin: 0; 
            font-size: 18px; 
            font-weight: 600; 
            color: #333;
        }
        .email-content { 
            padding: 20px;
        }
        .email-content h3 { 
            margin-top: 0; 
            color: #333; 
            font-size: 16px; 
            font-weight: 600;
        }
        .email-content p {
            margin: 10px 0;
            color: #666;
        }
        .email-content blockquote {
            border-left: 3px solid #4A90E2;
            padding-left: 15px;
            margin: 15px 0;
            color: #555;
            background-color: #f9f9f9;
            padding: 10px 15px;
        }
        .email-footer { 
            padding: 20px; 
            text-align: center; 
            border-top: 1px solid #f0f0f0;
            font-size: 12px; 
            color: #999;
        }
        .email-footer p {
            margin: 5px 0;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4A90E2;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
        }
        .info-box {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .info-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .info-box li {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <h2>{$site_name}</h2>
        </div>
        <div class="email-content">
            <h3>{$title}</h3>
            {$content}
        </div>
        <div class="email-footer">
            <p>此邮件由系统自动发送，请勿回复</p>
            <p>发送时间：{$current_time}</p>
            <p><a href="{$site_url}">访问网站</a></p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * 记录邮件日志
 * @param string $message 日志消息
 * @param string $recipient 收件人
 * @param string $type 邮件类型
 * @param string $status 发送状态 (success/error/queued)
 */
function logMail($message, $recipient, $type, $status) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        // 获取当前用户信息
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
        
        // 构建日志数据
        $log_data = [
            'action' => '发送邮件',
            'target_type' => 'email',
            'target_id' => 0,
            'user_id' => $user_id,
            'details' => json_encode([
                'message' => $message,
                'recipient' => $recipient,
                'type' => $type,
                'status' => $status,
                'time' => date('Y-m-d H:i:s')
            ]),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // 插入日志记录
        $db->insert("{$prefix}logs", $log_data);
        
    } catch (Exception $e) {
        // 如果数据库记录失败，写入文件日志
        error_log('邮件日志记录失败：' . $e->getMessage());
    }
}

/**
 * 发送回复通知邮件
 * @param int $topic_id 主题ID
 * @param int $reply_user_id 回复用户ID
 * @param string $reply_content 回复内容
 * @return bool 是否发送成功
 */
function sendReplyNotification($topic_id, $reply_user_id, $reply_content) {
    // 检查是否启用了邮件通知
    if (getSetting('email_notification_enabled', '0') !== '1') {
        return false;
    }
    
    // 检查是否启用了回复通知
    if (getSetting('email_notification_reply', '1') !== '1') {
        return false;
    }

    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';

        // 获取主题信息
        $topic = $db->fetch(
            "SELECT * FROM `{$prefix}topics` WHERE `id` = :id",
            ['id' => $topic_id]
        );

        if (!$topic) {
            return false;
        }

        // 获取主题作者信息
        $author = $db->fetch(
            "SELECT `username`, `email` FROM `{$prefix}users` WHERE `id` = :id",
            ['id' => $topic['user_id']]
        );

        if (!$author) {
            return false;
        }

        $topic['author_name'] = $author['username'] ?? '未知用户';
        $topic['author_email'] = $author['email'] ?? '';

        if (empty($topic['author_email'])) {
            return false;
        }

        // 不给自己发送通知
        if ($topic['user_id'] == $reply_user_id) {
            return false;
        }
        
        // 获取回复用户信息
        $reply_user = $db->fetch(
            "SELECT username FROM `{$prefix}users` WHERE id = :id",
            ['id' => $reply_user_id]
        );
        
        $reply_username = $reply_user ? $reply_user['username'] : '未知用户';
        
        // 构建邮件内容
        $site_name = getSetting('site_name', 'PHP轻论坛');
        $topic_url = getTopicUrl($topic_id);
        
        $subject = "【{$site_name}】您的主题收到了新回复";
        $content = <<<HTML
<p>您好，{$topic['author_name']}：</p>
<p>您的主题《{$topic['title']}》收到了来自 <strong>{$reply_username}</strong> 的新回复。</p>
<div class="info-box">
    <p><strong>回复内容：</strong></p>
    <blockquote>{$reply_content}</blockquote>
</div>
<p style="margin-top: 20px;">
    <a href="{$topic_url}" class="button">查看回复</a>
</p>
HTML;
        
        return sendMail($topic['author_email'], $subject, $content, 'reply');
        
    } catch (Exception $e) {
        logMail('发送回复通知失败：' . $e->getMessage(), '', 'reply', 'error');
        return false;
    }
}

/**
 * 发送主题被编辑通知邮件
 * @param int $topic_id 主题ID
 * @param int $editor_user_id 编辑用户ID
 * @return bool 是否发送成功
 */
function sendTopicEditedNotification($topic_id, $editor_user_id) {
    // 检查是否启用了邮件通知
    if (getSetting('email_notification_enabled', '0') !== '1') {
        return false;
    }
    
    // 检查是否启用了主题编辑通知
    if (getSetting('email_notification_topic', '1') !== '1') {
        return false;
    }

    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';

        // 获取主题信息
        $topic = $db->fetch(
            "SELECT * FROM `{$prefix}topics` WHERE `id` = :id",
            ['id' => $topic_id]
        );

        if (!$topic) {
            return false;
        }

        // 获取主题作者信息
        $author = $db->fetch(
            "SELECT `username`, `email` FROM `{$prefix}users` WHERE `id` = :id",
            ['id' => $topic['user_id']]
        );

        if (!$author) {
            return false;
        }

        $topic['author_name'] = $author['username'] ?? '未知用户';
        $topic['author_email'] = $author['email'] ?? '';

        if (empty($topic['author_email'])) {
            return false;
        }

        // 不给自己发送通知
        if ($topic['user_id'] == $editor_user_id) {
            return false;
        }
        
        // 获取编辑用户信息
        $editor = $db->fetch(
            "SELECT username FROM `{$prefix}users` WHERE id = :id",
            ['id' => $editor_user_id]
        );
        
        $editor_name = $editor ? $editor['username'] : '管理员';
        
        // 构建邮件内容
        $site_name = getSetting('site_name', 'PHP轻论坛');
        $topic_url = getTopicUrl($topic_id);
        
        $subject = "【{$site_name}】您的主题已被编辑";
        $content = <<<HTML
<p>您好，{$topic['author_name']}：</p>
<p>您的主题《{$topic['title']}》已被 <strong>{$editor_name}</strong> 编辑。</p>
<p style="margin-top: 20px;">
    <a href="{$topic_url}" class="button">查看主题</a>
</p>
HTML;
        
        return sendMail($topic['author_email'], $subject, $content, 'topic');
        
    } catch (Exception $e) {
        logMail('发送主题编辑通知失败：' . $e->getMessage(), '', 'topic', 'error');
        return false;
    }
}

/**
 * 发送注册成功通知邮件
 * @param string $to 收件人邮箱
 * @param string $username 用户名
 * @return bool 是否发送成功
 */
function sendRegisterNotification($to, $username) {
    // 检查是否启用了邮件通知
    if (getSetting('email_notification_enabled', '0') !== '1') {
        return false;
    }
    
    // 检查是否启用了注册通知
    if (getSetting('email_notification_register', '1') !== '1') {
        return false;
    }
    
    $site_name = getSetting('site_name', 'PHP轻论坛');
    $site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    
    $register_time = date('Y-m-d H:i:s');
    
    $subject = "【{$site_name}】注册成功通知";
    $content = <<<HTML
<p>您好，{$username}：</p>
<p>恭喜您成功注册成为 <strong>{$site_name}</strong> 的会员！</p>
<div class="info-box">
    <p><strong>您的账号信息：</strong></p>
    <ul>
        <li>用户名：{$username}</li>
        <li>注册时间：{$register_time}</li>
    </ul>
</div>
<p>现在您可以登录网站，开始您的论坛之旅。</p>
<p style="margin-top: 20px;">
    <a href="{$site_url}" class="button">访问网站</a>
</p>
HTML;
    
    return sendMail($to, $subject, $content, 'register');
}

/**
 * 发送登录成功通知邮件
 * @param string $to 收件人邮箱
 * @param string $username 用户名
 * @param string $login_ip 登录IP
 * @return bool 是否发送成功
 */
function sendLoginNotification($to, $username, $login_ip) {
    // 检查是否启用了邮件通知
    if (getSetting('email_notification_enabled', '0') !== '1') {
        return false;
    }
    
    // 检查是否启用了登录通知
    if (getSetting('email_notification_login', '1') !== '1') {
        return false;
    }
    
    $site_name = getSetting('site_name', 'PHP轻论坛');
    $site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $login_time = date('Y-m-d H:i:s');
    
    $subject = "【{$site_name}】登录成功通知";
    $content = <<<HTML
<p>您好，{$username}：</p>
<p>您的账号 <strong>{$username}</strong> 刚刚成功登录了 <strong>{$site_name}</strong>。</p>
<div class="info-box">
    <p><strong>登录信息：</strong></p>
    <ul>
        <li>登录时间：{$login_time}</li>
        <li>登录IP：{$login_ip}</li>
    </ul>
</div>
<p>如果这不是您本人的操作，请立即 <a href="{$site_url}/profile.php">修改密码</a>。</p>
<p style="margin-top: 20px;">
    <a href="{$site_url}" class="button">访问网站</a>
</p>
HTML;
    
    return sendMail($to, $subject, $content, 'login');
}

/**
 * 发送系统通知邮件
 * @param string $to 收件人邮箱
 * @param string $subject 邮件主题
 * @param string $content 邮件内容
 * @return bool 是否发送成功
 */
function sendSystemNotification($to, $subject, $content) {
    return sendMail($to, $subject, $content, 'system');
}

/**
 * 发送密码修改通知邮件
 * @param string $to 收件人邮箱
 * @param string $username 用户名
 * @param string $change_ip 修改IP
 * @return bool 是否发送成功
 */
function sendPasswordChangeNotification($to, $username, $change_ip) {
    // 检查是否启用了邮件通知
    if (getSetting('email_notification_enabled', '0') !== '1') {
        return false;
    }
    
    // 检查是否启用了密码修改通知
    if (getSetting('email_notification_login', '1') !== '1') {
        return false;
    }
    
    $site_name = getSetting('site_name', 'PHP轻论坛');
    $site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $change_time = date('Y-m-d H:i:s');
    
    $subject = "【{$site_name}】密码修改成功通知";
    $content = <<<HTML
<p>您好，{$username}：</p>
<p>您的账号 <strong>{$username}</strong> 的密码已成功修改。</p>
<div class="info-box">
    <p><strong>修改信息：</strong></p>
    <ul>
        <li>修改时间：{$change_time}</li>
        <li>修改IP：{$change_ip}</li>
    </ul>
</div>
<p>如果这不是您本人的操作，请立即联系管理员。</p>
<p style="margin-top: 20px;">
    <a href="{$site_url}" class="button">访问网站</a>
</p>
HTML;
    
    return sendMail($to, $subject, $content, 'system');
}

/**
 * 发送站内互动消息通知
 * @param int $receiver_id 接收用户ID
 * @param string $type 通知类型
 * @param array $data 通知数据
 * @return bool 是否发送成功
 */
function sendInteractionNotification($receiver_id, $type, $data) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        // 加载系统账户函数
        require_once __DIR__ . '/system_account_functions.php';
        
        // 确保互动消息系统账户存在
        $infoUserId = 'info';
        ensureCoreSystemAccounts();
        
        // 构建通知内容
        $content = '';
        $site_name = getSetting('site_name', 'PHP轻论坛');
        $current_time = date('Y-m-d H:i:s');
        
        switch ($type) {
            case 'topic_reply':
                // 获取回复者信息
                $replier_name = '用户';
                $replier_id = '';
                if (isset($data['replier_id']) && !empty($data['replier_id'])) {
                    // 直接使用传入的replier_id作为ID
                    $replier_id = $data['replier_id'];
                    // 尝试获取用户名
                    // 直接遍历用户列表查找
                    $users = $db->fetchAll("SELECT id, username FROM `{$prefix}users`");
                    foreach ($users as $user) {
                        if ((string)$user['id'] === (string)$data['replier_id']) {
                            $replier_name = $user['username'];
                            break;
                        }
                    }
                }
                
                $content = "📢 互动通知\n\n";
                $content .= "您的主题《{$data['topic_title']}》收到了新回复。\n\n";
                $content .= "回复者：{$replier_name} (ID: {$replier_id})\n";
                $content .= "回复时间：{$current_time}\n\n";
                $content .= "回复内容：\n{$data['reply_content']}\n\n";
                $content .= "点击查看：{$data['topic_url']}\n\n";
                $content .= "---\n";
                $content .= "此消息由系统自动发送，请勿回复。";
                break;
            
            case 'reply_reply':
                // 获取回复者信息
                $replier_name = '用户';
                $replier_id = '';
                if (isset($data['replier_id']) && !empty($data['replier_id'])) {
                    // 直接使用传入的replier_id作为ID
                    $replier_id = $data['replier_id'];
                    // 尝试获取用户名
                    // 直接遍历用户列表查找
                    $users = $db->fetchAll("SELECT id, username FROM `{$prefix}users`");
                    foreach ($users as $user) {
                        if ((string)$user['id'] === (string)$data['replier_id']) {
                            $replier_name = $user['username'];
                            break;
                        }
                    }
                }
                
                $content = "📢 互动通知\n\n";
                $content .= "您在主题《{$data['topic_title']}》中的回复收到了新的回复。\n\n";
                $content .= "回复者：{$replier_name} (ID: {$replier_id})\n";
                $content .= "回复时间：{$current_time}\n\n";
                $content .= "您的原回复：\n{$data['original_reply']}\n\n";
                $content .= "新回复内容：\n{$data['reply_content']}\n\n";
                $content .= "点击查看：{$data['topic_url']}\n\n";
                $content .= "---\n";
                $content .= "此消息由系统自动发送，请勿回复。";
                break;
            
            case 'topic_hidden':
                $content = "📢 管理通知\n\n";
                $content .= "您的主题《{$data['topic_title']}》已被隐藏。\n\n";
                $content .= "隐藏时间：{$current_time}\n";
                $content .= "隐藏原因：{$data['reason']}\n\n";
                $content .= "点击查看：{$data['topic_url']}\n\n";
                $content .= "---\n";
                $content .= "如有疑问，请通过申诉功能提交申诉。";
                break;
            
            case 'topic_deleted':
                $content = "📢 管理通知\n\n";
                $content .= "您的主题《{$data['topic_title']}》已被删除。\n\n";
                $content .= "删除时间：{$current_time}\n";
                $content .= "删除原因：{$data['reason']}\n\n";
                $content .= "---\n";
                $content .= "如有疑问，请通过申诉功能提交申诉。";
                break;
            
            case 'post_deleted':
                $content = "📢 管理通知\n\n";
                $content .= "您在主题《{$data['topic_title']}》中的回复已被删除。\n\n";
                $content .= "删除时间：{$current_time}\n";
                $content .= "删除原因：{$data['reason']}\n\n";
                $content .= "---\n";
                $content .= "如有疑问，请通过申诉功能提交申诉。";
                break;
            
            case 'appeal_result':
                $content = "📢 申诉结果通知\n\n";
                $content .= "您的申诉结果已处理。\n\n";
                $content .= "处理时间：{$current_time}\n";
                $content .= "申诉类型：{$data['appeal_type']}\n";
                $content .= "申诉内容：{$data['appeal_content']}\n";
                $content .= "处理结果：{$data['result']}\n";
                $content .= "处理意见：{$data['comment']}\n\n";
                $content .= "---\n";
                $content .= "此消息由系统自动发送，请勿回复。";
                break;
            
            case 'new_follower':
                $content = "📢 粉丝通知\n\n";
                $content .= "您收到了新的粉丝！\n\n";
                $content .= "粉丝名称：{$data['follower_name']}\n";
                $content .= "关注时间：{$current_time}\n\n";
                $content .= "点击查看粉丝主页：http://" . $_SERVER['HTTP_HOST'] . "/profile.php?id={$data['follower_id']}\n\n";
                $content .= "---\n";
                $content .= "此消息由系统自动发送，请勿回复。";
                break;
            
            case 'new_badge':
                $content = "📢 徽章通知\n\n";
                $content .= "恭喜您获得了新的徽章！\n\n";
                $content .= "徽章名称：{$data['badge_name']}\n";
                $content .= "获得时间：{$current_time}\n\n";
                $content .= "点击查看您的个人资料：http://" . $_SERVER['HTTP_HOST'] . "/profile.php?id={$receiver_id}\n\n";
                $content .= "---\n";
                $content .= "此消息由系统自动发送，请勿回复。";
                break;
            
            case 'topic_liked':
                $liker_name = '用户';
                $liker_id = '';
                if (isset($data['liker_id'])) {
                    $liker = $db->fetch(
                        "SELECT id, username FROM `{$prefix}users` WHERE `id` = ?",
                        [$data['liker_id']]
                    );
                    if ($liker) {
                        $liker_name = $liker['username'];
                        $liker_id = $liker['id'];
                    }
                }
                
                $content = "📢 点赞通知\n\n";
                $content .= "您的主题《{$data['topic_title']}》收到了新的点赞。\n\n";
                $content .= "点赞者：{$liker_name} (ID: {$liker_id})\n";
                $content .= "点赞时间：{$current_time}\n\n";
                $content .= "点击查看：{$data['topic_url']}\n\n";
                $content .= "---\n";
                $content .= "此消息由系统自动发送，请勿回复。";
                break;
            
            case 'topic_bookmarked':
                $bookmarker_name = '用户';
                $bookmarker_id = '';
                if (isset($data['bookmarker_id'])) {
                    $bookmarker = $db->fetch(
                        "SELECT id, username FROM `{$prefix}users` WHERE `id` = ?",
                        [$data['bookmarker_id']]
                    );
                    if ($bookmarker) {
                        $bookmarker_name = $bookmarker['username'];
                        $bookmarker_id = $bookmarker['id'];
                    }
                }
                
                $content = "📢 收藏通知\n\n";
                $content .= "您的主题《{$data['topic_title']}》被收藏了。\n\n";
                $content .= "收藏者：{$bookmarker_name} (ID: {$bookmarker_id})\n";
                $content .= "收藏时间：{$current_time}\n\n";
                $content .= "点击查看：{$data['topic_url']}\n\n";
                $content .= "---\n";
                $content .= "此消息由系统自动发送，请勿回复。";
                break;
        }
        
        if (empty($content)) {
            return false;
        }
        
        // 发送站内消息
        $result = $db->insert("{$prefix}messages", [
            'sender_id' => $infoUserId,
            'receiver_id' => $receiver_id,
            'content' => $content,
            'status' => 'unread',
            'ip' => '127.0.0.1',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return $result !== false;
        
    } catch (Exception $e) {
        error_log('发送互动通知失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 发送主题回复通知
 * @param int $topic_id 主题ID
 * @param int $reply_user_id 回复用户ID
 * @param string $reply_content 回复内容
 * @param int|null $reply_to 回复的回复ID
 */
function sendTopicReplyNotification($topic_id, $reply_user_id, $reply_content, $reply_to = null) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        // 获取主题信息
        $topic = $db->fetch(
            "SELECT * FROM `{$prefix}topics` WHERE `id` = :id",
            ['id' => $topic_id]
        );
        
        if (!$topic) {
            return;
        }
        
        // 发送邮件通知
        sendReplyNotification($topic_id, $reply_user_id, $reply_content);
        
        // 发送站内互动消息通知给主题作者
        if ($topic['user_id'] != $reply_user_id) {
            $topic_url = getTopicUrl($topic_id);
            sendInteractionNotification($topic['user_id'], 'topic_reply', [
                'topic_title' => $topic['title'],
                'reply_content' => $reply_content,
                'topic_url' => $topic_url,
                'replier_id' => $reply_user_id
            ]);
        }
        
        // 如果是回复其他回复，发送通知给被回复的用户
        if ($reply_to) {
            $reply_to_post = $db->fetch(
                "SELECT * FROM `{$prefix}posts` WHERE `id` = :reply_to",
                ['reply_to' => $reply_to]
            );
            
            if ($reply_to_post && $reply_to_post['user_id'] != $reply_user_id) {
                $topic_url = getTopicUrl($topic_id);
                sendInteractionNotification($reply_to_post['user_id'], 'reply_reply', [
                    'topic_title' => $topic['title'],
                    'reply_content' => $reply_content,
                    'topic_url' => $topic_url,
                    'replier_id' => $reply_user_id,
                    'original_reply' => $reply_to_post['content']
                ]);
            }
        }
        
    } catch (Exception $e) {
        error_log('发送主题回复通知失败：' . $e->getMessage());
    }
}
