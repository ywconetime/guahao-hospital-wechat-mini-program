<?php
// 获取用户信息接口
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
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($token)) {
        // 尝试从请求参数中获取token
        $token = $_POST['token'] ?? $_GET['token'] ?? '';
    } else {
        // 移除Bearer前缀
        $token = str_replace('Bearer ', '', $token);
    }
    
    if (empty($token)) {
        throw new Exception('token不能为空');
    }
    
    // 验证token
    $payload = JWT::decode($token, 'your_secret_key', ['HS256']); // 请替换为你的密钥
    $userId = $payload->user_id;
    
    // 获取用户信息
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('用户不存在');
    }
    
    // 移除外部头像服务URL（避免网络问题），前端会使用内置头像图标
    $avatar = $user['avatar'];
    if (!empty($avatar) && strpos($avatar, 'api.dicebear.com') !== false) {
        $avatar = '';
    }
    
    // 返回用户信息
    echo json_encode([
        'code' => 200,
        'message' => '获取用户信息成功',
        'data' => [
            'id' => $user['id'],
            'nickname' => $user['nickname'],
            'avatar' => $avatar,
            'phone' => $user['phone']
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '获取用户信息失败: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>