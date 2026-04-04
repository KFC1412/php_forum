<?php
    date_default_timezone_set('Asia/Shanghai');
/**
 * 新主题页面 - 支持伪静态URL
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

// 检查是否已登录
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = getNewTopicUrl();
    header('Location: ' . getLoginUrl());
    exit;
}

// 检查用户状态
if (isset($_SESSION['status']) && $_SESSION['status'] === 'restricted') {
    $_SESSION['error'] = '您的账号已被限制，无法发布新主题';
    header('Location: ' . getHomeUrl());
    exit;
}

// 获取预选分类ID
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

try {
    $db = getDB();
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    
    // 获取所有分类
    $categories = $db->fetchAll("SELECT * FROM `{$prefix}categories` ORDER BY `sort_order` ASC");
    
} catch (Exception $e) {
    $error = '加载分类列表失败: ' . $e->getMessage();
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
                // 获取IP地址和详细信息
                $ip_address = getClientIp();
                $ip_info = getIpInfo($ip_address);
                
                // 创建主题
                $db->insert("{$prefix}topics", [
                    'category_id' => $category_id,
                    'user_id' => $_SESSION['user_id'],
                    'title' => $title,
                    'content' => $content,
                    'status' => 'published',
                    'is_sticky' => 0,
                    'is_locked' => 0,
                    'view_count' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'last_post_id' => null,
                    'last_post_user_id' => $_SESSION['user_id'],
                    'last_post_time' => date('Y-m-d H:i:s'),
                    'created_ip' => $ip_address,
                    'ip_info' => $ip_info ? json_encode($ip_info) : null
                ]);
                
                $topic_id = $db->lastInsertId();
                
                // 处理标签
                if (isset($_POST['tags']) && !empty($_POST['tags'])) {
                    $tags_input = $_POST['tags'];
                    $tags_array = array_map('trim', explode(',', $tags_input));
                    // 过滤空标签和过长的标签
                    $tags_array = array_filter($tags_array, function($tag) {
                        return !empty($tag) && strlen($tag) <= 20;
                    });
                    // 限制标签数量
                    $tags_array = array_slice($tags_array, 0, 5);
                    // 添加标签
                    if (!empty($tags_array)) {
                        addTopicTags($topic_id, $tags_array);
                    }
                }
                
                // 记录创建主题日志
                logAction('用户发布新主题', 'topic', $topic_id, [
                    'title' => $title,
                    'category_id' => $category_id,
                    'category_title' => $category['title'],
                    'content_length' => mb_strlen($content),
                    'create_time' => date('Y-m-d H:i:s'),
                    'create_ip' => getClientIp()
                ]);
                
                $success = '主题创建成功';
                
                // 重定向到主题页面
                header('Location: ' . getTopicUrl($topic_id, null, $title));
                exit;
            }
        } catch (Exception $e) {
            $error = '创建主题失败: ' . $e->getMessage();
        }
    }
}

// 设置页面标题
$page_title = '发布新主题';

// 加载页面头部
include __DIR__ . '/templates/header.php';
?>

<table border="1" width="100%" cellspacing="0" cellpadding="5">
    <!-- 发布新主题标题 -->
    <tr>
        <td colspan="2" style="font-weight: bold; font-size: 16px;">
            发布新主题
        </td>
    </tr>
    
    <!-- 导航路径 -->
    <tr>
        <td colspan="2" style="padding: 5px;">
            <a href="<?php echo getHomeUrl(); ?>">首页</a> > 发布新主题
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
    
    <!-- 发布表单 -->
    <tr>
        <td colspan="2">
            <form method="post" action="<?php echo getNewTopicUrl(); ?>">
                <table border="1" width="100%" cellspacing="0" cellpadding="5">
                    <!-- 分类选择 -->
                    <tr>
                        <td width="20%" style="padding: 10px;">
                            <strong>选择分类</strong>
                        </td>
                        <td width="80%" style="padding: 10px;">
                            <select id="category_id" name="category_id" required style="width: 100%; padding: 4px;">
                                <option value="">-- 请选择分类 --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
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
                            <input type="text" id="title" name="title" required minlength="1" maxlength="100" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" style="width: 100%; padding: 4px;">
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
                            <textarea id="content" name="content" rows="10" required minlength="1" style="width: 100%; padding: 4px;"><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
                            <br>
                            <small style="color: #666;">内容长度必须至少为1个字符</small>
                        </td>
                    </tr>
                    
                    <!-- 主题标签 -->
                    <tr>
                        <td width="20%" style="padding: 10px;">
                            <strong>主题标签</strong>
                        </td>
                        <td width="80%" style="padding: 10px;">
                            <input type="text" id="tags" name="tags" placeholder="请输入标签，用逗号分隔" style="width: 100%; padding: 4px;">
                            <br>
                            <small style="color: #666;">多个标签用逗号分隔，每个标签不超过20个字符</small>
                        </td>
                    </tr>
                    
                    <!-- 提交按钮 -->
                    <tr>
                        <td colspan="2" align="center" style="padding: 10px;">
                            <button type="submit" onclick="return validateForm()" style="padding: 6px 12px; background-color: #f0f0f0; border: 1px solid #ddd; text-decoration: none; border-radius: 2px; cursor: pointer;">发布主题</button>
                        </td>
                    </tr>
                </table>
                
                <script>
                function validateForm() {
                    // 同步编辑器内容
                    if (contentEditor) {
                        contentEditor.setTextarea();
                    }
                    
                    // 验证分类
                    var category = document.getElementById('category_id');
                    if (!category.value) {
                        alert('请选择分类');
                        category.focus();
                        return false;
                    }
                    
                    // 验证标题
                    var title = document.getElementById('title');
                    if (!title.value.trim()) {
                        alert('请填写主题标题');
                        title.focus();
                        return false;
                    }
                    
                    // 验证内容
                    var content = document.getElementById('content');
                    if (!content.value.trim()) {
                        alert('请填写主题内容');
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
    //自定义编辑器菜单
    ice.editor("content",function(e){
        // 存储编辑器实例
        contentEditor = this;
        this.uploadUrl = "assets/src/upload/php-upload.php";
        this.pasteText = false;
        this.screenshot = true;
        this.screenshotUpload = true;
        this.height = '250px'; // 设置编辑器高度
        this.menu = [
            'backColor', 'fontSize', 'foreColor', 'bold', 'italic', 'underline', 'strikeThrough', 'line', 'justifyLeft',
            'justifyCenter', 'justifyRight', 'indent', 'outdent', 'line', 'insertOrderedList', 'insertUnorderedList', 'line', 'createLink', 'unlink', 'line', 'hr', 'face', 'table', 'files', 'music', 'video', 'insertImage',
            'removeFormat', 'paste', 'line', 'code'
        ];
        // 注意：不要在这里调用 this.create()，它已经在 ice.editor 内部自动调用了
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
