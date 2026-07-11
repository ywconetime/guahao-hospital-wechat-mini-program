<?php
// 获取用户列表的API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // 允许跨域访问

try {
    require_once __DIR__ . '/../utils/Database.php';
    
    // 获取数据库连接
    $db = Database::getInstance()->getConn();
    
    // 获取用户列表
    $stmt = $db->query('SELECT * FROM users ORDER BY created_at DESC');
    $users = $stmt->fetchAll();
    
    echo json_encode([
        'code' => 200,
        'message' => '获取用户列表成功',
        'data' => $users
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '获取用户列表失败: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>