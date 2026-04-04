<?php
/**
 * URL助手函数库 - 用于生成URL
 * 
 * 此文件包含所有与URL生成相关的函数
 * 所有页面链接应统一通过此文件中的函数生成，确保全站URL风格一致
 */

/**
 * 获取网站根URL
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    
    if ($path === '/' || $path === '\\' || $path === '.') {
        $path = '';
    }
    
    $path = rtrim($path, '/\\');
    
    $baseUrl = $protocol . $host . $path;
    
    return rtrim($baseUrl, '/\\');
}

/**
 * 获取首页URL
 */
function getHomeUrl() {
    return getBaseUrl() . '/index.php';
}

/**
 * 获取首页分页URL
 */
function getHomePageUrl($page = 1) {
    if ($page <= 1) {
        return getHomeUrl();
    }
    return getBaseUrl() . '/index.php?page=' . $page;
}

/**
 * 获取主题页URL
 */
function getTopicUrl($id, $page = null, $title = '') {
    if ($page && $page > 1) {
        return getBaseUrl() . '/topic.php?id=' . $id . '&page=' . $page;
    }
    return getBaseUrl() . '/topic.php?id=' . $id;
}

/**
 * 获取编辑主题URL
 */
function getEditTopicUrl($id) {
    return getBaseUrl() . '/edit_topic.php?id=' . $id;
}

/**
 * 获取分类页URL
 */
function getCategoryUrl($id, $page = null, $title = '') {
    if ($page && $page > 1) {
        return getBaseUrl() . '/category.php?id=' . $id . '&page=' . $page;
    }
    return getBaseUrl() . '/category.php?id=' . $id;
}

/**
 * 获取分类列表页URL
 */
function getCategoriesUrl() {
    return getBaseUrl() . '/categories.php';
}

/**
 * 获取用户资料页URL
 */
function getUserProfileUrl($id, $username = '') {
    return getBaseUrl() . '/profile.php?id=' . $id;
}

/**
 * 获取搜索页URL
 */
function getSearchUrl($query = null, $page = null) {
    $url = getBaseUrl() . '/search.php';
    
    $params = [];
    if ($query) {
        $params['q'] = $query;
    }
    if ($page && $page > 1) {
        $params['page'] = $page;
    }
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    return $url;
}

/**
 * 获取登录页URL
 */
function getLoginUrl($redirect = null) {
    $url = getBaseUrl() . '/login.php';
    if ($redirect) {
        $url .= '?redirect=' . urlencode($redirect);
    }
    return $url;
}

/**
 * 获取注册页URL
 */
function getRegisterUrl() {
    return getBaseUrl() . '/register.php';
}

/**
 * 获取忘记密码页URL
 */
function getForgotPasswordUrl() {
    return getBaseUrl() . '/forgot_password.php';
}

/**
 * 获取重置密码页URL
 */
function getResetPasswordUrl($token) {
    return getBaseUrl() . '/reset_password.php?token=' . $token;
}

/**
 * 获取新主题页URL
 */
function getNewTopicUrl($category_id = null) {
    if ($category_id) {
        return getBaseUrl() . '/new_topic.php?category_id=' . $category_id;
    }
    return getBaseUrl() . '/new_topic.php';
}

/**
 * 获取退出登录URL
 */
function getLogoutUrl() {
    return getBaseUrl() . '/logout.php';
}

/**
 * 获取后台首页URL
 */
function getAdminUrl() {
    return getBaseUrl() . '/admin/';
}

/**
 * 生成分页URL模式
 * 
 * @param string $page_name 页面名称
 * @param array $params 参数数组
 * @return string 分页URL模式
 */
function getPaginationUrlPattern($page_name, $params = []) {
    $url = getBaseUrl() . '/' . $page_name . '?page=%d';
    
    foreach ($params as $key => $value) {
        if ($key !== 'page') {
            $url .= '&' . $key . '=' . urlencode($value);
        }
    }
    
    return $url;
}
