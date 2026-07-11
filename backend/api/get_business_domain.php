<?php
// 获取业务域名配置
header('Content-Type: application/json');

// 修正引用路径，使用admin的配置文件
require_once __DIR__ . '/../admin/includes/config.php';

try {
    // 使用小程序后台的数据库连接
    $db = getAdminDB();
    
    if (!$db) {
        throw new Exception('无法连接数据库');
    }
    
    // 从 settings 表读取业务域名配置（与 email_settings.php 保存的位置一致）
    $stmt = $db->query("SELECT key_name, value FROM settings WHERE key_name IN ('business_domain', 'use_business_domain')");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['key_name']] = $row['value'];
    }
    
    // 返回配置
    echo json_encode([
        'code' => 200,
        'message' => '获取成功',
        'data' => [
            'business_domain' => $settings['business_domain'] ?? '',
            'use_business_domain' => intval($settings['use_business_domain'] ?? 0)
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