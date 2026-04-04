<?php
/**
 * 系统配置文件 - 存储系统的基本配置
 */

date_default_timezone_set('Asia/Shanghai');
// 存储类型配置
define('STORAGE_TYPE', 'json');
// JSON存储配置
define('DB_PREFIX', 'forum_');
define('JSON_STORAGE_DIR', 'storage/json');
?>