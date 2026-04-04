<?php
    date_default_timezone_set('Asia/Shanghai');
/**
 * 备份恢复页面
 */

// 启动会话
session_start();

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 检查是否已安装
if (!file_exists(__DIR__ . '/../config/config.php')) {
    header('Location: ../install/index.php');
    exit;
}

// 加载配置和函数
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/admin_functions.php';

// 检查是否已登录且是管理员
checkAdminAccess();

// 获取操作类型
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// 处理操作
$error = '';
$success = '';

// 获取数据库实例
$db = getDB();
$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';

// 备份目录
$backup_dir = __DIR__ . '/../backups';

// 确保备份目录存在
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// 处理备份恢复操作
switch ($action) {
    case 'create':
        // 创建备份
        try {
            $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
            
            if ($storage_type === 'json') {
                // JSON存储备份
                $json_backup_dir = defined('JSON_STORAGE_DIR') ? JSON_STORAGE_DIR : 'storage/json';
                $json_dir = __DIR__ . '/../' . $json_backup_dir;
                
                if (!is_dir($json_dir)) {
                    throw new Exception('JSON存储目录不存在');
                }
                
                // 创建备份文件名（zip格式）
                $backup_file = $backup_dir . '/backup_' . date('Y-m-d_H-i-s') . '.zip';
                
                $zip = new ZipArchive();
                if ($zip->open($backup_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    throw new Exception('无法创建备份文件');
                }
                
                // 添加所有JSON文件到zip
                $json_files = glob($json_dir . '/*.json');
                if (empty($json_files)) {
                    throw new Exception('没有找到需要备份的JSON文件');
                }
                
                foreach ($json_files as $json_file) {
                    $zip->addFile($json_file, basename($json_file));
                }
                
                // 添加备份信息
                $backup_info = [
                    'type' => 'json',
                    'version' => getSetting('forum_version', 'v0.2.0_t_260404'),
                    'created_at' => date('Y-m-d H:i:s'),
                    'tables' => array_map('basename', $json_files)
                ];
                $zip->addFromString('backup_info.json', json_encode($backup_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                
                $zip->close();
            } else {
                // MySQL存储备份
                // 获取所有表
                $tables = $db->fetchAll("SHOW TABLES LIKE '{$prefix}%'");
                
                if (empty($tables)) {
                    throw new Exception('没有找到需要备份的表');
                }
                
                // 创建备份文件名
                $backup_file = $backup_dir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
                
                // 打开备份文件
                $fp = fopen($backup_file, 'w');
                
                if (!$fp) {
                    throw new Exception('无法创建备份文件');
                }
                
                // 写入备份文件头
                fwrite($fp, "-- PHP轻论坛数据库备份\n");
                fwrite($fp, "-- 创建时间: " . date('Y-m-d H:i:s') . "\n");
                fwrite($fp, "-- 版本: " . getSetting('forum_version', 'v0.2.0_t_260404') . "\n");
                fwrite($fp, "-- -------------------------------------------------------\n\n");
                
                // 备份每个表
                foreach ($tables as $table) {
                    $table_name = reset($table);
                    
                    // 写入表结构
                    fwrite($fp, "-- 表结构: `{$table_name}`\n");
                    
                    $create_table = $db->fetch("SHOW CREATE TABLE `{$table_name}`");
                    fwrite($fp, $create_table['Create Table'] . ";\n\n");
                    
                    // 获取表数据
                    $rows = $db->fetchAll("SELECT * FROM `{$table_name}`");
                    
                    if (!empty($rows)) {
                        // 写入表数据
                        fwrite($fp, "-- 表数据: `{$table_name}`\n");
                        
                        foreach ($rows as $row) {
                            $columns = array_keys($row);
                            $values = array_values($row);
                            
                            // 转义值
                            foreach ($values as &$value) {
                                if ($value === null) {
                                    $value = 'NULL';
                                } else {
                                    $value = "'" . addslashes($value) . "'";
                                }
                            }
                            
                            fwrite($fp, "INSERT INTO `{$table_name}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n");
                        }
                        
                        fwrite($fp, "\n");
                    }
                }
                
                // 关闭备份文件
                fclose($fp);
            }
            
            // 记录操作日志
            logAdminAction('管理员创建备份', 'system', 0, [
                'file' => basename($backup_file),
                'file_size' => filesize($backup_file),
                'backup_type' => $backup_type,
                'tables' => $selected_tables,
                'admin_id' => $_SESSION['user_id'],
                'admin_username' => $_SESSION['username'],
                'action_time' => date('Y-m-d H:i:s'),
                'ip_address' => getClientIp()
            ]);
            
            $success = '备份创建成功';
        } catch (Exception $e) {
            $error = '创建备份失败: ' . $e->getMessage();
        }
        
        // 重定向到备份列表
        header('Location: backup.php' . ($success ? '?success=' . urlencode($success) : ($error ? '?error=' . urlencode($error) : '')));
        exit;
        break;
        
    case 'restore':
        // 恢复备份
        $file = isset($_GET['file']) ? $_GET['file'] : '';
        
        if (empty($file)) {
            header('Location: backup.php?error=' . urlencode('未指定备份文件'));
            exit;
        }
        
        // 安全检查：确保文件名只包含允许的字符
        if (!preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.(sql|zip)$/', $file)) {
            header('Location: backup.php?error=' . urlencode('无效的备份文件名'));
            exit;
        }
        
        $backup_file = $backup_dir . '/' . $file;
        
        if (!file_exists($backup_file)) {
            header('Location: backup.php?error=' . urlencode('备份文件不存在'));
            exit;
        }
        
        try {
            $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
            
            if (pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
                // JSON存储恢复
                $json_backup_dir = defined('JSON_STORAGE_DIR') ? JSON_STORAGE_DIR : 'storage/json';
                $json_dir = __DIR__ . '/../' . $json_backup_dir;
                
                $zip = new ZipArchive();
                $open_result = $zip->open($backup_file);
                if ($open_result !== true) {
                    $error_message = '无法打开备份文件 (错误代码: ' . $open_result . ')';
                    switch ($open_result) {
                        case ZipArchive::ER_OPEN:
                            $error_message .= ' - 无法打开文件';
                            break;
                        case ZipArchive::ER_READ:
                            $error_message .= ' - 读取文件错误';
                            break;
                        case ZipArchive::ER_NOZIP:
                            $error_message .= ' - 不是有效的ZIP文件';
                            break;
                        case ZipArchive::ER_INCONS:
                            $error_message .= ' - ZIP文件格式不一致';
                            break;
                        case ZipArchive::ER_CRC:
                            $error_message .= ' - CRC校验错误';
                            break;
                    }
                    throw new Exception($error_message);
                }
                
                // 解压所有JSON文件
                $zip->extractTo($json_dir);
                $zip->close();
            } else {
                // MySQL存储恢复
                // 读取备份文件
                $sql = file_get_contents($backup_file);
                
                if (empty($sql)) {
                    throw new Exception('备份文件为空');
                }
                
                // 分割SQL语句
                $queries = explode(';', $sql);
                
                // 开始事务
                $db->beginTransaction();
                
                // 执行每个SQL语句
                foreach ($queries as $query) {
                    $query = trim($query);
                    
                    if (!empty($query)) {
                        $db->execute($query);
                    }
                }
                
                // 提交事务
                $db->commit();
            }
            
            // 记录操作日志
            logAdminAction('管理员恢复备份', 'system', 0, [
                'file' => $file,
                'file_size' => filesize($backup_file),
                'admin_id' => $_SESSION['user_id'],
                'admin_username' => $_SESSION['username'],
                'action_time' => date('Y-m-d H:i:s'),
                'ip_address' => getClientIp()
            ]);
            
            $success = '备份恢复成功';
        } catch (Exception $e) {
            // 回滚事务
            if (isset($db) && $storage_type !== 'json') {
                $db->rollBack();
            }
            
            $error = '恢复备份失败: ' . $e->getMessage();
        }
        
        // 重定向到备份列表
        header('Location: backup.php' . ($success ? '?success=' . urlencode($success) : ($error ? '?error=' . urlencode($error) : '')));
        exit;
        break;
        
    case 'download':
        // 下载备份
        $file = isset($_GET['file']) ? $_GET['file'] : '';
        
        if (empty($file)) {
            header('Location: backup.php?error=' . urlencode('未指定备份文件'));
            exit;
        }
        
        // 安全检查：确保文件名只包含允许的字符
        if (!preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.(sql|zip)$/', $file)) {
            header('Location: backup.php?error=' . urlencode('无效的备份文件名'));
            exit;
        }
        
        $backup_file = $backup_dir . '/' . $file;
        
        if (!file_exists($backup_file)) {
            header('Location: backup.php?error=' . urlencode('备份文件不存在'));
            exit;
        }
        
        // 记录操作日志
        logAdminAction('管理员下载备份', 'system', 0, [
            'file' => $file,
            'file_size' => filesize($backup_file),
            'admin_id' => $_SESSION['user_id'],
            'admin_username' => $_SESSION['username'],
            'action_time' => date('Y-m-d H:i:s'),
            'ip_address' => getClientIp()
        ]);
        
        // 设置下载头
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($backup_file));
        
        // 输出文件内容
        readfile($backup_file);
        exit;
        break;
        
    case 'delete':
        // 删除备份
        $file = isset($_GET['file']) ? $_GET['file'] : '';
        
        if (empty($file)) {
            header('Location: backup.php?error=' . urlencode('未指定备份文件'));
            exit;
        }
        
        // 安全检查：确保文件名只包含允许的字符
        if (!preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.(sql|zip)$/', $file)) {
            header('Location: backup.php?error=' . urlencode('无效的备份文件名'));
            exit;
        }
        
        $backup_file = $backup_dir . '/' . $file;
        
        if (!file_exists($backup_file)) {
            header('Location: backup.php?error=' . urlencode('备份文件不存在'));
            exit;
        }
        
        try {
            // 删除备份文件
            if (!unlink($backup_file)) {
                throw new Exception('无法删除备份文件');
            }
            
            // 记录操作日志
            logAdminAction('管理员删除备份', 'system', 0, [
                'file' => $file,
                'admin_id' => $_SESSION['user_id'],
                'admin_username' => $_SESSION['username'],
                'action_time' => date('Y-m-d H:i:s'),
                'ip_address' => getClientIp()
            ]);
            
            $success = '备份文件删除成功';
        } catch (Exception $e) {
            $error = '删除备份文件失败: ' . $e->getMessage();
        }
        
        // 重定向到备份列表
        header('Location: backup.php' . ($success ? '?success=' . urlencode($success) : ($error ? '?error=' . urlencode($error) : '')));
        exit;
        break;
        
    default:
        // 备份列表
        try {
            // 获取备份文件列表
            $backup_files = [];
            
            if (is_dir($backup_dir)) {
                $files = scandir($backup_dir);
                
                foreach ($files as $file) {
                    if (preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.(sql|zip)$/', $file)) {
                        $backup_files[] = [
                            'name' => $file,
                            'size' => filesize($backup_dir . '/' . $file),
                            'time' => filemtime($backup_dir . '/' . $file),
                            'type' => pathinfo($file, PATHINFO_EXTENSION)
                        ];
                    }
                }
                
                // 按时间倒序排序
                usort($backup_files, function($a, $b) {
                    return $b['time'] - $a['time'];
                });
            }
        } catch (Exception $e) {
            $error = '获取备份文件列表失败: ' . $e->getMessage();
        }
        
        // 获取成功或错误消息
        if (isset($_GET['success'])) {
            $success = $_GET['success'];
        }
        
        if (isset($_GET['error'])) {
            $error = $_GET['error'];
        }
        
        // 设置页面标题
        $page_title = '备份恢复';
        
        // 加载页面头部
        include __DIR__ . '/templates/admin_header.php';
        ?>
        
        <table border="1" width="100%" cellspacing="0" cellpadding="10">
            <tr>
                <!-- 侧边栏 -->
                <td width="200" valign="top">
                    <?php include __DIR__ . '/templates/admin_sidebar.php'; ?>
                </td>
                
                <!-- 主内容区 -->
                <td valign="top">
                    <table width="100%" cellspacing="0" cellpadding="5">
                        <tr>
                            <td colspan="2">
                                <h1>备份恢复</h1>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="right">
                                <a href="backup.php?action=create">创建备份</a>
                            </td>
                        </tr>
                        
                        <?php if (!empty($error)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>错误：</strong><?php echo $error; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>成功：</strong><?php echo $success; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <tr>
                            <td colspan="2">
                                <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                    <tr>
                                        <td colspan="4"><h5>备份文件列表</h5></td>
                                    </tr>
                                    <tr>
                                        <th>文件名</th>
                                        <th>大小</th>
                                        <th>创建时间</th>
                                        <th>操作</th>
                                    </tr>
                                    <?php if (isset($backup_files) && count($backup_files) > 0): ?>
                                        <?php foreach ($backup_files as $file): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($file['name']); ?></td>
                                                <td><?php echo formatFileSize($file['size']); ?></td>
                                                <td><?php echo date('Y-m-d H:i:s', $file['time']); ?></td>
                                                <td>
                                                    <a href="backup.php?action=download&file=<?php echo urlencode($file['name']); ?>">下载</a> | 
                                                    <a href="backup.php?action=restore&file=<?php echo urlencode($file['name']); ?>" class="confirm-action" data-confirm-message="确定要恢复此备份吗？当前数据将被覆盖！">恢复</a> | 
                                                    <a href="backup.php?action=delete&file=<?php echo urlencode($file['name']); ?>" class="confirm-action" data-confirm-message="确定要删除此备份吗？此操作不可恢复！">删除</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" align="center">没有找到备份文件</td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </td>
                        </tr>
                        
                        <tr>
                            <td colspan="2">
                                <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                    <tr>
                                        <td colspan="2"><h5>备份说明</h5></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2">
                                            <ul>
                                                <li>备份文件包含数据库中所有表的结构和数据</li>
                                                <li>恢复备份将覆盖当前数据库中的所有数据，请谨慎操作</li>
                                                <li>建议定期创建备份，以防数据丢失</li>
                                                <li>备份文件保存在 <code>backups</code> 目录下</li>
                                            </ul>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        
        <?php
        // 加载页面底部
        include __DIR__ . '/templates/admin_footer.php';
        break;
}

?>

