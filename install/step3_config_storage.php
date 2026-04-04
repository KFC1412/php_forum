<?php
$storage_type = $_SESSION['storage_type'] ?? 'mysql';

// 检查是否已有数据
$has_existing_data = false;
if ($storage_type === 'mysql') {
    $db_host = $_SESSION['db_host'] ?? '';
    $db_name = $_SESSION['db_name'] ?? '';
    $db_user = $_SESSION['db_user'] ?? '';
    $db_pass = $_SESSION['db_pass'] ?? '';
    $db_prefix = $_SESSION['db_prefix'] ?? 'forum_';
    
    if (!empty($db_host) && !empty($db_name)) {
        try {
            $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("USE `$db_name`");
            $stmt = $pdo->query("SHOW TABLES LIKE '{$db_prefix}settings'");
            if ($stmt->fetch()) {
                $count = $pdo->query("SELECT COUNT(*) FROM `{$db_prefix}settings`")->fetchColumn();
                $has_existing_data = ($count > 0);
            }
        } catch (Exception $e) {
            // 忽略错误
        }
    }
} else {
    $storage_dir = $_SESSION['storage_dir'] ?? 'storage/json';
    $db_prefix = $_SESSION['db_prefix'] ?? 'forum_';
    $full_storage_dir = __DIR__ . '/../' . $storage_dir;
    $settings_file = $full_storage_dir . '/' . $db_prefix . 'settings.json';
    if (file_exists($settings_file)) {
        $settings_data = json_decode(file_get_contents($settings_file), true);
        $has_existing_data = !empty($settings_data['data']);
    }
}
?>

<?php if ($has_existing_data): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>检测到已有数据！</strong>
    <p class="mb-4">当前数据库/存储目录中已有EDUCN论坛的数据。</p>
    
    <form method="post" class="mb-4">
        <div class="mb-3">
            <div class="form-check mb-3">
                <input class="form-check-input" type="radio" name="data_action" id="keep_data" value="keep" checked>
                <label class="form-check-label" for="keep_data">
                    <strong>保留现有数据</strong>
                    <p class="text-muted small">保留现有数据，仅补全缺失的表结构和默认数据</p>
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="data_action" id="overwrite_data" value="overwrite">
                <label class="form-check-label" for="overwrite_data">
                    <strong>覆盖现有数据</strong>
                    <p class="text-muted small">清空所有现有数据，重新安装</p>
                </label>
            </div>
        </div>
        
        <?php if ($storage_type === 'mysql'): ?>
        <input type="hidden" name="db_host" value="<?php echo $_SESSION['db_host'] ?? 'localhost'; ?>">
        <input type="hidden" name="db_name" value="<?php echo $_SESSION['db_name'] ?? ''; ?>">
        <input type="hidden" name="db_user" value="<?php echo $_SESSION['db_user'] ?? ''; ?>">
        <input type="hidden" name="db_pass" value="<?php echo $_SESSION['db_pass'] ?? ''; ?>">
        <input type="hidden" name="db_prefix" value="<?php echo $_SESSION['db_prefix'] ?? 'forum_'; ?>">
        <?php else: ?>
        <input type="hidden" name="db_prefix" value="<?php echo $_SESSION['db_prefix'] ?? 'forum_'; ?>">
        <input type="hidden" name="storage_dir" value="<?php echo $_SESSION['storage_dir'] ?? 'storage/json'; ?>">
        <?php endif; ?>
        
        <div class="install-footer">
            <a href="index.php?step=2" class="btn btn-secondary">上一步</a>
            <button type="submit" class="btn btn-primary">继续</button>
        </div>
    </form>
</div>
<?php else: ?>
<?php if ($storage_type === 'mysql'): ?>
<h4><i class="bi bi-database me-2"></i>MySQL 数据库配置</h4>
<p class="text-muted">请填写您的MySQL数据库连接信息。如果数据库不存在，安装程序将自动创建。</p>

<form method="post">
    <div class="mb-3">
        <label for="db_host" class="form-label">数据库主机 <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="db_host" name="db_host" 
               value="<?php echo $_SESSION['db_host'] ?? 'localhost'; ?>" required>
        <div class="form-text">通常是 localhost 或 127.0.0.1</div>
    </div>
    
    <div class="mb-3">
        <label for="db_name" class="form-label">数据库名称 <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="db_name" name="db_name" 
               value="<?php echo $_SESSION['db_name'] ?? ''; ?>" required>
        <div class="form-text">数据库名称，如果不存在将自动创建</div>
    </div>
    
    <div class="mb-3">
        <label for="db_user" class="form-label">数据库用户名 <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="db_user" name="db_user" 
               value="<?php echo $_SESSION['db_user'] ?? ''; ?>" required>
    </div>
    
    <div class="mb-3">
        <label for="db_pass" class="form-label">数据库密码</label>
        <input type="password" class="form-control" id="db_pass" name="db_pass" 
               value="<?php echo $_SESSION['db_pass'] ?? ''; ?>">
    </div>
    
    <div class="mb-3">
        <label for="db_prefix" class="form-label">表前缀</label>
        <input type="text" class="form-control" id="db_prefix" name="db_prefix" 
               value="<?php echo $_SESSION['db_prefix'] ?? 'forum_'; ?>">
        <div class="form-text">表名前缀，用于区分同一数据库中的多个应用</div>
    </div>
    
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>注意：</strong>请确保数据库用户有创建数据库和表的权限。
    </div>
    
    <div class="install-footer">
        <a href="index.php?step=2" class="btn btn-secondary">上一步</a>
        <button type="submit" class="btn btn-primary">测试连接并继续</button>
    </div>
</form>

<?php else: ?>
<h4><i class="bi bi-file-earmark-text me-2"></i>JSON 文件存储配置</h4>
<p class="text-muted">JSON文件存储无需数据库，数据将保存在服务器的JSON文件中。请配置存储目录。</p>

<form method="post">
    <div class="mb-3">
        <label for="db_prefix" class="form-label">数据表前缀</label>
        <input type="text" class="form-control" id="db_prefix" name="db_prefix" 
               value="<?php echo $_SESSION['db_prefix'] ?? 'forum_'; ?>">
        <div class="form-text">用于区分不同的数据表，建议保持默认</div>
    </div>
    
    <div class="mb-3">
        <label for="storage_dir" class="form-label">存储目录</label>
        <input type="text" class="form-control" id="storage_dir" name="storage_dir" 
               value="<?php echo $_SESSION['storage_dir'] ?? 'storage/json'; ?>">
        <div class="form-text">JSON文件的存储目录（相对于网站根目录）</div>
    </div>
    
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        <strong>说明：</strong>
        <ul class="mb-0 mt-2">
            <li>JSON存储适合小型论坛，建议用户数不超过1000</li>
            <li>数据文件存储在指定目录中，请确保目录可写</li>
            <li>建议定期备份存储目录</li>
        </ul>
    </div>
    
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>安全提示：</strong>请确保存储目录不被公开访问，建议在目录中添加 .htaccess 文件限制访问。
    </div>
    
    <div class="install-footer">
        <a href="index.php?step=2" class="btn btn-secondary">上一步</a>
        <button type="submit" class="btn btn-primary">初始化存储并继续</button>
    </div>
</form>
<?php endif; ?>
<?php endif; ?>
