<?php

/**
 * 用户积分和等级系统
 */

/**
 * 增加用户积分
 * @param int $user_id 用户ID
 * @param int $points 积分数量
 * @param string $reason 积分变动原因
 * @return bool
 */
function addUserPoints($user_id, $points, $reason = '') {
    try {
        $db = getDB();
        $user = $db->findById('users', $user_id);
        
        if (!$user) {
            return false;
        }
        
        $new_points = ($user['points'] ?? 0) + $points;
        $new_experience = ($user['experience'] ?? 0) + $points;
        
        // 检查是否需要升级
        $new_level = calculateUserLevel($new_experience);
        
        $updates = [
            'points' => $new_points,
            'experience' => $new_experience,
            'level' => $new_level,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // 更新用户信息
        $db->update('users', $updates, '`id` = :id', ['id' => $user_id]);
        
        // 记录积分变动
        recordPointChange($user_id, $points, $new_points, $reason);
        
        return true;
    } catch (Exception $e) {
        error_log('增加用户积分失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 减少用户积分
 * @param int $user_id 用户ID
 * @param int $points 积分数量
 * @param string $reason 积分变动原因
 * @return bool
 */
function deductUserPoints($user_id, $points, $reason = '') {
    try {
        $db = getDB();
        $user = $db->findById('users', $user_id);
        
        if (!$user) {
            return false;
        }
        
        $current_points = $user['points'] ?? 0;
        $new_points = max(0, $current_points - $points);
        
        // 更新用户信息
        $db->update('users', [
            'points' => $new_points,
            'updated_at' => date('Y-m-d H:i:s')
        ], '`id` = :id', ['id' => $user_id]);
        
        // 记录积分变动
        recordPointChange($user_id, -$points, $new_points, $reason);
        
        return true;
    } catch (Exception $e) {
        error_log('减少用户积分失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 计算用户等级
 * @param int $experience 经验值
 * @return int 等级
 */
function calculateUserLevel($experience) {
    // 等级计算公式：level = floor(sqrt(experience / 100)) + 1
    return floor(sqrt($experience / 100)) + 1;
}

/**
 * 获取用户等级所需经验
 * @param int $level 等级
 * @return int 所需经验
 */
function getLevelExperience($level) {
    return ($level - 1) * ($level - 1) * 100;
}

/**
 * 记录积分变动
 * @param int $user_id 用户ID
 * @param int $change 变动积分
 * @param int $new_points 新积分
 * @param string $reason 变动原因
 */
function recordPointChange($user_id, $change, $new_points, $reason) {
    try {
        $db = getDB();
        $db->insert('point_changes', [
            'user_id' => $user_id,
            'change' => $change,
            'new_points' => $new_points,
            'reason' => $reason,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        error_log('记录积分变动失败：' . $e->getMessage());
    }
}

/**
 * 获取用户积分记录
 * @param int $user_id 用户ID
 * @param int $limit 限制数量
 * @return array
 */
function getUserPointHistory($user_id, $limit = 20) {
    try {
        $db = getDB();
        return $db->fetchAll(
            "SELECT * FROM `point_changes` WHERE `user_id` = :user_id ORDER BY `created_at` DESC LIMIT :limit",
            ['user_id' => $user_id, 'limit' => $limit]
        );
    } catch (Exception $e) {
        error_log('获取用户积分记录失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 获取用户徽章
 * @param int $user_id 用户ID
 * @return array
 */
function getUserBadges($user_id) {
    try {
        $db = getDB();
        return $db->fetchAll(
            "SELECT * FROM `user_badges` WHERE `user_id` = :user_id ORDER BY `earned_at` DESC",
            ['user_id' => $user_id]
        );
    } catch (Exception $e) {
        error_log('获取用户徽章失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 授予用户徽章
 * @param int $user_id 用户ID
 * @param string $badge_name 徽章名称
 * @param string $description 徽章描述
 * @return bool
 */
function awardBadge($user_id, $badge_name, $description = '') {
    try {
        $db = getDB();
        
        // 检查是否已经有此徽章
        $exists = $db->fetchColumn(
            "SELECT COUNT(*) FROM `user_badges` WHERE `user_id` = :user_id AND `badge_name` = :badge_name",
            ['user_id' => $user_id, 'badge_name' => $badge_name]
        );
        
        if ($exists) {
            return false;
        }
        
        $db->insert('user_badges', [
            'user_id' => $user_id,
            'badge_name' => $badge_name,
            'description' => $description,
            'earned_at' => date('Y-m-d H:i:s')
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log('授予用户徽章失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 获取等级名称
 * @param int $level 等级
 * @return string 等级名称
 */
function getLevelName($level) {
    $level_names = [
        1 => '新手',
        2 => '进阶',
        3 => '熟练',
        4 => '专家',
        5 => '大师',
        6 => '宗师',
        7 => '传奇',
        8 => '神话',
        9 => '不朽',
        10 => '创世'
    ];
    
    return $level_names[$level] ?? '未知';
}

/**
 * 初始化积分变动表
 */
function initPointChangesTable() {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        // 检查表是否存在
        $exists = $db->fetchColumn("SELECT COUNT(*) FROM `{$prefix}point_changes`");
        if ($exists === false) {
            // 创建表
            $db->query("CREATE TABLE IF NOT EXISTS `{$prefix}point_changes` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `change` int(11) NOT NULL,
                `new_points` int(11) NOT NULL,
                `reason` varchar(255) DEFAULT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    } catch (Exception $e) {
        error_log('初始化积分变动表失败：' . $e->getMessage());
    }
}

/**
 * 初始化用户徽章表
 */
function initUserBadgesTable() {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        // 检查表是否存在
        $exists = $db->fetchColumn("SELECT COUNT(*) FROM `{$prefix}user_badges`");
        if ($exists === false) {
            // 创建表
            $db->query("CREATE TABLE IF NOT EXISTS `{$prefix}user_badges` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `badge_name` varchar(50) NOT NULL,
                `description` varchar(255) DEFAULT NULL,
                `earned_at` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `user_badge` (`user_id`, `badge_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    } catch (Exception $e) {
        error_log('初始化用户徽章表失败：' . $e->getMessage());
    }
}

/**
 * 初始化积分系统
 */
function initPointsSystem() {
    initPointChangesTable();
    initUserBadgesTable();
}

/**
 * 处理用户行为积分
 * @param string $action 行为类型
 * @param int $user_id 用户ID
 * @param array $data 相关数据
 */
function handleUserAction($action, $user_id, $data = []) {
    $point_rules = [
        'post_topic' => 10,      // 发布主题
        'post_reply' => 5,       // 发布回复
        'topic_liked' => 2,      // 主题被点赞
        'reply_liked' => 1,      // 回复被点赞
        'topic_viewed' => 0.1,   // 主题被查看
        'profile_complete' => 20, // 完善个人资料
        'first_login' => 5       // 首次登录
    ];
    
    if (isset($point_rules[$action])) {
        $points = $point_rules[$action];
        $reason = getActionReason($action, $data);
        addUserPoints($user_id, $points, $reason);
    }
}

/**
 * 获取行为原因描述
 * @param string $action 行为类型
 * @param array $data 相关数据
 * @return string
 */
function getActionReason($action, $data = []) {
    $reasons = [
        'post_topic' => '发布主题',
        'post_reply' => '发布回复',
        'topic_liked' => '主题被点赞',
        'reply_liked' => '回复被点赞',
        'topic_viewed' => '主题被查看',
        'profile_complete' => '完善个人资料',
        'first_login' => '首次登录'
    ];
    
    return $reasons[$action] ?? '未知行为';
}

/**
 * 获取用户积分
 * @param int $user_id 用户ID
 * @return int 积分
 */
function getUserPoints($user_id) {
    try {
        $db = getDB();
        $user = $db->findById('users', $user_id);
        return $user['points'] ?? 0;
    } catch (Exception $e) {
        error_log('获取用户积分失败：' . $e->getMessage());
        return 0;
    }
}

/**
 * 获取用户经验值
 * @param int $user_id 用户ID
 * @return int 经验值
 */
function getUserExperience($user_id) {
    try {
        $db = getDB();
        $user = $db->findById('users', $user_id);
        return $user['experience'] ?? 0;
    } catch (Exception $e) {
        error_log('获取用户经验值失败：' . $e->getMessage());
        return 0;
    }
}

/**
 * 获取用户等级
 * @param int $user_id 用户ID
 * @return int 等级
 */
function getUserLevel($user_id) {
    try {
        $db = getDB();
        $user = $db->findById('users', $user_id);
        if ($user && isset($user['level'])) {
            return $user['level'];
        }
        // 如果没有等级，根据经验值计算
        $experience = $user['experience'] ?? 0;
        return calculateUserLevel($experience);
    } catch (Exception $e) {
        error_log('获取用户等级失败：' . $e->getMessage());
        return 1;
    }
}
?>