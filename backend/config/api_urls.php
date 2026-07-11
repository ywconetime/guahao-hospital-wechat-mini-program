<?php
// config/api_urls.dev.php - 开发版（无保护）
// 用途：开发、维护、修改代码时使用
// 特点：无加密、无保护机制，方便修改测试

// 本地开发环境
define('LICENSE_API_URL_LOCAL', 'http://localhost:88/license_system/api');
// 云服务器生产环境
define('LICENSE_API_URL_CLOUD', 'https://shouquan.4wc.cn/license_system/api');

// 根据当前环境选择API地址
function getLicenseApiUrl() {
    $currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost:88';
    
    // 检测是否为本地开发环境
    $isLocal = strpos($currentHost, 'localhost') !== false || 
               strpos($currentHost, '127.0.0.1') !== false ||
               strpos($currentHost, '192.168.') !== false ||
               strpos($currentHost, '10.') !== false;
    
    return $isLocal ? LICENSE_API_URL_LOCAL : LICENSE_API_URL_CLOUD;
}

// 定义主常量
if (!defined('LICENSE_API_URL')) {
    define('LICENSE_API_URL', getLicenseApiUrl());
}
?>