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
