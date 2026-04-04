<?php

/**
 * 用户认证系统
 */

/**
 * 提交实名认证申请
 * @param int $user_id 用户ID
 * @param string $real_name 真实姓名
 * @param string $id_card 身份证号
 * @param string $id_card_front 身份证正面照片
 * @param string $id_card_back 身份证反面照片
 * @return int 申请ID
 */
function submitRealNameVerification($user_id, $real_name, $id_card, $id_card_front, $id_card_back) {
    try {
        $db = getDB();
        
        // 检查是否已经有未审核的申请
        $pending = $db->fetchColumn(
            "SELECT COUNT(*) FROM `user_verifications` 
            WHERE `user_id` = :user_id AND `type` = :type AND `status` = :status",
            ['user_id' => $user_id, 'type' => 'real_name', 'status' => 'pending']
        );
        
        if ($pending) {
            return 0;
        }
        
        // 插入认证申请
        $verification_id = $db->insert('user_verifications', [
            'user_id' => $user_id,
            'type' => 'real_name',
            'real_name' => $real_name,
            'id_card' => $id_card,
            'id_card_front' => $id_card_front,
            'id_card_back' => $id_card_back,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return $verification_id;
    } catch (Exception $e) {
        error_log('提交实名认证申请失败：' . $e->getMessage());
        return 0;
    }
}

/**
 * 提交专业认证申请
 * @param int $user_id 用户ID
 * @param string $profession 专业领域
 * @param string $qualification 资质证明
 * @param string $certificate 证书照片
 * @param string $description 个人简介
 * @return int 申请ID
 */
function submitProfessionalVerification($user_id, $profession, $qualification, $certificate, $description = '') {
    try {
        $db = getDB();
        
        // 检查是否已经有未审核的申请
        $pending = $db->fetchColumn(
            "SELECT COUNT(*) FROM `user_verifications` 
            WHERE `user_id` = :user_id AND `type` = :type AND `status` = :status",
            ['user_id' => $user_id, 'type' => 'professional', 'status' => 'pending']
        );
        
        if ($pending) {
            return 0;
        }
        
        // 插入认证申请
        $verification_id = $db->insert('user_verifications', [
            'user_id' => $user_id,
            'type' => 'professional',
            'profession' => $profession,
            'qualification' => $qualification,
            'certificate' => $certificate,
            'description' => $description,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return $verification_id;
    } catch (Exception $e) {
        error_log('提交专业认证申请失败：' . $e->getMessage());
        return 0;
    }
}

/**
 * 审核认证申请
 * @param int $verification_id 申请ID
 * @param string $status 审核状态（approved/rejected）
 * @param string $reason 审核原因
 * @param int $admin_id 审核管理员ID
 * @return bool
 */
function reviewVerification($verification_id, $status, $reason = '', $admin_id = 0) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        // 获取申请信息
        $verification = $db->findById("{$prefix}user_verifications", $verification_id);
        if (!$verification || $verification['status'] != 'pending') {
            return false;
        }
        
        // 检查是否支持事务（JSON存储不支持事务）
        $use_transaction = method_exists($db, 'beginTransaction');
        
        if ($use_transaction) {
            // 开始事务
            $db->beginTransaction();
        }
        
        try {
            // 更新申请状态
            $db->update("{$prefix}user_verifications", [
                'status' => $status,
                'reason' => $reason,
                'admin_id' => $admin_id,
                'updated_at' => date('Y-m-d H:i:s')
            ], '`id` = :id', ['id' => $verification_id]);
            
            // 如果审核通过，更新用户认证状态
            if ($status == 'approved') {
                $user_updates = [];
                if ($verification['type'] == 'real_name') {
                    $user_updates['real_name_verified'] = 1;
                    $user_updates['real_name'] = $verification['real_name'];
                } else if ($verification['type'] == 'professional') {
                    $user_updates['professional_verified'] = 1;
                    $user_updates['profession'] = $verification['profession'];
                }
                
                if (!empty($user_updates)) {
                    $db->update("{$prefix}users", $user_updates, '`id` = :user_id', ['user_id' => $verification['user_id']]);
                }
            }
            
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
        error_log('审核认证申请失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 获取用户的认证状态
 * @param int $user_id 用户ID
 * @return array
 */
function getUserVerificationStatus($user_id) {
    try {
        $db = getDB();
        
        $user = $db->findById('users', $user_id);
        if (!$user) {
            return [];
        }
        
        return [
            'real_name_verified' => (bool)($user['real_name_verified'] ?? 0),
            'professional_verified' => (bool)($user['professional_verified'] ?? 0),
            'real_name' => $user['real_name'] ?? '',
            'profession' => $user['profession'] ?? ''
        ];
    } catch (Exception $e) {
        error_log('获取用户认证状态失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 获取认证申请列表
 * @param string $status 状态筛选
 * @param string $type 类型筛选
 * @param int $limit 限制数量
 * @return array
 */
function getVerificationRequests($status = '', $type = '', $limit = 50) {
    try {
        $db = getDB();
        
        $where = [];
        $params = [];
        
        if (!empty($status)) {
            $where[] = '`status` = :status';
            $params['status'] = $status;
        }
        
        if (!empty($type)) {
            $where[] = '`type` = :type';
            $params['type'] = $type;
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $params['limit'] = $limit;
        
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        // 获取认证申请列表
        $verifications = $db->fetchAll(
            "SELECT * FROM `{$prefix}user_verifications` 
            $where_clause
            ORDER BY created_at DESC
            LIMIT :limit",
            $params
        );
        
        // 获取用户信息
        foreach ($verifications as &$verification) {
            $user = $db->fetch(
                "SELECT username FROM `{$prefix}users` WHERE id = :user_id",
                ['user_id' => $verification['user_id']]
            );
            $verification['username'] = $user['username'] ?? null;
        }
        
        return $verifications;
    } catch (Exception $e) {
        error_log('获取认证申请列表失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 获取用户的认证申请历史
 * @param int $user_id 用户ID
 * @param int $limit 限制数量
 * @return array
 */
function getUserVerificationHistory($user_id, $limit = 20) {
    try {
        $db = getDB();
        
        return $db->fetchAll(
            "SELECT * FROM `user_verifications` 
            WHERE `user_id` = :user_id 
            ORDER BY `created_at` DESC
            LIMIT :limit",
            ['user_id' => $user_id, 'limit' => $limit]
        );
    } catch (Exception $e) {
        error_log('获取用户认证历史失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 初始化用户认证系统表结构
 */
function initVerificationSystem() {
    try {
        $db = getDB();
        
        // 初始化认证申请表
        $db->query("CREATE TABLE IF NOT EXISTS `user_verifications` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `type` enum('real_name','professional') NOT NULL,
            `real_name` varchar(50) DEFAULT NULL,
            `id_card` varchar(20) DEFAULT NULL,
            `id_card_front` varchar(255) DEFAULT NULL,
            `id_card_back` varchar(255) DEFAULT NULL,
            `profession` varchar(100) DEFAULT NULL,
            `qualification` varchar(255) DEFAULT NULL,
            `certificate` varchar(255) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `status` enum('pending','approved','rejected') DEFAULT 'pending',
            `reason` text DEFAULT NULL,
            `admin_id` int(11) DEFAULT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `type` (`type`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 为users表添加认证相关字段
        try {
            $db->query("ALTER TABLE `users` ADD COLUMN `real_name` varchar(50) DEFAULT NULL");
        } catch (Exception $e) {
            // 字段已存在，忽略错误
        }
        
        try {
            $db->query("ALTER TABLE `users` ADD COLUMN `real_name_verified` tinyint(1) DEFAULT 0");
        } catch (Exception $e) {
            // 字段已存在，忽略错误
        }
        
        try {
            $db->query("ALTER TABLE `users` ADD COLUMN `profession` varchar(100) DEFAULT NULL");
        } catch (Exception $e) {
            // 字段已存在，忽略错误
        }
        
        try {
            $db->query("ALTER TABLE `users` ADD COLUMN `professional_verified` tinyint(1) DEFAULT 0");
        } catch (Exception $e) {
            // 字段已存在，忽略错误
        }
    } catch (Exception $e) {
        error_log('初始化用户认证系统失败：' . $e->getMessage());
    }
}

/**
 * 验证身份证号格式
 * @param string $id_card 身份证号
 * @return bool
 */
function validateIdCard($id_card) {
    // 简单的身份证号格式验证
    $pattern = '/^[1-9]\d{5}(18|19|20)\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])\d{3}[0-9Xx]$/';
    return preg_match($pattern, $id_card);
}

/**
 * 验证真实姓名格式
 * @param string $real_name 真实姓名
 * @return bool
 */
function validateRealName($real_name) {
    // 简单的姓名格式验证（2-20个汉字）
    $pattern = '/^[\x{4e00}-\x{9fa5}]{2,20}$/u';
    return preg_match($pattern, $real_name);
}

/**
 * 检查用户是否已实名认证
 * @param int $user_id 用户ID
 * @return bool
 */
function isRealNameVerified($user_id) {
    try {
        $db = getDB();
        
        $verified = $db->fetchColumn(
            "SELECT `real_name_verified` FROM `users` WHERE `id` = :user_id",
            ['user_id' => $user_id]
        );
        
        return (bool)$verified;
    } catch (Exception $e) {
        error_log('检查实名认证状态失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 检查用户是否已专业认证
 * @param int $user_id 用户ID
 * @return bool
 */
function isProfessionalVerified($user_id) {
    try {
        $db = getDB();
        
        $verified = $db->fetchColumn(
            "SELECT `professional_verified` FROM `users` WHERE `id` = :user_id",
            ['user_id' => $user_id]
        );
        
        return (bool)$verified;
    } catch (Exception $e) {
        error_log('检查专业认证状态失败：' . $e->getMessage());
        return false;
    }
}
?>