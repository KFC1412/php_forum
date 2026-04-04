<?php
/**
 * 分类列表页面
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

// 检查安装状态和闭站模式
checkInstall();

// 获取分类列表
try {
    $db = getDB();
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
    
    // 获取所有分类
    $categories = $db->fetchAll("SELECT * FROM `{$prefix}categories` ORDER BY `sort_order` ASC");
    
    // 获取每个分类的主题数量
    foreach ($categories as &$_category) {
        $_category['topic_count'] = $db->fetchColumn(
            "SELECT COUNT(*) FROM `{$prefix}topics` WHERE `category_id` = :category_id AND `status` = 'published'",
            ['category_id' => $_category['id']]
        );
        
        // 获取最新主题
        if ($storage_type === 'json') {
            // JSON存储：使用简单查询
            $latest_topic = $db->select('topics', ['category_id' => $_category['id'], 'status' => 'published'], 'created_at DESC', 1);
            
            if (!empty($latest_topic)) {
                $latest_topic = $latest_topic[0];
                $user = $db->findById('users', $latest_topic['user_id']);
                $latest_topic['username'] = $user ? $user['username'] : '未知用户';
            } else {
                $latest_topic = null;
            }
        } else {
            // MySQL存储：使用JOIN查询
            $latest_topic = $db->fetch(
                "SELECT t.*, u.username FROM `{$prefix}topics` t 
                JOIN `{$prefix}users` u ON t.user_id = u.id 
                WHERE t.category_id = :category_id AND t.status = 'published' 
                ORDER BY t.created_at DESC LIMIT 1",
                ['category_id' => $_category['id']]
            );
        }
        
        $_category['latest_topic'] = $latest_topic;
    }
    
} catch (Exception $e) {
    $error = '加载分类列表失败: ' . $e->getMessage();
}

// 设置页面标题
$page_title = '分类列表';

// 加载页面头部
include __DIR__ . '/templates/header.php';
?>

<table border="1" width="100%" cellspacing="0" cellpadding="10">
    <tr>
        <td colspan="3">
            <table width="100%" cellspacing="0" cellpadding="5">
                <tr>
                    <td width="50%">
                        <h1>分类列表</h1>
                    </td>
                    <td width="50%" align="right">
                        <a href="index.php">首页</a> &gt; 分类列表
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    
    <?php if (isset($error)): ?>
        <tr>
            <td colspan="3"><?php echo $error; ?></td>
        </tr>
    <?php else: ?>
        <tr>
            <td width="50%">分类</td>
            <td width="15%" align="center">主题数</td>
            <td width="35%">最新主题</td>
        </tr>
        <?php if (count($categories) > 0): ?>
            <?php foreach ($categories as $category): ?>
                <tr>
                    <td>
                        <a href="category.php?id=<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['title']); ?></a>
                        <br>
                        <?php echo htmlspecialchars($category['description']); ?>
                    </td>
                    <td align="center">
                        <?php echo $category['topic_count']; ?>
                    </td>
                    <td>
                        <?php if ($category['latest_topic']): ?>
                            <a href="topic.php?id=<?php echo $category['latest_topic']['id']; ?>"><?php echo htmlspecialchars($category['latest_topic']['title']); ?></a>
                            <br>
                            <small>由 <?php echo htmlspecialchars($category['latest_topic']['username']); ?> 发表于 <?php echo formatDateTime($category['latest_topic']['created_at']); ?></small>
                        <?php else: ?>
                            暂无主题
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="3" align="center">暂无分类</td>
            </tr>
        <?php endif; ?>
    <?php endif; ?>
</table>

<?php
// 加载页面底部
include __DIR__ . '/templates/footer.php';
?>

