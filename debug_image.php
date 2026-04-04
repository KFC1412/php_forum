<?php
// 调试页面 - 查看图片居中的HTML结构和CSS应用
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';

// 检查安装状态和闭站模式
checkInstall();

$db = getDB();
$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';

// 获取最新的一个包含图片的回复
$posts = $db->fetchAll(
    "SELECT * FROM `{$prefix}posts` WHERE `content` LIKE '%<img%' ORDER BY `id` DESC LIMIT 1"
);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>图片居中调试</title>
    <link rel="stylesheet" href="assets/css/forum.css">
    <style>
        .debug-info {
            background: #f0f0f0;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
        }
        .raw-html {
            background: #fff;
            padding: 10px;
            border: 1px solid #ddd;
            font-family: monospace;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    <h1>图片居中调试页面</h1>
    
    <?php if (!empty($posts)): ?>
        <?php foreach ($posts as $post): ?>
            <div class="debug-info">
                <h2>回复ID: <?php echo $post['id']; ?></h2>
                <h3>原始HTML内容:</h3>
                <div class="raw-html"><?php echo htmlspecialchars($post['content']); ?></div>
                
                <h3>渲染后的内容:</h3>
                <table border="1" width="100%" cellspacing="0" cellpadding="5">
                    <tr>
                        <td colspan="2">
                            <div><?php echo formatContent($post['content']); ?></div>
                        </td>
                    </tr>
                </table>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>没有找到包含图片的回复</p>
    <?php endif; ?>
    
    <h2>手动测试</h2>
    <table border="1" width="100%" cellspacing="0" cellpadding="5">
        <tr>
            <td colspan="2">
                <div>
                    <div style="text-align: center;">
                        <img src="icon.png" alt="测试图片" width="100">
                    </div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>