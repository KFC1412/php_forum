<?php

namespace App\Models;

use App\Storage\StorageFactory;

class Topic {
    private $db;
    private $prefix;
    
    public function __construct() {
        $this->db = StorageFactory::create();
        $this->prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    }
    
    public function getById($id) {
        return $this->db->fetch(
            "SELECT * FROM `{$this->prefix}topics` WHERE `id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function create($data) {
        return $this->db->insert("{$this->prefix}topics", $data);
    }
    
    public function update($id, $data) {
        return $this->db->update(
            "{$this->prefix}topics",
            $data,
            "`id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function delete($id) {
        return $this->db->delete(
            "{$this->prefix}topics",
            "`id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function getAll($page = 1, $perPage = 20, $filters = []) {
        $where = [];
        $params = [];
        
        if (!empty($filters['category_id'])) {
            $where[] = "`category_id` = :category_id";
            $params['category_id'] = $filters['category_id'];
        }
        
        if (!empty($filters['user_id'])) {
            $where[] = "`user_id` = :user_id";
            $params['user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "`title` LIKE :search OR `content` LIKE :search";
            $params['search'] = "%{$filters['search']}%";
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // 获取总数
        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->prefix}topics` {$whereClause}",
            $params
        );
        
        // 计算偏移量
        $offset = ($page - 1) * $perPage;
        
        // 获取主题列表
        $topics = $this->db->fetchAll(
            "SELECT * FROM `{$this->prefix}topics` {$whereClause} ORDER BY `created_at` DESC LIMIT :offset, :limit",
            array_merge($params, ['offset' => $offset, 'limit' => $perPage])
        );
        
        return [
            'topics' => $topics,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }
    
    public function getHotTopics($limit = 10) {
        return $this->db->fetchAll(
            "SELECT * FROM `{$this->prefix}topics` ORDER BY `views` DESC, `replies` DESC LIMIT :limit",
            ['limit' => $limit]
        );
    }
    
    public function incrementViews($id) {
        return $this->db->update(
            "{$this->prefix}topics",
            ['views' => new \PDOStatement('views + 1'), 'updated_at' => date('Y-m-d H:i:s')],
            "`id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function incrementReplies($id) {
        return $this->db->update(
            "{$this->prefix}topics",
            ['replies' => new \PDOStatement('replies + 1'), 'updated_at' => date('Y-m-d H:i:s')],
            "`id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function decrementReplies($id) {
        return $this->db->update(
            "{$this->prefix}topics",
            ['replies' => new \PDOStatement('replies - 1'), 'updated_at' => date('Y-m-d H:i:s')],
            "`id` = :id",
            ['id' => (string)$id]
        );
    }
}