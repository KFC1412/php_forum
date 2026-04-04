<?php

/**
 * 社交互动功能
 */

/**
 * 关注用户
 * @param int $follower_id 关注者ID
 * @param int $followed_id 被关注者ID
 * @return bool
 */
function followUser($follower_id, $followed_id) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        // 检查是否已经关注
        $exists = $db->fetchColumn(
            "SELECT COUNT(*) FROM `{$prefix}user_follows` WHERE `follower_id` = :follower_id AND `followed_id` = :followed_id",
            ['follower_id' => $follower_id, 'followed_id' => $followed_id]
        );
        
        if ($exists) {
            return false;
        }
        
        // 插入关注记录
        $db->insert("{$prefix}user_follows", [
            'follower_id' => $follower_id,
            'followed_id' => $followed_id,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // 发送关注通知
        // 获取关注者信息
        $follower = $db->fetch(
            "SELECT username FROM `{$prefix}users` WHERE `id` = ?",
            [$follower_id]
        );
        
        if ($follower) {
            // 发送互动消息通知
            include_once __DIR__ . '/mail_functions.php';
            sendInteractionNotification($followed_id, 'new_follower', [
                'follower_name' => $follower['username'],
                'follower_id' => $follower_id
            ]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log('关注用户失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 取消关注用户
 * @param int $follower_id 关注者ID
 * @param int $followed_id 被关注者ID
 * @return bool
 */
function unfollowUser($follower_id, $followed_id) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        // 删除关注记录
        $result = $db->delete(
            "{$prefix}user_follows",
            '`follower_id` = :follower_id AND `followed_id` = :followed_id',
            ['follower_id' => $follower_id, 'followed_id' => $followed_id]
        );
        
        return $result > 0;
    } catch (Exception $e) {
        error_log('取消关注用户失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 检查是否关注了用户
 * @param int $follower_id 关注者ID
 * @param int $followed_id 被关注者ID
 * @return bool
 */
function isFollowing($follower_id, $followed_id) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        $count = $db->fetchColumn(
            "SELECT COUNT(*) FROM `{$prefix}user_follows` WHERE `follower_id` = :follower_id AND `followed_id` = :followed_id",
            ['follower_id' => $follower_id, 'followed_id' => $followed_id]
        );
        
        return $count > 0;
    } catch (Exception $e) {
        error_log('检查关注状态失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 获取用户的关注列表
 * @param int $user_id 用户ID
 * @param int $limit 限制数量
 * @return array
 */
function getUserFollowing($user_id, $limit = 50) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        return $db->fetchAll(
            "SELECT u.*, uf.created_at FROM `{$prefix}users` u
            JOIN `{$prefix}user_follows` uf ON u.id = uf.followed_id
            WHERE uf.follower_id = :user_id
            ORDER BY uf.created_at DESC
            LIMIT :limit",
            ['user_id' => $user_id, 'limit' => $limit]
        );
    } catch (Exception $e) {
        error_log('获取关注列表失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 获取用户的粉丝列表
 * @param int $user_id 用户ID
 * @param int $limit 限制数量
 * @return array
 */
function getUserFollowers($user_id, $limit = 50) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        return $db->fetchAll(
            "SELECT u.*, uf.created_at FROM `{$prefix}users` u
            JOIN `{$prefix}user_follows` uf ON u.id = uf.follower_id
            WHERE uf.followed_id = :user_id
            ORDER BY uf.created_at DESC
            LIMIT :limit",
            ['user_id' => $user_id, 'limit' => $limit]
        );
    } catch (Exception $e) {
        error_log('获取粉丝列表失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 发送私信
 * @param int $sender_id 发送者ID
 * @param int $receiver_id 接收者ID
 * @param string $content 消息内容
 * @return int 消息ID
 */
function sendMessage($sender_id, $receiver_id, $content) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        // 获取发送者IP
        $ip = getClientIp();
        
        // 插入消息记录
        $message_id = $db->insert("{$prefix}messages", [
            'sender_id' => $sender_id,
            'receiver_id' => $receiver_id,
            'content' => $content,
            'status' => 'unread',
            'ip' => $ip,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return $message_id;
    } catch (Exception $e) {
        error_log('发送私信失败：' . $e->getMessage());
        return 0;
    }
}

/**
 * 获取用户的私信列表
 * @param int $user_id 用户ID
 * @param int $limit 限制数量
 * @return array
 */
function getUserMessages($user_id, $limit = 50) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        return $db->fetchAll(
            "SELECT m.*, s.username as sender_name, r.username as receiver_name
            FROM `{$prefix}messages` m
            LEFT JOIN `{$prefix}users` s ON m.sender_id = s.id
            LEFT JOIN `{$prefix}users` r ON m.receiver_id = r.id
            WHERE m.sender_id = :user_id OR m.receiver_id = :user_id
            ORDER BY m.created_at DESC
            LIMIT :limit",
            ['user_id' => (string)$user_id, 'limit' => $limit]
        );
    } catch (Exception $e) {
        error_log('获取私信列表失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 获取与特定用户的私信对话
 * @param int $user_id 当前用户ID
 * @param int $other_user_id 对方用户ID
 * @param int $limit 限制数量
 * @return array
 */
function getMessageThread($user_id, $other_user_id, $limit = 50) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        // 标记消息为已读
        $db->update("{$prefix}messages",
            ['status' => 'read'],
            '`sender_id` = :other_user_id AND `receiver_id` = :user_id AND `status` = :status',
            ['other_user_id' => (string)$other_user_id, 'user_id' => (string)$user_id, 'status' => 'unread']
        );
        
        // 根据对话类型构建查询条件
        if ($other_user_id == 'info') {
            // 与互动消息的对话，只显示发送者是'info'的消息
            $messages = $db->fetchAll(
                "SELECT * FROM `{$prefix}messages` WHERE 
                `sender_id` = 'info' AND `receiver_id` = :user_id 
                ORDER BY created_at ASC",
                ['user_id' => (string)$user_id]
            );
        } elseif ($other_user_id == 'system') {
            // 与系统通知的对话，只显示当前用户与系统通知之间的消息
            $messages = $db->fetchAll(
                "SELECT * FROM `{$prefix}messages` WHERE 
                (`sender_id` = 'system' AND `receiver_id` = :user_id) OR
                (`sender_id` = :user_id AND `receiver_id` = 'system')
                ORDER BY created_at ASC",
                ['user_id' => (string)$user_id]
            );
            
            // 过滤出真正的系统通知消息，确保只显示当前用户与系统之间的消息
            $messages = array_filter($messages, function($msg) use ($user_id) {
                return ($msg['sender_id'] == 'system' && $msg['receiver_id'] == (string)$user_id) ||
                       ($msg['sender_id'] == (string)$user_id && $msg['receiver_id'] == 'system');
            });
        } else {
            // 与普通用户的对话，只显示两个用户之间的消息
            $messages = $db->fetchAll(
                "SELECT * FROM `{$prefix}messages` WHERE 
                (sender_id = :user_id AND receiver_id = :other_user_id) OR 
                (sender_id = :other_user_id AND receiver_id = :user_id)
                ORDER BY created_at ASC",
                ['user_id' => (string)$user_id, 'other_user_id' => (string)$other_user_id]
            );
            
            // 过滤掉系统消息和互动消息
            $messages = array_filter($messages, function($msg) {
                return $msg['sender_id'] !== 'system' && $msg['sender_id'] !== 'info' &&
                       $msg['receiver_id'] !== 'system' && $msg['receiver_id'] !== 'info';
            });
        }
        
        // 限制数量
        if (count($messages) > $limit) {
            $messages = array_slice($messages, -$limit);
        }
        
        return $messages;
    } catch (Exception $e) {
        error_log('获取私信对话失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 发送好友请求
 * @param int $requester_id 请求者ID
 * @param int $addressee_id 被请求者ID
 * @return bool
 */
function sendFriendRequest($requester_id, $addressee_id) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        // 检查是否已经有好友请求 - 使用字符串类型匹配JSON存储
        $exists = $db->fetchColumn(
            "SELECT COUNT(*) FROM `{$prefix}friend_requests` 
            WHERE (`requester_id` = :requester_id AND `addressee_id` = :addressee_id) OR
                  (`requester_id` = :addressee_id AND `addressee_id` = :requester_id)",
            ['requester_id' => (string)$requester_id, 'addressee_id' => (string)$addressee_id]
        );
        
        if ($exists) {
            return false;
        }
        
        // 检查是否已经是好友
        $is_friend = $db->fetchColumn(
            "SELECT COUNT(*) FROM `{$prefix}user_friends` 
            WHERE (`user_id` = :requester_id AND `friend_id` = :addressee_id) OR
                  (`user_id` = :addressee_id AND `friend_id` = :requester_id)",
            ['requester_id' => (string)$requester_id, 'addressee_id' => (string)$addressee_id]
        );
        
        if ($is_friend) {
            return false;
        }
        
        // 发送好友请求
        $db->insert("{$prefix}friend_requests", [
            'requester_id' => $requester_id,
            'addressee_id' => $addressee_id,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log('发送好友请求失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 接受好友请求
 * @param int $request_id 请求ID
 * @param int $user_id 当前用户ID
 * @return bool
 */
function acceptFriendRequest($request_id, $user_id) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        // 获取好友请求 - 使用字符串类型匹配JSON存储
        $request = $db->fetch(
            "SELECT * FROM `{$prefix}friend_requests` WHERE `id` = :request_id AND `addressee_id` = :user_id AND `status` = :status",
            ['request_id' => (int)$request_id, 'user_id' => (string)$user_id, 'status' => 'pending']
        );
        
        if (!$request) {
            error_log('接受好友请求失败：未找到请求记录 request_id=' . $request_id . ', user_id=' . $user_id);
            return false;
        }
        
        // 检查是否支持事务（JSON存储不支持事务）
        $use_transaction = method_exists($db, 'beginTransaction');
        
        if ($use_transaction) {
            // 开始事务
            $db->beginTransaction();
        }
        
        try {
            // 更新请求状态
            $db->update("{$prefix}friend_requests",
                ['status' => 'accepted', 'updated_at' => date('Y-m-d H:i:s')],
                '`id` = :request_id',
                ['request_id' => $request_id]
            );
            
            // 添加好友关系
            $db->insert("{$prefix}user_friends", [
                'user_id' => $request['requester_id'],
                'friend_id' => $request['addressee_id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $db->insert("{$prefix}user_friends", [
                'user_id' => $request['addressee_id'],
                'friend_id' => $request['requester_id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // 提交事务
            if ($use_transaction) {
                $db->commit();
            }
            return true;
        } catch (Exception $e) {
            // 回滚事务
            if ($use_transaction) {
                $db->rollBack();
            }
            throw $e;
        }
    } catch (Exception $e) {
        error_log('接受好友请求失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 拒绝好友请求
 * @param int $request_id 请求ID
 * @param int $user_id 当前用户ID
 * @return bool
 */
function rejectFriendRequest($request_id, $user_id) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        // 更新请求状态
        $result = $db->update("{$prefix}friend_requests",
            ['status' => 'rejected', 'updated_at' => date('Y-m-d H:i:s')],
            '`id` = :request_id AND `addressee_id` = :user_id AND `status` = :status',
            ['request_id' => $request_id, 'user_id' => $user_id, 'status' => 'pending']
        );
        
        return $result > 0;
    } catch (Exception $e) {
        error_log('拒绝好友请求失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 获取用户的好友列表
 * @param int $user_id 用户ID
 * @param int $limit 限制数量
 * @return array
 */
function getUserFriends($user_id, $limit = 50) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        // 获取好友关系列表 - 使用字符串类型匹配JSON存储
        $friends = $db->fetchAll(
            "SELECT * FROM `{$prefix}user_friends` WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit",
            ['user_id' => (string)$user_id, 'limit' => $limit]
        );
        
        // 获取好友用户信息
        $result = [];
        foreach ($friends as $friend) {
            $user = $db->fetch(
                "SELECT * FROM `{$prefix}users` WHERE id = :user_id",
                ['user_id' => (string)$friend['friend_id']]
            );
            if ($user) {
                $user['friend_created_at'] = $friend['created_at'];
                $result[] = $user;
            }
        }
        
        return $result;
    } catch (Exception $e) {
        error_log('获取好友列表失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 获取用户收到的好友请求
 * @param int $user_id 用户ID
 * @param int $limit 限制数量
 * @return array
 */
function getUserFriendRequests($user_id, $limit = 20) {
    try {
        $db = getDB();
        
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        // 获取好友请求列表 - 使用字符串类型匹配JSON存储
        $requests = $db->fetchAll(
            "SELECT * FROM `{$prefix}friend_requests` 
            WHERE addressee_id = :user_id AND status = :status
            ORDER BY created_at DESC
            LIMIT :limit",
            ['user_id' => (string)$user_id, 'status' => 'pending', 'limit' => $limit]
        );
        
        // 获取请求者用户信息
        foreach ($requests as &$request) {
            $user = $db->fetch(
                "SELECT username, avatar FROM `{$prefix}users` WHERE id = :user_id",
                ['user_id' => (string)$request['requester_id']]
            );
            $request['requester_name'] = $user['username'] ?? null;
            $request['requester_avatar'] = $user['avatar'] ?? null;
        }
        
        return $requests;
    } catch (Exception $e) {
        error_log('获取好友请求失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 初始化社交系统表结构
 */
function initSocialSystem() {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        // 初始化关注表
        $db->query("CREATE TABLE IF NOT EXISTS `user_follows` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `follower_id` int(11) NOT NULL,
            `followed_id` int(11) NOT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `follower_followed` (`follower_id`, `followed_id`),
            KEY `follower_id` (`follower_id`),
            KEY `followed_id` (`followed_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 初始化消息表
        $db->query("CREATE TABLE IF NOT EXISTS `{$prefix}messages` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `sender_id` int(11) NOT NULL,
            `receiver_id` int(11) NOT NULL,
            `content` text NOT NULL,
            `status` enum('unread','read') DEFAULT 'unread',
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `sender_id` (`sender_id`),
            KEY `receiver_id` (`receiver_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 初始化好友请求表
        $db->query("CREATE TABLE IF NOT EXISTS `{$prefix}friend_requests` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `requester_id` int(11) NOT NULL,
            `addressee_id` int(11) NOT NULL,
            `status` enum('pending','accepted','rejected') DEFAULT 'pending',
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `requester_addressee` (`requester_id`, `addressee_id`),
            KEY `requester_id` (`requester_id`),
            KEY `addressee_id` (`addressee_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 初始化好友关系表
        $db->query("CREATE TABLE IF NOT EXISTS `{$prefix}user_friends` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `friend_id` int(11) NOT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_friend` (`user_id`, `friend_id`),
            KEY `user_id` (`user_id`),
            KEY `friend_id` (`friend_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) {
        error_log('初始化社交系统失败：' . $e->getMessage());
    }
}

/**
 * 获取未读消息数量
 * @param int $user_id 用户ID
 * @return int
 */
function getUnreadMessageCount($user_id) {
    try {
        $db = getDB();
        
        return $db->fetchColumn(
            "SELECT COUNT(*) FROM `messages` WHERE `receiver_id` = :user_id AND `status` = :status",
            ['user_id' => $user_id, 'status' => 'unread']
        );
    } catch (Exception $e) {
        error_log('获取未读消息数量失败：' . $e->getMessage());
        return 0;
    }
}

/**
 * 获取未处理的好友请求数量
 * @param int $user_id 用户ID
 * @return int
 */
function getPendingFriendRequestCount($user_id) {
    try {
        $db = getDB();
        
        return $db->fetchColumn(
            "SELECT COUNT(*) FROM `friend_requests` WHERE `addressee_id` = :user_id AND `status` = :status",
            ['user_id' => $user_id, 'status' => 'pending']
        );
    } catch (Exception $e) {
        error_log('获取未处理好友请求数量失败：' . $e->getMessage());
        return 0;
    }
}
?>