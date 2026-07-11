<?php

// ============================================================================
// 保护机制错误显示函数（必须放在最前面）
// ============================================================================
if (!function_exists('_protection_show_error')) {
    function _protection_show_error($layer, $message) {
    if (php_sapi_name() === 'cli' || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => '系统保护已激活', 'code' => 'LAYER_' . $layer, 'message' => $message]);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>系统保护已激活</title><style>body{font-family:Microsoft YaHei;background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}.error-container{text-align:center;padding:60px;background:rgba(231,76,60,0.1);border-radius:30px;border:2px solid rgba(231,76,60,0.4);max-width:600px;box-shadow:0 20px 60px rgba(0,0,0,0.5);}.error-icon{font-size:80px;margin-bottom:20px;}.error-title{color:#e74c3c;font-size:28px;margin-bottom:15px;}.error-message{color:rgba(255,255,255,0.8);line-height:1.8;font-size:16px;}.layer-info{background:rgba(0,0,0,0.3);padding:10px 20px;border-radius:8px;margin-top:20px;font-family:monospace;font-size:14px;color:#f39c12;}</style></head><body><div class="error-container"><div class="error-icon">🛡️</div><h1 class="error-title">系统保护已激活</h1><p class="error-message">检测到配置文件异常，系统已停止运行以保护数据安全。</p><p class="error-message">请联系系统管理员进行修复。</p><div class="layer-info">[LAYER_' . $layer . '] ' . htmlspecialchars($message) . '</div></div></body></html>';
    }
    exit(1);
    }
}

// ============================================================================
// 10层保护机制
// ============================================================================

// 保护开关
$PROTECTION_ENABLED = true;
if (!$PROTECTION_ENABLED) goto _SKIP_PROTECTION;

// Layer 0: 检查保护开关状态文件 - 优先检查开发模式
$switchFile = dirname(__DIR__) . '/config/protection_switch.php';
$isDevMode = false;

// 直接读取文件内容检查开发模式标志
if (file_exists($switchFile)) {
    $content = file_get_contents($switchFile);
    if (strpos($content, '$PROTECTION_DISABLED = true;') !== false) {
        $isDevMode = true;
    }
}

// 开发模式下直接跳过所有保护检查
if ($isDevMode) {
    goto _SKIP_PROTECTION;
}

// Layer 1: 检查本文件完整性
$selfFile = __FILE__;
// 计算相对于根目录的相对路径，用于查找哈希文件
$rootDir = dirname(__DIR__);
$selfRelativePath = substr($selfFile, strlen($rootDir) + 1);
// ✅ 统一路径分隔符为 /，确保Windows和Linux一致！
$normalizedPath = str_replace('\\', '/', $selfRelativePath);
$selfHashFile = __DIR__ . '/../config/hashes/' . md5($normalizedPath) . '.hash';
if (file_exists($selfHashFile)) {
    $expectedHash = trim(file_get_contents($selfHashFile));
    $actualHash = hash_file('sha256', $selfFile);
    if ($actualHash !== $expectedHash) {
        _protection_show_error('001', '文件已被修改');
    }
}

// Layer 2-9: 检查所有关键文件
$rootDir = dirname(__DIR__);
$ds = DIRECTORY_SEPARATOR;
$protectedFiles = array(
    'api' . $ds . 'check_license.php',
    'api' . $ds . 'check_auth_status.php',
    'api' . $ds . 'create_appointment.php',
    'api' . $ds . 'send_auth_email.php',
    'api' . $ds . 'sync_wechat_to_auth.php',
    'admin' . $ds . 'check_license.php',
    'admin' . $ds . 'authorize.php',
    'admin' . $ds . 'login.php',
    'admin' . $ds . 'includes' . $ds . 'functions.php'
);

$layerNum = 2;
foreach ($protectedFiles as $file) {
    $filePath = $rootDir . $ds . $file;
    if (!file_exists($filePath)) {
        _protection_show_error(str_pad($layerNum, 3, '0', STR_PAD_LEFT), '关键文件缺失: ' . $file);
    }
    $layerNum++;
    if ($layerNum > 9) break;
}

_SKIP_PROTECTION:

// 所有检查通过


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 使用统一的API地址配置（支持本地/云端自动切换）
$protectedFile = __DIR__ . '/../config/api_urls_protected.php';
if (!file_exists($protectedFile)) {
    // 文件不存在，返回 JSON 错误
    echo json_encode(['success' => false, 'error' => '系统保护已激活', 'code' => 'LAYER_000']);
    exit(1);
}
require_once $protectedFile;

function getClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    
    return trim($ip);
}

/**
 * 生成设备指纹：结合 IP + UserAgent + 其他特征
 * 确保同一局域网不同设备可以区分
 */
function generateDeviceFingerprint($ip = '') {
    $fingerprint = [];
    
    // 1. IP 地址
    $fingerprint[] = $ip ?: $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // 2. User-Agent（小程序标识）
    $fingerprint[] = $_SERVER['HTTP_USER_AGENT'] ?? 'miniprogram';
    
    // 3. 如果是小程序环境，添加额外标识
    if (isset($_SERVER['HTTP_X_MINIPROGRAM'])) {
        $fingerprint[] = $_SERVER['HTTP_X_MINIPROGRAM'];
    }
    
    // 生成 SHA256 指纹
    return hash('sha256', implode('|', $fingerprint));
}

function verifyLicense($ip, $domain) {
    $url = LICENSE_API_URL . '/free_verify.php';
    
    // 生成设备指纹（与后台保持一致）
    $deviceFingerprint = generateDeviceFingerprint($ip);
    
    $postData = [
        'action' => 'verify',
        'ip' => $ip,
        'domain' => $domain,
        'device_fingerprint' => $deviceFingerprint  // 传递设备指纹
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode != 200) {
        return ['success' => false, 'message' => '无法连接授权服务器'];
    }
    
    $result = json_decode($response, true);
    if (!$result) {
        return ['success' => false, 'message' => '授权服务器响应错误'];
    }
    
    if ($result['code'] == 200) {
        return ['success' => true, 'message' => $result['message'], 'data' => $result['data']];
    }
    
    return ['success' => false, 'message' => $result['message'] ?? '验证失败'];
}

// 使用服务器IP（小程序后台所在服务器的IP），不是访客IP
$serverIP = $_SERVER['REMOTE_ADDR'] ?? '';
if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
    $serverIP = $_SERVER['HTTP_X_REAL_IP'];
}
$domain = $_SERVER['HTTP_HOST'] ?? 'unknown';

$result = verifyLicense($serverIP, $domain);

if ($result['success']) {
    $responseData = ['authorized' => true];
    // 如果有授权数据，包含auth_id
    if (!empty($result['data'])) {
        $responseData = array_merge($responseData, $result['data']);
    }
    echo json_encode([
        'code' => 200,
        'message' => $result['message'],
        'data' => $responseData
    ]);
} else {
    echo json_encode([
        'code' => 403,
        'message' => $result['message'],
        'data' => ['authorized' => false]
    ]);
}
?>