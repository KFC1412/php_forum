<?php

namespace App\Models;

use App\Storage\StorageFactory;

class Message {
    private $db;
    private $prefix;
    
    public function __construct() {
        $this->db = StorageFactory::create();
        $this->prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    }
    
    public function getById($id) {
        return $this->db->fetch(
            "SELECT * FROM `{$this->prefix}messages` WHERE `id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function create($data) {
        return $this->db->insert("{$this->prefix}messages", $data);
    }
    
    public function update($id, $data) {
        return $this->db->update(
            "{$this->prefix}messages",
            $data,
            "`id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function delete($id) {
        return $this->db->delete(
            "{$this->prefix}messages",
            "`id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function getByUserId($userId, $page = 1, $perPage = 20) {
        // 获取总数
        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->prefix}messages` WHERE `receiver_id` = :receiver_id",
            ['receiver_id' => (string)$userId]
        );
        
        // 计算偏移量
        $offset = ($page - 1) * $perPage;
        
        // 获取消息列表
        $messages = $this->db->fetchAll(
            "SELECT * FROM `{$this->prefix}messages` WHERE `receiver_id` = :receiver_id ORDER BY `created_at` DESC LIMIT :offset, :limit",
            ['receiver_id' => (string)$userId, 'offset' => $offset, 'limit' => $perPage]
        );
        
        return [
            'messages' => $messages,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }
    
    public function getBySenderId($senderId, $page = 1, $perPage = 20) {
        // 获取总数
        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->prefix}messages` WHERE `sender_id` = :sender_id",
            ['sender_id' => (string)$senderId]
        );
        
        // 计算偏移量
        $offset = ($page - 1) * $perPage;
        
        // 获取消息列表
        $messages = $this->db->fetchAll(
            "SELECT * FROM `{$this->prefix}messages` WHERE `sender_id` = :sender_id ORDER BY `created_at` DESC LIMIT :offset, :limit",
            ['sender_id' => (string)$senderId, 'offset' => $offset, 'limit' => $perPage]
        );
        
        return [
            'messages' => $messages,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }
    
    public function getThread($userId1, $userId2, $page = 1, $perPage = 20) {
        // 获取总数
        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->prefix}messages` WHERE (sender_id = :user1 AND receiver_id = :user2) OR (sender_id = :user2 AND receiver_id = :user1)",
            ['user1' => (string)$userId1, 'user2' => (string)$userId2]
        );
        
        // 计算偏移量
        $offset = ($page - 1) * $perPage;
        
        // 获取消息列表
        $messages = $this->db->fetchAll(
            "SELECT * FROM `{$this->prefix}messages` WHERE (sender_id = :user1 AND receiver_id = :user2) OR (sender_id = :user2 AND receiver_id = :user1) ORDER BY `created_at` ASC LIMIT :offset, :limit",
            ['user1' => (string)$userId1, 'user2' => (string)$userId2, 'offset' => $offset, 'limit' => $perPage]
        );
        
        // 标记已读
        $this->db->update(
            "{$this->prefix}messages",
            ['status' => 'read'],
            "receiver_id = :receiver_id AND sender_id = :sender_id AND status = 'unread'",
            ['receiver_id' => (string)$userId1, 'sender_id' => (string)$userId2]
        );
        
        return [
            'messages' => $messages,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }
    
    public function getUnreadCount($userId) {
        return $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->prefix}messages` WHERE `receiver_id` = :receiver_id AND `status` = 'unread'",
            ['receiver_id' => (string)$userId]
        );
    }
    
    public function markAsRead($id) {
        return $this->db->update(
            "{$this->prefix}messages",
            ['status' => 'read'],
            "`id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function markAllAsRead($userId) {
        return $this->db->update(
            "{$this->prefix}messages",
            ['status' => 'read'],
            "`receiver_id` = :receiver_id AND `status` = 'unread'",
            ['receiver_id' => (string)$userId]
        );
    }
}

class InteractionMessage {
    private $db;
    private $prefix;
    
    public function __construct() {
        $this->db = StorageFactory::create();
        $this->prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    }
    
    public function create($data) {
        return $this->db->insert("{$this->prefix}interaction_messages", $data);
    }
    
    public function getByUserId($userId, $page = 1, $perPage = 20) {
        // 获取总数
        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->prefix}interaction_messages` WHERE `user_id` = :user_id",
            ['user_id' => (string)$userId]
        );
        
        // 计算偏移量
        $offset = ($page - 1) * $perPage;
        
        // 获取消息列表
        $messages = $this->db->fetchAll(
            "SELECT * FROM `{$this->prefix}interaction_messages` WHERE `user_id` = :user_id ORDER BY `created_at` DESC LIMIT :offset, :limit",
            ['user_id' => (string)$userId, 'offset' => $offset, 'limit' => $perPage]
        );
        
        return [
            'messages' => $messages,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }
    
    public function markAsRead($id) {
        return $this->db->update(
            "{$this->prefix}interaction_messages",
            ['status' => 'read'],
            "`id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function markAllAsRead($userId) {
        return $this->db->update(
            "{$this->prefix}interaction_messages",
            ['status' => 'read'],
            "`user_id` = :user_id AND `status` = 'unread'",
            ['user_id' => (string)$userId]
        );
    }
}

class SystemNotification {
    private $db;
    private $prefix;
    
    public function __construct() {
        $this->db = StorageFactory::create();
        $this->prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    }
    
    public function create($data) {
        return $this->db->insert("{$this->prefix}system_notifications", $data);
    }
    
    public function getByUserId($userId, $page = 1, $perPage = 20) {
        // 获取总数
        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->prefix}system_notifications` WHERE `user_id` = :user_id",
            ['user_id' => (string)$userId]
        );
        
        // 计算偏移量
        $offset = ($page - 1) * $perPage;
        
        // 获取通知列表
        $notifications = $this->db->fetchAll(
            "SELECT * FROM `{$this->prefix}system_notifications` WHERE `user_id` = :user_id ORDER BY `created_at` DESC LIMIT :offset, :limit",
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
    
    public function markAsRead($id) {
        return $this->db->update(
            "{$this->prefix}system_notifications",
            ['status' => 'read'],
            "`id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function markAllAsRead($userId) {
        return $this->db->update(
            "{$this->prefix}system_notifications",
            ['status' => 'read'],
            "`user_id` = :user_id AND `status` = 'unread'",
            ['user_id' => (string)$userId]
        );
    }
}