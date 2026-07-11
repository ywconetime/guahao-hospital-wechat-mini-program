<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/JWT.php';

try {
    $db = Database::getInstance()->getConn();
    
    $content = file_get_contents('php://input');
    $params = json_decode($content, true) ?? [];

    $token = $params['token'] ?? '';
    $name = $params['name'] ?? '';
    $gender = $params['gender'] ?? '男';
    $age = $params['age'] ?? '';
    $phone = $params['phone'] ?? '';
    
    if (!$token) {
        echo json_encode(['code' => 401, 'message' => '请先登录', 'debug' => ['step' => 'token_empty', 'token' => $token, 'file' => 'addPatient.php']]);
        exit;
    }

    try {
        $decoded = JWT::decode($token, 'your_secret_key', ['HS256']);
        $userId = $decoded->user_id;
    } catch (Exception $e) {
        echo json_encode(['code' => 401, 'message' => '登录已过期，请重新登录', 'debug' => ['step' => 'token_decode', 'error' => $e->getMessage(), 'file' => 'addPatient.php']]);
        exit;
    }

    if (!$name) {
        echo json_encode(['code' => 400, 'message' => '请填写姓名', 'debug' => ['step' => 'name_empty', 'name' => $name, 'file' => 'addPatient.php']]);
        exit;
    }

    if (!$age) {
        echo json_encode(['code' => 400, 'message' => '请填写年龄', 'debug' => ['step' => 'age_empty', 'age' => $age, 'file' => 'addPatient.php']]);
        exit;
    }

    if (!$phone) {
        echo json_encode(['code' => 400, 'message' => '请填写手机号', 'debug' => ['step' => 'phone_empty', 'phone' => $phone, 'file' => 'addPatient.php']]);
        exit;
    }

    try {
        $stmt = $db->prepare('INSERT INTO patients (user_id, name, gender, age, phone, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$userId, $name, $gender, $age, $phone]);
        
        $patientId = $db->lastInsertId();
        
        echo json_encode([
            'code' => 200, 
            'message' => '添加成功',
            'data' => ['id' => $patientId, 'name' => $name, 'gender' => $gender, 'age' => $age, 'phone' => $phone],
            'debug' => ['step' => 'success', 'userId' => $userId, 'file' => 'addPatient.php']
        ]);
    } catch (Exception $e) {
        echo json_encode(['code' => 500, 'message' => '添加失败: ' . $e->getMessage(), 'debug' => ['step' => 'db_error', 'error' => $e->getMessage(), 'file' => 'addPatient.php']]);
    }
} catch (Exception $e) {
    echo json_encode(['code' => 500, 'message' => '系统错误: ' . $e->getMessage(), 'debug' => ['step' => 'system_error', 'error' => $e->getMessage(), 'file' => 'addPatient.php']]);
}
?>