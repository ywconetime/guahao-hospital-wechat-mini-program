<?php
/**
 * 获取 license_system 配置文件的正确路径
 * 兼容本地和云服务器环境
 */
function getLicenseConfigPath() {
    // 尝试多个可能的路径（包括云服务器常见路径）
    $possiblePaths = [
        __DIR__ . '/../license_system/config.php',  // admin/../license_system
        __DIR__ . '/license_system/config.php',      // admin/license_system
        dirname(__DIR__) . '/license_system/config.php',  // 根目录/license_system
        '/www/wwwroot/test.mmgcyy.com/license_system/config.php',  // 宝塔面板默认路径
        '/www/wwwroot/shouquan.mmgcyy.com/license_system/config.php',  // 授权系统域名路径
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    return false; // 找不到
}

/**
 * 安全加载 license_system 配置
 */
function loadLicenseSystem() {
    $configPath = getLicenseConfigPath();
    
    if (!$configPath) {
        error_log("[授权调试] 找不到 license_system/config.php 文件");
        return false;
    }
    
    require_once $configPath;
    
    $functionsPath = dirname($configPath) . '/includes/functions.php';
    if (file_exists($functionsPath)) {
        require_once $functionsPath;
    }
    
    return true;
}
?>
