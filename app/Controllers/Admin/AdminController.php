<?php

namespace App\Controllers\Admin;

use App\Models\User;
use App\Models\Topic;
use App\Models\Post;
use App\Models\Message;
use App\Models\Notification;
use App\Services\Container;

class AdminController {
    private $userModel;
    private $topicModel;
    private $postModel;
    private $messageModel;
    private $notificationModel;
    private $container;
    
    public function __construct(Container $container) {
        $this->userModel = new User();
        $this->topicModel = new Topic();
        $this->postModel = new Post();
        $this->messageModel = new Message();
        $this->notificationModel = new Notification();
        $this->container = $container;
    }
    
    public function dashboard() {
        // 检查权限
        if (!$this->checkPermission()) {
            return [
                'error' => '没有权限访问管理后台',
                'redirect' => '/'
            ];
        }
        
        // 获取统计数据
        $totalUsers = $this->userModel->getAll(1, 10000)['total'];
        $totalTopics = $this->topicModel->getAll(1, 10000)['total'];
        $totalPosts = $this->postModel->getLatestPosts(10000);
        $totalPosts = count($totalPosts);
        
        return [
            'view' => 'admin/dashboard',
            'totalUsers' => $totalUsers,
            'totalTopics' => $totalTopics,
            'totalPosts' => $totalPosts
        ];
    }
    
    public function users() {
        // 检查权限
        if (!$this->checkPermission()) {
            return [
                'error' => '没有权限访问管理后台',
                'redirect' => '/'
            ];
        }
        
        $page = $_GET['page'] ?? 1;
        $perPage = 20;
        $filters = [];
        
        if (!empty($_GET['search'])) {
            $filters['search'] = $_GET['search'];
        }
        
        if (!empty($_GET['role'])) {
            $filters['role'] = $_GET['role'];
        }
        
        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        
        $users = $this->userModel->getAll($page, $perPage, $filters);
        $roles = $this->userModel->getUserRoles();
        $statuses = $this->userModel->getUserStatuses();
        
        return [
            'view' => 'admin/users',
            'users' => $users['users'],
            'total' => $users['total'],
            'page' => $users['page'],
            'perPage' => $users['perPage'],
            'totalPages' => $users['totalPages'],
            'roles' => $roles,
            'statuses' => $statuses,
            'filters' => $filters
        ];
    }
    
    public function editUser($request, $id) {
        // 检查权限
        if (!$this->checkPermission()) {
            return [
                'error' => '没有权限访问管理后台',
                'redirect' => '/'
            ];
        }
        
        $user = $this->userModel->getById($id);
        
        if (!$user) {
            return [
                'error' => '用户不存在'
            ];
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $mobile = $_POST['mobile'] ?? '';
            $role = $_POST['role'] ?? 'user';
            $status = $_POST['status'] ?? 'active';
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($email) || empty($mobile)) {
                return [
                    'error' => '请填写所有必填字段'
                ];
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'error' => '邮箱格式不正确'
                ];
            }
            
            if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
                return [
                    'error' => '手机号格式不正确'
                ];
            }
            
            $updateData = [
                'username' => $username,
                'email' => $email,
                'mobile' => $mobile,
                'role' => $role,
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    return [
                        'error' => '密码长度必须至少6个字符'
                    ];
                }
                $updateData['password'] = password_hash($password, PASSWORD_DEFAULT);
            }
            
            if ($this->userModel->update($id, $updateData)) {
                return [
                    'success' => '用户信息更新成功',
                    'redirect' => '/admin/users'
                ];
            } else {
                return [
                    'error' => '更新失败，请重试'
                ];
            }
        }
        
        $roles = $this->userModel->getUserRoles();
        $statuses = $this->userModel->getUserStatuses();
        
        return [
            'view' => 'admin/edit_user',
            'user' => $user,
            'roles' => $roles,
            'statuses' => $statuses
        ];
    }
    
    public function deleteUser($request, $id) {
        // 检查权限
        if (!$this->checkPermission()) {
            return [
                'error' => '没有权限访问管理后台',
                'redirect' => '/'
            ];
        }
        
        $user = $this->userModel->getById($id);
        
        if (!$user) {
            return [
                'error' => '用户不存在'
            ];
        }
        
        if ($user['role'] === 'system') {
            return [
                'error' => '不能删除系统用户'
            ];
        }
        
        if ($this->userModel->delete($id)) {
            return [
                'success' => '用户删除成功',
                'redirect' => '/admin/users'
            ];
        } else {
            return [
                'error' => '删除失败，请重试'
            ];
        }
    }
    
    public function topics() {
        // 检查权限
        if (!$this->checkPermission()) {
            return [
                'error' => '没有权限访问管理后台',
                'redirect' => '/'
            ];
        }
        
        $page = $_GET['page'] ?? 1;
        $perPage = 20;
        $filters = [];
        
        if (!empty($_GET['search'])) {
            $filters['search'] = $_GET['search'];
        }
        
        $topics = $this->topicModel->getAll($page, $perPage, $filters);
        
        return [
            'view' => 'admin/topics',
            'topics' => $topics['topics'],
            'total' => $topics['total'],
            'page' => $topics['page'],
            'perPage' => $topics['perPage'],
            'totalPages' => $topics['totalPages'],
            'filters' => $filters
        ];
    }
    
    public function deleteTopic($request, $id) {
        // 检查权限
        if (!$this->checkPermission()) {
            return [
                'error' => '没有权限访问管理后台',
                'redirect' => '/'
            ];
        }
        
        if ($this->topicModel->delete($id)) {
            return [
                'success' => '主题删除成功',
                'redirect' => '/admin/topics'
            ];
        } else {
            return [
                'error' => '删除失败，请重试'
            ];
        }
    }
    
    public function posts() {
        // 检查权限
        if (!$this->checkPermission()) {
            return [
                'error' => '没有权限访问管理后台',
                'redirect' => '/'
            ];
        }
        
        $page = $_GET['page'] ?? 1;
        $perPage = 20;
        $posts = $this->postModel->getLatestPosts($perPage * $page);
        $total = count($posts);
        $posts = array_slice($posts, ($page - 1) * $perPage, $perPage);
        
        return [
            'view' => 'admin/posts',
            'posts' => $posts,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }
    
    public function deletePost($request, $id) {
        // 检查权限
        if (!$this->checkPermission()) {
            return [
                'error' => '没有权限访问管理后台',
                'redirect' => '/'
            ];
        }
        
        if ($this->postModel->delete($id)) {
            return [
                'success' => '帖子删除成功',
                'redirect' => '/admin/posts'
            ];
        } else {
            return [
                'error' => '删除失败，请重试'
            ];
        }
    }
    
    public function settings() {
        // 检查权限
        if (!$this->checkPermission()) {
            return [
                'error' => '没有权限访问管理后台',
                'redirect' => '/'
            ];
        }
        
        return [
            'view' => 'admin/settings'
        ];
    }
    
    private function checkPermission() {
        return isset($_SESSION['user_id']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'moderator');
    }
}