<?php
    date_default_timezone_set('Asia/Shanghai');
/**
 * 友链管理页面
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

// 处理友链操作
switch ($action) {
    case 'add':
        // 添加友链
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = $_POST['name'] ?? '';
            $url = $_POST['url'] ?? '';
            $description = $_POST['description'] ?? '';
            $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
            $status = isset($_POST['status']) ? 1 : 0;
            
            // 验证输入
            if (empty($name) || empty($url)) {
                $error = '友链名称和URL不能为空';
            } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
                $error = '请输入有效的URL地址';
            } else {
                try {
                    // 检查友链名称是否已存在
                    $exists = $db->fetchColumn(
                        "SELECT COUNT(*) FROM `{$prefix}links` WHERE `name` = :name",
                        ['name' => $name]
                    );
                    
                    if ($exists > 0) {
                        $error = '友链名称已存在';
                    } else {
                        // 创建友链
                        $db->insert("{$prefix}links", [
                            'name' => $name,
                            'url' => $url,
                            'description' => $description,
                            'sort_order' => $sort_order,
                            'status' => $status,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        // 记录操作日志
                        logAdminAction('管理员添加友链', 'link', $db->lastInsertId(), [
                            'name' => $name,
                            'url' => $url,
                            'description' => $description,
                            'sort_order' => $sort_order,
                            'status' => $status,
                            'admin_id' => $_SESSION['user_id'],
                            'admin_username' => $_SESSION['username'],
                            'action_time' => date('Y-m-d H:i:s'),
                            'ip_address' => getClientIp()
                        ]);
                        
                        $success = '友链添加成功';
                        
                        // 重定向到友链列表
                        header('Location: links.php?success=' . urlencode($success));
                        exit;
                    }
                } catch (Exception $e) {
                    $error = '添加友链失败: ' . $e->getMessage();
                }
            }
        }
        
        // 设置页面标题
        $page_title = '添加友链';
        
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
                                <h1>添加友链</h1>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="right">
                                <a href="links.php">返回友链列表</a>
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
                                <form method="post" action="links.php?action=add">
                                    <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                        <tr>
                                            <td width="20%">友链名称</td>
                                            <td>
                                                <input type="text" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>友链URL</td>
                                            <td>
                                                <input type="url" name="url" value="<?php echo isset($_POST['url']) ? htmlspecialchars($_POST['url']) : ''; ?>" required>
                                                <div>请输入完整的URL，包括http://或https://</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>友链描述</td>
                                            <td>
                                                <textarea name="description" rows="3"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>排序顺序</td>
                                            <td>
                                                <input type="number" name="sort_order" value="<?php echo isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0; ?>">
                                                <div>数字越小排序越靠前</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>状态</td>
                                            <td>
                                                <input type="checkbox" name="status" checked>
                                                启用友链
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <button type="submit">添加友链</button>
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
        // 编辑友链
        $link_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($link_id <= 0) {
            header('Location: links.php');
            exit;
        }
        
        // 获取友链信息
        try {
            $link = $db->fetch(
                "SELECT * FROM `{$prefix}links` WHERE `id` = :id",
                ['id' => $link_id]
            );
            
            if (!$link) {
                header('Location: links.php');
                exit;
            }
        } catch (Exception $e) {
            $error = '获取友链信息失败: ' . $e->getMessage();
        }
        
        // 处理表单提交
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = $_POST['name'] ?? '';
            $url = $_POST['url'] ?? '';
            $description = $_POST['description'] ?? '';
            $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
            $status = isset($_POST['status']) ? 1 : 0;
            
            // 验证输入
            if (empty($name) || empty($url)) {
                $error = '友链名称和URL不能为空';
            } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
                $error = '请输入有效的URL地址';
            } else {
                try {
                    // 检查友链名称是否已存在（排除当前ID）
                    $exists = $db->fetchColumn(
                        "SELECT COUNT(*) FROM `{$prefix}links` WHERE `name` = :name AND `id` != :id",
                        ['name' => $name, 'id' => $link_id]
                    );
                    
                    if ($exists > 0) {
                        $error = '友链名称已存在';
                    } else {
                        // 更新友链信息
                        $db->update("{$prefix}links", [
                            'name' => $name,
                            'url' => $url,
                            'description' => $description,
                            'sort_order' => $sort_order,
                            'status' => $status,
                            'updated_at' => date('Y-m-d H:i:s')
                        ], '`id` = :id', ['id' => $link_id]);
                        
                        // 记录操作日志
                        logAdminAction('管理员编辑友链', 'link', $link_id, [
                            'name' => $name,
                            'old_name' => $link['name'],
                            'url' => $url,
                            'old_url' => $link['url'],
                            'description' => $description,
                            'old_description' => $link['description'],
                            'sort_order' => $sort_order,
                            'old_sort_order' => $link['sort_order'],
                            'status' => $status,
                            'old_status' => $link['status'],
                            'admin_id' => $_SESSION['user_id'],
                            'admin_username' => $_SESSION['username'],
                            'action_time' => date('Y-m-d H:i:s'),
                            'ip_address' => getClientIp()
                        ]);
                        
                        $success = '友链信息更新成功';
                    }
                } catch (Exception $e) {
                    $error = '更新友链信息失败: ' . $e->getMessage();
                }
            }
        }
        
        // 设置页面标题
        $page_title = '编辑友链';
        
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
                                <h1>编辑友链</h1>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="right">
                                <a href="links.php">返回友链列表</a>
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
                                <form method="post" action="links.php?action=edit&id=<?php echo $link_id; ?>">
                                    <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                        <tr>
                                            <td width="20%">友链名称</td>
                                            <td>
                                                <input type="text" name="name" value="<?php echo htmlspecialchars($link['name']); ?>" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>友链URL</td>
                                            <td>
                                                <input type="url" name="url" value="<?php echo htmlspecialchars($link['url']); ?>" required>
                                                <div>请输入完整的URL，包括http://或https://</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>友链描述</td>
                                            <td>
                                                <textarea name="description" rows="3"><?php echo htmlspecialchars($link['description']); ?></textarea>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>排序顺序</td>
                                            <td>
                                                <input type="number" name="sort_order" value="<?php echo (int)$link['sort_order']; ?>">
                                                <div>数字越小排序越靠前</div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>状态</td>
                                            <td>
                                                <input type="checkbox" name="status" <?php echo $link['status'] ? 'checked' : ''; ?>>
                                                启用友链
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
        // 删除友链
        $link_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($link_id <= 0) {
            header('Location: links.php');
            exit;
        }
        
        try {
            // 获取友链信息
            $link = $db->fetch(
                "SELECT * FROM `{$prefix}links` WHERE `id` = :id",
                ['id' => $link_id]
            );
            
            if (!$link) {
                header('Location: links.php');
                exit;
            }
            
            // 删除友链
            $db->delete("{$prefix}links", '`id` = :id', ['id' => $link_id]);
            
            // 记录操作日志
            logAdminAction('管理员删除友链', 'link', $link_id, [
                'name' => $link['name'],
                'url' => $link['url'],
                'description' => $link['description'],
                'sort_order' => $link['sort_order'],
                'status' => $link['status'],
                'admin_id' => $_SESSION['user_id'],
                'admin_username' => $_SESSION['username'],
                'action_time' => date('Y-m-d H:i:s'),
                'ip_address' => getClientIp()
            ]);
            
            header('Location: links.php?success=' . urlencode('友链删除成功'));
            exit;
        } catch (Exception $e) {
            header('Location: links.php?error=' . urlencode('删除友链失败: ' . $e->getMessage()));
            exit;
        }
        break;
        
    default:
        // 友链列表
        try {
            $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
            
            if ($storage_type === 'json') {
                // JSON存储：使用简单查询
                $links = $db->select('links', [], 'sort_order ASC');
            } else {
                // MySQL存储
                $links = $db->fetchAll(
                    "SELECT * FROM `{$prefix}links` ORDER BY `sort_order` ASC, `id` ASC"
                );
            }
        } catch (Exception $e) {
            $error = '获取友链列表失败: ' . $e->getMessage();
        }
        
        // 获取成功或错误消息
        if (isset($_GET['success'])) {
            $success = $_GET['success'];
        }
        
        if (isset($_GET['error'])) {
            $error = $_GET['error'];
        }
        
        // 设置页面标题
        $page_title = '友链管理';
        
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
                                <h1>友链管理</h1>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="right">
                                <a href="links.php?action=add">添加友链</a>
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
                                        <th>名称</th>
                                        <th>URL</th>
                                        <th>描述</th>
                                        <th>排序</th>
                                        <th>状态</th>
                                        <th>创建时间</th>
                                        <th>操作</th>
                                    </tr>
                                    <?php if (isset($links) && count($links) > 0): ?>
                                        <?php foreach ($links as $link): ?>
                                            <tr>
                                                <td><?php echo $link['id']; ?></td>
                                                <td><?php echo htmlspecialchars($link['name']); ?></td>
                                                <td><a href="<?php echo $link['url']; ?>" target="_blank"><?php echo htmlspecialchars($link['url']); ?></a></td>
                                                <td><?php echo htmlspecialchars(mb_substr($link['description'], 0, 50)) . (mb_strlen($link['description']) > 50 ? '...' : ''); ?></td>
                                                <td><?php echo $link['sort_order']; ?></td>
                                                <td>
                                                    <?php if (($link['status'] ?? 0) == 1): ?>
                                                        启用
                                                    <?php else: ?>
                                                        禁用
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatDateTime($link['created_at']); ?></td>
                                                <td>
                                                    <a href="links.php?action=edit&id=<?php echo $link['id']; ?>">编辑</a>
                                                    <a href="links.php?action=delete&id=<?php echo $link['id']; ?>" class="confirm-delete">删除</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" align="center">没有找到友链</td>
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

<script>
// 确认删除提示
$(document).ready(function() {
    $('.confirm-delete').click(function(e) {
        if (!confirm('确定要删除这个友链吗？')) {
            e.preventDefault();
        }
    });
});
</script>