<?php
// 测试脚本：验证getMessageThread函数

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/social_functions.php';

// 设置存储类型为JSON
define('STORAGE_TYPE', 'json');

// 测试用户1的系统消息
echo "测试用户1的系统消息:\n";
echo "====================================\n";
$messages1 = getMessageThread(1, 'system');
foreach ($messages1 as $msg) {
    echo "ID: {$msg['id']}, Sender: {$msg['sender_id']}, Receiver: {$msg['receiver_id']}, Content: {$msg['content']}\n";
}

echo "\n预期结果: 只应该显示sender_id=1或receiver_id=1的消息\n";
echo "====================================\n";

// 测试用户2的系统消息
echo "\n测试用户2的系统消息:\n";
echo "====================================\n";
$messages2 = getMessageThread(2, 'system');
foreach ($messages2 as $msg) {
    echo "ID: {$msg['id']}, Sender: {$msg['sender_id']}, Receiver: {$msg['receiver_id']}, Content: {$msg['content']}\n";
}

echo "\n预期结果: 只应该显示sender_id=2或receiver_id=2的消息\n";
echo "====================================\n";