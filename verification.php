<?php
/**
 * 处理用户认证申请
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
require_once __DIR__ . '/includes/user_verification.php';

// 检查安装状态和闭站模式
checkInstall();

// 检查是否登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

// 获取参数
$type = $_POST['type'] ?? '';

if ($type === 'real_name') {
    // 实名认证
    $real_name = $_POST['real_name'] ?? '';
    $id_card = $_POST['id_card'] ?? '';
    
    // 验证输入
    if (empty($real_name) || empty($id_card)) {
        echo json_encode(['success' => false, 'message' => '请填写所有必填字段']);
        exit;
    }
    
    // 验证真实姓名
    if (!validateRealName($real_name)) {
        echo json_encode(['success' => false, 'message' => '真实姓名格式不正确']);
        exit;
    }
    
    // 验证身份证号
    if (!validateIdCard($id_card)) {
        echo json_encode(['success' => false, 'message' => '身份证号格式不正确']);
        exit;
    }
    
    // 处理文件上传
    $id_card_front = '';
    $id_card_back = '';
    
    if (isset($_FILES['id_card_front']) && $_FILES['id_card_front']['error'] === UPLOAD_ERR_OK) {
        $id_card_front = uploadFile($_FILES['id_card_front'], 'verification');
        if (!$id_card_front) {
            echo json_encode(['success' => false, 'message' => '身份证正面照片上传失败']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => '请上传身份证正面照片']);
        exit;
    }
    
    if (isset($_FILES['id_card_back']) && $_FILES['id_card_back']['error'] === UPLOAD_ERR_OK) {
        $id_card_back = uploadFile($_FILES['id_card_back'], 'verification');
        if (!$id_card_back) {
            echo json_encode(['success' => false, 'message' => '身份证反面照片上传失败']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => '请上传身份证反面照片']);
        exit;
    }
    
    // 提交实名认证申请
    $result = submitRealNameVerification($_SESSION['user_id'], $real_name, $id_card, $id_card_front, $id_card_back);
    
    if ($result) {
        // 记录操作日志
        logAction('用户提交实名认证申请', 'user', $_SESSION['user_id'], [
            'real_name' => $real_name,
            'id_card' => $id_card,
            'submit_time' => date('Y-m-d H:i:s'),
            'ip' => getClientIp()
        ]);
        
        echo json_encode(['success' => true, 'message' => '实名认证申请提交成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '提交失败，请稍后重试']);
    }
} else if ($type === 'professional') {
    // 专业认证
    $profession = $_POST['profession'] ?? '';
    $qualification = $_POST['qualification'] ?? '';
    $description = $_POST['description'] ?? '';
    
    // 验证输入
    if (empty($profession) || empty($qualification)) {
        echo json_encode(['success' => false, 'message' => '请填写所有必填字段']);
        exit;
    }
    
    // 处理文件上传
    $certificate = '';
    
    if (isset($_FILES['certificate']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
        $certificate = uploadFile($_FILES['certificate'], 'verification');
        if (!$certificate) {
            echo json_encode(['success' => false, 'message' => '证书照片上传失败']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => '请上传证书照片']);
        exit;
    }
    
    // 提交专业认证申请
    $result = submitProfessionalVerification($_SESSION['user_id'], $profession, $qualification, $certificate, $description);
    
    if ($result) {
        // 记录操作日志
        logAction('用户提交专业认证申请', 'user', $_SESSION['user_id'], [
            'profession' => $profession,
            'qualification' => $qualification,
            'submit_time' => date('Y-m-d H:i:s'),
            'ip' => getClientIp()
        ]);
        
        echo json_encode(['success' => true, 'message' => '专业认证申请提交成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '提交失败，请稍后重试']);
    }
} else {
    echo json_encode(['success' => false, 'message' => '无效的认证类型']);
    exit;
}

/**
 * 上传文件
 * @param array $file 文件信息
 * @param string $folder 文件夹
 * @return string|false 文件路径或false
 */
function uploadFile($file, $folder) {
    $upload_dir = __DIR__ . '/storage/uploads/' . $folder . '/';
    
    // 确保目录存在
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // 生成文件名
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $file_path = $upload_dir . $filename;
    
    // 移动文件
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return '/storage/uploads/' . $folder . '/' . $filename;
    }
    
    return false;
}
?>