<?php
/**
 * 系统账户功能函数
 * 用于管理系统账户（互动消息、系统通知等）
 */

/**
 * 获取系统账户信息
 * @param string $account_id 账户ID（如 'system', 'info'）
 * @return array|null 账户信息或null
 */
function getSystemAccount($account_id) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        $account = $db->fetch(
            "SELECT * FROM `{$prefix}system_accounts` WHERE `id` = ?",
            [$account_id]
        );
        
        return $account;
    } catch (Exception $e) {
        error_log('获取系统账户失败：' . $e->getMessage());
        return null;
    }
}

/**
 * 获取所有系统账户
 * @param string $type 账户类型（可选：system, info, service）
 * @return array 账户列表
 */
function getSystemAccounts($type = null) {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        if ($type) {
            $accounts = $db->fetchAll(
                "SELECT * FROM `{$prefix}system_accounts` WHERE `account_type` = ? ORDER BY `id`",
                [$type]
            );
        } else {
            $accounts = $db->fetchAll(
                "SELECT * FROM `{$prefix}system_accounts` ORDER BY `id`"
            );
        }
        
        return $accounts ?: [];
    } catch (Exception $e) {
        error_log('获取系统账户列表失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 创建或更新系统账户
 * @param array $data 账户数据
 * @return bool 是否成功
 */
function saveSystemAccount($data) {
    // 检查权限，只有管理员可以操作
    if (!isAdmin()) {
        return false;
    }
    
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        // 检查账户是否已存在
        $existing = $db->fetch(
            "SELECT id FROM `{$prefix}system_accounts` WHERE `id` = ?",
            [$data['id']]
        );
        
        if ($existing) {
            // 更新现有账户
            $update_data = [
                'username' => $data['username'],
                'display_name' => $data['display_name'] ?? null,
                'avatar' => $data['avatar'] ?? null,
                'account_type' => $data['account_type'] ?? 'service',
                'description' => $data['description'] ?? null,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $db->update("{$prefix}system_accounts", $update_data, "`id` = :id", ['id' => $data['id']]);
        } else {
            // 创建新账户
            $insert_data = [
                'id' => $data['id'],
                'username' => $data['username'],
                'display_name' => $data['display_name'] ?? null,
                'avatar' => $data['avatar'] ?? null,
                'account_type' => $data['account_type'] ?? 'service',
                'description' => $data['description'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $db->insert("{$prefix}system_accounts", $insert_data);
        }
        
        return true;
    } catch (Exception $e) {
        error_log('保存系统账户失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 删除系统账户
 * @param string $account_id 账户ID
 * @return bool 是否成功
 */
function deleteSystemAccount($account_id) {
    // 检查权限，只有管理员可以操作
    if (!isAdmin()) {
        return false;
    }
    
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        // 不允许删除核心系统账户
        if (in_array($account_id, ['system', 'info'])) {
            return false;
        }
        
        $db->delete("{$prefix}system_accounts", "`id` = :id", ['id' => $account_id]);
        
        return true;
    } catch (Exception $e) {
        error_log('删除系统账户失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 确保核心系统账户存在
 * 在安装或升级时调用
 * @return bool 是否成功
 */
function ensureCoreSystemAccounts() {
    try {
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        // 检查并创建 system 账户
        $system_account = $db->fetch(
            "SELECT id FROM `{$prefix}system_accounts` WHERE `id` = 'system'"
        );
        
        if (!$system_account) {
            $db->insert("{$prefix}system_accounts", [
                'id' => 'system',
                'username' => 'system',
                'display_name' => '【系统通知】',
                'avatar' => null,
                'account_type' => 'system',
                'description' => '系统通知账户，用于发送系统级别的通知消息',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        // 检查并创建 info 账户
        $info_account = $db->fetch(
            "SELECT id FROM `{$prefix}system_accounts` WHERE `id` = 'info'"
        );
        
        if (!$info_account) {
            $db->insert("{$prefix}system_accounts", [
                'id' => 'info',
                'username' => 'info',
                'display_name' => '【互动消息】',
                'avatar' => null,
                'account_type' => 'info',
                'description' => '互动消息账户，用于发送用户互动相关的通知',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log('确保核心系统账户失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 获取系统账户的显示名称
 * @param string $account_id 账户ID
 * @return string 显示名称
 */
function getSystemAccountDisplayName($account_id) {
    $account = getSystemAccount($account_id);
    if ($account) {
        return $account['display_name'] ?: $account['username'];
    }
    
    // 默认显示名称
    $default_names = [
        'system' => '【系统通知】',
        'info' => '【互动消息】'
    ];
    
    return $default_names[$account_id] ?: $account_id;
}

/**
 * 获取系统账户头像
 * @param string $account_id 账户ID
 * @return string 头像URL或默认头像
 */
function getSystemAccountAvatar($account_id) {
    $account = getSystemAccount($account_id);
    if ($account && !empty($account['avatar'])) {
        return $account['avatar'];
    }
    
    // 返回默认系统头像
    return '/assets/images/system-avatar.png';
}

/**
 * 检查是否为系统账户
 * @param string $account_id 账户ID
 * @return bool 是否为系统账户
 */
function isSystemAccount($account_id) {
    return in_array($account_id, ['system', 'info']);
}
