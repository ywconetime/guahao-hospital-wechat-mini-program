<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/JWT.php';

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

if (!$token) {
    echo json_encode(['code' => 401, 'message' => '请先登录', 'debug' => ['step' => 'token_empty', 'token' => $token]]);
    exit;
}

try {
    $decoded = JWT::decode($token, 'your_secret_key', ['HS256']);
    $userId = $decoded->user_id;
} catch (Exception $e) {
    echo json_encode(['code' => 401, 'message' => '登录已过期，请重新登录', 'debug' => ['step' => 'token_decode', 'error' => $e->getMessage()]]);
    exit;
}

try {
    $stmt = $db->prepare('SELECT * FROM patients WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);
    $patients = $stmt->fetchAll();
    
    echo json_encode([
        'code' => 200, 
        'message' => '获取成功',
        'data' => $patients,
        'debug' => ['step' => 'success', 'userId' => $userId, 'count' => count($patients)]
    ]);
} catch (Exception $e) {
    echo json_encode(['code' => 500, 'message' => '获取失败: ' . $e->getMessage(), 'debug' => ['step' => 'db_error', 'error' => $e->getMessage()]]);
}
?>