<?php
/**
 * 后台管理侧边栏模板 - 支持伪静态URL
 */
?>
<table border="1" width="100%" cellspacing="0" cellpadding="5">
    <tr>
        <td colspan="2"><strong>管理菜单</strong></td>
    </tr>
    <tr>
        <td colspan="2">
            <a href="index.php" <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'style="font-weight: bold;"' : ''; ?>>控制面板</a>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <a href="users.php" <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'style="font-weight: bold;"' : ''; ?>>用户管理</a>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <a href="categories.php" <?php echo basename($_SERVER['PHP_SELF']) === 'categories.php' ? 'style="font-weight: bold;"' : ''; ?>>分类管理</a>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <a href="topics.php" <?php echo basename($_SERVER['PHP_SELF']) === 'topics.php' ? 'style="font-weight: bold;"' : ''; ?>>主题管理</a>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <a href="posts.php" <?php echo basename($_SERVER['PHP_SELF']) === 'posts.php' ? 'style="font-weight: bold;"' : ''; ?>>回复管理</a>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <a href="reports.php" <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'style="font-weight: bold;"' : ''; ?>>举报申诉</a>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <a href="verifications.php" <?php echo basename($_SERVER['PHP_SELF']) === 'verifications.php' ? 'style="font-weight: bold;"' : ''; ?>>认证管理</a>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <a href="links.php" <?php echo basename($_SERVER['PHP_SELF']) === 'links.php' ? 'style="font-weight: bold;"' : ''; ?>>友链管理</a>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <a href="edit_things.php" <?php echo basename($_SERVER['PHP_SELF']) === 'edit_things.php' ? 'style="font-weight: bold;"' : ''; ?>>公告管理</a>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <a href="system_messages.php" <?php echo basename($_SERVER['PHP_SELF']) === 'system_messages.php' || basename($_SERVER['PHP_SELF']) === 'system_messages_view.php' ? 'style="font-weight: bold;"' : ''; ?>>系统消息</a>
        </td>
    </tr>
    <tr>
        <td colspan="2" style="margin-top: 10px;"><strong>系统设置</strong></td>
    </tr>
    <tr>
        <td colspan="2">
            <a href="settings.php" <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'style="font-weight: bold;"' : ''; ?>>系统设置</a>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <a href="smtp_settings.php" <?php echo basename($_SERVER['PHP_SELF']) === 'smtp_settings.php' ? 'style="font-weight: bold;"' : ''; ?>>邮箱配置</a>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <a href="logs.php" <?php echo basename($_SERVER['PHP_SELF']) === 'logs.php' ? 'style="font-weight: bold;"' : ''; ?>>系统日志</a>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <a href="backup.php" <?php echo basename($_SERVER['PHP_SELF']) === 'backup.php' ? 'style="font-weight: bold;"' : ''; ?>>备份恢复</a>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <a href="file_manager.php" <?php echo basename($_SERVER['PHP_SELF']) === 'file_manager.php' ? 'style="font-weight: bold;"' : ''; ?>>文件管理</a>
        </td>
    </tr>
</table>