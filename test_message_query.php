<?php
// 测试脚本：验证JSON存储的参数绑定问题

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';

// 设置存储类型为JSON
define('STORAGE_TYPE', 'json');

// 初始化数据库
$db = getDB();
$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';

// 测试SQL查询
$sql = "SELECT * FROM `{$prefix}messages` WHERE 
(`sender_id` = 'system' AND `receiver_id` = ?) OR
(`sender_id` = ? AND `receiver_id` = 'system')
ORDER BY created_at ASC";

$params = ['1', '1'];

// 执行查询
$result = $db->fetchAll($sql, $params);

echo "查询结果 (user_id=1):\n";
echo "====================================\n";
foreach ($result as $row) {
    echo "ID: {$row['id']}, Sender: {$row['sender_id']}, Receiver: {$row['receiver_id']}, Content: {$row['content']}\n";
}

echo "\n预期结果: 只应该显示sender_id=1或receiver_id=1的消息\n";
echo "====================================\n";

// 测试另一个用户
$params2 = ['2', '2'];
$result2 = $db->fetchAll($sql, $params2);

echo "\n查询结果 (user_id=2):\n";
echo "====================================\n";
foreach ($result2 as $row) {
    echo "ID: {$row['id']}, Sender: {$row['sender_id']}, Receiver: {$row['receiver_id']}, Content: {$row['content']}\n";
}

echo "\n预期结果: 只应该显示sender_id=2或receiver_id=2的消息\n";
echo "====================================\n";