<?php

/**
 * 管理工具和安全功能
 */

/**
 * 记录系统日志
 * @param string $type 日志类型
 * @param string $message 日志消息
 * @param int $user_id 用户ID
 * @param string $ip IP地址
 */
function logSystem($type, $message, $user_id = 0, $ip = '') {
    try {
        $db = getDB();
        
        $db->insert('system_logs', [
            'type' => $type,
            'message' => $message,
            'user_id' => $user_id,
            'ip' => $ip,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        error_log('记录系统日志失败：' . $e->getMessage());
    }
}

/**
 * 获取系统日志
 * @param string $type 日志类型
 * @param int $user_id 用户ID
 * @param int $limit 限制数量
 * @return array
 */
function getSystemLogs($type = '', $user_id = 0, $limit = 100) {
    try {
        $db = getDB();
        
        $where = [];
        if (!empty($type)) {
            $where['type'] = $type;
        }
        if ($user_id > 0) {
            $where['user_id'] = $user_id;
        }
        
        return $db->fetchAll(
            "SELECT sl.*, u.username FROM `system_logs` sl
            LEFT JOIN `users` u ON sl.user_id = u.id
            " . (!empty($where) ? "WHERE " . implode(' AND ', array_map(function($k) { return "`$k` = :$k"; }, array_keys($where))) : "") . "
            ORDER BY sl.created_at DESC
            LIMIT :limit",
            array_merge($where, ['limit' => $limit])
        );
    } catch (Exception $e) {
        error_log('获取系统日志失败：' . $e->getMessage());
        return [];
    }
}

/**
 * 清理系统日志
 * @param int $days 保留天数
 * @return bool
 */
function cleanupSystemLogs($days = 30) {
    try {
        $db = getDB();
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        $result = $db->delete('system_logs', '`created_at` < :cutoff_date', ['cutoff_date' => $cutoff_date]);
        
        return $result > 0;
    } catch (Exception $e) {
        error_log('清理系统日志失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 数据备份
 * @param string $type 备份类型（full/structure/data）
 * @return string 备份文件路径
 */
function backupData($type = 'full') {
    try {
        $backup_dir = __DIR__ . '/../storage/backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $filename = 'backup_' . date('Ymd_His') . '_' . $type . '.sql';
        $backup_file = $backup_dir . $filename;
        
        // 根据存储类型执行不同的备份操作
        if (defined('STORAGE_TYPE') && STORAGE_TYPE == 'json') {
            // JSON存储备份
            $json_dir = __DIR__ . '/../storage/json/';
            $files = glob($json_dir . '*.json');
            
            $backup_data = [];
            foreach ($files as $file) {
                $table_name = basename($file, '.json');
                $content = file_get_contents($file);
                $backup_data[$table_name] = json_decode($content, true);
            }
            
            file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT));
        } else {
            // 数据库备份（简化版）
            $db = getDB();
            $tables = $db->fetchAll("SHOW TABLES");
            
            $backup_content = '';
            foreach ($tables as $table) {
                $table_name = reset($table);
                
                // 获取表结构
                $create_table = $db->fetchColumn("SHOW CREATE TABLE `$table_name`");
                $backup_content .= $create_table . ";\n\n";
                
                // 获取数据
                if ($type != 'structure') {
                    $rows = $db->fetchAll("SELECT * FROM `$table_name`");
                    if (!empty($rows)) {
                        $columns = array_keys($rows[0]);
                        $columns_str = '`' . implode('`, `', $columns) . '`';
                        
                        foreach ($rows as $row) {
                            $values = [];
                            foreach ($row as $value) {
                                if ($value === null) {
                                    $values[] = 'NULL';
                                } else {
                                    $values[] = "'" . $db->quote($value) . "'";
                                }
                            }
                            $values_str = implode(', ', $values);
                            $backup_content .= "INSERT INTO `$table_name` ($columns_str) VALUES ($values_str);\n";
                        }
                        $backup_content .= "\n";
                    }
                }
            }
            
            file_put_contents($backup_file, $backup_content);
        }
        
        return $filename;
    } catch (Exception $e) {
        error_log('数据备份失败：' . $e->getMessage());
        return '';
    }
}

/**
 * 数据恢复
 * @param string $backup_file 备份文件
 * @return bool
 */
function restoreData($backup_file) {
    try {
        $backup_path = __DIR__ . '/../storage/backups/' . $backup_file;
        if (!file_exists($backup_path)) {
            throw new Exception('备份文件不存在');
        }
        
        if (defined('STORAGE_TYPE') && STORAGE_TYPE == 'json') {
            // JSON存储恢复
            $backup_data = json_decode(file_get_contents($backup_path), true);
            
            foreach ($backup_data as $table_name => $data) {
                $json_file = __DIR__ . '/../storage/json/' . $table_name . '.json';
                file_put_contents($json_file, json_encode($data, JSON_PRETTY_PRINT));
            }
        } else {
            // 数据库恢复（简化版）
            $db = getDB();
            $backup_content = file_get_contents($backup_path);
            
            // 执行SQL语句
            $statements = explode(';', $backup_content);
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $db->query($statement);
                }
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log('数据恢复失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 获取备份文件列表
 * @return array
 */
function getBackupFiles() {
    $backup_dir = __DIR__ . '/../storage/backups/';
    if (!is_dir($backup_dir)) {
        return [];
    }
    
    $files = glob($backup_dir . '*.sql');
    $backup_files = [];
    
    foreach ($files as $file) {
        $filename = basename($file);
        $backup_files[] = [
            'name' => $filename,
            'size' => filesize($file),
            'mtime' => filemtime($file)
        ];
    }
    
    // 按修改时间排序
    usort($backup_files, function($a, $b) {
        return $b['mtime'] - $a['mtime'];
    });
    
    return $backup_files;
}

/**
 * 删除备份文件
 * @param string $filename 文件名
 * @return bool
 */
function deleteBackupFile($filename) {
    $backup_path = __DIR__ . '/../storage/backups/' . $filename;
    if (file_exists($backup_path)) {
        return unlink($backup_path);
    }
    return false;
}

/**
 * 清理过期备份
 * @param int $days 保留天数
 * @return int 删除的文件数
 */
function cleanupBackups($days = 7) {
    $backup_dir = __DIR__ . '/../storage/backups/';
    if (!is_dir($backup_dir)) {
        return 0;
    }
    
    $files = glob($backup_dir . '*.sql');
    $count = 0;
    
    $cutoff_time = time() - ($days * 24 * 3600);
    foreach ($files as $file) {
        if (filemtime($file) < $cutoff_time) {
            if (unlink($file)) {
                $count++;
            }
        }
    }
    
    return $count;
}

/**
 * 检查用户权限
 * @param string $permission 权限名称
 * @param int $user_id 用户ID
 * @return bool
 */
function checkPermission($permission, $user_id = 0) {
    if ($user_id == 0 && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }
    
    if ($user_id == 0) {
        return false;
    }
    
    try {
        $db = getDB();
        $user = $db->findById('users', $user_id);
        
        if (!$user) {
            return false;
        }
        
        // 管理员拥有所有权限
        if ($user['role'] == 'admin') {
            return true;
        }
        
        // 版主权限
        if ($user['role'] == 'moderator') {
            $mod_permissions = ['manage_users', 'manage_topics', 'manage_replies', 'moderate_content'];
            return in_array($permission, $mod_permissions);
        }
        
        // 普通用户权限
        $user_permissions = ['create_topic', 'create_reply', 'edit_profile'];
        return in_array($permission, $user_permissions);
    } catch (Exception $e) {
        error_log('检查权限失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 初始化管理工具和安全系统
 */
function initAdminToolsSystem() {
    try {
        $db = getDB();
        
        // 初始化系统日志表
        $db->query("CREATE TABLE IF NOT EXISTS `system_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `type` enum('info','warning','error','debug') DEFAULT 'info',
            `message` text NOT NULL,
            `user_id` int(11) DEFAULT NULL,
            `ip` varchar(50) DEFAULT NULL,
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `type` (`type`),
            KEY `user_id` (`user_id`),
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 创建存储目录
        $directories = [
            __DIR__ . '/../storage/backups/',
            __DIR__ . '/../storage/logs/'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    } catch (Exception $e) {
        error_log('初始化管理工具系统失败：' . $e->getMessage());
    }
}

/**
 * 获取系统状态
 * @return array
 */
function getSystemStatus() {
    $status = [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
        'os' => PHP_OS,
        'memory_usage' => memory_get_usage() / 1024 / 1024 . ' MB',
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time') . 's',
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'date' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get()
    ];
    
    return $status;
}

/**
 * 清理系统缓存
 * @return bool
 */
function clearSystemCache() {
    try {
        $cache = getCache();
        $cache->clear();
        
        // 清理模板缓存
        $template_cache = __DIR__ . '/../storage/cache/templates/';
        if (is_dir($template_cache)) {
            $files = glob($template_cache . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log('清理系统缓存失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 批量操作用户
 * @param array $user_ids 用户ID数组
 * @param string $action 操作类型
 * @param array $params 操作参数
 * @return int 成功操作的数量
 */
function batchUserAction($user_ids, $action, $params = []) {
    $count = 0;
    
    foreach ($user_ids as $user_id) {
        switch ($action) {
            case 'ban':
                if (banUser($user_id, $params['reason'] ?? '')) {
                    $count++;
                }
                break;
            case 'unban':
                if (unbanUser($user_id)) {
                    $count++;
                }
                break;
            case 'promote':
                if (promoteUser($user_id, $params['role'] ?? 'moderator')) {
                    $count++;
                }
                break;
            case 'demote':
                if (demoteUser($user_id)) {
                    $count++;
                }
                break;
        }
    }
    
    return $count;
}

/**
 * 封禁用户
 * @param int $user_id 用户ID
 * @param string $reason 封禁原因
 * @return bool
 */
function banUser($user_id, $reason = '') {
    try {
        $db = getDB();
        
        $result = $db->update('users', [
            'status' => 'banned',
            'updated_at' => date('Y-m-d H:i:s')
        ], '`id` = :id', ['id' => $user_id]);
        
        if ($result > 0) {
            logSystem('warning', "用户 $user_id 被封禁，原因：$reason", $_SESSION['user_id'] ?? 0, $_SERVER['REMOTE_ADDR'] ?? '');
        }
        
        return $result > 0;
    } catch (Exception $e) {
        error_log('封禁用户失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 解除封禁用户
 * @param int $user_id 用户ID
 * @return bool
 */
function unbanUser($user_id) {
    try {
        $db = getDB();
        
        $result = $db->update('users', [
            'status' => 'active',
            'updated_at' => date('Y-m-d H:i:s')
        ], '`id` = :id', ['id' => $user_id]);
        
        if ($result > 0) {
            logSystem('info', "用户 $user_id 被解除封禁", $_SESSION['user_id'] ?? 0, $_SERVER['REMOTE_ADDR'] ?? '');
        }
        
        return $result > 0;
    } catch (Exception $e) {
        error_log('解除封禁用户失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 提升用户权限
 * @param int $user_id 用户ID
 * @param string $role 角色
 * @return bool
 */
function promoteUser($user_id, $role = 'moderator') {
    try {
        $db = getDB();
        
        $result = $db->update('users', [
            'role' => $role,
            'updated_at' => date('Y-m-d H:i:s')
        ], '`id` = :id', ['id' => $user_id]);
        
        if ($result > 0) {
            logSystem('info', "用户 $user_id 被提升为 $role", $_SESSION['user_id'] ?? 0, $_SERVER['REMOTE_ADDR'] ?? '');
        }
        
        return $result > 0;
    } catch (Exception $e) {
        error_log('提升用户权限失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 降低用户权限
 * @param int $user_id 用户ID
 * @return bool
 */
function demoteUser($user_id) {
    try {
        $db = getDB();
        
        $result = $db->update('users', [
            'role' => 'user',
            'updated_at' => date('Y-m-d H:i:s')
        ], '`id` = :id', ['id' => $user_id]);
        
        if ($result > 0) {
            logSystem('info', "用户 $user_id 被降为普通用户", $_SESSION['user_id'] ?? 0, $_SERVER['REMOTE_ADDR'] ?? '');
        }
        
        return $result > 0;
    } catch (Exception $e) {
        error_log('降低用户权限失败：' . $e->getMessage());
        return false;
    }
}
?>