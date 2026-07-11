<?php
// ================== 超级授权保护系统 - 暂时禁用 ==================
// 暂时解除保护，开发完功能后重新启用
/*
// 层级 1：系统守护者 - 用于保护 api/create_appointment.php
$system_guardian = __DIR__ . '/../../license_system/integration/SystemGuardian.php';
if (!file_exists($system_guardian)) {
    http_response_code(503);
    die('系统守护组件缺失 [GUARDIAN]');
}
require_once $system_guardian;

// 激活系统守护者并验证
SystemGuardian::getInstance();
SystemGuardian::verifySystemIntegrity();
*/

// 原有的授权检查
require_once __DIR__ . '/../check_license.php';
if (!AdminLicenseChecker::check()) {
    AdminLicenseChecker::showUnauthorizedPage();
}
// ================== 超级授权保护系统结束 ==================

// 管理后台配置文件
$config = require __DIR__ . '/../../config/config.php';

// 域名白名单（已禁用）
// 移除了域名白名单限制，所有域名都可以访问系统
// 如果需要重新启用，请取消注释下面的代码并添加允许的域名
/*
$allowedDomains = [
    'bjjs.365xiang.cn',
    '127.0.0.1',
    // 可以添加其他允许访问的域名
];

function checkDomainAccess() {
    global $allowedDomains;
    
    $currentDomain = $_SERVER['HTTP_HOST'] ?? '';
    $currentDomain = preg_replace('/^www\./', '', $currentDomain);
    
    if (!in_array($currentDomain, $allowedDomains)) {
        http_response_code(403);
        echo '访问被拒绝';
        exit;
    }
}
*/

// 域名检查函数（已禁用，所有域名都允许访问）
function checkDomainAccess() {
    // 空函数，不进行域名检查
    // 所有域名都可以访问系统
}

// 管理后台登录状态检查
function checkAdminLogin() {
    // 首先检查域名访问权限
    checkDomainAccess();
    
    session_start();
    // 检查用户是否登录
    if (!isset($_SESSION['admin_id'])) {
        // 使用绝对路径重定向到登录页面
        header('Location: /admin/login.php');
        exit;
    }
    
    // 检查session是否过期（30分钟）
    if (isset($_SESSION['login_time'])) {
        $loginTime = strtotime($_SESSION['login_time']);
        $currentTime = time();
        if ($currentTime - $loginTime > 30 * 60) {
            // Session过期，需要重新登录
            session_destroy();
            header('Location: /admin/login.php');
            exit;
        }
        // 更新最后活动时间
        $_SESSION['last_activity'] = date('Y-m-d H:i:s');
    }
}

// 生成API token
function generateApiToken($userId, $expire = 3600) {
    $secret = 'your-secret-key-here'; // 请修改为更安全的密钥
    $payload = [
        'user_id' => $userId,
        'exp' => time() + $expire,
        'iat' => time(),
        'jti' => uniqid()
    ];
    
    // 简单的token生成，实际项目中建议使用JWT库
    $token = base64_encode(json_encode($payload)) . '.' . md5(json_encode($payload) . $secret);
    return $token;
}

// 验证API token
function validateApiToken($token) {
    $secret = 'your-secret-key-here'; // 请修改为更安全的密钥
    
    if (empty($token)) {
        return false;
    }
    
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return false;
    }
    
    $payloadJson = base64_decode($parts[0]);
    $payload = json_decode($payloadJson, true);
    
    if (!$payload) {
        return false;
    }
    
    // 检查token是否过期
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false;
    }
    
    // 验证签名
    $expectedSignature = md5($payloadJson . $secret);
    if ($parts[1] !== $expectedSignature) {
        return false;
    }
    
    return $payload;
}

// API访问控制检查
function checkApiAccess() {
    // 从请求头获取token
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($token)) {
        // 尝试从GET参数获取
        $token = $_GET['token'] ?? '';
    }
    
    // 移除Bearer前缀（如果有）
    $token = str_replace('Bearer ', '', $token);
    
    $payload = validateApiToken($token);
    if (!$payload) {
        http_response_code(401);
        echo json_encode([
            'code' => 401,
            'message' => '未授权访问'
        ]);
        exit;
    }
    
    return $payload;
}

// 管理后台数据库连接（单例模式）
$adminDB = null;

function getAdminDB() {
    global $config, $adminDB;
    
    if ($adminDB === null) {
        try {
            $adminDB = new PDO(
                "mysql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['database']};charset={$config['db']['charset']}",
                $config['db']['username'],
                $config['db']['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            // 记录错误但不中断执行
            error_log('数据库连接失败: ' . $e->getMessage());
            return null;
        }
    }
    
    return $adminDB;
}
?>
