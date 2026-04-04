<?php
/**
 * 好友管理页面
 */

// 启动会话
session_start();

// 检查是否已安装
if (!file_exists(__DIR__ . '/config/config.php')) {
    header('Location: install/index.php');
    exit;
}

// 加载配置和函数
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/social_functions.php';

// 检查安装状态和闭站模式
checkInstall();

// 检查是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 获取用户ID
$user_id = $_SESSION['user_id'];

// 处理好友请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'send_request':
                // 发送好友请求
                $addressee_id = $_POST['addressee_id'] ?? 0;
                if (!empty($addressee_id) && $addressee_id != $user_id) {
                    try {
                        $db = getDB();
                        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
                        
                        // 检查是否已经发送过请求
                        $existing = $db->fetchColumn(
                            "SELECT COUNT(*) FROM `{$prefix}friend_requests` WHERE `requester_id` = :requester_id AND `addressee_id` = :addressee_id",
                            ['requester_id' => $user_id, 'addressee_id' => $addressee_id]
                        );
                        
                        if ($existing) {
                            $error = '已经发送过好友请求';
                        } else {
                            // 检查是否已经是好友
                            $is_friend = $db->fetchColumn(
                                "SELECT COUNT(*) FROM `{$prefix}user_friends` WHERE `user_id` = :user_id AND `friend_id` = :friend_id",
                                ['user_id' => $user_id, 'friend_id' => $addressee_id]
                            );
                            
                            if ($is_friend) {
                                $error = '已经是好友';
                            } else {
                                // 发送好友请求
                                $db->insert('friend_requests', [
                                    'requester_id' => $user_id,
                                    'addressee_id' => $addressee_id,
                                    'status' => 'pending',
                                    'created_at' => date('Y-m-d H:i:s')
                                ]);
                                $success = '好友请求发送成功';
                            }
                        }
                    } catch (Exception $e) {
                        $error = '发送好友请求失败: ' . $e->getMessage();
                    }
                } else {
                    $error = '无效的用户ID';
                }
                break;
                
            case 'accept_request':
                // 接受好友请求
                $request_id = $_POST['request_id'] ?? 0;
                if (!empty($request_id)) {
                    if (acceptFriendRequest($request_id, $user_id)) {
                        $success = '好友请求已接受';
                    } else {
                        $error = '接受好友请求失败';
                    }
                } else {
                    $error = '无效的请求ID';
                }
                break;
                
            case 'reject_request':
                // 拒绝好友请求
                $request_id = $_POST['request_id'] ?? 0;
                if (!empty($request_id)) {
                    try {
                        $db = getDB();
                        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
                        $db->update("{$prefix}friend_requests",
                            ['status' => 'rejected', 'updated_at' => date('Y-m-d H:i:s')],
                            '`id` = :request_id AND `addressee_id` = :user_id',
                            ['request_id' => $request_id, 'user_id' => $user_id]
                        );
                        $success = '好友请求已拒绝';
                    } catch (Exception $e) {
                        $error = '拒绝好友请求失败: ' . $e->getMessage();
                    }
                } else {
                    $error = '无效的请求ID';
                }
                break;
                
            case 'remove_friend':
                // 删除好友
                $friend_id = $_POST['friend_id'] ?? 0;
                if (!empty($friend_id)) {
                    try {
                        $db = getDB();
                        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
                        
                        // 删除双向好友关系
                        $db->delete("{$prefix}user_friends", '`user_id` = :user_id AND `friend_id` = :friend_id', ['user_id' => $user_id, 'friend_id' => $friend_id]);
                        $db->delete("{$prefix}user_friends", '`user_id` = :friend_id AND `friend_id` = :user_id', ['friend_id' => $friend_id, 'user_id' => $user_id]);
                        
                        $success = '好友已删除';
                    } catch (Exception $e) {
                        $error = '删除好友失败: ' . $e->getMessage();
                    }
                } else {
                    $error = '无效的好友ID';
                }
                break;
        }
    }
}

// 设置页面标题
$page_title = '好友管理';

// 加载页面头部
include __DIR__ . '/templates/header.php';
?>

<table border="1" width="100%" cellspacing="0" cellpadding="10">
    <tr>
        <td colspan="2">
            <table width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <td><h1>好友管理</h1></td>
                    <td align="right">
                        <a href="<?php echo getHomeUrl(); ?>">首页</a> &gt; 好友管理
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    
    <?php if (isset($error)): ?>
        <tr>
            <td colspan="2" class="error"><?php echo $error; ?></td>
        </tr>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <tr>
            <td colspan="2" class="success"><?php echo $success; ?></td>
        </tr>
    <?php endif; ?>
    
    <!-- 发送好友请求 -->
    <tr>
        <td colspan="2">
            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                <tr>
                    <td colspan="2"><h5>发送好友请求</h5></td>
                </tr>
                <tr>
                    <td colspan="2">
                        <form method="post" action="friends.php">
                            <input type="hidden" name="action" value="send_request">
                            <input type="text" name="addressee_id" placeholder="请输入用户ID" style="width: 200px; padding: 5px;" required>
                            <button type="submit" style="padding: 5px 15px; background-color: #4A90E2; color: white; border: none; border-radius: 3px; cursor: pointer;">发送请求</button>
                        </form>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    
    <tr>
        <td width="50%" valign="top">
            <!-- 好友请求 -->
            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                <tr>
                    <td colspan="2"><h5>好友请求</h5></td>
                </tr>
                <?php
                try {
                    $friend_requests = getUserFriendRequests($user_id);
                    
                    if (count($friend_requests) > 0) {
                        foreach ($friend_requests as $request) {
                            ?>
                            <tr>
                                <td width="20%" align="center">
                                    <img src="<?php echo htmlspecialchars(getUserAvatar(['avatar' => $request['requester_avatar'] ?? ''], 50)); ?>" alt="<?php echo htmlspecialchars($request['requester_name'] ?? '未知用户'); ?>" style="width: 40px; height: 40px;" onerror="this.src='/icon.png'; this.onerror=null;">
                                </td>
                                <td>
                                    <a href="profile.php?id=<?php echo $request['requester_id']; ?>"><?php echo htmlspecialchars($request['requester_name'] ?? '未知用户'); ?> (ID: <?php echo $request['requester_id']; ?>)</a>
                                    <br>
                                    <small style="color: #999;"><?php echo formatDateTime($request['created_at']); ?></small>
                                    <div style="margin-top: 5px;">
                                        <form method="post" action="friends.php" style="display: inline;">
                                            <input type="hidden" name="action" value="accept_request">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" style="padding: 2px 10px; background-color: green; color: white; border: none; border-radius: 3px; cursor: pointer; margin-right: 5px;">接受</button>
                                        </form>
                                        <form method="post" action="friends.php" style="display: inline;">
                                            <input type="hidden" name="action" value="reject_request">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" style="padding: 2px 10px; background-color: red; color: white; border: none; border-radius: 3px; cursor: pointer;">拒绝</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="2" align="center">暂无好友请求</td>
                        </tr>
                        <?php
                    }
                } catch (Exception $e) {
                    ?>
                    <tr>
                        <td colspan="2" class="error">加载好友请求失败: <?php echo $e->getMessage(); ?></td>
                    </tr>
                    <?php
                }
                ?>
            </table>
        </td>
        
        <td width="50%" valign="top">
            <!-- 好友列表 -->
            <table border="1" width="100%" cellspacing="0" cellpadding="5">
                <tr>
                    <td colspan="2"><h5>好友列表</h5></td>
                </tr>
                <?php
                try {
                    $db = getDB();
                    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'forum_';
                    
                    // 获取好友关系列表（JSON存储不支持JOIN）
                    $friendRelations = $db->fetchAll(
                        "SELECT * FROM `{$prefix}user_friends` WHERE user_id = :user_id ORDER BY created_at DESC",
                        ['user_id' => (string)$user_id]
                    );
                    
                    // 获取好友用户信息
                    $friends = [];
                    foreach ($friendRelations as $relation) {
                        $user = $db->fetch(
                            "SELECT id, username, avatar FROM `{$prefix}users` WHERE id = :user_id",
                            ['user_id' => (string)$relation['friend_id']]
                        );
                        if ($user) {
                            $friends[] = $user;
                        }
                    }
                    
                    if (count($friends) > 0) {
                        foreach ($friends as $friend) {
                            ?>
                            <tr>
                                <td width="20%" align="center">
                                    <img src="<?php echo htmlspecialchars(getUserAvatar($friend, 50)); ?>" alt="<?php echo htmlspecialchars($friend['username']); ?>" style="width: 40px; height: 40px;" onerror="this.src='/icon.png'; this.onerror=null;">
                                </td>
                                <td>
                                    <a href="profile.php?id=<?php echo $friend['id']; ?>"><?php echo htmlspecialchars($friend['username']); ?> (ID: <?php echo $friend['id']; ?>)</a>
                                    <a href="messages.php?action=conversation&user_id=<?php echo $friend['id']; ?>" style="margin-left: 10px; padding: 2px 5px; background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 3px; text-decoration: none; color: #333; font-size: 12px;">发消息</a>
                                    <form method="post" action="friends.php" style="display: inline; margin-left: 10px;">
                                        <input type="hidden" name="action" value="remove_friend">
                                        <input type="hidden" name="friend_id" value="<?php echo $friend['id']; ?>">
                                        <button type="submit" onclick="return confirm('确定要删除这个好友吗？');" style="padding: 2px 5px; background-color: red; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px;">删除</button>
                                    </form>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="2" align="center">暂无好友</td>
                        </tr>
                        <?php
                    }
                } catch (Exception $e) {
                    ?>
                    <tr>
                        <td colspan="2" class="error">加载好友列表失败: <?php echo $e->getMessage(); ?></td>
                    </tr>
                    <?php
                }
                ?>
            </table>
        </td>
    </tr>
</table>

<?php
// 加载页面底部
include __DIR__ . '/templates/footer.php';
?>