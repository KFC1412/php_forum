<?php

namespace App\Models;

use App\Storage\StorageFactory;

class Post {
    private $db;
    private $prefix;
    
    public function __construct() {
        $this->db = StorageFactory::create();
        $this->prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    }
    
    public function getById($id) {
        return $this->db->fetch(
            "SELECT * FROM `{$this->prefix}posts` WHERE `id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function create($data) {
        return $this->db->insert("{$this->prefix}posts", $data);
    }
    
    public function update($id, $data) {
        return $this->db->update(
            "{$this->prefix}posts",
            $data,
            "`id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function delete($id) {
        return $this->db->delete(
            "{$this->prefix}posts",
            "`id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function getByTopicId($topicId, $page = 1, $perPage = 20) {
        // 获取总数
        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->prefix}posts` WHERE `topic_id` = :topic_id",
            ['topic_id' => (string)$topicId]
        );
        
        // 计算偏移量
        $offset = ($page - 1) * $perPage;
        
        // 获取帖子列表
        $posts = $this->db->fetchAll(
            "SELECT * FROM `{$this->prefix}posts` WHERE `topic_id` = :topic_id ORDER BY `created_at` ASC LIMIT :offset, :limit",
            ['topic_id' => (string)$topicId, 'offset' => $offset, 'limit' => $perPage]
        );
        
        return [
            'posts' => $posts,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }
    
    public function getByUserId($userId, $page = 1, $perPage = 20) {
        // 获取总数
        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->prefix}posts` WHERE `user_id` = :user_id",
            ['user_id' => (string)$userId]
        );
        
        // 计算偏移量
        $offset = ($page - 1) * $perPage;
        
        // 获取帖子列表
        $posts = $this->db->fetchAll(
            "SELECT * FROM `{$this->prefix}posts` WHERE `user_id` = :user_id ORDER BY `created_at` DESC LIMIT :offset, :limit",
            ['user_id' => (string)$userId, 'offset' => $offset, 'limit' => $perPage]
        );
        
        return [
            'posts' => $posts,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }
    
    public function getLatestPosts($limit = 10) {
        return $this->db->fetchAll(
            "SELECT * FROM `{$this->prefix}posts` ORDER BY `created_at` DESC LIMIT :limit",
            ['limit' => $limit]
        );
    }
}