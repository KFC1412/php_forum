<?php

/**
 * 用户体验和移动端优化功能
 */

/**
 * 生成响应式CSS类
 * @param array $breakpoints 断点配置
 * @return string CSS代码
 */
function generateResponsiveCss($breakpoints = []) {
    $default_breakpoints = [
        'xs' => 0,
        'sm' => 576,
        'md' => 768,
        'lg' => 992,
        'xl' => 1200,
        'xxl' => 1400
    ];
    
    $breakpoints = array_merge($default_breakpoints, $breakpoints);
    
    $css = '';
    
    // 响应式容器
    $css .= '.container {
    width: 100%;
    padding-right: var(--bs-gutter-x, 0.75rem);
    padding-left: var(--bs-gutter-x, 0.75rem);
    margin-right: auto;
    margin-left: auto;
}

';
    
    foreach ($breakpoints as $name => $width) {
        if ($width > 0) {
            $css .= '@media (min-width: ' . $width . 'px) {
    .container {
        max-width: ' . ($width - 30) . 'px;
    }
}

';
        }
    }
    
    // 响应式网格
    $css .= '.row {
    display: flex;
    flex-wrap: wrap;
    margin-right: -0.75rem;
    margin-left: -0.75rem;
}

';
    
    for ($i = 1; $i <= 12; $i++) {
        $css .= '.col-' . $i . ' {
    flex: 0 0 auto;
    width: ' . ($i * 100 / 12) . '%;
}

';
        
        foreach ($breakpoints as $name => $width) {
            if ($width > 0) {
                $css .= '@media (min-width: ' . $width . 'px) {
    .col-' . $name . '-' . $i . ' {
        flex: 0 0 auto;
        width: ' . ($i * 100 / 12) . '%;
    }
}

';
            }
        }
    }
    
    return $css;
}

/**
 * 生成压缩的CSS
 * @param string $css CSS代码
 * @return string 压缩后的CSS
 */
function minifyCss($css) {
    // 移除注释
    $css = preg_replace('/\/\*[\s\S]*?\*\//', '', $css);
    // 移除多余的空白字符
    $css = preg_replace('/\s+/', ' ', $css);
    // 移除分号前的空白
    $css = preg_replace('/\s*;\s*/', ';', $css);
    // 移除花括号前后的空白
    $css = preg_replace('/\s*{\s*/', '{', $css);
    $css = preg_replace('/\s*}\s*/', '}', $css);
    // 移除冒号后的空白
    $css = preg_replace('/\s*:\s*/', ':', $css);
    // 移除逗号后的空白
    $css = preg_replace('/\s*,\s*/', ',', $css);
    // 移除最后一个分号
    $css = preg_replace('/;}/', '}', $css);
    return trim($css);
}

/**
 * 生成压缩的JavaScript
 * @param string $js JavaScript代码
 * @return string 压缩后的JavaScript
 */
function minifyJs($js) {
    // 移除注释
    $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
    $js = preg_replace('/\/\/.*$/m', '', $js);
    // 移除多余的空白字符
    $js = preg_replace('/\s+/', ' ', $js);
    // 移除分号前的空白
    $js = preg_replace('/\s*;\s*/', ';', $js);
    // 移除花括号前后的空白
    $js = preg_replace('/\s*{\s*/', '{', $js);
    $js = preg_replace('/\s*}\s*/', '}', $js);
    // 移除冒号后的空白
    $js = preg_replace('/\s*:\s*/', ':', $js);
    // 移除逗号后的空白
    $js = preg_replace('/\s*,\s*/', ',', $js);
    // 移除圆括号前后的空白
    $js = preg_replace('/\s*\(\s*/', '(', $js);
    $js = preg_replace('/\s*\)\s*/', ')', $js);
    return trim($js);
}

/**
 * 生成图片懒加载JavaScript
 * @return string JavaScript代码
 */
function generateLazyLoadScript() {
    $js = 'document.addEventListener("DOMContentLoaded", function() {
    const lazyImages = document.querySelectorAll("img[data-src]");
    
    if ("IntersectionObserver" in window) {
        const imageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const image = entry.target;
                    image.src = image.dataset.src;
                    image.classList.remove("lazy");
                    imageObserver.unobserve(image);
                }
            });
        });
        
        lazyImages.forEach(function(image) {
            imageObserver.observe(image);
        });
    } else {
        // 降级方案
        lazyImages.forEach(function(image) {
            image.src = image.dataset.src;
            image.classList.remove("lazy");
        });
    }
});';
    
    return $js;
}

/**
 * 生成响应式导航菜单JavaScript
 * @return string JavaScript代码
 */
function generateResponsiveNavScript() {
    $js = 'document.addEventListener("DOMContentLoaded", function() {
    const navToggle = document.querySelector(".navbar-toggle");
    const navMenu = document.querySelector(".navbar-menu");
    
    if (navToggle && navMenu) {
        navToggle.addEventListener("click", function() {
            navMenu.classList.toggle("active");
        });
    }
    
    // 点击菜单项后关闭菜单
    const navLinks = document.querySelectorAll(".navbar-menu a");
    navLinks.forEach(function(link) {
        link.addEventListener("click", function() {
            if (navMenu.classList.contains("active")) {
                navMenu.classList.remove("active");
            }
        });
    });
});';
    
    return $js;
}

/**
 * 生成平滑滚动JavaScript
 * @return string JavaScript代码
 */
function generateSmoothScrollScript() {
    $js = 'document.addEventListener("DOMContentLoaded", function() {
    const links = document.querySelectorAll("a[href^=\"#\"]");
    
    links.forEach(function(link) {
        link.addEventListener("click", function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute("href");
            if (targetId === "#") return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 80,
                    behavior: "smooth"
                });
            }
        });
    });
});';
    
    return $js;
}

/**
 * 生成表单验证JavaScript
 * @return string JavaScript代码
 */
function generateFormValidationScript() {
    $js = 'document.addEventListener("DOMContentLoaded", function() {
    const forms = document.querySelectorAll("form");
    
    forms.forEach(function(form) {
        form.addEventListener("submit", function(e) {
            let isValid = true;
            const requiredFields = form.querySelectorAll("[required]");
            
            requiredFields.forEach(function(field) {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add("error");
                    field.setAttribute("title", "此字段为必填项");
                } else {
                    field.classList.remove("error");
                    field.removeAttribute("title");
                }
            });
            
            // 邮箱验证
            const emailFields = form.querySelectorAll("[type=email]");
            emailFields.forEach(function(field) {
                if (field.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) {
                    isValid = false;
                    field.classList.add("error");
                    field.setAttribute("title", "请输入有效的邮箱地址");
                } else if (field.value) {
                    field.classList.remove("error");
                    field.removeAttribute("title");
                }
            });
            
            // 密码验证
            const passwordFields = form.querySelectorAll("[type=password]");
            passwordFields.forEach(function(field) {
                if (field.value && field.value.length < 6) {
                    isValid = false;
                    field.classList.add("error");
                    field.setAttribute("title", "密码长度至少为6位");
                } else if (field.value) {
                    field.classList.remove("error");
                    field.removeAttribute("title");
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert("请检查表单中的错误");
            }
        });
        
        // 实时验证
        const inputFields = form.querySelectorAll("input, textarea, select");
        inputFields.forEach(function(field) {
            field.addEventListener("input", function() {
                if (this.classList.contains("error")) {
                    if (this.hasAttribute("required") && this.value.trim()) {
                        this.classList.remove("error");
                        this.removeAttribute("title");
                    } else if (this.type === "email" && this.value && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value)) {
                        this.classList.remove("error");
                        this.removeAttribute("title");
                    } else if (this.type === "password" && this.value.length >= 6) {
                        this.classList.remove("error");
                        this.removeAttribute("title");
                    }
                }
            });
        });
    });
});';
    
    return $js;
}

/**
 * 生成加载动画CSS
 * @return string CSS代码
 */
function generateLoadingAnimationCss() {
    $css = '.loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.8);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}';
    
    return $css;
}

/**
 * 生成移动端适配CSS
 * @return string CSS代码
 */
function generateMobileCss() {
    $css = '@media (max-width: 768px) {
    /* 移动端导航 */
    .navbar {
        padding: 0.5rem 1rem;
    }
    
    .navbar-brand {
        font-size: 1.2rem;
    }
    
    .navbar-menu {
        position: fixed;
        top: 60px;
        left: 0;
        width: 100%;
        background-color: #fff;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }
    
    .navbar-menu.active {
        max-height: 300px;
    }
    
    .navbar-menu ul {
        flex-direction: column;
        padding: 0;
    }
    
    .navbar-menu li {
        margin: 0;
        border-bottom: 1px solid #eee;
    }
    
    .navbar-menu a {
        display: block;
        padding: 0.75rem 1rem;
    }
    
    /* 移动端内容 */
    .container {
        padding: 0 1rem;
    }
    
    .topic-item {
        padding: 1rem;
    }
    
    .topic-title {
        font-size: 1.1rem;
    }
    
    .topic-meta {
        font-size: 0.8rem;
    }
    
    /* 移动端表单 */
    form {
        padding: 1rem;
    }
    
    input, textarea, select {
        padding: 0.75rem;
        font-size: 1rem;
    }
    
    button {
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
    }
    
    /* 移动端评论 */
    .reply-item {
        padding: 1rem;
    }
    
    .reply-content {
        font-size: 0.9rem;
    }
    
    /* 移动端分页 */
    .pagination {
        flex-wrap: wrap;
    }
    
    .pagination li {
        margin: 0.25rem;
    }
    
    /* 移动端侧边栏 */
    .sidebar {
        display: none;
    }
    
    /* 移动端按钮 */
    .btn {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }
}';
    
    return $css;
}

/**
 * 生成主题切换功能
 * @return string JavaScript代码
 */
function generateThemeToggleScript() {
    $js = 'document.addEventListener("DOMContentLoaded", function() {
    const themeToggle = document.querySelector(".theme-toggle");
    
    if (themeToggle) {
        // 加载保存的主题
        const savedTheme = localStorage.getItem("theme");
        if (savedTheme) {
            document.documentElement.setAttribute("data-theme", savedTheme);
        }
        
        themeToggle.addEventListener("click", function() {
            const currentTheme = document.documentElement.getAttribute("data-theme") || "light";
            const newTheme = currentTheme === "light" ? "dark" : "light";
            
            document.documentElement.setAttribute("data-theme", newTheme);
            localStorage.setItem("theme", newTheme);
        });
    }
});';
    
    return $js;
}

/**
 * 生成暗黑模式CSS
 * @return string CSS代码
 */
function generateDarkModeCss() {
    $css = ':root {
    --primary-color: #007bff;
    --secondary-color: #6c757d;
    --background-color: #fff;
    --text-color: #333;
    --border-color: #dee2e6;
    --hover-color: #f8f9fa;
}

[data-theme="dark"] {
    --primary-color: #17a2b8;
    --secondary-color: #adb5bd;
    --background-color: #212529;
    --text-color: #e9ecef;
    --border-color: #343a40;
    --hover-color: #343a40;
}

body {
    background-color: var(--background-color);
    color: var(--text-color);
    transition: background-color 0.3s ease, color 0.3s ease;
}

.navbar {
    background-color: var(--background-color);
    border-bottom: 1px solid var(--border-color);
}

.card {
    background-color: var(--background-color);
    border: 1px solid var(--border-color);
}

.btn {
    background-color: var(--primary-color);
    color: #fff;
}

.btn:hover {
    opacity: 0.9;
}

input, textarea, select {
    background-color: var(--background-color);
    color: var(--text-color);
    border: 1px solid var(--border-color);
}

input:focus, textarea:focus, select:focus {
    border-color: var(--primary-color);
    outline: none;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

a {
    color: var(--primary-color);
}

a:hover {
    text-decoration: underline;
}

.topic-item:hover {
    background-color: var(--hover-color);
}

.reply-item:hover {
    background-color: var(--hover-color);
}';
    
    return $css;
}

/**
 * 初始化用户体验优化
 */
function initUserExperience() {
    // 创建必要的目录
    $dirs = [
        __DIR__ . '/../storage/cache/css/',
        __DIR__ . '/../storage/cache/js/'
    ];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

/**
 * 生成完整的前端优化代码
 * @return array 包含CSS和JS的数组
 */
function generateFrontendOptimizations() {
    $css = '';
    $js = '';
    
    // 响应式CSS
    $css .= generateResponsiveCss();
    
    // 加载动画
    $css .= generateLoadingAnimationCss();
    
    // 移动端CSS
    $css .= generateMobileCss();
    
    // 暗黑模式
    $css .= generateDarkModeCss();
    
    // 压缩CSS
    $css = minifyCss($css);
    
    // 懒加载
    $js .= generateLazyLoadScript() . '\n';
    
    // 响应式导航
    $js .= generateResponsiveNavScript() . '\n';
    
    // 平滑滚动
    $js .= generateSmoothScrollScript() . '\n';
    
    // 表单验证
    $js .= generateFormValidationScript() . '\n';
    
    // 主题切换
    $js .= generateThemeToggleScript() . '\n';
    
    // 压缩JS
    $js = minifyJs($js);
    
    return [
        'css' => $css,
        'js' => $js
    ];
}

/**
 * 缓存前端资源
 * @return bool
 */
function cacheFrontendResources() {
    try {
        $optimizations = generateFrontendOptimizations();
        
        // 缓存CSS
        file_put_contents(__DIR__ . '/../storage/cache/css/optimized.css', $optimizations['css']);
        
        // 缓存JS
        file_put_contents(__DIR__ . '/../storage/cache/js/optimized.js', $optimizations['js']);
        
        return true;
    } catch (Exception $e) {
        error_log('缓存前端资源失败：' . $e->getMessage());
        return false;
    }
}

/**
 * 获取前端资源URL
 * @param string $type 资源类型
 * @return string URL
 */
function getFrontendResourceUrl($type) {
    $base_url = '/storage/cache/';
    
    switch ($type) {
        case 'css':
            return $base_url . 'css/optimized.css?' . filemtime(__DIR__ . '/../storage/cache/css/optimized.css');
        case 'js':
            return $base_url . 'js/optimized.js?' . filemtime(__DIR__ . '/../storage/cache/js/optimized.js');
        default:
            return '';
    }
}
?>