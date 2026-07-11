<?php
// 业务域名配置API - 用于接收前端配置工具的请求
header('Content-Type: application/json');
require_once __DIR__ . '/../admin/includes/config.php';

$response = ['success' => false, 'message' => '未知错误'];

try {
    $db = getAdminDB();
    
    if (!$db) {
        throw new Exception('无法连接数据库');
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['business_domain'])) {
        throw new Exception('缺少业务域名参数');
    }
    
    $business_domain = trim($data['business_domain']);
    $use_business_domain = isset($data['use_business_domain']) ? (int)$data['use_business_domain'] : 0;
    
    // 保存业务域名
    $stmt = $db->prepare("INSERT INTO settings (key_name, value) VALUES ('business_domain', ?) ON DUPLICATE KEY UPDATE value = ?");
    $stmt->execute([$business_domain, $business_domain]);
    
    // 保存启用状态
    $stmt = $db->prepare("INSERT INTO settings (key_name, value) VALUES ('use_business_domain', ?) ON DUPLICATE KEY UPDATE value = ?");
    $stmt->execute([$use_business_domain, $use_business_domain]);
    
    $response = [
        'success' => true,
        'message' => '配置保存成功',
        'data' => [
            'business_domain' => $business_domain,
            'use_business_domain' => $use_business_domain
        ]
    ];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>