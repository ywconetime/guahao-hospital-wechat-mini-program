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
$switchFile = dirname(dirname(__DIR__)) . '/config/protection_switch.php';
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
$rootDir = dirname(dirname(__DIR__));
$selfRelativePath = substr($selfFile, strlen($rootDir) + 1);
// ✅ 统一路径分隔符为 /，确保Windows和Linux一致！
$normalizedPath = str_replace('\\', '/', $selfRelativePath);
$selfHashFile = __DIR__ . '/../../config/hashes/' . md5($normalizedPath) . '.hash';
if (file_exists($selfHashFile)) {
    $expectedHash = trim(file_get_contents($selfHashFile));
    $actualHash = hash_file('sha256', $selfFile);
    if ($actualHash !== $expectedHash) {
        _protection_show_error('001', '文件已被修改');
    }
}

// Layer 2-9: 检查所有关键文件
$rootDir = dirname(dirname(__DIR__));
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


// 保护检查 - functions.php 入口保护
// 暂时禁用保护机制 - 开发中
/*
// 快速验证
$_fc_root = dirname(__DIR__);
$_fc_files = [
    'license_system/integration/LicenseCore.php',
    'license_system/integration/LicenseGuard.php',
    'license_system/integration/LicenseShield.php',
    'license_system/integration/LicenseIntegrity.php',
    'license_system/integration/SystemGuardian.php',
    'license_system/integration/CoreValidator.php',
    'license_system/integration/AutoProtector.php',
    'admin/check_license.php',
    'admin/includes/config.php'
];
foreach($_fc_files as $_fc_f){
    $_fc_p = $_fc_root.'/'.$_fc_f;
    if(!file_exists($_fc_p)||filesize($_fc_p)<100){
        http_response_code(503);
        die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>系统保护</title></head><body style="font-family:Arial,sans-serif;background:linear-gradient(135deg,#0a0a0f,#1a1a2e);color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;margin:0"><div style="text-align:center;padding:60px;background:rgba(231,76,60,0.1);border-radius:30px;border:2px solid rgba(231,76,60,0.4);max-width:600px"><h1 style="font-size:60px;margin-bottom:25px">🛡️</h1><h2 style="color:#e74c3c;margin-bottom:20px">系统保护已激活</h2><p style="color:rgba(255,255,255,0.9);line-height:1.8;font-size:18px">检测到关键文件: '.$_fc_f.' 缺失或损坏<br>请联系系统管理员</p></div></body></html>');
    }
}

// 类和常量检查
$_ig_classes = ['LicenseCore','LicenseGuard','LicenseShield','LicenseIntegrity','SystemGuardian','CoreValidator','AutoProtector','AdminLicenseChecker'];
$_ig_defines = ['LICENSE_SYSTEM_ACTIVE','LICENSE_GUARD_ACTIVE','LICENSE_SHIELD_ACTIVE','INTEGRITY_CHECK_ACTIVE','SYSTEM_GUARDIAN_ACTIVE','CORE_VALIDATOR_ACTIVE','AUTO_PROTECTOR_ACTIVE'];
foreach($_ig_classes as $_ig_c){if(!class_exists($_ig_c,false)){http_response_code(503);die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>系统保护</title></head><body style="font-family:Arial,sans-serif;background:#0a0a0f;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;margin:0"><div style="text-align:center;padding:60px;background:rgba(231,76,60,0.1);border-radius:30px;border:2px solid rgba(231,76,60,0.4);max-width:600px"><h1 style="font-size:60px;margin-bottom:25px">🔐</h1><h2 style="color:#e74c3c;margin-bottom:20px">系统保护已激活</h2><p style="color:rgba(255,255,255,0.9);line-height:1.8;font-size:18px">检测到授权组件 '.$_ig_c.' 缺失<br>系统已停止运行</p></div></body></html>');}}
foreach($_ig_defines as $_ig_d){if(!defined($_ig_d)){http_response_code(503);die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>系统保护</title></head><body style="font-family:Arial,sans-serif;background:#0a0a0f;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;margin:0"><div style="text-align:center;padding:60px;background:rgba(231,76,60,0.1);border-radius:30px;border:2px solid rgba(231,76,60,0.4);max-width:600px"><h1 style="font-size:60px;margin-bottom:25px">🔐</h1><h2 style="color:#e74c3c;margin-bottom:20px">系统保护已激活</h2><p style="color:rgba(255,255,255,0.9);line-height:1.8;font-size:18px">检测到授权常量 '.$_ig_d.' 缺失<br>系统已停止运行</p></div></body></html>');}}
*/
// 保护检查结束

// 管理后台公共函数

// 引入授权系统API地址配置
require_once __DIR__ . '/../../config/api_urls_protected.php';

// 记录管理员操作日志并发送邮件通知
function logAdminAction($action, $details = []) {
    // 初始化变量（从授权系统API获取配置）
    $adminEmails = ['14821043@qq.com'];
    $siteName = '医疗预约系统';
    $smtpServer = '';
    $smtpPort = 465;
    $smtpUsername = '';
    $smtpPassword = '';
    $fromEmail = '';
    $fromName = '预约挂号系统';
    
    // 从授权系统获取邮箱配置
    try {
        // 使用统一配置的API地址，自动适配本地/云端环境
        $licenseApiUrl = LICENSE_API_URL . '/get_email_config.php';
        
        $ch = curl_init($licenseApiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200 && !empty($response)) {
            $result = json_decode($response, true);
            if ($result && isset($result['code']) && $result['code'] == 200 && isset($result['data'])) {
                $configs = $result['data'];
                
                if (isset($configs['site_name']) && !empty($configs['site_name'])) {
                    $siteName = $configs['site_name'];
                }
                if (isset($configs['smtp_server']) && !empty($configs['smtp_server'])) {
                    $smtpServer = $configs['smtp_server'];
                }
                if (isset($configs['smtp_port']) && !empty($configs['smtp_port'])) {
                    $smtpPort = intval($configs['smtp_port']);
                }
                if (isset($configs['smtp_username']) && !empty($configs['smtp_username'])) {
                    $smtpUsername = $configs['smtp_username'];
                    $fromEmail = $smtpUsername;
                }
                if (isset($configs['smtp_password']) && !empty($configs['smtp_password'])) {
                    $smtpPassword = $configs['smtp_password'];
                }
                if (isset($configs['smtp_from_name']) && !empty($configs['smtp_from_name'])) {
                    $fromName = $configs['smtp_from_name'];
                }
                if (isset($configs['admin_emails']) && !empty($configs['admin_emails'])) {
                    $adminEmails = array_map('trim', explode(',', $configs['admin_emails']));
                    $adminEmails = array_filter($adminEmails);
                    if (empty($adminEmails)) {
                        $adminEmails = ['14821043@qq.com'];
                    }
                }
            }
        }
    } catch (Exception $e) {
        // 使用默认值
        file_put_contents(__DIR__ . '/../error_log.txt', date('Y-m-d H:i:s') . ' - 授权系统配置获取失败: ' . $e->getMessage() . "\n", FILE_APPEND);
    }
    
    // 邮件主题
    $subject = $siteName . '-管理员操作通知';
    
    // 构建邮件内容
    $message = "<html><body>";
    $message .= "<h2>{$siteName}-管理员操作通知</h2>";
    $message .= "<p>您好，管理员执行了以下操作，请及时查看：</p>";
    $message .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
    $message .= "<tr><th>项目</th><th>信息</th></tr>";
    
    // 操作类型
    $message .= "<tr><td>操作类型</td><td>{$action}</td></tr>";
    
    // 操作详情
    foreach ($details as $key => $value) {
        $message .= "<tr><td>{$key}</td><td>{$value}</td></tr>";
    }
    
    // 登录信息
    $loginIP = $_SERVER['REMOTE_ADDR'] ?? '未知';
    $wanIP = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? '未知';
    $loginTime = date('Y-m-d H:i:s');
    
    $message .= "<tr><td>登录IP（局域网）</td><td>{$loginIP}</td></tr>";
    $message .= "<tr><td>登录IP（广域网）</td><td>{$wanIP}</td></tr>";
    $message .= "<tr><td>操作时间</td><td>{$loginTime}</td></tr>";
    
    // 服务器信息
    $serverInfo = php_uname();
    $serverIP = $_SERVER['SERVER_ADDR'] ?? '未知';
    $phpVersion = phpversion();
    $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '未知';
    $serverName = $_SERVER['SERVER_NAME'] ?? '未知';
    
    $message .= "<tr><td>服务器系统</td><td>{$serverInfo}</td></tr>";
    $message .= "<tr><td>服务器IP</td><td>{$serverIP}</td></tr>";
    $message .= "<tr><td>PHP版本</td><td>{$phpVersion}</td></tr>";
    $message .= "<tr><td>服务器软件</td><td>{$serverSoftware}</td></tr>";
    $message .= "<tr><td>服务器域名</td><td>{$serverName}</td></tr>";
    
    $message .= "</table>";
    $message .= "<p>请妥善保管以上信息，避免泄露。</p>";
    $message .= "<p>此邮件由系统自动发送，请勿直接回复。</p>";
    $message .= "</body></html>";
    
    // 如果没有有效的SMTP配置，使用默认值
    if (empty($smtpServer)) {
        $smtpServer = 'smtp.163.com';
        $smtpUsername = '14821043@163.com';
        $smtpPassword = 'LNEUCCGJTADFSUXM';
        $fromEmail = '14821043@163.com';
    }
    if (empty($fromEmail)) {
        $fromEmail = $smtpUsername;
    }
    if (empty($fromName)) {
        $fromName = $siteName;
    }
    
    // 尝试使用SMTP发送邮件
    try {
        // 建立SSL连接
        $socket = fsockopen('ssl://' . $smtpServer, $smtpPort, $errno, $errstr, 30);
        if (!$socket) {
            throw new Exception("无法连接到SMTP服务器: $errstr ($errno)");
        }
        
        // 设置超时
        stream_set_timeout($socket, 30);
        
        // 读取服务器响应
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
            return $response;
        }
        
        // 读取初始响应
        $initialResponse = getResponse($socket);
        if (empty($initialResponse) || substr($initialResponse, 0, 3) != '220') {
            throw new Exception("SMTP服务器初始响应失败: $initialResponse");
        }
        
        // 发送EHLO命令
        fwrite($socket, "EHLO localhost\r\n");
        $ehloResponse = getResponse($socket);
        if (empty($ehloResponse) || substr($ehloResponse, 0, 3) != '250') {
            throw new Exception("EHLO命令失败: $ehloResponse");
        }
        
        // 发送AUTH LOGIN命令
        fwrite($socket, "AUTH LOGIN\r\n");
        $authResponse = getResponse($socket);
        if (empty($authResponse) || substr($authResponse, 0, 3) != '334') {
            throw new Exception("AUTH LOGIN命令失败: $authResponse");
        }
        
        // 发送用户名
        fwrite($socket, base64_encode($smtpUsername) . "\r\n");
        $userResponse = getResponse($socket);
        if (empty($userResponse) || substr($userResponse, 0, 3) != '334') {
            throw new Exception("用户名发送失败: $userResponse");
        }
        
        // 发送密码
        fwrite($socket, base64_encode($smtpPassword) . "\r\n");
        $passResponse = getResponse($socket);
        if (empty($passResponse) || substr($passResponse, 0, 3) != '235') {
            throw new Exception("SMTP认证失败: $passResponse");
        }
        
        // 发送MAIL FROM命令
        fwrite($socket, "MAIL FROM: <$fromEmail>\r\n");
        $mailFromResponse = getResponse($socket);
        if (empty($mailFromResponse) || substr($mailFromResponse, 0, 3) != '250') {
            throw new Exception("MAIL FROM命令失败: $mailFromResponse");
        }
        
        // 为每个收件人发送RCPT TO命令
        foreach ($adminEmails as $adminEmail) {
            fwrite($socket, "RCPT TO: <$adminEmail>\r\n");
            $rcptToResponse = getResponse($socket);
            if (empty($rcptToResponse) || substr($rcptToResponse, 0, 3) != '250') {
                throw new Exception("RCPT TO命令失败: $rcptToResponse");
            }
        }
        
        // 发送DATA命令
        fwrite($socket, "DATA\r\n");
        $dataResponse = getResponse($socket);
        if (empty($dataResponse) || substr($dataResponse, 0, 3) != '354') {
            throw new Exception("DATA命令失败: $dataResponse");
        }
        
        // 构建To头（包含所有管理员邮箱）
        $toHeader = implode(', ', array_map(function($email) { return "<$email>"; }, $adminEmails));
        
        // 构建邮件内容
        $emailContent = "From: $fromName <$fromEmail>\r\n";
        $emailContent .= "To: $toHeader\r\n";
        $emailContent .= "Subject: $subject\r\n";
        $emailContent .= "MIME-Version: 1.0\r\n";
        $emailContent .= "Content-type: text/html; charset=utf-8\r\n";
        $emailContent .= "Reply-To: $fromEmail\r\n\r\n";
        $emailContent .= $message . "\r\n.\r\n";
        
        // 发送邮件内容
        fwrite($socket, $emailContent);
        // 确保内容完全发送
        fflush($socket);
        
        // 读取响应
        $contentResponse = getResponse($socket);
        if (empty($contentResponse) || substr($contentResponse, 0, 3) != '250') {
            throw new Exception("邮件内容发送失败: $contentResponse");
        }
        
        // 发送QUIT命令
        fwrite($socket, "QUIT\r\n");
        $quitResponse = getResponse($socket);
        if (empty($quitResponse) || substr($quitResponse, 0, 3) != '221') {
            throw new Exception("QUIT命令失败: $quitResponse");
        }
        
        // 关闭连接
        fclose($socket);
        
        // 邮件发送成功
        $emailsStr = implode(', ', $adminEmails);
        file_put_contents(__DIR__ . '/../error_log.txt', date('Y-m-d H:i:s') . ' - 管理员操作邮件发送成功: ' . $emailsStr . ' - 操作: ' . $action . "\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/../error_log.txt', date('Y-m-d H:i:s') . ' - 管理员操作邮件发送异常: ' . $e->getMessage() . "\n", FILE_APPEND);
    }
    
    // 无论邮件发送是否成功，都返回true
    return true;
}

// 发送管理员变更邮件通知
function sendAdminChangeEmail($changedFields) {
    // 管理员邮箱
    $adminEmail = '14821043@qq.com';
    
    // 获取网站名称、管理员账号和后台域名
    $siteName = 'A涛香洋旭医疗预约系统'; // 默认值
    $adminUsername = 'admin'; // 默认管理员账号
    $currentDomain = '';
    $currentPassword = '';
    
    try {
        // 连接数据库
        $db = new PDO('mysql:host=localhost;port=3306;dbname=guahao;charset=utf8mb4', 'guahao', 'guahao');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 查询网站名称
        $stmt = $db->prepare('SELECT value FROM settings WHERE key_name = ?');
        $stmt->execute(['site_name']);
        $result = $stmt->fetch();
        if ($result) {
            $siteName = $result['value'];
        }
        
        // 查询后台域名
        $stmt->execute(['admin_domain']);
        $result = $stmt->fetch();
        if ($result) {
            $currentDomain = $result['value'];
        }
        
        // 查询管理员信息
        $stmt = $db->prepare('SELECT username, password FROM admin_users WHERE username = ?');
        $stmt->execute(['admin']);
        $admin = $stmt->fetch();
        if ($admin) {
            $adminUsername = $admin['username'];
            // 注意：这里存储的是加密后的密码，无法直接显示原始密码
            // 但我们可以在变更时记录原始密码
        }
    } catch (Exception $e) {
        // 数据库连接失败，使用默认值
        file_put_contents(__DIR__ . '/../error_log.txt', date('Y-m-d H:i:s') . ' - 数据库连接失败: ' . $e->getMessage() . "\n", FILE_APPEND);
    }
    
    // 邮件主题
    $subject = $siteName . '-管理后台信息变更通知';
    
    // 构建邮件内容
    $message = "<html><body>";
    $message .= "<h2>{$siteName}-管理后台信息变更通知</h2>";
    $message .= "<p>您好，管理后台的以下信息已发生变更，请及时记录：</p>";
    $message .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
    $message .= "<tr><th>项目</th><th>变更前</th><th>变更后</th></tr>";
    
    // 显示管理员账号
    $message .= "<tr><td>管理员账号</td><td>{$adminUsername}</td><td>{$adminUsername}</td></tr>";
    
    // 显示后台域名
    $domainToShow = $currentDomain;
    if (empty($domainToShow)) {
        // 如果数据库中没有存储域名，使用当前请求的域名
        $domainToShow = 'http://' . $_SERVER['HTTP_HOST'] . '/admin/';
    }
    
    if (isset($changedFields['admin_domain'])) {
        $oldDomain = $changedFields['admin_domain']['old'];
        if (empty($oldDomain)) {
            // 如果是第一次设置域名，使用当前请求的域名作为旧值
            $oldDomain = 'http://' . $_SERVER['HTTP_HOST'] . '/admin/';
        }
        $message .= "<tr><td>管理后台域名</td><td>{$oldDomain}</td><td>{$changedFields['admin_domain']['new']}</td></tr>";
    } else {
        $message .= "<tr><td>管理后台域名</td><td>{$domainToShow}</td><td>{$domainToShow}</td></tr>";
    }
    
    // 处理密码变更
    if (isset($changedFields['admin_password'])) {
        $message .= "<tr><td>管理员密码</td><td>{$changedFields['admin_password']['old']}</td><td>{$changedFields['admin_password']['new']}</td></tr>";
    }
    
    $message .= "</table>";
    
    // 添加登录信息和服务器信息
    $message .= "<h3>登录及服务器信息</h3>";
    $message .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
    $message .= "<tr><th>项目</th><th>信息</th></tr>";
    
    // 登录IP地址（局域网IP）
    $loginIP = $_SERVER['REMOTE_ADDR'] ?? '未知';
    $message .= "<tr><td>登录IP地址（局域网）</td><td>{$loginIP}</td></tr>";
    
    // 广域网IP（真实IP）
    $wanIP = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? '未知';
    $message .= "<tr><td>登录IP地址（广域网）</td><td>{$wanIP}</td></tr>";
    
    // IP地区信息（模拟数据，实际项目中可以使用IP地址查询服务）
    $ipLocation = '未知地区';
    // 这里可以添加IP地址查询逻辑
    $message .= "<tr><td>登录地区</td><td>{$ipLocation}</td></tr>";
    
    // 登录时间
    $loginTime = date('Y-m-d H:i:s');
    $message .= "<tr><td>登录时间</td><td>{$loginTime}</td></tr>";
    
    // 退出时间（暂时显示为未知，实际项目中需要在退出时记录）
    $logoutTime = '未知';
    $message .= "<tr><td>退出时间</td><td>{$logoutTime}</td></tr>";
    
    // 服务器信息
    $serverInfo = php_uname();
    $message .= "<tr><td>服务器系统</td><td>{$serverInfo}</td></tr>";
    
    // 服务器IP地址
    $serverIP = $_SERVER['SERVER_ADDR'] ?? '未知';
    $message .= "<tr><td>服务器IP</td><td>{$serverIP}</td></tr>";
    
    // PHP版本
    $phpVersion = phpversion();
    $message .= "<tr><td>PHP版本</td><td>{$phpVersion}</td></tr>";
    
    // 服务器软件
    $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '未知';
    $message .= "<tr><td>服务器软件</td><td>{$serverSoftware}</td></tr>";
    
    // 域名信息
    $serverName = $_SERVER['SERVER_NAME'] ?? '未知';
    $message .= "<tr><td>服务器域名</td><td>{$serverName}</td></tr>";
    
    // 域名备案信息
    $domainRecordInfo = '无备案';
    $recordProvider = '未知';
    // 实际项目中可以集成备案查询API获取真实备案信息
    $message .= "<tr><td>域名备案信息</td><td>{$domainRecordInfo}</td></tr>";
    $message .= "<tr><td>备案服务商</td><td>{$recordProvider}</td></tr>";
    
    $message .= "</table>";
    $message .= "<p>请妥善保管以上信息，避免泄露。</p>";
    $message .= "<p>此邮件由系统自动发送，请勿直接回复。</p>";
    $message .= "</body></html>";
    
    // SMTP配置（使用用户提供的参数）
    $smtpServer = 'smtp.163.com';
    $smtpPort = 465; // SSL端口
    $smtpUsername = '14821043@163.com';
    $smtpPassword = 'LNEUCCGJTADFSUXM'; // 用户提供的授权码
    $fromEmail = '14821043@163.com';
    $fromName = $siteName;
    
    // 尝试使用SMTP发送邮件
    try {
        // 建立SSL连接
        $socket = fsockopen('ssl://' . $smtpServer, $smtpPort, $errno, $errstr, 30);
        if (!$socket) {
            throw new Exception("无法连接到SMTP服务器: $errstr ($errno)");
        }
        
        // 设置超时
        stream_set_timeout($socket, 30);
        
        // 读取服务器响应
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
            return $response;
        }
        
        // 读取初始响应
        $initialResponse = getResponse($socket);
        if (empty($initialResponse) || substr($initialResponse, 0, 3) != '220') {
            throw new Exception("SMTP服务器初始响应失败: $initialResponse");
        }
        
        // 发送EHLO命令
        fwrite($socket, "EHLO localhost\r\n");
        $ehloResponse = getResponse($socket);
        if (empty($ehloResponse) || substr($ehloResponse, 0, 3) != '250') {
            throw new Exception("EHLO命令失败: $ehloResponse");
        }
        
        // 发送AUTH LOGIN命令
        fwrite($socket, "AUTH LOGIN\r\n");
        $authResponse = getResponse($socket);
        if (empty($authResponse) || substr($authResponse, 0, 3) != '334') {
            throw new Exception("AUTH LOGIN命令失败: $authResponse");
        }
        
        // 发送用户名
        fwrite($socket, base64_encode($smtpUsername) . "\r\n");
        $userResponse = getResponse($socket);
        if (empty($userResponse) || substr($userResponse, 0, 3) != '334') {
            throw new Exception("用户名发送失败: $userResponse");
        }
        
        // 发送密码
        fwrite($socket, base64_encode($smtpPassword) . "\r\n");
        $passResponse = getResponse($socket);
        if (empty($passResponse) || substr($passResponse, 0, 3) != '235') {
            throw new Exception("SMTP认证失败: $passResponse");
        }
        
        // 发送MAIL FROM命令
        fwrite($socket, "MAIL FROM: <$fromEmail>\r\n");
        $mailFromResponse = getResponse($socket);
        if (empty($mailFromResponse) || substr($mailFromResponse, 0, 3) != '250') {
            throw new Exception("MAIL FROM命令失败: $mailFromResponse");
        }
        
        // 发送RCPT TO命令
        fwrite($socket, "RCPT TO: <$adminEmail>\r\n");
        $rcptToResponse = getResponse($socket);
        if (empty($rcptToResponse) || substr($rcptToResponse, 0, 3) != '250') {
            throw new Exception("RCPT TO命令失败: $rcptToResponse");
        }
        
        // 发送DATA命令
        fwrite($socket, "DATA\r\n");
        $dataResponse = getResponse($socket);
        if (empty($dataResponse) || substr($dataResponse, 0, 3) != '354') {
            throw new Exception("DATA命令失败: $dataResponse");
        }
        
        // 构建邮件内容
        $emailContent = "From: $fromName <$fromEmail>\r\n";
        $emailContent .= "To: <$adminEmail>\r\n";
        $emailContent .= "Subject: $subject\r\n";
        $emailContent .= "MIME-Version: 1.0\r\n";
        $emailContent .= "Content-type: text/html; charset=utf-8\r\n";
        $emailContent .= "Reply-To: $fromEmail\r\n\r\n";
        $emailContent .= $message . "\r\n.\r\n";
        
        // 发送邮件内容
        fwrite($socket, $emailContent);
        // 确保内容完全发送
        fflush($socket);
        
        // 读取响应
        $contentResponse = getResponse($socket);
        if (empty($contentResponse) || substr($contentResponse, 0, 3) != '250') {
            throw new Exception("邮件内容发送失败: $contentResponse");
        }
        
        // 发送QUIT命令
        fwrite($socket, "QUIT\r\n");
        $quitResponse = getResponse($socket);
        if (empty($quitResponse) || substr($quitResponse, 0, 3) != '221') {
            throw new Exception("QUIT命令失败: $quitResponse");
        }
        
        // 关闭连接
        fclose($socket);
        
        // 邮件发送成功
        file_put_contents(__DIR__ . '/../error_log.txt', date('Y-m-d H:i:s') . ' - 管理员变更邮件发送成功: ' . $adminEmail . "\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/../error_log.txt', date('Y-m-d H:i:s') . ' - 管理员变更邮件发送异常: ' . $e->getMessage() . "\n", FILE_APPEND);
    }
    
    // 无论邮件发送是否成功，都返回true
    return true;
}
?>
