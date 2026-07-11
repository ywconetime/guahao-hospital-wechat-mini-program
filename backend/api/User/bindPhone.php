<?php
// 绑定手机号接口
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // 允许跨域访问
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/JWT.php';

// 获取微信小程序配置 - 从 settings 表读取
function getWechatConfigBindPhone() {
    $db = Database::getInstance()->getConn();
    
    $config = require __DIR__ . '/../../config/config.php';
    
    $appid = $config['wechat']['appid'] ?? '';
    $appsecret = $config['wechat']['appsecret'] ?? '';
    
    // 从 settings 表读取配置
    try {
        $stmt = $db->prepare("SELECT value FROM settings WHERE key_name = 'auth_appid' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch();
        if ($result && !empty($result['value'])) {
            $appid = $result['value'];
        }
        
        $stmt = $db->prepare("SELECT value FROM settings WHERE key_name = 'auth_appsecret' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch();
        if ($result && !empty($result['value'])) {
            $appsecret = $result['value'];
        }
    } catch (Exception $e) {
        // 如果 settings 表不存在或读取失败，继续使用配置文件
    }
    
    return [
        'appid' => $appid,
        'appsecret' => $appsecret
    ];
}

// 微信小程序AppID和AppSecret
$wechatConfig = getWechatConfigBindPhone();
$appId = $wechatConfig['appid'];
$appSecret = $wechatConfig['appsecret'];

// 测试模式开关 - 设置为true使用模拟手机号，false使用真实微信API
$testMode = false;

// 如果配置为空且不是测试模式，抛出异常
if ((empty($appId) || empty($appSecret)) && !$testMode) {
    throw new Exception('小程序配置未设置，请联系管理员配置AppID和AppSecret');
}

try {
    // 获取数据库连接
    $db = Database::getInstance()->getConn();
    
    // 获取请求参数
    $content = file_get_contents('php://input');
    $params = json_decode($content, true) ?? [];
    
    // 获取token
    $token = '';
    // 尝试从请求头中获取token
    if (isset($_SERVER['HTTP_AUTHORIZATION']) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = $_SERVER['HTTP_AUTHORIZATION'];
        // 移除Bearer前缀
        $token = str_replace('Bearer ', '', $token);
    }
    // 尝试从请求参数中获取token
    if (empty($token)) {
        $token = $_POST['token'] ?? $_GET['token'] ?? $params['token'] ?? '';
    }
    
    // 测试模式下，即使没有token也允许获取模拟手机号
    if (empty($token) && !$testMode) {
        throw new Exception('token不能为空');
    }
    
    // 如果有token，验证并获取userId
    $userId = 1; // 默认用户ID
    if (!empty($token)) {
        try {
            // 验证token
            $payload = JWT::decode($token, 'your_secret_key', ['HS256']);
            $userId = $payload->user_id;
        } catch (Exception $e) {
            // token验证失败，但在测试模式下仍允许继续
            if (!$testMode) {
                throw $e;
            }
        }
    }
    
    // 获取其他请求参数
    $encryptedData = $params['encryptedData'] ?? '';
    $iv = $params['iv'] ?? '';
    $code = $params['code'] ?? '';
    
    if (empty($encryptedData) || empty($iv) || empty($code)) {
        throw new Exception('参数不能为空');
    }
    
    // 检查users表是否存在
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        // 创建users表
        $createTableSql = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            openid VARCHAR(255) NOT NULL UNIQUE,
            nickname VARCHAR(255) DEFAULT NULL,
            avatar VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        $db->exec($createTableSql);
    }
    
    // 检查用户是否存在
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // 如果用户不存在，创建一个新用户
        $stmt = $db->prepare('INSERT INTO users (id, nickname, avatar) VALUES (?, ?, ?)');
        $stmt->execute([$userId, '微信用户', '']);
    }
    
    // 测试模式：使用模拟手机号
    if ($testMode) {
        // 生成一个模拟手机号（138开头）
        $phone = '138' . rand(10000000, 99999999);
        
        // 更新用户手机号
        $stmt = $db->prepare('UPDATE users SET phone = ? WHERE id = ?');
        $stmt->execute([$phone, $userId]);
        
        // 获取更新后的用户信息
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        echo json_encode([
            'code' => 200,
            'message' => '手机号绑定成功（测试模式）',
            'data' => [
                'userInfo' => [
                    'id' => $user['id'],
                    'nickname' => $user['nickname'],
                    'avatar' => $user['avatar'],
                    'phone' => $user['phone']
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 生产模式：使用真实微信API
    // 解密手机号
    function decryptData($appId, $sessionKey, $encryptedData, $iv) {
        $sessionKey = base64_decode($sessionKey);
        $encryptedData = base64_decode($encryptedData);
        $iv = base64_decode($iv);
        
        $data = openssl_decrypt($encryptedData, 'AES-128-CBC', $sessionKey, OPENSSL_RAW_DATA, $iv);
        $decrypted = json_decode($data, true);
        
        if ($decrypted['watermark']['appid'] !== $appId) {
            throw new Exception('解密失败：appid不匹配');
        }
        
        return $decrypted;
    }
    
    // 获取session_key
    function getSessionKey($appId, $appSecret, $code) {
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appId}&secret={$appSecret}&js_code={$code}&grant_type=authorization_code";
        
        // 尝试使用file_get_contents获取响应
        $response = @file_get_contents($url);
        
        // 如果file_get_contents失败，尝试使用curl
        if ($response === false) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($response === false) {
                throw new Exception('无法连接到微信服务器: ' . $curlError);
            }
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('解析微信响应失败: ' . json_last_error_msg());
        }
        
        if (isset($result['errcode'])) {
            // IP白名单错误
            if ($result['errcode'] == 40164) {
                throw new Exception('服务器IP不在微信白名单中，请在微信公众平台添加IP: ' . $_SERVER['SERVER_ADDR']);
            }
            throw new Exception('获取session_key失败: [' . $result['errcode'] . '] ' . $result['errmsg']);
        }
        
        if (!isset($result['session_key'])) {
            throw new Exception('获取session_key失败，响应数据: ' . json_encode($result));
        }
        
        return $result['session_key'];
    }
    
    // 获取session_key
    $sessionKey = getSessionKey($appId, $appSecret, $code);
    
    // 解密手机号
    $decryptedData = decryptData($appId, $sessionKey, $encryptedData, $iv);
    
    if (!isset($decryptedData['phoneNumber'])) {
        throw new Exception('解密失败：未获取到手机号');
    }
    
    $phone = $decryptedData['phoneNumber'];
    
    // 更新用户手机号
    $stmt = $db->prepare('UPDATE users SET phone = ? WHERE id = ?');
    $stmt->execute([$phone, $userId]);
    
    // 获取更新后的用户信息
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    echo json_encode([
        'code' => 200,
        'message' => '手机号绑定成功',
        'data' => [
            'userInfo' => [
                'id' => $user['id'],
                'nickname' => $user['nickname'],
                'avatar' => $user['avatar'],
                'phone' => $user['phone']
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Exception $e) {
    // 记录错误日志
    error_log('绑定手机号失败: ' . $e->getMessage());
    
    echo json_encode([
        'code' => 500,
        'message' => '绑定手机号失败: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>