<?php
/**
 * 处理文件上传
 */

date_default_timezone_set('Asia/Shanghai');
session_start();

header('Content-Type: application/json');

// 检查是否有文件上传
if (!isset($_FILES['file']) && !isset($_POST['action'])) {
    echo json_encode([
        'success' => false,
        'message' => '没有文件上传'
    ]);
    exit;
}

// 处理图片上传
if (isset($_POST['action']) && $_POST['action'] == 'upload_image') {
    try {
        // 确保上传目录存在
        $uploadDir = __DIR__ . '/upload/files/' . date('Ymd') . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // 处理上传的文件
        $file = $_FILES['file'];
        $fileName = basename($file['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // 检查文件类型
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp'];
        if (!in_array($fileExt, $allowedTypes)) {
            echo json_encode([
                'success' => false,
                'message' => '不支持的文件类型'
            ]);
            exit;
        }
        
        // 生成唯一文件名
        $uniqueName = time() . '_' . uniqid() . '.' . $fileExt;
        $filePath = $uploadDir . $uniqueName;
        
        // 移动文件
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // 生成访问URL
            $http = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $domain = $_SERVER['HTTP_HOST'];
            $url = $http . '://' . $domain . '/upload/files/' . date('Ymd') . '/' . $uniqueName;
            
            echo json_encode([
                'success' => true,
                'url' => $url
            ]);
            exit;
        } else {
            echo json_encode([
                'success' => false,
                'message' => '文件上传失败'
            ]);
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => '上传失败：' . $e->getMessage()
        ]);
        exit;
    }
}

// 处理其他上传
if (isset($_FILES['file'])) {
    // 这里可以处理其他类型的文件上传
    echo json_encode([
        'success' => false,
        'message' => '未支持的上传类型'
    ]);
    exit;
}

// 默认响应
echo json_encode([
    'success' => false,
    'message' => '无效的请求'
]);
?>