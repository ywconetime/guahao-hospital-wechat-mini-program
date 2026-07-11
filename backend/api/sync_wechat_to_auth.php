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


/**
 * ============================================================================
 * 【灵码修复】微信用户同步中转API
 * 修复日期: 2026-05-30
 * 修复工具: 通义灵码 (Tongyi Lingma)
 * 功能说明: 小程序前端调用此API，后端转发调用授权系统同步微信用户信息
 * 数据流向: 小程序前端 → 此API → 授权系统API → 存储用户数据
 * 影响范围: 独立新增API，不影响现有功能
 * 风险评估: 🟢低风险（中转转发，失败不影响小程序登录）
 * 测试状态: □未测试 □本地通过 □云端通过 □全部通过
 * ============================================================================
 */

// 使用统一的API地址配置（支持本地/云端自动切换）
$protectedFile = __DIR__ . '/../config/api_urls_protected.php';
if (!file_exists($protectedFile)) {
    // 文件不存在，返回 JSON 错误
    echo json_encode(['success' => false, 'error' => '系统保护已激活', 'code' => 'LAYER_000']);
    exit(1);
}
require_once $protectedFile;

header('Content-Type: application/json; charset=utf-8');

// 引入配置文件（使用多种方式确保路径正确）
$rootDir = dirname(dirname(__DIR__));
$logFile = __DIR__ . '/../logs/sync_wechat_debug.log';
file_put_contents($logFile, "【路径调试】__DIR__: " . __DIR__ . ", rootDir: " . $rootDir . ", DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n", FILE_APPEND);

if (!file_exists($rootDir . '/config/config.php')) {
    $rootDir = $_SERVER['DOCUMENT_ROOT'] ?? $rootDir;
}
file_put_contents($logFile, "【路径调试】最终rootDir: " . $rootDir . ", config.php存在: " . (file_exists($rootDir . '/config/config.php') ? '是' : '否') . "\n", FILE_APPEND);

// 获取授权系统API地址（优先从配置文件读取）
// 1. 优先从 api_urls.php 读取（通过授权系统后台同步生成）
$apiUrlsFile = __DIR__ . '/../config/api_urls.php';
if (file_exists($apiUrlsFile)) {
    require_once $apiUrlsFile;
    if (defined('LICENSE_API_URL') && !empty(LICENSE_API_URL)) {
        $LICENSE_API_URL = LICENSE_API_URL;
    } elseif (function_exists('getLicenseApiUrl')) {
        $LICENSE_API_URL = getLicenseApiUrl();
    }
}

// 2. 如果 api_urls.php 没有配置，尝试从 config.php 读取
if (empty($LICENSE_API_URL)) {
    require_once $rootDir . '/config/config.php';
    $LICENSE_API_URL = $config['license_api_url'] ?? '';
}

// 3. 如果都没有配置，使用后备方案（确保授权系统一定能收到数据）
if (empty($LICENSE_API_URL)) {
    $currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost:88';
    $isLocal = strpos($currentHost, 'localhost') !== false || 
               strpos($currentHost, '127.0.0.1') !== false ||
               strpos($currentHost, '192.168.') !== false ||
               strpos($currentHost, '10.') !== false;
    $LICENSE_API_URL = $isLocal ? 'http://localhost:88/license_system/api' : 'https://shouquan.4wc.cn/license_system/api';
}

file_put_contents($logFile, "【授权API】地址: {$LICENSE_API_URL}\n", FILE_APPEND);

// 获取请求参数（支持GET和POST）
$input = json_decode(file_get_contents('php://input'), true);

$openid = trim($input['openid'] ?? $_GET['openid'] ?? '');
$unionid = trim($input['unionid'] ?? $_GET['unionid'] ?? '');
$nickname = trim($input['nickname'] ?? $_GET['nickname'] ?? '');
$avatarUrl = trim($input['avatar_url'] ?? $_GET['avatar_url'] ?? '');
$phone = trim($input['phone'] ?? $_GET['phone'] ?? '');
$realName = trim($input['real_name'] ?? $_GET['real_name'] ?? '');
$gender = intval($input['gender'] ?? $_GET['gender'] ?? 0);
$province = trim($input['province'] ?? $_GET['province'] ?? '');
$city = trim($input['city'] ?? $_GET['city'] ?? '');
    
    // 验证必填参数
    if (empty($openid)) {
        file_put_contents($logFile, "【参数错误】缺少openid\n", FILE_APPEND);
        echo json_encode([
            'code' => 400,
            'message' => '缺少必填参数：openid',
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 获取授权ID和名称（从请求中获取，由登录API传递）
    $authIdFromRequest = intval($input['auth_id'] ?? $_GET['auth_id'] ?? 0);
    $appName = trim($input['app_name'] ?? $_GET['app_name'] ?? '小程序用户');
    
    if ($authIdFromRequest <= 0) {
        file_put_contents($logFile, "【参数错误】缺少auth_id\n", FILE_APPEND);
        echo json_encode([
            'code' => 400,
            'message' => '缺少auth_id参数',
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $authId = $authIdFromRequest;
    file_put_contents($logFile, "【授权信息】auth_id: {$authId}, app_name: {$appName}\n", FILE_APPEND);
    
    // 调用授权系统API同步用户信息
    $authApiUrl = $LICENSE_API_URL . '/sync_wechat_user.php';
    file_put_contents($logFile, "【同步API】地址: {$authApiUrl}\n", FILE_APPEND);
    
    $syncData = [
        'auth_id' => $authId,
        'app_name' => $appName,
        'openid' => $openid,
        'unionid' => $unionid,
        'nickname' => $nickname,
        'avatar_url' => $avatarUrl,
        'phone' => $phone,
        'real_name' => $realName,
        'gender' => $gender,
        'province' => $province,
        'city' => $city
    ];
    
    // 记录同步日志到专门的日志文件
    $logFile = __DIR__ . '/../logs/sync_wechat.log';
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'auth_id' => $authId,
        'app_name' => $appName,
        'openid' => $openid,
        'api_url' => $authApiUrl,
        'sync_data' => $syncData
    ];
    file_put_contents($logFile, "【同步开始】" . json_encode($logData, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    
    // 使用cURL调用授权系统API
    $ch = curl_init($authApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($syncData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10秒超时
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    // 记录详细日志到专门的日志文件
    $resultLog = [
        'timestamp' => date('Y-m-d H:i:s'),
        'auth_id' => $authId,
        'http_code' => $httpCode,
        'curl_error' => $curlError,
        'response' => substr($response, 0, 500),
        'curl_info' => $info
    ];
    file_put_contents($logFile, "【同步结果】" . json_encode($resultLog, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    
    // 解析授权系统返回结果
    $authResponse = json_decode($response, true);
    
    if ($curlError) {
        error_log("微信用户同步失败：cURL错误 - {$curlError}");
        echo json_encode([
            'code' => 200,
            'message' => '用户信息已记录（授权系统同步失败，稍后重试）',
            'data' => ['sync_status' => 'failed', 'error' => $curlError]
        ], JSON_UNESCAPED_UNICODE);
    } elseif ($httpCode === 200 && $authResponse['code'] === 200) {
        echo json_encode([
            'code' => 200,
            'message' => '用户信息同步成功',
            'data' => [
                'sync_status' => 'success',
                'auth_response' => $authResponse['data']
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        error_log("微信用户同步失败：授权系统返回错误 - " . json_encode($authResponse));
        echo json_encode([
            'code' => 200,
            'message' => '用户信息已记录（授权系统返回异常）',
            'data' => [
                'sync_status' => 'error',
                'auth_response' => $authResponse
            ]
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    error_log("微信用户同步异常：" . $e->getMessage());
    echo json_encode([
        'code' => 200,
        'message' => '用户信息已记录（同步异常）',
        'data' => ['sync_status' => 'exception', 'error' => $e->getMessage()]
    ], JSON_UNESCAPED_UNICODE);
}
?>
