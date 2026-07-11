<?php
// 获取预约详情的API
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
    
    if (empty($appointmentId)) {
        throw new Exception('预约ID不能为空');
    }
    
    // 获取预约详情，连接医生表获取相关信息
    // 从appointments表中获取就诊人信息，而不是从users表中获取
    $sql = "SELECT a.id, a.order_id, a.patient_name, a.patient_phone, a.patient_gender, a.patient_age, a.disease_name, a.symptoms, d.name as doctor_name, a.appointment_time, a.status, a.created_at 
            FROM appointments a 
            LEFT JOIN doctors d ON a.doctor_id = d.id 
            WHERE a.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        throw new Exception('预约不存在');
    }
    
    echo json_encode([
        'code' => 200,
        'message' => '获取预约详情成功',
        'data' => $appointment
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '获取预约详情失败: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>