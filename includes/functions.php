<?php
/**
 * 核心功能函数库 - 提供各种通用功能函数
 */

require_once __DIR__ . '/url_helper.php';
require_once __DIR__ . '/pagination_helper.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/user_points.php';
require_once __DIR__ . '/social_functions.php';
require_once __DIR__ . '/content_functions.php';
require_once __DIR__ . '/user_verification.php';
require_once __DIR__ . '/content_moderation.php';
require_once __DIR__ . '/multimedia_functions.php';
require_once __DIR__ . '/content_organization.php';
require_once __DIR__ . '/admin_tools.php';
require_once __DIR__ . '/user_experience.php';

function table($name) {
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    return $prefix . $name;
}

function getSetting($key, $default = null) {
    $cache = getCache();
    $cache_key = 'settings';
    
    static $settings = null;

    if ($settings === null) {
        // 尝试从缓存获取
        $cached_settings = $cache->get($cache_key);
        if ($cached_settings) {
            $settings = $cached_settings;
        } else {
            try {
                $db = getDB();
                $result = $db->fetchAll("SELECT `setting_key`, `setting_value` FROM `" . table('settings') . "`");
                $settings = [];
                foreach ($result as $row) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
                // 缓存设置，有效期5分钟
                $cache->set($cache_key, $settings, 300);
            } catch (Exception $e) {
                $settings = [];
            }
        }
    }

    return $settings[$key] ?? $default;
}

function setSetting($key, $value) {
    try {
        $db = getDB();

        $exists = $db->fetchColumn("SELECT COUNT(*) FROM `" . table('settings') . "` WHERE `setting_key` = :key", ['key' => $key]);

        if ($exists) {
            $result = $db->update(table('settings'), ['setting_value' => $value], "`setting_key` = :key", ['key' => $key]);
        } else {
            $result = $db->insert(table('settings'), [
                'setting_key' => $key,
                'setting_value' => $value,
                'setting_type' => 'string',
                'description' => ''
            ]);
        }
        
        // 清除设置缓存
        $cache = getCache();
        $cache->delete('settings');
        
        return $result;
    } catch (Exception $e) {
        return false;
    }
}

function logAction($action, $target_type = null, $target_id = null, $details = null, $result = 'success') {
    try {
        $db = getDB();

        // 构建详细的日志数据
        $log_data = [
            'user_id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'action' => $action,
            'target_type' => $target_type,
            'target_id' => $target_id,
            'result' => $result,
            'details' => $details ? json_encode($details) : null,
            'ip_address' => getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'created_at_ms' => microtime(true),
        ];

        return $db->insert(table('logs'), $log_data);
    } catch (Exception $e) {
        error_log('日志写入失败：' . $e->getMessage());
        return false;
    }
}

function getClientIp() {
    $ip = '';
    
    // 检查是否有代理
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // X-Forwarded-For可能包含多个IP，取第一个
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    // 验证IP地址格式
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    // 防止内网IP伪造
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return $ip;
    }
    
    // 如果是内网IP，返回REMOTE_ADDR
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/**
 * 通过API查询IP地址的详细信息
 * @param string $ip IP地址
 * @return array|null IP地址详细信息
 */
function getIpInfo($ip) {
    if (empty($ip)) {
        return null;
    }
    
    // 本地IP
    if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
        return [
            'ip' => $ip,
            'country' => '中国',
            'prov' => '本地',
            'city' => '本地',
            'isp' => '本地网络'
        ];
    }
    
    // 使用ip-api.com API，返回中文
    $api_url = "http://ip-api.com/json/{$ip}?lang=zh-CN";
    
    // 使用curl发送请求
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    
    $response = curl_exec($ch);
    
    if ($response === false) {
        return null;
    }
    
    curl_close($ch);
    
    // 解析响应
    $data = json_decode($response, true);
    
    if (isset($data['status']) && $data['status'] == 'success') {
        // 转换为与之前API相同的格式
        return [
            'ip' => $data['query'],
            'country' => $data['country'],
            'prov' => $data['regionName'],
            'city' => $data['city'],
            'isp' => $data['isp']
        ];
    }
    
    return null;
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function safeInput($input) {
    if (is_array($input)) {
        return array_map('safeInput', $input);
    }
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

function formatDateTime($datetime, $format = 'Y-m-d H:i:s') {
    if (empty($datetime)) {
        return '-';
    }
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '-';
    }
    return date($format, $timestamp);
}

function getActiveLinks() {
    $cache = getCache();
    $cache_key = 'active_links';
    
    // 尝试从缓存获取
    $cached_links = $cache->get($cache_key);
    if ($cached_links) {
        return $cached_links;
    }
    
    try {
        $db = getDB();
        $links = $db->fetchAll("SELECT * FROM `links` WHERE `status` = 1 ORDER BY `sort_order` ASC");
        
        // 缓存友链列表，有效期10分钟
        $cache->set($cache_key, $links, 600);
        
        return $links;
    } catch (Exception $e) {
        error_log('获取友链列表失败：' . $e->getMessage());
        return [];
    }
}

function isAdmin() {
    return ($_SESSION['role'] ?? '') === 'admin';
}

function isModerator() {
    return in_array($_SESSION['role'] ?? '', ['moderator', 'admin']);
}

function getStorageType() {
    return defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
}

function isJsonStorage() {
    return getStorageType() === 'json';
}

function getStorageInfo() {
    $type = getStorageType();
    $info = [
        'type' => $type,
        'name' => $type === 'json' ? 'JSON文件存储' : 'MySQL/MariaDB',
        'icon' => $type === 'json' ? 'file-text' : 'database'
    ];
    
    if ($type === 'json') {
        $info['storage_dir'] = defined('JSON_STORAGE_DIR') ? JSON_STORAGE_DIR : 'storage/json';
    } else {
        $info['db_host'] = defined('DB_HOST') ? DB_HOST : 'localhost';
        $info['db_name'] = defined('DB_NAME') ? DB_NAME : '';
    }
    
    return $info;
}

/**
 * 检查系统是否已安装
 * 
 * @return bool 是否已安装
 */
function isInstalled() {
    $cache = getCache();
    $cache_key = 'is_installed';
    
    // 尝试从缓存获取
    $cached_result = $cache->get($cache_key);
    if ($cached_result !== false) {
        return $cached_result;
    }
    
    // 检查配置文件是否存在
    if (!file_exists(__DIR__ . '/../config/config.php')) {
        $cache->set($cache_key, false, 60);
        return false;
    }
    
    // 检查安装锁定文件
    if (!file_exists(__DIR__ . '/../install/install.lock')) {
        $cache->set($cache_key, false, 60);
        return false;
    }
    
    // 检查数据库/存储是否为空（未初始化数据）
    try {
        require_once __DIR__ . '/database.php';
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        // 检查settings表是否有数据
        $settings_count = $db->fetchColumn("SELECT COUNT(*) FROM `{$prefix}settings`");
        if ($settings_count == 0) {
            $cache->set($cache_key, false, 60);
            return false;
        }
        
        // 检查users表是否有管理员
        $admin_count = $db->fetchColumn("SELECT COUNT(*) FROM `{$prefix}users` WHERE `role` = 'admin'");
        if ($admin_count == 0) {
            $cache->set($cache_key, false, 60);
            return false;
        }
        
        // 已安装，缓存结果
        $cache->set($cache_key, true, 300);
        return true;
    } catch (Exception $e) {
        // 数据库连接失败或表不存在，视为未安装
        error_log('isInstalled error: ' . $e->getMessage());
        $cache->set($cache_key, false, 60);
        return false;
    }
}

/**
 * 检查网站是否处于闭站模式
 * 如果是，显示闭站页面，除非用户是管理员或访问登录页面
 */
function checkSiteClosed() {
    // 检查是否已安装
    if (!isInstalled()) {
        return;
    }
    
    // 检查是否是管理员
    if (isAdmin()) {
        return;
    }
    
    // 检查是否访问登录页面
    $current_file = basename($_SERVER['SCRIPT_NAME']);
    if ($current_file === 'login.php') {
        return;
    }
    
    // 检查是否处于闭站模式
    $site_closed = getSetting('site_closed', '0');
    if ($site_closed === '1') {
        $site_name = getSetting('site_name', '网站');
        $site_closed_message = getSetting('site_closed_message', '网站正在维护中，请稍后访问');
        
        // 显示闭站页面
        header('HTTP/1.1 503 Service Temporarily Unavailable');
        header('Status: 503 Service Temporarily Unavailable');
        header('Retry-After: 3600');
        
        echo '<!DOCTYPE html>';
        echo '<html lang="zh-CN">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>' . htmlspecialchars($site_name) . ' - 网站维护</title>';
        echo '<style>';
        echo 'body {';
        echo '    font-family: Arial, sans-serif;';
        echo '    background-color: #f5f5f5;';
        echo '    margin: 0;';
        echo '    padding: 0;';
        echo '    display: flex;';
        echo '    justify-content: center;';
        echo '    align-items: center;';
        echo '    min-height: 100vh;';
        echo '}';
        echo '.container {';
        echo '    background-color: white;';
        echo '    padding: 40px;';
        echo '    border-radius: 8px;';
        echo '    box-shadow: 0 2px 10px rgba(0,0,0,0.1);';
        echo '    text-align: center;';
        echo '    max-width: 600px;';
        echo '    width: 90%;';
        echo '}';
        echo 'h1 {';
        echo '    color: #333;';
        echo '    margin-bottom: 20px;';
        echo '}';
        echo '.message {';
        echo '    font-size: 18px;';
        echo '    color: #666;';
        echo '    margin-bottom: 30px;';
        echo '    line-height: 1.5;';
        echo '}';
        echo '.logo {';
        echo '    font-size: 48px;';
        echo '    margin-bottom: 20px;';
        echo '    color: #337ab7;';
        echo '}';
        echo '.login-button {';
        echo '    display: inline-block;';
        echo '    background-color: #337ab7;';
        echo '    color: white;';
        echo '    padding: 10px 20px;';
        echo '    border-radius: 4px;';
        echo '    text-decoration: none;';
        echo '    font-size: 16px;';
        echo '    margin-top: 20px;';
        echo '    transition: background-color 0.3s;';
        echo '}';
        echo '.login-button:hover {';
        echo '    background-color: #286090;';
        echo '}';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        echo '<div class="container">';
        echo '<div class="logo">🛠️</div>';
        echo '<h1>网站维护中</h1>';
        echo '<div class="message">' . nl2br(htmlspecialchars($site_closed_message)) . '</div>';
        echo '<div style="color: #999; font-size: 14px; margin-bottom: 20px;">我们正在进行系统维护，稍后将恢复正常访问</div>';
        echo '<a href="login.php" class="login-button">管理员登录</a>';
        echo '</div>';
        echo '</body>';
        echo '</html>';
        exit;
    }
}

/**
 * 检查安装并重定向到安装页面（如果需要）
 */
function checkInstall() {
    // 暂时禁用安装检查，直接返回
    return;
    
    if (!isInstalled()) {
        $script_dir = dirname($_SERVER['SCRIPT_NAME']);
        if ($script_dir === '/' || $script_dir === '\\') {
            $script_dir = '';
        }
        header('Location: ' . $script_dir . '/install/index.php');
        exit;
    }
    
    // 检查网站是否处于闭站模式
    checkSiteClosed();
}

function formatContent($content) {
    if (empty($content)) {
        return '';
    }
    
    $content = stripslashes($content);
    $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
    
    // 允许的标签，包括style属性
    $allowed_tags = '<p><br><strong><em><u><a><img><ul><ol><li><blockquote><code><pre><h1><h2><h3><h4><h5><h6><span><div><table><thead><tbody><tr><td><th><hr><iframe><video><audio><source>';
    
    // 使用strip_tags函数，但允许style属性
    $content = strip_tags($content, $allowed_tags);
    
    return $content;
}

/**
 * 获取用户头像URL
 * 规则：
 * 1. 如果用户已设置头像，返回用户头像
 * 2. 如果是纯数字QQ邮箱（如123456@qq.com），返回QQ头像
 * 3. 其他情况返回默认头像 /icon.png
 * 
 * @param array $user 用户数据数组，包含avatar和email字段
 * @param int $size 头像尺寸，默认100
 * @return string 头像URL
 */
function getUserAvatar($user, $size = 100) {
    // 1. 如果用户已设置头像，直接返回
    if (!empty($user['avatar'])) {
        return $user['avatar'];
    }
    
    // 2. 检查是否为纯数字QQ邮箱
    if (!empty($user['email']) && preg_match('/^([1-9][0-9]{4,10})@qq\.com$/i', $user['email'], $matches)) {
        $qq = $matches[1];
        // 返回QQ头像URL
        return 'https://q.qlogo.cn/headimg_dl?dst_uin=' . $qq . '&spec=' . $size;
    }
    
    // 3. 返回默认头像
    return '/icon.png';
}

/**
 * 获取举报数量
 * @param string $report_type 举报类型：user, topic, post
 * @param int $target_id 目标ID
 * @return int 举报数量
 */
function getReportCount($report_type, $target_id) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        $count = $db->fetchColumn(
            "SELECT COUNT(*) FROM `{$prefix}reports` WHERE `report_type` = :report_type AND `target_id` = :target_id AND `status` = 'processed'",
            ['report_type' => $report_type, 'target_id' => $target_id]
        );
        
        return (int)$count;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * 获取举报标记类型
 * @param string $report_type 举报类型：user, topic, post
 * @param int $target_id 目标ID
 * @return string 标记类型：none, yellow, red, ban
 */
function getReportFlag($report_type, $target_id) {
    $report_count = getReportCount($report_type, $target_id);
    
    $yellow_threshold = (int)getSetting('report_yellow_threshold', 3);
    $red_threshold = (int)getSetting('report_red_threshold', 5);
    $ban_threshold = (int)getSetting('report_ban_threshold', 10);
    
    if ($report_count >= $ban_threshold) {
        return 'ban';
    } elseif ($report_count >= $red_threshold) {
        return 'red';
    } elseif ($report_count >= $yellow_threshold) {
        return 'yellow';
    } else {
        return 'none';
    }
}

/**
 * 格式化文件大小
 * @param int $bytes 字节数
 * @return string 格式化后的大小
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

/**
 * 获取IP属地（通过API查询）
 * @param string $ip IP地址
 * @return string IP属地 [省份 城市] 格式
 */
function getIPLocation($ip = null) {
    if (empty($ip)) {
        $ip = getClientIp();
    }
    
    // 本地IP
    if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
        return '本地';
    }
    
    // 验证IP格式
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
        return '未知';
    }
    
    // 缓存目录
    $cacheDir = __DIR__ . '/../storage/cache/ip/';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    // 缓存文件
    $cacheFile = $cacheDir . md5($ip) . '.json';
    $cacheTime = 86400 * 30; // 缓存30天
    
    // 检查缓存
    if (file_exists($cacheFile)) {
        $cache = json_decode(file_get_contents($cacheFile), true);
        if ($cache && isset($cache['time']) && (time() - $cache['time'] < $cacheTime)) {
            return $cache['location'];
        }
    }
    
    // 使用API查询IP属地
    $location = '未知';
    
    // 尝试使用ip-api.com API
    $apiUrl = "http://ip-api.com/json/{$ip}?lang=zh-CN";
    $response = @file_get_contents($apiUrl);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data && $data['status'] === 'success') {
            if ($data['country'] === 'China') {
                $province = $data['regionName'] ?? '';
                $city = $data['city'] ?? '';
                
                // 简化省份名称
                $province = str_replace('省', '', $province);
                $province = str_replace('市', '', $province);
                $province = str_replace('自治区', '', $province);
                $province = str_replace('特别行政区', '', $province);
                
                // 简化城市名称
                $city = str_replace('市', '', $city);
                
                if ($province && $city) {
                    $location = "[{$province} {$city}]";
                } elseif ($province) {
                    $location = "[{$province}]";
                }
            } else {
                // 海外IP
                $country = $data['country'] ?? '海外';
                $location = "[海外 {$country}]";
            }
        }
    }
    
    // 如果API查询失败，尝试备用API
    if ($location === '未知') {
        // 使用ipapi.co API
        $apiUrl = "https://ipapi.co/{$ip}/json/";
        $response = @file_get_contents($apiUrl);
        
        if ($response) {
            $data = json_decode($response, true);
            if ($data && !isset($data['error'])) {
                if ($data['country_name'] === 'China') {
                    $province = $data['region'] ?? '';
                    $city = $data['city'] ?? '';
                    
                    // 简化省份名称
                    $province = str_replace('省', '', $province);
                    $province = str_replace('市', '', $province);
                    $province = str_replace('自治区', '', $province);
                    $province = str_replace('特别行政区', '', $province);
                    
                    // 简化城市名称
                    $city = str_replace('市', '', $city);
                    
                    if ($province && $city) {
                        $location = "[{$province} {$city}]";
                    } elseif ($province) {
                        $location = "[{$province}]";
                    }
                } else {
                    // 海外IP
                    $country = $data['country_name'] ?? '海外';
                    $location = "[海外 {$country}]";
                }
            }
        }
    }
    
    // 保存缓存
    $cache = [
        'ip' => $ip,
        'location' => $location,
        'time' => time()
    ];
    file_put_contents($cacheFile, json_encode($cache, JSON_UNESCAPED_UNICODE));
    
    return $location;
}

/**
 * 清理IP属地缓存
 * @param string $ip IP地址，为空则清理所有缓存
 */
function clearIPLocationCache($ip = null) {
    $cacheDir = __DIR__ . '/../storage/cache/ip/';
    
    if ($ip) {
        $cacheFile = $cacheDir . md5($ip) . '.json';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    } else {
        // 清理所有缓存
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '*.json');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }
}

/**
 * 压缩图片（无损/有损压缩）
 * @param string $source_path 源图片路径
 * @param string $dest_path 目标图片路径（为空则覆盖源文件）
 * @param int $max_width 最大宽度
 * @param int $max_height 最大高度
 * @param int $quality JPEG质量 (1-100)
 * @return bool 是否成功
 */
function compressImage($source_path, $dest_path = '', $max_width = 1920, $max_height = 1080, $quality = 85) {
    if (empty($dest_path)) {
        $dest_path = $source_path;
    }
    
    // 获取图片信息
    $info = getimagesize($source_path);
    if (!$info) {
        return false;
    }
    
    $width = $info[0];
    $height = $info[1];
    $mime = $info['mime'];
    
    // 计算新的尺寸（保持比例）
    $ratio = min($max_width / $width, $max_height / $height, 1);
    $new_width = round($width * $ratio);
    $new_height = round($height * $ratio);
    
    // 创建源图片资源
    switch ($mime) {
        case 'image/jpeg':
            $src_image = imagecreatefromjpeg($source_path);
            break;
        case 'image/png':
            $src_image = imagecreatefrompng($source_path);
            // 保留PNG透明度
            imagealphablending($src_image, true);
            imagesavealpha($src_image, true);
            break;
        case 'image/gif':
            $src_image = imagecreatefromgif($source_path);
            break;
        case 'image/webp':
            $src_image = imagecreatefromwebp($source_path);
            break;
        default:
            return false;
    }
    
    if (!$src_image) {
        return false;
    }
    
    // 创建目标图片资源
    $dst_image = imagecreatetruecolor($new_width, $new_height);
    
    // 处理透明度
    if ($mime == 'image/png' || $mime == 'image/gif') {
        imagealphablending($dst_image, false);
        imagesavealpha($dst_image, true);
        $transparent = imagecolorallocatealpha($dst_image, 255, 255, 255, 127);
        imagefilledrectangle($dst_image, 0, 0, $new_width, $new_height, $transparent);
    }
    
    // 重采样图片（高质量）
    imagecopyresampled($dst_image, $src_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    // 保存图片
    $result = false;
    switch ($mime) {
        case 'image/jpeg':
            $result = imagejpeg($dst_image, $dest_path, $quality);
            break;
        case 'image/png':
            // PNG使用压缩级别0-9
            $png_quality = round((100 - $quality) / 11);
            $result = imagepng($dst_image, $dest_path, $png_quality);
            break;
        case 'image/gif':
            $result = imagegif($dst_image, $dest_path);
            break;
        case 'image/webp':
            $result = imagewebp($dst_image, $dest_path, $quality);
            break;
    }
    
    // 释放资源
    imagedestroy($src_image);
    imagedestroy($dst_image);
    
    return $result;
}

/**
 * 获取数据库类型
 * @return string 数据库类型
 */
function getDBType() {
    // 优先检查STORAGE_TYPE常量
    if (defined('STORAGE_TYPE')) {
        return STORAGE_TYPE;
    }
    
    global $config;
    if (isset($config['db_type'])) {
        return $config['db_type'];
    }
    return 'mysql';
}

/**
 * 处理Base64图片并压缩
 * @param string $base64_data Base64图片数据
 * @param string $upload_dir 上传目录
 * @param int $max_width 最大宽度
 * @param int $max_height 最大高度
 * @param int $quality 质量
 * @return string|false 返回图片URL或false
 */
function processBase64Image($base64_data, $upload_dir, $max_width = 1920, $max_height = 1080, $quality = 85) {
    if (strpos($base64_data, 'data:image') !== 0) {
        return false;
    }
    
    // 解析base64数据
    $image_parts = explode(';base64,', $base64_data);
    if (count($image_parts) !== 2) {
        return false;
    }
    
    $image_type_aux = explode('image/', $image_parts[0]);
    $image_type = $image_type_aux[1] ?? 'png';
    $image_base64 = base64_decode($image_parts[1]);
    
    if (!$image_base64) {
        return false;
    }
    
    // 生成文件名
    $filename = 'img_' . time() . '_' . uniqid() . '.' . $image_type;
    
    // 确保目录存在
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $temp_path = $upload_dir . 'temp_' . $filename;
    $final_path = $upload_dir . $filename;
    
    // 保存临时文件
    if (!file_put_contents($temp_path, $image_base64)) {
        return false;
    }
    
    // 压缩图片
    if (compressImage($temp_path, $final_path, $max_width, $max_height, $quality)) {
        // 删除临时文件
        @unlink($temp_path);
        return $final_path;
    } else {
        // 压缩失败，使用原图
        rename($temp_path, $final_path);
        return $final_path;
    }
}
?>
