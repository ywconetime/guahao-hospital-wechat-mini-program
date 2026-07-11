<?php
// 删除就诊人的API
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
        echo json_encode([
            'code' => 401,
            'message' => '请先登录',
            'debug' => ['step' => 'token_empty', 'token' => $token]
        ]);
        exit;
    }
    
    // 验证token
    $payload = JWT::decode($token, 'your_secret_key', ['HS256']);
    $userId = $payload->user_id;
    
    // 获取请求数据
    $content = file_get_contents('php://input');
    $data = json_decode($content, true) ?? [];
    
    $patientId = $data['patient_id'] ?? '';
    
    if (empty($patientId)) {
        echo json_encode([
            'code' => 400,
            'message' => '就诊人ID不能为空',
            'debug' => ['step' => 'patient_id_empty', 'patient_id' => $patientId]
        ]);
        exit;
    }
    
    // 检查就诊人是否存在且属于当前用户
    $checkSql = "SELECT * FROM patients WHERE id = ? AND user_id = ?";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([$patientId, $userId]);
    $patient = $checkStmt->fetch();
    
    if (!$patient) {
        echo json_encode([
            'code' => 404,
            'message' => '就诊人不存在或不属于当前用户',
            'debug' => ['step' => 'patient_not_found', 'patient_id' => $patientId, 'user_id' => $userId]
        ]);
        exit;
    }
    
    // 删除就诊人
    $sql = "DELETE FROM patients WHERE id = ? AND user_id = ?";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([$patientId, $userId]);
    
    echo json_encode([
        'code' => 200,
        'message' => '删除就诊人成功',
        'data' => null,
        'debug' => ['step' => 'success', 'patient_id' => $patientId, 'user_id' => $userId, 'result' => $result]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '删除就诊人失败: ' . $e->getMessage(),
        'data' => null,
        'debug' => ['step' => 'exception', 'error' => $e->getMessage()]
    ]);
}
?>