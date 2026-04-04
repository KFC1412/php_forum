<?php
    date_default_timezone_set('Asia/Shanghai');
/**
 * 主题管理页面
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

// 获取操作类型
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// 处理操作
$error = '';
$success = '';

// 获取数据库实例
$db = getDB();
$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';

// 处理主题操作
switch ($action) {
    case 'sticky':
        // 置顶/取消置顶主题
        $topic_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($topic_id <= 0) {
            header('Location: topics.php');
            exit;
        }
        
        try {
            // 获取主题信息
            $topic = $db->fetch(
                "SELECT * FROM `{$prefix}topics` WHERE `id` = :id",
                ['id' => $topic_id]
            );
            
            if (!$topic) {
                header('Location: topics.php');
                exit;
            }
            
            // 切换置顶状态
            $new_sticky = $topic['is_sticky'] ? 0 : 1;
            
            // 更新主题状态
            $db->update("{$prefix}topics", [
                'is_sticky' => $new_sticky,
                'updated_at' => date('Y-m-d H:i:s')
            ], '`id` = :id', ['id' => $topic_id]);
            
            // 记录操作日志
            logAdminAction($new_sticky ? '管理员置顶主题' : '管理员取消置顶主题', 'topic', $topic_id, [
                'title' => $topic['title'],
                'category_id' => $topic['category_id'],
                'author_id' => $topic['user_id'],
                'admin_id' => $_SESSION['user_id'],
                'admin_username' => $_SESSION['username']
            ]);
            
            header('Location: topics.php?success=' . urlencode($new_sticky ? '主题置顶成功' : '取消主题置顶成功'));
            exit;
        } catch (Exception $e) {
            header('Location: topics.php?error=' . urlencode('操作失败: ' . $e->getMessage()));
            exit;
        }
        break;
        
    case 'recommend':
        // 推荐/取消推荐主题
        $topic_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($topic_id <= 0) {
            header('Location: topics.php');
            exit;
        }
        
        try {
            // 获取主题信息
            $topic = $db->fetch(
                "SELECT * FROM `{$prefix}topics` WHERE `id` = :id",
                ['id' => $topic_id]
            );
            
            if (!$topic) {
                header('Location: topics.php');
                exit;
            }
            
            // 切换推荐状态
            $new_recommended = $topic['is_recommended'] ? 0 : 1;
            
            // 更新主题状态
            $db->update("{$prefix}topics", [
                'is_recommended' => $new_recommended,
                'updated_at' => date('Y-m-d H:i:s')
            ], '`id` = :id', ['id' => $topic_id]);
            
            // 记录操作日志
            logAdminAction($new_recommended ? '管理员推荐主题' : '管理员取消推荐主题', 'topic', $topic_id, [
                'title' => $topic['title'],
                'category_id' => $topic['category_id'],
                'author_id' => $topic['user_id'],
                'admin_id' => $_SESSION['user_id'],
                'admin_username' => $_SESSION['username']
            ]);
            
            header('Location: topics.php?success=' . urlencode($new_recommended ? '主题推荐成功' : '取消主题推荐成功'));
            exit;
        } catch (Exception $e) {
            header('Location: topics.php?error=' . urlencode('操作失败: ' . $e->getMessage()));
            exit;
        }
        break;
        
    case 'edit':
        // 编辑主题
        $topic_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($topic_id <= 0) {
            header('Location: topics.php');
            exit;
        }
        
        // 获取主题信息
        try {
            $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
            
            if ($storage_type === 'json') {
                // JSON存储：使用简单查询
                $topic = $db->findById('topics', $topic_id);
                
                if (!$topic) {
                    header('Location: topics.php');
                    exit;
                }
                
                // 获取用户和分类信息
                $user = $db->findById('users', $topic['user_id']);
                $category = $db->findById('categories', $topic['category_id']);
                $topic['username'] = $user ? $user['username'] : '未知用户';
                $topic['category_title'] = $category ? $category['title'] : '未知分类';
            } else {
                // MySQL存储：使用JOIN查询
                $topic = $db->fetch(
                    "SELECT t.*, u.username, c.title as category_title 
                    FROM `{$prefix}topics` t 
                    JOIN `{$prefix}users` u ON t.user_id = u.id 
                    JOIN `{$prefix}categories` c ON t.category_id = c.id 
                    WHERE t.id = :id",
                    ['id' => $topic_id]
                );
            }
            
            if (!$topic) {
                header('Location: topics.php');
                exit;
            }
            
            // 获取分类列表
            $categories = $db->fetchAll(
                "SELECT * FROM `{$prefix}categories` ORDER BY `sort_order` ASC, `id` ASC"
            );
        } catch (Exception $e) {
            $error = '获取主题信息失败: ' . $e->getMessage();
        }
        
        // 处理表单提交
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
            $status = $_POST['status'] ?? 'published';
            
            // 验证输入
            if (empty($title) || empty($content) || $category_id <= 0) {
                $error = '请填写所有必填字段';
            } else {
                try {
                    // 检查分类是否存在
                    $category_exists = $db->fetchColumn(
                        "SELECT COUNT(*) FROM `{$prefix}categories` WHERE `id` = :id",
                        ['id' => $category_id]
                    );
                    
                    if ($category_exists == 0) {
                        $error = '所选分类不存在';
                    } else {
                        // 更新主题信息
                        $db->update("{$prefix}topics", [
                            'title' => $title,
                            'content' => $content,
                            'category_id' => $category_id,
                            'status' => $status,
                            'updated_at' => date('Y-m-d H:i:s')
                        ], '`id` = :id', ['id' => $topic_id]);
                        
                        // 发送状态变更通知
                        if ($status === 'hidden' || $status === 'deleted') {
                            // 加载邮件函数
                            require_once __DIR__ . '/../includes/mail_functions.php';
                            $topic_url = getTopicUrl($topic_id);
                            sendInteractionNotification($topic['user_id'], $status === 'hidden' ? 'topic_hidden' : 'topic_deleted', [
                                'topic_title' => $title,
                                'reason' => '管理员操作',
                                'topic_url' => $topic_url
                            ]);
                        }
                        
                        // 记录操作日志
                        logAdminAction('管理员编辑主题', 'topic', $topic_id, [
                            'title' => $title,
                            'old_title' => $topic['title'],
                            'category_id' => $category_id,
                            'old_category_id' => $topic['category_id'],
                            'content_length' => mb_strlen($content),
                            'admin_id' => $_SESSION['user_id'],
                            'admin_username' => $_SESSION['username'],
                            'action_time' => date('Y-m-d H:i:s'),
                            'ip_address' => getClientIp()
                        ]);
                        
                        $success = '主题信息更新成功';
                        
                        // 重新获取主题信息
                        if ($storage_type === 'json') {
                            $topic = $db->findById('topics', $topic_id);
                            $user = $db->findById('users', $topic['user_id']);
                            $category = $db->findById('categories', $topic['category_id']);
                            $topic['username'] = $user ? $user['username'] : '未知用户';
                            $topic['category_title'] = $category ? $category['title'] : '未知分类';
                        } else {
                            $topic = $db->fetch(
                                "SELECT t.*, u.username, c.title as category_title 
                                FROM `{$prefix}topics` t 
                                JOIN `{$prefix}users` u ON t.user_id = u.id 
                                JOIN `{$prefix}categories` c ON t.category_id = c.id 
                                WHERE t.id = :id",
                                ['id' => $topic_id]
                            );
                        }
                    }
                } catch (Exception $e) {
                    $error = '更新主题信息失败: ' . $e->getMessage();
                }
            }
        }
        
        // 设置页面标题
        $page_title = '编辑主题';
        
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
                                <h1>编辑主题</h1>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="right">
                                <a href="topics.php">返回主题列表</a> | 
                                <a href="../topic.php?id=<?php echo $topic_id; ?>" target="_blank">查看主题</a>
                            </td>
                        </tr>
                        
                        <?php if (!empty($error)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>错误：</strong><?php echo $error; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>成功：</strong><?php echo $success; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <tr>
                            <td colspan="2">
                                <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                    <tr>
                                        <td colspan="2"><h5>主题信息</h5></td>
                                    </tr>
                                    <tr>
                                        <td width="20%"><strong>ID:</strong></td>
                                        <td><?php echo $topic['id']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>作者:</strong></td>
                                        <td><?php echo htmlspecialchars($topic['username']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>创建时间:</strong></td>
                                        <td><?php echo formatDateTime($topic['created_at']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>最后更新:</strong></td>
                                        <td><?php echo formatDateTime($topic['updated_at']); ?></td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        
                        <tr>
                            <td colspan="2">
                                <form method="post" action="topics.php?action=edit&id=<?php echo $topic_id; ?>">
                                    <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                        <tr>
                                            <td width="20%">主题标题</td>
                                            <td>
                                                <input type="text" name="title" value="<?php echo htmlspecialchars($topic['title']); ?>" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>所属分类</td>
                                            <td>
                                                <select name="category_id" required>
                                                    <option value="">-- 请选择分类 --</option>
                                                    <?php foreach ($categories as $category): ?>
                                                        <option value="<?php echo $category['id']; ?>" <?php echo $topic['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($category['title']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>主题内容</td>
                                            <td>
                                                <textarea name="content" rows="10" required><?php echo htmlspecialchars($topic['content']); ?></textarea>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>状态</td>
                                            <td>
                                                <select name="status">
                                                    <?php foreach (getTopicStatuses() as $key => $value): ?>
                                                        <option value="<?php echo $key; ?>" <?php echo $topic['status'] === $key ? 'selected' : ''; ?>>
                                                            <?php echo $value; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <button type="submit">保存修改</button>
                                            </td>
                                        </tr>
                                    </table>
                                </form>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        
        <?php
        // 加载页面底部
        include __DIR__ . '/templates/admin_footer.php';
        break;
        
    case 'delete':
        // 删除主题
        $topic_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($topic_id <= 0) {
            header('Location: topics.php');
            exit;
        }
        
        try {
            // 获取主题信息
            $topic = $db->fetch(
                "SELECT * FROM `{$prefix}topics` WHERE `id` = :id",
                ['id' => $topic_id]
            );
            
            if (!$topic) {
                header('Location: topics.php');
                exit;
            }
            
            // 删除主题相关的回复
            $db->delete("{$prefix}posts", '`topic_id` = :topic_id', ['topic_id' => $topic_id]);
            
            // 删除主题
            $db->delete("{$prefix}topics", '`id` = :id', ['id' => $topic_id]);
            
            // 发送删除通知
            require_once __DIR__ . '/../includes/mail_functions.php';
            sendInteractionNotification($topic['user_id'], 'topic_deleted', [
                'topic_title' => $topic['title'],
                'reason' => '管理员删除'
            ]);
            
            // 记录操作日志
            logAdminAction('管理员删除主题', 'topic', $topic_id, [
                'title' => $topic['title'],
                'category_id' => $topic['category_id'],
                'author_id' => $topic['user_id'],
                'author_username' => $topic['username'],
                'reply_count' => $topic['reply_count'],
                'view_count' => $topic['view_count'],
                'admin_id' => $_SESSION['user_id'],
                'admin_username' => $_SESSION['username'],
                'action_time' => date('Y-m-d H:i:s'),
                'ip_address' => getClientIp()
            ]);
            
            header('Location: topics.php?success=' . urlencode('主题删除成功'));
            exit;
        } catch (Exception $e) {
            header('Location: topics.php?error=' . urlencode('删除主题失败: ' . $e->getMessage()));
            exit;
        }
        break;
        
    default:
        // 主题列表
        // 获取页码
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) {
            $page = 1;
        }
        
        // 每页显示的主题数
        $topics_per_page = 20;
        
        // 获取搜索参数
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        
        try {
            // 获取分类列表
            $categories = $db->fetchAll(
                "SELECT * FROM `{$prefix}categories` ORDER BY `sort_order` ASC, `id` ASC"
            );
            
            $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
            
            // 构建查询条件
            $conditions = [];
            $params = [];
            
            if (!empty($search)) {
                $conditions[] = '(t.title LIKE :search OR t.content LIKE :search)';
                $params['search'] = '%' . $search . '%';
            }
            
            if ($category_id > 0) {
                $conditions[] = 't.category_id = :category_id';
                $params['category_id'] = $category_id;
            }
            
            if (!empty($status)) {
                $conditions[] = 't.status = :status';
                $params['status'] = $status;
            }
            
            // 排除每日60秒热点资讯主题（ID为0）
            $conditions[] = 't.id != 0';
            
            $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
            
            // 获取主题总数
            // 初始化总主题数
            $total_topics = 0;
            
            if ($storage_type === 'json') {
                // JSON存储：先获取所有主题，后续会在过滤后重新计算
                $all_topics = $db->select('topics', [], 'id DESC');
                
                // 应用搜索条件
                $filtered_topics = [];
                foreach ($all_topics as $topic) {
                    // 排除每日60秒热点资讯主题（ID为0）
                    if (isset($topic['id']) && $topic['id'] == 0) {
                        continue;
                    }
                    
                    // 搜索条件
                    if (!empty($search)) {
                        if (strpos($topic['title'], $search) === false && strpos($topic['content'], $search) === false) {
                            continue;
                        }
                    }
                    
                    // 分类条件
                    if ($category_id > 0 && $topic['category_id'] != $category_id) {
                        continue;
                    }
                    
                    // 状态条件
                    if (!empty($status) && $topic['status'] != $status) {
                        continue;
                    }
                    
                    $filtered_topics[] = $topic;
                }
                
                // 计算总数
                $total_topics = count($filtered_topics);
            } else {
                // MySQL存储：使用SQL查询
                $total_topics = $db->fetchColumn(
                    "SELECT COUNT(*) FROM `{$prefix}topics` t {$where_clause}",
                    $params
                );
            }
            
            // 计算总页数
            $total_pages = ceil($total_topics / $topics_per_page);
            
            // 确保页码不超过总页数
            if ($page > $total_pages && $total_pages > 0) {
                $page = $total_pages;
            }
            
            // 计算偏移量
            $offset = ($page - 1) * $topics_per_page;
            
            // 获取主题列表
            if ($storage_type === 'json') {
                // 应用分页
                $topics = array_slice($filtered_topics, $offset, $topics_per_page);
                
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
                $all_posts = $db->select('posts');
                $post_counts = [];
                foreach ($all_posts as $p) {
                    if (!isset($post_counts[$p['topic_id']])) {
                        $post_counts[$p['topic_id']] = 0;
                    }
                    $post_counts[$p['topic_id']]++;
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
                    
                    $topic['username'] = isset($users[$topic['user_id']]) ? $users[$topic['user_id']]['username'] : '未知用户';
                    $topic['category_title'] = isset($cats[$topic['category_id']]) ? $cats[$topic['category_id']]['title'] : '未知分类';
                    $topic['reply_count'] = isset($post_counts[$topic['id']]) ? $post_counts[$topic['id']] : 0;
                }
                unset($topic);
            } else {
                // MySQL存储：使用JOIN查询
                $topics = $db->fetchAll(
                    "SELECT t.*, u.username, c.title as category_title,
                    (SELECT COUNT(*) FROM `{$prefix}posts` WHERE `topic_id` = t.id) as reply_count
                    FROM `{$prefix}topics` t 
                    JOIN `{$prefix}users` u ON t.user_id = u.id 
                    JOIN `{$prefix}categories` c ON t.category_id = c.id 
                    {$where_clause} 
                    ORDER BY t.id DESC 
                    LIMIT :offset, :limit",
                    array_merge($params, ['offset' => $offset, 'limit' => $topics_per_page])
                );
                
                // 设置默认值
                foreach ($topics as &$topic) {
                    if (!isset($topic['is_sticky'])) {
                        $topic['is_sticky'] = false;
                    }
                    if (!isset($topic['is_recommended'])) {
                        $topic['is_recommended'] = false;
                    }
                }
                unset($topic);
            }
        } catch (Exception $e) {
            $error = '获取主题列表失败: ' . $e->getMessage();
        }
        
        // 获取成功或错误消息
        if (isset($_GET['success'])) {
            $success = $_GET['success'];
        }
        
        if (isset($_GET['error'])) {
            $error = $_GET['error'];
        }
        
        // 设置页面标题
        $page_title = '主题管理';
        
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
                                <h1>主题管理</h1>
                            </td>
                        </tr>
                        
                        <?php if (!empty($error)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>错误：</strong><?php echo $error; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>成功：</strong><?php echo $success; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <tr>
                            <td colspan="2">
                                <form method="get" action="topics.php">
                                    <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                        <tr>
                                            <td width="30%">
                                                <input type="text" name="search" placeholder="搜索主题标题或内容" value="<?php echo htmlspecialchars($search); ?>">
                                            </td>
                                            <td width="25%">
                                                <select name="category_id">
                                                    <option value="0">所有分类</option>
                                                    <?php foreach ($categories as $category): ?>
                                                        <option value="<?php echo $category['id']; ?>" <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($category['title']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td width="25%">
                                                <select name="status">
                                                    <option value="">所有状态</option>
                                                    <?php foreach (getTopicStatuses() as $key => $value): ?>
                                                        <option value="<?php echo $key; ?>" <?php echo $status === $key ? 'selected' : ''; ?>>
                                                            <?php echo $value; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td width="20%" align="center">
                                                <button type="submit">搜索</button>
                                            </td>
                                        </tr>
                                    </table>
                                </form>
                            </td>
                        </tr>
                        
                        <tr>
                            <td colspan="2">
                                <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                    <tr>
                                        <th>ID</th>
                                        <th>标题</th>
                                        <th>分类</th>
                                        <th>作者</th>
                                        <th>回复数</th>
                                        <th>状态</th>
                                        <th>创建时间</th>
                                        <th>操作</th>
                                    </tr>
                                    <?php if (isset($topics) && count($topics) > 0): ?>
                                        <?php foreach ($topics as $topic): ?>
                                            <tr>
                                                <td><?php echo $topic['id']; ?></td>
                                                <td>
                                                    <a href="../topic.php?id=<?php echo $topic['id']; ?>" target="_blank">
                                                        <?php echo htmlspecialchars(mb_substr($topic['title'], 0, 30)) . (mb_strlen($topic['title']) > 30 ? '...' : ''); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($topic['category_title']); ?></td>
                                                <td><?php echo htmlspecialchars($topic['username']); ?></td>
                                                <td><?php echo $topic['reply_count']; ?></td>
                                                <td>
                                                    <?php if ($topic['status'] === 'published'): ?>
                                                        已发布
                                                    <?php elseif ($topic['status'] === 'draft'): ?>
                                                        草稿
                                                    <?php elseif ($topic['status'] === 'hidden'): ?>
                                                        <span style="background-color: red; color: white; padding: 2px 5px; border-radius: 3px; font-size: 0.8em;">已隐藏</span>
                                                    <?php else: ?>
                                                        已删除
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatDateTime($topic['created_at']); ?></td>
                                                <td>
                                                    <a href="topics.php?action=edit&id=<?php echo $topic['id']; ?>">编辑</a>
                                                    <a href="topics.php?action=sticky&id=<?php echo $topic['id']; ?>" class="confirm-action">
                                                        <?php echo $topic['is_sticky'] ? '取消置顶' : '置顶'; ?>
                                                    </a>
                                                    <a href="topics.php?action=recommend&id=<?php echo $topic['id']; ?>" class="confirm-action">
                                                        <?php echo $topic['is_recommended'] ? '取消推荐' : '推荐'; ?>
                                                    </a>
                                                    <a href="topics.php?action=delete&id=<?php echo $topic['id']; ?>" class="confirm-delete">删除</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" align="center">没有找到主题</td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </td>
                        </tr>
                        
                        <?php if (isset($total_pages) && $total_pages > 1): ?>
                            <tr>
                                <td colspan="2" align="center">
                                    <?php
                                    // 构建分页URL
                                    $pagination_url = 'topics.php?';
                                    if (!empty($search)) {
                                        $pagination_url .= 'search=' . urlencode($search) . '&';
                                    }
                                    if ($category_id > 0) {
                                        $pagination_url .= 'category_id=' . $category_id . '&';
                                    }
                                    if (!empty($status)) {
                                        $pagination_url .= 'status=' . urlencode($status) . '&';
                                    }
                                    $pagination_url .= 'page=%d';
                                    
                                    echo generateAdminPagination($page, $total_pages, $pagination_url);
                                    ?>
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
        break;
}
?>

