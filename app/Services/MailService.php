<?php

namespace App\Services;

class MailService {
    public function send($to, $subject, $message, $from = null) {
        // 设置默认发件人
        if (is_null($from)) {
            $from = 'no-reply@forum.com';
        }
        
        // 邮件头部
        $headers = "From: {$from}\r\n";
        $headers .= "Reply-To: {$from}\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        // 发送邮件
        return mail($to, $subject, $message, $headers);
    }
    
    public function sendVerificationEmail($to, $username, $verificationCode) {
        $subject = '邮箱验证 - 论坛系统';
        $message = "<h1>邮箱验证</h1>
                    <p>亲爱的 {$username}，</p>
                    <p>欢迎注册我们的论坛系统！请点击以下链接验证您的邮箱：</p>
                    <p><a href='http://localhost/verify?code={$verificationCode}'>点击验证</a></p>
                    <p>如果您没有注册，请忽略此邮件。</p>
                    <p>此致，<br>论坛系统团队</p>";
        
        return $this->send($to, $subject, $message);
    }
    
    public function sendPasswordResetEmail($to, $username, $resetToken) {
        $subject = '密码重置 - 论坛系统';
        $message = "<h1>密码重置</h1>
                    <p>亲爱的 {$username}，</p>
                    <p>您请求重置密码，请点击以下链接完成重置：</p>
                    <p><a href='http://localhost/reset-password?token={$resetToken}'>点击重置密码</a></p>
                    <p>如果您没有请求重置密码，请忽略此邮件。</p>
                    <p>此致，<br>论坛系统团队</p>";
        
        return $this->send($to, $subject, $message);
    }
    
    public function sendNotificationEmail($to, $username, $notificationTitle, $notificationContent) {
        $subject = "通知：{$notificationTitle} - 论坛系统";
        $message = "<h1>{$notificationTitle}</h1>
                    <p>亲爱的 {$username}，</p>
                    <p>{$notificationContent}</p>
                    <p>此致，<br>论坛系统团队</p>";
        
        return $this->send($to, $subject, $message);
    }
    
    public function sendWelcomeEmail($to, $username) {
        $subject = '欢迎加入 - 论坛系统';
        $message = "<h1>欢迎加入论坛系统</h1>
                    <p>亲爱的 {$username}，</p>
                    <p>很高兴您加入我们的论坛系统！</p>
                    <p>您可以：</p>
                    <ul>
                        <li>发布主题</li>
                        <li>回复帖子</li>
                        <li>与其他用户交流</li>
                        <li>参与社区活动</li>
                    </ul>
                    <p>祝您在论坛系统中度过愉快的时光！</p>
                    <p>此致，<br>论坛系统团队</p>";
        
        return $this->send($to, $subject, $message);
    }
}