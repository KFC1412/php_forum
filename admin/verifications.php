<?php
/**
 * 认证管理页面
 */

// 启动会话
session_start();

// 检查是否已安装
if (!file_exists(__DIR__ . '/../config/config.php')) {
    header('Location: ../install/index.php');
    exit;
}

// 加载配置和函数
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/user_verification.php';

// 检查安装状态和闭站模式
checkInstall();

// 检查是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// 检查是否为管理员
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// 处理审核操作
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'review') {
    $verification_id = $_POST['verification_id'] ?? 0;
    $status = $_POST['status'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if ($verification_id > 0 && ($status === 'approved' || $status === 'rejected')) {
        $result = reviewVerification($verification_id, $status, $reason, $_SESSION['user_id']);
        
        if ($result) {
            // 记录操作日志
            logAction('管理员审核认证申请', 'admin', $_SESSION['user_id'], [
                'verification_id' => $verification_id,
                'status' => $status,
                'reason' => $reason,
                'review_time' => date('Y-m-d H:i:s')
            ]);
            
            $success = '审核操作成功';
        } else {
            $error = '审核操作失败';
        }
    } else {
        $error = '无效的参数';
    }
}

// 获取认证申请列表
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';
$verifications = getVerificationRequests($status, $type);

// 设置页面标题
$page_title = '认证管理';

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
                        <h2>认证管理</h2>
                    </td>
                </tr>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="mb-3">
    <a href="verifications.php" class="btn btn-primary">全部</a>
    <a href="verifications.php?status=pending" class="btn btn-secondary">待审核</a>
    <a href="verifications.php?status=approved" class="btn btn-success">已通过</a>
    <a href="verifications.php?status=rejected" class="btn btn-danger">已拒绝</a>
    <a href="verifications.php?type=real_name" class="btn btn-info">实名认证</a>
    <a href="verifications.php?type=professional" class="btn btn-warning">专业认证</a>
</div>

<table class="table table-bordered">
    <thead>
        <tr>
            <th>ID</th>
            <th>用户</th>
            <th>认证类型</th>
            <th>认证信息</th>
            <th>状态</th>
            <th>提交时间</th>
            <th>操作</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($verifications) > 0): ?>
            <?php foreach ($verifications as $verification): ?>
                <tr>
                    <td><?php echo $verification['id']; ?></td>
                    <td>
                        <a href="../profile.php?id=<?php echo $verification['user_id']; ?>">
                            <?php echo htmlspecialchars($verification['username'] ?? '未知用户'); ?> (ID: <?php echo $verification['user_id']; ?>)
                        </a>
                    </td>
                    <td>
                        <?php echo $verification['type'] === 'real_name' ? '实名认证' : '专业认证'; ?>
                    </td>
                    <td>
                        <?php if ($verification['type'] === 'real_name'): ?>
                            <div>真实姓名：<?php echo htmlspecialchars($verification['real_name']); ?></div>
                            <div>身份证号：<?php echo htmlspecialchars($verification['id_card']); ?></div>
                            <div>身份证正面：<a href="javascript:void(0);" onclick="showImageModal('<?php echo htmlspecialchars($verification['id_card_front']); ?>', '身份证正面')">查看</a></div>
                            <div>身份证反面：<a href="javascript:void(0);" onclick="showImageModal('<?php echo htmlspecialchars($verification['id_card_back']); ?>', '身份证反面')">查看</a></div>
                        <?php else: ?>
                            <div>专业领域：<?php echo htmlspecialchars($verification['profession']); ?></div>
                            <div>资质证明：<?php echo htmlspecialchars($verification['qualification']); ?></div>
                            <div>证书照片：<a href="javascript:void(0);" onclick="showImageModal('<?php echo htmlspecialchars($verification['certificate']); ?>', '证书照片')">查看</a></div>
                            <div>个人简介：<?php echo htmlspecialchars($verification['description']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($verification['status'] === 'pending'): ?>
                            <span class="badge bg-warning">待审核</span>
                        <?php elseif ($verification['status'] === 'approved'): ?>
                            <span class="badge bg-success">已通过</span>
                        <?php else: ?>
                            <span class="badge bg-danger">已拒绝</span>
                            <?php if (!empty($verification['reason'])): ?>
                                <div class="text-muted small">原因：<?php echo htmlspecialchars($verification['reason']); ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $verification['created_at']; ?></td>
                    <td>
                        <?php if ($verification['status'] === 'pending'): ?>
                            <button type="button" class="btn btn-sm btn-success" onclick="openReviewModal(<?php echo $verification['id']; ?>, 'approved')">通过</button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="openReviewModal(<?php echo $verification['id']; ?>, 'rejected')">拒绝</button>
                        <?php else: ?>
                            <span class="text-muted">已处理</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="7" class="text-center">暂无认证申请</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- 审核弹窗 -->
<div id="reviewModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: white; padding: 20px; border-radius: 5px; width: 400px; max-width: 90%;">
        <h3 style="margin-top: 0; margin-bottom: 20px;">审核认证申请</h3>
        <form id="reviewForm" method="post" action="verifications.php">
            <input type="hidden" name="action" value="review">
            <input type="hidden" name="verification_id" id="reviewVerificationId">
            <input type="hidden" name="status" id="reviewStatus">
            <div style="margin-bottom: 15px;">
                <label for="reviewReason" style="display: block; margin-bottom: 5px;">审核原因：</label>
                <textarea id="reviewReason" name="reason" rows="4" style="width: 100%; padding: 5px; resize: vertical;"></textarea>
            </div>
            <div style="text-align: right;">
                <button type="button" onclick="closeReviewModal()" style="padding: 5px 15px; margin-right: 10px; background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 3px; cursor: pointer;">取消</button>
                <button type="submit" style="padding: 5px 15px; background-color: #4A90E2; color: white; border: none; border-radius: 3px; cursor: pointer;">确定</button>
            </div>
        </form>
    </div>
</div>

<!-- 图片查看弹窗 -->
<div id="imageModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.8); z-index: 2000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: white; padding: 20px; border-radius: 5px; max-width: 90%; max-height: 90%;">
        <div style="text-align: center; margin-bottom: 15px;">
            <h3 id="imageModalTitle" style="margin: 0; display: inline-block;"></h3>
            <button onclick="closeImageModal()" style="float: right; background: none; border: none; font-size: 24px; cursor: pointer; line-height: 1;">&times;</button>
        </div>
        <div style="text-align: center;">
            <img id="imageModalImg" src="" alt="" style="max-width: 100%; max-height: 70vh; border: 1px solid #ddd;">
        </div>
        <div style="text-align: center; margin-top: 15px;">
            <a id="imageModalLink" href="" target="_blank" style="padding: 5px 15px; background-color: #4A90E2; color: white; text-decoration: none; border-radius: 3px;">在新窗口打开</a>
            <button onclick="closeImageModal()" style="padding: 5px 15px; margin-left: 10px; background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 3px; cursor: pointer;">关闭</button>
        </div>
    </div>
</div>

<script>
// 打开审核弹窗
function openReviewModal(verificationId, status) {
    document.getElementById('reviewVerificationId').value = verificationId;
    document.getElementById('reviewStatus').value = status;
    document.getElementById('reviewModal').style.display = 'block';
}

// 关闭审核弹窗
function closeReviewModal() {
    document.getElementById('reviewModal').style.display = 'none';
    document.getElementById('reviewForm').reset();
}

// 打开图片弹窗
function showImageModal(imageUrl, title) {
    document.getElementById('imageModalImg').src = imageUrl;
    document.getElementById('imageModalLink').href = imageUrl;
    document.getElementById('imageModalTitle').textContent = title || '图片查看';
    document.getElementById('imageModal').style.display = 'block';
}

// 关闭图片弹窗
function closeImageModal() {
    document.getElementById('imageModal').style.display = 'none';
    document.getElementById('imageModalImg').src = '';
}

// 点击图片弹窗背景关闭
window.onclick = function(event) {
    var reviewModal = document.getElementById('reviewModal');
    var imageModal = document.getElementById('imageModal');
    if (event.target == reviewModal) {
        closeReviewModal();
    }
    if (event.target == imageModal) {
        closeImageModal();
    }
}
</script>

            </table>
        </td>
    </tr>
</table>

<?php
// 加载页面底部
include __DIR__ . '/templates/admin_footer.php';
?>