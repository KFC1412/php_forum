<?php

/**
 * 内容审核系统
 */

/**
 * 自动审核内容
 * @param string $content 内容
 * @param string $type 内容类型（topic/reply）
 * @return array 审核结果
 */
function autoModerateContent($content, $type = 'topic') {
    $result = [
        'passed' => true,
        'reason' => '',
        'score' => 0
    ];
    
    // 检查敏感词
    $sensitive_score = checkSensitiveWords($content);
    if ($sensitive_score > 0) {
        $result['passed'] = false;
        $result['reason'] = '包含敏感词汇';
        $result['score'] += $sensitive_score;
    }
    
    // 检查垃圾内容
    $spam_score = checkSpamContent($content);
    if ($spam_score > 0) {
        $result['passed'] = false;
        $result['reason'] = '疑似垃圾内容';
        $result['score'] += $spam_score;
    }
    
    // 检查违法内容
    $illegal_score = checkIllegalContent($content);
    if ($illegal_score > 0) {
        $result['passed'] = false;
        $result['reason'] = '包含违法内容';
        $result['score'] += $illegal_score;
    }
    
    return $result;
}

/**
 * 检查敏感词
 * @param string $content 内容
 * @return int 敏感程度分数
 */
function checkSensitiveWords($content) {
    // 简单的敏感词列表
    $sensitive_words = [
        '政治敏感词1', '政治敏感词2',
        '色情词汇1', '色情词汇2',
        '暴力词汇1', '暴力词汇2',
        '违法词汇1', '违法词汇2'
    ];
    
    $score = 0;
    foreach ($sensitive_words as $word) {
        if (strpos($content, $word) !== false) {
            $score += 10;
        }
    }
    
    return $score;
}

/**
 * 检查垃圾内容
 * @param string $content 内容
 * @return int 垃圾内容分数
 */
function checkSpamContent($content) {
    $score = 0;
    
    // 检查重复内容
    $words = explode(' ', $content);
    $unique_words = array_unique($words);
    if (count($unique_words) / count($words) < 0.5) {
        $score += 5;
    }
    
    // 检查链接数量
    $link_pattern = '/https?:\/\/(www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b([-a-zA-Z0-9()@:%_\+.~#?&//=]*)/';
    preg_match_all($link_pattern, $content, $matches);
    if (count($matches[0]) > 3) {
        $score += 8;
    }
    
    // 检查特殊字符
    $special_chars = preg_match_all('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $content);
    if ($special_chars / strlen($content) > 0.1) {
        $score += 3;
    }
    
    return $score;
}

/**
 * 检查违法内容
 * @param string $content 内容
 * @return int 违法内容分数
 */
function checkIllegalContent($content) {
    // 简单的违法内容检查
    $illegal_patterns = [
        '/赌博|博彩|彩票/',
        '/毒品|大麻|海洛因/',
        '/枪支|弹药|武器/',
        '/诈骗|骗钱|欺诈/',
        '/黄赌毒|违禁品/'
    ];
    
    $score = 0;
    foreach ($illegal_patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            $score += 15;
        }
    }
    
    return $score;
}

/**
 * 提交内容审核
 * @param int $content_id 内容ID
 * @param string $content_type 内容类型（topic/reply）
 * @param int $user_id 用户ID
 * @param string $content 内容
 * @return int 审核ID
 */
function submitContentForModeration($content_id, $content_type, $user_id, $content) {
    try {
        $db = getDB();
        
        // 自动审核
        $auto_result = autoModerateContent($content, $content_type);
        
        // 确定审核状态
        $status = $auto_result['passed'] ? 'approved' : 'pending';
        
        // 插入审核记录
        $moderation_id = $db->insert('content_moderation', [
            'content_id' => $content_id,
            'content_type' => $content_type,
            'user_id' => $user_id,
            'content' => $content,
            'auto_score' => $auto_result['score'],
            'auto_reason' => $auto_result['reason'],
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // 如果自动审核不通过，更新内容状态
        if (!$auto_result['passed']) {
            $table = $content_type == 'topic' ? 'topics' : 'replies';
            $db->update($table, ['status' => 'pending'], '`id` = :id', ['id' => $content_id]);
        }
        
        return $moderation_id;
    } catch (Exception $e) {
        error_log('提交内容审核失败：' . $e->getMessage());
        return 0;
    }
}

/**
 * 人工审核内容
 * @param int $moderation_id 审核ID
 * @param string $status 审核状态（approved/rejected）
 * @param string $reason 审核原因
 * @param int $admin_id 审核管理员ID
 * @return bool
 */
function moderateContent($moderation_id, $status, $reason = '', $admin_id = 0) {
    try {
        $db = getDB();
        
        // 获取审核记录
        $moderation = $db->findById('content_moderation', $moderation_id);
        if (!$moderation || $moderation['status'] != 'pending') {
            return false;
        }
        
        // 开始事务
        $db->beginTransaction();
        
        try {
            // 更新审核记录
            $db->update('content_moderation', [
                'status' => $status,
                'reason' => $reason,
                'admin_id' => $admin_id,
                'updated_at' => date('Y-m-d H:i:s')
            ], '`id` = :id', ['id' => $moderation_id]);
            
            // 更新内容状态
            $table = $moderation['content_type'] == 'topic' ? 'topics' : 'replies';
            $content_status = $status == 'approved' ? 'published' : 'rejected';
            
            $db->update($table, ['status' => $content_status], '`id` = :id', ['id' => $moderation['content_id']]);
            
            // 如果拒绝，记录用户违规
            if ($status == 'rejected') {
                recordUserViolation($moderation['user_id'], $moderation['content_type'], $moderation['content_id'], $reason);
            }
            
            // 提交事务
            $db->commit();
            return true;
        } catch (Exception $e) {
            // 回滚事务
            $db->rollBack();
            throw $e;
        }
    } catch (Exception $e) {
        error_log('人工审核内容失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 记录用户违规
 * @param int $user_id 用户ID
 * @param string $content_type 内容类型
 * @param int $content_id 内容ID
 * @param string $reason 违规原因
 */
function recordUserViolation($user_id, $content_type, $content_id, $reason) {
    try {
        $db = getDB();
        
        // 插入违规记录
        $db->insert('user_violations', [
            'user_id' => $user_id,
            'content_type' => $content_type,
            'content_id' => $content_id,
            'reason' => $reason,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // 检查用户违规次数
        $violation_count = $db->fetchColumn(
            "SELECT COUNT(*) FROM `user_violations` WHERE `user_id` = :user_id",
            ['user_id' => $user_id]
        );
        
        // 根据违规次数采取措施
        if ($violation_count >= 5) {
            // 封禁用户
            $db->update('users', ['status' => 'banned'], '`id` = :user_id', ['user_id' => $user_id]);
        } else if ($violation_count >= 3) {
            // 警告用户
            $db->update('users', ['status' => 'warning'], '`id` = :user_id', ['user_id' => $user_id]);
        }
    } catch (Exception $e) {
        error_log('记录用户违规失败：' . $e->getMessage());
    }
}

/**
 * 获取待审核内容列表
 * @param string $content_type 内容类型
 * @param int $limit 限制数量
 * @return array
 */
function getPendingModeration($content_type = '', $limit = 50) {
    try {
        $db = getDB();
        
        $where = ['status' => 'pending'];
        if (!empty($content_type)) {
            $where['content_type'] = $content_type;
        }
        
        return $db->fetchAll(
            "SELECT cm.*, u.username, 
            CASE 
                WHEN cm.content_type = 'topic' THEN t.title 
                ELSE CONCAT('回复 #', r.id) 
            END as content_title
            FROM `content_moderation` cm
            LEFT JOIN `users` u ON cm.user_id = u.id
            LEFT JOIN `topics` t ON cm.content_type = 'topic' AND cm.content_id = t.id
            LEFT JOIN `replies` r ON cm.content_type = 'reply' AND cm.content_id = r.id
            WHERE cm.status = :status " . 
            (!empty($content_type) ? "AND cm.content_type = :content_type " : "") .
            "ORDER BY cm.created_at DESC
            LIMIT :limit",
            array_merge($where, ['limit' => $limit])
        );
    } catch (Exception $e) {
        error_log('获取待审核内容失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 获取用户违规记录
 * @param int $user_id 用户ID
 * @param int $limit 限制数量
 * @return array
 */
function getUserViolations($user_id, $limit = 20) {
    try {
        $db = getDB();
        
        return $db->fetchAll(
            "SELECT * FROM `user_violations` 
            WHERE `user_id` = :user_id 
            ORDER BY `created_at` DESC
            LIMIT :limit",
            ['user_id' => $user_id, 'limit' => $limit]
        );
    } catch (Exception $e) {
        error_log('获取用户违规记录失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 初始化内容审核系统表结构
 */
function initModerationSystem() {
    try {
        $db = getDB();
        
        // 初始化内容审核表
        $db->query("CREATE TABLE IF NOT EXISTS `content_moderation` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `content_id` int(11) NOT NULL,
            `content_type` enum('topic','reply') NOT NULL,
            `user_id` int(11) NOT NULL,
            `content` text NOT NULL,
            `auto_score` int(11) DEFAULT 0,
            `auto_reason` text DEFAULT NULL,
            `status` enum('pending','approved','rejected') DEFAULT 'pending',
            `reason` text DEFAULT NULL,
            `admin_id` int(11) DEFAULT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `content_id` (`content_id`),
            KEY `content_type` (`content_type`),
            KEY `user_id` (`user_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 初始化用户违规表
        $db->query("CREATE TABLE IF NOT EXISTS `user_violations` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `content_type` enum('topic','reply') NOT NULL,
            `content_id` int(11) NOT NULL,
            `reason` text NOT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `content_id` (`content_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 为topics表添加status字段（如果不存在）
        try {
            $db->query("ALTER TABLE `topics` ADD COLUMN `status` enum('pending','published','rejected','hidden') DEFAULT 'published'");
        } catch (Exception $e) {
            // 字段已存在，忽略错误
        }
        
        // 为replies表添加status字段（如果不存在）
        try {
            $db->query("ALTER TABLE `replies` ADD COLUMN `status` enum('pending','published','rejected') DEFAULT 'published'");
        } catch (Exception $e) {
            // 字段已存在，忽略错误
        }
    } catch (Exception $e) {
        error_log('初始化内容审核系统失败：' . $e->getMessage());
    }
}

/**
 * 获取内容审核统计
 * @return array
 */
function getModerationStats() {
    try {
        $db = getDB();
        
        $stats = [
            'pending' => $db->fetchColumn("SELECT COUNT(*) FROM `content_moderation` WHERE `status` = 'pending'"),
            'approved' => $db->fetchColumn("SELECT COUNT(*) FROM `content_moderation` WHERE `status` = 'approved'"),
            'rejected' => $db->fetchColumn("SELECT COUNT(*) FROM `content_moderation` WHERE `status` = 'rejected'"),
            'total' => $db->fetchColumn("SELECT COUNT(*) FROM `content_moderation`")
        ];
        
        return $stats;
    } catch (Exception $e) {
        error_log('获取审核统计失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 批量审核内容
 * @param array $moderation_ids 审核ID数组
 * @param string $status 审核状态
 * @param string $reason 审核原因
 * @param int $admin_id 管理员ID
 * @return int 成功审核的数量
 */
function batchModerateContent($moderation_ids, $status, $reason = '', $admin_id = 0) {
    $count = 0;
    foreach ($moderation_ids as $id) {
        if (moderateContent($id, $status, $reason, $admin_id)) {
            $count++;
        }
    }
    return $count;
}
?>