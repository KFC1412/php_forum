<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>每日60秒热点资讯 - 论坛系统</title>
    <link rel="stylesheet" href="/assets/css/forum.css">
</head>
<body>
    <div class="container">
        <h1>每日60秒热点资讯</h1>
        
        <div class="daily-news">
            <?php echo $news['content']; ?>
        </div>
        
        <div class="news-meta">
            <p>发布时间: <?php echo $news['created_at']; ?></p>
            <p>浏览量: <?php echo $news['view_count']; ?></p>
        </div>
    </div>
</body>
</html>