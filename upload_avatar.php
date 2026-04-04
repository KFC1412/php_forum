<?php
/**
 * 头像上传处理 - 处理用户头像上传和设置
 */

session_start();

// 检查是否已登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

// 加载配置和函数
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';

// 检查安装状态和闭站模式
checkInstall();

// 获取用户ID
$user_id = $_SESSION['user_id'];

// 创建上传目录
$upload_dir = __DIR__ . '/uploads/avatars/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// 处理上传请求
$upload_type = $_POST['upload_type'] ?? '';

if ($upload_type === 'file') {
    // 处理文件上传
    if (!isset($_FILES['avatar'])) {
        echo json_encode(['success' => false, 'message' => '请选择文件']);
        exit;
    }
    
    $file = $_FILES['avatar'];
    
    // 验证文件
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => '文件上传失败']);
        exit;
    }
    
    // 验证文件类型
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => '只支持JPG、PNG、GIF格式']);
        exit;
    }
    
    // 生成文件名
    $filename = 'avatar_' . $user_id . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_path = $upload_dir . $filename;
    
    // 压缩并保存图片
    if (compressImage($file['tmp_name'], $file_path, 100, 100)) {
        // 更新用户头像信息
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        $result = $db->update(
            "{$prefix}users",
            ['avatar' => '/uploads/avatars/' . $filename],
            'id = :id',
            ['id' => $user_id]
        );
        
        if ($result) {
            // 记录头像上传日志
            logAction('用户上传头像', 'user', $user_id, [
                'upload_type' => 'file',
                'filename' => $filename,
                'file_path' => '/uploads/avatars/' . $filename,
                'upload_time' => date('Y-m-d H:i:s'),
                'upload_ip' => getClientIp()
            ]);
            echo json_encode(['success' => true, 'message' => '头像上传成功']);
        } else {
            // 删除已上传的文件
            unlink($file_path);
            echo json_encode(['success' => false, 'message' => '更新头像信息失败']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '图片处理失败']);
    }
} elseif ($upload_type === 'url') {
    // 处理URL上传
    $avatar_url = $_POST['avatar_url'] ?? '';
    
    if (empty($avatar_url)) {
        echo json_encode(['success' => false, 'message' => '请输入头像URL']);
        exit;
    }
    
    // 验证URL格式
    if (filter_var($avatar_url, FILTER_VALIDATE_URL) || strpos($avatar_url, '/') === 0) {
        // 更新用户头像信息
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        $result = $db->update(
            "{$prefix}users",
            ['avatar' => $avatar_url],
            'id = :id',
            ['id' => $user_id]
        );
        
        if ($result) {
            // 记录头像设置日志
            logAction('用户设置头像URL', 'user', $user_id, [
                'upload_type' => 'url',
                'avatar_url' => $avatar_url,
                'upload_time' => date('Y-m-d H:i:s'),
                'upload_ip' => getClientIp()
            ]);
            echo json_encode(['success' => true, 'message' => '头像设置成功']);
        } else {
            echo json_encode(['success' => false, 'message' => '更新头像信息失败']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '无效的URL格式']);
    }
} else {
    echo json_encode(['success' => false, 'message' => '无效的上传类型']);
}


?>