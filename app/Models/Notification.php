<?php

namespace App\Models;

use App\Storage\StorageFactory;

class Notification {
    private $db;
    private $prefix;
    
    public function __construct() {
        $this->db = StorageFactory::create();
        $this->prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    }
    
    public function getById($id) {
        return $this->db->fetch(
            "SELECT * FROM `{$this->prefix}notifications` WHERE `id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function create($data) {
        return $this->db->insert("{$this->prefix}notifications", $data);
    }
    
    public function update($id, $data) {
        return $this->db->update(
            "{$this->prefix}notifications",
            $data,
            "`id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function delete($id) {
        return $this->db->delete(
            "{$this->prefix}notifications",
            "`id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function getByUserId($userId, $page = 1, $perPage = 20) {
        // 获取总数
        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->prefix}notifications` WHERE `user_id` = :user_id",
            ['user_id' => (string)$userId]
        );
        
        // 计算偏移量
        $offset = ($page - 1) * $perPage;
        
        // 获取通知列表
        $notifications = $this->db->fetchAll(
            "SELECT * FROM `{$this->prefix}notifications` WHERE `user_id` = :user_id ORDER BY `created_at` DESC LIMIT :offset, :limit",
            ['user_id' => (string)$userId, 'offset' => $offset, 'limit' => $perPage]
        );
        
        return [
            'notifications' => $notifications,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }
    
    public function getUnreadCount($userId) {
        return $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->prefix}notifications` WHERE `user_id` = :user_id AND `status` = 'unread'",
            ['user_id' => (string)$userId]
        );
    }
    
    public function markAsRead($id) {
        return $this->db->update(
            "{$this->prefix}notifications",
            ['status' => 'read'],
            "`id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function markAllAsRead($userId) {
        return $this->db->update(
            "{$this->prefix}notifications",
            ['status' => 'read'],
            "`user_id` = :user_id AND `status` = 'unread'",
            ['user_id' => (string)$userId]
        );
    }
    
    public function createSystemNotification($userId, $title, $content, $type = 'system') {
        $notificationData = [
            'user_id' => $userId,
            'title' => $title,
            'content' => $content,
            'type' => $type,
            'status' => 'unread',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->create($notificationData);
    }
    
    public function createInteractionNotification($userId, $type, $data) {
        $title = '';
        $content = '';
        
        switch ($type) {
            case 'topic_reply':
                $title = '新回复通知';
                $content = "用户 {$data['replier_name']} (ID: {$data['replier_id']}) 回复了你的主题: {$data['topic_title']}";
                break;
            case 'post_like':
                $title = '点赞通知';
                $content = "用户 {$data['user_name']} (ID: {$data['user_id']}) 点赞了你的帖子";
                break;
            case 'friend_request':
                $title = '好友请求';
                $content = "用户 {$data['user_name']} (ID: {$data['user_id']}) 向你发送了好友请求";
                break;
            default:
                $title = '互动通知';
                $content = '你有新的互动';
        }
        
        $notificationData = [
            'user_id' => $userId,
            'title' => $title,
            'content' => $content,
            'type' => 'interaction',
            'status' => 'unread',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->create($notificationData);
    }
}