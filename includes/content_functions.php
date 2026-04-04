<?php

/**
 * 内容互动功能
 */

/**
 * 点赞主题
 * @param int $user_id 用户ID
 * @param int $topic_id 主题ID
 * @return bool
 */
function likeTopic($user_id, $topic_id) {
    try {
        $db = getDB();
        
        // 检查是否已经点赞
        $exists = $db->fetchColumn(
            "SELECT COUNT(*) FROM `topic_likes` WHERE `user_id` = :user_id AND `topic_id` = :topic_id",
            ['user_id' => $user_id, 'topic_id' => $topic_id]
        );
        
        if ($exists) {
            return false;
        }
        
        // 插入点赞记录
        $db->insert('topic_likes', [
            'user_id' => $user_id,
            'topic_id' => $topic_id,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // 获取主题信息
        $topic = $db->findById('topics', $topic_id);
        if ($topic) {
            // 更新主题的点赞数
            $current_likes = isset($topic['like_count']) ? $topic['like_count'] : 0;
            $db->update('topics',
                ['like_count' => $current_likes + 1],
                '`id` = :topic_id',
                ['topic_id' => $topic_id]
            );
            
            // 给主题作者增加积分
            if ($topic['user_id'] != $user_id) {
                handleUserAction('topic_liked', $topic['user_id'], ['topic_id' => $topic_id]);
                
                // 发送点赞通知
                include_once __DIR__ . '/mail_functions.php';
                sendInteractionNotification($topic['user_id'], 'topic_liked', [
                    'liker_id' => $user_id,
                    'topic_id' => $topic_id,
                    'topic_title' => $topic['title'],
                    'topic_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/topic.php?id=' . $topic_id
                ]);
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log('点赞主题失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 取消点赞主题
 * @param int $user_id 用户ID
 * @param int $topic_id 主题ID
 * @return bool
 */
function unlikeTopic($user_id, $topic_id) {
    try {
        $db = getDB();
        
        // 删除点赞记录
        $result = $db->delete(
            'topic_likes',
            '`user_id` = :user_id AND `topic_id` = :topic_id',
            ['user_id' => $user_id, 'topic_id' => $topic_id]
        );
        
        if ($result) {
            // 获取主题信息
            $topic = $db->findById('topics', $topic_id);
            if ($topic) {
                // 更新主题的点赞数
                $current_likes = isset($topic['like_count']) ? $topic['like_count'] : 0;
                $new_likes = max($current_likes - 1, 0);
                $db->update('topics',
                    ['like_count' => $new_likes],
                    '`id` = :topic_id',
                    ['topic_id' => $topic_id]
                );
            }
        }
        
        return $result;
    } catch (Exception $e) {
        error_log('取消点赞主题失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 检查用户是否点赞了主题
 * @param int $user_id 用户ID
 * @param int $topic_id 主题ID
 * @return bool
 */
function hasLikedTopic($user_id, $topic_id) {
    try {
        $db = getDB();
        
        $count = $db->fetchColumn(
            "SELECT COUNT(*) FROM `topic_likes` WHERE `user_id` = :user_id AND `topic_id` = :topic_id",
            ['user_id' => $user_id, 'topic_id' => $topic_id]
        );
        
        return $count > 0;
    } catch (Exception $e) {
        error_log('检查主题点赞状态失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 点赞回复
 * @param int $user_id 用户ID
 * @param int $reply_id 回复ID
 * @return bool
 */
function likeReply($user_id, $reply_id) {
    try {
        $db = getDB();
        
        // 检查是否已经点赞
        $exists = $db->fetchColumn(
            "SELECT COUNT(*) FROM `reply_likes` WHERE `user_id` = :user_id AND `reply_id` = :reply_id",
            ['user_id' => $user_id, 'reply_id' => $reply_id]
        );
        
        if ($exists) {
            return false;
        }
        
        // 开始事务
        $db->beginTransaction();
        
        try {
            // 插入点赞记录
            $db->insert('reply_likes', [
                'user_id' => $user_id,
                'reply_id' => $reply_id,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // 更新回复的点赞数
            $db->update('replies',
                ['like_count' => $db->raw('like_count + 1')],
                '`id` = :reply_id',
                ['reply_id' => $reply_id]
            );
            
            // 给回复作者增加积分
            $reply = $db->findById('replies', $reply_id);
            if ($reply && $reply['user_id'] != $user_id) {
                handleUserAction('reply_liked', $reply['user_id'], ['reply_id' => $reply_id]);
                
                // 获取主题信息
                $topic = $db->findById('topics', $reply['topic_id']);
                
                // 发送点赞通知
                include_once __DIR__ . '/mail_functions.php';
                sendInteractionNotification($reply['user_id'], 'topic_liked', [
                    'liker_id' => $user_id,
                    'topic_id' => $reply['topic_id'],
                    'topic_title' => $topic ? $topic['title'] : '未知主题',
                    'topic_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/topic.php?id=' . $reply['topic_id']
                ]);
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
        error_log('点赞回复失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 取消点赞回复
 * @param int $user_id 用户ID
 * @param int $reply_id 回复ID
 * @return bool
 */
function unlikeReply($user_id, $reply_id) {
    try {
        $db = getDB();
        
        // 开始事务
        $db->beginTransaction();
        
        try {
            // 删除点赞记录
            $result = $db->delete(
                'reply_likes',
                '`user_id` = :user_id AND `reply_id` = :reply_id',
                ['user_id' => $user_id, 'reply_id' => $reply_id]
            );
            
            if ($result > 0) {
                // 更新回复的点赞数
                $db->update('replies',
                    ['like_count' => $db->raw('GREATEST(like_count - 1, 0)')],
                    '`id` = :reply_id',
                    ['reply_id' => $reply_id]
                );
            }
            
            // 提交事务
            $db->commit();
            return $result > 0;
        } catch (Exception $e) {
            // 回滚事务
            $db->rollBack();
            throw $e;
        }
    } catch (Exception $e) {
        error_log('取消点赞回复失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 检查用户是否点赞了回复
 * @param int $user_id 用户ID
 * @param int $reply_id 回复ID
 * @return bool
 */
function hasLikedReply($user_id, $reply_id) {
    try {
        $db = getDB();
        
        $count = $db->fetchColumn(
            "SELECT COUNT(*) FROM `reply_likes` WHERE `user_id` = :user_id AND `reply_id` = :reply_id",
            ['user_id' => $user_id, 'reply_id' => $reply_id]
        );
        
        return $count > 0;
    } catch (Exception $e) {
        error_log('检查回复点赞状态失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 收藏主题
 * @param int $user_id 用户ID
 * @param int $topic_id 主题ID
 * @return bool
 */
function bookmarkTopic($user_id, $topic_id) {
    try {
        $db = getDB();
        
        // 检查是否已经收藏
        $exists = $db->fetchColumn(
            "SELECT COUNT(*) FROM `user_bookmarks` WHERE `user_id` = :user_id AND `topic_id` = :topic_id",
            ['user_id' => $user_id, 'topic_id' => $topic_id]
        );
        
        if ($exists) {
            return false;
        }
        
        // 插入收藏记录
        $db->insert('user_bookmarks', [
            'user_id' => $user_id,
            'topic_id' => $topic_id,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // 获取主题信息
        $topic = $db->findById('topics', $topic_id);
        if ($topic && $topic['user_id'] != $user_id) {
            // 发送收藏通知
            include_once __DIR__ . '/mail_functions.php';
            sendInteractionNotification($topic['user_id'], 'topic_bookmarked', [
                'bookmarker_id' => $user_id,
                'topic_id' => $topic_id,
                'topic_title' => $topic['title'],
                'topic_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/topic.php?id=' . $topic_id
            ]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log('收藏主题失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 取消收藏主题
 * @param int $user_id 用户ID
 * @param int $topic_id 主题ID
 * @return bool
 */
function removeBookmark($user_id, $topic_id) {
    try {
        $db = getDB();
        
        // 删除收藏记录
        $result = $db->delete(
            'user_bookmarks',
            '`user_id` = :user_id AND `topic_id` = :topic_id',
            ['user_id' => $user_id, 'topic_id' => $topic_id]
        );
        
        return $result > 0;
    } catch (Exception $e) {
        error_log('取消收藏主题失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 检查用户是否收藏了主题
 * @param int $user_id 用户ID
 * @param int $topic_id 主题ID
 * @return bool
 */
function hasBookmarkedTopic($user_id, $topic_id) {
    try {
        $db = getDB();
        
        $count = $db->fetchColumn(
            "SELECT COUNT(*) FROM `user_bookmarks` WHERE `user_id` = :user_id AND `topic_id` = :topic_id",
            ['user_id' => $user_id, 'topic_id' => $topic_id]
        );
        
        return $count > 0;
    } catch (Exception $e) {
        error_log('检查主题收藏状态失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 获取用户的收藏列表
 * @param int $user_id 用户ID
 * @param int $limit 限制数量
 * @return array
 */
function getUserBookmarks($user_id, $limit = 50) {
    try {
        $db = getDB();
        
        return $db->fetchAll(
            "SELECT t.*, b.created_at as bookmarked_at FROM `topics` t
            JOIN `user_bookmarks` b ON t.id = b.topic_id
            WHERE b.user_id = :user_id
            ORDER BY b.created_at DESC
            LIMIT :limit",
            ['user_id' => $user_id, 'limit' => $limit]
        );
    } catch (Exception $e) {
        error_log('获取用户收藏列表失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 添加主题标签
 * @param int $topic_id 主题ID
 * @param array $tags 标签数组
 * @return bool
 */
function addTopicTags($topic_id, $tags) {
    try {
        $db = getDB();
        
        // 检查是否支持事务
        $supportsTransaction = method_exists($db, 'beginTransaction');
        
        // 开始事务（如果支持）
        if ($supportsTransaction) {
            $db->beginTransaction();
        }
        
        try {
            // 删除旧标签
            $db->delete('topic_tags', '`topic_id` = :topic_id', ['topic_id' => $topic_id]);
            
            // 添加新标签
            foreach ($tags as $tag_name) {
                $tag_name = trim($tag_name);
                if (empty($tag_name)) {
                    continue;
                }
                
                // 查找或创建标签
                $tag = $db->fetch(
                    "SELECT * FROM `tags` WHERE `name` = :name",
                    ['name' => $tag_name]
                );
                
                if (!$tag) {
                    $tag_id = $db->insert('tags', [
                        'name' => $tag_name,
                        'slug' => strtolower(str_replace(' ', '-', $tag_name)),
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    $tag_id = $tag['id'];
                }
                
                // 关联标签到主题
                $db->insert('topic_tags', [
                    'topic_id' => $topic_id,
                    'tag_id' => $tag_id
                ]);
            }
            
            // 提交事务（如果支持）
            if ($supportsTransaction) {
                $db->commit();
            }
            
            return true;
        } catch (Exception $e) {
            // 回滚事务（如果支持）
            if ($supportsTransaction) {
                $db->rollBack();
            }
            throw $e;
        }
    } catch (Exception $e) {
        error_log('添加主题标签失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 获取主题的标签
 * @param int $topic_id 主题ID
 * @return array
 */
function getTopicTags($topic_id) {
    try {
        $db = getDB();
        
        return $db->fetchAll(
            "SELECT t.* FROM `tags` t
            JOIN `topic_tags` tt ON t.id = tt.tag_id
            WHERE tt.topic_id = :topic_id",
            ['topic_id' => $topic_id]
        );
    } catch (Exception $e) {
        error_log('获取主题标签失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 获取热门标签
 * @param int $limit 限制数量
 * @return array
 */
function getPopularTags($limit = 20) {
    try {
        $db = getDB();
        
        return $db->fetchAll(
            "SELECT t.*, COUNT(tt.topic_id) as count FROM `tags` t
            JOIN `topic_tags` tt ON t.id = tt.tag_id
            GROUP BY t.id
            ORDER BY count DESC
            LIMIT :limit",
            ['limit' => $limit]
        );
    } catch (Exception $e) {
        error_log('获取热门标签失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 获取热门标签（别名函数，与getPopularTags功能相同）
 * @param int $limit 限制数量
 * @return array
 */
function getHotTags($limit = 20) {
    return getPopularTags($limit);
}

/**
 * 初始化内容互动系统表结构
 */
function initContentSystem() {
    try {
        $db = getDB();
        
        // 初始化主题点赞表
        $db->query("CREATE TABLE IF NOT EXISTS `topic_likes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `topic_id` int(11) NOT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_topic` (`user_id`, `topic_id`),
            KEY `user_id` (`user_id`),
            KEY `topic_id` (`topic_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 初始化回复点赞表
        $db->query("CREATE TABLE IF NOT EXISTS `reply_likes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `reply_id` int(11) NOT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_reply` (`user_id`, `reply_id`),
            KEY `user_id` (`user_id`),
            KEY `reply_id` (`reply_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 初始化用户收藏表
        $db->query("CREATE TABLE IF NOT EXISTS `user_bookmarks` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `topic_id` int(11) NOT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_topic` (`user_id`, `topic_id`),
            KEY `user_id` (`user_id`),
            KEY `topic_id` (`topic_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 初始化标签表
        $db->query("CREATE TABLE IF NOT EXISTS `tags` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(50) NOT NULL,
            `slug` varchar(50) NOT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`),
            UNIQUE KEY `slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 初始化主题标签关联表
        $db->query("CREATE TABLE IF NOT EXISTS `topic_tags` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `topic_id` int(11) NOT NULL,
            `tag_id` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `topic_tag` (`topic_id`, `tag_id`),
            KEY `topic_id` (`topic_id`),
            KEY `tag_id` (`tag_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 为topics表添加like_count字段
        try {
            $db->query("ALTER TABLE `topics` ADD COLUMN `like_count` int(11) DEFAULT 0");
        } catch (Exception $e) {
            // 字段已存在，忽略错误
        }
        
        // 为replies表添加like_count字段
        try {
            $db->query("ALTER TABLE `replies` ADD COLUMN `like_count` int(11) DEFAULT 0");
        } catch (Exception $e) {
            // 字段已存在，忽略错误
        }
    } catch (Exception $e) {
        error_log('初始化内容互动系统失败：' . $e->getMessage());
    }
}

/**
 * 搜索标签
 * @param string $keyword 关键词
 * @param int $limit 限制数量
 * @return array
 */
function searchTags($keyword, $limit = 10) {
    try {
        $db = getDB();
        
        return $db->fetchAll(
            "SELECT * FROM `tags` WHERE `name` LIKE :keyword OR `slug` LIKE :keyword ORDER BY `name` LIMIT :limit",
            ['keyword' => '%' . $keyword . '%', 'limit' => $limit]
        );
    } catch (Exception $e) {
        error_log('搜索标签失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 获取标签相关的主题
 * @param int $tag_id 标签ID
 * @param int $limit 限制数量
 * @return array
 */
function getTopicsByTag($tag_id, $limit = 50) {
    try {
        $db = getDB();
        
        return $db->fetchAll(
            "SELECT t.* FROM `topics` t
            JOIN `topic_tags` tt ON t.id = tt.topic_id
            WHERE tt.tag_id = :tag_id AND t.status = 'published'
            ORDER BY t.created_at DESC
            LIMIT :limit",
            ['tag_id' => $tag_id, 'limit' => $limit]
        );
    } catch (Exception $e) {
        error_log('获取标签相关主题失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 获取主题的点赞数量
 * @param int $topic_id 主题ID
 * @return int 点赞数量
 */
function getTopicLikeCount($topic_id) {
    try {
        $db = getDB();
        $topic = $db->findById('topics', $topic_id);
        return $topic['like_count'] ?? 0;
    } catch (Exception $e) {
        error_log('获取主题点赞数量失败：' . $e->getMessage());
        return 0;
    }
}
?>