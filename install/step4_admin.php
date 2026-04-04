<?php
$storage_type = $_SESSION['storage_type'] ?? 'mysql';
$storage_name = $storage_type === 'mysql' ? 'MySQL数据库' : 'JSON文件存储';
?>

<h4><i class="bi bi-gear me-2"></i>系统设置</h4>
<p class="text-muted">请配置论坛的基本信息和管理员账户。</p>

<form method="post">
    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-building me-2"></i>网站信息
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label for="site_name" class="form-label">网站名称</label>
                <input type="text" class="form-control" id="site_name" name="site_name" 
                       value="<?php echo $_POST['site_name'] ?? 'EDUCN论坛'; ?>">
            </div>
            
            <div class="mb-3">
                <label for="site_description" class="form-label">网站描述</label>
                <textarea class="form-control" id="site_description" name="site_description" rows="2"><?php echo $_POST['site_description'] ?? '一个非常牛逼的PHP论坛程序'; ?></textarea>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-person-badge me-2"></i>管理员账户
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label for="admin_username" class="form-label">管理员用户名 <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="admin_username" name="admin_username" 
                       value="<?php echo $_POST['admin_username'] ?? ''; ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="admin_password" class="form-label">管理员密码 <span class="text-danger">*</span></label>
                <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                <div class="form-text">密码至少需要6个字符</div>
            </div>
            
            <div class="mb-3">
                <label for="admin_email" class="form-label">管理员邮箱 <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="admin_email" name="admin_email" 
                       value="<?php echo $_POST['admin_email'] ?? ''; ?>" required>
            </div>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-hdd me-2"></i>存储配置确认
        </div>
        <div class="card-body">
            <p><strong>存储类型：</strong><?php echo $storage_name; ?></p>
            <?php if ($storage_type === 'mysql'): ?>
                <p><strong>数据库主机：</strong><?php echo $_SESSION['db_host'] ?? ''; ?></p>
                <p><strong>数据库名称：</strong><?php echo $_SESSION['db_name'] ?? ''; ?></p>
            <?php else: ?>
                <p><strong>存储目录：</strong><?php echo $_SESSION['storage_dir'] ?? 'storage/json'; ?></p>
            <?php endif; ?>
            <p><strong>表前缀：</strong><?php echo $_SESSION['db_prefix'] ?? 'forum_'; ?></p>
        </div>
    </div>
    
    <div class="install-footer">
        <a href="index.php?step=3" class="btn btn-secondary">上一步</a>
        <button type="submit" class="btn btn-primary">完成安装</button>
    </div>
</form>
