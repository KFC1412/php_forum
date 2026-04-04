<?php
date_default_timezone_set('Asia/Shanghai');

/**
 * 配置文件示例
 * 安装程序会自动生成config.php文件
 */

// 存储类型配置
// 可选值: 'mysql' (MySQL/MariaDB) 或 'json' (JSON文件存储)
define('STORAGE_TYPE', 'mysql');

// MySQL数据库配置 (当 STORAGE_TYPE 为 'mysql' 时使用)
define('DB_HOST', 'localhost');     // 数据库主机
define('DB_NAME', 'forum');         // 数据库名
define('DB_USER', 'root');          // 数据库用户名
define('DB_PASS', '');              // 数据库密码
define('DB_PORT', 3306);            // 数据库端口
define('DB_PREFIX', 'forum_');      // 数据库表前缀

// JSON存储配置 (当 STORAGE_TYPE 为 'json' 时使用)
define('JSON_STORAGE_DIR', 'storage/json');  // JSON文件存储目录

// 安全配置
define('SECURITY_SALT', '随机生成的安全盐');  // 用于密码加密
define('SESSION_NAME', 'PHPSESSID');         // 会话名称

// 调试配置
define('DEBUG_MODE', false);  // 是否开启调试模式

// 时区配置
define('TIMEZONE', 'Asia/Shanghai');  // 默认时区

// 设置时区
date_default_timezone_set(TIMEZONE);

/**
 * 存储类型说明:
 * 
 * MySQL/MariaDB (mysql):
 *   - 适合大型论坛，用户量大、访问频繁
 *   - 需要MySQL数据库服务器支持
 *   - 高性能查询，支持复杂事务
 *   - 数据完整性强
 * 
 * JSON文件存储 (json):
 *   - 适合小型论坛或测试环境
 *   - 无需数据库，开箱即用
 *   - 易于备份和迁移
 *   - 建议用户数不超过1000
 */
?>
