<?php
/**
 * 首页 - 支持伪静态URL
 */

// 启动会话
session_start();

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 加载配置
require_once __DIR__ . '/config/config.php';

// 加载函数库
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/daily_news_functions.php';
require("admin/includes/site_functions.php");

// 检查并更新每日60秒热点资讯
checkAndUpdate60sNews();

// 检查是否已安装
try {
    $result = isInstalled();
    error_log('isInstalled result: ' . var_export($result, true));
    if (!$result) {
        try {
            checkInstall();
        } catch (Exception $installException) {
            error_log('Error in checkInstall: ' . $installException->getMessage());
            // 显示友好的错误信息
            echo '<html><body><h1>安装失败</h1><p>系统安装过程中发生错误，请检查日志或联系管理员。</p><p>错误信息：' . htmlspecialchars($installException->getMessage()) . '</p></body></html>';
            exit;
        }
    }
} catch (Exception $e) {
    error_log('Error in isInstalled: ' . $e->getMessage());
    try {
        checkInstall();
    } catch (Exception $installException) {
        error_log('Error in checkInstall: ' . $installException->getMessage());
        // 显示友好的错误信息
        echo '<html><body><h1>安装失败</h1><p>系统安装过程中发生错误，请检查日志或联系管理员。</p><p>错误信息：' . htmlspecialchars($installException->getMessage()) . '</p></body></html>';
        exit;
    }
}

// 检查是否已登录
if (isset($_SESSION['user_id'])) {
    // 获取用户ID（使用会话中的用户ID，确保获取当前登录用户的信息）
    $user_id = $_SESSION['user_id'];
    
    // 获取用户信息（包含email和status字段）
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
        $user = $db->fetch(
            "SELECT id, username, email, avatar, created_at, updated_at, status, role 
             FROM `{$prefix}users` 
             WHERE `id` = :id",
            ['id' => $user_id]
        );
        
        // 存储用户状态到会话
        if ($user) {
            $_SESSION['status'] = $user['status'] ?? 'active';
            $_SESSION['role'] = $user['role'] ?? 'user';
            $_SESSION['username'] = $user['username'] ?? '';
        } else {
            // 如果用户不存在，清除会话
            session_unset();
            session_destroy();
            header('Location: ' . getHomeUrl());
            exit;
        }
        
        // 获取用户的最新被驳回的申诉记录
        $rejected_appeal = null;
        if ($storage_type === 'json') {
            $appeals = $db->select('appeals', ['user_id' => $user_id, 'status' => 'rejected'], 'updated_at DESC', 1);
            $rejected_appeal = $appeals[0] ?? null;
        } else {
            $rejected_appeal = $db->fetch(
                "SELECT * FROM `{$prefix}appeals` 
                WHERE `user_id` = :user_id AND `status` = 'rejected' 
                ORDER BY `updated_at` DESC LIMIT 1",
                ['user_id' => $user_id]
            );
        }
    } catch (Exception $e) {
        $error = '加载用户信息失败: ' . $e->getMessage();
    }
}

// 获取页码
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

// 获取排序方式
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created';
if (!in_array($sort, ['created', 'updated'])) {
    $sort = 'created';
}

// 每页显示的主题数
$topics_per_page = getSetting('topics_per_page', '12');

// 获取主题列表
try {
    $db = getDB();
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
    $cache = getCache();
    
    // 尝试从缓存获取统计数据
    $stats_cache_key = 'forum_stats';
    $stats = $cache->get($stats_cache_key);
    
    if (!$stats) {
        // 获取主题总数
        if (isset($_SESSION['user_id']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'moderator')) {
            // 管理员和版主可以看到所有主题，包括被隐藏的
            $total_topics = $db->fetchColumn(
                "SELECT COUNT(*) FROM `{$prefix}topics`"
            );
        } else if (isset($_SESSION['user_id'])) {
            // 普通登录用户可以看到已发布的主题和自己被隐藏的主题
            $total_topics = $db->fetchColumn(
                "SELECT COUNT(*) FROM `{$prefix}topics` WHERE `status` = 'published' OR (status = 'hidden' AND user_id = :user_id)",
                ['user_id' => $_SESSION['user_id']]
            );
        } else {
            // 未登录用户只能看到已发布的主题
            $total_topics = $db->fetchColumn(
                "SELECT COUNT(*) FROM `{$prefix}topics` WHERE `status` = 'published'"
            );
        }
        
        // 获取回复总数
        $total_posts = $db->fetchColumn(
            "SELECT COUNT(*) FROM `{$prefix}posts` WHERE `status` = 'published'"
        );
        
        // 获取用户总数（排除系统用户）
        $total_users = $db->fetchColumn(
            "SELECT COUNT(*) FROM `{$prefix}users` WHERE id NOT IN ('system', 'info')"
        );
        
        // 获取最新用户（排除系统用户）
        if ($storage_type === 'json') {
            // 先获取所有用户，然后过滤掉系统用户，再排序
            $all_users = $db->selectAll('users');
            $filtered_users = array_filter($all_users, function($user) {
                return $user['id'] !== 'system' && $user['id'] !== 'info';
            });
            // 按创建时间排序
            usort($filtered_users, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            $newest_user = $filtered_users[0] ?? null;
        } else {
            $newest_user = $db->fetch(
                "SELECT id, username FROM `{$prefix}users` WHERE id NOT IN ('system', 'info') ORDER BY created_at DESC LIMIT 1"
            );
        }
        
        $stats = [
            'total_topics' => $total_topics,
            'total_posts' => $total_posts,
            'total_users' => $total_users,
            'newest_user' => $newest_user
        ];
        
        // 缓存统计数据，有效期1分钟
        $cache->set($stats_cache_key, $stats, 60);
    } else {
        $total_topics = $stats['total_topics'];
        $total_posts = $stats['total_posts'];
        $total_users = $stats['total_users'];
        $newest_user = $stats['newest_user'];
    }
    
    // 计算总页数
    $total_pages = ceil($total_topics / $topics_per_page);
    
    // 获取当前页的主题列表
    $offset = ($page - 1) * $topics_per_page;
    
    // 尝试从缓存获取分类列表
    $categories_cache_key = 'categories';
    $categories = $cache->get($categories_cache_key);
    
    if (!$categories) {
        if ($storage_type === 'json') {
            // JSON存储：使用简单查询
            $categories = $db->selectAll('categories', [], 'sort_order ASC');
            $all_topics = $db->selectAll('topics', ['status' => 'published']);
            foreach ($categories as &$cat) {
                $count = 0;
                foreach ($all_topics as $t) {
                    if ($t['category_id'] == $cat['id']) {
                        $count++;
                    }
                }
                $cat['topic_count'] = $count;
            }
            unset($cat);
        } else {
            // MySQL存储：使用复杂SQL查询
            $categories = $db->fetchAll(
                "SELECT c.*, COUNT(t.id) as topic_count 
                FROM `{$prefix}categories` c 
                LEFT JOIN `{$prefix}topics` t ON c.id = t.category_id AND t.status = 'published'
                GROUP BY c.id 
                ORDER BY c.sort_order ASC"
            );
        }
        
        // 缓存分类列表，有效期2分钟
        $cache->set($categories_cache_key, $categories, 120);
    }
    
    // 获取主题列表（不缓存，因为需要实时数据）
    if ($storage_type === 'json') {
        // JSON存储：使用简单查询
        $order_by = $sort === 'created' ? 'is_sticky DESC, created_at DESC' : 'is_sticky DESC, last_post_time DESC';
        
        if (isset($_SESSION['user_id']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'moderator')) {
            // 管理员和版主可以看到所有主题，包括被隐藏的
            $all_topics = $db->selectAll('topics', [], $order_by);
        } else if (isset($_SESSION['user_id'])) {
            // 普通登录用户可以看到已发布的主题和自己被隐藏的主题
            $all_topics = $db->selectAll('topics', [], $order_by);
            $filtered_topics = [];
            foreach ($all_topics as $topic) {
                if ($topic['status'] === 'published' || ($topic['status'] === 'hidden' && $topic['user_id'] == $_SESSION['user_id'])) {
                    $filtered_topics[] = $topic;
                }
            }
            $all_topics = $filtered_topics;
        } else {
            // 未登录用户只能看到已发布的主题
            $all_topics = $db->selectAll('topics', ['status' => 'published'], $order_by);
        }
        
        // 应用分页
        $topics = array_slice($all_topics, $offset, $topics_per_page);
        
        // 获取用户和分类信息
        $users = [];
        $all_users = $db->selectAll('users');
        foreach ($all_users as $u) {
            $users[$u['id']] = $u;
        }
        
        // 添加系统账户支持
        $system_accounts = [
            'system' => ['username' => '系统通知'],
            'info' => ['username' => '互动消息']
        ];
        
        $cats = [];
        foreach ($categories as $c) {
            $cats[$c['id']] = $c;
        }
        
        // 关联数据
        foreach ($topics as &$topic) {
            // 设置默认值
            if (!isset($topic['is_sticky'])) {
                $topic['is_sticky'] = false;
            }
            if (!isset($topic['is_recommended'])) {
                $topic['is_recommended'] = false;
            }
            
            // 处理系统账户
            if (isset($system_accounts[$topic['user_id']])) {
                $topic['username'] = $system_accounts[$topic['user_id']]['username'];
                $topic['author_email'] = '';
            } else {
                $topic['username'] = isset($users[$topic['user_id']]) ? $users[$topic['user_id']]['username'] : '未知用户';
                $topic['author_email'] = isset($users[$topic['user_id']]) ? $users[$topic['user_id']]['email'] : '';
            }
            
            $topic['category_title'] = isset($cats[$topic['category_id']]) ? $cats[$topic['category_id']]['title'] : '未知分类';
            $topic['category_id'] = $topic['category_id'];
            $topic['last_post_username'] = '';
            if (!empty($topic['last_post_user_id'])) {
                if (isset($system_accounts[$topic['last_post_user_id']])) {
                    $topic['last_post_username'] = $system_accounts[$topic['last_post_user_id']]['username'];
                } elseif (isset($users[$topic['last_post_user_id']])) {
                    $topic['last_post_username'] = $users[$topic['last_post_user_id']]['username'];
                }
            }
        }
        unset($topic);
    } else {
        // MySQL存储：使用复杂SQL查询
        if (isset($_SESSION['user_id']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'moderator')) {
            // 管理员和版主可以看到所有主题，包括被隐藏的
            $topics = $db->fetchAll(
                "SELECT t.*, 
                        CASE 
                            WHEN t.user_id = 'system' THEN '系统通知'
                            WHEN t.user_id = 'info' THEN '互动消息'
                            ELSE u.username
                        END as username, 
                        CASE 
                            WHEN t.user_id IN ('system', 'info') THEN ''
                            ELSE u.email
                        END as author_email, 
                        c.title as category_title, c.id as category_id,
                        CASE 
                            WHEN t.last_post_user_id = 'system' THEN '系统通知'
                            WHEN t.last_post_user_id = 'info' THEN '互动消息'
                            ELSE lu.username
                        END as last_post_username
                FROM `{$prefix}topics` t 
                LEFT JOIN `{$prefix}users` u ON t.user_id = u.id AND t.user_id NOT IN ('system', 'info')
                JOIN `{$prefix}categories` c ON t.category_id = c.id 
                LEFT JOIN `{$prefix}users` lu ON t.last_post_user_id = lu.id AND t.last_post_user_id NOT IN ('system', 'info')
                ORDER BY t.is_sticky DESC, " . ($sort === 'created' ? 't.created_at DESC' : 't.last_post_time DESC') . " 
                LIMIT :offset, :limit",
                [
                    'offset' => $offset,
                    'limit' => $topics_per_page
                ]
            );
        } else if (isset($_SESSION['user_id'])) {
            // 普通登录用户可以看到已发布的主题和自己被隐藏的主题
            $topics = $db->fetchAll(
                "SELECT t.*, 
                        CASE 
                            WHEN t.user_id = 'system' THEN '【系统通知】'
                            WHEN t.user_id = 'info' THEN '【互动消息】'
                            ELSE u.username
                        END as username, 
                        CASE 
                            WHEN t.user_id IN ('system', 'info') THEN ''
                            ELSE u.email
                        END as author_email, 
                        c.title as category_title, c.id as category_id,
                        CASE 
                            WHEN t.last_post_user_id = 'system' THEN '【系统通知】'
                            WHEN t.last_post_user_id = 'info' THEN '【互动消息】'
                            ELSE lu.username
                        END as last_post_username
                FROM `{$prefix}topics` t 
                LEFT JOIN `{$prefix}users` u ON t.user_id = u.id AND t.user_id NOT IN ('system', 'info')
                JOIN `{$prefix}categories` c ON t.category_id = c.id 
                LEFT JOIN `{$prefix}users` lu ON t.last_post_user_id = lu.id AND t.last_post_user_id NOT IN ('system', 'info')
                WHERE t.status = 'published' OR (t.status = 'hidden' AND t.user_id = :user_id) 
                ORDER BY t.is_sticky DESC, " . ($sort === 'created' ? 't.created_at DESC' : 't.last_post_time DESC') . " 
                LIMIT :offset, :limit",
                [
                    'user_id' => $_SESSION['user_id'],
                    'offset' => $offset,
                    'limit' => $topics_per_page
                ]
            );
        } else {
            // 未登录用户只能看到已发布的主题
            $topics = $db->fetchAll(
                "SELECT t.*, 
                        CASE 
                            WHEN t.user_id = 'system' THEN '【系统通知】'
                            WHEN t.user_id = 'info' THEN '【互动消息】'
                            ELSE u.username
                        END as username, 
                        CASE 
                            WHEN t.user_id IN ('system', 'info') THEN ''
                            ELSE u.email
                        END as author_email, 
                        c.title as category_title, c.id as category_id,
                        CASE 
                            WHEN t.last_post_user_id = 'system' THEN '【系统通知】'
                            WHEN t.last_post_user_id = 'info' THEN '【互动消息】'
                            ELSE lu.username
                        END as last_post_username
                FROM `{$prefix}topics` t 
                LEFT JOIN `{$prefix}users` u ON t.user_id = u.id AND t.user_id NOT IN ('system', 'info')
                JOIN `{$prefix}categories` c ON t.category_id = c.id 
                LEFT JOIN `{$prefix}users` lu ON t.last_post_user_id = lu.id AND t.last_post_user_id NOT IN ('system', 'info')
                WHERE t.status = 'published' 
                ORDER BY t.is_sticky DESC, " . ($sort === 'created' ? 't.created_at DESC' : 't.last_post_time DESC') . " 
                LIMIT :offset, :limit",
                [
                    'offset' => $offset,
                    'limit' => $topics_per_page
                ]
            );
        }
        
        // 设置默认值
        foreach ($topics as &$topic) {
            if (!isset($topic['is_sticky'])) {
                $topic['is_sticky'] = false;
            }
            if (!isset($topic['is_recommended'])) {
                $topic['is_recommended'] = false;
            }
            // 确保系统账户显示正确
            if ($topic['user_id'] == 'system') {
                $topic['username'] = '【系统通知】';
                $topic['author_email'] = '';
            } elseif ($topic['user_id'] == 'info') {
                $topic['username'] = '【互动消息】';
                $topic['author_email'] = '';
            }
            if ($topic['last_post_user_id'] == 'system') {
                $topic['last_post_username'] = '【系统通知】';
            } elseif ($topic['last_post_user_id'] == 'info') {
                $topic['last_post_username'] = '【互动消息】';
            }
        }
        unset($topic);
    }
    
} catch (Exception $e) {
    $error = '加载主题列表失败: ' . $e->getMessage();
}

// 设置页面标题
$page_title = getSetting('site_title', 'PHP轻论坛') . ($page > 1 ? ' - 第' . $page . '页' : '');

// 加载页面头部
include __DIR__ . '/templates/header.php';
?>

<table border="1" width="100%" cellspacing="0" cellpadding="5">
    <!-- 欢迎信息 -->
    <tr>
        <td colspan="2">
            <strong><?php echo '<span style="color: blue;">' . htmlspecialchars(!empty($user['username']) ? $user['username'] : '游客') . '</span>'; ?> 欢迎来到 <?php echo htmlspecialchars(getSetting('site_name', 'PHP轻论坛')); ?></strong>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <?php echo htmlspecialchars(getSetting('site_description', '一个简单易用的PHP论坛程序')); ?>
        </td>
    </tr>
    <?php if (!isset($_SESSION['user_id'])): ?>
    <tr>
        <td colspan="2">
            您尚未登录。请 <a href="login.php">登录</a> 或 <a href="register.php">注册</a> 以参与讨论。
        </td>
    </tr>
    <?php elseif (isset($_SESSION['status']) && $_SESSION['status'] === 'restricted'): ?>
    <tr>
        <td colspan="2" style="background-color: #ffebee; color: #c62828; padding: 10px; border: 1px solid #ef9a9a; border-radius: 3px;">
            <strong>账号已被限制</strong><br>
            您的账号因违反论坛规则已被限制发布权限。
            <?php if (isset($rejected_appeal) && $rejected_appeal): ?>
                <br>驳回原因: <?php echo htmlspecialchars($rejected_appeal['process_note']); ?>
            <?php endif; ?>
            <br>您可以提交申诉以恢复正常权限。
            <a href="javascript:void(0);" onclick="openAppealModal('user', <?php echo $_SESSION['user_id']; ?>, '<?php echo addslashes($_SESSION['username']); ?>')" style="display: inline-block; margin-top: 0px; padding: 2px 5px; background-color: #ff9800; color: white; border-radius: 3px; text-decoration: none;">提交申诉</a>
        </td>
    </tr>
    <?php endif; ?>
    <!-- 实时北京时间 -->
    <tr>
        <td colspan="2" style="text-align: center; font-weight: bold; color: #1565c0;">
            当前时间：<span id="beijing-time"></span>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <?php showSiteAlert(); ?>
        </td>
    </tr>
    
    <!-- 主要内容区 -->
    <tr>
        <!-- 左侧最新主题 -->
        <td width="70%" valign="top">
            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                <tr>
                    <td style="font-weight: bold; font-size: 14px; padding: 4px 8px; white-space: nowrap;">最新主题</td>
                    <td style="padding: 4px 8px; text-align: right; white-space: nowrap;">
                        <form method="get" style="margin: 0; display: inline-block;">
                            <input type="hidden" name="page" value="<?php echo $page; ?>">
                            <select name="sort" onchange="this.form.submit()" style="font-size: 12px; padding: 2px 6px; border: 1px solid #ddd; border-radius: 2px; background-color: white;">
                                <option value="created" <?php echo $sort === 'created' ? 'selected' : ''; ?>>最新发布</option>
                                <option value="updated" <?php echo $sort === 'updated' ? 'selected' : ''; ?>>最新回复</option>
                            </select>
                        </form>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" align="right" style="padding: 5px;">
                        <?php if (isset($_SESSION['user_id']) && (!isset($_SESSION['status']) || $_SESSION['status'] !== 'restricted')): ?>
                            <a href="<?php echo getNewTopicUrl(); ?>" style="padding: 4px 8px; background-color: #f0f0f0; border: 1px solid #ddd; text-decoration: none; border-radius: 2px;">发布新主题</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (isset($error)): ?>
                    <tr>
                        <td colspan="2" style="color: red;"><?php echo $error; ?></td>
                    </tr>
                <?php elseif (empty($topics)): ?>
                    <tr>
                        <td colspan="2" align="center">暂无主题</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($topics as $topic): ?>
                        <tr>
                            <td colspan="2">
                                <a href="<?php echo getTopicUrl($topic['id'], null, $topic['title']); ?>">
                                        <?php $report_flag = getReportFlag('topic', $topic['id']); ?>
                                        <?php if ($report_flag === 'yellow'): ?>
                                            <span style="font-weight: bold; color: white; background-color: orange; padding: 2px 5px; border-radius: 3px;">[警告] </span>
                                        <?php elseif ($report_flag === 'red'): ?>
                                            <span style="font-weight: bold; color: white; background-color: red; padding: 2px 5px; border-radius: 3px;">[严重警告] </span>
                                        <?php elseif ($report_flag === 'ban'): ?>
                                            <span style="font-weight: bold; color: white; background-color: darkred; padding: 2px 5px; border-radius: 3px;">[已封禁] </span>
                                        <?php endif; ?>
                                        <?php if ($topic['is_sticky']): ?>
                                            <span style="font-weight: bold; color: red;">[置顶] </span>
                                        <?php endif; ?>
                                        <?php if ($topic['is_recommended']): ?>
                                            <span style="font-weight: bold; color: blue;">[推荐] </span>
                                        <?php endif; ?>
                                        <?php if (isset($topic['status']) && $topic['status'] === 'hidden'): ?>
                                            <span style="font-weight: bold; color: white; background-color: red; padding: 2px 5px; border-radius: 3px;">[已隐藏] </span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($topic['title']); ?>
                                    </a>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 5px;">
                                    <div>
                                        <small>作者: <a href="<?php echo getUserProfileUrl($topic['user_id'], $topic['username']); ?>"><?php echo htmlspecialchars($topic['username']); ?></a> | 分类: <a href="<?php echo getCategoryUrl($topic['category_id'], null, $topic['category_title']); ?>"><?php echo htmlspecialchars($topic['category_title']); ?></a></small>
                                        <?php if (isset($topic['status']) && $topic['status'] === 'hidden'): ?>
                                            <small style="color: red;"> [已隐藏]</small>
                                        <?php endif; ?>
                                        <?php if ($topic['last_post_time'] && $topic['last_post_time'] != $topic['created_at']): ?>
                                            <br>
                                            <small>最后回复: <a href="<?php echo getUserProfileUrl($topic['last_post_user_id'], $topic['last_post_username']); ?>"><?php echo htmlspecialchars($topic['last_post_username']); ?></a> (<?php echo formatDateTime($topic['last_post_time'], 'm-d H:i'); ?>)</small>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $topic['user_id'] && isset($topic['status']) && $topic['status'] === 'hidden'): ?>
                                        <a href="javascript:void(0);" onclick="openAppealModal('topic', <?php echo $topic['id']; ?>, '<?php echo addslashes($topic['title']); ?>')" style="display: inline-block; padding: 2px 5px; background-color: #ff9800; color: white; border-radius: 3px; font-size: 0.8em; text-decoration: none;">申诉</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($total_pages > 1): ?>
                        <tr>
                            <td colspan="2" align="center" style="padding: 10px;">
                                <?php 
                                    $pagination_url = getPaginationUrlPattern('index.php', ['sort' => $sort]);
                                    echo generatePagination($page, $total_pages, $pagination_url); 
                                ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endif; ?>
            </table>
        </td>
        
        <!-- 右侧边栏 -->
        <td width="30%" valign="top">
            <!-- 分类列表 -->
            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                <tr>
                    <td><strong>分类列表</strong></td>
                </tr>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td>
                            <a href="<?php echo getCategoryUrl($category['id'], null, $category['title']); ?>"><?php echo htmlspecialchars($category['title']); ?></a>
                            <span style="float: right;">(<?php echo $category['topic_count']; ?>)</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            
            <!-- 论坛统计 -->
            <table border="1" width="100%" cellspacing="0" cellpadding="5" style="margin-top: 10px;">
                <tr>
                    <td><strong>论坛统计</strong></td>
                </tr>
                <tr>
                    <td>主题数: <?php echo $total_topics; ?></td>
                </tr>
                <tr>
                    <td>回复数: <?php echo $total_posts; ?></td>
                </tr>
                <tr>
                    <td>用户数: <?php echo $total_users; ?></td>
                </tr>
                <?php if ($newest_user): ?>
                    <tr>
                        <td>最新会员: <a href="<?php echo getUserProfileUrl($newest_user['id'], $newest_user['username']); ?>"><?php echo htmlspecialchars($newest_user['username']); ?></a></td>
                    </tr>
                <?php endif; ?>
            </table>
            
            <!-- 用户列表 -->
            <table border="1" width="100%" cellspacing="0" cellpadding="5" style="margin-top: 10px;">
                <tr>
                    <td><strong>最新注册</strong></td>
                </tr>
                <?php
                try {
                    $db = getDB();
                    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
                    
                    if ($storage_type === 'json') {
                        // 先获取所有用户，然后过滤掉系统用户，再排序
                        $all_users = $db->selectAll('users');
                        $filtered_users = array_filter($all_users, function($user) {
                            return $user['id'] !== 'system' && $user['id'] !== 'info';
                        });
                        // 按创建时间排序
                        usort($filtered_users, function($a, $b) {
                            return strtotime($b['created_at']) - strtotime($a['created_at']);
                        });
                        // 取前4个
                        $latest_users = array_slice($filtered_users, 0, 4);
                    } else {
                        $latest_users = $db->fetchAll("SELECT `username`, `id`, `created_at` FROM `{$prefix}users` WHERE id NOT IN ('system', 'info') ORDER BY `created_at` DESC LIMIT 4");
                    }
                    
                    if (count($latest_users) > 0) {
                        foreach ($latest_users as $user) {
                            $report_flag = getReportFlag('user', $user['id']);
                            echo '<tr>';
                            echo '<td>';
                            if ($report_flag === 'yellow') {
                                echo '<span style="font-weight: bold; color: white; background-color: orange; padding: 2px 5px; border-radius: 3px;">[警告] </span>';
                            } elseif ($report_flag === 'red') {
                                echo '<span style="font-weight: bold; color: white; background-color: red; padding: 2px 5px; border-radius: 3px;">[严重警告] </span>';
                            } elseif ($report_flag === 'ban') {
                                echo '<span style="font-weight: bold; color: white; background-color: darkred; padding: 2px 5px; border-radius: 3px;">[已封禁] </span>';
                            }
                            echo '<a href="' . getUserProfileUrl($user['id'], $user['username']) . '">' . htmlspecialchars($user['username']) . '</a> <small>(' . formatDateTime($user['created_at'], 'm-d') . ')</small>';
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td>暂无用户</td></tr>';
                    }
                } catch (Exception $e) {
                    echo '<tr><td>加载用户数据时出错</td></tr>';
                }
                ?>
            </table>
            
            <!-- 热门标签 -->
            <table border="1" width="100%" cellspacing="0" cellpadding="5" style="margin-top: 10px;">
                <tr>
                    <td><strong>热门标签</strong></td>
                </tr>
                <?php
                try {
                    // 加载content_functions.php以使用getHotTags函数
                    require_once __DIR__ . '/includes/content_functions.php';
                    
                    $hot_tags = getHotTags(10);
                    if (!empty($hot_tags)) {
                        echo '<tr>';
                        echo '<td style="padding: 10px;">';
                        foreach ($hot_tags as $tag) {
                            $count = isset($tag['count']) ? (int)$tag['count'] : 0;
                            $first_count = isset($hot_tags[0]['count']) ? (int)$hot_tags[0]['count'] : 1;
                            $font_size = 12 + ($count / max(1, $first_count)) * 8;
                            echo '<a href="search.php?tag=' . urlencode($tag['name']) . '" style="display: inline-block; padding: 2px 6px; margin: 2px; background-color: #f0f0f0; border-radius: 10px; font-size: ' . $font_size . 'px; color: #333; text-decoration: none;">' . htmlspecialchars($tag['name']) . ' (' . $count . ')</a>';
                        }
                        echo '</td>';
                        echo '</tr>';
                    } else {
                        echo '<tr><td>暂无标签</td></tr>';
                    }
                } catch (Exception $e) {
                    echo '<tr><td>加载标签数据时出错</td></tr>';
                }
                ?>
            </table>
        </td>
    </tr>
    
    <!-- 分类主题列表 -->
    <?php
    /*
    foreach ($categories as $category) {
        echo '<tr>';
        echo '<td colspan="2" style="padding-top: 15px;">';
        echo '<table border="1" width="100%" cellspacing="0" cellpadding="5">';
        echo '<tr>';
        echo '<td width="80%"><strong>' . htmlspecialchars($category['title']) . '</strong></td>';
        echo '<td width="20%" align="right"><a href="category.php?id=' . $category['id'] . '">查看全部</a></td>';
        echo '</tr>';
        if (!empty($category['description'])) {
            echo '<tr>';
            echo '<td colspan="2"><small>' . htmlspecialchars($category['description']) . '</small></td>';
            echo '</tr>';
        }
        
        // 获取该分类下的最新主题
        if ($storage_type === 'json') {
            $cat_topics = $db->select('topics', ['category_id' => $category['id'], 'status' => 'published'], 'is_sticky DESC, created_at DESC', 5);
            $all_users = $db->select('users');
            $users_map = [];
            foreach ($all_users as $u) {
                $users_map[$u['id']] = $u['username'];
            }
            foreach ($cat_topics as &$t) {
                $t['username'] = isset($users_map[$t['user_id']]) ? $users_map[$t['user_id']] : '未知用户';
            }
            unset($t);
        } else {
            $cat_topics = $db->fetchAll(
                "SELECT t.*, u.username 
                FROM `{$prefix}topics` t 
                JOIN `{$prefix}users` u ON t.user_id = u.id 
                WHERE t.category_id = :category_id AND t.status = 'published' 
                ORDER BY t.is_sticky DESC, t.created_at DESC 
                LIMIT 5",
                ['category_id' => $category['id']]
            );
        }
        
        if (count($cat_topics) > 0) {
            foreach ($cat_topics as $topic) {
                echo '<tr>';
                echo '<td>';
                if ($topic['is_sticky']) {
                    echo '<span style="font-weight: bold;">[置顶] </span>';
                }
                echo '<a href="topic.php?id=' . $topic['id'] . '">' . htmlspecialchars($topic['title']) . '</a>';
                echo '<br>';
                echo '<small>by ' . htmlspecialchars($topic['username']) . '</small>';
                echo '</td>';
                echo '<td align="right" valign="top"><small>' . $topic['view_count'] . ' 浏览</small></td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="2" align="center">暂无主题</td></tr>';
        }
        
        echo '</table>';
        echo '</td>';
        echo '</tr>';
    }
    */
    ?>
    
    <!-- 友情链接 -->
    <?php
    // 获取启用的友链列表
    $links = getActiveLinks();
    if (!empty($links)): ?>
    <tr>
        <td colspan="2" style="padding-top: 15px;">
            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                <tr>
                    <td><strong>友情链接</strong></td>
                </tr>
                <tr>
                    <td style="text-align: center;">
                        <?php 
                        $link_count = count($links);
                        $i = 0;
                        foreach ($links as $link): 
                            echo '<a href="' . $link['url'] . '" target="_blank">' . htmlspecialchars($link['name']) . '</a>';
                            $i++;
                            if ($i < $link_count) {
                                echo ' | ';
                            }
                        endforeach; 
                        ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <?php endif; ?>
</table>

<!-- 申诉弹窗 -->
<div id="appealModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: white; padding: 20px; border-radius: 5px; width: 400px; max-width: 90%;">
        <h3 style="margin-top: 0; margin-bottom: 20px;">申诉</h3>
        <form id="appealForm" method="post" action="appeal.php">
            <input type="hidden" name="appeal_type" id="appealType">
            <input type="hidden" name="target_id" id="appealTargetId">
            <div style="margin-bottom: 15px;">
                <label for="appealReason" style="display: block; margin-bottom: 5px;">申诉原因：</label>
                <textarea id="appealReason" name="reason" rows="4" style="width: 100%; padding: 5px; resize: vertical;" placeholder="请详细说明申诉原因..." required></textarea>
            </div>
            <div style="text-align: right;">
                <button type="button" onclick="closeAppealModal()" style="padding: 5px 15px; margin-right: 10px; background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 3px; cursor: pointer;">取消</button>
                <button type="submit" style="padding: 5px 15px; background-color: #ff9800; color: white; border: none; border-radius: 3px; cursor: pointer;">提交申诉</button>
            </div>
        </form>
    </div>
</div>

<?php
// 加载页面底部
include __DIR__ . '/templates/footer.php';
?>

<script>
// 实时显示北京时间
function updateBeijingTime() {
    const now = new Date();
    // 使用toLocaleString获取北京时间
    const beijingTime = now.toLocaleString('zh-CN', {
        timeZone: 'Asia/Shanghai',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    });
    
    // 格式化时间字符串
    // toLocaleString 返回格式: 2026/04/03 00:15:52
    // 先替换第一个 / 为 年，第二个 / 为 月，空格替换为 日
    const timeString = beijingTime.replace(/\//, '年').replace(/\//, '月').replace(/ /, '日 ');
    document.getElementById('beijing-time').textContent = timeString;
}

// 初始更新
updateBeijingTime();

// 每秒更新一次
setInterval(updateBeijingTime, 1000);

// 打开申诉弹窗
function openAppealModal(type, id, name) {
    document.getElementById('appealType').value = type;
    document.getElementById('appealTargetId').value = id;
    document.getElementById('appealModal').style.display = 'block';
}

// 关闭申诉弹窗
function closeAppealModal() {
    document.getElementById('appealModal').style.display = 'none';
    document.getElementById('appealForm').reset();
}

// 处理申诉表单提交
document.getElementById('appealForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('appeal.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('申诉提交成功，管理员会尽快审核');
            closeAppealModal();
        } else {
            alert('申诉失败：' + data.message);
        }
    })
    .catch(error => {
        alert('提交失败：' + error.message);
    });
});

// 小彩蛋功能
let easterEggClicks = 0;
const siteNameElement = document.querySelector('strong');

if (siteNameElement) {
    siteNameElement.addEventListener('click', function() {
        easterEggClicks++;
        
        if (easterEggClicks === 5) {
            // 显示彩蛋消息
            const easterEggModal = document.createElement('div');
            easterEggModal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.8);
                z-index: 2000;
                display: flex;
                justify-content: center;
                align-items: center;
                animation: fadeIn 0.5s ease-in-out;
            `;
            
            const easterEggContent = document.createElement('div');
            easterEggContent.style.cssText = `
                background-color: white;
                padding: 30px;
                border-radius: 10px;
                text-align: center;
                max-width: 500px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
                animation: slideIn 0.5s ease-in-out;
            `;
            
            easterEggContent.innerHTML = `
                <h2 style="color: #ff6b6b; margin-bottom: 20px;">🎉 恭喜你发现了彩蛋！</h2>
                <p style="font-size: 16px; margin-bottom: 20px;">你是一个细心的用户，这是给你的小奖励！</p>
                <p style="font-size: 14px; color: #666; margin-bottom: 30px;">论坛 v0.2.0_t_260404</p>
                <button onclick="this.parentElement.parentElement.remove()" style="
                    padding: 10px 20px;
                    background-color: #4ecdc4;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 14px;
                ">关闭</button>
            `;
            
            const style = document.createElement('style');
            style.textContent = `
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                @keyframes slideIn {
                    from { transform: translateY(-50px); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }
            `;
            
            document.head.appendChild(style);
            easterEggModal.appendChild(easterEggContent);
            document.body.appendChild(easterEggModal);
            
            // 重置点击计数
            easterEggClicks = 0;
        }
    });
}
</script>
