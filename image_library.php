<?php
/**
 * 用户图片库页面
 */

// 启动会话
session_start();

// 检查是否已安装
if (!file_exists(__DIR__ . '/config/config.php')) {
    header('Location: install/index.php');
    exit;
}

// 加载配置和函数
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/multimedia_functions.php';

// 检查安装状态和闭站模式
checkInstall();

// 检查是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 获取用户ID
$user_id = $_SESSION['user_id'];

// 处理图片上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['images'])) {
    // 检查是否有文件被选择
    if (!isset($_FILES['images']['name']) || empty($_FILES['images']['name'][0]) || $_FILES['images']['error'][0] === UPLOAD_ERR_NO_FILE) {
        $error = '请选择要上传的图片';
    } else {
        $type = $_POST['type'] ?? 'other';
        $results = batchUploadImages($_FILES['images'], $user_id, $type);
        
        // 检查上传结果
        $success_count = 0;
        $fail_count = 0;
        foreach ($results as $result) {
            if ($result['success']) {
                $success_count++;
            } else {
                $fail_count++;
            }
        }
        
        if ($success_count > 0 && $fail_count === 0) {
            $success = "成功上传 {$success_count} 张图片";
        } elseif ($success_count > 0 && $fail_count > 0) {
            $success = "成功上传 {$success_count} 张图片，失败 {$fail_count} 张";
        } elseif ($success_count === 0 && $fail_count > 0) {
            $error = "上传失败，共 {$fail_count} 张图片上传失败";
        }
    }
}

// 处理图片删除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $image_id = $_POST['image_id'] ?? 0;
    if (!empty($image_id)) {
        try {
            $db = getDB();
            $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
            
            // 获取图片信息
            $image = $db->fetch(
                "SELECT * FROM `{$prefix}user_images` WHERE `id` = :id AND `user_id` = :user_id",
                ['id' => $image_id, 'user_id' => $user_id]
            );
            
            if ($image) {
                // 删除图片文件
                $file_path = __DIR__ . $image['path'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                
                // 删除数据库记录
                $db->delete('user_images', '`id` = :id AND `user_id` = :user_id', ['id' => $image_id, 'user_id' => $user_id]);
                
                $success = '图片删除成功';
            } else {
                $error = '图片不存在';
            }
        } catch (Exception $e) {
            $error = '删除图片失败: ' . $e->getMessage();
        }
    }
}

// 获取参数
$type = $_GET['type'] ?? '';

// 设置页面标题
$page_title = '图片库';

// 加载页面头部
include __DIR__ . '/templates/header.php';
?>

<table border="1" width="100%" cellspacing="0" cellpadding="10">
    <tr>
        <td colspan="2">
            <table width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <td><h1>图片库</h1></td>
                    <td align="right">
                        <a href="<?php echo getHomeUrl(); ?>">首页</a> &gt; 图片库
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    
    <?php if (isset($error)): ?>
        <tr>
            <td colspan="2" class="error"><?php echo $error; ?></td>
        </tr>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <tr>
            <td colspan="2" class="success"><?php echo $success; ?></td>
        </tr>
    <?php endif; ?>
    
    <!-- 上传图片 -->
    <tr>
        <td colspan="2">
            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                <tr>
                    <td colspan="2"><h5>上传图片</h5></td>
                </tr>
                <tr>
                    <td colspan="2">
                        <form method="post" action="image_library.php" enctype="multipart/form-data">
                            <input type="file" name="images[]" multiple accept="image/*" style="margin-bottom: 10px;">
                            <select name="type" style="margin-bottom: 10px; padding: 5px;">
                                <option value="topic">主题图片</option>
                                <option value="reply">回复图片</option>
                                <option value="other">其他图片</option>
                            </select>
                            <button type="submit" style="padding: 5px 15px; background-color: #4A90E2; color: white; border: none; border-radius: 3px; cursor: pointer;">批量上传</button>
                        </form>
                        <small style="color: #666;">支持 JPG、PNG、GIF、WebP 格式，单张图片最大 2MB</small>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    
    <!-- 图片分类 -->
    <tr>
        <td colspan="2">
            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                <tr>
                    <td colspan="2">
                        <a href="image_library.php" style="margin-right: 10px; padding: 3px 8px; background-color: <?php echo empty($type) ? '#4A90E2' : '#f0f0f0'; ?>; color: <?php echo empty($type) ? 'white' : '#333'; ?>; border-radius: 15px; text-decoration: none;">全部</a>
                        <a href="image_library.php?type=topic" style="margin-right: 10px; padding: 3px 8px; background-color: <?php echo $type === 'topic' ? '#4A90E2' : '#f0f0f0'; ?>; color: <?php echo $type === 'topic' ? 'white' : '#333'; ?>; border-radius: 15px; text-decoration: none;">主题图片</a>
                        <a href="image_library.php?type=reply" style="margin-right: 10px; padding: 3px 8px; background-color: <?php echo $type === 'reply' ? '#4A90E2' : '#f0f0f0'; ?>; color: <?php echo $type === 'reply' ? 'white' : '#333'; ?>; border-radius: 15px; text-decoration: none;">回复图片</a>
                        <a href="image_library.php?type=other" style="margin-right: 10px; padding: 3px 8px; background-color: <?php echo $type === 'other' ? '#4A90E2' : '#f0f0f0'; ?>; color: <?php echo $type === 'other' ? 'white' : '#333'; ?>; border-radius: 15px; text-decoration: none;">其他图片</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    
    <!-- 图片列表 -->
    <tr>
        <td colspan="2">
            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                <tr>
                    <td colspan="4"><h5>图片列表</h5></td>
                </tr>
                <?php
                try {
                    $images = getUserImages($user_id, $type, 100);
                    $total_images = count($images);
                    
                    // 按类型统计图片数量
                    $db = getDB();
                    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
                    $image_stats = $db->fetchAll(
                        "SELECT type, COUNT(*) as count FROM `{$prefix}user_images` WHERE user_id = :user_id GROUP BY type",
                        ['user_id' => $user_id]
                    );
                    $stats = [];
                    foreach ($image_stats as $stat) {
                        $stats[$stat['type']] = isset($stat['count']) ? $stat['count'] : 0;
                    }
                    ?>
                    <tr>
                        <td colspan="4">
                            <div style="margin-bottom: 10px;">
                                <strong>图片统计：</strong>
                                总计: <?php echo $total_images; ?> 张
                                <?php if (isset($stats['topic'])): ?>, 主题图片: <?php echo $stats['topic']; ?> 张<?php endif; ?>
                                <?php if (isset($stats['reply'])): ?>, 回复图片: <?php echo $stats['reply']; ?> 张<?php endif; ?>
                                <?php if (isset($stats['other'])): ?>, 其他图片: <?php echo $stats['other']; ?> 张<?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php
                    if (count($images) > 0) {
                        $count = 0;
                        foreach ($images as $image) {
                            if ($count % 4 == 0) {
                                echo '<tr>';
                            }
                            ?>
                            <td width="25%" align="center" style="padding: 10px;">
                                <div style="margin-bottom: 10px;">
                                    <img src="<?php echo htmlspecialchars($image['path']); ?>" alt="图片" style="max-width: 150px; max-height: 150px; border: 1px solid #ddd; padding: 5px;">
                                </div>
                                <div style="margin-bottom: 5px;">
                                    <small style="color: #666;"><?php echo formatDateTime($image['created_at']); ?></small>
                                </div>
                                <div>
                                    <form method="post" action="image_library.php" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="image_id" value="<?php echo $image['id']; ?>">
                                        <button type="submit" onclick="return confirm('确定要删除这张图片吗？');" style="padding: 2px 8px; background-color: red; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px;">删除</button>
                                    </form>
                                    <button type="button" onclick="copyImageUrl('<?php echo htmlspecialchars($image['path']); ?>')" style="padding: 2px 8px; background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 3px; cursor: pointer; font-size: 12px; margin-left: 5px;">复制链接</button>
                                </div>
                            </td>
                            <?php
                            $count++;
                            if ($count % 4 == 0) {
                                echo '</tr>';
                            }
                        }
                        if ($count % 4 != 0) {
                            echo '</tr>';
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="4" align="center">暂无图片</td>
                        </tr>
                        <?php
                    }
                } catch (Exception $e) {
                    ?>
                    <tr>
                        <td colspan="4" class="error">加载图片库失败: <?php echo $e->getMessage(); ?></td>
                    </tr>
                    <?php
                }
                ?>
            </table>
        </td>
    </tr>
</table>

<script>
// 复制图片链接
function copyImageUrl(url) {
    navigator.clipboard.writeText(url)
        .then(function() {
            alert('图片链接已复制到剪贴板');
        })
        .catch(function(err) {
            alert('复制失败，请手动复制');
        });
}
</script>

<?php
// 加载页面底部
include __DIR__ . '/templates/footer.php';
?>