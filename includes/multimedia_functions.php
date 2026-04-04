<?php

/**
 * 多媒体功能
 */

/**
 * 处理视频嵌入
 * @param string $content 内容
 * @return string 处理后的内容
 */
function processVideoEmbeds($content) {
    // 支持的视频平台
    $video_platforms = [
        'youtube' => [
            'pattern' => '/https?:\/\/(www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/',
            'embed' => '<iframe width="560" height="315" src="https://www.youtube.com/embed/$2" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>'
        ],
        'bilibili' => [
            'pattern' => '/https?:\/\/(www\.)?bilibili\.com\/video\/([a-zA-Z0-9]+)/',
            'embed' => '<iframe width="560" height="315" src="https://player.bilibili.com/player.html?aid=$2&page=1" frameborder="0" allowfullscreen></iframe>'
        ],
        'youku' => [
            'pattern' => '/https?:\/\/(v\.)?youku\.com\/v_show\/id_([a-zA-Z0-9=]+)\.html/',
            'embed' => '<iframe width="560" height="315" src="https://player.youku.com/embed/$2" frameborder="0" allowfullscreen></iframe>'
        ],
        'tencent' => [
            'pattern' => '/https?:\/\/(v\.)?qq\.com\/x\/page\/([a-zA-Z0-9]+)\.html/',
            'embed' => '<iframe width="560" height="315" src="https://v.qq.com/iframe/player.html?vid=$2&tiny=0&auto=0" frameborder="0" allowfullscreen></iframe>'
        ]
    ];
    
    // 处理视频链接
    foreach ($video_platforms as $platform => $config) {
        $content = preg_replace($config['pattern'], $config['embed'], $content);
    }
    
    return $content;
}

/**
 * 上传图片
 * @param array $file 文件信息
 * @param int $user_id 用户ID
 * @param string $type 上传类型（avatar/topic/reply）
 * @return string 图片路径
 */
function uploadImage($file, $user_id, $type = 'topic') {
    try {
        // 检查文件类型
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('不支持的图片类型');
        }
        
        // 检查文件大小（最大2MB）
        $max_size = 2 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            throw new Exception('图片大小超过限制');
        }
        
        // 创建上传目录
        $upload_dir = __DIR__ . '/../storage/uploads/' . $type . '/' . date('Y/m/d/');
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // 生成唯一文件名
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . $user_id . '.' . $extension;
        $file_path = $upload_dir . $filename;
        
        // 移动文件
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception('文件上传失败');
        }
        
        // 生成相对路径
        $relative_path = '/storage/uploads/' . $type . '/' . date('Y/m/d/') . $filename;
        
        // 记录图片信息
        recordImage($user_id, $relative_path, $type);
        
        return $relative_path;
    } catch (Exception $e) {
        error_log('上传图片失败：' . $e->getMessage());
        return '';
    }
}

/**
 * 记录图片信息
 * @param int $user_id 用户ID
 * @param string $path 图片路径
 * @param string $type 图片类型
 */
function recordImage($user_id, $path, $type) {
    try {
        $db = getDB();
        
        $db->insert('user_images', [
            'user_id' => $user_id,
            'path' => $path,
            'type' => $type,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        error_log('记录图片信息失败：' . $e->getMessage());
    }
}

/**
 * 获取用户的图片库
 * @param int $user_id 用户ID
 * @param string $type 图片类型
 * @param int $limit 限制数量
 * @return array
 */
function getUserImages($user_id, $type = '', $limit = 50) {
    try {
        $db = getDB();
        
        $where = ['user_id' => $user_id];
        if (!empty($type)) {
            $where['type'] = $type;
        }
        
        return $db->fetchAll(
            "SELECT * FROM `user_images` 
            WHERE user_id = :user_id " . 
            (!empty($type) ? "AND type = :type " : "") .
            "ORDER BY created_at DESC
            LIMIT :limit",
            array_merge($where, ['limit' => $limit])
        );
    } catch (Exception $e) {
        error_log('获取用户图片库失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 删除图片
 * @param int $image_id 图片ID
 * @param int $user_id 用户ID
 * @return bool
 */
function deleteImage($image_id, $user_id) {
    try {
        $db = getDB();
        
        // 获取图片信息
        $image = $db->fetch(
            "SELECT * FROM `user_images` WHERE `id` = :id AND `user_id` = :user_id",
            ['id' => $image_id, 'user_id' => $user_id]
        );
        
        if (!$image) {
            return false;
        }
        
        // 删除文件
        $file_path = __DIR__ . '/../' . $image['path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // 删除数据库记录
        $db->delete('user_images', '`id` = :id', ['id' => $image_id]);
        
        return true;
    } catch (Exception $e) {
        error_log('删除图片失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 生成图片缩略图
 * @param string $image_path 图片路径
 * @param int $width 宽度
 * @param int $height 高度
 * @return string 缩略图路径
 */
function generateThumbnail($image_path, $width = 200, $height = 200) {
    try {
        $full_path = __DIR__ . '/../' . $image_path;
        if (!file_exists($full_path)) {
            return $image_path;
        }
        
        // 获取图片信息
        $info = getimagesize($full_path);
        if (!$info) {
            return $image_path;
        }
        
        // 创建缩略图路径
        $dir = dirname($image_path);
        $filename = pathinfo($image_path, PATHINFO_FILENAME);
        $extension = pathinfo($image_path, PATHINFO_EXTENSION);
        $thumb_path = $dir . '/' . $filename . '_thumb.' . $extension;
        $thumb_full_path = __DIR__ . '/../' . $thumb_path;
        
        // 创建缩略图
        switch ($info[2]) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($full_path);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($full_path);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($full_path);
                break;
            case IMAGETYPE_WEBP:
                $source = imagecreatefromwebp($full_path);
                break;
            default:
                return $image_path;
        }
        
        if (!$source) {
            return $image_path;
        }
        
        // 计算缩略图尺寸
        $src_width = imagesx($source);
        $src_height = imagesy($source);
        
        $aspect_ratio = $src_width / $src_height;
        if ($width / $height > $aspect_ratio) {
            $new_width = $height * $aspect_ratio;
            $new_height = $height;
        } else {
            $new_width = $width;
            $new_height = $width / $aspect_ratio;
        }
        
        // 创建缩略图画布
        $thumb = imagecreatetruecolor($width, $height);
        
        // 填充白色背景
        $white = imagecolorallocate($thumb, 255, 255, 255);
        imagefill($thumb, 0, 0, $white);
        
        // 计算居中位置
        $x = ($width - $new_width) / 2;
        $y = ($height - $new_height) / 2;
        
        // 调整图片大小
        imagecopyresampled($thumb, $source, $x, $y, 0, 0, $new_width, $new_height, $src_width, $src_height);
        
        // 保存缩略图
        switch ($info[2]) {
            case IMAGETYPE_JPEG:
                imagejpeg($thumb, $thumb_full_path, 80);
                break;
            case IMAGETYPE_PNG:
                imagepng($thumb, $thumb_full_path, 6);
                break;
            case IMAGETYPE_GIF:
                imagegif($thumb, $thumb_full_path);
                break;
            case IMAGETYPE_WEBP:
                imagewebp($thumb, $thumb_full_path, 80);
                break;
        }
        
        // 释放资源
        imagedestroy($source);
        imagedestroy($thumb);
        
        return $thumb_path;
    } catch (Exception $e) {
        error_log('生成缩略图失败：' . $e->getMessage());
        return $image_path;
    }
}

/**
 * 处理图片标签
 * @param string $content 内容
 * @return string 处理后的内容
 */
function processImageTags($content) {
    // 处理 [img] 标签
    $pattern = '/\[img\]([^\[]+)\[\/img\]/';
    $replacement = '<img src="$1" class="img-responsive" alt="图片">';
    $content = preg_replace($pattern, $replacement, $content);
    
    // 处理 [img width="100"] 标签
    $pattern = '/\[img\s+width="(\d+)"\]([^\[]+)\[\/img\]/';
    $replacement = '<img src="$2" class="img-responsive" style="width: $1px;" alt="图片">';
    $content = preg_replace($pattern, $replacement, $content);
    
    return $content;
}

/**
 * 初始化多媒体系统表结构
 */
function initMultimediaSystem() {
    try {
        $db = getDB();
        
        // 初始化用户图片表
        $db->query("CREATE TABLE IF NOT EXISTS `user_images` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `path` varchar(255) NOT NULL,
            `type` enum('avatar','topic','reply','other') DEFAULT 'other',
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `type` (`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 创建上传目录
        $upload_dirs = [
            __DIR__ . '/../storage/uploads/avatar/',
            __DIR__ . '/../storage/uploads/topic/',
            __DIR__ . '/../storage/uploads/reply/',
            __DIR__ . '/../storage/uploads/other/'
        ];
        
        foreach ($upload_dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    } catch (Exception $e) {
        error_log('初始化多媒体系统失败：' . $e->getMessage());
    }
}

/**
 * 获取图片统计信息
 * @param int $user_id 用户ID
 * @return array
 */
function getImageStats($user_id) {
    try {
        $db = getDB();
        
        $stats = [
            'total' => $db->fetchColumn("SELECT COUNT(*) FROM `user_images` WHERE `user_id` = :user_id", ['user_id' => $user_id]),
            'avatar' => $db->fetchColumn("SELECT COUNT(*) FROM `user_images` WHERE `user_id` = :user_id AND `type` = 'avatar'", ['user_id' => $user_id]),
            'topic' => $db->fetchColumn("SELECT COUNT(*) FROM `user_images` WHERE `user_id` = :user_id AND `type` = 'topic'", ['user_id' => $user_id]),
            'reply' => $db->fetchColumn("SELECT COUNT(*) FROM `user_images` WHERE `user_id` = :user_id AND `type` = 'reply'", ['user_id' => $user_id])
        ];
        
        return $stats;
    } catch (Exception $e) {
        error_log('获取图片统计失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 批量上传图片
 * @param array $files 文件数组
 * @param int $user_id 用户ID
 * @param string $type 上传类型
 * @return array 上传结果
 */
function batchUploadImages($files, $user_id, $type = 'topic') {
    $results = [];
    
    foreach ($files['name'] as $key => $name) {
        if ($files['error'][$key] === UPLOAD_ERR_OK) {
            $file = [
                'name' => $files['name'][$key],
                'type' => $files['type'][$key],
                'tmp_name' => $files['tmp_name'][$key],
                'error' => $files['error'][$key],
                'size' => $files['size'][$key]
            ];
            
            $path = uploadImage($file, $user_id, $type);
            if ($path) {
                $results[] = [
                    'success' => true,
                    'path' => $path,
                    'name' => $name
                ];
            } else {
                $results[] = [
                    'success' => false,
                    'name' => $name,
                    'error' => '上传失败'
                ];
            }
        }
    }
    
    return $results;
}
?>