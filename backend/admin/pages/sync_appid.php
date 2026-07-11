<?php
// 同步AppID到小程序前端配置文件
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../../license_system/config.php';
require_once __DIR__ . '/../../license_system/includes/functions.php';

try {
    $licenseDb = get_db_connection();
    
    // 1. 获取数据库中的小程序配置
    $stmt = $licenseDb->query("SELECT appid, appsecret FROM license_free_authorization WHERE appid IS NOT NULL AND appid != '' LIMIT 1");
    $auth = $stmt->fetch();
    
    if (!$auth || empty($auth['appid'])) {
        echo json_encode([
            'code' => 400,
            'message' => '数据库中未找到小程序AppID配置，请先设置小程序配置'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $appid = $auth['appid'];
    
    // 2. 检查并更新project.config.json
    $projectConfigPath = __DIR__ . '/../../xuaochengxu/project.config.json';
    
    if (!file_exists($projectConfigPath)) {
        echo json_encode([
            'code' => 404,
            'message' => '未找到小程序前端配置文件：xuaochengxu/project.config.json'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $configContent = file_get_contents($projectConfigPath);
    $config = json_decode($configContent, true);
    
    if (!$config || !is_array($config)) {
        echo json_encode([
            'code' => 500,
            'message' => '小程序配置文件格式错误'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $oldAppid = $config['appid'] ?? '';
    $config['appid'] = $appid;
    file_put_contents($projectConfigPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        'code' => 200,
        'message' => "AppID已成功同步！\n\n旧值：{$oldAppid}\n新值：{$appid}"
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '同步失败：' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>