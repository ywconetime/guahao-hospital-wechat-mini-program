<?php
// 更新就诊人的API
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
    
    // 获取请求数据
    $content = file_get_contents('php://input');
    $data = json_decode($content, true) ?? [];
    
    $id = $data['id'] ?? '';
    $name = $data['name'] ?? '';
    $gender = $data['gender'] ?? '';
    $age = $data['age'] ?? '';
    $phone = $data['phone'] ?? '';
    
    if (empty($id)) {
        throw new Exception('就诊人ID不能为空');
    }
    if (empty($name)) {
        throw new Exception('姓名不能为空');
    }
    if (empty($age)) {
        throw new Exception('年龄不能为空');
    }
    if (empty($phone)) {
        throw new Exception('手机号不能为空');
    }
    
    // 检查就诊人是否存在且属于当前用户
    $checkSql = "SELECT * FROM patients WHERE id = ? AND user_id = ?";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([$id, $userId]);
    $patient = $checkStmt->fetch();
    
    if (!$patient) {
        throw new Exception('就诊人不存在或不属于当前用户');
    }
    
    // 更新就诊人
    $sql = "UPDATE patients SET name = ?, gender = ?, age = ?, phone = ?, updated_at = NOW() WHERE id = ? AND user_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$name, $gender, $age, $phone, $id, $userId]);
    
    echo json_encode([
        'code' => 200,
        'message' => '更新就诊人成功',
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '更新就诊人失败: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>
