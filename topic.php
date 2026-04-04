<?php
date_default_timezone_set('Asia/Shanghai');
/**
 * 主题详情页面
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
require_once __DIR__ . '/includes/mail_functions.php';
require_once __DIR__ . '/includes/content_functions.php';

// 检查安装状态和闭站模式
checkInstall();

// 获取主题ID
$topic_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($topic_id <= 0) {
    header('Location: ' . getHomeUrl());
    exit;
}

// 获取页码
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

// 获取排序方式
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
// 验证排序参数
if (!in_array($sort, ['newest', 'oldest'])) {
    $sort = 'newest';
}

// 每页显示的回复数
$posts_per_page = (int)getSetting('posts_per_page', 15);

// 获取主题信息和回复列表
try {
    $db = getDB();
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
    
    if ($storage_type === 'json') {
        // JSON存储：使用简单查询
        $topic = $db->findById('topics', $topic_id);
        
        // 允许用户查看自己被隐藏的主题，管理员和版主可以查看所有主题
        $can_view = false;
        if ($topic['status'] === 'published') {
            $can_view = true;
        } else if (isset($_SESSION['user_id'])) {
            if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'moderator') {
                $can_view = true;
            } else if ($topic['user_id'] == $_SESSION['user_id']) {
                $can_view = true;
            }
        }
        
        if (!$topic || !$can_view) {
            header('Location: ' . getHomeUrl());
            exit;
        }
        
        // 获取用户和分类信息
        $user = $db->findById('users', $topic['user_id']);
        $category = $db->findById('categories', $topic['category_id']);
        
        $topic['username'] = $user ? $user['username'] : '未知用户';
        $topic['role'] = $user ? ($user['role'] ?? 'user') : 'user';
        $topic['category_title'] = $category ? $category['title'] : '未知分类';
        $topic['category_id'] = $topic['category_id'];
    } else {
        // MySQL存储：使用JOIN查询
        if (isset($_SESSION['user_id']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'moderator')) {
            // 管理员和版主可以查看所有主题，包括被隐藏的
            $topic = $db->fetch(
                "SELECT t.*, u.username, u.role, c.title as category_title, c.id as category_id 
                FROM `{$prefix}topics` t 
                JOIN `{$prefix}users` u ON t.user_id = u.id 
                JOIN `{$prefix}categories` c ON t.category_id = c.id 
                WHERE t.id = :id",
                ['id' => $topic_id]
            );
        } else if (isset($_SESSION['user_id'])) {
            // 普通登录用户可以查看已发布的主题和自己被隐藏的主题
            $topic = $db->fetch(
                "SELECT t.*, u.username, u.role, c.title as category_title, c.id as category_id 
                FROM `{$prefix}topics` t 
                JOIN `{$prefix}users` u ON t.user_id = u.id 
                JOIN `{$prefix}categories` c ON t.category_id = c.id 
                WHERE t.id = :id AND (t.status = 'published' OR (t.status = 'hidden' AND t.user_id = :user_id))",
                ['id' => $topic_id, 'user_id' => $_SESSION['user_id']]
            );
        } else {
            // 未登录用户只能查看已发布的主题
            $topic = $db->fetch(
                "SELECT t.*, u.username, u.role, c.title as category_title, c.id as category_id 
                FROM `{$prefix}topics` t 
                JOIN `{$prefix}users` u ON t.user_id = u.id 
                JOIN `{$prefix}categories` c ON t.category_id = c.id 
                WHERE t.id = :id AND t.status = 'published'",
                ['id' => $topic_id]
            );
        }
    }
    
    if (!$topic) {
        header('Location: ' . getHomeUrl());
        exit;
    }
    
    // 设置默认值
    if (!isset($topic['is_sticky'])) {
        $topic['is_sticky'] = false;
    }
    if (!isset($topic['is_recommended'])) {
        $topic['is_recommended'] = false;
    }
    
    // 更新浏览次数
    $db->update(
        "{$prefix}topics",
        ['view_count' => $topic['view_count'] + 1],
        "`id` = :id",
        ['id' => $topic_id]
    );
    
    // 获取回复总数
    $total_posts = $db->fetchColumn(
        "SELECT COUNT(*) FROM `{$prefix}posts` WHERE `topic_id` = :topic_id AND `status` = 'published'",
        ['topic_id' => $topic_id]
    );
    
    // 计算总页数
    $total_pages = ceil(($total_posts + 1) / $posts_per_page); // +1 是因为主题内容也算一个帖子
    
    // 获取当前页的回复列表
    $offset = ($page - 1) * $posts_per_page;
    
    // 如果是第一页，则减去1，因为主题内容占用了一个位置
    if ($page == 1) {
        $limit = $posts_per_page - 1;
        $offset = 0;
    } else {
        $limit = $posts_per_page;
        $offset = $offset - 1; // 减去主题内容占用的位置
    }
    
    $posts = [];
    
    if ($limit > 0) {
        // 根据排序参数确定排序方向
        $order_direction = $sort === 'newest' ? 'DESC' : 'ASC';
        
        if ($storage_type === 'json') {
            // JSON存储：使用简单查询
            $posts = $db->select('posts', ['topic_id' => $topic_id, 'status' => 'published'], 'created_at ' . $order_direction, $limit);
            
            // 获取用户信息
            $users = [];
            $all_users = $db->select('users');
            foreach ($all_users as $u) {
                $users[$u['id']] = $u;
            }
            
            // 关联用户数据
            foreach ($posts as &$post) {
                $post['username'] = isset($users[$post['user_id']]) ? $users[$post['user_id']]['username'] : '未知用户';
                $post['avatar'] = isset($users[$post['user_id']]) ? ($users[$post['user_id']]['avatar'] ?? '') : '';
                $post['role'] = isset($users[$post['user_id']]) ? ($users[$post['user_id']]['role'] ?? 'user') : 'user';
                $post['user_created_at'] = isset($users[$post['user_id']]) ? ($users[$post['user_id']]['created_at'] ?? '') : '';
                $post['email'] = isset($users[$post['user_id']]) ? ($users[$post['user_id']]['email'] ?? '') : '';
            }
            unset($post);
        } else {
            // MySQL存储：使用JOIN查询
            $posts = $db->fetchAll(
                "SELECT p.*, u.username, u.avatar, u.email, u.role, u.created_at as user_created_at 
                FROM `{$prefix}posts` p 
                JOIN `{$prefix}users` u ON p.user_id = u.id 
                WHERE p.topic_id = :topic_id AND p.status = 'published' 
                ORDER BY p.created_at {$order_direction} 
                LIMIT :offset, :limit",
                [
                    'topic_id' => $topic_id,
                    'offset' => $offset,
                    'limit' => $limit
                ]
            );
        }
    }
    
    // 处理回复表单提交
    $error = '';
    $success = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
        $content = $_POST['content'] ?? '';
        $reply_to = isset($_POST['reply_to']) ? (int)$_POST['reply_to'] : null;
        
        // 验证输入
        if (empty(trim($content))) {
            $error = '请填写回复内容';
        } else if (strlen(trim($content)) < 1) {
            $error = '回复内容不能为空';
        } else if ($topic['is_locked']) {
            $error = '该主题已被锁定，无法回复';
        } else if (isset($_SESSION['status']) && $_SESSION['status'] === 'restricted') {
            $error = '您的账号已被限制，无法回复主题';
        } else {
            try {
                // 清理和验证内容
                $content = trim($content);
                
                // 获取IP地址和详细信息
                $ip_address = getClientIp();
                $ip_info = getIpInfo($ip_address);
                
                // 创建回复
                $result = $db->insert("{$prefix}posts", [
                    'topic_id' => $topic_id,
                    'user_id' => $_SESSION['user_id'],
                    'content' => $content,
                    'reply_to' => $reply_to,
                    'status' => 'published',
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_ip' => $ip_address,
                    'ip_info' => $ip_info ? json_encode($ip_info) : null
                ]);
                
                if (!$result) {
                    throw new Exception('插入回复数据失败');
                }
                
                $post_id = $db->lastInsertId();
                
                if (empty($post_id)) {
                    throw new Exception('获取回复ID失败');
                }
                
                // 更新主题的最后回复信息
                $update_result = $db->update(
                    "{$prefix}topics",
                    [
                        'last_post_id' => $post_id,
                        'last_post_user_id' => $_SESSION['user_id'],
                        'last_post_time' => date('Y-m-d H:i:s')
                    ],
                    "`id` = :id",
                    ['id' => $topic_id]
                );
                
                if (!$update_result) {
                    throw new Exception('更新主题信息失败');
                }
                
                // 获取被回复用户信息
                $reply_to_user_info = [];
                if ($reply_to > 0) {
                    // 检查是回复主题还是回复回复
                    $reply_to_post = $db->fetch(
                        "SELECT * FROM `{$prefix}posts` WHERE `id` = :reply_to",
                        ['reply_to' => $reply_to]
                    );
                    
                    if ($reply_to_post) {
                        // 是回复回复
                        $reply_to_user = $db->fetch(
                            "SELECT * FROM `{$prefix}users` WHERE `id` = :user_id",
                            ['user_id' => $reply_to_post['user_id']]
                        );
                        if ($reply_to_user) {
                            $reply_to_user_info = [
                                'reply_to_user_id' => $reply_to_user['id'],
                                'reply_to_username' => $reply_to_user['username']
                            ];
                        }
                    } else {
                        // 可能是回复主题
                        $reply_to_topic = $db->fetch(
                            "SELECT * FROM `{$prefix}topics` WHERE `id` = :reply_to",
                            ['reply_to' => $reply_to]
                        );
                        if ($reply_to_topic) {
                            $reply_to_user = $db->fetch(
                                "SELECT * FROM `{$prefix}users` WHERE `id` = :user_id",
                                ['user_id' => $reply_to_topic['user_id']]
                            );
                            if ($reply_to_user) {
                                $reply_to_user_info = [
                                    'reply_to_user_id' => $reply_to_user['id'],
                                    'reply_to_username' => $reply_to_user['username']
                                ];
                            }
                        }
                    }
                }
                
                // 记录回复日志
                logAction('用户发表回复', 'post', $post_id, [
                    'topic_id' => $topic_id,
                    'topic_title' => $topic['title'],
                    'reply_to' => $reply_to,
                    'reply_to_user_id' => $reply_to_user_info['reply_to_user_id'] ?? null,
                    'reply_to_username' => $reply_to_user_info['reply_to_username'] ?? null,
                    'content_length' => mb_strlen($content),
                    'reply_time' => date('Y-m-d H:i:s'),
                    'reply_ip' => getClientIp()
                ]);
                
                $success = '回复成功';
                
                // 重定向到最后一页
                $new_total_posts = $total_posts + 1;
                $new_total_pages = ceil(($new_total_posts + 1) / $posts_per_page);
                
                // 发送回复通知
                sendTopicReplyNotification($topic_id, $_SESSION['user_id'], $content, $reply_to);
                
                header('Location: ' . getTopicUrl($topic_id, $new_total_pages, $topic['title']) . '#post-' . $post_id);
                exit;
            } catch (Exception $e) {
                $error = '回复失败: ' . $e->getMessage();
                // 记录详细错误信息
                error_log('回复失败：' . $e->getMessage() . ' ' . $e->getTraceAsString());
            }
        }
    }
    
} catch (Exception $e) {
    $error = '加载主题信息失败: ' . $e->getMessage();
}

// 设置页面标题
$page_title = isset($topic) ? $topic['title'] : '主题详情';

// 加载页面头部
include __DIR__ . '/templates/header.php';
?>
<table border="1" width="100%" cellspacing="0" cellpadding="10">
    <?php if (isset($error) && !isset($topic)): ?>
        <tr>
            <td><?php echo $error; ?></td>
        </tr>
    <?php else: ?>
        <tr>
            <td colspan="2">
                <h1 style="margin: 0; display: flex; justify-content: space-between; align-items: center;">
                    <span>
                        <?php echo htmlspecialchars($topic['title']); ?>
                        <?php $report_flag = getReportFlag('topic', $topic['id']); ?>
                        <?php if ($report_flag === 'yellow'): ?>
                            <span style="font-weight: bold; color: white; background-color: orange; padding: 2px 5px; border-radius: 3px; margin-left: 10px; font-size: 0.8em;">[警告]</span>
                        <?php elseif ($report_flag === 'red'): ?>
                            <span style="font-weight: bold; color: white; background-color: red; padding: 2px 5px; border-radius: 3px; margin-left: 10px; font-size: 0.8em;">[严重警告]</span>
                        <?php elseif ($report_flag === 'ban'): ?>
                            <span style="font-weight: bold; color: white; background-color: darkred; padding: 2px 5px; border-radius: 3px; margin-left: 10px; font-size: 0.8em;">[已封禁]</span>
                        <?php endif; ?>
                        <?php if ($topic['is_sticky']): ?>
                            <span style="color: red; margin-left: 10px; font-size: 0.8em;">[置顶]</span>
                        <?php endif; ?>
                        <?php if ($topic['is_recommended']): ?>
                            <span style="font-weight: bold; color: blue; margin-left: 10px; font-size: 0.8em;">[推荐]</span>
                        <?php endif; ?>
                        <?php if (isset($topic['status']) && $topic['status'] === 'hidden'): ?>
                            <span style="color: orange; margin-left: 10px; font-size: 0.8em;">[已隐藏]</span>
                        <?php endif; ?>
                    </span>
                    <?php if (isset($_SESSION['user_id']) && isset($topic['status']) && $topic['status'] === 'hidden' && $_SESSION['user_id'] == $topic['user_id']): ?>
                        <a href="javascript:void(0);" onclick="openAppealModal('topic', <?php echo $topic['id']; ?>, '<?php echo addslashes($topic['title']); ?>')" style="display: inline-block; padding: 0px 5px; background-color: #ff9800; color: white; border-radius: 3px; text-decoration: none; font-size: 0.8em;">申诉</a>
                    <?php endif; ?>
                </h1>
                <!-- 主题标签 -->
                <?php
                $topic_tags = getTopicTags($topic_id);
                if (!empty($topic_tags)) {
                    echo '<div style="margin-top: 10px;">';
                    echo '<span style="font-size: 14px; color: #666;">标签：</span>';
                    foreach ($topic_tags as $tag) {
                        echo '<a href="search.php?tag=' . urlencode($tag['name']) . '" style="display: inline-block; padding: 2px 8px; background-color: #f0f0f0; border-radius: 10px; margin-right: 5px; font-size: 12px; color: #333; text-decoration: none;">' . htmlspecialchars($tag['name']) . '</a>';
                    }
                    echo '</div>';
                }
                ?>
            </td>
        </tr>
        <tr>
            <td colspan="2" align="right">
                <a href="<?php echo getHomeUrl(); ?>">首页</a> &gt; 
                <a href="<?php echo getCategoriesUrl(); ?>">分类列表</a> &gt; 
                <a href="<?php echo getCategoryUrl($topic['category_id'], null, $topic['category_title']); ?>"><?php echo htmlspecialchars($topic['category_title']); ?></a> &gt; 
                主题详情
            </td>
        </tr>
        
        <!-- 排序选择 -->
        <tr style="height: auto; min-height: 30px;">
            <td style="padding: 5px;">共 <?php echo $total_posts; ?> 条回复</td>
            <td align="right" nowrap style="white-space: nowrap; padding: 5px;">
                <form method="get" action="<?php echo getTopicUrl($topic_id, 1, $topic['title']); ?>" style="display: inline-block; margin: 0;">
                    <input type="hidden" name="id" value="<?php echo $topic_id; ?>">
                    <input type="hidden" name="page" value="1">
                    <span style="display: inline-block; margin-right: 5px; vertical-align: middle;">排序: </span>
                    <select name="sort" onchange="this.form.submit()" style="display: inline-block; vertical-align: middle; padding: 2px;">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>最新</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>最旧</option>
                    </select>
                </form>
            </td>
        </tr>
        
        <?php if ($page == 1): ?>
            <tr>
                <td colspan="2">
                    <table border="1" width="100%" cellspacing="0" cellpadding="5" id="post-<?php echo $topic['id']; ?>">
                        <tr>
                            <td width="100%">
                                <a href="<?php echo getUserProfileUrl($topic['user_id']); ?>"><strong><?php echo htmlspecialchars($topic['username']); ?></strong></a> 
                                <?php if ($topic['role'] === 'admin'): ?>
                                    <span style="color: red;">(管理员)</span>
                                <?php elseif ($topic['role'] === 'moderator'): ?>
                                    <span style="color: blue;">(版主)</span>
                                <?php endif; ?>
                                <span><?php echo formatDateTime($topic['created_at']); ?></span>
                                <?php if (isset($topic['ip_info'])): ?>
                                    <?php $ip_info = json_decode($topic['ip_info'], true); ?>
                                    <?php if (isset($ip_info['prov']) && isset($ip_info['city'])): ?>
                                        <span style="margin-left: 10px; color: #999; font-size: 12px;">[<?php echo htmlspecialchars($ip_info['prov'] . ' ' . $ip_info['city']); ?>]</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td align="right" style="white-space: nowrap; width: 1%;">
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <a href="javascript:void(0);" class="reply-btn reply-topic-btn" data-username="<?php echo htmlspecialchars($topic['username']); ?>" style="display: inline-block;">回复</a><span style="margin: 0 8px;">|</span><a href="javascript:void(0);" onclick="openReportModal('topic', <?php echo $topic['id']; ?>, '<?php echo addslashes($topic['title']); ?>')" style="display: inline-block;">举报</a><?php if ($_SESSION['user_id'] == $topic['user_id'] || $_SESSION['role'] == 'admin'): ?><span style="margin: 0 8px;">|</span><a href="<?php echo getEditTopicUrl($topic_id); ?>" style="display: inline-block;">编辑</a><span style="margin: 0 8px;">|</span><a href="delete.php?type=topic&id=<?php echo $topic['id']; ?>&redirect=user.php" class="confirm-action" data-confirm-message="确定要删除这个主题吗？这将删除所有相关回复。" style="display: inline-block;">删除</a><?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <?php if ($topic_id == 1): ?>
                                    <?php
                                    // 直接包含60s的内容
                                    $sixty_seconds_path = __DIR__ . '/60s/index.php';
                                    if (file_exists($sixty_seconds_path)) {
                                        // 读取并包含60s的核心逻辑
                                        $config = include __DIR__ . '/60s/config.php';
                                        include __DIR__ . '/60s/common/common.php';
                                        
                                        // 直接生成内容
                                        ob_start();
                                        ?>
                                        <div style="padding: 10px; font-family: Arial, sans-serif; color: #333;">
                                            <!-- 保存图片按钮 -->
                                            <div style="text-align: right; margin-bottom: 10px;">
                                                <button style="background-color: #ff7f00; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 14px;" onclick="save60sImage()">保存图片</button>
                                            </div>
                                            
                                            <!-- 头部区域 -->
                                            <div style="background-color: #ff7f00; color: white; padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px;">
                                                <p style="font-size: 20px; font-weight: bold; margin: 0;"><?php echo date('Y'); ?></p>
                                                <h2 style="font-size: 32px; font-weight: bold; margin: 10px 0;"><?php echo date('n月j日'); ?></h2>
                                                <div style="font-size: 16px;">
                                                    <span><?php echo $Lunar_week; ?></span>
                                                    <span style="margin: 0 20px;"><?php echo $lunar_md; ?></span>
                                                </div>
                                            </div>
                                            
                                            <!-- 内容区域 -->
                                            <div style="background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);" id="sixty-seconds-content">
                                                <?php if ($config['day60s']) { ?>
                                                    <!-- 60秒读懂世界 -->
                                                    <div style="margin: 20px 0;">
                                                        <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 15px; color: #ff7f00;">「60秒读懂世界」</h3>
                                                        <ul style="font-size: 14px; line-height: 1.8; list-style: none; padding: 0;">
                                                            <?php
                                                            if (!empty($day60s)) {
                                                                foreach ($day60s as $item) {
                                                                    preg_match('/^\d+、(.+)；/', $item, $search);
                                                                    if (array_key_exists(1, $search)) {
                                                                        echo '<li style="margin: 8px 0; padding-left: 20px; position: relative;">' . $item . '</li>';
                                                                    } else {
                                                                        echo '<li style="margin: 8px 0; padding-left: 20px; position: relative;">' . $item . '</li>';
                                                                    }
                                                                }
                                                            } else {
                                                                echo '<li style="margin: 8px 0; padding-left: 20px; position: relative;">获取数据失败</li>';
                                                            }
                                                            ?>
                                                        </ul>
                                                    </div>
                                                <?php } ?>
                                                
                                                <?php if ($config['hot']) { ?>
                                                    <!-- 实时热搜 -->
                                                    <div style="margin: 20px 0;">
                                                        <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 15px; color: #ff7f00;">
                                                            <span>「实时热搜」</span>
                                                            <span style="float: right; font-weight: normal;"><a href="./60s/hot/" target="_blank" style="color: #ff7f00;">完整榜单&gt;</a></span>
                                                        </h3>
                                                        <?php
                                                        if (!empty($hots)) {
                                                            foreach ($hots as $item) {
                                                                echo '<h4 style="font-size: 16px; font-weight: bold; margin: 15px 0; color: #ff7f00;">
                                                                    <span>「' . $item["name"] . '」</span>
                                                                    <span style="float: right; font-weight: normal;"><a href="./60s/hot?type=' . $item["alias"] . '" target="_blank" style="color: #ff7f00;">更多></a></span>
                                                                </h4>';
                                                                $slices = array_slice($item['data'], 0, 10); //显示前10条
                                                                $i = 1;
                                                                echo '<table style="width: 100%; border-collapse: collapse; font-size: 14px;"><tbody>';
                                                                foreach ($slices as $slice) {
                                                                    echo '<tr style="border-bottom: 1px solid #f0f0f0;">
                                                                        <td style="width: 40px; text-align: center; padding: 8px; font-weight: bold;">' . $i . '.</td>
                                                                        <td style="padding: 8px;"><a href="' . $slice['url'] . '" target="_blank" rel="nofollow" style="color: #333;">' . $slice['title'] . '</a></td>
                                                                        <td style="width: 100px; text-align: right; padding: 8px; color: #999;">' . formatNumber($slice['hotScore']) . '</td>
                                                                    </tr>';
                                                                    $i++;
                                                                }
                                                                echo '</tbody></table>';
                                                            }
                                                        } else {
                                                            echo '<p style="margin: 10px 0;">获取数据失败</p>';
                                                        }
                                                        ?>
                                                    </div>
                                                <?php } ?>
                                                
                                                <?php if ($config['history']) { ?>
                                                    <!-- 历史上的今天 -->
                                                    <div style="margin: 20px 0;">
                                                        <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 15px; color: #ff7f00;">「历史上的今天」</h3>
                                                        <div style="font-size: 14px; line-height: 1.8;">
                                                            <?php
                                                            foreach ($history_today as $item) {
                                                                echo '<div style="margin: 8px 0;">
                                                                    <span style="display: inline-block; width: 60px; font-style: italic; color: #999;">' . $item['year'] . '</span>
                                                                    <span style="margin: 0 5px;">·</span>
                                                                    <span><a href="' . $item['link'] . '" target="_blank" title="' . $item['desc'] . '" style="color: #333;">' . $item['title'] . '</a></span>
                                                                </div>';
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                                
                                                <?php if ($config['lunar']) { ?>
                                                    <!-- 今日黄历 -->
                                                    <div style="margin: 20px 0;">
                                                        <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 15px; color: #ff7f00;">「今日黄历」</h3>
                                                        <div style="display: flex; font-size: 14px;">
                                                            <!-- 左侧农历日期（纵向显示） -->
                                                            <div style="width: 80px; margin-right: 20px;">
                                                                <div style="font-size: 24px; font-weight: bold; color: #333; line-height: 1.2; text-align: center;">
                                                                    <?php
                                                                    // 纵向显示农历日期
                                                                    $lunar_text = $lunar_md;
                                                                    // 移除年月日等字符，只保留数字和月份
                                                                    $lunar_text = str_replace(['年', '', '日'], '', $lunar_text);
                                                                    // 处理字符编码问题
                                                                    $lunar_array = preg_split('//u', $lunar_text, -1, PREG_SPLIT_NO_EMPTY);
                                                                    foreach ($lunar_array as $char) {
                                                                        echo '<div>' . $char . '</div>';
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- 中间信息（每行一条） -->
                                                            <div style="flex: 1; display: flex; flex-direction: column;">
                                                                <!-- 第一行 -->
                                                                <div style="margin: 5px 0;">
                                                                    <span style="color: #ff7f00;"><?php echo $Lunar->getYearGan() . $Lunar->getYearZhi() . $Lunar->getYearShengXiao(); ?>年</span>
                                                                    <span style="color: #ff7f00;"><?php echo $Lunar->getMonthInGanZhi(); ?></span>
                                                                    <span style="color: #ff7f00;"><?php echo $Lunar->getDayInGanZhi(); ?></span>
                                                                    <span style="color: #ff7f00;">星期<?php echo $Lunar->getSolar()->getWeekInChinese(); ?></span>
                                                                </div>
                                                                
                                                                <!-- 第五行 -->
                                                                <div style="margin: 5px 0;">
                                                                    <span style="font-weight: bold; color: #ff7f00;">五行：</span>
                                                                    <span><?php echo $lunar_nayin; ?></span>
                                                                </div>
                                                                
                                                                <!-- 第六行 -->
                                                                <div style="margin: 5px 0;">
                                                                    <span style="font-weight: bold; color: #ff7f00;">冲煞：</span>
                                                                    <span><?php echo $lunar_chongsha; ?></span>
                                                                </div>
                                                                
                                                                <!-- 第七行 -->
                                                                <div style="margin: 5px 0;">
                                                                    <span style="font-weight: bold; color: #ff7f00;">彭祖：</span>
                                                                    <span><?php echo $lunar_pengzu; ?></span>
                                                                </div>
                                                                
                                                                <!-- 第八行 -->
                                                                <div style="margin: 5px 0;">
                                                                    <span style="font-weight: bold; color: #ff7f00;">喜神：</span>
                                                                    <span><?php echo $lunar_xishen; ?></span>
                                                                </div>
                                                                
                                                                <!-- 第九行 -->
                                                                <div style="margin: 5px 0;">
                                                                    <span style="font-weight: bold; color: #ff7f00;">福神：</span>
                                                                    <span><?php echo $lunar_fushen; ?></span>
                                                                </div>
                                                                
                                                                <!-- 第十行 -->
                                                                <div style="margin: 5px 0;">
                                                                    <span style="font-weight: bold; color: #ff7f00;">财神：</span>
                                                                    <span><?php echo $lunar_caishen; ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- 宜忌 -->
                                                        <div style="margin: 15px 0;">
                                                            <div style="margin: 10px 0;">
                                                                <span style="font-weight: bold; color: #4CAF50;">宜：</span>
                                                                <span><?php echo implode(' ', $lunar_yi); ?></span>
                                                            </div>
                                                            <div style="margin: 10px 0;">
                                                                <span style="font-weight: bold; color: #f44336;">忌：</span>
                                                                <span><?php echo implode(' ', $lunar_ji); ?></span>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- 吉神凶神 -->
                                                        <div style="margin: 15px 0;">
                                                            <div style="margin: 5px 0;">
                                                                <span style="font-weight: bold; color: #4CAF50;">吉神：</span>
                                                                <span><?php echo implode(' ', $lunar_jshen); ?></span>
                                                            </div>
                                                            <div style="margin: 5px 0;">
                                                                <span style="font-weight: bold; color: #f44336;">凶神：</span>
                                                                <span><?php echo implode(' ', $lunar_xshen); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                                
                                                <?php if ($config['yan']) { ?>
                                                    <div style="margin: 20px 0;">
                                                        <h3 style="font-size: 18px; font-weight: bold; margin-bottom: 15px; color: #ff7f00;">「每日一语」</h3>
                                                        <p style="font-size: 14px; line-height: 1.8; padding: 15px; background-color: #f9f9f9; border-left: 4px solid #ff7f00;"><?php echo yan(); ?></p>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                        
                                        <script>
                                            function save60sImage() {
                                                // 检查html2canvas是否加载
                                                if (typeof html2canvas === 'undefined') {
                                                    // 动态加载html2canvas
                                                    var script = document.createElement('script');
                                                    script.src = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
                                                    script.onload = function() {
                                                        generateImage();
                                                    };
                                                    document.head.appendChild(script);
                                                } else {
                                                    generateImage();
                                                }
                                            }
                                            
                                            function generateImage() {
                                                var element = document.getElementById('sixty-seconds-content');
                                                html2canvas(element, {
                                                    scale: 2,
                                                    useCORS: true,
                                                    backgroundColor: '#ffffff'
                                                }).then(function(canvas) {
                                                    var imgUrl = canvas.toDataURL('image/png');
                                                    var link = document.createElement('a');
                                                    link.href = imgUrl;
                                                    link.download = '60s_' + new Date().toISOString().slice(0,10) + '.png';
                                                    link.click();
                                                });
                                            }
                                        </script>
                                        <?php
                                        $content = ob_get_clean();
                                        echo $content;
                                    } else {
                                        echo '<div>60s内容未找到</div>';
                                    }
                                    ?>
                                <?php else: ?>
                                    <div><?php echo formatContent($topic['content']); ?></div>
                                <?php endif; ?>
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #f0f0f0;">
                                        <button onclick="likeTopic(<?php echo $topic['id']; ?>)" style="padding: 5px 10px; margin-right: 10px; background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 3px; cursor: pointer;">
                                            <span id="topic-like-count-<?php echo $topic['id']; ?>"><?php echo getTopicLikeCount($topic['id']); ?></span> 点赞
                                        </button>
                                        <button onclick="bookmarkTopic(<?php echo $topic['id']; ?>)" style="padding: 5px 10px; margin-right: 10px; background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 3px; cursor: pointer;">
                                            收藏
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #f0f0f0; color: #999;">
                                        登录后可以点赞和收藏
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        <?php endif; ?>
        
        <?php if (count($posts) > 0): ?>
            <?php foreach ($posts as $index => $post): ?>
                <tr>
                    <td colspan="2">
                        <table border="1" width="100%" cellspacing="0" cellpadding="5" id="post-<?php echo $post['id']; ?>">
                            <tr>
                                <td width="100%">
                                    <a href="<?php echo getUserProfileUrl($post['user_id']); ?>"><strong><?php echo htmlspecialchars($post['username']); ?></strong></a>
                                    <?php if ($post['role'] === 'admin'): ?>
                                        <span style="color: red;">(管理员)</span>
                                    <?php elseif ($post['role'] === 'moderator'): ?>
                                        <span style="color: blue;">(版主)</span>
                                    <?php endif; ?>
                                    <?php $user_report_flag = getReportFlag('user', $post['user_id']); ?>
                                    <?php if ($user_report_flag === 'yellow'): ?>
                                        <span style="font-weight: bold; color: white; background-color: orange; padding: 2px 5px; border-radius: 3px;">[警告]</span>
                                    <?php elseif ($user_report_flag === 'red'): ?>
                                        <span style="font-weight: bold; color: white; background-color: red; padding: 2px 5px; border-radius: 3px;">[严重警告]</span>
                                    <?php elseif ($user_report_flag === 'ban'): ?>
                                        <span style="font-weight: bold; color: white; background-color: darkred; padding: 2px 5px; border-radius: 3px;">[已封禁]</span>
                                    <?php endif; ?>
                                    <span><?php echo formatDateTime($post['created_at']); ?></span>
                                    <?php if (isset($post['ip_info'])): ?>
                                        <?php $ip_info = json_decode($post['ip_info'], true); ?>
                                        <?php if (isset($ip_info['prov']) && isset($ip_info['city'])): ?>
                                            <span style="margin-left: 10px; color: #999; font-size: 12px;">[<?php echo htmlspecialchars($ip_info['prov'] . ' ' . $ip_info['city']); ?>]</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td align="right" style="white-space: nowrap; width: 1%;">
                                    <?php if (isset($_SESSION['user_id'])): ?>
                                        <a href="javascript:void(0);" class="reply-btn" data-post-id="<?php echo $post['id']; ?>" data-username="<?php echo htmlspecialchars($post['username']); ?>" style="display: inline-block;">回复</a><span style="margin: 0 8px;">|</span><a href="javascript:void(0);" onclick="openReportModal('post', <?php echo $post['id']; ?>, '回复')" style="display: inline-block;">举报</a><?php if ($_SESSION['user_id'] == $topic['user_id'] || $_SESSION['user_id'] == $post['user_id'] || $_SESSION['role'] == 'admin'): ?><span style="margin: 0 8px;">|</span><a href="delete.php?type=post&id=<?php echo $post['id']; ?>&redirect=user.php" class="confirm-action" data-confirm-message="确定要删除这个回复吗？" style="display: inline-block;">删除</a><?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <?php if ($post['reply_to']): ?>
                                        <table width="100%" cellspacing="0" cellpadding="10" class="quote-table" style="margin-left: 20px; margin-bottom: 10px; border-left: 3px solid #0066cc; border: 1px solid #999999; box-sizing: border-box; max-width: calc(100% - 20px);">
                                            <tr style="border: none;">
                                                <td style="border: none;">
                                                    <?php
                                                    $reply_to_post = null;
                                                    if ($storage_type === 'json') {
                                                        // 先尝试从posts表中获取（回复）
                                                        $reply_to_post = $db->findById('posts', $post['reply_to']);
                                                        if ($reply_to_post) {
                                                            $reply_user = $db->findById('users', $reply_to_post['user_id']);
                                                            $reply_to_post['username'] = $reply_user ? $reply_user['username'] : '未知用户';
                                                        } else {
                                                            // 再尝试从topics表中获取（主题）
                                                            $reply_to_topic = $db->findById('topics', $post['reply_to']);
                                                            if ($reply_to_topic) {
                                                                $reply_user = $db->findById('users', $reply_to_topic['user_id']);
                                                                $reply_to_post = [
                                                                    'content' => $reply_to_topic['content'],
                                                                    'created_at' => $reply_to_topic['created_at'],
                                                                    'username' => $reply_user ? $reply_user['username'] : '未知用户'
                                                                ];
                                                            }
                                                        }
                                                    } else {
                                                        // 先尝试从posts表中获取（回复）
                                                        $reply_to_post = $db->fetch(
                                                            "SELECT p.content, p.created_at, u.username FROM `{$prefix}posts` p 
                                                            JOIN `{$prefix}users` u ON p.user_id = u.id 
                                                            WHERE p.id = :id",
                                                            ['id' => $post['reply_to']]
                                                        );
                                                        if (!$reply_to_post) {
                                                            // 再尝试从topics表中获取（主题）
                                                            $reply_to_post = $db->fetch(
                                                                "SELECT t.content, t.created_at, u.username FROM `{$prefix}topics` t 
                                                                JOIN `{$prefix}users` u ON t.user_id = u.id 
                                                                WHERE t.id = :id",
                                                                ['id' => $post['reply_to']]
                                                            );
                                                        }
                                                    }
                                                    
                                                    if ($reply_to_post):
                                                        // 计算被引用回复所在的页码
                                                        $reply_page = 1;
                                                        if ($storage_type === 'json') {
                                                            // 检查被引用的是否是主题
                                                            $is_topic = false;
                                                            $reply_to_topic = $db->findById('topics', $post['reply_to']);
                                                            if ($reply_to_topic) {
                                                                $is_topic = true;
                                                            }
                                                            
                                                            if (!$is_topic) {
                                                                // JSON存储：获取所有回复并计算页码
                                                                $all_posts = $db->select('posts', ['topic_id' => $topic_id, 'status' => 'published'], 'created_at ASC');
                                                                $reply_index = 0;
                                                                foreach ($all_posts as $index => $p) {
                                                                    if ($p['id'] == $post['reply_to']) {
                                                                        $reply_index = $index + 1; // 从1开始计数
                                                                        break;
                                                                    }
                                                                }
                                                                if ($reply_index > 0) {
                                                                    $reply_page = ceil(($reply_index + 1) / $posts_per_page); // +1 因为主题内容也算一页
                                                                }
                                                            }
                                                        } else {
                                                            // 检查被引用的是否是主题
                                                            $is_topic = false;
                                                            $reply_to_topic = $db->fetch(
                                                                "SELECT id FROM `{$prefix}topics` WHERE id = :id",
                                                                ['id' => $post['reply_to']]
                                                            );
                                                            if ($reply_to_topic) {
                                                                $is_topic = true;
                                                            }
                                                            
                                                            if (!$is_topic) {
                                                                // MySQL存储：使用SQL查询计算页码
                                                                $reply_count = $db->fetchColumn(
                                                                    "SELECT COUNT(*) FROM `{$prefix}posts` 
                                                                    WHERE topic_id = :topic_id AND status = 'published' AND created_at <= (
                                                                        SELECT created_at FROM `{$prefix}posts` WHERE id = :reply_id
                                                                    )",
                                                                    ['topic_id' => $topic_id, 'reply_id' => $post['reply_to']]
                                                                );
                                                                if ($reply_count > 0) {
                                                                    $reply_page = ceil(($reply_count + 1) / $posts_per_page); // +1 因为主题内容也算一页
                                                                }
                                                            }
                                                        }
                                                    ?>
                                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                                            <div>
                                                                <strong style="color: #0066cc;">回复 <?php echo htmlspecialchars($reply_to_post['username']); ?>:</strong>
                                                                <span style="font-size: 12px; color: #999; margin-left: 10px;"><?php echo isset($reply_to_post['created_at']) ? formatDateTime($reply_to_post['created_at']) : ''; ?></span>
                                                            </div>
                                                            <a href="<?php echo getTopicUrl($topic_id, $reply_page, $topic['title']); ?>#post-<?php echo $post['reply_to']; ?>" style="font-size: 12px; color: #0066cc; text-decoration: none;">定位</a>
                                                        </div>
                                                        <p style="margin: 5px 0; font-size: 14px; line-height: 1.5;"><?php echo mb_substr(strip_tags($reply_to_post['content']), 0, 100) . (mb_strlen(strip_tags($reply_to_post['content'])) > 100 ? '...' : ''); ?></p>
                                                    <?php else: ?>
                                                        <div style="color: #999; font-style: italic;">引用的内容已被删除</div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        </table>
                                    <?php endif; ?>
                                    
                                    <div><?php echo formatContent($post['content']); ?></div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if ($total_pages > 1): ?>
            <tr>
                <td colspan="2" align="center">
                    <?php 
                        $pagination_url = getPaginationUrlPattern('topic.php', ['id' => $topic_id, 'sort' => $sort]);
                        echo generatePagination($page, $total_pages, $pagination_url); 
                    ?>
                </td>
            </tr>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['user_id']) && !$topic['is_locked'] && (!isset($_SESSION['status']) || $_SESSION['status'] !== 'restricted')): ?>
            <tr>
                <td colspan="2">
                    <table border="1" width="100%" cellspacing="0" cellpadding="5">
                        <tr>
                            <td><h5>发表回复</h5></td>
                        </tr>
                        <tr>
                            <td>
                                <?php if (!empty($error)): ?>
                            <div class="error"><?php echo $error; ?></div>
                        <?php endif; ?>
                                
                                <?php if (!empty($success)): ?>
                                    <div class="success"><?php echo $success; ?></div>
                                <?php endif; ?>
                                
                                <form method="post" action="<?php echo getTopicUrl($topic_id); ?>" id="reply-form">
                                    <input type="hidden" name="reply_to" id="reply_to" value="">
                                    
                                    <div id="reply-to-info" style="display: none;">
                回复给: <span id="reply-username"></span>
                <a href="javascript:void(0);" id="cancel-reply">取消</a>
            </div>
                                    
                                    <div>
                                        <label for="content">回复内容</label><br>
                                        <textarea id="content" name="content" rows="5" required style="width: 100%;"></textarea>
                                    </div>
                                    
                                    <div style="margin-top: 10px;">
                                        <button type="submit" onclick="return validateReplyForm()" style="background-color: #f0f0f0; color: #333; border: 1px solid #ddd; padding: 6px 12px; font-size: 14px; border-radius: 2px;">提交回复</button>
                                    </div>
                                    
                                    <script>
                                    function validateReplyForm() {
                                        // 同步编辑器内容
                                        if (contentEditor) {
                                            contentEditor.setTextarea();
                                        }
                                        
                                        // 验证回复内容
                                        var content = document.getElementById('content');
                                        if (!content.value.trim()) {
                                            alert('请填写回复内容');
                                            content.focus();
                                            return false;
                                        }
                                        
                                        return true;
                                    }
                                    </script>
                                </form>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        <?php elseif (isset($_SESSION['user_id']) && isset($_SESSION['status']) && $_SESSION['status'] === 'restricted'): ?>
            <tr>
                <td colspan="2">
                    <div style="background-color: #ffebee; color: #c62828; padding: 10px; border: 1px solid #ef9a9a; border-radius: 3px;">
                        您的账号已被限制，无法回复主题。您可以 <a href="javascript:void(0);" onclick="openAppealModal('user', <?php echo $_SESSION['user_id']; ?>, '<?php echo addslashes($_SESSION['username']); ?>')">提交申诉</a> 以恢复正常权限。
                    </div>
                </td>
            </tr>
        <?php elseif ($topic['is_locked']): ?>
            <tr>
                <td colspan="2">
                    <div>该主题已被锁定，无法回复</div>
                </td>
            </tr>
        <?php else: ?>
            <tr>
                <td colspan="2">
                    <div><a href="<?php echo getLoginUrl(); ?>">登录</a> 后才能回复</div>
                </td>
            </tr>
        <?php endif; ?>
    <?php endif; ?>
</table>
<?php if (isset($_SESSION['user_id'])): ?>
<!-- ice -->
<script type="text/JavaScript" src="./assets/src/iceEditor.js?v=3"></script>
<!-- 编辑器脚本 -->
<script>
// 存储编辑器实例
var contentEditor = null;

// 同步编辑器内容到textarea
function syncEditorContent() {
    if (contentEditor) {
        // 使用编辑器内部的setTextarea方法确保获取最新内容
        contentEditor.setTextarea();
    }
    return true;
}

// 初始化编辑器
function initEditor() {
    // 检查content元素是否存在
    if (document.getElementById('content')) {
        //自定义编辑器菜单
        ice.editor("content",function(e){
            // 存储编辑器实例
            contentEditor = this;
            this.uploadUrl = "assets/src/upload/php-upload.php";
            this.pasteText = false;
            this.screenshot = true;
            this.screenshotUpload = true;
            this.height='150px'; //高度
            this.menu = [
                    'backColor', 'fontSize', 'foreColor', 'bold', 'italic', 'underline', 'strikeThrough', 'line', 'justifyLeft',
                    'justifyCenter', 'justifyRight', 'indent', 'outdent', 'line', 'insertOrderedList', 'insertUnorderedList', 'line', 'superscript',
                    'subscript', 'createLink', 'unlink', 'line', 'hr', 'face', 'table', 'files', 'music', 'video', 'insertImage',
                    'removeFormat', 'paste', 'line', 'code'
                ];
            // 注意：不要在这里调用 this.create()，它已经在 ice.editor 内部自动调用了
        });
    }
}

// 确保DOM加载完成后再初始化编辑器
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEditor);
} else {
    initEditor();
}
</script>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 回复功能
    const replyButtons = document.querySelectorAll('.reply-btn');
    const replyForm = document.getElementById('reply-form');
    const replyToInput = document.getElementById('reply_to');
    const replyToInfo = document.getElementById('reply-to-info');
    const replyUsername = document.getElementById('reply-username');
    const cancelReply = document.getElementById('cancel-reply');
    
    // 处理主题回复按钮
    const replyTopicButtons = document.querySelectorAll('.reply-topic-btn');
    replyTopicButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // 清除引用信息
            replyToInput.value = '';
            replyToInfo.style.display = 'none';
            
            // 滚动到回复表单
            replyForm.scrollIntoView({ behavior: 'smooth' });
            
            // 聚焦到文本框
            document.getElementById('content').focus();
        });
    });
    
    // 处理普通回复按钮
    const replyPostButtons = document.querySelectorAll('.reply-btn:not(.reply-topic-btn)');
    replyPostButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const postId = this.getAttribute('data-post-id');
            const username = this.getAttribute('data-username');
            
            replyToInput.value = postId;
            replyUsername.textContent = username;
            replyToInfo.style.display = 'block';
            
            // 滚动到回复表单
            replyForm.scrollIntoView({ behavior: 'smooth' });
            
            // 聚焦到文本框
            document.getElementById('content').focus();
        });
    });
    
    if (cancelReply) {
        cancelReply.addEventListener('click', function(e) {
            e.preventDefault();
            replyToInput.value = '';
            replyToInfo.style.display = 'none';
        });
    }
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 删除确认对话框
    const confirmButtons = document.querySelectorAll('.confirm-action');
    confirmButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const confirmMessage = this.getAttribute('data-confirm-message') || '确定要执行此操作吗？';
            
            if (confirm(confirmMessage)) {
                window.location.href = this.href;
            }
        });
    });
});
</script>
<!-- 举报弹窗 -->
<div id="reportModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: white; padding: 20px; border-radius: 5px; width: 400px; max-width: 90%;">
        <h3 style="margin-top: 0; margin-bottom: 20px;">举报</h3>
        <form id="reportForm" method="post" action="report.php">
            <input type="hidden" name="report_type" id="reportType">
            <input type="hidden" name="target_id" id="targetId">
            <div style="margin-bottom: 15px;">
                <label for="reportReason" style="display: block; margin-bottom: 5px;">举报原因：</label>
                <textarea id="reportReason" name="reason" rows="4" style="width: 100%; padding: 5px; resize: vertical;" required></textarea>
            </div>
            <div style="text-align: right;">
                <button type="button" onclick="closeReportModal()" style="padding: 5px 15px; margin-right: 10px; background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 3px; cursor: pointer;">取消</button>
                <button type="submit" style="padding: 5px 15px; background-color: #4A90E2; color: white; border: none; border-radius: 3px; cursor: pointer;">提交举报</button>
            </div>
        </form>
    </div>
</div>

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

<script>
// 打开举报弹窗
function openReportModal(type, id, name) {
    document.getElementById('reportType').value = type;
    document.getElementById('targetId').value = id;
    document.getElementById('reportModal').style.display = 'block';
}

// 关闭举报弹窗
function closeReportModal() {
    document.getElementById('reportModal').style.display = 'none';
    document.getElementById('reportForm').reset();
}

// 处理举报表单提交
document.getElementById('reportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('report.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('举报成功，我们会尽快处理');
            closeReportModal();
        } else {
            alert('举报失败：' + data.message);
        }
    })
    .catch(error => {
        alert('提交失败：' + error.message);
    });
});

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
// 点赞主题
function likeTopic(topicId) {
    fetch('like.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'topic_id=' + topicId + '&action=like'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('topic-like-count-' + topicId).textContent = data.count;
            alert('点赞成功');
        } else {
            alert('操作失败：' + data.message);
        }
    })
    .catch(error => {
        alert('操作失败：' + error.message);
    });
}

// 收藏主题
function bookmarkTopic(topicId) {
    fetch('bookmark.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'topic_id=' + topicId + '&action=bookmark'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('收藏成功');
        } else {
            alert('操作失败：' + data.message);
        }
    })
    .catch(error => {
        alert('操作失败：' + error.message);
    });
}
</script>
<?php
// 加载页面底部
include __DIR__ . '/templates/footer.php';
?>
