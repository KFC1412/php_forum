<?php

/**
 * 内容组织功能
 */

/**
 * 创建专题
 * @param string $title 专题标题
 * @param string $description 专题描述
 * @param string $cover 专题封面
 * @param int $user_id 创建用户ID
 * @return int 专题ID
 */
function createTopic($title, $description, $cover = '', $user_id = 0) {
    try {
        $db = getDB();
        
        // 插入专题
        $topic_id = $db->insert('special_topics', [
            'title' => $title,
            'description' => $description,
            'cover' => $cover,
            'user_id' => $user_id,
            'status' => 'published',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        return $topic_id;
    } catch (Exception $e) {
        error_log('创建专题失败：' . $e->getMessage());
        return 0;
    }
}

/**
 * 更新专题
 * @param int $topic_id 专题ID
 * @param array $data 更新数据
 * @return bool
 */
function updateTopic($topic_id, $data) {
    try {
        $db = getDB();
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $result = $db->update('special_topics', $data, '`id` = :id', ['id' => $topic_id]);
        
        return $result > 0;
    } catch (Exception $e) {
        error_log('更新专题失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 删除专题
 * @param int $topic_id 专题ID
 * @return bool
 */
function deleteTopic($topic_id) {
    try {
        $db = getDB();
        
        // 开始事务
        $db->beginTransaction();
        
        try {
            // 删除专题下的内容
            $db->delete('topic_contents', '`topic_id` = :topic_id', ['topic_id' => $topic_id]);
            
            // 删除专题
            $result = $db->delete('special_topics', '`id` = :id', ['id' => $topic_id]);
            
            // 提交事务
            $db->commit();
            return $result > 0;
        } catch (Exception $e) {
            // 回滚事务
            $db->rollBack();
            throw $e;
        }
    } catch (Exception $e) {
        error_log('删除专题失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 向专题添加内容
 * @param int $topic_id 专题ID
 * @param int $content_id 内容ID
 * @param string $content_type 内容类型（topic/reply）
 * @param string $note 备注
 * @return bool
 */
function addContentToTopic($topic_id, $content_id, $content_type, $note = '') {
    try {
        $db = getDB();
        
        // 检查是否已存在
        $exists = $db->fetchColumn(
            "SELECT COUNT(*) FROM `topic_contents` WHERE `topic_id` = :topic_id AND `content_id` = :content_id AND `content_type` = :content_type",
            ['topic_id' => $topic_id, 'content_id' => $content_id, 'content_type' => $content_type]
        );
        
        if ($exists) {
            return false;
        }
        
        // 添加内容到专题
        $db->insert('topic_contents', [
            'topic_id' => $topic_id,
            'content_id' => $content_id,
            'content_type' => $content_type,
            'note' => $note,
            'added_at' => date('Y-m-d H:i:s')
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log('向专题添加内容失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 从专题移除内容
 * @param int $topic_id 专题ID
 * @param int $content_id 内容ID
 * @param string $content_type 内容类型
 * @return bool
 */
function removeContentFromTopic($topic_id, $content_id, $content_type) {
    try {
        $db = getDB();
        
        $result = $db->delete(
            'topic_contents',
            '`topic_id` = :topic_id AND `content_id` = :content_id AND `content_type` = :content_type',
            ['topic_id' => $topic_id, 'content_id' => $content_id, 'content_type' => $content_type]
        );
        
        return $result > 0;
    } catch (Exception $e) {
        error_log('从专题移除内容失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 获取专题列表
 * @param string $status 状态筛选
 * @param int $limit 限制数量
 * @return array
 */
function getTopics($status = '', $limit = 50) {
    try {
        $db = getDB();
        
        $where = [];
        if (!empty($status)) {
            $where['status'] = $status;
        }
        
        return $db->fetchAll(
            "SELECT st.*, u.username as creator_name FROM `special_topics` st
            LEFT JOIN `users` u ON st.user_id = u.id
            " . (!empty($where) ? "WHERE " . implode(' AND ', array_map(function($k) { return "`$k` = :$k"; }, array_keys($where))) : "") . "
            ORDER BY st.created_at DESC
            LIMIT :limit",
            array_merge($where, ['limit' => $limit])
        );
    } catch (Exception $e) {
        error_log('获取专题列表失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 获取专题详情
 * @param int $topic_id 专题ID
 * @return array
 */
function getTopic($topic_id) {
    try {
        $db = getDB();
        
        $topic = $db->fetch(
            "SELECT st.*, u.username as creator_name FROM `special_topics` st
            LEFT JOIN `users` u ON st.user_id = u.id
            WHERE st.id = :id",
            ['id' => $topic_id]
        );
        
        if (!$topic) {
            return [];
        }
        
        // 获取专题内容
        $contents = $db->fetchAll(
            "SELECT tc.*, 
            CASE 
                WHEN tc.content_type = 'topic' THEN t.title 
                ELSE CONCAT('回复 #', r.id) 
            END as content_title,
            CASE 
                WHEN tc.content_type = 'topic' THEN t.content 
                ELSE r.content 
            END as content_body,
            CASE 
                WHEN tc.content_type = 'topic' THEN t.user_id 
                ELSE r.user_id 
            END as content_user_id,
            u.username as content_username
            FROM `topic_contents` tc
            LEFT JOIN `topics` t ON tc.content_type = 'topic' AND tc.content_id = t.id
            LEFT JOIN `replies` r ON tc.content_type = 'reply' AND tc.content_id = r.id
            LEFT JOIN `users` u ON (
                (tc.content_type = 'topic' AND u.id = t.user_id) OR
                (tc.content_type = 'reply' AND u.id = r.user_id)
            )
            WHERE tc.topic_id = :topic_id
            ORDER BY tc.added_at DESC",
            ['topic_id' => $topic_id]
        );
        
        $topic['contents'] = $contents;
        return $topic;
    } catch (Exception $e) {
        error_log('获取专题详情失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 创建精选内容
 * @param int $content_id 内容ID
 * @param string $content_type 内容类型
 * @param string $reason 精选原因
 * @param int $user_id 操作用户ID
 * @return int 精选ID
 */
function createFeaturedContent($content_id, $content_type, $reason = '', $user_id = 0) {
    try {
        $db = getDB();
        
        // 检查是否已精选
        $exists = $db->fetchColumn(
            "SELECT COUNT(*) FROM `featured_contents` WHERE `content_id` = :content_id AND `content_type` = :content_type",
            ['content_id' => $content_id, 'content_type' => $content_type]
        );
        
        if ($exists) {
            return 0;
        }
        
        // 插入精选记录
        $featured_id = $db->insert('featured_contents', [
            'content_id' => $content_id,
            'content_type' => $content_type,
            'reason' => $reason,
            'user_id' => $user_id,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // 更新内容为精选
        $table = $content_type == 'topic' ? 'topics' : 'replies';
        $db->update($table, ['is_featured' => 1], '`id` = :id', ['id' => $content_id]);
        
        return $featured_id;
    } catch (Exception $e) {
        error_log('创建精选内容失败：' . $e->getMessage());
        return 0;
    }
}

/**
 * 取消精选内容
 * @param int $featured_id 精选ID
 * @return bool
 */
function removeFeaturedContent($featured_id) {
    try {
        $db = getDB();
        
        // 获取精选信息
        $featured = $db->findById('featured_contents', $featured_id);
        if (!$featured) {
            return false;
        }
        
        // 开始事务
        $db->beginTransaction();
        
        try {
            // 删除精选记录
            $db->delete('featured_contents', '`id` = :id', ['id' => $featured_id]);
            
            // 更新内容状态
            $table = $featured['content_type'] == 'topic' ? 'topics' : 'replies';
            $db->update($table, ['is_featured' => 0], '`id` = :id', ['id' => $featured['content_id']]);
            
            // 提交事务
            $db->commit();
            return true;
        } catch (Exception $e) {
            // 回滚事务
            $db->rollBack();
            throw $e;
        }
    } catch (Exception $e) {
        error_log('取消精选内容失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 获取精选内容列表
 * @param string $content_type 内容类型
 * @param int $limit 限制数量
 * @return array
 */
function getFeaturedContents($content_type = '', $limit = 50) {
    try {
        $db = getDB();
        
        $where = [];
        if (!empty($content_type)) {
            $where['content_type'] = $content_type;
        }
        
        return $db->fetchAll(
            "SELECT fc.*, 
            CASE 
                WHEN fc.content_type = 'topic' THEN t.title 
                ELSE CONCAT('回复 #', r.id) 
            END as content_title,
            CASE 
                WHEN fc.content_type = 'topic' THEN t.content 
                ELSE r.content 
            END as content_body,
            CASE 
                WHEN fc.content_type = 'topic' THEN t.user_id 
                ELSE r.user_id 
            END as content_user_id,
            u.username as content_username,
            a.username as operator_username
            FROM `featured_contents` fc
            LEFT JOIN `topics` t ON fc.content_type = 'topic' AND fc.content_id = t.id
            LEFT JOIN `replies` r ON fc.content_type = 'reply' AND fc.content_id = r.id
            LEFT JOIN `users` u ON (
                (fc.content_type = 'topic' AND u.id = t.user_id) OR
                (fc.content_type = 'reply' AND u.id = r.user_id)
            )
            LEFT JOIN `users` a ON fc.user_id = a.id
            " . (!empty($where) ? "WHERE " . implode(' AND ', array_map(function($k) { return "`$k` = :$k"; }, array_keys($where))) : "") . "
            ORDER BY fc.created_at DESC
            LIMIT :limit",
            array_merge($where, ['limit' => $limit])
        );
    } catch (Exception $e) {
        error_log('获取精选内容失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 初始化内容组织系统表结构
 */
function initContentOrganizationSystem() {
    try {
        $db = getDB();
        
        // 初始化专题表
        $db->query("CREATE TABLE IF NOT EXISTS `special_topics` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) NOT NULL,
            `description` text DEFAULT NULL,
            `cover` varchar(255) DEFAULT NULL,
            `user_id` int(11) DEFAULT NULL,
            `status` enum('published','draft','archived') DEFAULT 'published',
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 初始化专题内容表
        $db->query("CREATE TABLE IF NOT EXISTS `topic_contents` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `topic_id` int(11) NOT NULL,
            `content_id` int(11) NOT NULL,
            `content_type` enum('topic','reply') NOT NULL,
            `note` text DEFAULT NULL,
            `added_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `topic_content` (`topic_id`, `content_id`, `content_type`),
            KEY `topic_id` (`topic_id`),
            KEY `content_id` (`content_id`),
            KEY `content_type` (`content_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 初始化精选内容表
        $db->query("CREATE TABLE IF NOT EXISTS `featured_contents` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `content_id` int(11) NOT NULL,
            `content_type` enum('topic','reply') NOT NULL,
            `reason` text DEFAULT NULL,
            `user_id` int(11) DEFAULT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `content` (`content_id`, `content_type`),
            KEY `content_id` (`content_id`),
            KEY `content_type` (`content_type`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 为topics表添加is_featured字段
        try {
            $db->query("ALTER TABLE `topics` ADD COLUMN `is_featured` tinyint(1) DEFAULT 0");
        } catch (Exception $e) {
            // 字段已存在，忽略错误
        }
        
        // 为replies表添加is_featured字段
        try {
            $db->query("ALTER TABLE `replies` ADD COLUMN `is_featured` tinyint(1) DEFAULT 0");
        } catch (Exception $e) {
            // 字段已存在，忽略错误
        }
    } catch (Exception $e) {
        error_log('初始化内容组织系统失败：' . $e->getMessage());
    }
}

/**
 * 获取专题统计信息
 * @return array
 */
function getTopicStats() {
    try {
        $db = getDB();
        
        $stats = [
            'total' => $db->fetchColumn("SELECT COUNT(*) FROM `special_topics`"),
            'published' => $db->fetchColumn("SELECT COUNT(*) FROM `special_topics` WHERE `status` = 'published'"),
            'draft' => $db->fetchColumn("SELECT COUNT(*) FROM `special_topics` WHERE `status` = 'draft'"),
            'archived' => $db->fetchColumn("SELECT COUNT(*) FROM `special_topics` WHERE `status` = 'archived'")
        ];
        
        return $stats;
    } catch (Exception $e) {
        error_log('获取专题统计失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 获取精选内容统计
 * @return array
 */
function getFeaturedStats() {
    try {
        $db = getDB();
        
        $stats = [
            'total' => $db->fetchColumn("SELECT COUNT(*) FROM `featured_contents`"),
            'topics' => $db->fetchColumn("SELECT COUNT(*) FROM `featured_contents` WHERE `content_type` = 'topic'"),
            'replies' => $db->fetchColumn("SELECT COUNT(*) FROM `featured_contents` WHERE `content_type` = 'reply'")
        ];
        
        return $stats;
    } catch (Exception $e) {
        error_log('获取精选统计失败：' . $e->getMessage());
        return [];
    }
}
?>