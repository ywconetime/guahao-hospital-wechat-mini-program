<?php
// 获取用户通知列表
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // 允许跨域访问

require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/JWT.php';

try {
    // 获取数据库连接
    $db = Database::getInstance()->getConn();
    
    // 获取token
    $token = '';
    // 尝试从请求头中获取token
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = $_SERVER['HTTP_AUTHORIZATION'];
        // 移除Bearer前缀
        $token = str_replace('Bearer ', '', $token);
    }
    // 尝试从请求参数中获取token
    if (empty($token)) {
        $token = $_POST['token'] ?? $_GET['token'] ?? '';
    }
    
    if (empty($token)) {
        throw new Exception('token不能为空');
    }
    
    // 验证token
    $payload = JWT::decode($token, 'your_secret_key', ['HS256']);
    $userId = $payload->user_id;
    
    // 获取通知列表
    $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();
    
    // 格式化通知数据
    $formattedNotifications = [];
    foreach ($notifications as $notification) {
        $formattedNotifications[] = [
            'id' => $notification['id'],
            'title' => $notification['title'],
            'content' => $notification['content'],
            'time' => $notification['created_at'],
            'read' => (bool)$notification['read_status'],
            'type' => $notification['type']
        ];
    }
    
    echo json_encode([
        'code' => 200,
        'message' => '获取通知列表成功',
        'data' => $formattedNotifications
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '获取通知列表失败: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>