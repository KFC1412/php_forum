<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - 管理后台' : '管理后台'; ?> - 论坛</title>
    <link rel="stylesheet" href="../assets/css/forum.css">
</head>
<body>
    <table border="1" width="100%" cellspacing="0" cellpadding="5">
        <tr>
            <td width="80%">
                <a href="index.php" style="font-weight: bold;"><?php echo getSetting('site_name', 'PHP轻论坛'); ?> 管理后台</a>
            </td>
            <td width="20%" align="right">
                <a href="../logout.php">退出登录</a>
            </td>
        </tr>
    </table>

