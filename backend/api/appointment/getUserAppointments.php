<?php
// 获取用户预约列表的API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // 允许跨域访问
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
    // 尝试从JSON请求体中获取token
    if (empty($token)) {
        $content = file_get_contents('php://input');
        $params = json_decode($content, true) ?? [];
        $token = $params['token'] ?? '';
    }
    
    if (empty($token)) {
        throw new Exception('token不能为空');
    }
    
    // 验证token
    $payload = JWT::decode($token, 'your_secret_key', ['HS256']);
    $userId = $payload->user_id;
    
    // 获取用户的预约列表，连接医生表获取相关信息
    // 从appointments表中获取就诊人信息，而不是从users表中获取
    $sql = "SELECT a.id, a.order_id, a.patient_name, a.patient_phone, a.patient_gender, a.patient_age, a.disease_name, a.symptoms, d.name as doctor_name, a.appointment_time, a.status, a.created_at 
            FROM appointments a 
            LEFT JOIN doctors d ON a.doctor_id = d.id 
            WHERE a.user_id = ? 
            ORDER BY a.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$userId]);
    $appointments = $stmt->fetchAll();
    
    echo json_encode([
        'code' => 200,
        'message' => '获取预约列表成功',
        'data' => $appointments
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '获取预约列表失败: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>