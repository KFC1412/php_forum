<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?><?php echo htmlspecialchars(getSetting('site_name', 'EDUCN论坛')); ?></title>
    <link rel="stylesheet" href="assets/css/forum.css">
    <link rel="icon" type="image/png" href="icon.png">
</head>
<body>
    <table border="1" width="100%" cellspacing="0" cellpadding="5">
        <tr>
            <td width="20%" nowrap>
                <a href="<?php echo getHomeUrl(); ?>" style="font-weight: bold;"><?php echo htmlspecialchars(getSetting('site_name', 'EDUCN论坛')); ?></a>
            </td>
            <td width="50%" nowrap>
                <a href="<?php echo getHomeUrl(); ?>">首页</a> | 
                <a href="<?php echo getCategoriesUrl(); ?>">分类</a> | 
                <a href="<?php echo getSearchUrl(); ?>">搜索</a>
            </td>
            <td width="30%" align="right" nowrap>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php 
                    // 加载社交功能函数
                    require_once __DIR__ . '/../includes/social_functions.php';
                    $unread_count = getUnreadMessageCount($_SESSION['user_id']);
                    $pending_requests = getPendingFriendRequestCount($_SESSION['user_id']);
                    ?>
                    <a href="<?php echo getUserProfileUrl($_SESSION['user_id']); ?>"><?php echo htmlspecialchars($_SESSION['username'] ?? '用户'); ?></a> | 
                    <a href="messages.php">消息<?php if ($unread_count > 0): ?> <span style="color: red; font-weight: bold;">(<?php echo $unread_count; ?>)</span><?php endif; ?></a> | 
                    <a href="friends.php">好友<?php if ($pending_requests > 0): ?> <span style="color: red; font-weight: bold;">(<?php echo $pending_requests; ?>)</span><?php endif; ?></a> | 
                    <a href="image_library.php">图片库</a><?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?> | 
                        <a href="<?php echo getAdminUrl(); ?>">管理后台</a><?php endif; ?> | 
                    <a href="<?php echo getLogoutUrl(); ?>">退出登录</a>
                <?php else: ?>
                    <a href="<?php echo getLoginUrl(); ?>">登录</a> | 
                    <a href="<?php echo getRegisterUrl(); ?>">注册</a>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    
    <div class="container">
        <!-- 页面内容将在这里 -->
