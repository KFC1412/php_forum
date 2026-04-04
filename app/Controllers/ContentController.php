<?php

namespace App\Controllers;

use App\Models\Topic;
use App\Models\Post;
use App\Models\DailyNews;
use App\Services\Container;

class ContentController {
    private $topicModel;
    private $postModel;
    private $dailyNewsModel;
    private $container;
    
    public function __construct(Container $container) {
        $this->topicModel = new Topic();
        $this->postModel = new Post();
        $this->dailyNewsModel = new DailyNews();
        $this->container = $container;
    }
    
    public function index() {
        $page = $_GET['page'] ?? 1;
        $perPage = 20;
        $filters = [];
        
        if (!empty($_GET['category_id'])) {
            $filters['category_id'] = $_GET['category_id'];
        }
        
        if (!empty($_GET['search'])) {
            $filters['search'] = $_GET['search'];
        }
        
        $result = $this->topicModel->getAll($page, $perPage, $filters);
        $hotTopics = $this->topicModel->getHotTopics(10);
        
        return [
            'view' => 'content/index',
            'topics' => $result['topics'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'totalPages' => $result['totalPages'],
            'hotTopics' => $hotTopics,
            'filters' => $filters
        ];
    }
    
    public function createTopic() {
        if (!isset($_SESSION['user_id'])) {
            return [
                'error' => '请先登录',
                'redirect' => '/login'
            ];
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            $categoryId = $_POST['category_id'] ?? 1;
            
            if (empty($title) || empty($content)) {
                return [
                    'error' => '请填写标题和内容'
                ];
            }
            
            if (strlen($title) < 3 || strlen($title) > 100) {
                return [
                    'error' => '标题长度必须在3-100个字符之间'
                ];
            }
            
            if (strlen($content) < 10) {
                return [
                    'error' => '内容长度必须至少10个字符'
                ];
            }
            
            $topicData = [
                'title' => $title,
                'content' => $content,
                'user_id' => $_SESSION['user_id'],
                'category_id' => $categoryId,
                'views' => 0,
                'replies' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($this->topicModel->create($topicData)) {
                $topicId = $this->topicModel->lastInsertId();
                return [
                    'success' => '主题创建成功',
                    'redirect' => "/topic/{$topicId}"
                ];
            } else {
                return [
                    'error' => '主题创建失败，请重试'
                ];
            }
        }
        
        return [
            'view' => 'content/create_topic'
        ];
    }
    
    public function showTopic($id) {
        $topic = $this->topicModel->getById($id);
        
        if (!$topic) {
            return [
                'error' => '主题不存在'
            ];
        }
        
        // 增加浏览量
        $this->topicModel->incrementViews($id);
        
        $page = $_GET['page'] ?? 1;
        $perPage = 20;
        $posts = $this->postModel->getByTopicId($id, $page, $perPage);
        
        return [
            'view' => 'content/show_topic',
            'topic' => $topic,
            'posts' => $posts['posts'],
            'total' => $posts['total'],
            'page' => $posts['page'],
            'perPage' => $posts['perPage'],
            'totalPages' => $posts['totalPages']
        ];
    }
    
    public function replyTopic($id) {
        if (!isset($_SESSION['user_id'])) {
            return [
                'error' => '请先登录',
                'redirect' => '/login'
            ];
        }
        
        $topic = $this->topicModel->getById($id);
        
        if (!$topic) {
            return [
                'error' => '主题不存在'
            ];
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $content = $_POST['content'] ?? '';
            
            if (empty($content)) {
                return [
                    'error' => '请填写回复内容'
                ];
            }
            
            if (strlen($content) < 5) {
                return [
                    'error' => '回复内容长度必须至少5个字符'
                ];
            }
            
            $postData = [
                'topic_id' => $id,
                'user_id' => $_SESSION['user_id'],
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($this->postModel->create($postData)) {
                // 增加主题回复数
                $this->topicModel->incrementReplies($id);
                
                return [
                    'success' => '回复成功',
                    'redirect' => "/topic/{$id}"
                ];
            } else {
                return [
                    'error' => '回复失败，请重试'
                ];
            }
        }
        
        return [
            'view' => 'content/reply_topic',
            'topic' => $topic
        ];
    }
    
    public function editTopic($id) {
        if (!isset($_SESSION['user_id'])) {
            return [
                'error' => '请先登录',
                'redirect' => '/login'
            ];
        }
        
        $topic = $this->topicModel->getById($id);
        
        if (!$topic) {
            return [
                'error' => '主题不存在'
            ];
        }
        
        if ($topic['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] != 'admin' && $_SESSION['role'] != 'moderator') {
            return [
                'error' => '没有权限编辑此主题'
            ];
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            $categoryId = $_POST['category_id'] ?? 1;
            
            if (empty($title) || empty($content)) {
                return [
                    'error' => '请填写标题和内容'
                ];
            }
            
            if (strlen($title) < 3 || strlen($title) > 100) {
                return [
                    'error' => '标题长度必须在3-100个字符之间'
                ];
            }
            
            if (strlen($content) < 10) {
                return [
                    'error' => '内容长度必须至少10个字符'
                ];
            }
            
            $updateData = [
                'title' => $title,
                'content' => $content,
                'category_id' => $categoryId,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($this->topicModel->update($id, $updateData)) {
                return [
                    'success' => '主题更新成功',
                    'redirect' => "/topic/{$id}"
                ];
            } else {
                return [
                    'error' => '主题更新失败，请重试'
                ];
            }
        }
        
        return [
            'view' => 'content/edit_topic',
            'topic' => $topic
        ];
    }
    
    public function deleteTopic($id) {
        if (!isset($_SESSION['user_id'])) {
            return [
                'error' => '请先登录',
                'redirect' => '/login'
            ];
        }
        
        $topic = $this->topicModel->getById($id);
        
        if (!$topic) {
            return [
                'error' => '主题不存在'
            ];
        }
        
        if ($topic['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] != 'admin' && $_SESSION['role'] != 'moderator') {
            return [
                'error' => '没有权限删除此主题'
            ];
        }
        
        if ($this->topicModel->delete($id)) {
            return [
                'success' => '主题删除成功',
                'redirect' => '/'
            ];
        } else {
            return [
                'error' => '主题删除失败，请重试'
            ];
        }
    }
    
    public function editPost($id) {
        if (!isset($_SESSION['user_id'])) {
            return [
                'error' => '请先登录',
                'redirect' => '/login'
            ];
        }
        
        $post = $this->postModel->getById($id);
        
        if (!$post) {
            return [
                'error' => '帖子不存在'
            ];
        }
        
        if ($post['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] != 'admin' && $_SESSION['role'] != 'moderator') {
            return [
                'error' => '没有权限编辑此帖子'
            ];
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $content = $_POST['content'] ?? '';
            
            if (empty($content)) {
                return [
                    'error' => '请填写内容'
                ];
            }
            
            if (strlen($content) < 5) {
                return [
                    'error' => '内容长度必须至少5个字符'
                ];
            }
            
            $updateData = [
                'content' => $content,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($this->postModel->update($id, $updateData)) {
                return [
                    'success' => '帖子更新成功',
                    'redirect' => "/topic/{$post['topic_id']}"
                ];
            } else {
                return [
                    'error' => '帖子更新失败，请重试'
                ];
            }
        }
        
        return [
            'view' => 'content/edit_post',
            'post' => $post
        ];
    }
    
    public function deletePost($id) {
        if (!isset($_SESSION['user_id'])) {
            return [
                'error' => '请先登录',
                'redirect' => '/login'
            ];
        }
        
        $post = $this->postModel->getById($id);
        
        if (!$post) {
            return [
                'error' => '帖子不存在'
            ];
        }
        
        if ($post['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] != 'admin' && $_SESSION['role'] != 'moderator') {
            return [
                'error' => '没有权限删除此帖子'
            ];
        }
        
        $topicId = $post['topic_id'];
        
        if ($this->postModel->delete($id)) {
            // 减少主题回复数
            $this->topicModel->decrementReplies($topicId);
            
            return [
                'success' => '帖子删除成功',
                'redirect' => "/topic/{$topicId}"
            ];
        } else {
            return [
                'error' => '帖子删除失败，请重试'
            ];
        }
    }
    
    public function dailyNews() {
        $news = $this->dailyNewsModel->getLatest();
        
        if (!$news) {
            return [
                'error' => '暂无热点资讯'
            ];
        }
        
        return [
            'view' => 'content/daily_news',
            'news' => $news
        ];
    }
}