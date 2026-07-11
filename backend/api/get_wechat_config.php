<?php
// 获取微信小程序配置 - 从 settings 表读取
header('Content-Type: application/json');

require_once __DIR__ . '/../../utils/Database.php';

try {
    $db = Database::getInstance()->getConn();
    
    $config = require __DIR__ . '/../../config/config.php';
    
    $appid = $config['wechat']['appid'] ?? '';
    $appsecret = $config['wechat']['appsecret'] ?? '';
    
    // 从 settings 表读取配置
    try {
        $stmt = $db->prepare("SELECT value FROM settings WHERE key_name = 'auth_appid' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch();
        if ($result && !empty($result['value'])) {
            $appid = $result['value'];
        }
        
        $stmt = $db->prepare("SELECT value FROM settings WHERE key_name = 'auth_appsecret' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch();
        if ($result && !empty($result['value'])) {
            $appsecret = $result['value'];
        }
    } catch (Exception $e) {
        // 如果 settings 表不存在或读取失败，继续使用配置文件
    }
    
    echo json_encode([
        'code' => 200,
        'message' => '获取成功',
        'data' => [
            'appid' => $appid,
            'appsecret' => $appsecret
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '获取失败: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>