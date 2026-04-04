<?php
    date_default_timezone_set('Asia/Shanghai');
/**
 * 搜索页面
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
require_once __DIR__ . '/includes/content_functions.php';

// 检查安装状态和闭站模式
checkInstall();

// 获取搜索参数
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$tag = isset($_GET['tag']) ? trim($_GET['tag']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : 'topics';
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// 获取页码
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

// 每页显示的结果数
$items_per_page = 20;

// 搜索结果
$results = [];
$total_items = 0;
$total_pages = 1;

// 获取分类列表
try {
    $db = getDB();
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
    
    // 获取所有分类
    $categories = $db->fetchAll("SELECT * FROM `{$prefix}categories` ORDER BY `sort_order` ASC");
    
    // 执行搜索
    if (!empty($keyword) || !empty($tag)) {
        // 记录搜索日志
        logAction('用户搜索', 'search', 0, [
            'keyword' => $keyword,
            'tag' => $tag,
            'type' => $type,
            'category_id' => $category_id,
            'search_time' => date('Y-m-d H:i:s'),
            'search_ip' => getClientIp()
        ]);
        
        // 计算偏移量
        $offset = ($page - 1) * $items_per_page;
        
        if (!empty($tag)) {
            // 标签搜索
            $topic_ids = getTopicIdsByTag($tag);
            
            if (!empty($topic_ids)) {
                if ($storage_type === 'json') {
                    // JSON存储：使用简单查询和PHP过滤
                    $all_topics = $db->select('topics', ['status' => 'published']);
                    
                    // 过滤主题
                    $filtered_topics = [];
                    foreach ($all_topics as $topic) {
                        if (in_array($topic['id'], $topic_ids)) {
                            if ($category_id > 0 && $topic['category_id'] != $category_id) {
                                continue;
                            }
                            $filtered_topics[] = $topic;
                        }
                    }
                    
                    $total_items = count($filtered_topics);
                    
                    // 分页
                    $paged_topics = array_slice($filtered_topics, $offset, $items_per_page);
                    
                    // 获取用户和分类信息
                    $users = [];
                    $all_users = $db->select('users');
                    foreach ($all_users as $u) {
                        $users[$u['id']] = $u;
                    }
                    
                    $cats = [];
                    $all_cats = $db->select('categories');
                    foreach ($all_cats as $c) {
                        $cats[$c['id']] = $c;
                    }
                    
                    // 获取回复数
                    $all_posts = $db->select('posts', ['status' => 'published']);
                    $post_counts = [];
                    foreach ($all_posts as $p) {
                        if (!isset($post_counts[$p['topic_id']])) {
                            $post_counts[$p['topic_id']] = 0;
                        }
                        $post_counts[$p['topic_id']]++;
                    }
                    
                    // 关联数据
                    $results = [];
                    foreach ($paged_topics as $topic) {
                        $topic['username'] = isset($users[$topic['user_id']]) ? $users[$topic['user_id']]['username'] : '未知用户';
                        $topic['category_title'] = isset($cats[$topic['category_id']]) ? $cats[$topic['category_id']]['title'] : '未知分类';
                        $topic['category_id'] = $topic['category_id'];
                        $topic['reply_count'] = isset($post_counts[$topic['id']]) ? $post_counts[$topic['id']] : 0;
                        $results[] = $topic;
                    }
                } else {
                    // MySQL存储：使用复杂SQL查询
                    // 准备搜索条件
                    $topic_ids_str = implode(',', $topic_ids);
                    $params = [];
                    
                    $category_condition = '';
                    if ($category_id > 0) {
                        $category_condition = 'AND t.category_id = :category_id';
                        $params['category_id'] = $category_id;
                    }
                    
                    // 搜索主题
                    $total_items = $db->fetchColumn(
                        "SELECT COUNT(*) FROM `{$prefix}topics` t 
                        WHERE t.id IN ($topic_ids_str) 
                        AND t.status = 'published' $category_condition",
                        $params
                    );
                    
                    $results = $db->fetchAll(
                        "SELECT t.*, u.username, c.title as category_title, c.id as category_id,
                        (SELECT COUNT(*) FROM `{$prefix}posts` WHERE `topic_id` = t.id AND `status` = 'published') as reply_count
                        FROM `{$prefix}topics` t 
                        JOIN `{$prefix}users` u ON t.user_id = u.id 
                        JOIN `{$prefix}categories` c ON t.category_id = c.id 
                        WHERE t.id IN ($topic_ids_str) 
                        AND t.status = 'published' $category_condition
                        ORDER BY t.created_at DESC 
                        LIMIT :offset, :limit",
                        array_merge($params, ['offset' => $offset, 'limit' => $items_per_page])
                    );
                }
            } else {
                $total_items = 0;
                $results = [];
            }
        } else {
            // 关键词搜索
            if ($storage_type === 'json') {
                // JSON存储：使用简单查询和PHP过滤
                if ($type === 'topics') {
                    // 搜索主题
                    $all_topics = $db->select('topics', ['status' => 'published']);
                    
                    // 过滤关键词
                    $filtered_topics = [];
                    foreach ($all_topics as $topic) {
                        if (stripos($topic['title'], $keyword) !== false || stripos($topic['content'], $keyword) !== false) {
                            if ($category_id > 0 && $topic['category_id'] != $category_id) {
                                continue;
                            }
                            $filtered_topics[] = $topic;
                        }
                    }
                    
                    $total_items = count($filtered_topics);
                    
                    // 分页
                    $paged_topics = array_slice($filtered_topics, $offset, $items_per_page);
                    
                    // 获取用户和分类信息
                    $users = [];
                    $all_users = $db->select('users');
                    foreach ($all_users as $u) {
                        $users[$u['id']] = $u;
                    }
                    
                    $cats = [];
                    $all_cats = $db->select('categories');
                    foreach ($all_cats as $c) {
                        $cats[$c['id']] = $c;
                    }
                    
                    // 获取回复数
                    $all_posts = $db->select('posts', ['status' => 'published']);
                    $post_counts = [];
                    foreach ($all_posts as $p) {
                        if (!isset($post_counts[$p['topic_id']])) {
                            $post_counts[$p['topic_id']] = 0;
                        }
                        $post_counts[$p['topic_id']]++;
                    }
                    
                    // 关联数据
                    $results = [];
                    foreach ($paged_topics as $topic) {
                        $topic['username'] = isset($users[$topic['user_id']]) ? $users[$topic['user_id']]['username'] : '未知用户';
                        $topic['category_title'] = isset($cats[$topic['category_id']]) ? $cats[$topic['category_id']]['title'] : '未知分类';
                        $topic['category_id'] = $topic['category_id'];
                        $topic['reply_count'] = isset($post_counts[$topic['id']]) ? $post_counts[$topic['id']] : 0;
                        $results[] = $topic;
                    }
                } else {
                    // 搜索回复
                    $all_posts = $db->select('posts', ['status' => 'published']);
                    
                    // 过滤关键词
                    $filtered_posts = [];
                    foreach ($all_posts as $post) {
                        if (stripos($post['content'], $keyword) !== false) {
                            $filtered_posts[] = $post;
                        }
                    }
                    
                    $total_items = count($filtered_posts);
                    
                    // 分页
                    $paged_posts = array_slice($filtered_posts, $offset, $items_per_page);
                    
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
                    
                    $cats = [];
                    $all_cats = $db->select('categories');
                    foreach ($all_cats as $c) {
                        $cats[$c['id']] = $c;
                    }
                    
                    // 关联数据
                    $results = [];
                    foreach ($paged_posts as $post) {
                        $post['username'] = isset($users[$post['user_id']]) ? $users[$post['user_id']]['username'] : '未知用户';
                        $post['topic_title'] = isset($topics[$post['topic_id']]) ? $topics[$post['topic_id']]['title'] : '未知主题';
                        $post['topic_id'] = $post['topic_id'];
                        
                        $topic_cat_id = isset($topics[$post['topic_id']]) ? $topics[$post['topic_id']]['category_id'] : 0;
                        $post['category_title'] = isset($cats[$topic_cat_id]) ? $cats[$topic_cat_id]['title'] : '未知分类';
                        $post['category_id'] = $topic_cat_id;
                        
                        $results[] = $post;
                    }
                }
            } else {
                // MySQL存储：使用复杂SQL查询
                // 准备搜索条件
                $search_keyword = '%' . $keyword . '%';
                $params = ['keyword1' => $search_keyword, 'keyword2' => $search_keyword];
                
                $category_condition = '';
                if ($category_id > 0) {
                    $category_condition = 'AND t.category_id = :category_id';
                    $params['category_id'] = $category_id;
                }
                
                if ($type === 'topics') {
                    // 搜索主题
                    $total_items = $db->fetchColumn(
                        "SELECT COUNT(*) FROM `{$prefix}topics` t 
                        WHERE (t.title LIKE :keyword1 OR t.content LIKE :keyword2) 
                        AND t.status = 'published' $category_condition",
                        $params
                    );
                    
                    $results = $db->fetchAll(
                        "SELECT t.*, u.username, c.title as category_title, c.id as category_id,
                        (SELECT COUNT(*) FROM `{$prefix}posts` WHERE `topic_id` = t.id AND `status` = 'published') as reply_count
                        FROM `{$prefix}topics` t 
                        JOIN `{$prefix}users` u ON t.user_id = u.id 
                        JOIN `{$prefix}categories` c ON t.category_id = c.id 
                        WHERE (t.title LIKE :keyword1 OR t.content LIKE :keyword2) 
                        AND t.status = 'published' $category_condition
                        ORDER BY t.created_at DESC 
                        LIMIT :offset, :limit",
                        array_merge($params, ['offset' => $offset, 'limit' => $items_per_page])
                    );
                } else {
                    // 搜索回复
                    $total_items = $db->fetchColumn(
                        "SELECT COUNT(*) FROM `{$prefix}posts` p 
                        JOIN `{$prefix}topics` t ON p.topic_id = t.id 
                        WHERE p.content LIKE :keyword1 
                        AND p.status = 'published' $category_condition",
                        $params
                    );
                    
                    $results = $db->fetchAll(
                        "SELECT p.*, t.title as topic_title, t.id as topic_id, u.username, 
                        c.title as category_title, c.id as category_id
                        FROM `{$prefix}posts` p 
                        JOIN `{$prefix}topics` t ON p.topic_id = t.id 
                        JOIN `{$prefix}users` u ON p.user_id = u.id 
                        JOIN `{$prefix}categories` c ON t.category_id = c.id 
                        WHERE p.content LIKE :keyword1 
                        AND p.status = 'published' $category_condition
                        ORDER BY p.created_at DESC 
                        LIMIT :offset, :limit",
                        array_merge($params, ['offset' => $offset, 'limit' => $items_per_page])
                    );
                }
            }
        }
        
        // 计算总页数
        $total_pages = ceil($total_items / $items_per_page);
    }
    
} catch (Exception $e) {
    $error = '搜索失败: ' . $e->getMessage();
}

// 设置页面标题
$page_title = '搜索' . (!empty($keyword) ? ': ' . $keyword : '');

// 加载页面头部
include __DIR__ . '/templates/header.php';
?>

<table border="1" width="100%" cellspacing="0" cellpadding="5">
    <!-- 搜索标题 -->
    <tr>
        <td colspan="2" style="font-weight: bold; font-size: 16px;">
            搜索
        </td>
    </tr>
    
    <!-- 搜索表单 -->
    <tr>
        <td colspan="2">
            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                <form method="get" action="<?php echo getSearchUrl(); ?>">
                    <tr>
                        <td width="20%" style="padding: 10px;">
                            <strong>关键词</strong>
                        </td>
                        <td width="40%" style="padding: 10px;">
                            <input type="text" name="keyword" value="<?php echo htmlspecialchars($keyword); ?>" required style="width: 100%; padding: 4px;">
                        </td>
                        <td width="20%" style="padding: 10px;">
                            <strong>分类</strong>
                        </td>
                        <td width="20%" style="padding: 10px;">
                            <select name="category_id" style="width: 100%; padding: 4px;">
                                <option value="0">所有分类</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" align="center" style="padding: 10px;">
                            <button type="submit" style="padding: 6px 12px; background-color: #f0f0f0; border: 1px solid #ddd; text-decoration: none; border-radius: 2px; cursor: pointer;">搜索</button>
                        </td>
                    </tr>
                </form>
            </table>
        </td>
    </tr>
    
    <!-- 搜索结果 -->
    <?php if (isset($error)): ?>
        <tr>
            <td colspan="2" class="error"><?php echo $error; ?></td>
        </tr>
    <?php elseif (!empty($keyword) || !empty($tag)): ?>
        <tr>
            <td colspan="2" style="padding: 10px;">
                <table border="1" width="100%" cellspacing="0" cellpadding="5">
                    <tr>
                        <td colspan="2">
                            <strong>搜索结果: <?php echo !empty($tag) ? '标签: ' . htmlspecialchars($tag) : htmlspecialchars($keyword); ?></strong>
                            <br>
                            <small>共找到 <?php echo $total_items; ?> 条结果</small>
                        </td>
                    </tr>
                    
                    <?php if (count($results) > 0): ?>
                        <?php if ($type === 'topics'): ?>
                            <?php foreach ($results as $topic): ?>
                                <tr>
                                    <td colspan="2" style="padding: 10px;">
                                        <?php $report_flag = getReportFlag('topic', $topic['id']); ?>
                                        <?php if ($report_flag === 'yellow'): ?>
                                            <span style="font-weight: bold; color: white; background-color: orange; padding: 2px 5px; border-radius: 3px;">[警告] </span>
                                        <?php elseif ($report_flag === 'red'): ?>
                                            <span style="font-weight: bold; color: white; background-color: red; padding: 2px 5px; border-radius: 3px;">[严重警告] </span>
                                        <?php elseif ($report_flag === 'ban'): ?>
                                            <span style="font-weight: bold; color: white; background-color: darkred; padding: 2px 5px; border-radius: 3px;">[已封禁] </span>
                                        <?php endif; ?>
                                        <h4 style="margin: 0 0 5px 0; display: inline;">
                                            <a href="<?php echo getTopicUrl($topic['id'], null, $topic['title']); ?>">
                                                <?php echo highlightKeyword($topic['title'], $keyword); ?>
                                            </a>
                                        </h4>
                                        <p style="margin: 0 0 5px 0;"><?php echo highlightKeyword(mb_substr(strip_tags($topic['content']), 0, 200) . '...', $keyword); ?></p>
                                        <small style="color: #666;">
                                            分类: <a href="<?php echo getCategoryUrl($topic['category_id'], null, $topic['category_title']); ?>"><?php echo htmlspecialchars($topic['category_title']); ?></a> | 
                                            作者: <?php echo htmlspecialchars($topic['username']); ?> | 
                                            发表于: <?php echo formatDateTime($topic['created_at']); ?> | 
                                            回复: <?php echo $topic['reply_count']; ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ($results as $post): ?>
                                <tr>
                                    <td colspan="2" style="padding: 10px;">
                                        <h4 style="margin: 0 0 5px 0;">
                                            <a href="<?php echo getTopicUrl($post['topic_id'], null, $post['topic_title']); ?>#post-<?php echo $post['id']; ?>">回复: <?php echo htmlspecialchars($post['topic_title']); ?></a>
                                        </h4>
                                        <p style="margin: 0 0 5px 0;"><?php echo highlightKeyword(mb_substr(strip_tags($post['content']), 0, 200) . '...', $keyword); ?></p>
                                        <small style="color: #666;">
                                            分类: <a href="<?php echo getCategoryUrl($post['category_id'], null, $post['category_title']); ?>"><?php echo htmlspecialchars($post['category_title']); ?></a> | 
                                            作者: <?php echo htmlspecialchars($post['username']); ?> | 
                                            发表于: <?php echo formatDateTime($post['created_at']); ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2" align="center" class="info" style="padding: 20px;">
                                没有找到匹配的结果
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
            </td>
        </tr>
        
        <!-- 分页 -->
        <?php if ($total_pages > 1): ?>
            <tr>
                <td colspan="2" align="center" style="padding: 10px;">
                    <?php 
                    if (!empty($tag)) {
                        echo generatePagination($page, $total_pages, 'search.php?tag=' . urlencode($tag) . '&category_id=' . $category_id . '&page=%d');
                    } else {
                        echo generatePagination($page, $total_pages, 'search.php?keyword=' . urlencode($keyword) . '&type=' . $type . '&category_id=' . $category_id . '&page=%d');
                    }
                    ?>
                </td>
            </tr>
        <?php endif; ?>
    <?php endif; ?>
</table>

<?php
// 加载页面底部
include __DIR__ . '/templates/footer.php';

/**
 * 高亮关键词
 */
function highlightKeyword($text, $keyword) {
    if (empty($keyword)) {
        return htmlspecialchars($text);
    }
    
    $text = htmlspecialchars($text);
    $keyword = htmlspecialchars($keyword);
    
    return preg_replace('/(' . preg_quote($keyword, '/') . ')/i', '<mark>$1</mark>', $text);
}
?>

