<?php
// 取消预约的API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // 允许跨域访问
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/JWT.php';

try {
    // 获取数据库连接
    $db = Database::getInstance()->getConn();
    
    // 获取请求参数
    $data = json_decode(file_get_contents('php://input'), true);
    $appointmentId = $data['appointment_id'] ?? $_GET['appointment_id'] ?? '';
    
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
        $token = $_GET['token'] ?? $data['token'] ?? '';
    }
    
    if (empty($token)) {
        throw new Exception('token不能为空');
    }
    
    // 验证token
    $payload = JWT::decode($token, 'your_secret_key', ['HS256']);
    $user_id = $payload->user_id;
    
    if (empty($appointmentId)) {
        throw new Exception('缺少预约ID参数');
    }
    
    try {
        // 检查预约是否存在且属于当前用户
        $stmt = $db->prepare("SELECT * FROM appointments WHERE id = ? AND user_id = ?");
        $stmt->execute([$appointmentId, $user_id]);
        $appointment = $stmt->fetch();
        
        if (!$appointment) {
            throw new Exception('预约不存在或不属于当前用户');
        }
        
        // 更新预约状态为已取消
        $sql = "UPDATE appointments SET status = 'cancelled' WHERE id = ?";
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([$appointmentId]);
        
        if ($result) {
            // 发送通知给用户
            $title = '预约已取消';
            $content = '您的预约已成功取消。如有需要，请重新预约。';
            
            $sql = "INSERT INTO notifications (user_id, title, content, type) VALUES (?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$user_id, $title, $content, 'appointment']);
            
            echo json_encode([
                'code' => 200,
                'message' => '预约取消成功',
                'data' => null
            ], JSON_UNESCAPED_UNICODE);
        } else {
            throw new Exception('预约取消失败');
        }
    } catch (Exception $e) {
        throw $e;
    }
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>