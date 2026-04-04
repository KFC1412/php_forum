<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>论坛首页 - 论坛系统</title>
    <link rel="stylesheet" href="/assets/css/forum.css">
</head>
<body>
    <div class="container">
        <h1>论坛首页</h1>
        
        <div class="create-topic">
            <a href="/topic/create" class="btn">发布主题</a>
        </div>
        
        <div class="topic-list">
            <?php foreach ($topics as $topic): ?>
                <div class="topic-item">
                    <h2><a href="/topic/<?php echo $topic['id']; ?>"><?php echo $topic['title']; ?></a></h2>
                    <div class="topic-meta">
                        <span>作者: <?php echo $topic['user_id']; ?></span>
                        <span>发布时间: <?php echo $topic['created_at']; ?></span>
                        <span>浏览: <?php echo $topic['view_count'] ?? 0; ?></span>
                        <span>回复: <?php echo $topic['replies'] ?? 0; ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo !empty($filters['category_id']) ? '&category_id=' . $filters['category_id'] : ''; ?><?php echo !empty($filters['search']) ? '&search=' . urlencode($filters['search']) : ''; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
        
        <div class="hot-topics">
            <h3>热门主题</h3>
            <ul>
                <?php foreach ($hotTopics as $hotTopic): ?>
                    <li><a href="/topic/<?php echo $hotTopic['id']; ?>"><?php echo $hotTopic['title']; ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</body>
</html>