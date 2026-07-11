<?php
// 小程序获取系统设置接口
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 使用Database类连接数据库
try {
    require_once __DIR__ . '/../utils/Database.php';
    
    // 获取数据库连接
    $db = Database::getInstance()->getConn();
    
    if ($db) {
        $stmt = $db->query('SELECT key_name, value FROM settings');
        $settings = array();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key_name']] = $row['value'];
        }
        
        $response = array(
            'code' => 200,
            'message' => '获取成功',
            'data' => $settings
        );
    } else {
        $response = array(
            'code' => 500,
            'message' => '数据库连接失败'
        );
    }
} catch (Exception $e) {
    $response = array(
        'code' => 500,
        'message' => '服务器内部错误: ' . $e->getMessage()
    );
}

echo json_encode($response);
?>