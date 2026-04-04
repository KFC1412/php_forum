<?php

session_start();

// 检查install.lock文件是否存在，如果存在则阻止访问安装程序
if (file_exists(__DIR__ . '/install.lock')) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>论坛已安装 - EDUCN论坛</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            background-color: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔒 论坛已安装</h1>
        <div class="warning">
            检测到论坛已经安装完成（install.lock文件存在）。<br>
            如需重新安装，请先删除 <strong>install/install.lock</strong> 文件。
        </div>
        <p>如果您需要访问论坛，请点击下方按钮：</p>
        <a href="../index.php" class="btn">访问论坛首页</a>
    </div>
</body>
</html>';
    exit;
}

$steps = [
    1 => '环境检测',
    2 => '存储选择',
    3 => '存储配置',
    4 => '系统设置',
    5 => '安装完成'
];

$current_step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($current_step < 1 || $current_step > count($steps)) {
    $current_step = 1;
}

// 检查是否已安装（使用更严格的检测）
require_once __DIR__ . '/../includes/functions.php';
$installed = isInstalled();

if ($installed && $current_step != 5) {
    $warning = '警告：检测到论坛已经安装。继续安装将覆盖现有数据！';
} else {
    $warning = '';
}

// 处理清除数据请求
if (isset($_GET['action']) && $_GET['action'] === 'clear_data' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $clear_type = $_POST['clear_type'] ?? '';
    $storage_type = $_SESSION['storage_type'] ?? 'mysql';
    
    try {
        if ($storage_type === 'mysql') {
            $db_host = $_SESSION['db_host'] ?? '';
            $db_name = $_SESSION['db_name'] ?? '';
            $db_user = $_SESSION['db_user'] ?? '';
            $db_pass = $_SESSION['db_pass'] ?? '';
            $db_prefix = $_SESSION['db_prefix'] ?? 'forum_';
            
            if (empty($db_host) || empty($db_name)) {
                $error = '请先配置数据库信息';
            } else {
                $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->exec("USE `$db_name`");
                
                if ($clear_type === 'drop_tables') {
                    // 删除所有表
                    $tables = $pdo->query("SHOW TABLES LIKE '{$db_prefix}%'");
                    while ($table = $tables->fetchColumn()) {
                        $pdo->exec("DROP TABLE IF EXISTS `$table`");
                    }
                    $success = '已成功清空数据库中的所有表';
                } elseif ($clear_type === 'clear_config') {
                    // 删除配置文件
                    if (file_exists(__DIR__ . '/../config/config.php')) {
                        unlink(__DIR__ . '/../config/config.php');
                    }
                    // 删除锁定文件
                    if (file_exists(__DIR__ . '/install.lock')) {
                        unlink(__DIR__ . '/install.lock');
                    }
                    $success = '已成功清除配置文件，请重新安装';
                    $installed = false;
                }
            }
        } else {
            // JSON 存储
            $storage_dir = $_SESSION['storage_dir'] ?? 'storage/json';
            $db_prefix = $_SESSION['db_prefix'] ?? 'forum_';
            $full_storage_dir = __DIR__ . '/../' . $storage_dir;
            
            if ($clear_type === 'drop_tables') {
                // 删除所有JSON文件
                if (is_dir($full_storage_dir)) {
                    $files = glob($full_storage_dir . '/*.json');
                    foreach ($files as $file) {
                        if (basename($file) === 'index.html') continue;
                        unlink($file);
                    }
                }
                $success = '已成功清空存储目录中的数据';
            } elseif ($clear_type === 'clear_config') {
                // 删除配置文件
                if (file_exists(__DIR__ . '/../config/config.php')) {
                    unlink(__DIR__ . '/../config/config.php');
                }
                // 删除锁定文件
                if (file_exists(__DIR__ . '/install.lock')) {
                    unlink(__DIR__ . '/install.lock');
                }
                $success = '已成功清除配置文件，请重新安装';
                $installed = false;
            }
        }
    } catch (Exception $e) {
        $error = '清除数据失败: ' . $e->getMessage();
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($current_step) {
        case 1:
            header('Location: index.php?step=2');
            exit;
            
        case 2:
            $storage_type = $_POST['storage_type'] ?? 'mysql';
            
            if (!in_array($storage_type, ['mysql', 'json'])) {
                $error = '请选择有效的存储类型';
            } else {
                $_SESSION['storage_type'] = $storage_type;
                header('Location: index.php?step=3');
                exit;
            }
            break;
            
        case 3:
            $storage_type = $_SESSION['storage_type'] ?? 'mysql';
            
            if ($storage_type === 'mysql') {
                $db_host = $_POST['db_host'] ?? 'localhost';
                $db_name = $_POST['db_name'] ?? '';
                $db_user = $_POST['db_user'] ?? '';
                $db_pass = $_POST['db_pass'] ?? '';
                $db_prefix = $_POST['db_prefix'] ?? 'forum_';
                $data_action = $_POST['data_action'] ?? 'keep';
                
                if (empty($db_host) || empty($db_name) || empty($db_user)) {
                    $error = '请填写所有必填字段';
                } else {
                    try {
                        $_SESSION['db_host'] = $db_host;
                        $_SESSION['db_name'] = $db_name;
                        $_SESSION['db_user'] = $db_user;
                        $_SESSION['db_pass'] = $db_pass;
                        $_SESSION['db_prefix'] = $db_prefix;
                        
                        $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$db_name'");
                        if (!$stmt->fetch()) {
                            $pdo->exec("CREATE DATABASE `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        }
                        
                        $pdo->exec("USE `$db_name`");
                        
                        // 检查是否已有数据
                        $stmt = $pdo->query("SHOW TABLES LIKE '{$db_prefix}settings'");
                        $has_existing_data = $stmt->fetch();
                        
                        if ($has_existing_data) {
                            // 表已存在，检查是否有数据
                            $count = $pdo->query("SELECT COUNT(*) FROM `{$db_prefix}settings`")->fetchColumn();
                            $has_existing_data = ($count > 0);
                        }
                        
                        if ($has_existing_data && $data_action === 'overwrite') {
                            // 覆盖数据，删除所有表（但保留系统账户和每日热点资讯数据）
                            include_once 'database_schema.php';
                            $tables = getDatabaseSchema($db_prefix);
                            
                            // 备份系统账户数据
                            $system_accounts_backup = [];
                            try {
                                $stmt = $pdo->query("SELECT * FROM `{$db_prefix}system_accounts`");
                                $system_accounts_backup = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (PDOException $e) {
                                // 表可能不存在，忽略错误
                            }
                            
                            // 备份每日热点资讯数据
                            $daily_news_backup = [];
                            try {
                                $stmt = $pdo->query("SELECT * FROM `{$db_prefix}daily_news`");
                                $daily_news_backup = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (PDOException $e) {
                                // 表可能不存在，忽略错误
                            }
                            
                            foreach (array_reverse(array_keys($tables)) as $table_name) {
                                $pdo->exec("DROP TABLE IF EXISTS `$table_name`");
                            }
                            
                            // 恢复标记：需要恢复系统数据
                            $_SESSION['restore_system_data'] = true;
                            $_SESSION['system_accounts_backup'] = $system_accounts_backup;
                            $_SESSION['daily_news_backup'] = $daily_news_backup;
                        }
                        
                        include_once 'database_schema.php';
                        $tables = getDatabaseSchema($db_prefix);
                        
                        foreach ($tables as $table_name => $sql) {
                            // 检查表是否存在
                            $stmt = $pdo->query("SHOW TABLES LIKE '$table_name'");
                            if (!$stmt->fetch()) {
                                $pdo->exec($sql);
                            }
                        }
                        
                        include_once 'default_data.php';
                        $default_data = getDefaultData($db_prefix);
                        
                        foreach ($default_data as $table => $rows) {
                            // 跳过系统账户表和每日热点资讯表，稍后单独处理
                            if ($table === "{$db_prefix}system_accounts" || $table === "{$db_prefix}daily_news") {
                                continue;
                            }
                            
                            foreach ($rows as $row) {
                                // 检查是否已存在相同数据
                                $where = [];
                                $values = [];
                                foreach ($row as $key => $value) {
                                    if ($key !== 'created_at' && $key !== 'updated_at') {
                                        $where[] = "`$key` = ?";
                                        $values[] = $value;
                                    }
                                }
                                
                                if (!empty($where)) {
                                    $sql = "SELECT COUNT(*) FROM `$table` WHERE " . implode(' AND ', $where);
                                    $stmt = $pdo->prepare($sql);
                                    $stmt->execute($values);
                                    $count = $stmt->fetchColumn();
                                    
                                    if ($count === 0) {
                                        // 数据不存在，插入
                                        $fields = array_keys($row);
                                        $placeholders = array_fill(0, count($fields), '?');
                                        
                                        $sql = "INSERT INTO `$table` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
                                        $stmt = $pdo->prepare($sql);
                                        $stmt->execute(array_values($row));
                                    }
                                }
                            }
                        }
                        
                        // 处理系统账户数据 - 合并默认数据和备份数据
                        $system_accounts_table = "{$db_prefix}system_accounts";
                        if (isset($default_data[$system_accounts_table])) {
                            // 首先插入默认的系统账户
                            foreach ($default_data[$system_accounts_table] as $row) {
                                $sql = "SELECT COUNT(*) FROM `{$system_accounts_table}` WHERE `id` = ?";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute([$row['id']]);
                                $count = $stmt->fetchColumn();
                                
                                if ($count === 0) {
                                    $fields = array_keys($row);
                                    $placeholders = array_fill(0, count($fields), '?');
                                    $sql = "INSERT INTO `{$system_accounts_table}` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
                                    $stmt = $pdo->prepare($sql);
                                    $stmt->execute(array_values($row));
                                }
                            }
                        }
                        
                        // 恢复备份的系统账户数据（如果有）
                        if (!empty($_SESSION['system_accounts_backup'])) {
                            foreach ($_SESSION['system_accounts_backup'] as $backup_row) {
                                $sql = "SELECT COUNT(*) FROM `{$system_accounts_table}` WHERE `id` = ?";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute([$backup_row['id']]);
                                $count = $stmt->fetchColumn();
                                
                                if ($count === 0) {
                                    // 移除自动生成的字段
                                    unset($backup_row['created_at']);
                                    unset($backup_row['updated_at']);
                                    $fields = array_keys($backup_row);
                                    $placeholders = array_fill(0, count($fields), '?');
                                    $sql = "INSERT INTO `{$system_accounts_table}` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
                                    $stmt = $pdo->prepare($sql);
                                    $stmt->execute(array_values($backup_row));
                                }
                            }
                            // 清除备份数据
                            unset($_SESSION['system_accounts_backup']);
                        }
                        
                        // 处理每日热点资讯数据 - 合并默认数据和备份数据
                        $daily_news_table = "{$db_prefix}daily_news";
                        if (isset($default_data[$daily_news_table])) {
                            // 首先插入默认的每日热点
                            foreach ($default_data[$daily_news_table] as $row) {
                                $sql = "SELECT COUNT(*) FROM `{$daily_news_table}` WHERE `title` = ? AND `publish_date` = ?";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute([$row['title'], $row['publish_date']]);
                                $count = $stmt->fetchColumn();
                                
                                if ($count === 0) {
                                    $fields = array_keys($row);
                                    $placeholders = array_fill(0, count($fields), '?');
                                    $sql = "INSERT INTO `{$daily_news_table}` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
                                    $stmt = $pdo->prepare($sql);
                                    $stmt->execute(array_values($row));
                                }
                            }
                        }
                        
                        // 恢复备份的每日热点数据（如果有）
                        if (!empty($_SESSION['daily_news_backup'])) {
                            foreach ($_SESSION['daily_news_backup'] as $backup_row) {
                                $sql = "SELECT COUNT(*) FROM `{$daily_news_table}` WHERE `title` = ? AND `publish_date` = ?";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute([$backup_row['title'], $backup_row['publish_date']]);
                                $count = $stmt->fetchColumn();
                                
                                if ($count === 0) {
                                    // 移除自动生成的字段
                                    unset($backup_row['created_at']);
                                    unset($backup_row['updated_at']);
                                    $fields = array_keys($backup_row);
                                    $placeholders = array_fill(0, count($fields), '?');
                                    $sql = "INSERT INTO `{$daily_news_table}` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
                                    $stmt = $pdo->prepare($sql);
                                    $stmt->execute(array_values($backup_row));
                                }
                            }
                            // 清除备份数据
                            unset($_SESSION['daily_news_backup']);
                        }
                        
                        // 清除恢复标记
                        if (isset($_SESSION['restore_system_data'])) {
                            unset($_SESSION['restore_system_data']);
                        }
                        
                        $config_content = "<?php\n";
                        $config_content .= "date_default_timezone_set('Asia/Shanghai');\n";
                        $config_content .= "// 存储类型配置\n";
                        $config_content .= "define('STORAGE_TYPE', 'mysql');\n";
                        $config_content .= "// 数据库配置\n";
                        $config_content .= "define('DB_HOST', '$db_host');\n";
                        $config_content .= "define('DB_NAME', '$db_name');\n";
                        $config_content .= "define('DB_USER', '$db_user');\n";
                        $config_content .= "define('DB_PASS', '$db_pass');\n";
                        $config_content .= "define('DB_PREFIX', '$db_prefix');\n";
                        $config_content .= "?>";
                        
                        $_SESSION['config_content'] = $config_content;
                        
                        header('Location: index.php?step=4');
                        exit;
                        
                    } catch (PDOException $e) {
                        $error = '数据库连接失败: ' . $e->getMessage();
                    }
                }
            } else {
                $db_prefix = $_POST['db_prefix'] ?? 'forum_';
                $storage_dir = $_POST['storage_dir'] ?? 'storage/json';
                $data_action = $_POST['data_action'] ?? 'keep';
                
                $_SESSION['db_prefix'] = $db_prefix;
                $_SESSION['storage_dir'] = $storage_dir;
                
                $full_storage_dir = __DIR__ . '/../' . $storage_dir;
                if (!is_dir($full_storage_dir)) {
                    if (!mkdir($full_storage_dir, 0755, true)) {
                        $error = '无法创建存储目录: ' . $storage_dir;
                    }
                }
                
                if (empty($error)) {
                    if (!is_writable($full_storage_dir)) {
                        $error = '存储目录不可写: ' . $storage_dir;
                    }
                }
                
                if (empty($error)) {
                    require_once __DIR__ . '/../includes/json_storage.php';
                    $jsonStorage = JsonStorage::getInstance();
                    $jsonStorage->setPrefix($db_prefix);
                    $jsonStorage->setDataDir($full_storage_dir);
                    
                    // 检查是否已有数据
                    $settings_file = $full_storage_dir . '/' . $db_prefix . 'settings.json';
                    $has_existing_data = file_exists($settings_file);
                    
                    if ($has_existing_data) {
                        $settings_data = json_decode(file_get_contents($settings_file), true);
                        $has_existing_data = !empty($settings_data['data']);
                    }
                    
                    if ($has_existing_data && $data_action === 'overwrite') {
                        // 覆盖数据，删除所有JSON文件（但保留系统账户和每日热点资讯数据）
                        include_once 'database_schema.php';
                        $tables = getDatabaseSchema($db_prefix);
                        
                        // 备份系统账户数据
                        $system_accounts_backup = [];
                        $system_accounts_file = $full_storage_dir . '/' . $db_prefix . 'system_accounts.json';
                        if (file_exists($system_accounts_file)) {
                            $system_accounts_data = json_decode(file_get_contents($system_accounts_file), true);
                            if (!empty($system_accounts_data['data'])) {
                                $system_accounts_backup = $system_accounts_data['data'];
                            }
                        }
                        
                        // 备份每日热点资讯数据
                        $daily_news_backup = [];
                        $daily_news_file = $full_storage_dir . '/' . $db_prefix . 'daily_news.json';
                        if (file_exists($daily_news_file)) {
                            $daily_news_data = json_decode(file_get_contents($daily_news_file), true);
                            if (!empty($daily_news_data['data'])) {
                                $daily_news_backup = $daily_news_data['data'];
                            }
                        }
                        
                        foreach (array_keys($tables) as $table_name) {
                            $table_file = $full_storage_dir . '/' . $table_name . '.json';
                            if (file_exists($table_file)) {
                                unlink($table_file);
                            }
                        }
                        
                        // 恢复标记：需要恢复系统数据
                        $_SESSION['restore_system_data'] = true;
                        $_SESSION['system_accounts_backup'] = $system_accounts_backup;
                        $_SESSION['daily_news_backup'] = $daily_news_backup;
                    }
                    
                    include_once 'database_schema.php';
                    $tables = getDatabaseSchema($db_prefix);
                    
                    foreach ($tables as $table_name => $schema) {
                        // 检查表是否存在
                        $table_file = $full_storage_dir . '/' . $table_name . '.json';
                        if (!file_exists($table_file)) {
                            $jsonStorage->createTable($table_name, $schema);
                        }
                    }
                    
                    include_once 'default_data.php';
                    $default_data = getDefaultData($db_prefix);
                    
                    foreach ($default_data as $table => $rows) {
                        // 跳过系统账户表和每日热点资讯表，稍后单独处理
                        if ($table === "{$db_prefix}system_accounts" || $table === "{$db_prefix}daily_news") {
                            continue;
                        }
                        
                        foreach ($rows as $row) {
                            // 检查是否已存在相同数据
                            $existing_data = $jsonStorage->select($table, $row);
                            if (empty($existing_data)) {
                                // 数据不存在，插入
                                $jsonStorage->insert($table, $row);
                            }
                        }
                    }
                    
                    // 处理系统账户数据 - 合并默认数据和备份数据
                    $system_accounts_table = "{$db_prefix}system_accounts";
                    if (isset($default_data[$system_accounts_table])) {
                        // 首先插入默认的系统账户
                        foreach ($default_data[$system_accounts_table] as $row) {
                            $existing = $jsonStorage->select($system_accounts_table, ['id' => $row['id']]);
                            if (empty($existing)) {
                                $jsonStorage->insert($system_accounts_table, $row);
                            }
                        }
                    }
                    
                    // 恢复备份的系统账户数据（如果有）
                    if (!empty($_SESSION['system_accounts_backup'])) {
                        foreach ($_SESSION['system_accounts_backup'] as $backup_row) {
                            $existing = $jsonStorage->select($system_accounts_table, ['id' => $backup_row['id']]);
                            if (empty($existing)) {
                                $jsonStorage->insert($system_accounts_table, $backup_row);
                            }
                        }
                        // 清除备份数据
                        unset($_SESSION['system_accounts_backup']);
                    }
                    
                    // 处理每日热点资讯数据 - 合并默认数据和备份数据
                    $daily_news_table = "{$db_prefix}daily_news";
                    if (isset($default_data[$daily_news_table])) {
                        // 首先插入默认的每日热点
                        foreach ($default_data[$daily_news_table] as $row) {
                            $existing = $jsonStorage->select($daily_news_table, [
                                'title' => $row['title'],
                                'publish_date' => $row['publish_date']
                            ]);
                            if (empty($existing)) {
                                $jsonStorage->insert($daily_news_table, $row);
                            }
                        }
                    }
                    
                    // 恢复备份的每日热点数据（如果有）
                    if (!empty($_SESSION['daily_news_backup'])) {
                        foreach ($_SESSION['daily_news_backup'] as $backup_row) {
                            $existing = $jsonStorage->select($daily_news_table, [
                                'title' => $backup_row['title'],
                                'publish_date' => $backup_row['publish_date']
                            ]);
                            if (empty($existing)) {
                                $jsonStorage->insert($daily_news_table, $backup_row);
                            }
                        }
                        // 清除备份数据
                        unset($_SESSION['daily_news_backup']);
                    }
                    
                    // 清除恢复标记
                    if (isset($_SESSION['restore_system_data'])) {
                        unset($_SESSION['restore_system_data']);
                    }
                    
                    $config_content = "<?php\n";
                    $config_content .= "date_default_timezone_set('Asia/Shanghai');\n";
                    $config_content .= "// 存储类型配置\n";
                    $config_content .= "define('STORAGE_TYPE', 'json');\n";
                    $config_content .= "// JSON存储配置\n";
                    $config_content .= "define('DB_PREFIX', '$db_prefix');\n";
                    $config_content .= "define('JSON_STORAGE_DIR', '$storage_dir');\n";
                    $config_content .= "?>";
                    
                    $_SESSION['config_content'] = $config_content;
                    
                    header('Location: index.php?step=4');
                    exit;
                }
            }
            break;
            
        case 4:
            $site_name = $_POST['site_name'] ?? 'EDUCN论坛';
            $site_description = $_POST['site_description'] ?? '一个超级牛逼的PHP论坛程序';
            $admin_username = $_POST['admin_username'] ?? '';
            $admin_password = $_POST['admin_password'] ?? '';
            $admin_email = $_POST['admin_email'] ?? '';
            
            if (empty($admin_username) || empty($admin_password) || empty($admin_email)) {
                $error = '请填写所有必填字段';
            } elseif (strlen($admin_password) < 6) {
                $error = '管理员密码至少需要6个字符';
            } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
                $error = '请输入有效的电子邮件地址';
            } else {
                try {
                    $storage_type = $_SESSION['storage_type'] ?? 'mysql';
                    $db_prefix = $_SESSION['db_prefix'];
                    
                    if ($storage_type === 'mysql') {
                        $db_host = $_SESSION['db_host'];
                        $db_name = $_SESSION['db_name'];
                        $db_user = $_SESSION['db_user'];
                        $db_pass = $_SESSION['db_pass'];
                        
                        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        $settings = [
                            'site_name' => $site_name,
                            'site_description' => $site_description,
                            'install_date' => date('Y-m-d H:i:s')
                        ];
                        
                        foreach ($settings as $key => $value) {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$db_prefix}settings` WHERE `setting_key` = ?");
                            $stmt->execute([$key]);
                            $exists = $stmt->fetchColumn();
                            
                            if ($exists) {
                                $stmt = $pdo->prepare("UPDATE `{$db_prefix}settings` SET `setting_value` = ? WHERE `setting_key` = ?");
                                $stmt->execute([$value, $key]);
                            } else {
                                $stmt = $pdo->prepare("INSERT INTO `{$db_prefix}settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES (?, ?, 'string', '')");
                                $stmt->execute([$key, $value]);
                            }
                        }
                        
                        $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
                        
                        $stmt = $pdo->prepare("INSERT INTO `{$db_prefix}users` (`username`, `password`, `email`, `role`, `status`, `created_at`) VALUES (?, ?, ?, 'admin', 'active', NOW())");
                        $stmt->execute([$admin_username, $password_hash, $admin_email]);
                    } else {
                        require_once __DIR__ . '/../includes/json_storage.php';
                        $storage_dir = $_SESSION['storage_dir'];
                        $full_storage_dir = __DIR__ . '/../' . $storage_dir;
                        
                        $jsonStorage = JsonStorage::getInstance();
                        $jsonStorage->setPrefix($db_prefix);
                        $jsonStorage->setDataDir($full_storage_dir);
                        
                        $settings = [
                            'site_name' => $site_name,
                            'site_description' => $site_description,
                            'install_date' => date('Y-m-d H:i:s')
                        ];
                        
                        foreach ($settings as $key => $value) {
                            $existing = $jsonStorage->select('settings', ['setting_key' => $key]);
                            if ($existing) {
                                $jsonStorage->update('settings', ['setting_value' => $value], 'setting_key = ?', [$key]);
                            } else {
                                $jsonStorage->insert('settings', [
                                    'setting_key' => $key,
                                    'setting_value' => $value,
                                    'setting_type' => 'string',
                                    'description' => ''
                                ]);
                            }
                        }
                        
                        $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
                        
                        $jsonStorage->insert('users', [
                            'username' => $admin_username,
                            'password' => $password_hash,
                            'email' => $admin_email,
                            'role' => 'admin',
                            'status' => 'active'
                        ]);
                    }
                    
                    $config_dir = __DIR__ . '/../config/';
                    if (!is_dir($config_dir)) {
                        mkdir($config_dir, 0755, true);
                    }
                    
                    $config_file = $config_dir . 'config.php';
                    file_put_contents($config_file, $_SESSION['config_content']);
                    
                    header('Location: index.php?step=5');
                    exit;
                    
                } catch (PDOException $e) {
                    $error = '配置失败: ' . $e->getMessage();
                } catch (Exception $e) {
                    $error = '配置失败: ' . $e->getMessage();
                }
            }
            break;
    }
}

function showHeader($title, $steps, $current_step) {
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $title; ?> - EDUCN论坛安装向导</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: Arial, sans-serif;
                background-color: #f5f5f5;
                padding: 20px;
            }
            table {
                border-collapse: collapse;
                width: 100%;
            }
            td, th {
                padding: 8px;
                border: 1px solid #999;
            }
            .install-wrapper {
                max-width: 900px;
                margin: 0 auto;
                background-color: #fff;
                border: 1px solid #999;
            }
            .install-header {
                background-color: #f0f0f0;
                padding: 15px;
                text-align: center;
                border-bottom: 1px solid #999;
            }
            .install-header h1 {
                font-size: 24px;
                margin-bottom: 5px;
            }
            .install-version {
                color: #666;
                font-size: 14px;
            }
            .install-steps {
                background-color: #f9f9f9;
                padding: 10px;
                border-bottom: 1px solid #999;
            }
            .install-steps table {
                width: 100%;
            }
            .install-steps td {
                text-align: center;
                padding: 10px;
                border: 1px solid #ccc;
                background-color: #fff;
            }
            .install-steps td.active {
                background-color: #007bff;
                color: white;
                font-weight: bold;
            }
            .install-steps td.completed {
                background-color: #28a745;
                color: white;
            }
            .install-content {
                padding: 20px;
                min-height: 300px;
            }
            .install-footer {
                padding: 15px;
                background-color: #f0f0f0;
                border-top: 1px solid #999;
                text-align: right;
            }
            .section-title {
                background-color: #f0f0f0;
                padding: 10px;
                font-weight: bold;
                border: 1px solid #999;
                margin-bottom: 0;
            }
            .section-content {
                border: 1px solid #999;
                border-top: none;
                padding: 15px;
                margin-bottom: 20px;
            }
            .btn {
                display: inline-block;
                padding: 8px 20px;
                background-color: #007bff;
                color: white;
                text-decoration: none;
                border: 1px solid #0056b3;
                cursor: pointer;
                font-size: 14px;
            }
            .btn:hover {
                background-color: #0056b3;
            }
            .btn-secondary {
                background-color: #6c757d;
                border-color: #545b62;
            }
            .btn-secondary:hover {
                background-color: #545b62;
            }
            .btn-success {
                background-color: #28a745;
                border-color: #1e7e34;
            }
            .btn-success:hover {
                background-color: #1e7e34;
            }
            .alert {
                padding: 12px;
                margin-bottom: 15px;
                border: 1px solid transparent;
            }
            .alert-danger {
                background-color: #f8d7da;
                border-color: #f5c6cb;
                color: #721c24;
            }
            .alert-success {
                background-color: #d4edda;
                border-color: #c3e6cb;
                color: #155724;
            }
            .alert-warning {
                background-color: #fff3cd;
                border-color: #ffeeba;
                color: #856404;
            }
            .alert-info {
                background-color: #d1ecf1;
                border-color: #bee5eb;
                color: #0c5460;
            }
            .form-control {
                width: 100%;
                padding: 8px;
                border: 1px solid #999;
                font-size: 14px;
            }
            .form-label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
            }
            .form-text {
                font-size: 12px;
                color: #666;
                margin-top: 3px;
            }
            .mb-3 {
                margin-bottom: 15px;
            }
            .badge {
                display: inline-block;
                padding: 4px 8px;
                font-size: 12px;
                font-weight: bold;
            }
            .bg-success {
                background-color: #28a745;
                color: white;
            }
            .bg-danger {
                background-color: #dc3545;
                color: white;
            }
            .text-danger {
                color: #dc3545;
            }
            .text-muted {
                color: #666;
            }
            .storage-card {
                cursor: pointer;
                transition: all 0.3s ease;
                border: 2px solid #999;
                padding: 15px;
                margin-bottom: 10px;
            }
            .storage-card:hover {
                border-color: #007bff;
            }
            .storage-card.selected {
                border-color: #007bff;
                background-color: #f0f7ff;
            }
            .storage-icon {
                font-size: 2rem;
                margin-bottom: 10px;
                color: #007bff;
            }
            .requirement-list {
                list-style: none;
                padding-left: 0;
            }
            .requirement-list li {
                padding: 5px 0;
            }
            .text-success {
                color: #28a745;
            }
            .row {
                display: flex;
                flex-wrap: wrap;
                margin: 0 -10px;
            }
            .col-md-6 {
                flex: 0 0 50%;
                max-width: 50%;
                padding: 0 10px;
            }
            @media (max-width: 768px) {
                .col-md-6 {
                    flex: 0 0 100%;
                    max-width: 100%;
                }
            }
        </style>
    </head>
    <body>
        <table class="install-wrapper" border="1" cellspacing="0" cellpadding="0">
            <tr>
                <td class="install-header">
                    <h1>EDUCN论坛安装向导</h1>
                    <div class="install-version">EDUCN-Forum php_forum_json_v0.2.0_t_260404</div>
                </td>
            </tr>
            <tr>
                <td class="install-steps">
                    <table border="1" cellspacing="0" cellpadding="0">
                        <tr>
                            <?php foreach ($steps as $step_num => $step_name): ?>
                            <td class="<?php echo $step_num == $current_step ? 'active' : ($step_num < $current_step ? 'completed' : ''); ?>">
                                <?php echo $step_num . '. ' . $step_name; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="install-content">
    <?php
}

function showFooter() {
    ?>
                </td>
            </tr>
        </table>
    </body>
    </html>
    <?php
}

function showSteps($steps, $current_step) {
    // 步骤现在在 showHeader 中显示
}

function showError($message) {
    if (!empty($message)) {
        echo '<div class="alert alert-danger">' . $message . '</div>';
    }
}

function showSuccess($message) {
    if (!empty($message)) {
        echo '<div class="alert alert-success">' . $message . '</div>';
    }
}

function showWarning($message) {
    if (!empty($message)) {
        echo '<div class="alert alert-warning">' . $message . '</div>';
    }
}

showHeader($steps[$current_step], $steps, $current_step);
showSteps($steps, $current_step);
showError($error);
showSuccess($success);
showWarning($warning);
?>

<div class="install-content">
    <?php
    switch ($current_step) {
        case 1:
            include 'step1_environment.php';
            break;
        case 2:
            include 'step2_storage.php';
            break;
        case 3:
            include 'step3_config_storage.php';
            break;
        case 4:
            include 'step4_admin.php';
            break;
        case 5:
            include 'step5_complete.php';
            break;
    }
    ?>

<?php
showFooter();
?>
