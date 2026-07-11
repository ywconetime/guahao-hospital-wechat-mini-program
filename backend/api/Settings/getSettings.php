<?php
// 获取系统设置的API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // 允许跨域访问

require_once __DIR__ . '/../../utils/Database.php';

try {
    // 获取数据库连接
    $db = Database::getInstance()->getConn();
    
    // 获取系统设置
    $settings = [];
    if ($db !== null) {
        $stmt = $db->query('SELECT key_name, value FROM settings');
        while ($row = $stmt->fetch()) {
            $settings[$row['key_name']] = $row['value'];
        }
    }
    
    // 设置默认值
    $defaultSettings = [
        'site_name' => '厦门元火妇科男科医院',
        'site_description' => '厦门元火妇科男科医院是一家专业的妇科男科医院，提供优质的医疗服务。',
        'contact_phone' => '0592-12345678',
        'contact_email' => 'contact@example.com',
        'address' => '厦门市思明区某某路123号',
        'copyright' => '© 2026 厦门元火妇科男科医院'
    ];
    
    // 合并默认值和数据库中的设置
    $result = array_merge($defaultSettings, $settings);
    
    echo json_encode([
        'code' => 200,
        'message' => '获取系统设置成功',
        'data' => $result
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '获取系统设置失败: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>