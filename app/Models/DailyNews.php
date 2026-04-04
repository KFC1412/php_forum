<?php

namespace App\Models;

use App\Storage\StorageFactory;

class DailyNews {
    private $db;
    private $prefix;
    
    public function __construct() {
        $this->db = StorageFactory::create();
        $this->prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    }
    
    public function getLatest() {
        return $this->db->fetch(
            "SELECT * FROM `{$this->prefix}daily_news` ORDER BY `created_at` DESC LIMIT 1"
        );
    }
    
    public function getAll($page = 1, $perPage = 10) {
        // 获取总数
        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->prefix}daily_news`"
        );
        
        // 计算偏移量
        $offset = ($page - 1) * $perPage;
        
        // 获取新闻列表
        $news = $this->db->fetchAll(
            "SELECT * FROM `{$this->prefix}daily_news` ORDER BY `created_at` DESC LIMIT :offset, :limit",
            ['offset' => $offset, 'limit' => $perPage]
        );
        
        return [
            'news' => $news,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }
    
    public function create($data) {
        return $this->db->insert("{$this->prefix}daily_news", $data);
    }
    
    public function update($id, $data) {
        return $this->db->update(
            "{$this->prefix}daily_news",
            $data,
            "`id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function delete($id) {
        return $this->db->delete(
            "{$this->prefix}daily_news",
            "`id` = :id",
            ['id' => (string)$id]
        );
    }
}