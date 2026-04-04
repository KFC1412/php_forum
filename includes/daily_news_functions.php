<?php
/**
 * 每日热点资讯功能函数
 * 用于管理每日60秒热点资讯
 */

/**
 * 验证表名是否在白名单中
 * @param string $table_name 表名
 * @return string 验证后的表名
 */
function validateTableName($table_name) {
    $allowed_tables = ['daily_news', 'system_accounts'];
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    
    foreach ($allowed_tables as $allowed) {
        if ($table_name === "{$prefix}{$allowed}") {
            return $table_name;
        }
    }
    
    throw new Exception('无效的表名');
}

/**
 * 获取每日热点资讯列表
 * @param int $limit 限制数量
 * @param int $offset 偏移量
 * @param string $category 分类（可选）
 * @param string $status 状态（可选：published, draft, archived）
 * @return array 资讯列表
 */
function getDailyNewsList($limit = 10, $offset = 0, $category = null, $status = 'published') {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        $table_name = validateTableName("{$prefix}daily_news");
        $where = ["`status` = ?"];
        $params = [$status];
        
        if ($category) {
            $where[] = "`category` = ?";
            $params[] = $category;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $news = $db->fetchAll(
            "SELECT * FROM `{$table_name}` WHERE {$where_clause} ORDER BY `publish_date` DESC, `created_at` DESC LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );
        
        return $news ?: [];
    } catch (Exception $e) {
        error_log('获取每日热点列表失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 获取单条热点资讯
 * @param int $id 资讯ID
 * @return array|null 资讯信息或null
 */
function getDailyNews($id) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        $news = $db->fetch(
            "SELECT * FROM `{$prefix}daily_news` WHERE `id` = ?",
            [$id]
        );
        
        return $news;
    } catch (Exception $e) {
        error_log('获取每日热点失败：' . $e->getMessage());
        return null;
    }
}

/**
 * 获取指定日期的热点资讯
 * @param string $date 日期（Y-m-d格式）
 * @return array|null 资讯信息或null
 */
function getDailyNewsByDate($date) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        $news = $db->fetch(
            "SELECT * FROM `{$prefix}daily_news` WHERE `publish_date` = ? AND `status` = 'published' ORDER BY `created_at` DESC LIMIT 1",
            [$date]
        );
        
        return $news;
    } catch (Exception $e) {
        error_log('获取指定日期热点失败：' . $e->getMessage());
        return null;
    }
}

/**
 * 获取今日热点资讯
 * @return array|null 资讯信息或null
 */
function getTodayNews() {
    return getDailyNewsByDate(date('Y-m-d'));
}

/**
 * 创建热点资讯
 * @param array $data 资讯数据
 * @return int|bool 新资讯ID或false
 */
function createDailyNews($data) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        $insert_data = [
            'title' => $data['title'],
            'content' => $data['content'],
            'summary' => $data['summary'] ?? null,
            'source' => $data['source'] ?? null,
            'source_url' => $data['source_url'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'category' => $data['category'] ?? 'general',
            'status' => $data['status'] ?? 'published',
            'publish_date' => $data['publish_date'] ?? date('Y-m-d'),
            'view_count' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $db->insert("{$prefix}daily_news", $insert_data);
        $new_id = $db->lastInsertId();
        
        return $new_id;
    } catch (Exception $e) {
        error_log('创建每日热点失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 更新热点资讯
 * @param int $id 资讯ID
 * @param array $data 更新数据
 * @return bool 是否成功
 */
function updateDailyNews($id, $data) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        $update_data = [
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // 只更新提供的字段
        $allowed_fields = ['title', 'content', 'summary', 'source', 'source_url', 'image_url', 'category', 'status', 'publish_date'];
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = $data[$field];
            }
        }
        
        $db->update("{$prefix}daily_news", $update_data, "`id` = :id", ['id' => $id]);
        
        return true;
    } catch (Exception $e) {
        error_log('更新每日热点失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 删除热点资讯
 * @param int $id 资讯ID
 * @return bool 是否成功
 */
function deleteDailyNews($id) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        $db->delete("{$prefix}daily_news", "`id` = :id", ['id' => $id]);
        
        return true;
    } catch (Exception $e) {
        error_log('删除每日热点失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 增加资讯浏览次数
 * @param int $id 资讯ID
 * @return bool 是否成功
 */
function incrementNewsViewCount($id) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        $db->query(
            "UPDATE `{$prefix}daily_news` SET `view_count` = `view_count` + 1 WHERE `id` = ?",
            [$id]
        );
        
        return true;
    } catch (Exception $e) {
        error_log('增加资讯浏览次数失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 获取热点资讯分类列表
 * @return array 分类列表
 */
function getNewsCategories() {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        $categories = $db->fetchAll(
            "SELECT DISTINCT `category` FROM `{$prefix}daily_news` WHERE `status` = 'published' ORDER BY `category`"
        );
        
        return array_column($categories, 'category');
    } catch (Exception $e) {
        error_log('获取热点分类失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 获取热点资讯总数
 * @param string $category 分类（可选）
 * @param string $status 状态（可选）
 * @return int 资讯总数
 */
function getDailyNewsCount($category = null, $status = 'published') {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        $where = ["`status` = ?"];
        $params = [$status];
        
        if ($category) {
            $where[] = "`category` = ?";
            $params[] = $category;
        }
        
        $where_clause = implode(' AND ', $where);
        
        $count = $db->fetchColumn(
            "SELECT COUNT(*) FROM `{$prefix}daily_news` WHERE {$where_clause}",
            $params
        );
        
        return (int)$count;
    } catch (Exception $e) {
        error_log('获取热点总数失败：' . $e->getMessage());
        return 0;
    }
}

/**
 * 搜索热点资讯
 * @param string $keyword 关键词
 * @param int $limit 限制数量
 * @param int $offset 偏移量
 * @return array 搜索结果
 */
function searchDailyNews($keyword, $limit = 10, $offset = 0) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        $search_term = "%{$keyword}%";
        
        $news = $db->fetchAll(
            "SELECT * FROM `{$prefix}daily_news` WHERE (`title` LIKE ? OR `content` LIKE ? OR `summary` LIKE ?) AND `status` = 'published' ORDER BY `publish_date` DESC LIMIT ? OFFSET ?",
            [$search_term, $search_term, $search_term, $limit, $offset]
        );
        
        return $news ?: [];
    } catch (Exception $e) {
        error_log('搜索每日热点失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 获取热点资讯归档（按月份分组）
 * @return array 归档列表
 */
function getDailyNewsArchive() {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        $archive = $db->fetchAll(
            "SELECT DATE_FORMAT(`publish_date`, '%Y-%m') as month, COUNT(*) as count FROM `{$prefix}daily_news` WHERE `status` = 'published' GROUP BY month ORDER BY month DESC"
        );
        
        return $archive ?: [];
    } catch (Exception $e) {
        error_log('获取热点归档失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 自动抓取每日热点（示例函数，需要根据实际情况实现）
 * @return bool 是否成功
 */
function fetchDailyNewsAuto() {
    // 这里可以实现自动抓取逻辑
    // 例如：从RSS源、API或其他网站抓取热点资讯
    
    // 示例：检查今天是否已有热点
    $today_news = getTodayNews();
    if ($today_news) {
        // 今天已有热点，不需要抓取
        return true;
    }
    
    // TODO: 实现实际的抓取逻辑
    // 可以使用 cURL 或 file_get_contents 获取远程数据
    // 然后解析并存储到数据库
    
    return false;
}

/**
 * 初始化每日60秒热点资讯主题
 * @return bool 是否成功
 */
function init60sNewsTopic() {
    try {
        $storage_type = getStorageType();
        
        if ($storage_type === 'json') {
            $topics_file = __DIR__ . '/../storage/json/forum_topics.json';
            
            // 读取现有主题数据
            if (file_exists($topics_file)) {
                $topics_data = json_decode(file_get_contents($topics_file), true);
            } else {
                $topics_data = ['data' => [], 'auto_increment' => 1];
            }
            
            // 检查是否已存在60秒热点资讯主题
            $exists = false;
            foreach ($topics_data['data'] as $topic) {
                if (isset($topic['id']) && $topic['id'] == 0) {
                    $exists = true;
                    break;
                }
            }
            
            if (!$exists) {
                // 创建新的60秒热点资讯主题，使用ID 0
                $new_topic = [
                    'id' => 0, // 特殊ID，不占用常规主题ID
                    'category_id' => 6, // 假设6是资讯分类
                    'user_id' => 'system', // 作者为系统通知
                    'title' => '每日60秒热点资讯 ' . date('Y年m月d日'),
                    'content' => '<iframe src="/60s" width="100%" height="1200px" frameborder="0" scrolling="auto"></iframe>',
                    'status' => 'published',
                    'is_sticky' => 1, // 置顶
                    'is_locked' => 0,
                    'is_recommended' => 1, // 推荐
                    'view_count' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'last_post_id' => null,
                    'last_post_user_id' => 'system',
                    'last_post_time' => date('Y-m-d H:i:s'),
                    'created_ip' => '127.0.0.1',
                    'ip_info' => '系统自动创建'
                ];
                
                array_unshift($topics_data['data'], $new_topic);
                
                // 保存数据
                file_put_contents($topics_file, json_encode($topics_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log('初始化60秒热点资讯主题失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 更新每日60秒热点资讯主题
 * @return bool 是否成功
 */
function update60sNewsTopic() {
    try {
        $storage_type = getStorageType();
        
        if ($storage_type === 'json') {
            $topics_file = __DIR__ . '/../storage/json/forum_topics.json';
            
            // 读取现有主题数据
            if (file_exists($topics_file)) {
                $topics_data = json_decode(file_get_contents($topics_file), true);
            } else {
                return init60sNewsTopic();
            }
            
            // 查找60秒热点资讯主题
            $topic_index = -1;
            foreach ($topics_data['data'] as $index => $topic) {
                if (isset($topic['id']) && $topic['id'] == 0) {
                    $topic_index = $index;
                    break;
                }
            }
            
            if ($topic_index >= 0) {
                // 更新主题信息
                $topics_data['data'][$topic_index]['title'] = '每日60秒热点资讯 ' . date('Y年m月d日');
                $topics_data['data'][$topic_index]['updated_at'] = date('Y-m-d H:i:s');
                $topics_data['data'][$topic_index]['last_post_time'] = date('Y-m-d H:i:s');
                
                // 保存数据
                file_put_contents($topics_file, json_encode($topics_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                // 主题不存在，初始化
                return init60sNewsTopic();
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log('更新60秒热点资讯主题失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 检查并更新每日60秒热点资讯
 * @return bool 是否成功
 */
function checkAndUpdate60sNews() {
    // 检查是否需要更新（每天只更新一次）
    $last_update_file = __DIR__ . '/../storage/cache/60s_last_update.txt';
    $today = date('Y-m-d');
    
    if (file_exists($last_update_file)) {
        $last_update = file_get_contents($last_update_file);
        if ($last_update == $today) {
            // 今天已经更新过了
            return true;
        }
    }
    
    // 更新60秒热点资讯主题
    $result = update60sNewsTopic();
    
    if ($result) {
        // 记录更新时间
        file_put_contents($last_update_file, $today);
    }
    
    return $result;
}

/**
 * 获取每日60秒热点资讯主题
 * @return array|null 主题数据或null
 */
function get60sNewsTopic() {
    try {
        $storage_type = getStorageType();
        
        if ($storage_type === 'json') {
            $topics_file = __DIR__ . '/../storage/json/forum_topics.json';
            
            // 读取主题数据
            if (file_exists($topics_file)) {
                $topics_data = json_decode(file_get_contents($topics_file), true);
                
                // 查找60秒热点资讯主题
                foreach ($topics_data['data'] as $topic) {
                    if (isset($topic['id']) && $topic['id'] == 0) {
                        // 为主题添加必要的字段
                        $topic['username'] = '系统通知';
                        $topic['role'] = 'system';
                        $topic['category_title'] = '每日热点';
                        $topic['category_id'] = 6;
                        return $topic;
                    }
                }
            }
        }
        
        return null;
    } catch (Exception $e) {
        error_log('获取60秒热点资讯主题失败：' . $e->getMessage());
        return null;
    }
}
