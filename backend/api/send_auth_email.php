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
 * 发送授权提醒邮件API
 * 供授权系统调用，发送新授权申请的邮件通知
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
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'code' => 400,
        'message' => '只允许POST请求'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取请求数据
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    // 尝试从POST获取
    $data = $_POST;
}

$name = $data['name'] ?? '';
$phone = $data['phone'] ?? '';
$wechat = $data['wechat'] ?? '';
$ip = $data['ip'] ?? '';
$domain = $data['domain'] ?? '';
$auth_id = $data['auth_id'] ?? '';

if (empty($name) || empty($phone)) {
    echo json_encode([
        'code' => 400,
        'message' => '缺少必要参数'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 记录开始发送邮件
    logEmailError("开始发送授权邮件 - 姓名: $name, 手机: $phone, 域名: $domain");
    
    // 从授权系统获取邮件配置
    $config = getEmailConfig();
    
    // 记录使用的配置
    logEmailError("使用SMTP配置: {$config['smtp_server']}:{$config['smtp_port']}, 发件人: {$config['smtp_username']}, 收件人: {$config['admin_emails']}");
    
    // 发送邮件
    $result = sendEmail($config, $name, $phone, $wechat, $ip, $domain, $auth_id);
    
    if ($result['success']) {
        logEmailError("邮件发送成功！");
        echo json_encode([
            'code' => 200,
            'message' => '邮件发送成功'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        logEmailError("邮件发送失败: " . $result['message']);
        echo json_encode([
            'code' => 500,
            'message' => '邮件发送失败: ' . $result['message']
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    logEmailError("邮件发送异常: " . $e->getMessage());
    echo json_encode([
        'code' => 500,
        'message' => '邮件发送异常: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 记录邮件发送日志
 */
function logEmailError($message) {
    $logFile = __DIR__ . '/email_error_log.txt';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

/**
 * 从授权系统获取邮件配置
 */
function getEmailConfig() {
    // 完全依赖授权系统API，不使用任何硬编码默认值
    $config = [
        'site_name' => '',
        'smtp_server' => '',
        'smtp_port' => 0,
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_from_name' => '',
        'admin_emails' => ''
    ];
    
    try {
        // 尝试调用授权系统API获取配置
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $apiUrl = $protocol . '://' . $host . '/license_system/api/get_email_config.php';
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200 && !empty($response)) {
            $result = json_decode($response, true);
            if ($result && isset($result['code']) && $result['code'] == 200 && isset($result['data'])) {
                $data = $result['data'];
                $config['site_name'] = $data['site_name'] ?? '';
                $config['smtp_server'] = $data['smtp_server'] ?? '';
                $config['smtp_port'] = intval($data['smtp_port'] ?? 0);
                $config['smtp_username'] = $data['smtp_username'] ?? '';
                $config['smtp_password'] = $data['smtp_password'] ?? '';
                $config['smtp_from_name'] = $data['smtp_from_name'] ?? '';
                $config['admin_emails'] = $data['admin_emails'] ?? '';
            }
        }
    } catch (Exception $e) {
        // 获取配置失败，不使用硬编码
    }
    
    return $config;
}

/**
 * 发送邮件
 */
function sendEmail($config, $name, $phone, $wechat, $ip, $domain, $auth_id) {
    // 检查配置是否完整，不完整就不发送邮件
    if (empty($config['smtp_server']) || empty($config['smtp_port']) || empty($config['smtp_username']) || empty($config['smtp_password']) || empty($config['admin_emails'])) {
        return ['success' => false, 'message' => '邮件配置不完整，请在授权系统后台配置'];
    }
    
    $smtpServer = $config['smtp_server'];
    $smtpPort = $config['smtp_port'];
    $smtpUsername = $config['smtp_username'];
    $smtpPassword = $config['smtp_password'];
    $fromName = $config['smtp_from_name'] ?: '系统通知';
    $fromEmail = $smtpUsername;
    $siteName = $config['site_name'] ?: '系统';
    
    // 解析管理员邮箱
    $adminEmails = array_map('trim', explode(',', $config['admin_emails']));
    $adminEmails = array_filter($adminEmails);
    if (empty($adminEmails)) {
        return ['success' => false, 'message' => '未配置接收邮箱，请在授权系统后台配置'];
    }
    
    // 邮件主题
    $subject = $siteName . ' - 新授权申请通知';
    
    // 邮件内容
    $message = '<html><body>';
    $message .= '<h2 style="color: #667eea;">' . $siteName . ' - 新授权申请</h2>';
    $message .= '<p style="font-size: 16px; color: #333;">您好，有新的授权申请，请及时处理！</p>';
    $message .= '<table border="1" cellpadding="10" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
    $message .= '<tr><th style="background: #667eea; color: white; text-align: left;">项目</th><th style="background: #667eea; color: white; text-align: left;">信息</th></tr>';
    $message .= '<tr><td>用户姓名</td><td>' . htmlspecialchars($name) . '</td></tr>';
    $message .= '<tr><td>手机号</td><td>' . htmlspecialchars($phone) . '</td></tr>';
    $message .= '<tr><td>微信号</td><td>' . htmlspecialchars($wechat ?: '未填写') . '</td></tr>';
    $message .= '<tr><td>IP地址</td><td>' . htmlspecialchars($ip) . '</td></tr>';
    $message .= '<tr><td>授权域名</td><td>' . htmlspecialchars($domain) . '</td></tr>';
    if ($auth_id) {
        $message .= '<tr><td>授权ID</td><td>' . htmlspecialchars($auth_id) . '</td></tr>';
    }
    $message .= '<tr><td>申请时间</td><td>' . date('Y-m-d H:i:s') . '</td></tr>';
    $message .= '</table>';
    $message .= '<p style="margin-top: 20px; color: #999; font-size: 12px;">此邮件由系统自动发送，请勿回复。</p>';
    $message .= '</body></html>';
    
    try {
        // 建立SSL连接
        logEmailError("正在连接SMTP服务器: ssl://$smtpServer:$smtpPort");
        $socket = fsockopen('ssl://' . $smtpServer, $smtpPort, $errno, $errstr, 30);
        if (!$socket) {
            logEmailError("无法连接到SMTP服务器: $errstr ($errno)");
            return ['success' => false, 'message' => "无法连接到SMTP服务器: $errstr ($errno)"];
        }
        logEmailError("已连接到SMTP服务器");
        
        // 设置超时
        stream_set_timeout($socket, 30);
        
        // 读取服务器响应
        $response = getResponse($socket);
        if (empty($response) || substr($response, 0, 3) != '220') {
            fclose($socket);
            logEmailError("SMTP服务器初始响应失败: $response");
            return ['success' => false, 'message' => "SMTP服务器初始响应失败: $response"];
        }
        
        // 发送EHLO命令
        fwrite($socket, "EHLO localhost\r\n");
        $response = getResponse($socket);
        if (empty($response) || substr($response, 0, 3) != '250') {
            fclose($socket);
            logEmailError("EHLO命令失败: $response");
            return ['success' => false, 'message' => "EHLO命令失败: $response"];
        }
        
        // 发送AUTH LOGIN命令
        fwrite($socket, "AUTH LOGIN\r\n");
        $response = getResponse($socket);
        if (empty($response) || substr($response, 0, 3) != '334') {
            fclose($socket);
            logEmailError("AUTH LOGIN命令失败: $response");
            return ['success' => false, 'message' => "AUTH LOGIN命令失败: $response"];
        }
        
        // 发送用户名
        fwrite($socket, base64_encode($smtpUsername) . "\r\n");
        $response = getResponse($socket);
        if (empty($response) || substr($response, 0, 3) != '334') {
            fclose($socket);
            logEmailError("用户名发送失败: $response");
            return ['success' => false, 'message' => "用户名发送失败: $response"];
        }
        
        // 发送密码
        fwrite($socket, base64_encode($smtpPassword) . "\r\n");
        $response = getResponse($socket);
        if (empty($response) || substr($response, 0, 3) != '235') {
            fclose($socket);
            logEmailError("SMTP认证失败: $response");
            return ['success' => false, 'message' => "SMTP认证失败: $response"];
        }
        logEmailError("SMTP认证成功");
        
        // 发送MAIL FROM命令
        fwrite($socket, "MAIL FROM: <$fromEmail>\r\n");
        $response = getResponse($socket);
        if (empty($response) || substr($response, 0, 3) != '250') {
            fclose($socket);
            logEmailError("MAIL FROM命令失败: $response");
            return ['success' => false, 'message' => "MAIL FROM命令失败: $response"];
        }
        
        // 为每个管理员邮箱发送RCPT TO命令
        foreach ($adminEmails as $adminEmail) {
            fwrite($socket, "RCPT TO: <$adminEmail>\r\n");
            $response = getResponse($socket);
            if (empty($response) || substr($response, 0, 3) != '250') {
                fclose($socket);
                logEmailError("RCPT TO命令失败: $response");
                return ['success' => false, 'message' => "RCPT TO命令失败: $response"];
            }
        }
        
        // 发送DATA命令
        fwrite($socket, "DATA\r\n");
        $response = getResponse($socket);
        if (empty($response) || substr($response, 0, 3) != '354') {
            fclose($socket);
            logEmailError("DATA命令失败: $response");
            return ['success' => false, 'message' => "DATA命令失败: $response"];
        }
        
        // 构建To头
        $toHeader = implode(', ', array_map(function($email) { return "<$email>"; }, $adminEmails));
        
        // 发送邮件内容
        $emailContent = "From: $fromName <$fromEmail>\r\n";
        $emailContent .= "To: $toHeader\r\n";
        $emailContent .= "Subject: $subject\r\n";
        $emailContent .= "MIME-Version: 1.0\r\n";
        $emailContent .= "Content-Type: text/html; charset=utf-8\r\n";
        $emailContent .= "Reply-To: $fromEmail\r\n\r\n";
        $emailContent .= $message . "\r\n.\r\n";
        
        // 发送邮件内容
        fwrite($socket, $emailContent);
        fflush($socket);
        
        // 读取响应
        $response = getResponse($socket);
        if (empty($response) || substr($response, 0, 3) != '250') {
            fclose($socket);
            logEmailError("邮件内容发送失败: $response");
            return ['success' => false, 'message' => "邮件内容发送失败: $response"];
        }
        
        // 发送QUIT命令
        fwrite($socket, "QUIT\r\n");
        $response = getResponse($socket);
        
        // 关闭连接
        fclose($socket);
        
        logEmailError("邮件发送成功！");
        return ['success' => true];
    } catch (Exception $e) {
        if (isset($socket) && is_resource($socket)) {
            fclose($socket);
        }
        logEmailError("邮件发送异常: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * 读取SMTP响应
 */
function getResponse($socket) {
    $response = '';
    $startTime = time();
    while (time() - $startTime < 30) {
        $line = fgets($socket, 512);
        if ($line === false) {
            break;
        }
        $response .= $line;
        if (substr($line, 3, 1) == ' ') {
            break;
        }
    }
    return $response;}
}
?>