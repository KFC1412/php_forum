<?php

namespace App\Controllers;

use App\Models\User;
use App\Services\Container;

class UserController {
    private $userModel;
    private $container;
    
    public function __construct(Container $container) {
        $this->userModel = new User();
        $this->container = $container;
    }
    
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                return [
                    'error' => '请填写用户名和密码'
                ];
            }
            
            $user = $this->userModel->getByUsername($username);
            
            if (!$user || !password_verify($password, $user['password'])) {
                return [
                    'error' => '用户名或密码错误'
                ];
            }
            
            if ($user['status'] !== 'active') {
                return [
                    'error' => '账户已被禁用'
                ];
            }
            
            // 登录成功，设置会话
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            return [
                'success' => '登录成功',
                'redirect' => '/'
            ];
        }
        
        return [
            'view' => 'auth/login'
        ];
    }
    
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $mobile = $_POST['mobile'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // 验证输入
            if (empty($username) || empty($email) || empty($mobile) || empty($password)) {
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
            
            if (strlen($password) < 6) {
                return [
                    'error' => '密码长度必须至少6个字符'
                ];
            }
            
            if ($password !== $confirmPassword) {
                return [
                    'error' => '两次输入的密码不一致'
                ];
            }
            
            // 检查用户名是否已存在
            if ($this->userModel->getByUsername($username)) {
                return [
                    'error' => '用户名已存在'
                ];
            }
            
            // 检查邮箱是否已存在
            if ($this->userModel->getByEmail($email)) {
                return [
                    'error' => '邮箱已被注册'
                ];
            }
            
            // 检查手机号是否已存在
            if ($this->userModel->getByMobile($mobile)) {
                return [
                    'error' => '手机号已被注册'
                ];
            }
            
            // 创建用户
            $userData = [
                'username' => $username,
                'email' => $email,
                'mobile' => $mobile,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'user',
                'status' => 'active',
                'points' => 0,
                'experience' => 0,
                'level' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($this->userModel->create($userData)) {
                return [
                    'success' => '注册成功，请登录',
                    'redirect' => '/login'
                ];
            } else {
                return [
                    'error' => '注册失败，请重试'
                ];
            }
        }
        
        return [
            'view' => 'auth/register'
        ];
    }
    
    public function logout() {
        session_destroy();
        return [
            'success' => '退出登录成功',
            'redirect' => '/'
        ];
    }
    
    public function profile() {
        $userId = $_SESSION['user_id'] ?? 0;
        
        if (!$userId) {
            return [
                'error' => '请先登录',
                'redirect' => '/login'
            ];
        }
        
        $user = $this->userModel->getById($userId);
        
        if (!$user) {
            return [
                'error' => '用户不存在'
            ];
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['email'] ?? '';
            $mobile = $_POST['mobile'] ?? '';
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // 验证输入
            if (empty($email) || empty($mobile)) {
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
            
            // 检查邮箱是否已被其他用户使用
            $existingUser = $this->userModel->getByEmail($email);
            if ($existingUser && $existingUser['id'] != $userId) {
                return [
                    'error' => '邮箱已被其他用户使用'
                ];
            }
            
            // 检查手机号是否已被其他用户使用
            $existingUser = $this->userModel->getByMobile($mobile);
            if ($existingUser && $existingUser['id'] != $userId) {
                return [
                    'error' => '手机号已被其他用户使用'
                ];
            }
            
            // 更新用户信息
            $updateData = [
                'email' => $email,
                'mobile' => $mobile,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // 如果修改密码
            if (!empty($currentPassword) && !empty($newPassword)) {
                if (!password_verify($currentPassword, $user['password'])) {
                    return [
                        'error' => '当前密码错误'
                    ];
                }
                
                if (strlen($newPassword) < 6) {
                    return [
                        'error' => '新密码长度必须至少6个字符'
                    ];
                }
                
                if ($newPassword !== $confirmPassword) {
                    return [
                        'error' => '两次输入的新密码不一致'
                    ];
                }
                
                $updateData['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            }
            
            if ($this->userModel->update($userId, $updateData)) {
                return [
                    'success' => '个人资料更新成功',
                    'user' => $this->userModel->getById($userId)
                ];
            } else {
                return [
                    'error' => '更新失败，请重试'
                ];
            }
        }
        
        return [
            'view' => 'user/profile',
            'user' => $user
        ];
    }
    
    public function show($id) {
        $user = $this->userModel->getById($id);
        
        if (!$user) {
            return [
                'error' => '用户不存在'
            ];
        }
        
        return [
            'view' => 'user/show',
            'user' => $user
        ];
    }
}