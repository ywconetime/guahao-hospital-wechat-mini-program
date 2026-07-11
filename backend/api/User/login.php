<?php
// 用户登录接口 - 优化版
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/JWT.php';
require_once __DIR__ . '/../../utils/Wechat.php';

// 使用已有的API配置（不需要重复配置！）
require_once __DIR__ . '/../../config/api_urls_protected.php';

// 获取微信小程序配置 - 从 settings 表读取
function getWechatConfig() {
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

$wechatConfig = getWechatConfig();
$appId = $wechatConfig['appid'];
$appSecret = $wechatConfig['appsecret'];

if (empty($appId) || empty($appSecret)) {
    echo json_encode([
        'code' => 500,
        'message' => '小程序配置未设置，请联系管理员配置AppID和AppSecret',
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = Database::getInstance()->getConn();
    
    $content = file_get_contents('php://input');
    $params = json_decode($content, true) ?? [];
    $code = $params['code'] ?? '';
    $userInfo = $params['userInfo'] ?? null;
    $encryptedData = $params['encryptedData'] ?? '';
    $iv = $params['iv'] ?? '';
    $phone = '';
    
    if (empty($code)) {
        throw new Exception('登录凭证不能为空');
    }
    
    // 使用真实微信API获取session_key和openid
    $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appId}&secret={$appSecret}&js_code={$code}&grant_type=authorization_code";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($response === false || !empty($curlError)) {
        throw new Exception('无法连接到微信服务器');
    }
    
    $result = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('解析微信响应失败');
    }
    
    if (isset($result['errcode'])) {
        throw new Exception('获取openid失败: ' . ($result['errmsg'] ?? '未知错误'));
    }
    
    if (!isset($result['openid'])) {
        throw new Exception('获取openid失败');
    }
    
    $openid = $result['openid'];
    $sessionKey = $result['session_key'] ?? '';
    
    // 解密手机号
    if (!empty($encryptedData) && !empty($iv) && !empty($sessionKey)) {
        $decryptedData = Wechat::decryptData($encryptedData, $iv, $sessionKey);
        
        if ($decryptedData && isset($decryptedData['phoneNumber'])) {
            $phone = $decryptedData['phoneNumber'];
        }
    }
    
    // 检查用户是否已存在
    $stmt = $db->prepare('SELECT * FROM users WHERE openid = ?');
    $stmt->execute([$openid]);
    $user = $stmt->fetch();
    
    $defaultAvatar = 'https://api.dicebear.com/7.x/avataaars/svg?seed=user&size=128&background=%23FF69B4';
    
    if ($user) {
        // 用户已存在，更新用户信息
        if ($userInfo || !empty($phone)) {
            $updateData = [];
            $updateFields = [];
            
            if ($userInfo) {
                if (isset($userInfo['nickName'])) {
                    $updateFields[] = 'nickname = ?';
                    $updateData[] = $userInfo['nickName'];
                }
                if (isset($userInfo['avatarUrl'])) {
                    $updateFields[] = 'avatar = ?';
                    $updateData[] = $userInfo['avatarUrl'];
                }
            }
            
            if (!empty($phone)) {
                $updateFields[] = 'phone = ?';
                $updateData[] = $phone;
            }
            
            if (!empty($updateFields)) {
                $updateData[] = $openid;
                $sql = 'UPDATE users SET ' . implode(', ', $updateFields) . ' WHERE openid = ?';
                $stmt = $db->prepare($sql);
                $stmt->execute($updateData);
                
                $stmt = $db->prepare('SELECT * FROM users WHERE openid = ?');
                $stmt->execute([$openid]);
                $user = $stmt->fetch();
            }
        }
    } else {
        // 用户不存在，创建新用户
        $nickname = $userInfo ? ($userInfo['nickName'] ?? '微信用户') : '微信用户';
        $avatar = $userInfo ? ($userInfo['avatarUrl'] ?? $defaultAvatar) : $defaultAvatar;
        
        $stmt = $db->prepare('INSERT INTO users (openid, nickname, avatar, phone) VALUES (?, ?, ?, ?)');
        $stmt->execute([$openid, $nickname, $avatar, $phone]);
        
        $stmt = $db->prepare('SELECT * FROM users WHERE openid = ?');
        $stmt->execute([$openid]);
        $user = $stmt->fetch();
    }
    
    // 生成token
    $payload = [
        'user_id' => $user['id'],
        'openid' => $user['openid'],
        'nickname' => $user['nickname']
    ];
    
    $token = JWT::encode($payload, 'your_secret_key', 'HS256');
    
    // ===== 异步同步用户信息到授权系统 =====
    // 先记录需要同步的数据，然后返回响应
    $syncUserData = [
        'openid' => $openid,
        'nickname' => $user['nickname'],
        'avatar' => $user['avatar'],
        'phone' => $user['phone']
    ];
    
    // 返回用户信息和token（先响应，同步在后台执行）
    echo json_encode([
        'code' => 200,
        'message' => '登录成功',
        'data' => [
            'token' => $token,
            'userInfo' => [
                'id' => $user['id'],
                'nickname' => $user['nickname'],
                'avatar' => $user['avatar'],
                'phone' => $user['phone']
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);
    
    // 立即发送响应，后台继续执行同步
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    // ===== 后台异步同步逻辑 =====
    try {
        // 记录日志到专门文件
        $logDir = dirname(__DIR__, 2) . '/logs';
        $logFile = $logDir . '/sync_wechat.log';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // 获取当前域名和IP
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        file_put_contents($logFile, "【异步同步开始】时间: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        file_put_contents($logFile, "【环境信息】域名: {$domain}, IP: {$ip}, LICENSE_API_URL: " . LICENSE_API_URL . "\n", FILE_APPEND);
        
        // 先调用授权系统获取授权ID
        $authId = 0;
        $appName = '小程序用户';
        
        $authCheckUrl = LICENSE_API_URL . '/get_auth_id.php?domain=' . urlencode($domain) . '&ip=' . urlencode($ip);
        file_put_contents($logFile, "【获取授权ID】URL: {$authCheckUrl}\n", FILE_APPEND);
        
        $ch = curl_init($authCheckUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $authCheckResponse = curl_exec($ch);
        $authCheckHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $authCheckError = curl_error($ch);
        curl_close($ch);
        
        file_put_contents($logFile, "【获取授权ID】HTTP: {$authCheckHttpCode}, Error: {$authCheckError}, Response: " . ($authCheckResponse ? substr($authCheckResponse, 0, 300) : '空') . "\n", FILE_APPEND);
        
        if ($authCheckResponse) {
            $authCheckResult = json_decode($authCheckResponse, true);
            if (is_array($authCheckResult) && isset($authCheckResult['success']) && $authCheckResult['success'] && isset($authCheckResult['data']['auth_id'])) {
                $authId = intval($authCheckResult['data']['auth_id']);
                $appName = $authCheckResult['data']['app_name'] ?? $appName;
            }
        }
        
        file_put_contents($logFile, "【获取授权ID】最终 auth_id: {$authId}, app_name: {$appName}\n", FILE_APPEND);
        
        // 只有当auth_id有效时才进行同步
        if ($authId > 0) {
            $syncData = [
                'auth_id' => $authId,
                'app_name' => $appName,
                'openid' => $syncUserData['openid'],
                'unionid' => '',
                'nickname' => $syncUserData['nickname'],
                'avatar_url' => $syncUserData['avatar'],
                'phone' => $syncUserData['phone'],
                'real_name' => '',
                'gender' => 0,
                'province' => '',
                'city' => ''
            ];
            
            $syncUrl = LICENSE_API_URL . '/sync_wechat_user.php';
            file_put_contents($logFile, "【同步用户】URL: {$syncUrl}\n", FILE_APPEND);
            file_put_contents($logFile, "【同步用户】数据: " . json_encode($syncData, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
            
            $ch = curl_init($syncUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($syncData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $syncResponse = curl_exec($ch);
            $syncHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $syncError = curl_error($ch);
            curl_close($ch);
            
            file_put_contents($logFile, "【同步用户】HTTP: {$syncHttpCode}, Error: {$syncError}, Response: " . ($syncResponse ? substr($syncResponse, 0, 500) : '空') . "\n", FILE_APPEND);
            file_put_contents($logFile, "【异步同步结束】\n\n", FILE_APPEND);
        } else {
            file_put_contents($logFile, "【异步同步跳过】auth_id无效，跳过同步\n\n", FILE_APPEND);
        }
    } catch (Exception $e) {
        // 同步失败不影响登录
        if (isset($logFile)) {
            file_put_contents($logFile, "【异步同步异常】" . $e->getMessage() . "\n\n", FILE_APPEND);
        }
    }
    
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '登录失败: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>