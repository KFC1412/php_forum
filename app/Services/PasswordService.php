<?php

namespace App\Services;

class PasswordService {
    public function hash($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    public function verify($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public function needsRehash($hash) {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
    
    public function generateRandomPassword($length = 12) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()_+';
        $charactersLength = strlen($characters);
        $randomPassword = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomPassword .= $characters[rand(0, $charactersLength - 1)];
        }
        
        return $randomPassword;
    }
    
    public function validateStrength($password) {
        $strength = 0;
        
        // 长度检查
        if (strlen($password) >= 8) {
            $strength += 1;
        }
        
        // 包含数字
        if (preg_match('/[0-9]/', $password)) {
            $strength += 1;
        }
        
        // 包含小写字母
        if (preg_match('/[a-z]/', $password)) {
            $strength += 1;
        }
        
        // 包含大写字母
        if (preg_match('/[A-Z]/', $password)) {
            $strength += 1;
        }
        
        // 包含特殊字符
        if (preg_match('/[!@#$%^&*()_+]/', $password)) {
            $strength += 1;
        }
        
        return $strength;
    }
    
    public function getStrengthLevel($strength) {
        switch ($strength) {
            case 0:
            case 1:
                return '弱';
            case 2:
            case 3:
                return '中等';
            case 4:
            case 5:
                return '强';
            default:
                return '未知';
        }
    }
}