<?php
    date_default_timezone_set('Asia/Shanghai');
/**
 * 管理后台首页
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
require_once __DIR__ . '/includes/admin_functions.php';

// 检查是否已登录且是管理员
checkAdminAccess();

// 获取统计信息
try {
    $db = getDB();
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
    
    // 获取用户数量（排除系统账户）
    $user_count = $db->fetchColumn("SELECT COUNT(*) FROM `{$prefix}users` WHERE `role` != 'system'");
    
    // 获取主题数量
    $topic_count = $db->fetchColumn("SELECT COUNT(*) FROM `{$prefix}topics`");
    
    // 获取回复数量
    $post_count = $db->fetchColumn("SELECT COUNT(*) FROM `{$prefix}posts`");
    
    // 获取分类数量
    $category_count = $db->fetchColumn("SELECT COUNT(*) FROM `{$prefix}categories`");
    
    // 获取最近注册的用户（排除系统账户）
    $recent_users = $db->fetchAll(
        "SELECT * FROM `{$prefix}users` WHERE `role` != 'system' ORDER BY `created_at` DESC LIMIT 5"
    );
    
    // 获取最近的主题
    if ($storage_type === 'json') {
        // JSON存储：使用简单查询
        $recent_topics = $db->select('topics', [], 'created_at DESC', 5);
        
        // 获取用户信息
        $users = [];
        $all_users = $db->select('users');
        foreach ($all_users as $u) {
            $users[$u['id']] = $u;
        }
        
        // 关联用户数据
        foreach ($recent_topics as &$topic) {
            $topic['username'] = isset($users[$topic['user_id']]) ? $users[$topic['user_id']]['username'] : '未知用户';
        }
        unset($topic);
    } else {
        // MySQL存储：使用JOIN查询
        $recent_topics = $db->fetchAll(
            "SELECT t.*, u.username FROM `{$prefix}topics` t 
            JOIN `{$prefix}users` u ON t.user_id = u.id 
            ORDER BY t.created_at DESC LIMIT 5"
        );
    }
    
    // 获取最近的回复
    if ($storage_type === 'json') {
        // JSON存储：使用简单查询
        $recent_posts = $db->select('posts', [], 'created_at DESC', 5);
        
        // 获取用户和主题信息
        $users = [];
        $all_users = $db->select('users');
        foreach ($all_users as $u) {
            $users[$u['id']] = $u;
        }
        
        $topics = [];
        $all_topics = $db->select('topics');
        foreach ($all_topics as $t) {
            $topics[$t['id']] = $t;
        }
        
        // 关联数据
        foreach ($recent_posts as &$post) {
            $post['username'] = isset($users[$post['user_id']]) ? $users[$post['user_id']]['username'] : '未知用户';
            $post['topic_title'] = isset($topics[$post['topic_id']]) ? $topics[$post['topic_id']]['title'] : '未知主题';
        }
        unset($post);
    } else {
        // MySQL存储：使用JOIN查询
        $recent_posts = $db->fetchAll(
            "SELECT p.*, t.title as topic_title, u.username FROM `{$prefix}posts` p 
            JOIN `{$prefix}topics` t ON p.topic_id = t.id 
            JOIN `{$prefix}users` u ON p.user_id = u.id 
            ORDER BY p.created_at DESC LIMIT 5"
        );
    }
    
    // 获取系统信息
    $system_info = [
        'php_version' => PHP_VERSION,
        'mysql_version' => $storage_type === 'json' ? 'JSON Storage' : $db->fetchColumn("SELECT VERSION()"),
        'forum_version' => getSetting('forum_version', 'v0.1.8_t_260403'),
        'install_date' => getSetting('install_date', date('Y-m-d H:i:s')),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'server_os' => PHP_OS
    ];
    
} catch (Exception $e) {
    $error = '加载统计信息失败: ' . $e->getMessage();
}

// 设置页面标题
$page_title = '管理后台';

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
                        <h1>控制面板</h1>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" align="right">
                        <a href="../index.php" target="_blank">访问前台</a>
                    </td>
                </tr>
                
                <?php if (isset($error)): ?>
                    <tr>
                        <td colspan="2"><?php echo $error; ?></td>
                    </tr>
                <?php else: ?>
                    <!-- 统计卡片 -->
                    <tr>
                        <td colspan="2">
                            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                <tr>
                                    <td width="25%">
                                        <strong>用户数量</strong>
                                        <br>
                                        <?php echo $user_count; ?>
                                        <br>
                                        <a href="users.php">查看详情</a>
                                    </td>
                                    <td width="25%">
                                        <strong>主题数量</strong>
                                        <br>
                                        <?php echo $topic_count; ?>
                                        <br>
                                        <a href="topics.php">查看详情</a>
                                    </td>
                                    <td width="25%">
                                        <strong>回复数量</strong>
                                        <br>
                                        <?php echo $post_count; ?>
                                        <br>
                                        <a href="posts.php">查看详情</a>
                                    </td>
                                    <td width="25%">
                                        <strong>分类数量</strong>
                                        <br>
                                        <?php echo $category_count; ?>
                                        <br>
                                        <a href="categories.php">查看详情</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <tr>
                        <!-- 最近注册的用户 -->
                        <td width="50%" valign="top">
                            <table border="1" width="100%" cellspacing="0" cellpadding="5" style="margin-bottom: 10px;">
                                <tr>
                                    <td colspan="2"><h5>最近注册的用户</h5></td>
                                </tr>
                                <?php if (count($recent_users) > 0): ?>
                                    <?php foreach ($recent_users as $user): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                <?php if ($user['role'] === 'admin'): ?>
                                                    (管理员)
                                                <?php elseif ($user['role'] === 'moderator'): ?>
                                                    (版主)
                                                <?php endif; ?>
                                                <br>
                                                <small><?php echo htmlspecialchars($user['email']); ?></small>
                                            </td>
                                            <td align="right"><small><?php echo formatDateTime($user['created_at']); ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="2" align="center">暂无用户</td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td colspan="2" align="right">
                                        <a href="users.php">查看所有用户</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                        
                        <!-- 最近的主题 -->
                        <td width="50%" valign="top">
                            <table border="1" width="100%" cellspacing="0" cellpadding="5" style="margin-bottom: 10px;">
                                <tr>
                                    <td colspan="2"><h5>最近的主题</h5></td>
                                </tr>
                                <?php if (count($recent_topics) > 0): ?>
                                    <?php foreach ($recent_topics as $topic): ?>
                                        <tr>
                                            <td>
                                                <a href="../topic.php?id=<?php echo $topic['id']; ?>" target="_blank"><?php echo htmlspecialchars($topic['title']); ?></a>
                                                <br>
                                                <small>作者: <?php echo htmlspecialchars($topic['username']); ?></small>
                                            </td>
                                            <td align="right"><small><?php echo formatDateTime($topic['created_at']); ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="2" align="center">暂无主题</td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td colspan="2" align="right">
                                        <a href="topics.php">查看所有主题</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <tr>
                        <!-- 最近的回复 -->
                        <td width="50%" valign="top">
                            <table border="1" width="100%" cellspacing="0" cellpadding="5" style="margin-bottom: 10px;">
                                <tr>
                                    <td colspan="2"><h5>最近的回复</h5></td>
                                </tr>
                                <?php if (count($recent_posts) > 0): ?>
                                    <?php foreach ($recent_posts as $post): ?>
                                        <tr>
                                            <td>
                                                <a href="../topic.php?id=<?php echo $post['topic_id']; ?>#post-<?php echo $post['id']; ?>" target="_blank">回复: <?php echo htmlspecialchars($post['topic_title']); ?></a>
                                                <br>
                                                <small>作者: <?php echo htmlspecialchars($post['username']); ?></small>
                                            </td>
                                            <td align="right"><small><?php echo formatDateTime($post['created_at']); ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="2" align="center">暂无回复</td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td colspan="2" align="right">
                                        <a href="posts.php">查看所有回复</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                        
                        <!-- 系统信息 -->
                        <td width="50%" valign="top">
                            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                <tr>
                                    <td colspan="2"><h5>系统信息</h5></td>
                                </tr>
                                <tr>
                                    <td width="40%"><strong>论坛版本</strong></td>
                                    <td><?php echo htmlspecialchars($system_info['forum_version']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>安装日期</strong></td>
                                    <td><?php echo formatDateTime($system_info['install_date']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>PHP版本</strong></td>
                                    <td><?php echo htmlspecialchars($system_info['php_version']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>MySQL版本</strong></td>
                                    <td><?php echo htmlspecialchars($system_info['mysql_version']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>服务器软件</strong></td>
                                    <td><?php echo htmlspecialchars($system_info['server_software']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>服务器操作系统</strong></td>
                                    <td><?php echo htmlspecialchars($system_info['server_os']); ?></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
        </td>
    </tr>
</table>

<?php
// 加载页面底部
include __DIR__ . '/templates/admin_footer.php';
?>

