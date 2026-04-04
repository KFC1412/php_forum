<?php
/**
 * 邮件队列处理脚本
 * 建议设置为每分钟运行的定时任务
 */

date_default_timezone_set('Asia/Shanghai');

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 检查是否已安装
if (!file_exists(__DIR__ . '/../config/config.php')) {
    exit('系统未安装');
}

// 加载配置和函数
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail_functions.php';

// 处理邮件队列
$result = processMailQueue(10);

// 输出结果
echo date('Y-m-d H:i:s') . " - 邮件队列处理完成\n";
echo "处理数量: {$result['processed']}\n";
echo "成功: {$result['success']}\n";
echo "失败: {$result['failed']}\n";

if (!empty($result['errors'])) {
    echo "错误信息:\n";
    foreach ($result['errors'] as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n";
