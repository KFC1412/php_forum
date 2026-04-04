<?php
    date_default_timezone_set('Asia/Shanghai');
/**
 * 举报和申诉管理页面
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

// 处理举报操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $report_id = $_POST['report_id'] ?? 0;
        $appeal_id = $_POST['appeal_id'] ?? 0;
        
        $db = getDB();
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
        
        // 加载申诉邮件功能
        require_once __DIR__ . '/../includes/mail_functions_appeal.php';
        
        if ($report_id > 0) {
            switch ($action) {
                case 'process':
                    // 处理举报
                    $report = $db->fetch("SELECT * FROM `{$prefix}reports` WHERE `id` = :id", ['id' => $report_id]);
                    $db->update(
                        "{$prefix}reports",
                        ['status' => 'processed', 'processed_by' => $_SESSION['user_id'], 'updated_at' => date('Y-m-d H:i:s')],
                        "`id` = :id",
                        ['id' => $report_id]
                    );
                    
                    // 检查处理后举报数量是否达到自动处理阈值
                    if ($report) {
                        $ban_threshold = (int)getSetting('report_ban_threshold', 10);
                        $report_count = getReportCount($report['report_type'], $report['target_id']);
                        
                        if ($report_count >= $ban_threshold) {
                            if ($report['report_type'] === 'user') {
                                // 用户达到封禁阈值：禁用发布功能，设置状态为 restricted
                                $db->update("{$prefix}users", [
                                    'status' => 'restricted',
                                    'can_post_topic' => 0,
                                    'can_post_reply' => 0
                                ], '`id` = :id', ['id' => $report['target_id']]);
                            } elseif ($report['report_type'] === 'topic') {
                                // 主题达到封禁阈值：自动隐藏主题
                                $db->update("{$prefix}topics", [
                                    'status' => 'hidden',
                                    'hidden_reason' => '举报数量达到封禁阈值',
                                    'hidden_at' => date('Y-m-d H:i:s')
                                ], '`id` = :id', ['id' => $report['target_id']]);
                            }
                        }
                    }
                    break;
                case 'dismiss':
                    // 驳回举报
                    $db->update(
                        "{$prefix}reports",
                        ['status' => 'dismissed', 'processed_by' => $_SESSION['user_id'], 'updated_at' => date('Y-m-d H:i:s')],
                        "`id` = :id",
                        ['id' => $report_id]
                    );
                    break;
                case 'delete':
                    // 删除举报
                    $report = $db->fetch("SELECT * FROM `{$prefix}reports` WHERE `id` = :id", ['id' => $report_id]);
                    $db->delete(
                        "{$prefix}reports",
                        "`id` = :id",
                        ['id' => $report_id]
                    );
                    
                    // 检查是否还有针对该目标的其他已处理举报
                    if ($report) {
                        $remaining_count = $db->fetchColumn(
                            "SELECT COUNT(*) FROM `{$prefix}reports` WHERE `report_type` = :report_type AND `target_id` = :target_id AND `status` = 'processed'",
                            ['report_type' => $report['report_type'], 'target_id' => $report['target_id']]
                        );
                        
                        // 如果没有剩余已处理的举报，恢复用户或主题状态
                        if ($remaining_count == 0) {
                            if ($report['report_type'] === 'user') {
                                // 恢复用户状态为正常
                                $db->update("{$prefix}users", [
                                    'status' => 'active',
                                    'can_post_topic' => 1,
                                    'can_post_reply' => 1
                                ], '`id` = :id', ['id' => $report['target_id']]);
                            } elseif ($report['report_type'] === 'topic') {
                                // 恢复主题状态为已发布
                                $db->update("{$prefix}topics", [
                                    'status' => 'published',
                                    'hidden_reason' => null,
                                    'hidden_at' => null
                                ], '`id` = :id', ['id' => $report['target_id']]);
                            }
                        }
                    }
                    break;
            }
        }
        
        // 处理申诉
        if ($appeal_id > 0) {
            $process_note = $_POST['process_note'] ?? '';
            
            switch ($action) {
                case 'approve_appeal':
                    // 通过申诉
                    $appeal = $db->fetch("SELECT * FROM `{$prefix}appeals` WHERE `id` = :id", ['id' => $appeal_id]);
                    if ($appeal) {
                        // 更新申诉状态
                        $db->update(
                            "{$prefix}appeals",
                            ['status' => 'approved', 'processed_by' => $_SESSION['user_id'], 'process_note' => $process_note, 'updated_at' => date('Y-m-d H:i:s')],
                            "`id` = :id",
                            ['id' => $appeal_id]
                        );
                        
                        // 恢复用户或主题状态
                        if ($appeal['appeal_type'] === 'user') {
                            $db->update(
                                "{$prefix}users",
                                ['status' => 'active', 'can_post_topic' => 1, 'can_post_reply' => 1],
                                "`id` = :id",
                                ['id' => $appeal['target_id']]
                            );
                        } elseif ($appeal['appeal_type'] === 'topic') {
                            $db->update(
                                "{$prefix}topics",
                                ['status' => 'published'],
                                "`id` = :id",
                                ['id' => $appeal['target_id']]
                            );
                        }
                        
                        // 发送邮件通知
                        sendAppealResultNotification($appeal_id, 'approved', $process_note);
                        
                        // 发送站内互动消息通知
                        require_once __DIR__ . '/../includes/mail_functions.php';
                        sendInteractionNotification($appeal['user_id'], 'appeal_result', [
                            'appeal_type' => $appeal['appeal_type'] === 'user' ? '账号限制' : '主题隐藏',
                            'appeal_content' => $appeal['reason'],
                            'result' => '通过',
                            'comment' => $process_note
                        ]);
                    }
                    break;
                case 'reject_appeal':
                    // 驳回申诉
                    $db->update(
                        "{$prefix}appeals",
                        ['status' => 'rejected', 'processed_by' => $_SESSION['user_id'], 'process_note' => $process_note, 'updated_at' => date('Y-m-d H:i:s')],
                        "`id` = :id",
                        ['id' => $appeal_id]
                    );
                    
                    // 发送邮件通知
                    sendAppealResultNotification($appeal_id, 'rejected', $process_note);
                    
                    // 发送站内互动消息通知
                    require_once __DIR__ . '/../includes/mail_functions.php';
                    $appeal = $db->fetch("SELECT * FROM `{$prefix}appeals` WHERE `id` = :id", ['id' => $appeal_id]);
                    if ($appeal) {
                        sendInteractionNotification($appeal['user_id'], 'appeal_result', [
                            'appeal_type' => $appeal['appeal_type'] === 'user' ? '账号限制' : '主题隐藏',
                            'appeal_content' => $appeal['reason'],
                            'result' => '驳回',
                            'comment' => $process_note
                        ]);
                    }
                    break;
                case 'delete_appeal':
                    // 删除申诉
                    $appeal = $db->fetch("SELECT * FROM `{$prefix}appeals` WHERE `id` = :id", ['id' => $appeal_id]);
                    $db->delete(
                        "{$prefix}appeals",
                        "`id` = :id",
                        ['id' => $appeal_id]
                    );
                    
                    // 检查是否还有针对该目标的其他已处理举报
                    if ($appeal) {
                        $remaining_report_count = $db->fetchColumn(
                            "SELECT COUNT(*) FROM `{$prefix}reports` WHERE `report_type` = :report_type AND `target_id` = :target_id AND `status` = 'processed'",
                            ['report_type' => $appeal['appeal_type'], 'target_id' => $appeal['target_id']]
                        );
                        
                        // 如果没有剩余已处理的举报，恢复用户或主题状态
                        if ($remaining_report_count == 0) {
                            if ($appeal['appeal_type'] === 'user') {
                                // 恢复用户状态为正常
                                $db->update("{$prefix}users", [
                                    'status' => 'active',
                                    'can_post_topic' => 1,
                                    'can_post_reply' => 1
                                ], '`id` = :id', ['id' => $appeal['target_id']]);
                            } elseif ($appeal['appeal_type'] === 'topic') {
                                // 恢复主题状态为已发布
                                $db->update("{$prefix}topics", [
                                    'status' => 'published',
                                    'hidden_reason' => null,
                                    'hidden_at' => null
                                ], '`id` = :id', ['id' => $appeal['target_id']]);
                            }
                        }
                    }
                    break;
            }
        }
    }
}

// 获取举报列表
try {
    $db = getDB();
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
    $storage_type = defined('STORAGE_TYPE') ? STORAGE_TYPE : 'mysql';
    
    // 获取状态筛选
    $status = $_GET['status'] ?? 'all';
    
    // 构建查询条件
    $where = '';
    $params = [];
    
    if ($status !== 'all') {
        $where = "WHERE `status` = :status";
        $params['status'] = $status;
    }
    
    // 获取举报总数
    $total_reports = $db->fetchColumn(
        "SELECT COUNT(*) FROM `{$prefix}reports` {$where}",
        $params
    );
    
    // 分页
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) {
        $page = 1;
    }
    $per_page = 20;
    $total_pages = ceil($total_reports / $per_page);
    $offset = ($page - 1) * $per_page;
    
    // 获取举报列表
    if ($storage_type === 'json') {
        // JSON存储：使用简单查询
        $reports = $db->select('reports', $status !== 'all' ? ['status' => $status] : [], 'created_at DESC', $per_page, $offset);
    } else {
        // MySQL存储：使用SQL查询
        $reports = $db->fetchAll(
            "SELECT * FROM `{$prefix}reports` {$where} ORDER BY `created_at` DESC LIMIT :offset, :limit",
            array_merge($params, ['offset' => $offset, 'limit' => $per_page])
        );
    }
    
    // 获取申诉列表
    $appeal_status = $_GET['appeal_status'] ?? 'all';
    $appeal_where = '';
    $appeal_params = [];
    
    if ($appeal_status !== 'all') {
        $appeal_where = "WHERE `status` = :status";
        $appeal_params['status'] = $appeal_status;
    }
    
    $total_appeals = $db->fetchColumn(
        "SELECT COUNT(*) FROM `{$prefix}appeals` {$appeal_where}",
        $appeal_params
    );
    
    $appeal_page = isset($_GET['appeal_page']) ? (int)$_GET['appeal_page'] : 1;
    if ($appeal_page < 1) {
        $appeal_page = 1;
    }
    $appeal_total_pages = ceil($total_appeals / $per_page);
    $appeal_offset = ($appeal_page - 1) * $per_page;
    
    if ($storage_type === 'json') {
        $appeals = $db->select('appeals', $appeal_status !== 'all' ? ['status' => $appeal_status] : [], 'created_at DESC', $per_page, $appeal_offset);
    } else {
        $appeals = $db->fetchAll(
            "SELECT * FROM `{$prefix}appeals` {$appeal_where} ORDER BY `created_at` DESC LIMIT :offset, :limit",
            array_merge($appeal_params, ['offset' => $appeal_offset, 'limit' => $per_page])
        );
    }
    
    // 获取相关用户信息
    $users = [];
    $all_users = $db->select('users');
    foreach ($all_users as $user) {
        $users[$user['id']] = $user;
    }
    
    // 关联用户数据
    foreach ($reports as &$report) {
        $report['reporter_username'] = isset($users[$report['reporter_id']]) ? $users[$report['reporter_id']]['username'] : '未知用户';
        $report['processed_by_username'] = isset($report['processed_by']) && isset($users[$report['processed_by']]) ? $users[$report['processed_by']]['username'] : '';
        
        // 获取目标信息
        switch ($report['report_type']) {
            case 'user':
                $target = $db->findById('users', $report['target_id']);
                $report['target_info'] = $target ? $target['username'] : '未知用户';
                $report['target_url'] = '../profile.php?id=' . $report['target_id'];
                break;
            case 'topic':
                $target = $db->findById('topics', $report['target_id']);
                $report['target_info'] = $target ? $target['title'] : '未知主题';
                $report['target_url'] = '../topic.php?id=' . $report['target_id'];
                break;
            case 'post':
                $target = $db->findById('posts', $report['target_id']);
                if ($target) {
                    $topic = $db->findById('topics', $target['topic_id']);
                    $report['target_info'] = '回复 #' . $report['target_id'] . ' in ' . ($topic ? $topic['title'] : '未知主题');
                    $report['target_url'] = '../topic.php?id=' . $target['topic_id'] . '#post-' . $report['target_id'];
                } else {
                    $report['target_info'] = '未知回复';
                    $report['target_url'] = '';
                }
                break;
            default:
                $report['target_info'] = '未知目标';
                $report['target_url'] = '';
        }
    }
    unset($report);
    
    // 获取统计数据
    $pending_count = $db->fetchColumn("SELECT COUNT(*) FROM `{$prefix}reports` WHERE `status` = 'pending'");
    $processed_count = $db->fetchColumn("SELECT COUNT(*) FROM `{$prefix}reports` WHERE `status` = 'processed'");
    $dismissed_count = $db->fetchColumn("SELECT COUNT(*) FROM `{$prefix}reports` WHERE `status` = 'dismissed'");
    
    // 获取申诉统计数据
    $appeal_pending_count = $db->fetchColumn("SELECT COUNT(*) FROM `{$prefix}appeals` WHERE `status` = 'pending'");
    $appeal_approved_count = $db->fetchColumn("SELECT COUNT(*) FROM `{$prefix}appeals` WHERE `status` = 'approved'");
    $appeal_rejected_count = $db->fetchColumn("SELECT COUNT(*) FROM `{$prefix}appeals` WHERE `status` = 'rejected'");
    
} catch (Exception $e) {
    $error = '加载举报信息失败: ' . $e->getMessage();
}

// 设置页面标题
$page_title = '举报和申诉管理';

// 获取当前标签页
$tab = $_GET['tab'] ?? 'reports';

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
                        <h1>举报和申诉管理</h1>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" align="right">
                        <a href="index.php">返回控制面板</a>
                    </td>
                </tr>
                
                <!-- 标签页切换 -->
                <tr>
                    <td colspan="2" style="padding: 10px 0; border-bottom: 2px solid #4A90E2;">
                        <a href="reports.php?tab=reports" style="padding: 10px 20px; <?php echo $tab === 'reports' ? 'background-color: #4A90E2; color: white;' : 'background-color: #f0f0f0; color: #333;'; ?> text-decoration: none; border-radius: 5px 5px 0 0; margin-right: 5px;">举报管理</a>
                        <a href="reports.php?tab=appeals" style="padding: 10px 20px; <?php echo $tab === 'appeals' ? 'background-color: #4A90E2; color: white;' : 'background-color: #f0f0f0; color: #333;'; ?> text-decoration: none; border-radius: 5px 5px 0 0;">申诉管理 <?php if ($appeal_pending_count > 0): ?><span style="background-color: red; color: white; padding: 2px 6px; border-radius: 10px; font-size: 12px;"><?php echo $appeal_pending_count; ?></span><?php endif; ?></a>
                    </td>
                </tr>
                
                <?php if (isset($error)): ?>
                    <tr>
                        <td colspan="2"><?php echo $error; ?></td>
                    </tr>
                <?php elseif ($tab === 'reports'): ?>
                    <!-- 举报管理标签页 -->
                    <!-- 统计信息 -->
                    <tr>
                        <td colspan="2">
                            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                <tr>
                                    <td width="33%">
                                        <strong>待处理举报</strong>
                                        <br>
                                        <?php echo $pending_count; ?>
                                    </td>
                                    <td width="33%">
                                        <strong>已处理举报</strong>
                                        <br>
                                        <?php echo $processed_count; ?>
                                    </td>
                                    <td width="34%">
                                        <strong>已驳回举报</strong>
                                        <br>
                                        <?php echo $dismissed_count; ?>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- 状态筛选 -->
                    <tr>
                        <td colspan="2" style="padding: 10px 0;">
                            <a href="reports.php?status=all" <?php echo $status === 'all' ? 'style="font-weight: bold;"' : ''; ?>>全部</a> | 
                            <a href="reports.php?status=pending" <?php echo $status === 'pending' ? 'style="font-weight: bold;"' : ''; ?>>待处理</a> | 
                            <a href="reports.php?status=processed" <?php echo $status === 'processed' ? 'style="font-weight: bold;"' : ''; ?>>已处理</a> | 
                            <a href="reports.php?status=dismissed" <?php echo $status === 'dismissed' ? 'style="font-weight: bold;"' : ''; ?>>已驳回</a>
                        </td>
                    </tr>
                    
                    <!-- 举报列表 -->
                    <tr>
                        <td colspan="2">
                            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                <tr>
                                    <th width="50">ID</th>
                                    <th width="150">举报人</th>
                                    <th width="100">举报类型</th>
                                    <th>目标</th>
                                    <th width="200">举报原因</th>
                                    <th width="120">状态</th>
                                    <th width="120">处理人</th>
                                    <th width="150">举报时间</th>
                                    <th width="150">操作</th>
                                </tr>
                                <?php if (count($reports) > 0): ?>
                                    <?php foreach ($reports as $report): ?>
                                        <tr>
                                            <td><?php echo $report['id']; ?></td>
                                            <td><a href="../profile.php?id=<?php echo $report['reporter_id']; ?>" target="_blank"><?php echo htmlspecialchars($report['reporter_username']); ?></a></td>
                                            <td><?php 
                                                switch ($report['report_type']) {
                                                    case 'user': echo '用户'; break;
                                                    case 'topic': echo '主题'; break;
                                                    case 'post': echo '回复'; break;
                                                    default: echo '未知';
                                                }
                                            ?></td>
                                            <td><?php if ($report['target_url']): ?>
                                                <a href="<?php echo $report['target_url']; ?>" target="_blank"><?php echo htmlspecialchars($report['target_info']); ?></a>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($report['target_info']); ?>
                                            <?php endif; ?></td>
                                            <td><?php echo htmlspecialchars($report['reason']); ?></td>
                                            <td><?php 
                                                switch ($report['status']) {
                                                    case 'pending': echo '<span style="color: orange;">待处理</span>'; break;
                                                    case 'processed': echo '<span style="color: green;">已处理</span>'; break;
                                                    case 'dismissed': echo '<span style="color: gray;">已驳回</span>'; break;
                                                    default: echo '未知';
                                                }
                                            ?></td>
                                            <td><?php echo $report['processed_by_username'] ? htmlspecialchars($report['processed_by_username']) : '-'; ?></td>
                                            <td><?php echo formatDateTime($report['created_at']); ?></td>
                                            <td>
                                                <?php if ($report['status'] === 'pending'): ?>
                                                    <form method="post" action="reports.php?tab=reports&status=<?php echo $status; ?>" style="display: inline;">
                                                        <button type="submit" name="action" value="process" style="margin-right: 5px; padding: 2px 5px; background-color: green; color: white; border: none; border-radius: 3px; cursor: pointer;">处理</button>
                                                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                    </form>
                                                    <form method="post" action="reports.php?tab=reports&status=<?php echo $status; ?>" style="display: inline;">
                                                        <button type="submit" name="action" value="dismiss" style="margin-right: 5px; padding: 2px 5px; background-color: gray; color: white; border: none; border-radius: 3px; cursor: pointer;">驳回</button>
                                                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                    </form>
                                                <?php endif; ?>
                                                <form method="post" action="reports.php?tab=reports&status=<?php echo $status; ?>" style="display: inline;">
                                                    <button type="submit" name="action" value="delete" onclick="return confirm('确定要删除这个举报记录吗？');" style="padding: 2px 5px; background-color: red; color: white; border: none; border-radius: 3px; cursor: pointer;">删除</button>
                                                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" align="center">暂无举报记录</td>
                                    </tr>
                                <?php endif; ?>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- 分页 -->
                    <?php if ($total_pages > 1): ?>
                        <tr>
                            <td colspan="2" align="center">
                                <?php 
                                    $pagination_url = 'reports.php?tab=reports&status=' . urlencode($status) . '&page=';
                                    echo generatePagination($page, $total_pages, $pagination_url); 
                                ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    
                <?php elseif ($tab === 'appeals'): ?>
                    <!-- 申诉管理标签页 -->
                    <!-- 统计信息 -->
                    <tr>
                        <td colspan="2">
                            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                <tr>
                                    <td width="33%">
                                        <strong>待处理申诉</strong>
                                        <br>
                                        <?php echo $appeal_pending_count; ?>
                                    </td>
                                    <td width="33%">
                                        <strong>已通过申诉</strong>
                                        <br>
                                        <?php echo $appeal_approved_count; ?>
                                    </td>
                                    <td width="34%">
                                        <strong>已驳回申诉</strong>
                                        <br>
                                        <?php echo $appeal_rejected_count; ?>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- 状态筛选 -->
                    <tr>
                        <td colspan="2" style="padding: 10px 0;">
                            <a href="reports.php?tab=appeals&appeal_status=all" <?php echo $appeal_status === 'all' ? 'style="font-weight: bold;"' : ''; ?>>全部</a> | 
                            <a href="reports.php?tab=appeals&appeal_status=pending" <?php echo $appeal_status === 'pending' ? 'style="font-weight: bold;"' : ''; ?>>待处理</a> | 
                            <a href="reports.php?tab=appeals&appeal_status=approved" <?php echo $appeal_status === 'approved' ? 'style="font-weight: bold;"' : ''; ?>>已通过</a> | 
                            <a href="reports.php?tab=appeals&appeal_status=rejected" <?php echo $appeal_status === 'rejected' ? 'style="font-weight: bold;"' : ''; ?>>已驳回</a>
                        </td>
                    </tr>
                    
                    <!-- 申诉列表 -->
                    <tr>
                        <td colspan="2">
                            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                <tr>
                                    <th width="50">ID</th>
                                    <th width="150">申诉人</th>
                                    <th width="100">申诉类型</th>
                                    <th>目标</th>
                                    <th width="200">申诉原因</th>
                                    <th width="120">状态</th>
                                    <th width="120">处理人</th>
                                    <th width="150">申诉时间</th>
                                    <th width="200">操作</th>
                                </tr>
                                <?php if (count($appeals) > 0): ?>
                                    <?php foreach ($appeals as $appeal): 
                                        $appeal_user = isset($users[$appeal['user_id']]) ? $users[$appeal['user_id']] : null;
                                        $processed_by_user = isset($appeal['processed_by']) && isset($users[$appeal['processed_by']]) ? $users[$appeal['processed_by']] : null;
                                        
                                        // 获取目标信息
                                        if ($appeal['appeal_type'] === 'user') {
                                            $target = $db->findById('users', $appeal['target_id']);
                                            $target_info = $target ? $target['username'] : '未知用户';
                                            $target_url = '../profile.php?id=' . $appeal['target_id'];
                                        } elseif ($appeal['appeal_type'] === 'topic') {
                                            $target = $db->findById('topics', $appeal['target_id']);
                                            $target_info = $target ? $target['title'] : '未知主题';
                                            $target_url = '../topic.php?id=' . $appeal['target_id'];
                                        } else {
                                            $target_info = '未知目标';
                                            $target_url = '';
                                        }
                                    ?>
                                        <tr>
                                            <td><?php echo $appeal['id']; ?></td>
                                            <td><a href="../profile.php?id=<?php echo $appeal['user_id']; ?>" target="_blank"><?php echo htmlspecialchars($appeal_user ? $appeal_user['username'] : '未知用户'); ?></a></td>
                                            <td><?php 
                                                switch ($appeal['appeal_type']) {
                                                    case 'user': echo '账号限制'; break;
                                                    case 'topic': echo '主题隐藏'; break;
                                                    default: echo '未知';
                                                }
                                            ?></td>
                                            <td><?php if ($target_url): ?>
                                                <a href="<?php echo $target_url; ?>" target="_blank"><?php echo htmlspecialchars($target_info); ?></a>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($target_info); ?>
                                            <?php endif; ?></td>
                                            <td><?php echo htmlspecialchars($appeal['reason']); ?></td>
                                            <td><?php 
                                                switch ($appeal['status']) {
                                                    case 'pending': echo '<span style="color: orange; font-weight: bold;">待处理</span>'; break;
                                                    case 'approved': echo '<span style="color: green; font-weight: bold;">已通过</span>'; break;
                                                    case 'rejected': echo '<span style="color: red; font-weight: bold;">已驳回</span>'; break;
                                                    default: echo '未知';
                                                }
                                            ?></td>
                                            <td><?php echo $processed_by_user ? htmlspecialchars($processed_by_user['username']) : '-'; ?></td>
                                            <td><?php echo formatDateTime($appeal['created_at']); ?></td>
                                            <td>
                                                <?php if ($appeal['status'] === 'pending'): ?>
                                                    <form method="post" action="reports.php?tab=appeals&appeal_status=<?php echo $appeal_status; ?>" style="display: block; margin-bottom: 5px;">
                                                        <div style="margin-bottom: 5px;">
                                                            <textarea name="process_note" placeholder="处理备注（可选）" style="width: 100%; height: 40px; font-size: 12px; padding: 3px;"></textarea>
                                                        </div>
                                                        <button type="submit" name="action" value="approve_appeal" style="margin-right: 5px; padding: 2px 5px; background-color: green; color: white; border: none; border-radius: 3px; cursor: pointer;">通过</button>
                                                        <button type="submit" name="action" value="reject_appeal" style="margin-right: 5px; padding: 2px 5px; background-color: orange; color: white; border: none; border-radius: 3px; cursor: pointer;">驳回</button>
                                                        <input type="hidden" name="appeal_id" value="<?php echo $appeal['id']; ?>">
                                                    </form>
                                                <?php else: ?>
                                                    <?php if (!empty($appeal['process_note'])): ?>
                                                        <small style="color: #666;">备注: <?php echo htmlspecialchars($appeal['process_note']); ?></small><br>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <form method="post" action="reports.php?tab=appeals&appeal_status=<?php echo $appeal_status; ?>" style="display: block; margin-top: 5px;">
                                                    <button type="submit" name="action" value="delete_appeal" onclick="return confirm('确定要删除这个申诉记录吗？');" style="padding: 2px 5px; background-color: red; color: white; border: none; border-radius: 3px; cursor: pointer;">删除</button>
                                                    <input type="hidden" name="appeal_id" value="<?php echo $appeal['id']; ?>">
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" align="center">暂无申诉记录</td>
                                    </tr>
                                <?php endif; ?>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- 分页 -->
                    <?php if ($appeal_total_pages > 1): ?>
                        <tr>
                            <td colspan="2" align="center">
                                <?php 
                                    $appeal_pagination_url = 'reports.php?tab=appeals&appeal_status=' . urlencode($appeal_status) . '&appeal_page=';
                                    echo generatePagination($appeal_page, $appeal_total_pages, $appeal_pagination_url); 
                                ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endif; ?>
            </table>
        </td>
    </tr>
</table>

<?php
// 加载页面底部
include __DIR__ . '/templates/admin_footer.php';
?>