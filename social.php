<?php
/**
 * 社交互动功能处理
 */

// 启动会话
session_start();

// 检查是否已安装
if (!file_exists(__DIR__ . '/config/config.php')) {
    die(json_encode(['success' => false, 'message' => '系统未安装']));
}

// 加载配置和函数
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/social_functions.php';

// 检查安装状态和闭站模式
checkInstall();

// 检查是否已登录
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => '请先登录']));
}

// 处理请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    switch ($action) {
        case 'follow':
            // 关注用户
            $followed_id = $_POST['user_id'] ?? 0;
            if (empty($followed_id) || $followed_id == $user_id) {
                die(json_encode(['success' => false, 'message' => '无效的用户ID']));
            }
            
            if (followUser($user_id, $followed_id)) {
                die(json_encode(['success' => true, 'message' => '关注成功']));
            } else {
                die(json_encode(['success' => false, 'message' => '关注失败']));
            }
            break;
            
        case 'unfollow':
            // 取消关注用户
            $followed_id = $_POST['user_id'] ?? 0;
            if (empty($followed_id)) {
                die(json_encode(['success' => false, 'message' => '无效的用户ID']));
            }
            
            try {
                $db = getDB();
                $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
                $result = $db->delete("{$prefix}user_follows", '`follower_id` = :follower_id AND `followed_id` = :followed_id', [
                    'follower_id' => $user_id,
                    'followed_id' => $followed_id
                ]);
                
                if ($result) {
                    die(json_encode(['success' => true, 'message' => '取消关注成功']));
                } else {
                    die(json_encode(['success' => false, 'message' => '取消关注失败']));
                }
            } catch (Exception $e) {
                die(json_encode(['success' => false, 'message' => '操作失败: ' . $e->getMessage()]));
            }
            break;
            
        case 'send_message':
            // 发送私信
            $receiver_id = $_POST['receiver_id'] ?? 0;
            $content = $_POST['content'] ?? '';
            
            if (empty($receiver_id) || empty(trim($content))) {
                die(json_encode(['success' => false, 'message' => '请填写完整信息']));
            }
            
            if (strlen(trim($content)) < 1) {
                die(json_encode(['success' => false, 'message' => '私信内容不能为空']));
            }
            
            if (sendMessage($user_id, $receiver_id, trim($content))) {
                die(json_encode(['success' => true, 'message' => '私信发送成功']));
            } else {
                die(json_encode(['success' => false, 'message' => '私信发送失败']));
            }
            break;
            
        case 'get_following':
            // 获取关注列表
            $target_user_id = $_POST['user_id'] ?? $user_id;
            
            try {
                $db = getDB();
                $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
                
                // 获取关注关系列表
                $follows = $db->fetchAll(
                    "SELECT * FROM `{$prefix}user_follows` WHERE follower_id = :user_id ORDER BY created_at DESC",
                    ['user_id' => $target_user_id]
                );
                
                // 获取用户信息
                $following = [];
                foreach ($follows as $follow) {
                    $user = $db->fetch(
                        "SELECT id, username, avatar FROM `{$prefix}users` WHERE id = :user_id",
                        ['user_id' => $follow['followed_id']]
                    );
                    if ($user) {
                        $following[] = $user;
                    }
                }
                
                die(json_encode(['success' => true, 'users' => $following]));
            } catch (Exception $e) {
                die(json_encode(['success' => false, 'message' => '获取关注列表失败: ' . $e->getMessage()]));
            }
            break;
            
        case 'get_followers':
            // 获取粉丝列表
            $target_user_id = $_POST['user_id'] ?? $user_id;
            
            try {
                $db = getDB();
                $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
                
                // 获取粉丝关系列表
                $follows = $db->fetchAll(
                    "SELECT * FROM `{$prefix}user_follows` WHERE followed_id = :user_id ORDER BY created_at DESC",
                    ['user_id' => $target_user_id]
                );
                
                // 获取用户信息
                $followers = [];
                foreach ($follows as $follow) {
                    $user = $db->fetch(
                        "SELECT id, username, avatar FROM `{$prefix}users` WHERE id = :user_id",
                        ['user_id' => $follow['follower_id']]
                    );
                    if ($user) {
                        $followers[] = $user;
                    }
                }
                
                die(json_encode(['success' => true, 'users' => $followers]));
            } catch (Exception $e) {
                die(json_encode(['success' => false, 'message' => '获取粉丝列表失败: ' . $e->getMessage()]));
            }
            break;
            
        default:
            die(json_encode(['success' => false, 'message' => '无效的操作']));
    }
} else {
    die(json_encode(['success' => false, 'message' => '无效的请求方式']));
}
?>