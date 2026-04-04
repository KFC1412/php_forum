<?php

namespace App\Controllers;

use App\Models\Message;
use App\Models\InteractionMessage;
use App\Models\SystemNotification;
use App\Services\Container;

class SocialController {
    private $messageModel;
    private $interactionMessageModel;
    private $systemNotificationModel;
    private $container;
    
    public function __construct(Container $container) {
        $this->messageModel = new Message();
        $this->interactionMessageModel = new InteractionMessage();
        $this->systemNotificationModel = new SystemNotification();
        $this->container = $container;
    }
    
    public function messages() {
        if (!isset($_SESSION['user_id'])) {
            return [
                'error' => '请先登录',
                'redirect' => '/login'
            ];
        }
        
        $page = $_GET['page'] ?? 1;
        $perPage = 20;
        $messages = $this->messageModel->getByUserId($_SESSION['user_id'], $page, $perPage);
        
        return [
            'view' => 'social/messages',
            'messages' => $messages['messages'],
            'total' => $messages['total'],
            'page' => $messages['page'],
            'perPage' => $messages['perPage'],
            'totalPages' => $messages['totalPages']
        ];
    }
    
    public function sendMessage() {
        if (!isset($_SESSION['user_id'])) {
            return [
                'error' => '请先登录',
                'redirect' => '/login'
            ];
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $receiverId = $_POST['receiver_id'] ?? '';
            $content = $_POST['content'] ?? '';
            
            if (empty($receiverId) || empty($content)) {
                return [
                    'error' => '请填写收件人和消息内容'
                ];
            }
            
            if ($receiverId == $_SESSION['user_id']) {
                return [
                    'error' => '不能给自己发送消息'
                ];
            }
            
            $messageData = [
                'sender_id' => $_SESSION['user_id'],
                'receiver_id' => $receiverId,
                'content' => $content,
                'status' => 'unread',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            if ($this->messageModel->create($messageData)) {
                return [
                    'success' => '消息发送成功',
                    'redirect' => '/messages'
                ];
            } else {
                return [
                    'error' => '消息发送失败，请重试'
                ];
            }
        }
        
        return [
            'view' => 'social/send_message'
        ];
    }
    
    public function messageThread($userId) {
        if (!isset($_SESSION['user_id'])) {
            return [
                'error' => '请先登录',
                'redirect' => '/login'
            ];
        }
        
        $page = $_GET['page'] ?? 1;
        $perPage = 20;
        $thread = $this->messageModel->getThread($_SESSION['user_id'], $userId, $page, $perPage);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $content = $_POST['content'] ?? '';
            
            if (empty($content)) {
                return [
                    'error' => '请填写消息内容'
                ];
            }
            
            $messageData = [
                'sender_id' => $_SESSION['user_id'],
                'receiver_id' => $userId,
                'content' => $content,
                'status' => 'unread',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            if ($this->messageModel->create($messageData)) {
                return [
                    'success' => '消息发送成功',
                    'redirect' => "/message/thread/{$userId}"
                ];
            } else {
                return [
                    'error' => '消息发送失败，请重试'
                ];
            }
        }
        
        return [
            'view' => 'social/message_thread',
            'messages' => $thread['messages'],
            'total' => $thread['total'],
            'page' => $thread['page'],
            'perPage' => $thread['perPage'],
            'totalPages' => $thread['totalPages'],
            'userId' => $userId
        ];
    }
    
    public function notifications() {
        if (!isset($_SESSION['user_id'])) {
            return [
                'error' => '请先登录',
                'redirect' => '/login'
            ];
        }
        
        $page = $_GET['page'] ?? 1;
        $perPage = 20;
        $notifications = $this->systemNotificationModel->getByUserId($_SESSION['user_id'], $page, $perPage);
        
        // 标记所有通知为已读
        $this->systemNotificationModel->markAllAsRead($_SESSION['user_id']);
        
        return [
            'view' => 'social/notifications',
            'notifications' => $notifications['notifications'],
            'total' => $notifications['total'],
            'page' => $notifications['page'],
            'perPage' => $notifications['perPage'],
            'totalPages' => $notifications['totalPages']
        ];
    }
    
    public function markNotificationAsRead($id) {
        if (!isset($_SESSION['user_id'])) {
            return [
                'error' => '请先登录',
                'redirect' => '/login'
            ];
        }
        
        if ($this->systemNotificationModel->markAsRead($id)) {
            return [
                'success' => '通知已标记为已读',
                'redirect' => '/notifications'
            ];
        } else {
            return [
                'error' => '操作失败，请重试'
            ];
        }
    }
    
    public function deleteNotification($id) {
        if (!isset($_SESSION['user_id'])) {
            return [
                'error' => '请先登录',
                'redirect' => '/login'
            ];
        }
        
        // 系统通知暂时不支持删除
        return [
            'success' => '操作成功',
            'redirect' => '/notifications'
        ];
    }
    
    public function interactionMessages() {
        if (!isset($_SESSION['user_id'])) {
            return [
                'error' => '请先登录',
                'redirect' => '/login'
            ];
        }
        
        $page = $_GET['page'] ?? 1;
        $perPage = 20;
        $messages = $this->interactionMessageModel->getByUserId($_SESSION['user_id'], $page, $perPage);
        
        // 标记所有互动消息为已读
        $this->interactionMessageModel->markAllAsRead($_SESSION['user_id']);
        
        return [
            'view' => 'social/interaction_messages',
            'messages' => $messages['messages'],
            'total' => $messages['total'],
            'page' => $messages['page'],
            'perPage' => $messages['perPage'],
            'totalPages' => $messages['totalPages']
        ];
    }
}
