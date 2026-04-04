<?php
    date_default_timezone_set('Asia/Shanghai');
/**
 * 系统日志页面
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

// 处理日志操作
        switch ($action) {
            case 'clear':
                // 清空日志
                try {
                    $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
                    
                    if ($storage_type === 'json') {
                        // JSON存储：清空日志文件
                        $table = $prefix . 'logs';
                        $file = __DIR__ . '/../storage/json/' . $table . '.json';
                        if (file_exists($file)) {
                            $data = ['data' => [], 'auto_increment' => 1];
                            file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                        }
                        
                        // 清空admin_logs文件
                        $table = $prefix . 'admin_logs';
                        $file = __DIR__ . '/../storage/json/' . $table . '.json';
                        if (file_exists($file)) {
                            $data = ['data' => [], 'auto_increment' => 1];
                            file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                        }
                    } else {
                        // MySQL存储：清空日志表
                        $db->execute("TRUNCATE TABLE `{$prefix}logs`");
                        $db->execute("TRUNCATE TABLE `{$prefix}admin_logs`");
                    }
                    
                    // 记录操作日志
                    logAdminAction('管理员清空系统日志', 'system', 0, [
                        'admin_id' => $_SESSION['user_id'],
                        'admin_username' => $_SESSION['username'],
                        'action_time' => date('Y-m-d H:i:s'),
                        'ip_address' => getClientIp()
                    ]);
                    
                    header('Location: logs.php?success=' . urlencode('系统日志已清空'));
                    exit;
                } catch (Exception $e) {
                    header('Location: logs.php?error=' . urlencode('清空系统日志失败: ' . $e->getMessage()));
                    exit;
                }
                break;
        
    default:
        // 日志列表
        // 获取页码
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) {
            $page = 1;
        }
        
        // 每页显示的日志数
        $logs_per_page = 50;
        
        // 获取搜索参数
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $type = (isset($_GET['type']) && $_GET['type'] !== '') ? $_GET['type'] : '';
        $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
        $tab = (isset($_GET['tab']) && in_array($_GET['tab'], ['admin', 'regular'])) ? $_GET['tab'] : 'admin'; // 默认显示管理员日志
        
        try {
            $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
            
            // 构建查询条件
            $conditions = [];
            $params = [];
            
            if (!empty($search)) {
                $conditions[] = '(l.action LIKE :search OR l.details LIKE :search)';
                $params['search'] = '%' . $search . '%';
            }
            
            if (!empty($type)) {
                $conditions[] = 'l.target_type = :type';
                $params['type'] = $type;
            }
            
            if ($user_id > 0) {
                $conditions[] = 'l.user_id = :user_id';
                $params['user_id'] = $user_id;
            }
            
            if (!empty($start_date)) {
                $conditions[] = 'l.created_at >= :start_date';
                $params['start_date'] = $start_date . ' 00:00:00';
            }
            
            if (!empty($end_date)) {
                $conditions[] = 'l.created_at <= :end_date';
                $params['end_date'] = $end_date . ' 23:59:59';
            }
            
            $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
            
            // 根据标签页选择日志表
            $log_table = ($tab === 'admin') ? 'admin_logs' : 'logs';
            
            // 获取日志总数
            $total_logs = $db->fetchColumn(
                "SELECT COUNT(*) FROM `{$prefix}{$log_table}` l {$where_clause}",
                $params
            );
            
            // 计算总页数
            $total_pages = ceil($total_logs / $logs_per_page);
            
            // 确保页码不超过总页数
            if ($page > $total_pages && $total_pages > 0) {
                $page = $total_pages;
            }
            
            // 计算偏移量
            $offset = ($page - 1) * $logs_per_page;
            
            // 获取日志列表
            if ($storage_type === 'json') {
                // JSON存储：根据标签页选择日志表
                $logs_data = $db->select($log_table, [], 'id DESC');
                
                // 按创建时间排序
                usort($logs_data, function($a, $b) {
                    return strtotime($b['created_at']) - strtotime($a['created_at']);
                });
                
                // 应用过滤条件
                $filtered_logs = [];
                foreach ($logs_data as $log) {
                    // 搜索过滤
                    if (!empty($search)) {
                        $match = false;
                        if (strpos(strtolower($log['action']), strtolower($search)) !== false) {
                            $match = true;
                        }
                        if (!$match && !empty($log['details'])) {
                            if (strpos(strtolower($log['details']), strtolower($search)) !== false) {
                                $match = true;
                            }
                        }
                        if (!$match) {
                            continue;
                        }
                    }
                    
                    // 类型过滤
                    if (!empty($type)) {
                        if ($log['target_type'] != $type) {
                            continue;
                        }
                    }
                    
                    // 用户ID过滤
                    if ($user_id > 0) {
                        if ($log['user_id'] != $user_id) {
                            continue;
                        }
                    }
                    
                    // 开始日期过滤
                    if (!empty($start_date)) {
                        $log_date = strtotime($log['created_at']);
                        $start_date_time = strtotime($start_date . ' 00:00:00');
                        if ($log_date < $start_date_time) {
                            continue;
                        }
                    }
                    
                    // 结束日期过滤
                    if (!empty($end_date)) {
                        $log_date = strtotime($log['created_at']);
                        $end_date_time = strtotime($end_date . ' 23:59:59');
                        if ($log_date > $end_date_time) {
                            continue;
                        }
                    }
                    
                    $filtered_logs[] = $log;
                }
                
                // 计算总日志数
                $total_logs = count($filtered_logs);
                
                // 计算总页数
                $total_pages = ceil($total_logs / $logs_per_page);
                
                // 确保页码不超过总页数
                if ($page > $total_pages && $total_pages > 0) {
                    $page = $total_pages;
                }
                
                // 分页
                $offset = ($page - 1) * $logs_per_page;
                $logs = array_slice($filtered_logs, $offset, $logs_per_page);
                
                // 获取用户信息
                $users = [];
                $all_users = $db->select('users');
                foreach ($all_users as $u) {
                    $users[$u['id']] = $u;
                }
                
                // 关联用户数据
                foreach ($logs as &$log) {
                    $log['username'] = isset($users[$log['user_id']]) ? $users[$log['user_id']]['username'] : null;
                }
                unset($log);
            } else {
                // MySQL存储：使用JOIN查询，根据标签页选择日志表
                $logs = $db->fetchAll(
                    "SELECT l.*, u.username 
                    FROM `{$prefix}{$log_table}` l 
                    LEFT JOIN `{$prefix}users` u ON l.user_id = u.id 
                    {$where_clause} 
                    ORDER BY l.id DESC 
                    LIMIT :offset, :limit",
                    array_merge($params, ['offset' => $offset, 'limit' => $logs_per_page])
                );
            }
            
            // 获取日志类型列表
            if ($storage_type === 'json') {
                // JSON存储：从当前标签页的日志数据中提取唯一类型
                $logs_data = $db->select($log_table, [], 'id DESC');
                $type_map = [];
                foreach ($logs_data as $log) {
                    $log_type = $log['target_type'] ?? '';
                    if (!empty($log_type)) {
                        $type_map[$log_type] = true;
                    }
                }
                $log_types = [];
                foreach (array_keys($type_map) as $log_type) {
                    $log_types[] = ['target_type' => $log_type];
                }
                // 按类型排序
                usort($log_types, function($a, $b) {
                    return strcmp($a['target_type'], $b['target_type']);
                });
            } else {
                // MySQL存储：使用SQL查询，根据标签页选择日志表
                $log_types = $db->fetchAll(
                    "SELECT DISTINCT `target_type` FROM `{$prefix}{$log_table}` 
                    WHERE `target_type` IS NOT NULL AND `target_type` != ''
                    ORDER BY `target_type` ASC"
                );
            }
        } catch (Exception $e) {
            $error = '获取系统日志失败: ' . $e->getMessage();
        }
        
        // 获取成功或错误消息
        if (isset($_GET['success'])) {
            $success = $_GET['success'];
        }
        
        if (isset($_GET['error'])) {
            $error = $_GET['error'];
        }
        
        // 设置页面标题
        $page_title = '系统日志';
        
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
                                <h1>系统日志</h1>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="right">
                                <a href="logs.php?action=clear" class="confirm-action" data-confirm-message="确定要清空所有系统日志吗？此操作不可恢复！">清空日志</a>
                            </td>
                        </tr>
                        
                        <?php if (!empty($error)): ?>
                            <tr>
                                <td colspan="2" class="error"><strong>错误：</strong><?php echo $error; ?></td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <tr>
                                <td colspan="2" class="success"><strong>成功：</strong><?php echo $success; ?></td>
                            </tr>
                        <?php endif; ?>
                        
                        <!-- 标签页导航 -->
                        <tr>
                            <td colspan="2">
                                <div style="margin-bottom: 20px; border-bottom: 1px solid #ddd;">
                                    <a href="logs.php?tab=admin" style="display: inline-block; padding: 10px 20px; margin-right: 10px; background-color: <?php echo $tab === 'admin' ? '#f0f0f0' : '#fff'; ?>; text-decoration: none; border: 1px solid #ddd; border-bottom: none; border-top-left-radius: 5px; border-top-right-radius: 5px;">管理员日志</a>
                                    <a href="logs.php?tab=regular" style="display: inline-block; padding: 10px 20px; background-color: <?php echo $tab === 'regular' ? '#f0f0f0' : '#fff'; ?>; text-decoration: none; border: 1px solid #ddd; border-bottom: none; border-top-left-radius: 5px; border-top-right-radius: 5px;">常规日志</a>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <td colspan="2">
                                <form method="get" action="logs.php">
                                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
                                    <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                        <tr>
                                            <td width="25%">
                                                <input type="text" name="search" placeholder="搜索操作或详情" value="<?php echo htmlspecialchars($search); ?>">
                                            </td>
                                            <td width="15%">
                                                <select name="type">
                                                    <?php 
                                                    $hasSelectedType = !empty($type);
                                                    $type_map = [
                                                        'user' => '用户',
                                                        'topic' => '主题',
                                                        'post' => '回复',
                                                        'category' => '分类',
                                                        'link' => '友链',
                                                        'system' => '系统',
                                                        'email' => '邮件',
                                                        'search' => '搜索'
                                                    ];
                                                    ?>
                                                    <option value="" <?php echo !$hasSelectedType ? 'selected' : ''; ?>>所有类型</option>
                                                    <?php if (isset($log_types)): ?>
                                                        <?php foreach ($log_types as $log_type): ?>
                                                            <option value="<?php echo $log_type['target_type']; ?>" <?php echo $hasSelectedType && $type === $log_type['target_type'] ? 'selected' : ''; ?>>
                                                                <?php echo isset($type_map[$log_type['target_type']]) ? $type_map[$log_type['target_type']] : $log_type['target_type']; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                            </td>
                                            <td width="10%">
                                                <input type="number" name="user_id" placeholder="用户ID" value="<?php echo $user_id > 0 ? $user_id : ''; ?>">
                                            </td>
                                            <td width="15%">
                                                <input type="date" name="start_date" placeholder="开始日期" value="<?php echo $start_date; ?>">
                                            </td>
                                            <td width="15%">
                                                <input type="date" name="end_date" placeholder="结束日期" value="<?php echo $end_date; ?>">
                                            </td>
                                            <td width="20%" align="center">
                                                <button type="submit">搜索</button>
                                            </td>
                                        </tr>
                                    </table>
                                </form>
                            </td>
                        </tr>
                        
                        <tr>
                            <td colspan="2">
                                <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                    <tr>
                                        <th>ID</th>
                                        <th>类型</th>
                                        <th>操作</th>
                                        <th>目标ID</th>
                                        <th>用户</th>
                                        <th>IP地址</th>
                                        <th>详情</th>
                                        <th>时间</th>
                                    </tr>
                                    <?php if (isset($logs) && count($logs) > 0): ?>
                                        <?php foreach ($logs as $log): ?>
                                            <tr>
                                                <td><?php echo $log['id']; ?></td>
                                                <td><?php 
                                                    $type = $log['target_type'] ?? '';
                                                    $type_map = [
                                                        'user' => '用户',
                                                        'topic' => '主题',
                                                        'post' => '回复',
                                                        'category' => '分类',
                                                        'link' => '友链',
                                                        'system' => '系统',
                                                        'email' => '邮件',
                                                        'search' => '搜索'
                                                    ];
                                                    echo isset($type_map[$type]) ? $type_map[$type] : $type;
                                                ?></td>
                                                <td><?php 
                                                    $action = htmlspecialchars($log['action']);
                                                    $action_map = [
                                                        'send_email' => '发送邮件',
                                                        'delete_backup' => '管理员删除备份',
                                                        'create_backup' => '管理员创建备份',
                                                        'restore_backup' => '管理员恢复备份',
                                                        'download_backup' => '管理员下载备份',
                                                        'edit_topic' => '管理员编辑主题',
                                                        'delete_topic' => '管理员删除主题',
                                                        'edit_post' => '管理员编辑回复',
                                                        'delete_post' => '管理员删除回复',
                                                        'send_global_email' => '管理员发送全局邮件',
                                                        'update_settings' => '管理员更新系统设置',
                                                        'add_link' => '管理员添加友链',
                                                        'edit_link' => '管理员编辑友链',
                                                        'delete_link' => '管理员删除友链',
                                                        'clear_logs' => '管理员清空系统日志',
                                                        'search' => '用户搜索'
                                                    ];
                                                    echo isset($action_map[$action]) ? $action_map[$action] : $action;
                                                ?></td>
                                                <td><?php echo $log['target_id'] ?? ''; ?></td>
                                                <td>
                                                    <?php if ($log['user_id'] > 0): ?>
                                                        <?php echo htmlspecialchars($log['username'] ?? '未知用户'); ?> (<?php echo $log['user_id']; ?>)
                                                    <?php else: ?>
                                                        系统
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                                <td>
                                                    <?php if (!empty($log['details'])): ?>
                                                        <a href="javascript:void(0);" class="view-details" data-details="<?php echo htmlspecialchars($log['details']); ?>">查看详情</a>
                                                    <?php else: ?>
                                                        无
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatDateTime($log['created_at']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" align="center">没有找到日志记录</td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </td>
                        </tr>
                        
                        <?php if (isset($total_pages) && $total_pages > 1): ?>
                            <tr>
                                <td colspan="2" align="center">
                                    <?php
                                    // 构建分页URL
                                    $pagination_url = 'logs.php?';
                                    $pagination_url .= 'tab=' . urlencode($tab) . '&';
                                    if (!empty($search)) {
                                        $pagination_url .= 'search=' . urlencode($search) . '&';
                                    }
                                    if (!empty($type)) {
                                        $pagination_url .= 'type=' . urlencode($type) . '&';
                                    }
                                    if ($user_id > 0) {
                                        $pagination_url .= 'user_id=' . $user_id . '&';
                                    }
                                    if (!empty($start_date)) {
                                        $pagination_url .= 'start_date=' . urlencode($start_date) . '&';
                                    }
                                    if (!empty($end_date)) {
                                        $pagination_url .= 'end_date=' . urlencode($end_date) . '&';
                                    }
                                    $pagination_url .= 'page=%d';
                                    
                                    echo generateAdminPagination($page, $total_pages, $pagination_url);
                                    ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </td>
            </tr>
        </table>
        
        <script>
            // 详情查看
            document.addEventListener('DOMContentLoaded', function() {
                const viewDetailsButtons = document.querySelectorAll('.view-details');
                viewDetailsButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const details = this.getAttribute('data-details');
                        let displayDetails = details;
                        try {
                            // 尝试解析JSON
                            const detailsObj = JSON.parse(details);
                            // 转换字段名为中文
                            const fieldMap = {
                                'username': '用户名',
                                'email': '邮箱',
                                'role': '角色',
                                'status': '状态',
                                'login_time': '登录时间',
                                'login_ip': '登录IP',
                                'title': '标题',
                                'old_title': '旧标题',
                                'category_id': '分类ID',
                                'category_title': '分类标题',
                                'content_length': '内容长度',
                                'create_time': '创建时间',
                                'create_ip': '创建IP',
                                'delete_time': '删除时间',
                                'delete_ip': '删除IP',
                                'topic_id': '主题ID',
                                'topic_title': '主题标题',
                                'reply_to': '回复对象ID',
                                'reply_to_user_id': '被回复用户ID',
                                'reply_to_username': '被回复用户名',
                                'reply_time': '回复时间',
                                'reply_ip': '回复IP',
                                'author_id': '作者ID',
                                'action_time': '操作时间',
                                'ip_address': 'IP地址',
                                'admin_id': '管理员ID',
                                'admin_username': '管理员用户名',
                                'file': '文件名',
                                'file_size': '文件大小',
                                'backup_type': '备份类型',
                                'tables': '表名',
                                'message': '消息',
                                'recipient': '收件人',
                                'type': '类型',
                                'status': '状态',
                                'time': '时间',
                                'keyword': '关键词',
                                'search_time': '搜索时间',
                                'search_ip': '搜索IP'
                            };
                            
                            // 转换角色和状态值为中文
                            const valueMap = {
                                'role': {
                                    'user': '普通用户',
                                    'moderator': '版主',
                                    'admin': '管理员'
                                },
                                'status': {
                                    'active': '正常',
                                    'banned': '禁用',
                                    'pending': '待验证'
                                }
                            };
                            
                            // 递归转换字段名和值
                            function convertFields(obj) {
                                if (typeof obj !== 'object' || obj === null) {
                                    return obj;
                                }
                                if (Array.isArray(obj)) {
                                    return obj.map(item => convertFields(item));
                                }
                                const newObj = {};
                                for (const key in obj) {
                                    if (obj.hasOwnProperty(key)) {
                                        const newKey = fieldMap[key] || key;
                                        let value = obj[key];
                                        // 转换值
                                        if (valueMap[key] && valueMap[key][value]) {
                                            value = valueMap[key][value];
                                        }
                                        newObj[newKey] = convertFields(value);
                                    }
                                }
                                return newObj;
                            }
                            
                            const convertedDetails = convertFields(detailsObj);
                            displayDetails = JSON.stringify(convertedDetails, null, 2);
                        } catch (e) {
                            // 如果不是JSON，直接显示
                        }
                        // 使用alert显示详情
                        alert('日志详情:\n' + displayDetails);
                    });
                });
            });
        </script>
        
        <?php
        // 加载页面底部
        include __DIR__ . '/templates/admin_footer.php';
        break;
}
?>

