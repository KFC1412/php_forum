<?php
$selected_storage = $_SESSION['storage_type'] ?? 'mysql';
?>

<h4><i class="bi bi-hdd-stack me-2"></i>选择存储方式</h4>
<p class="text-muted">请选择适合您论坛的数据存储方式。不同的存储方式有不同的特点和适用场景。</p>

<form method="post" id="storageForm">
    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="card storage-card h-100 <?php echo $selected_storage === 'mysql' ? 'selected' : ''; ?>" data-storage="mysql">
                <div class="card-body text-center">
                    <div class="storage-icon text-primary">
                        <i class="bi bi-database"></i>
                    </div>
                    <h5 class="card-title">MySQL / MariaDB</h5>
                    <p class="card-text text-muted">传统关系型数据库</p>
                    <hr>
                    <ul class="requirement-list text-start">
                        <li><i class="bi bi-check-circle text-success"></i>适合大型论坛</li>
                        <li><i class="bi bi-check-circle text-success"></i>高性能查询</li>
                        <li><i class="bi bi-check-circle text-success"></i>支持复杂事务</li>
                        <li><i class="bi bi-check-circle text-success"></i>数据完整性强</li>
                    </ul>
                    <div class="mt-3">
                        <small class="text-muted">需要: PDO MySQL扩展, MySQL 5.7+</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-3">
            <div class="card storage-card h-100 <?php echo $selected_storage === 'json' ? 'selected' : ''; ?>" data-storage="json">
                <div class="card-body text-center">
                    <div class="storage-icon text-info">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <h5 class="card-title">JSON 文件存储</h5>
                    <p class="card-text text-muted">轻量级文件存储</p>
                    <hr>
                    <ul class="requirement-list text-start">
                        <li><i class="bi bi-check-circle text-success"></i>无需数据库</li>
                        <li><i class="bi bi-check-circle text-success"></i>安装简单快速</li>
                        <li><i class="bi bi-check-circle text-success"></i>易于备份迁移</li>
                        <li><i class="bi bi-check-circle text-success"></i>适合小型论坛</li>
                    </ul>
                    <div class="mt-3">
                        <small class="text-muted">需要: PHP 7.0+, 可写目录权限</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <input type="hidden" name="storage_type" id="storage_type" value="<?php echo $selected_storage; ?>">
    
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        <strong>提示：</strong>
        <?php if ($selected_storage === 'mysql'): ?>
            MySQL适合用户量大、访问频繁的论坛，需要数据库服务器支持。
        <?php else: ?>
            JSON存储适合小型论坛或测试环境，无需配置数据库，开箱即用。
        <?php endif; ?>
    </div>
    
    <div class="install-footer">
        <a href="index.php?step=1" class="btn btn-secondary">上一步</a>
        <button type="submit" class="btn btn-primary">下一步</button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.storage-card');
    const storageInput = document.getElementById('storage_type');
    
    cards.forEach(card => {
        card.addEventListener('click', function() {
            cards.forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            storageInput.value = this.dataset.storage;
        });
    });
});
</script>
