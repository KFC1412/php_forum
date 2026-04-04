<?php
    date_default_timezone_set('Asia/Shanghai');
/**
 * 回复管理页面
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

/**
 * 更新主题的最后回复信息
 * @param int $topic_id 主题ID
 */
function updateTopicLastPost($topic_id) {
    global $db, $prefix;
    
    $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
    
    // 查找该主题的最新回复
    if ($storage_type === 'json') {
        // JSON存储：查找最新回复
        $latest_post = $db->select('posts', ['topic_id' => $topic_id], 'created_at DESC', 1)[0] ?? null;
    } else {
        // MySQL存储：查找最新回复
        $latest_post = $db->fetch(
            "SELECT * FROM `{$prefix}posts` WHERE `topic_id` = :topic_id ORDER BY `created_at` DESC LIMIT 1",
            ['topic_id' => $topic_id]
        );
    }
    
    // 更新主题的最后回复信息
    if ($latest_post) {
        // 有最新回复，更新主题信息
        $db->update("{$prefix}topics", 
            [
                'last_post_id' => $latest_post['id'],
                'last_post_user_id' => $latest_post['user_id'],
                'last_post_time' => $latest_post['created_at']
            ],
            'id = :id',
            ['id' => $topic_id]
        );
    } else {
        // 没有回复，清除主题的最后回复信息
        $db->update("{$prefix}topics", 
            [
                'last_post_id' => null,
                'last_post_user_id' => null,
                'last_post_time' => null
            ],
            'id = :id',
            ['id' => $topic_id]
        );
    }
}

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

// 处理回复操作
switch ($action) {
    case 'edit':
        // 编辑回复
        $post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($post_id <= 0) {
            header('Location: posts.php');
            exit;
        }
        
        // 获取回复信息
        try {
            $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
            
            if ($storage_type === 'json') {
                // JSON存储：使用简单查询
                $post = $db->findById('posts', $post_id);
                
                if (!$post) {
                    header('Location: posts.php');
                    exit;
                }
                
                // 获取用户和主题信息
                $user = $db->findById('users', $post['user_id']);
                $topic = $db->findById('topics', $post['topic_id']);
                $post['username'] = $user ? $user['username'] : '未知用户';
                $post['topic_title'] = $topic ? $topic['title'] : '未知主题';
            } else {
                // MySQL存储：使用JOIN查询
                $post = $db->fetch(
                    "SELECT p.*, u.username, t.title as topic_title 
                    FROM `{$prefix}posts` p 
                    JOIN `{$prefix}users` u ON p.user_id = u.id 
                    JOIN `{$prefix}topics` t ON p.topic_id = t.id 
                    WHERE p.id = :id",
                    ['id' => $post_id]
                );
            }
            
            if (!$post) {
                header('Location: posts.php');
                exit;
            }
        } catch (Exception $e) {
            $error = '获取回复信息失败: ' . $e->getMessage();
        }
        
        // 处理表单提交
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $content = $_POST['content'] ?? '';
            $status = $_POST['status'] ?? 'published';
            
            // 验证输入
            if (empty($content)) {
                $error = '回复内容不能为空';
            } else {
                try {
                    // 更新回复信息
                        $db->update("{$prefix}posts", [
                            'content' => $content,
                            'status' => $status,
                            'updated_at' => date('Y-m-d H:i:s')
                        ], '`id` = :id', ['id' => $post_id]);
                        
                        // 发送状态变更通知
                        if ($status === 'hidden' || $status === 'deleted') {
                            // 加载邮件函数
                            require_once __DIR__ . '/../includes/mail_functions.php';
                            sendInteractionNotification($post['user_id'], 'post_deleted', [
                                'topic_title' => $post['topic_title'],
                                'reason' => '管理员操作'
                            ]);
                        }
                        
                        // 记录操作日志
                    logAdminAction('管理员编辑回复', 'post', $post_id, [
                        'topic_id' => $post['topic_id'],
                        'topic_title' => $post['topic_title'],
                        'author_id' => $post['user_id'],
                        'author_username' => $post['username'],
                        'content_length' => mb_strlen($content),
                        'admin_id' => $_SESSION['user_id'],
                        'admin_username' => $_SESSION['username'],
                        'action_time' => date('Y-m-d H:i:s'),
                        'ip_address' => getClientIp()
                    ]);
                    
                    $success = '回复信息更新成功';
                    
                    // 重新获取回复信息
                    if ($storage_type === 'json') {
                        $post = $db->findById('posts', $post_id);
                        $user = $db->findById('users', $post['user_id']);
                        $topic = $db->findById('topics', $post['topic_id']);
                        $post['username'] = $user ? $user['username'] : '未知用户';
                        $post['topic_title'] = $topic ? $topic['title'] : '未知主题';
                    } else {
                        $post = $db->fetch(
                            "SELECT p.*, u.username, t.title as topic_title 
                            FROM `{$prefix}posts` p 
                            JOIN `{$prefix}users` u ON p.user_id = u.id 
                            JOIN `{$prefix}topics` t ON p.topic_id = t.id 
                            WHERE p.id = :id",
                            ['id' => $post_id]
                        );
                    }
                } catch (Exception $e) {
                    $error = '更新回复信息失败: ' . $e->getMessage();
                }
            }
        }
        
        // 设置页面标题
        $page_title = '编辑回复';
        
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
                                <h1>编辑回复</h1>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="right">
                                <a href="posts.php">返回回复列表</a> | 
                                <a href="../topic.php?id=<?php echo $post['topic_id']; ?>#post-<?php echo $post_id; ?>" target="_blank">查看回复</a>
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
                                        <td colspan="2"><h5>回复信息</h5></td>
                                    </tr>
                                    <tr>
                                        <td width="20%"><strong>ID:</strong></td>
                                        <td><?php echo $post['id']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>所属主题:</strong></td>
                                        <td>
                                            <a href="../topic.php?id=<?php echo $post['topic_id']; ?>" target="_blank">
                                                <?php echo htmlspecialchars($post['topic_title']); ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>作者:</strong></td>
                                        <td><?php echo htmlspecialchars($post['username']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>创建时间:</strong></td>
                                        <td><?php echo formatDateTime($post['created_at']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>最后更新:</strong></td>
                                        <td><?php echo formatDateTime($post['updated_at']); ?></td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        
                        <tr>
                            <td colspan="2">
                                <form method="post" action="posts.php?action=edit&id=<?php echo $post_id; ?>">
                                    <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                        <tr>
                                            <td width="20%">回复内容</td>
                                            <td>
                                                <textarea name="content" rows="10" required style="width: 100%; padding: 4px;"><?php echo htmlspecialchars($post['content']); ?></textarea>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>状态</td>
                                            <td>
                                                <select name="status">
                                                    <?php foreach (getPostStatuses() as $key => $value): ?>
                                                        <option value="<?php echo $key; ?>" <?php echo $post['status'] === $key ? 'selected' : ''; ?>>
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
        // 删除回复
        $post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($post_id <= 0) {
            header('Location: posts.php');
            exit;
        }
        
        try {
            // 获取回复信息
            $post = $db->fetch(
                "SELECT * FROM `{$prefix}posts` WHERE `id` = :id",
                ['id' => $post_id]
            );
            
            if (!$post) {
                header('Location: posts.php');
                exit;
            }
            
            // 删除回复
            $db->delete("{$prefix}posts", '`id` = :id', ['id' => $post_id]);
            
            // 发送删除通知
            require_once __DIR__ . '/../includes/mail_functions.php';
            // 获取主题信息
            $topic = $db->fetch(
                "SELECT title FROM `{$prefix}topics` WHERE `id` = :topic_id",
                ['topic_id' => $post['topic_id']]
            );
            $topic_title = $topic ? $topic['title'] : '未知主题';
            sendInteractionNotification($post['user_id'], 'post_deleted', [
                'topic_title' => $topic_title,
                'reason' => '管理员删除'
            ]);
            
            // 更新主题的最后回复信息
            updateTopicLastPost($post['topic_id']);
            
            // 记录操作日志
            logAdminAction('管理员删除回复', 'post', $post_id, [
                'topic_id' => $post['topic_id'],
                'author_id' => $post['user_id'],
                'author_username' => $post['username'],
                'content_length' => mb_strlen($post['content']),
                'admin_id' => $_SESSION['user_id'],
                'admin_username' => $_SESSION['username'],
                'action_time' => date('Y-m-d H:i:s'),
                'ip_address' => getClientIp()
            ]);
            
            header('Location: posts.php?success=' . urlencode('回复删除成功'));
            exit;
        } catch (Exception $e) {
            header('Location: posts.php?error=' . urlencode('删除回复失败: ' . $e->getMessage()));
            exit;
        }
        break;
        
    default:
        // 回复列表
        // 获取页码
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) {
            $page = 1;
        }
        
        // 每页显示的回复数
        $posts_per_page = 20;
        
        // 获取搜索参数
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $topic_id = isset($_GET['topic_id']) ? (int)$_GET['topic_id'] : 0;
        $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        
        try {
            $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
            
            // 构建查询条件
            $conditions = [];
            $params = [];
            
            if (!empty($search)) {
                $conditions[] = 'p.content LIKE :search';
                $params['search'] = '%' . $search . '%';
            }
            
            if ($topic_id > 0) {
                $conditions[] = 'p.topic_id = :topic_id';
                $params['topic_id'] = $topic_id;
            }
            
            if ($user_id > 0) {
                $conditions[] = 'p.user_id = :user_id';
                $params['user_id'] = $user_id;
            }
            
            if (!empty($status)) {
                $conditions[] = 'p.status = :status';
                $params['status'] = $status;
            }
            
            $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
            
            // 获取回复总数
            $total_posts = $db->fetchColumn(
                "SELECT COUNT(*) FROM `{$prefix}posts` p {$where_clause}",
                $params
            );
            
            // 计算总页数
            $total_pages = ceil($total_posts / $posts_per_page);
            
            // 确保页码不超过总页数
            if ($page > $total_pages && $total_pages > 0) {
                $page = $total_pages;
            }
            
            // 计算偏移量
            $offset = ($page - 1) * $posts_per_page;
            
            // 获取回复列表
            if ($storage_type === 'json') {
                // JSON存储：使用简单查询
                $posts = $db->select('posts', [], 'id DESC', $posts_per_page);
                
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
                foreach ($posts as &$post) {
                    $post['username'] = isset($users[$post['user_id']]) ? $users[$post['user_id']]['username'] : '未知用户';
                    $post['topic_title'] = isset($topics[$post['topic_id']]) ? $topics[$post['topic_id']]['title'] : '未知主题';
                }
                unset($post);
            } else {
                // MySQL存储：使用JOIN查询
                $posts = $db->fetchAll(
                    "SELECT p.*, u.username, t.title as topic_title 
                    FROM `{$prefix}posts` p 
                    JOIN `{$prefix}users` u ON p.user_id = u.id 
                    JOIN `{$prefix}topics` t ON p.topic_id = t.id 
                    {$where_clause} 
                    ORDER BY p.id DESC 
                    LIMIT :offset, :limit",
                    array_merge($params, ['offset' => $offset, 'limit' => $posts_per_page])
                );
            }
        } catch (Exception $e) {
            $error = '获取回复列表失败: ' . $e->getMessage();
        }
        
        // 获取成功或错误消息
        if (isset($_GET['success'])) {
            $success = $_GET['success'];
        }
        
        if (isset($_GET['error'])) {
            $error = $_GET['error'];
        }
        
        // 设置页面标题
        $page_title = '回复管理';
        
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
                                <h1>回复管理</h1>
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
                                <form method="get" action="posts.php">
                                    <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                        <tr>
                                            <td width="30%">
                                                <input type="text" name="search" placeholder="搜索回复内容" value="<?php echo htmlspecialchars($search); ?>">
                                            </td>
                                            <td width="15%">
                                                <input type="number" name="topic_id" placeholder="主题ID" value="<?php echo $topic_id > 0 ? $topic_id : ''; ?>">
                                            </td>
                                            <td width="15%">
                                                <input type="number" name="user_id" placeholder="用户ID" value="<?php echo $user_id > 0 ? $user_id : ''; ?>">
                                            </td>
                                            <td width="20%">
                                                <select name="status">
                                                    <option value="">所有状态</option>
                                                    <?php foreach (getPostStatuses() as $key => $value): ?>
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
                                        <th>内容</th>
                                        <th>主题</th>
                                        <th>作者</th>
                                        <th>状态</th>
                                        <th>创建时间</th>
                                        <th>操作</th>
                                    </tr>
                                    <?php if (isset($posts) && count($posts) > 0): ?>
                                        <?php foreach ($posts as $post): ?>
                                            <tr>
                                                <td><?php echo $post['id']; ?></td>
                                                <td><?php echo htmlspecialchars(mb_substr($post['content'], 0, 50)) . (mb_strlen($post['content']) > 50 ? '...' : ''); ?></td>
                                                <td>
                                                    <a href="../topic.php?id=<?php echo $post['topic_id']; ?>" target="_blank">
                                                        <?php echo htmlspecialchars(mb_substr($post['topic_title'], 0, 20)) . (mb_strlen($post['topic_title']) > 20 ? '...' : ''); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($post['username']); ?></td>
                                                <td>
                                                    <?php if ($post['status'] === 'published'): ?>
                                                        已发布
                                                    <?php elseif ($post['status'] === 'hidden'): ?>
                                                        已隐藏
                                                    <?php else: ?>
                                                        已删除
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatDateTime($post['created_at']); ?></td>
                                                <td>
                                                    <a href="posts.php?action=edit&id=<?php echo $post['id']; ?>">编辑</a>
                                                    <a href="posts.php?action=delete&id=<?php echo $post['id']; ?>" class="confirm-delete">删除</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" align="center">没有找到回复</td>
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
                                    $pagination_url = 'posts.php?';
                                    if (!empty($search)) {
                                        $pagination_url .= 'search=' . urlencode($search) . '&';
                                    }
                                    if ($topic_id > 0) {
                                        $pagination_url .= 'topic_id=' . $topic_id . '&';
                                    }
                                    if ($user_id > 0) {
                                        $pagination_url .= 'user_id=' . $user_id . '&';
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

