<?php
// API登录接口，用于获取访问token
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../../admin/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    
    // 从数据库验证
    $db = getAdminDB();
    if ($db !== null) {
        $stmt = $db->prepare('SELECT * FROM admin_users WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if ($admin && md5($password) === $admin['password']) {
            // 生成token
            $token = generateApiToken($admin['id'], 3600); // 1小时过期
            
            echo json_encode([
                'code' => 200,
                'message' => '登录成功',
                'data' => [
                    'token' => $token,
                    'expire' => time() + 3600,
                    'user' => [
                        'id' => $admin['id'],
                        'username' => $admin['username']
                    ]
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode([
                'code' => 401,
                'message' => '用户名或密码错误'
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode([
            'code' => 500,
            'message' => '数据库连接失败'
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'code' => 405,
        'message' => '不支持的请求方法'
    ]);
}
?>