<?php
/**
 * 分类页面 - 支持伪静态URL
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

// 获取分类ID
$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($category_id <= 0) {
    header('Location: ' . getHomeUrl());
    exit;
}

// 获取页码
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

// 每页显示的主题数
$topics_per_page = (int)getSetting('topics_per_page', 20);

// 获取分类信息和主题列表
try {
    $db = getDB();
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
    
    // 获取分类信息
    $category = $db->fetch(
        "SELECT * FROM `{$prefix}categories` WHERE `id` = :id",
        ['id' => $category_id]
    );
    
    if (!$category) {
        header('Location: ' . getHomeUrl());
        exit;
    }
    
    // 获取该分类下的主题总数
    if (isset($_SESSION['user_id'])) {
        // 允许用户查看自己被隐藏的主题
        $total_topics = $db->fetchColumn(
            "SELECT COUNT(*) FROM `{$prefix}topics` WHERE `category_id` = :category_id AND (status = 'published' OR (status = 'hidden' AND user_id = :user_id))",
            ['category_id' => $category_id, 'user_id' => $_SESSION['user_id']]
        );
    } else {
        // 未登录用户只能查看已发布的主题
        $total_topics = $db->fetchColumn(
            "SELECT COUNT(*) FROM `{$prefix}topics` WHERE `category_id` = :category_id AND `status` = 'published'",
            ['category_id' => $category_id]
        );
    }
    
    // 计算总页数
    $total_pages = ceil($total_topics / $topics_per_page);
    
    // 获取当前页的主题列表
    $offset = ($page - 1) * $topics_per_page;
    
    if ($storage_type === 'json') {
        // JSON存储：使用简单查询
        if (isset($_SESSION['user_id'])) {
            // 允许用户查看自己被隐藏的主题
            $all_topics = $db->select('topics', ['category_id' => $category_id]);
            $topics = [];
            foreach ($all_topics as $topic) {
                if ($topic['status'] === 'published' || ($topic['status'] === 'hidden' && $topic['user_id'] == $_SESSION['user_id'])) {
                    $topics[] = $topic;
                }
            }
            // 排序
            usort($topics, function($a, $b) {
                if ($a['is_sticky'] != $b['is_sticky']) {
                    return $b['is_sticky'] - $a['is_sticky'];
                }
                return strtotime($b['last_post_time']) - strtotime($a['last_post_time']);
            });
            // 分页
            $topics = array_slice($topics, $offset, $topics_per_page);
        } else {
            // 未登录用户只能查看已发布的主题
            $topics = $db->select('topics', ['category_id' => $category_id, 'status' => 'published'], 'is_sticky DESC, last_post_time DESC', $topics_per_page, $offset);
        }
        
        // 获取用户信息
        $users = [];
        $all_users = $db->select('users');
        foreach ($all_users as $u) {
            $users[$u['id']] = $u;
        }
        
        // 关联用户数据
        foreach ($topics as &$topic) {
            $topic['username'] = isset($users[$topic['user_id']]) ? $users[$topic['user_id']]['username'] : '未知用户';
            $topic['last_post_username'] = '';
            if (!empty($topic['last_post_user_id']) && isset($users[$topic['last_post_user_id']])) {
                $topic['last_post_username'] = $users[$topic['last_post_user_id']]['username'];
            }
        }
        unset($topic);
    } else {
        // MySQL存储：使用JOIN查询
        if (isset($_SESSION['user_id'])) {
            // 允许用户查看自己被隐藏的主题
            $topics = $db->fetchAll(
                "SELECT t.*, u.username, lu.username as last_post_username
                FROM `{$prefix}topics` t 
                JOIN `{$prefix}users` u ON t.user_id = u.id 
                LEFT JOIN `{$prefix}users` lu ON t.last_post_user_id = lu.id
                WHERE t.category_id = :category_id AND (t.status = 'published' OR (t.status = 'hidden' AND t.user_id = :user_id)) 
                ORDER BY t.is_sticky DESC, t.last_post_time DESC 
                LIMIT :offset, :limit",
                [
                    'category_id' => $category_id,
                    'user_id' => $_SESSION['user_id'],
                    'offset' => $offset,
                    'limit' => $topics_per_page
                ]
            );
        } else {
            // 未登录用户只能查看已发布的主题
            $topics = $db->fetchAll(
                "SELECT t.*, u.username, lu.username as last_post_username
                FROM `{$prefix}topics` t 
                JOIN `{$prefix}users` u ON t.user_id = u.id 
                LEFT JOIN `{$prefix}users` lu ON t.last_post_user_id = lu.id
                WHERE t.category_id = :category_id AND t.status = 'published' 
                ORDER BY t.is_sticky DESC, t.last_post_time DESC 
                LIMIT :offset, :limit",
                [
                    'category_id' => $category_id,
                    'offset' => $offset,
                    'limit' => $topics_per_page
                ]
            );
        }
    }
    
} catch (Exception $e) {
    $error = '加载分类信息失败: ' . $e->getMessage();
}

// 设置页面标题
$page_title = isset($category) ? $category['title'] . ($page > 1 ? ' - 第' . $page . '页' : '') : '分类详情';

// 加载页面头部
include __DIR__ . '/templates/header.php';
?>

<table border="1" width="100%" cellspacing="0" cellpadding="10">
    <?php if (isset($error)): ?>
        <tr>
            <td><?php echo $error; ?></td>
        </tr>
    <?php else: ?>
        <tr>
            <td colspan="2">
                <table width="100%" cellspacing="0" cellpadding="0">
                    <tr>
                        <td><h1><?php echo htmlspecialchars($category['title']); ?></h1></td>
                        <td align="right">
                            <a href="<?php echo getHomeUrl(); ?>">首页</a> &gt; 
                            <a href="<?php echo getCategoriesUrl(); ?>">分类列表</a> &gt; 
                            <?php echo htmlspecialchars($category['title']); ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        
        <?php if (!empty($category['description'])): ?>
            <tr>
                <td colspan="2"><?php echo nl2br(htmlspecialchars($category['description'])); ?></td>
            </tr>
        <?php endif; ?>
        
        <tr>
            <td>共 <?php echo $total_topics; ?> 个主题</td>
            <td align="right">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo getNewTopicUrl($category_id); ?>">发布新主题</a>
                <?php endif; ?>
            </td>
        </tr>
        
        <?php if (empty($topics)): ?>
            <tr>
                <td colspan="2">该分类下暂无主题</td>
            </tr>
        <?php else: ?>
            <tr>
                <td colspan="2">
                    <table border="1" width="100%" cellspacing="0" cellpadding="5">
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
                                        <?php if (isset($topic['status']) && $topic['status'] === 'hidden'): ?>
                                            <span style="font-weight: bold; color: white; background-color: gray; padding: 2px 5px; border-radius: 3px;">[已隐藏] </span>
                                        <?php endif; ?>
                                        <?php if ($topic['is_sticky']): ?>
                                            [置顶] 
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($topic['title']); ?>
                                    </a>
                                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $topic['user_id'] && isset($topic['status']) && $topic['status'] === 'hidden'): ?>
                                        <a href="javascript:void(0);" onclick="openAppealModal('topic', <?php echo $topic['id']; ?>, '<?php echo addslashes($topic['title']); ?>')" style="display: inline-block; margin-left: 10px; padding: 2px 5px; background-color: #ff9800; color: white; border-radius: 3px; font-size: 0.8em; text-decoration: none;">申诉</a>
                                    <?php endif; ?>
                                    <br>
                                    <small>发布时间: <?php echo formatDateTime($topic['created_at'], 'Y-m-d'); ?> | 
                                    作者: <a href="<?php echo getUserProfileUrl($topic['user_id'], $topic['username']); ?>"><?php echo htmlspecialchars($topic['username']); ?></a> | 
                                    浏览: <?php echo $topic['view_count']; ?>
                                    <?php if ($topic['last_post_time'] && $topic['last_post_time'] != $topic['created_at']): ?>
                                        | 最后回复: <a href="<?php echo getUserProfileUrl($topic['last_post_user_id'], $topic['last_post_username']); ?>"><?php echo htmlspecialchars($topic['last_post_username']); ?></a> (<?php echo formatDateTime($topic['last_post_time'], 'm-d H:i'); ?>)
                                    <?php endif; ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </td>
            </tr>
            
            <?php if ($total_pages > 1): ?>
                <tr>
                    <td colspan="2" align="center">
                        <?php 
                            $pagination_url = getPaginationUrlPattern('category.php', ['id' => $category_id]);
                            echo generatePagination($page, $total_pages, $pagination_url); 
                        ?>
                    </td>
                </tr>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</table>

<?php
// 加载页面底部
include __DIR__ . '/templates/footer.php';
?>

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
</script>
