<?php

namespace App\Helpers;

class Helper {
    public static function slugify($string) {
        $string = strtolower($string);
        $string = preg_replace('/[^a-z0-9-]/', '-', $string);
        $string = preg_replace('/-+/', '-', $string);
        $string = trim($string, '-');
        return $string;
    }
    
    public static function truncate($string, $length = 100, $append = '...') {
        if (strlen($string) <= $length) {
            return $string;
        }
        return substr($string, 0, $length) . $append;
    }
    
    public static function formatDate($date, $format = 'Y-m-d H:i:s') {
        return date($format, strtotime($date));
    }
    
    public static function timeAgo($date) {
        $now = time();
        $past = strtotime($date);
        $diff = $now - $past;
        
        $seconds = $diff;
        $minutes = round($diff / 60);
        $hours = round($diff / 3600);
        $days = round($diff / 86400);
        $weeks = round($diff / 604800);
        $months = round($diff / 2592000);
        $years = round($diff / 31536000);
        
        if ($seconds < 60) {
            return '刚刚';
        } elseif ($minutes < 60) {
            return "{$minutes}分钟前";
        } elseif ($hours < 24) {
            return "{$hours}小时前";
        } elseif ($days < 7) {
            return "{$days}天前";
        } elseif ($weeks < 4) {
            return "{$weeks}周前";
        } elseif ($months < 12) {
            return "{$months}个月前";
        } else {
            return "{$years}年前";
        }
    }
    
    public static function arrayGet($array, $key, $default = null) {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }
        return $default;
    }
    
    public static function arraySet(&$array, $key, $value) {
        $array[$key] = $value;
    }
    
    public static function arrayHas($array, $key) {
        return array_key_exists($key, $array);
    }
    
    public static function arrayPull(&$array, $key, $default = null) {
        if (array_key_exists($key, $array)) {
            $value = $array[$key];
            unset($array[$key]);
            return $value;
        }
        return $default;
    }
    
    public static function arrayOnly($array, $keys) {
        return array_intersect_key($array, array_flip((array)$keys));
    }
    
    public static function arrayExcept($array, $keys) {
        return array_diff_key($array, array_flip((array)$keys));
    }
    
    public static function randomString($length = 16) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        
        return $randomString;
    }
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validateMobile($mobile) {
        return preg_match('/^1[3-9]\d{9}$/', $mobile) === 1;
    }
    
    public static function validateUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    public static function sanitizeHtml($html) {
        return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
    }
    
    public static function generatePagination($currentPage, $totalPages, $url) {
        $pagination = '';
        
        if ($totalPages > 1) {
            $pagination .= '<div class="pagination">';
            
            // 上一页
            if ($currentPage > 1) {
                $pagination .= '<a href="' . str_replace('{page}', $currentPage - 1, $url) . '">上一页</a>';
            }
            
            // 页码
            for ($i = 1; $i <= $totalPages; $i++) {
                if ($i == $currentPage) {
                    $pagination .= '<a href="' . str_replace('{page}', $i, $url) . '" class="active">' . $i . '</a>';
                } else {
                    $pagination .= '<a href="' . str_replace('{page}', $i, $url) . '">' . $i . '</a>';
                }
            }
            
            // 下一页
            if ($currentPage < $totalPages) {
                $pagination .= '<a href="' . str_replace('{page}', $currentPage + 1, $url) . '">下一页</a>';
            }
            
            $pagination .= '</div>';
        }
        
        return $pagination;
    }
}