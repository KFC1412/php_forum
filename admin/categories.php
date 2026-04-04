<?php
    date_default_timezone_set('Asia/Shanghai');
/**
 * 分类管理页面
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

// 处理分类操作
switch ($action) {
    case 'add':
        // 添加分类
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
            
            // 验证输入
            if (empty($title)) {
                $error = '分类标题不能为空';
            } else {
                try {
                    // 检查分类标题是否已存在
                    $exists = $db->fetchColumn(
                        "SELECT COUNT(*) FROM `{$prefix}categories` WHERE `title` = :title",
                        ['title' => $title]
                    );
                    
                    if ($exists > 0) {
                        $error = '分类标题已存在';
                    } else {
                        // 创建分类
                        $db->insert("{$prefix}categories", [
                            'title' => $title,
                            'description' => $description,
                            'sort_order' => $sort_order,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        // 记录操作日志
                        logAdminAction('管理员添加分类', 'category', $db->lastInsertId(), [
                            'title' => $title,
                            'description' => $description,
                            'sort_order' => $sort_order,
                            'admin_id' => $_SESSION['user_id'],
                            'admin_username' => $_SESSION['username']
                        ]);
                        
                        $success = '分类添加成功';
                        
                        // 重定向到分类列表
                        header('Location: categories.php?success=' . urlencode($success));
                        exit;
                    }
                } catch (Exception $e) {
                    $error = '添加分类失败: ' . $e->getMessage();
                }
            }
        }
        
        // 设置页面标题
        $page_title = '添加分类';
        
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
                                <h1>添加分类</h1>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="right">
                                <a href="categories.php">返回分类列表</a>
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
                                <form method="post" action="categories.php?action=add">
                                    <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                        <tr>
                                            <td width="20%">分类标题</td>
                                            <td>
                                                <input type="text" name="title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>分类描述</td>
                                            <td>
                                                <textarea name="description" rows="3"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>排序顺序</td>
                                            <td>
                                                <input type="number" name="sort_order" value="<?php echo isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0; ?>">
                                                <br>
                                                <small>数字越小排序越靠前</small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <button type="submit">添加分类</button>
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
        
    case 'edit':
        // 编辑分类
        $category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($category_id <= 0) {
            header('Location: categories.php');
            exit;
        }
        
        // 获取分类信息
        try {
            $category = $db->fetch(
                "SELECT * FROM `{$prefix}categories` WHERE `id` = :id",
                ['id' => $category_id]
            );
            
            if (!$category) {
                header('Location: categories.php');
                exit;
            }
        } catch (Exception $e) {
            $error = '获取分类信息失败: ' . $e->getMessage();
        }
        
        // 处理表单提交
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
            
            // 验证输入
            if (empty($title)) {
                $error = '分类标题不能为空';
            } else {
                try {
                    // 检查分类标题是否已存在
                    $exists = $db->fetchColumn(
                        "SELECT COUNT(*) FROM `{$prefix}categories` WHERE `title` = :title AND `id` != :id",
                        ['title' => $title, 'id' => $category_id]
                    );
                    
                    if ($exists > 0) {
                        $error = '分类标题已存在';
                    } else {
                        // 更新分类信息
                        $db->update("{$prefix}categories", [
                            'title' => $title,
                            'description' => $description,
                            'sort_order' => $sort_order,
                            'updated_at' => date('Y-m-d H:i:s')
                        ], '`id` = :id', ['id' => $category_id]);
                        
                        // 记录操作日志
                        logAdminAction('管理员编辑分类', 'category', $category_id, [
                            'title' => $title,
                            'old_title' => $category['title'],
                            'description' => $description,
                            'sort_order' => $sort_order,
                            'admin_id' => $_SESSION['user_id'],
                            'admin_username' => $_SESSION['username']
                        ]);
                        
                        $success = '分类信息更新成功';
                        
                        // 重新获取分类信息
                        $category = $db->fetch(
                            "SELECT * FROM `{$prefix}categories` WHERE `id` = :id",
                            ['id' => $category_id]
                        );
                    }
                } catch (Exception $e) {
                    $error = '更新分类信息失败: ' . $e->getMessage();
                }
            }
        }
        
        // 设置页面标题
        $page_title = '编辑分类';
        
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
                                <h1>编辑分类</h1>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="right">
                                <a href="categories.php">返回分类列表</a>
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
                                <form method="post" action="categories.php?action=edit&id=<?php echo $category_id; ?>">
                                    <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                        <tr>
                                            <td width="20%">分类标题</td>
                                            <td>
                                                <input type="text" name="title" value="<?php echo htmlspecialchars($category['title']); ?>" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>分类描述</td>
                                            <td>
                                                <textarea name="description" rows="3"><?php echo htmlspecialchars($category['description']); ?></textarea>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>排序顺序</td>
                                            <td>
                                                <input type="number" name="sort_order" value="<?php echo (int)$category['sort_order']; ?>">
                                                <br>
                                                <small>数字越小排序越靠前</small>
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
        // 删除分类
        $category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($category_id <= 0) {
            header('Location: categories.php');
            exit;
        }
        
        try {
            // 获取分类信息
            $category = $db->fetch(
                "SELECT * FROM `{$prefix}categories` WHERE `id` = :id",
                ['id' => $category_id]
            );
            
            if (!$category) {
                header('Location: categories.php');
                exit;
            }
            
            // 检查分类是否有主题
            $topic_count = $db->fetchColumn(
                "SELECT COUNT(*) FROM `{$prefix}topics` WHERE `category_id` = :category_id",
                ['category_id' => $category_id]
            );
            
            if ($topic_count > 0) {
                header('Location: categories.php?error=' . urlencode('无法删除分类，该分类下还有主题'));
                exit;
            }
            
            // 删除分类
            $db->delete("{$prefix}categories", '`id` = :id', ['id' => $category_id]);
            
            // 记录操作日志
            logAdminAction('管理员删除分类', 'category', $category_id, [
                'title' => $category['title'],
                'description' => $category['description'],
                'sort_order' => $category['sort_order'],
                'topic_count' => $topic_count,
                'admin_id' => $_SESSION['user_id'],
                'admin_username' => $_SESSION['username']
            ]);
            
            header('Location: categories.php?success=' . urlencode('分类删除成功'));
            exit;
        } catch (Exception $e) {
            header('Location: categories.php?error=' . urlencode('删除分类失败: ' . $e->getMessage()));
            exit;
        }
        break;
        
    default:
        // 分类列表
        try {
            $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
            
            if ($storage_type === 'json') {
                // JSON存储：使用简单查询
                $categories = $db->select('categories', [], 'sort_order ASC');
                
                // 获取每个分类的主题数
                $all_topics = $db->select('topics');
                $topic_counts = [];
                foreach ($all_topics as $topic) {
                    $cat_id = $topic['category_id'];
                    if (!isset($topic_counts[$cat_id])) {
                        $topic_counts[$cat_id] = 0;
                    }
                    $topic_counts[$cat_id]++;
                }
                
                // 添加主题数到分类
                foreach ($categories as &$cat) {
                    $cat['topic_count'] = isset($topic_counts[$cat['id']]) ? $topic_counts[$cat['id']] : 0;
                }
                unset($cat);
            } else {
                // MySQL存储：使用子查询
                $categories = $db->fetchAll(
                    "SELECT c.*, 
                    (SELECT COUNT(*) FROM `{$prefix}topics` WHERE `category_id` = c.id) as topic_count 
                    FROM `{$prefix}categories` c 
                    ORDER BY c.sort_order ASC, c.id ASC"
                );
            }
        } catch (Exception $e) {
            $error = '获取分类列表失败: ' . $e->getMessage();
        }
        
        // 获取成功或错误消息
        if (isset($_GET['success'])) {
            $success = $_GET['success'];
        }
        
        if (isset($_GET['error'])) {
            $error = $_GET['error'];
        }
        
        // 设置页面标题
        $page_title = '分类管理';
        
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
                                <h1>分类管理</h1>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="right">
                                <a href="categories.php?action=add">添加分类</a>
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
                                        <th>ID</th>
                                        <th>标题</th>
                                        <th>描述</th>
                                        <th>主题数</th>
                                        <th>排序</th>
                                        <th>创建时间</th>
                                        <th>操作</th>
                                    </tr>
                                    <?php if (isset($categories) && count($categories) > 0): ?>
                                        <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td><?php echo $category['id'] ?? ''; ?></td>
                                                <td><?php echo htmlspecialchars($category['title'] ?? ''); ?></td>
                                                <td><?php 
                                                    $desc = $category['description'] ?? '';
                                                    echo htmlspecialchars(mb_substr($desc, 0, 50)) . (mb_strlen($desc) > 50 ? '...' : ''); 
                                                ?></td>
                                                <td><?php echo $category['topic_count'] ?? 0; ?></td>
                                                <td><?php echo $category['sort_order'] ?? 0; ?></td>
                                                <td><?php echo formatDateTime($category['created_at'] ?? null); ?></td>
                                                <td>
                                                    <a href="categories.php?action=edit&id=<?php echo $category['id'] ?? ''; ?>">编辑</a>
                                                    <?php if (($category['topic_count'] ?? 0) == 0): ?>
                                                        <a href="categories.php?action=delete&id=<?php echo $category['id'] ?? ''; ?>" class="confirm-delete">删除</a>
                                                    <?php else: ?>
                                                        <span title="无法删除，该分类下还有主题">删除</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" align="center">没有找到分类</td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
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
}
?>

