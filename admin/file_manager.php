<?php
date_default_timezone_set('Asia/Shanghai');
/**
 * 文件管理页面
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

// 定义允许访问的目录
$allowed_dirs = [
    'storage/uploads' => '上传文件',
    'storage/cache' => '缓存文件',
    'storage/mail_queue' => '邮件队列',
    'storage/json' => 'JSON数据'
];

// 获取当前目录
$current_dir = isset($_GET['dir']) ? $_GET['dir'] : 'storage/uploads';

// 安全检查：确保目录在允许范围内
$real_path = realpath(__DIR__ . '/../' . $current_dir);
$base_path = realpath(__DIR__ . '/../storage');

if (!$real_path || strpos($real_path, $base_path) !== 0) {
    $current_dir = 'storage/uploads';
    $real_path = realpath(__DIR__ . '/../storage/uploads');
}

// 处理文件操作
switch ($action) {
    case 'upload':
        // 上传文件
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
            try {
                $file = $_FILES['file'];
                
                // 检查上传错误
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('文件上传失败');
                }
                
                // 检查文件大小（最大10MB）
                $max_size = 10 * 1024 * 1024;
                if ($file['size'] > $max_size) {
                    throw new Exception('文件大小超过限制（最大10MB）');
                }
                
                // 检查文件类型
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain', 'application/zip'];
                if (!in_array($file['type'], $allowed_types)) {
                    throw new Exception('不支持的文件类型');
                }
                
                // 生成安全文件名
                $filename = basename($file['name']);
                $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
                $destination = $real_path . '/' . $filename;
                
                // 检查文件是否已存在
                if (file_exists($destination)) {
                    $filename = pathinfo($filename, PATHINFO_FILENAME) . '_' . time() . '.' . pathinfo($filename, PATHINFO_EXTENSION);
                    $destination = $real_path . '/' . $filename;
                }
                
                // 移动文件
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    throw new Exception('文件保存失败');
                }
                
                // 记录操作日志
                logAdminAction('管理员上传文件', 'system', 0, [
                    'filename' => $filename,
                    'filepath' => $current_dir . '/' . $filename,
                    'filesize' => filesize($destination),
                    'filetype' => $file['type'],
                    'admin_id' => $_SESSION['user_id'],
                    'admin_username' => $_SESSION['username'],
                    'action_time' => date('Y-m-d H:i:s'),
                    'ip_address' => getClientIp()
                ]);
                
                $success = '文件上传成功';
            } catch (Exception $e) {
                $error = '上传文件失败: ' . $e->getMessage();
            }
        }
        
        // 重定向回文件列表
        header('Location: file_manager.php?dir=' . urlencode($current_dir) . ($success ? '&success=' . urlencode($success) : ($error ? '&error=' . urlencode($error) : '')));
        exit;
        break;
        
    case 'delete':
        // 删除文件
        $file = isset($_GET['file']) ? $_GET['file'] : '';
        
        if (empty($file)) {
            header('Location: file_manager.php?error=' . urlencode('未指定文件'));
            exit;
        }
        
        // 安全检查：确保文件名安全
        $file = basename($file);
        $file_path = $real_path . '/' . $file;
        
        if (!file_exists($file_path)) {
            header('Location: file_manager.php?error=' . urlencode('文件不存在'));
            exit;
        }
        
        try {
            // 检查是否是目录
            if (is_dir($file_path)) {
                throw new Exception('不能删除目录');
            }
            
            // 删除文件
            if (!unlink($file_path)) {
                throw new Exception('文件删除失败');
            }
            
            // 记录操作日志
            logAdminAction('管理员删除文件', 'system', 0, [
                'filename' => $file,
                'filepath' => $current_dir . '/' . $file,
                'filesize' => filesize($file_path),
                'admin_id' => $_SESSION['user_id'],
                'admin_username' => $_SESSION['username'],
                'action_time' => date('Y-m-d H:i:s'),
                'ip_address' => getClientIp()
            ]);
            
            $success = '文件删除成功';
        } catch (Exception $e) {
            $error = '删除文件失败: ' . $e->getMessage();
        }
        
        // 重定向回文件列表
        header('Location: file_manager.php?dir=' . urlencode($current_dir) . ($success ? '&success=' . urlencode($success) : ($error ? '&error=' . urlencode($error) : '')));
        exit;
        break;
        
    case 'rename':
        // 重命名文件
        $file = isset($_GET['file']) ? $_GET['file'] : '';
        $new_name = isset($_POST['new_name']) ? $_POST['new_name'] : '';
        
        if (empty($file)) {
            header('Location: file_manager.php?error=' . urlencode('未指定文件'));
            exit;
        }
        
        // 安全检查：确保文件名安全
        $file = basename($file);
        $file_path = $real_path . '/' . $file;
        
        if (!file_exists($file_path)) {
            header('Location: file_manager.php?error=' . urlencode('文件不存在'));
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (empty($new_name)) {
                $error = '新文件名不能为空';
            } else {
                try {
                    // 生成安全文件名
                    $new_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $new_name);
                    
                    // 检查文件扩展名
                    $old_ext = pathinfo($file, PATHINFO_EXTENSION);
                    $new_ext = pathinfo($new_name, PATHINFO_EXTENSION);
                    
                    if ($new_ext !== $old_ext) {
                        $new_name .= '.' . $old_ext;
                    }
                    
                    $new_path = $real_path . '/' . $new_name;
                    
                    // 检查新文件名是否已存在
                    if (file_exists($new_path)) {
                        throw new Exception('文件名已存在');
                    }
                    
                    // 重命名文件
                    if (!rename($file_path, $new_path)) {
                        throw new Exception('文件重命名失败');
                    }
                    
                    // 记录操作日志
                    logAdminAction('管理员重命名文件', 'system', 0, [
                        'old_name' => $file,
                        'new_name' => $new_name,
                        'filepath' => $current_dir . '/' . $new_name,
                        'admin_id' => $_SESSION['user_id'],
                        'admin_username' => $_SESSION['username'],
                        'action_time' => date('Y-m-d H:i:s'),
                        'ip_address' => getClientIp()
                    ]);
                    
                    $success = '文件重命名成功';
                    
                    // 重定向回文件列表
                    header('Location: file_manager.php?dir=' . urlencode($current_dir) . '&success=' . urlencode($success));
                    exit;
                } catch (Exception $e) {
                    $error = '重命名文件失败: ' . $e->getMessage();
                }
            }
        }
        
        // 设置页面标题
        $page_title = '重命名文件';
        
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
                                <h1>重命名文件</h1>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="right">
                                <a href="file_manager.php?dir=<?php echo urlencode($current_dir); ?>">返回文件列表</a>
                            </td>
                        </tr>
                        
                        <?php if (!empty($error)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>错误：</strong><?php echo $error; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <tr>
                            <td colspan="2">
                                <form method="post" action="file_manager.php?action=rename&dir=<?php echo urlencode($current_dir); ?>&file=<?php echo urlencode($file); ?>">
                                    <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                        <tr>
                                            <td width="20%">当前文件名</td>
                                            <td><?php echo htmlspecialchars($file); ?></td>
                                        </tr>
                                        <tr>
                                            <td>新文件名</td>
                                            <td>
                                                <input type="text" name="new_name" value="<?php echo htmlspecialchars(pathinfo($file, PATHINFO_FILENAME)); ?>" required>
                                                <div>文件扩展名会自动保留</div>
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
        
    default:
        // 文件列表
        try {
            // 获取文件列表
            $files = [];
            
            if (is_dir($real_path)) {
                $items = scandir($real_path);
                
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') {
                        continue;
                    }
                    
                    $item_path = $real_path . '/' . $item;
                    $files[] = [
                        'name' => $item,
                        'type' => is_dir($item_path) ? 'dir' : 'file',
                        'size' => is_file($item_path) ? filesize($item_path) : 0,
                        'modified' => filemtime($item_path),
                        'extension' => is_file($item_path) ? strtolower(pathinfo($item, PATHINFO_EXTENSION)) : ''
                    ];
                }
                
                // 按类型和名称排序
                usort($files, function($a, $b) {
                    if ($a['type'] === $b['type']) {
                        return strcmp($a['name'], $b['name']);
                    }
                    return $a['type'] === 'dir' ? -1 : 1;
                });
            }
            
            // 计算目录大小
            $total_size = 0;
            $file_count = 0;
            foreach ($files as $file) {
                if ($file['type'] === 'file') {
                    $total_size += $file['size'];
                    $file_count++;
                }
            }
        } catch (Exception $e) {
            $error = '获取文件列表失败: ' . $e->getMessage();
        }
        
        // 获取成功或错误消息
        if (isset($_GET['success'])) {
            $success = $_GET['success'];
        }
        
        if (isset($_GET['error'])) {
            $error = $_GET['error'];
        }
        
        // 设置页面标题
        $page_title = '文件管理';
        
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
                                <h1>文件管理</h1>
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
                        
                        <!-- 目录选择 -->
                        <tr>
                            <td colspan="2">
                                <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                    <tr>
                                        <td colspan="2"><h5>选择目录</h5></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">
                                            <?php foreach ($allowed_dirs as $dir => $name): ?>
                                                <a href="file_manager.php?dir=<?php echo urlencode($dir); ?>" style="display: inline-block; margin: 5px; padding: 5px 10px; background-color: <?php echo $current_dir === $dir ? '#4A90E2' : '#f0f0f0'; ?>; color: <?php echo $current_dir === $dir ? 'white' : '#333'; ?>; border-radius: 3px; text-decoration: none;">
                                                    <?php echo htmlspecialchars($name); ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        
                        <!-- 文件上传 -->
                        <tr>
                            <td colspan="2">
                                <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                    <tr>
                                        <td colspan="2"><h5>上传文件</h5></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">
                                            <form method="post" action="file_manager.php?action=upload&dir=<?php echo urlencode($current_dir); ?>" enctype="multipart/form-data">
                                                <input type="file" name="file" required>
                                                <button type="submit" style="margin-left: 10px; padding: 5px 15px; background-color: #4A90E2; color: white; border: none; border-radius: 3px; cursor: pointer;">上传</button>
                                                <small style="margin-left: 10px; color: #666;">支持 JPG、PNG、GIF、WebP、PDF、TXT、ZIP 格式，最大 10MB</small>
                                            </form>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        
                        <!-- 文件列表 -->
                        <tr>
                            <td colspan="2">
                                <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                    <tr>
                                        <td colspan="5">
                                            <h5>文件列表 - <?php echo htmlspecialchars($current_dir); ?></h5>
                                            <small>总计：<?php echo $file_count; ?> 个文件，<?php echo formatFileSize($total_size); ?></small>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>文件名</th>
                                        <th>类型</th>
                                        <th>大小</th>
                                        <th>修改时间</th>
                                        <th>操作</th>
                                    </tr>
                                    <?php if (isset($files) && count($files) > 0): ?>
                                        <?php foreach ($files as $file): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($file['type'] === 'dir'): ?>
                                                        <span style="color: #666;">📁</span>
                                                    <?php else: ?>
                                                        <span style="color: #666;">📄</span>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($file['name']); ?>
                                                </td>
                                                <td>
                                                    <?php if ($file['type'] === 'dir'): ?>
                                                        目录
                                                    <?php else: ?>
                                                        <?php echo strtoupper($file['extension']); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($file['type'] === 'file'): ?>
                                                        <?php echo formatFileSize($file['size']); ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('Y-m-d H:i:s', $file['modified']); ?></td>
                                                <td>
                                                    <?php if ($file['type'] === 'file'): ?>
                                                        <?php if (in_array(strtolower($file['extension']), ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                                            <a href="#" onclick="showImagePreview('<?php echo '../' . htmlspecialchars($current_dir . '/' . $file['name']); ?>', '<?php echo htmlspecialchars($file['name']); ?>'); return false;">查看</a> | 
                                                        <?php else: ?>
                                                            <a href="<?php echo '../' . htmlspecialchars($current_dir . '/' . $file['name']); ?>" target="_blank">查看</a> | 
                                                        <?php endif; ?>
                                                        <a href="file_manager.php?action=rename&dir=<?php echo urlencode($current_dir); ?>&file=<?php echo urlencode($file['name']); ?>">重命名</a> | 
                                                        <a href="file_manager.php?action=delete&dir=<?php echo urlencode($current_dir); ?>&file=<?php echo urlencode($file['name']); ?>" onclick="return confirm('确定要删除这个文件吗？此操作不可恢复！');">删除</a>
                                                    <?php else: ?>
                                                        <a href="file_manager.php?dir=<?php echo urlencode($current_dir . '/' . $file['name']); ?>">目录</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" align="center">没有找到文件</td>
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
function showImagePreview(imageUrl, imageName) {
    // 创建弹窗
    var modal = document.createElement('div');
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.width = '100%';
    modal.style.height = '100%';
    modal.style.backgroundColor = 'rgba(0,0,0,0.8)';
    modal.style.zIndex = '9999';
    modal.style.display = 'flex';
    modal.style.justifyContent = 'center';
    modal.style.alignItems = 'center';
    modal.style.flexDirection = 'column';
    
    // 创建图片容器
    var imgContainer = document.createElement('div');
    imgContainer.style.position = 'relative';
    imgContainer.style.maxWidth = '90%';
    imgContainer.style.maxHeight = '90%';
    
    // 创建图片
    var img = document.createElement('img');
    img.src = imageUrl;
    img.style.maxWidth = '100%';
    img.style.maxHeight = '80vh';
    img.style.border = '2px solid white';
    img.style.borderRadius = '5px';
    
    // 创建关闭按钮
    var closeBtn = document.createElement('button');
    closeBtn.innerHTML = '×';
    closeBtn.style.position = 'absolute';
    closeBtn.style.top = '-20px';
    closeBtn.style.right = '-20px';
    closeBtn.style.fontSize = '30px';
    closeBtn.style.color = 'white';
    closeBtn.style.backgroundColor = 'transparent';
    closeBtn.style.border = 'none';
    closeBtn.style.cursor = 'pointer';
    closeBtn.style.padding = '0';
    closeBtn.style.width = '40px';
    closeBtn.style.height = '40px';
    
    // 创建文件名
    var fileName = document.createElement('div');
    fileName.textContent = imageName;
    fileName.style.color = 'white';
    fileName.style.marginTop = '15px';
    fileName.style.fontSize = '16px';
    fileName.style.fontWeight = 'bold';
    
    // 添加关闭事件
    closeBtn.onclick = function() {
        document.body.removeChild(modal);
    };
    
    modal.onclick = function(e) {
        if (e.target === modal) {
            document.body.removeChild(modal);
        }
    };
    
    // 组装元素
    imgContainer.appendChild(img);
    imgContainer.appendChild(closeBtn);
    modal.appendChild(imgContainer);
    modal.appendChild(fileName);
    
    // 添加到页面
    document.body.appendChild(modal);
    
    // 添加ESC键关闭功能
    var escHandler = function(e) {
        if (e.key === 'Escape') {
            document.body.removeChild(modal);
            document.removeEventListener('keydown', escHandler);
        }
    };
    document.addEventListener('keydown', escHandler);
}
</script>
