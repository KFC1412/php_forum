<?php
/**
 * 编辑主题页面
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

// 检查是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . getLoginUrl());
    exit;
}

// 加载配置和函数
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mail_functions.php';

// 检查安装状态和闭站模式
checkInstall();

// 获取主题ID
$topic_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($topic_id <= 0) {
    header('Location: ' . getHomeUrl());
    exit;
}

try {
    $db = getDB();
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
    
    // 获取所有分类
    $categories = $db->fetchAll("SELECT * FROM `{$prefix}categories` ORDER BY `sort_order` ASC");
    
    // 获取主题信息
    if ($storage_type === 'json') {
        // JSON存储：使用简单查询
        $topic = $db->findById('topics', $topic_id);
        
        if (!$topic) {
            header('Location: ' . getHomeUrl());
            exit;
        }
        
        // 获取分类信息
        $category = $db->findById('categories', $topic['category_id']);
        $topic['category_title'] = $category ? $category['title'] : '未知分类';
    } else {
        // MySQL存储：使用JOIN查询
        $topic = $db->fetch(
            "SELECT t.*, c.title AS category_title 
             FROM `{$prefix}topics` t 
             JOIN `{$prefix}categories` c ON t.category_id = c.id 
             WHERE t.id = :id",
            ['id' => $topic_id]
        );
    }
    
    if (!$topic) {
        header('Location: ' . getHomeUrl());
        exit;
    }
    
    // 检查当前用户是否有权限编辑该主题
    if ($topic['user_id'] != $_SESSION['user_id'] && !isAdmin()) {
        header('Location: ' . getTopicUrl($topic_id));
        exit;
    }
    
} catch (Exception $e) {
    $error = '加载主题信息失败: ' . $e->getMessage();
}

// 处理表单提交
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    
    // 验证输入
    if (empty($title) || empty($content) || $category_id <= 0) {
        $error = '请填写所有必填字段';
    } else if (strlen($title) < 1 || strlen($title) > 100) {
        $error = '标题长度必须在1-100个字符之间';
    } else if (strlen($content) < 1) {
        $error = '内容长度必须至少为1个字符';
    } else {
        try {
            // 检查分类是否存在
            $category = $db->fetch(
                "SELECT * FROM `{$prefix}categories` WHERE `id` = :id",
                ['id' => $category_id]
            );
            
            if (!$category) {
                $error = '所选分类不存在';
            } else {
                // 更新主题
                $db->update(
                    "{$prefix}topics",
                    [
                        'category_id' => $category_id,
                        'title' => $title,
                        'content' => $content,
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    'id = :id',
                    ['id' => $topic_id]
                );

                // 记录编辑主题日志
                logAction('用户编辑主题', 'topic', $topic_id, [
                    'title' => $title,
                    'old_title' => $topic['title'],
                    'category_id' => $category_id,
                    'old_category_id' => $topic['category_id'],
                    'content_length' => mb_strlen($content),
                    'edit_time' => date('Y-m-d H:i:s'),
                    'edit_ip' => getClientIp()
                ]);
                
                // 发送邮件通知（如果编辑者不是主题作者）
                if ($topic['user_id'] != $_SESSION['user_id']) {
                    sendTopicEditedNotification($topic_id, $_SESSION['user_id']);
                }
                
                $success = '主题更新成功';
                
                // 重定向到主题页面
                header('Location: ' . getTopicUrl($topic_id, null, $title));
                exit;
            }
        } catch (Exception $e) {
            $error = '更新主题失败: ' . $e->getMessage();
        }
    }
}

// 设置页面标题
$page_title = '编辑主题: ' . htmlspecialchars($topic['title']);

// 加载页面头部
include __DIR__ . '/templates/header.php';
?>

<table border="1" width="100%" cellspacing="0" cellpadding="5">
    <!-- 编辑主题标题 -->
    <tr>
        <td colspan="2" style="font-weight: bold; font-size: 16px;">
            编辑主题: <?php echo htmlspecialchars($topic['title']); ?>
        </td>
    </tr>
    
    <!-- 导航路径 -->
    <tr>
        <td colspan="2" style="padding: 5px;">
            <a href="<?php echo getHomeUrl(); ?>">首页</a> > 
            <a href="<?php echo getCategoryUrl($topic['category_id'], null, $topic['category_title']); ?>"><?php echo htmlspecialchars($topic['category_title']); ?></a> > 
            <a href="<?php echo getTopicUrl($topic_id, null, $topic['title']); ?>"><?php echo htmlspecialchars($topic['title']); ?></a> > 
            编辑主题
        </td>
    </tr>
    
    <!-- 错误信息 -->
    <?php if (!empty($error)): ?>
        <tr>
            <td colspan="2" class="error"><?php echo $error; ?></td>
        </tr>
    <?php endif; ?>
    
    <!-- 成功信息 -->
    <?php if (!empty($success)): ?>
        <tr>
            <td colspan="2" class="success"><?php echo $success; ?></td>
        </tr>
    <?php endif; ?>
    
    <!-- 编辑表单 -->
    <tr>
        <td colspan="2">
            <form method="post" action="<?php echo getEditTopicUrl($topic_id); ?>">
                <table border="1" width="100%" cellspacing="0" cellpadding="5">
                    <!-- 分类选择 -->
                    <tr>
                        <td width="20%" style="padding: 10px;">
                            <strong>选择分类</strong>
                        </td>
                        <td width="80%" style="padding: 10px;">
                            <select name="category_id" required style="width: 100%; padding: 4px;">
                                <option value="">-- 请选择分类 --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $topic['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <!-- 主题标题 -->
                    <tr>
                        <td width="20%" style="padding: 10px;">
                            <strong>主题标题</strong>
                        </td>
                        <td width="80%" style="padding: 10px;">
                            <input type="text" name="title" required minlength="1" maxlength="100" value="<?php echo htmlspecialchars($topic['title']); ?>" style="width: 100%; padding: 4px;">
                            <br>
                            <small style="color: #666;">标题长度必须在1-100个字符之间</small>
                        </td>
                    </tr>
                    
                    <!-- 主题内容 -->
                    <tr>
                        <td width="20%" style="padding: 10px; vertical-align: top;">
                            <strong>主题内容</strong>
                        </td>
                        <td width="80%" style="padding: 10px;">
                            <textarea id="content" name="content" rows="10" required minlength="1" style="width: 100%; padding: 4px;"><?php echo htmlspecialchars($topic['content']); ?></textarea>
                            <br>
                            <small style="color: #666;">内容长度必须至少为1个字符</small>
                        </td>
                    </tr>
                    
                    <!-- 提交按钮 -->
                    <tr>
                        <td colspan="2" style="padding: 10px;">
                            <table border="1" width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse;">
                                <tr>
                                    <td style="border: 1px solid #ddd; padding: 3px; width: 50%;">
                                        <button type="submit" style="width: 100%; padding: 3px 6px; background-color: #f0f0f0; border: none; text-decoration: none; border-radius: 0; cursor: pointer; text-align: center; color: blue; font-size: 12px;">保存修改</button>
                                    </td>
                                    <td style="border: 1px solid #ddd; padding: 3px; width: 50%;">
                                        <a href="<?php echo getTopicUrl($topic_id, null, $topic['title']); ?>" style="display: block; width: 100%; padding: 3px 6px; background-color: #f0f0f0; border: none; text-decoration: none; border-radius: 0; cursor: pointer; text-align: center; color: blue; font-size: 12px;">取消</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </form>
        </td>
    </tr>
</table>
<!-- ice -->
<script type="text/JavaScript" src="./assets/src/iceEditor.js"></script>
<!-- 编辑器脚本 -->
<script>
// 初始化编辑器
function initEditor() {
    //自定义编辑器菜单
    ice.editor("content",function(e){
        this.uploadUrl = "assets/src/upload/php-upload.php";
        this.pasteText = false;
        this.screenshot = true;
        this.screenshotUpload = true;
        this.menu = [
            'backColor', 'fontSize', 'foreColor', 'bold', 'italic', 'underline', 'strikeThrough', 'line', 'justifyLeft',
            'justifyCenter', 'justifyRight', 'indent', 'outdent', 'line', 'insertOrderedList', 'insertUnorderedList', 'line', 'createLink', 'unlink', 'line', 'hr', 'face', 'table', 'files', 'music', 'video', 'insertImage',
            'removeFormat', 'paste', 'line', 'code'
        ];
        this.create();
    })
}

// 确保DOM加载完成后再初始化编辑器
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEditor);
} else {
    initEditor();
}
</script>
<?php
// 加载页面底部
include __DIR__ . '/templates/footer.php';
?>
