<?php
// 创建就诊人的API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // 允许跨域访问
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/JWT.php';

// 确保 patients 表存在
try {
    $db = Database::getInstance()->getConn();
    $sql = "CREATE TABLE IF NOT EXISTS patients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(50) NOT NULL,
        gender VARCHAR(10) NOT NULL,
        age INT NOT NULL,
        phone VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $db->exec($sql);
    // 创建索引
    $sql = "CREATE INDEX IF NOT EXISTS idx_patients_user_id ON patients(user_id)";
    $db->exec($sql);
} catch (Exception $e) {
    // 忽略创建表的错误，继续执行
}

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
    
    $name = $data['name'] ?? '';
    $gender = $data['gender'] ?? '';
    $age = $data['age'] ?? '';
    $phone = $data['phone'] ?? '';
    
    if (empty($name)) {
        throw new Exception('姓名不能为空');
    }
    if (empty($age)) {
        throw new Exception('年龄不能为空');
    }
    if (empty($phone)) {
        throw new Exception('手机号不能为空');
    }
    
    // 创建就诊人
    $sql = "INSERT INTO patients (user_id, name, gender, age, phone, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt = $db->prepare($sql);
    $stmt->execute([$userId, $name, $gender, $age, $phone]);
    
    echo json_encode([
        'code' => 200,
        'message' => '创建就诊人成功',
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '创建就诊人失败: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>
