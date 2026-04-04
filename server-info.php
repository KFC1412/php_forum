<?php
// 输出服务器软件信息
echo 'Server Software: ' . $_SERVER['SERVER_SOFTWARE'] . '<br>';

// 检查是否启用了mod_rewrite（仅适用于Apache）
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    echo 'Apache Modules: ' . implode(', ', $modules) . '<br>';
    echo 'mod_rewrite enabled: ' . (in_array('mod_rewrite', $modules) ? 'Yes' : 'No') . '<br>';
} else {
    echo 'Not using Apache<br>';
}

// 输出其他相关信息
echo 'Request URI: ' . $_SERVER['REQUEST_URI'] . '<br>';
echo 'Script Name: ' . $_SERVER['SCRIPT_NAME'] . '<br>';
echo 'PHP Self: ' . $_SERVER['PHP_SELF'] . '<br>';
echo 'Document Root: ' . $_SERVER['DOCUMENT_ROOT'] . '<br>';
?>