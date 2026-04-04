<?php
date_default_timezone_set('Asia/Shanghai');
/**
 * 用户管理页面
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
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : 'list');

// 处理操作
$error = '';
$success = '';

// 获取数据库实例
$db = getDB();
$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';

// 处理用户操作
switch ($action) {
    case 'add':
        // 添加用户
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $mobile = $_POST['mobile'] ?? '';
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'user';
            $status = $_POST['status'] ?? 'active';
            
            // 验证输入
            if (empty($username) || empty($email) || empty($mobile) || empty($password)) {
                $error = '请填写所有必填字段';
            } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = '邮箱格式不正确';
            } else if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
                $error = '手机号格式不正确';
            } else if (strlen($password) < 6) {
                $error = '密码长度必须至少6个字符';
            } else {
                try {
                    // 检查用户名是否已存在
                    $exists = $db->fetchColumn(
                        "SELECT COUNT(*) FROM `{$prefix}users` WHERE `username` = :username",
                        ['username' => $username]
                    );
                    
                    if ($exists > 0) {
                        $error = '用户名已存在';
                    } else {
                        // 检查手机号是否已存在
                        $exists = $db->fetchColumn(
                            "SELECT COUNT(*) FROM `{$prefix}users` WHERE `mobile` = :mobile",
                            ['mobile' => $mobile]
                        );
                        
                        if ($exists > 0) {
                            $error = '手机号已存在';
                        } else {
                            // 创建用户
                            $db->insert("{$prefix}users", [
                                'username' => $username,
                                'email' => $email,
                                'mobile' => $mobile,
                                'password' => password_hash($password, PASSWORD_DEFAULT),
                                'role' => $role,
                                'status' => $status,
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);
                            
                            // 记录操作日志
                            logAdminAction('管理员添加用户', 'user', $db->lastInsertId(), [
                                'username' => $username,
                                'email' => $email,
                                'mobile' => $mobile,
                                'role' => $role,
                                'status' => $status,
                                'admin_id' => $_SESSION['user_id'],
                                'admin_username' => $_SESSION['username']
                            ]);
                            
                            $success = '用户添加成功';
                            
                            // 重定向到用户列表
                            header('Location: users.php?success=' . urlencode($success));
                            exit;
                        }
                    }
                } catch (Exception $e) {
                    $error = '添加用户失败: ' . $e->getMessage();
                }
            }
        }
        
        // 设置页面标题
        $page_title = '添加用户';
        
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
                                <h1>添加用户</h1>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="right">
                                <a href="users.php">返回用户列表</a>
                            </td>
                        </tr>
                        
                        <?php if (!empty($error)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>错误信息：</strong><?php echo $error; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>成功信息：</strong><?php echo $success; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <tr>
                            <td colspan="2">
                                <form method="post" action="users.php?action=add">
                                    <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                        <tr>
                                            <td width="20%">用户名</td>
                                            <td>
                                                <input type="text" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>邮箱</td>
                                            <td>
                                                <input type="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>手机号</td>
                                            <td>
                                                <input type="text" name="mobile" value="<?php echo isset($_POST['mobile']) ? htmlspecialchars($_POST['mobile']) : ''; ?>" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>密码</td>
                                            <td>
                                                <input type="password" name="password" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>角色</td>
                                            <td>
                                                <select name="role">
                                                    <?php foreach (getUserRoles() as $key => $value): ?>
                                                        <option value="<?php echo $key; ?>" <?php echo (isset($_POST['role']) && $_POST['role'] === $key) ? 'selected' : ''; ?>>
                                                            <?php echo $value; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>状态</td>
                                            <td>
                                                <select name="status">
                                                    <?php foreach (getUserStatuses() as $key => $value): ?>
                                                        <option value="<?php echo $key; ?>" <?php echo (isset($_POST['status']) && $_POST['status'] === $key) ? 'selected' : ''; ?>>
                                                            <?php echo $value; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <button type="submit">添加用户</button>
                                            </td>
                                        </tr>
                                    </table>
                                </form>
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
    
    case 'edit':
        // 编辑用户
        $user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($user_id <= 0) {
            header('Location: users.php?error=无效的用户ID');
            exit;
        }
        
        // 不能编辑系统用户
        if ($user_id == 'system' || $user_id == 'info') {
            header('Location: users.php?error=系统用户不可编辑');
            exit;
        }
        
        // 获取用户信息
        try {
            $user = $db->fetch(
                "SELECT * FROM `{$prefix}users` WHERE `id` = :id",
                ['id' => (string)$user_id]
            );
            
            if (!$user) {
                header('Location: users.php?error=用户不存在');
                exit;
            }
            
            // 处理表单提交
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $username = $_POST['username'] ?? '';
                $email = $_POST['email'] ?? '';
                $mobile = $_POST['mobile'] ?? '';
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? 'user';
                $status = $_POST['status'] ?? 'active';
                
                // 验证输入
                if (empty($username) || empty($email) || empty($mobile)) {
                    $error = '请填写所有必填字段';
                } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = '邮箱格式不正确';
                } else if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
                    $error = '手机号格式不正确';
                } else if (!empty($password) && strlen($password) < 6) {
                    $error = '密码长度必须至少6个字符';
                } else {
                    try {
                        // 检查用户名是否已被其他用户使用
                        $exists = $db->fetchColumn(
                            "SELECT COUNT(*) FROM `{$prefix}users` WHERE `username` = :username AND `id` != :id",
                            ['username' => $username, 'id' => (string)$user_id]
                        );
                        
                        if ($exists > 0) {
                            $error = '用户名已被其他用户使用';
                        } else {
                            // 检查手机号是否已被其他用户使用
                            $exists = $db->fetchColumn(
                                "SELECT COUNT(*) FROM `{$prefix}users` WHERE `mobile` = :mobile AND `id` != :id",
                                ['mobile' => $mobile, 'id' => (string)$user_id]
                            );
                            
                            if ($exists > 0) {
                                $error = '手机号已被其他用户使用';
                            } else {
                                // 更新用户
                                $update_data = [
                                    'username' => $username,
                                    'email' => $email,
                                    'mobile' => $mobile,
                                    'role' => $role,
                                    'status' => $status,
                                    'updated_at' => date('Y-m-d H:i:s')
                                ];
                                
                                // 如果提供了新密码，则更新密码
                                if (!empty($password)) {
                                    $update_data['password'] = password_hash($password, PASSWORD_DEFAULT);
                                }
                                
                                $db->update("{$prefix}users", $update_data, "`id` = :id", ['id' => (string)$user_id]);
                                
                                // 记录操作日志
                                logAdminAction('管理员编辑用户', 'user', $user_id, [
                                    'username' => $username,
                                    'email' => $email,
                                    'mobile' => $mobile,
                                    'role' => $role,
                                    'status' => $status,
                                    'admin_id' => $_SESSION['user_id'],
                                    'admin_username' => $_SESSION['username']
                                ]);
                                
                                $success = '用户编辑成功';
                                
                                // 重定向到用户列表
                                header('Location: users.php?success=' . urlencode($success));
                                exit;
                            }
                        }
                    } catch (Exception $e) {
                        $error = '编辑用户失败: ' . $e->getMessage();
                    }
                }
            }
        } catch (Exception $e) {
            $error = '获取用户信息失败: ' . $e->getMessage();
        }
        
        // 设置页面标题
        $page_title = '编辑用户';
        
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
                                <h1>编辑用户</h1>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="right">
                                <a href="users.php">返回用户列表</a>
                            </td>
                        </tr>
                        
                        <?php if (!empty($error)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>错误信息：</strong><?php echo $error; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>成功信息：</strong><?php echo $success; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <tr>
                            <td colspan="2">
                                <form method="post" action="users.php?action=edit&id=<?php echo $user_id; ?>">
                                    <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                        <tr>
                                            <td width="20%">用户名</td>
                                            <td>
                                                <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>邮箱</td>
                                            <td>
                                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required style="width: 100%;">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>手机号</td>
                                            <td>
                                                <input type="tel" name="mobile" value="<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>" required style="width: 100%;">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>密码</td>
                                            <td>
                                                <input type="password" name="password" placeholder="留空表示不修改密码" style="width: 100%;">
                                                <br>
                                                <small>如果不需要修改密码，请留空</small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>角色</td>
                                            <td>
                                                <select name="role">
                                                    <?php foreach (getUserRoles() as $key => $value): ?>
                                                        <option value="<?php echo $key; ?>" <?php echo $user['role'] === $key ? 'selected' : ''; ?>>
                                                            <?php echo $value; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>状态</td>
                                            <td>
                                                <select name="status">
                                                    <?php foreach (getUserStatuses() as $key => $value): ?>
                                                        <option value="<?php echo $key; ?>" <?php echo $user['status'] === $key ? 'selected' : ''; ?>>
                                                            <?php echo $value; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <button type="submit">保存修改</button>
                                            </td>
                                        </tr>
                                    </table>
                                </form>
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
    
    case 'delete':
        // 删除用户
        $user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($user_id <= 0) {
            header('Location: users.php?error=无效的用户ID');
            exit;
        }
        
        // 不能删除系统用户
        if ($user_id == 'system' || $user_id == 'info') {
            header('Location: users.php?error=系统用户不可删除');
            exit;
        }
        
        // 不能删除自己
        if ($user_id == $_SESSION['user_id']) {
            header('Location: users.php?error=不能删除自己');
            exit;
        }
        
        // 删除用户
        try {
            $result = $db->delete("{$prefix}users", "`id` = :id", ['id' => (string)$user_id]);
            
            if ($result) {
                // 记录操作日志
                logAdminAction('管理员删除用户', 'user', $user_id, [
                    'admin_id' => $_SESSION['user_id'],
                    'admin_username' => $_SESSION['username']
                ]);
                
                $success = '用户删除成功';
            } else {
                $error = '用户删除失败';
            }
        } catch (Exception $e) {
            $error = '删除用户失败: ' . $e->getMessage();
        }
        
        // 重定向到用户列表
        header('Location: users.php?' . ($success ? 'success=' . urlencode($success) : 'error=' . urlencode($error)));
        exit;
    
    case 'points':
        // 批量操作积分
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action_type = $_POST['action_type'] ?? '';
            $value = $_POST['value'] ?? '';
            $user_ids = $_POST['user_ids'] ?? [];
            
            if (empty($action_type) || empty($value) || empty($user_ids)) {
                $error = '请填写所有必填字段';
            } else if (!is_numeric($value)) {
                $error = '积分值必须是数字';
            } else {
                try {
                    // 构建查询条件
                    $where = [];
                    $params = [];
                    foreach ($user_ids as $user_id) {
                        $where[] = "`id` = :id_{$user_id}";
                        $params["id_{$user_id}"] = (string)$user_id;
                    }
                    $where_clause = implode(' OR ', $where);
                    
                    // 排除系统用户
                    $where_clause .= " AND `role` != 'system'";
                    
                    // 获取符合条件的用户
                    $target_users = $db->fetchAll(
                        "SELECT * FROM `{$prefix}users` WHERE {$where_clause}",
                        $params
                    );
                    
                    if (count($target_users) > 0) {
                        // 根据操作类型计算新积分
                        foreach ($target_users as $user) {
                            $current_points = isset($user['points']) ? (int)$user['points'] : 0;
                            
                            if ($action_type === 'set') {
                                $new_points = (int)$value;
                            } else if ($action_type === 'add') {
                                $new_points = $current_points + (int)$value;
                            } else if ($action_type === 'subtract') {
                                $new_points = max(0, $current_points - (int)$value);
                            } else {
                                $new_points = $current_points;
                            }
                            
                            // 更新积分
                            $db->update("{$prefix}users", [
                                'points' => $new_points,
                                'updated_at' => date('Y-m-d H:i:s')
                            ], "`id` = :id", ['id' => (string)$user['id']]);
                        }
                        
                        // 记录操作日志
                        logAdminAction('管理员批量操作积分', 'user', 0, [
                            'action_type' => $action_type,
                            'value' => $value,
                            'affected_users' => count($target_users),
                            'admin_id' => $_SESSION['user_id'],
                            'admin_username' => $_SESSION['username']
                        ]);
                        
                        $success = '批量操作积分成功，影响 ' . count($target_users) . ' 个用户';
                        
                        // 重定向回积分管理页面
                        header('Location: users.php?success=' . urlencode($success));
                        exit;
                    } else {
                        $error = '没有找到符合条件的用户';
                    }
                } catch (Exception $e) {
                    $error = '批量操作积分失败: ' . $e->getMessage();
                }
            }
        }
        
        // 设置页面标题
        $page_title = '批量操作积分';
        
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
                                <h1>批量操作积分</h1>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="right">
                                <a href="users.php">返回用户列表</a>
                            </td>
                        </tr>
                        
                        <?php if (!empty($error)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>错误信息：</strong><?php echo $error; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>成功信息：</strong><?php echo $success; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <tr>
                            <td colspan="2">
                                <form method="post" action="users.php?action=points">
                                    <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                        <tr>
                                            <td width="20%">操作类型</td>
                                            <td>
                                                <select name="action_type">
                                                    <option value="set">设置积分</option>
                                                    <option value="add">增加积分</option>
                                                    <option value="subtract">减少积分</option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>积分值</td>
                                            <td>
                                                <input type="number" name="value" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>用户ID</td>
                                            <td>
                                                <textarea name="user_ids" rows="5" cols="50" placeholder="每行一个用户ID" required></textarea>
                                                <small>每行一个用户ID</small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <button type="submit">提交操作</button>
                                            </td>
                                        </tr>
                                    </table>
                                </form>
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
    
    case 'experience':
        // 批量操作经验
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action_type = $_POST['action_type'] ?? '';
            $value = $_POST['value'] ?? '';
            $user_ids = $_POST['user_ids'] ?? [];
            
            if (empty($action_type) || empty($value) || empty($user_ids)) {
                $error = '请填写所有必填字段';
            } else if (!is_numeric($value)) {
                $error = '经验值必须是数字';
            } else {
                try {
                    // 构建查询条件
                    $where = [];
                    $params = [];
                    foreach ($user_ids as $user_id) {
                        $where[] = "`id` = :id_{$user_id}";
                        $params["id_{$user_id}"] = (string)$user_id;
                    }
                    $where_clause = implode(' OR ', $where);
                    
                    // 排除系统用户
                    $where_clause .= " AND `role` != 'system'";
                    
                    // 获取符合条件的用户
                    $target_users = $db->fetchAll(
                        "SELECT * FROM `{$prefix}users` WHERE {$where_clause}",
                        $params
                    );
                    
                    if (count($target_users) > 0) {
                        // 根据操作类型计算新经验
                        foreach ($target_users as $user) {
                            $current_experience = isset($user['experience']) ? (int)$user['experience'] : 0;
                            
                            if ($action_type === 'set') {
                                $new_experience = (int)$value;
                            } else if ($action_type === 'add') {
                                $new_experience = $current_experience + (int)$value;
                            } else if ($action_type === 'subtract') {
                                $new_experience = max(0, $current_experience - (int)$value);
                            } else {
                                $new_experience = $current_experience;
                            }
                            
                            // 更新经验
                            $db->update("{$prefix}users", [
                                'experience' => $new_experience,
                                'updated_at' => date('Y-m-d H:i:s')
                            ], "`id` = :id", ['id' => (string)$user['id']]);
                        }
                        
                        // 记录操作日志
                        logAdminAction('管理员批量操作经验', 'user', 0, [
                            'action_type' => $action_type,
                            'value' => $value,
                            'affected_users' => count($target_users),
                            'admin_id' => $_SESSION['user_id'],
                            'admin_username' => $_SESSION['username']
                        ]);
                        
                        $success = '批量操作经验成功，影响 ' . count($target_users) . ' 个用户';
                        
                        // 重定向回经验管理页面
                        header('Location: users.php?success=' . urlencode($success));
                        exit;
                    } else {
                        $error = '没有找到符合条件的用户';
                    }
                } catch (Exception $e) {
                    $error = '批量操作经验失败: ' . $e->getMessage();
                }
            }
        }
        
        // 设置页面标题
        $page_title = '批量操作经验';
        
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
                                <h1>批量操作经验</h1>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="right">
                                <a href="users.php">返回用户列表</a>
                            </td>
                        </tr>
                        
                        <?php if (!empty($error)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>错误信息：</strong><?php echo $error; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>成功信息：</strong><?php echo $success; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <tr>
                            <td colspan="2">
                                <form method="post" action="users.php?action=experience">
                                    <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                        <tr>
                                            <td width="20%">操作类型</td>
                                            <td>
                                                <select name="action_type">
                                                    <option value="set">设置经验</option>
                                                    <option value="add">增加经验</option>
                                                    <option value="subtract">减少经验</option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>经验值</td>
                                            <td>
                                                <input type="number" name="value" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>用户ID</td>
                                            <td>
                                                <textarea name="user_ids" rows="5" cols="50" placeholder="每行一个用户ID" required></textarea>
                                                <small>每行一个用户ID</small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <button type="submit">提交操作</button>
                                            </td>
                                        </tr>
                                    </table>
                                </form>
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
    
    case 'level':
        // 批量操作等级
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $value = $_POST['value'] ?? '';
            $user_ids = $_POST['user_ids'] ?? [];
            
            if (empty($value) || empty($user_ids)) {
                $error = '请填写所有必填字段';
            } else if (!is_numeric($value)) {
                $error = '等级值必须是数字';
            } else {
                try {
                    // 构建查询条件
                    $where = [];
                    $params = [];
                    foreach ($user_ids as $user_id) {
                        $where[] = "`id` = :id_{$user_id}";
                        $params["id_{$user_id}"] = (string)$user_id;
                    }
                    $where_clause = implode(' OR ', $where);
                    
                    // 排除系统用户
                    $where_clause .= " AND `role` != 'system'";
                    
                    // 获取符合条件的用户
                    $target_users = $db->fetchAll(
                        "SELECT * FROM `{$prefix}users` WHERE {$where_clause}",
                        $params
                    );
                    
                    if (count($target_users) > 0) {
                        // 更新等级
                        foreach ($target_users as $user) {
                            $db->update("{$prefix}users", [
                                'level' => (int)$value,
                                'updated_at' => date('Y-m-d H:i:s')
                            ], "`id` = :id", ['id' => (string)$user['id']]);
                        }
                        
                        // 记录操作日志
                        logAdminAction('管理员批量操作等级', 'user', 0, [
                            'value' => $value,
                            'affected_users' => count($target_users),
                            'admin_id' => $_SESSION['user_id'],
                            'admin_username' => $_SESSION['username']
                        ]);
                        
                        $success = '批量操作等级成功，影响 ' . count($target_users) . ' 个用户';
                        
                        // 重定向回等级管理页面
                        header('Location: users.php?success=' . urlencode($success));
                        exit;
                    } else {
                        $error = '没有找到符合条件的用户';
                    }
                } catch (Exception $e) {
                    $error = '批量操作等级失败: ' . $e->getMessage();
                }
            }
        }
        
        // 设置页面标题
        $page_title = '批量操作等级';
        
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
                                <h1>批量操作等级</h1>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="right">
                                <a href="users.php">返回用户列表</a>
                            </td>
                        </tr>
                        
                        <?php if (!empty($error)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>错误信息：</strong><?php echo $error; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>成功信息：</strong><?php echo $success; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <tr>
                            <td colspan="2">
                                <form method="post" action="users.php?action=level">
                                    <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                        <tr>
                                            <td width="20%">等级值</td>
                                            <td>
                                                <input type="number" name="value" required>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>用户ID</td>
                                            <td>
                                                <textarea name="user_ids" rows="5" cols="50" placeholder="每行一个用户ID" required></textarea>
                                                <small>每行一个用户ID</small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <button type="submit">提交操作</button>
                                            </td>
                                        </tr>
                                    </table>
                                </form>
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
    
    case 'badge':
        // 批量操作徽章
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action_type = $_POST['action_type'] ?? '';
            $value = $_POST['value'] ?? '';
            $user_ids = $_POST['user_ids'] ?? [];
            
            if (empty($action_type) || empty($user_ids)) {
                $error = '请填写所有必填字段';
            } else {
                try {
                    // 构建查询条件
                    $where = [];
                    $params = [];
                    foreach ($user_ids as $user_id) {
                        $where[] = "`id` = :id_{$user_id}";
                        $params["id_{$user_id}"] = (string)$user_id;
                    }
                    $where_clause = implode(' OR ', $where);
                    
                    // 排除系统用户
                    $where_clause .= " AND `role` != 'system'";
                    
                    // 获取符合条件的用户
                    $target_users = $db->fetchAll(
                        "SELECT * FROM `{$prefix}users` WHERE {$where_clause}",
                        $params
                    );
                    
                    if (count($target_users) > 0) {
                        // 确定新徽章
                        $new_badge = $action_type === 'clear' ? '' : $value;
                        
                        // 检查徽章是否有变化
                        foreach ($target_users as $user) {
                            $current_badge = isset($user['badge']) ? $user['badge'] : '';
                            
                            if ($current_badge !== $new_badge) {
                                // 更新徽章
                                $db->update("{$prefix}users", [
                                    'badge' => $new_badge,
                                    'updated_at' => date('Y-m-d H:i:s')
                                ], "`id` = :id", ['id' => (string)$user['id']]);
                                
                                // 如果设置了新徽章，发送通知
                                if (!empty($new_badge)) {
                                    // 发送徽章通知
                                    sendInteractionNotification($user['id'], 'badge_awarded', [
                                        'badge_name' => $new_badge
                                    ]);
                                }
                            }
                        }
                        
                        // 记录操作日志
                        logAdminAction('管理员批量操作徽章', 'user', 0, [
                            'action_type' => $action_type,
                            'value' => $value,
                            'affected_users' => count($target_users),
                            'admin_id' => $_SESSION['user_id'],
                            'admin_username' => $_SESSION['username']
                        ]);
                        
                        $success = '批量操作徽章成功，影响 ' . count($target_users) . ' 个用户';
                        
                        // 重定向回徽章管理页面
                        header('Location: users.php?success=' . urlencode($success));
                        exit;
                    } else {
                        $error = '没有找到符合条件的用户';
                    }
                } catch (Exception $e) {
                    $error = '批量操作徽章失败: ' . $e->getMessage();
                }
            }
        }
        
        // 设置页面标题
        $page_title = '批量操作徽章';
        
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
                                <h1>批量操作徽章</h1>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="right">
                                <a href="users.php">返回用户列表</a>
                            </td>
                        </tr>
                        
                        <?php if (!empty($error)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>错误信息：</strong><?php echo $error; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>成功信息：</strong><?php echo $success; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <tr>
                            <td colspan="2">
                                <form method="post" action="users.php?action=badge">
                                    <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                        <tr>
                                            <td width="20%">操作类型</td>
                                            <td>
                                                <select name="action_type">
                                                    <option value="set">设置徽章</option>
                                                    <option value="clear">清除徽章</option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>徽章名称</td>
                                            <td>
                                                <input type="text" name="value" placeholder="留空表示清除徽章">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>用户ID</td>
                                            <td>
                                                <textarea name="user_ids" rows="5" cols="50" placeholder="每行一个用户ID" required></textarea>
                                                <small>每行一个用户ID</small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <button type="submit">提交操作</button>
                                            </td>
                                        </tr>
                                    </table>
                                </form>
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
    
    case 'list':
    default:
        // 用户列表
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        // 获取搜索条件
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $role_filter = isset($_GET['role']) ? $_GET['role'] : '';
        $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
        
        // 构建查询条件
        $where = [];
        $params = [];
        
        if (!empty($search)) {
            $where[] = "(`username` LIKE :search OR `email` LIKE :search OR `mobile` LIKE :search)";
            $params['search'] = "%{$search}%";
        }
        
        if (!empty($role_filter)) {
            $where[] = "`role` = :role";
            $params['role'] = $role_filter;
        }
        
        if (!empty($status_filter)) {
            $where[] = "`status` = :status";
            $params['status'] = $status_filter;
        }
        
        // 排除系统用户
        $where[] = "`role` != 'system'";
        
        $where_clause = !empty($where) ? implode(' AND ', $where) : '1=1';
        
        // 获取用户总数
        $total = $db->fetchColumn(
            "SELECT COUNT(*) FROM `{$prefix}users` WHERE {$where_clause}",
            $params
        );
        
        // 获取用户列表
        $users = $db->fetchAll(
            "SELECT * FROM `{$prefix}users` WHERE {$where_clause} ORDER BY `id` DESC LIMIT :offset, :limit",
            array_merge($params, ['offset' => $offset, 'limit' => $per_page])
        );
        
        // 计算总页数
        $total_pages = ceil($total / $per_page);
        
        // 设置页面标题
        $page_title = '用户管理';
        
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
                                <h1>用户管理</h1>
                            </td>
                        </tr>
                        
                        <!-- 搜索和筛选 -->
                        <tr>
                            <td colspan="2">
                                <form method="get" action="users.php">
                                    <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                        <tr>
                                            <td>搜索</td>
                                            <td>
                                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="用户名、邮箱或手机号">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>角色</td>
                                            <td>
                                                <select name="role">
                                                    <option value="">全部</option>
                                                    <?php foreach (getUserRoles() as $key => $value): ?>
                                                        <option value="<?php echo $key; ?>" <?php echo $role_filter === $key ? 'selected' : ''; ?>>
                                                            <?php echo $value; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>状态</td>
                                            <td>
                                                <select name="status">
                                                    <option value="">全部</option>
                                                    <?php foreach (getUserStatuses() as $key => $value): ?>
                                                        <option value="<?php echo $key; ?>" <?php echo $status_filter === $key ? 'selected' : ''; ?>>
                                                            <?php echo $value; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <button type="submit">搜索</button>
                                                <a href="users.php">重置</a>
                                            </td>
                                        </tr>
                                    </table>
                                </form>
                            </td>
                        </tr>
                        
                        <!-- 快捷操作 -->
                        <tr>
                            <td colspan="2">
                                <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                    <tr>
                                        <td colspan="2">
                                            <strong>快捷操作：</strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td width="50%">
                                            <a href="users.php?action=points">批量操作积分</a>
                                        </td>
                                        <td width="50%">
                                            <a href="users.php?action=experience">批量操作经验</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td width="50%">
                                            <a href="users.php?action=level">批量操作等级</a>
                                        </td>
                                        <td width="50%">
                                            <a href="users.php?action=badge">批量操作徽章</a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        
                        <!-- 错误和成功信息 -->
                        <?php if (!empty($error)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>错误信息：</strong><?php echo $error; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <tr>
                                <td colspan="2">
                                    <strong>成功信息：</strong><?php echo $success; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <!-- 用户列表 -->
                        <tr>
                            <td colspan="2">
                                <table border="1" width="100%" cellspacing="0" cellpadding="5">
                                    <tr>
                                        <th width="5%">ID</th>
                                        <th width="15%">用户名</th>
                                        <th width="20%">邮箱</th>
                                        <th width="15%">手机号</th>
                                        <th width="10%">角色</th>
                                        <th width="10%">状态</th>
                                        <th width="10%">积分</th>
                                        <th width="10%">等级</th>
                                        <th width="5%">操作</th>
                                    </tr>
                                    <?php if (count($users) > 0): ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td align="center"><?php echo $user['id']; ?></td>
                                                <td>
                                                    <a href="../profile.php?id=<?php echo $user['id']; ?>" target="_blank">
                                                        <?php echo htmlspecialchars($user['username']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo htmlspecialchars($user['mobile'] ?? ''); ?></td>
                                                <td><?php echo getUserRoles()[$user['role']] ?? $user['role']; ?></td>
                                                <td><?php echo getUserStatuses()[$user['status']] ?? $user['status']; ?></td>
                                                <td><?php echo isset($user['points']) ? $user['points'] : 0; ?></td>
                                                <td><?php echo isset($user['level']) ? $user['level'] : 1; ?></td>
                                                <td>
                                                    <?php if ($user['role'] !== 'system'): ?>
                                                        <a href="users.php?action=edit&id=<?php echo $user['id']; ?>">编辑</a>
                                                        <a href="users.php?action=delete&id=<?php echo $user['id']; ?>" onclick="return confirm('确定要删除该用户吗？');">删除</a>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" align="center">
                                                暂无用户
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </td>
                        </tr>
                        
                        <!-- 分页 -->
                        <tr>
                            <td colspan="2" align="center">
                                <?php if ($page > 1): ?>
                                    <a href="users.php?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '' : '&search=' . urlencode($search); ?><?php echo !empty($role_filter) ? '' : '&role=' . urlencode($role_filter); ?><?php echo !empty($status_filter) ? '' : '&status=' . urlencode($status_filter); ?>">上一页</a>
                                <?php endif; ?>
                                
                                第 <?php echo $page; ?> / <?php echo $total_pages; ?> 页
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="users.php?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '' : '&search=' . urlencode($search); ?><?php echo !empty($role_filter) ? '' : '&role=' . urlencode($role_filter); ?><?php echo !empty($status_filter) ? '' : '&status=' . urlencode($status_filter); ?>">下一页</a>
                                <?php endif; ?>
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
