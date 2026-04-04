<?php
/**
 * 安装步骤1：环境检测 - Table化样式版
 */

// 检查PHP版本
$php_version = phpversion();
$php_version_ok = version_compare($php_version, '7.4.0', '>=');

// 检查必要的PHP扩展
$extensions = [
    'pdo' => extension_loaded('pdo'),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'mbstring' => extension_loaded('mbstring'),
    'json' => extension_loaded('json'),
    'gd' => extension_loaded('gd')
];

// 检查目录权限
$directories = [
    '../config' => is_dir(__DIR__ . '/../config') && is_writable(__DIR__ . '/../config'),
    '../upload' => is_dir(__DIR__ . '/../upload') && is_writable(__DIR__ . '/../upload')
];

// 检查是否所有条件都满足
$all_requirements_met = $php_version_ok && !in_array(false, $extensions) && !in_array(false, $directories);
?>

<h2 style="margin-bottom: 15px;">环境检测</h2>
<p style="color: #666; margin-bottom: 20px;">安装程序将检查您的服务器环境是否满足运行EDUCN论坛的要求。</p>

<!-- PHP环境 -->
<table border="1" cellspacing="0" cellpadding="0" width="100%" style="margin-bottom: 20px;">
    <tr>
        <td colspan="3" style="background-color: #f0f0f0; padding: 10px; font-weight: bold;">PHP环境</td>
    </tr>
    <tr>
        <td width="30%" style="padding: 10px;">PHP版本</td>
        <td width="40%" style="padding: 10px;"><?php echo $php_version; ?></td>
        <td width="30%" style="padding: 10px;">
            <?php if ($php_version_ok): ?>
                <span class="badge bg-success">通过</span>
            <?php else: ?>
                <span class="badge bg-danger">不满足</span>
                <small style="color: #dc3545;">需要PHP 7.4.0或更高版本</small>
            <?php endif; ?>
        </td>
    </tr>
</table>

<!-- PHP扩展 -->
<table border="1" cellspacing="0" cellpadding="0" width="100%" style="margin-bottom: 20px;">
    <tr>
        <td colspan="2" style="background-color: #f0f0f0; padding: 10px; font-weight: bold;">PHP扩展</td>
    </tr>
    <?php foreach ($extensions as $extension => $loaded): ?>
    <tr>
        <td width="50%" style="padding: 10px;"><?php echo $extension; ?></td>
        <td width="50%" style="padding: 10px;">
            <?php if ($loaded): ?>
                <span class="badge bg-success">已安装</span>
            <?php else: ?>
                <span class="badge bg-danger">未安装</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<!-- 目录权限 -->
<table border="1" cellspacing="0" cellpadding="0" width="100%" style="margin-bottom: 20px;">
    <tr>
        <td colspan="2" style="background-color: #f0f0f0; padding: 10px; font-weight: bold;">目录权限</td>
    </tr>
    <?php foreach ($directories as $directory => $writable): ?>
    <tr>
        <td width="50%" style="padding: 10px;"><?php echo $directory; ?></td>
        <td width="50%" style="padding: 10px;">
            <?php if ($writable): ?>
                <span class="badge bg-success">可写</span>
            <?php else: ?>
                <span class="badge bg-danger">不可写</span>
                <small style="color: #dc3545;">请确保目录存在并且可写</small>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<!-- 底部按钮 -->
<table border="1" cellspacing="0" cellpadding="0" width="100%">
    <tr>
        <td style="padding: 15px; background-color: #f0f0f0; text-align: right;">
            <?php if ($all_requirements_met): ?>
                <form method="post" action="index.php?step=1" style="display: inline;">
                    <button type="submit" class="btn">下一步</button>
                </form>
            <?php else: ?>
                <div class="alert alert-warning" style="text-align: left; margin-bottom: 10px;">
                    请解决上述问题后再继续安装。
                </div>
                <button type="button" class="btn btn-secondary" onclick="location.reload()">重新检测</button>
            <?php endif; ?>
        </td>
    </tr>
</table>
