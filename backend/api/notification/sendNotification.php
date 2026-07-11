<?php
// API配置
require_once __DIR__ . '/../config.php';

// 发送通知
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // 允许跨域访问

require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/JWT.php';

try {
    // 获取数据库连接
    $db = Database::getInstance()->getConn();
    
    // 获取请求参数
    $content = file_get_contents('php://input');
    $params = json_decode($content, true) ?? [];
    
    $userId = $params['user_id'] ?? null;
    $title = $params['title'] ?? '';
    $contentText = $params['content'] ?? '';
    $type = $params['type'] ?? 'other';
    
    if (!$userId || empty($title) || empty($contentText)) {
        throw new Exception('参数不能为空');
    }
    
    // 验证用户是否存在
    $stmt = $db->prepare('SELECT id FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) {
        throw new Exception('用户不存在');
    }
    
    // 插入通知
    $sql = "INSERT INTO notifications (user_id, title, content, type) VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->execute([$userId, $title, $contentText, $type]);
    
    echo json_encode([
        'code' => 200,
        'message' => '通知发送成功',
        'data' => [
            'notification_id' => $db->lastInsertId()
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '发送通知失败: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>