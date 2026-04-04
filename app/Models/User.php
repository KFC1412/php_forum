<?php

namespace App\Models;

use App\Storage\StorageFactory;

class User {
    private $db;
    private $prefix;
    
    public function __construct() {
        $this->db = StorageFactory::create();
        $this->prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    }
    
    public function getById($id) {
        return $this->db->fetch(
            "SELECT * FROM `{$this->prefix}users` WHERE `id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function getByUsername($username) {
        return $this->db->fetch(
            "SELECT * FROM `{$this->prefix}users` WHERE `username` = :username",
            ['username' => $username]
        );
    }
    
    public function getByEmail($email) {
        return $this->db->fetch(
            "SELECT * FROM `{$this->prefix}users` WHERE `email` = :email",
            ['email' => $email]
        );
    }
    
    public function getByMobile($mobile) {
        return $this->db->fetch(
            "SELECT * FROM `{$this->prefix}users` WHERE `mobile` = :mobile",
            ['mobile' => $mobile]
        );
    }
    
    public function create($data) {
        return $this->db->insert("{$this->prefix}users", $data);
    }
    
    public function update($id, $data) {
        return $this->db->update(
            "{$this->prefix}users",
            $data,
            "`id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function delete($id) {
        return $this->db->delete(
            "{$this->prefix}users",
            "`id` = :id",
            ['id' => (string)$id]
        );
    }
    
    public function getAll($page = 1, $perPage = 20, $filters = []) {
        $where = [];
        $params = [];
        
        if (!empty($filters['search'])) {
            $where[] = "(`username` LIKE :search OR `email` LIKE :search OR `mobile` LIKE :search)";
            $params['search'] = "%{$filters['search']}%";
        }
        
        if (!empty($filters['role'])) {
            $where[] = "`role` = :role";
            $params['role'] = $filters['role'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "`status` = :status";
            $params['status'] = $filters['status'];
        }
        
        // 排除系统用户
        $where[] = "`role` != 'system'";
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // 获取总数
        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->prefix}users` {$whereClause}",
            $params
        );
        
        // 计算偏移量
        $offset = ($page - 1) * $perPage;
        
        // 获取用户列表
        $users = $this->db->fetchAll(
            "SELECT * FROM `{$this->prefix}users` {$whereClause} ORDER BY `id` DESC LIMIT :offset, :limit",
            array_merge($params, ['offset' => $offset, 'limit' => $perPage])
        );
        
        return [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }
    
    public function updatePoints($userId, $points) {
        return $this->db->update(
            "{$this->prefix}users",
            ['points' => $points, 'updated_at' => date('Y-m-d H:i:s')],
            "`id` = :id",
            ['id' => (string)$userId]
        );
    }
    
    public function updateExperience($userId, $experience) {
        return $this->db->update(
            "{$this->prefix}users",
            ['experience' => $experience, 'updated_at' => date('Y-m-d H:i:s')],
            "`id` = :id",
            ['id' => (string)$userId]
        );
    }
    
    public function updateLevel($userId, $level) {
        return $this->db->update(
            "{$this->prefix}users",
            ['level' => $level, 'updated_at' => date('Y-m-d H:i:s')],
            "`id` = :id",
            ['id' => (string)$userId]
        );
    }
    
    public function updateBadge($userId, $badge) {
        return $this->db->update(
            "{$this->prefix}users",
            ['badge' => $badge, 'updated_at' => date('Y-m-d H:i:s')],
            "`id` = :id",
            ['id' => (string)$userId]
        );
    }
    
    public function getUserRoles() {
        return [
            'user' => '普通用户',
            'moderator' => '版主',
            'admin' => '管理员',
            'system' => '系统用户'
        ];
    }
    
    public function getUserStatuses() {
        return [
            'active' => '活跃',
            'inactive' => '非活跃',
            'banned' => '已封禁'
        ];
    }
}