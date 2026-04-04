<?php
$storage_type = $_SESSION['storage_type'] ?? 'mysql';
$storage_name = $storage_type === 'mysql' ? 'MySQL数据库' : 'JSON文件存储';

// 创建安装锁定文件
$lock_file = __DIR__ . '/install.lock';
if (!file_exists($lock_file)) {
    file_put_contents($lock_file, date('Y-m-d H:i:s'));
}
?>

<div class="text-center">
    <div class="mb-4">
        <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
    </div>
    
    <h2 class="text-success mb-3">安装完成！</h2>
    <p class="text-muted mb-4">恭喜您，EDUCN论坛已成功安装！</p>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-info-circle me-2"></i>安装信息
        </div>
        <div class="card-body text-start">
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
    
    <div class="alert alert-warning text-start">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>安全建议：</strong>
        <ul class="mb-0 mt-2">
            <li>删除或重命名 install 目录，防止被他人访问</li>
            <li>设置 config 目录权限为只读（644）</li>
            <?php if ($storage_type === 'json'): ?>
                <li>确保 storage 目录不可通过Web访问</li>
                <li>定期备份 storage 目录中的JSON文件</li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-6 mb-3">
            <a href="../index.php" class="btn btn-primary btn-lg w-100">
                <i class="bi bi-house me-2"></i>访问首页
            </a>
        </div>
        <div class="col-md-6 mb-3">
            <a href="../admin/index.php" class="btn btn-outline-primary btn-lg w-100">
                <i class="bi bi-gear me-2"></i>进入后台
            </a>
        </div>
    </div>
    
    <div class="mt-4">
        <p class="text-muted small">
            感谢您选择 EDUCN论坛！如有问题，请发送邮件至 by2312@qq.com。
        </p>
    </div>
</div>
