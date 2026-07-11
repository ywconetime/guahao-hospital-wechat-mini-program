<?php
/**
 * 发送购买交易邮件提醒API（简化版）
 */

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$type = $data['type'] ?? '';
$orderNo = $data['order_no'] ?? '';
$planName = $data['plan_name'] ?? '';
$amount = $data['amount'] ?? 0;
$userName = $data['user_name'] ?? '';
$userPhone = $data['user_phone'] ?? '';
$userDomain = $data['user_domain'] ?? '';
$tradeNo = $data['trade_no'] ?? '';
$errorMsg = $data['error_msg'] ?? '';
$authId = $data['auth_id'] ?? 0;
$clientIP = $data['client_ip'] ?? '';
$serverIP = $data['server_ip'] ?? '';
$authIPv4 = $data['auth_ipv4'] ?? '';
$authIPv6 = $data['auth_ipv6'] ?? '';
$authPublicIP = $data['auth_public_ip'] ?? '';
$authDuration = $data['auth_duration'] ?? 0;

if (empty($type) || empty($orderNo)) {
    echo json_encode([
        'code' => 400,
        'message' => '缺少必要参数'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $result = sendSimpleEmail($type, $orderNo, $planName, $amount, $userName, $userPhone, $userDomain, $tradeNo, $errorMsg, $authId, $clientIP, $serverIP, $authIPv4, $authIPv6, $authPublicIP, $authDuration);
    
    if ($result['success']) {
        echo json_encode([
            'code' => 200,
            'message' => '邮件发送成功'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'code' => 500,
            'message' => '邮件发送失败: ' . $result['message']
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '邮件发送异常: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

function sendSimpleEmail($type, $orderNo, $planName, $amount, $userName, $userPhone, $userDomain, $tradeNo, $errorMsg, $authId, $clientIP, $serverIP, $authIPv4, $authIPv6, $authPublicIP, $authDuration)
{
    // 从授权系统获取邮件配置
    $config = getEmailConfig();
    
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

    $subject = '';
    $message = '';
    
    if ($type === 'purchase') {
        $subject = $siteName . ' - 用户点击购买套餐';
        $message = "<html><body>";
        $message .= "<h2 style='color: #007bff;'>" . $siteName . " - 用户点击购买套餐</h2>";
        $message .= "<p style='font-size: 16px; color: #333;'>有用户点击了购买套餐！</p>";
        $message .= "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
        $message .= "<tr><th style='background: #007bff; color: white; text-align: left; width: 150px;'>项目</th><th style='background: #007bff; color: white; text-align: left;'>信息</th></tr>";
        $message .= "<tr><td>用户姓名</td><td>" . htmlspecialchars($userName) . "</td></tr>";
        $message .= "<tr><td>手机号</td><td>" . htmlspecialchars($userPhone) . "</td></tr>";
        $message .= "<tr><td>授权域名</td><td>" . htmlspecialchars($userDomain) . "</td></tr>";
        $message .= "<tr><td>授权ID</td><td>" . htmlspecialchars($authId) . "</td></tr>";
        $message .= "<tr><td>套餐名称</td><td>" . htmlspecialchars($planName) . "</td></tr>";
        $message .= "<tr><td>授权时长</td><td>" . htmlspecialchars($authDuration) . " 天</td></tr>";
        $message .= "<tr><td>订单金额</td><td style='color: #d63384; font-weight: 600;'>¥" . number_format($amount, 2) . "</td></tr>";
        $message .= "<tr><td>订单号</td><td>" . htmlspecialchars($orderNo) . "</td></tr>";
        $message .= "<tr><td>用户设备IP</td><td>" . htmlspecialchars($clientIP) . "</td></tr>";
        $message .= "<tr><td>服务器IP</td><td>" . htmlspecialchars($serverIP) . "</td></tr>";
        if ($authIPv4) {
            $message .= "<tr><td>授权IPv4</td><td>" . htmlspecialchars($authIPv4) . "</td></tr>";
        }
        if ($authIPv6) {
            $message .= "<tr><td>授权IPv6</td><td>" . htmlspecialchars($authIPv6) . "</td></tr>";
        }
        if ($authPublicIP) {
            $message .= "<tr><td>授权公网IP</td><td>" . htmlspecialchars($authPublicIP) . "</td></tr>";
        }
        $message .= "<tr><td>提交时间</td><td>" . date('Y-m-d H:i:s') . "</td></tr>";
        $message .= "</table>";
        $message .= "<p style='margin-top: 20px; color: #666; font-size: 14px;'>请留意用户是否完成支付！</p>";
        $message .= "</body></html>";
    } elseif ($type === 'success') {
        $subject = $siteName . ' - 交易成功提醒';
        $message = "<html><body>";
        $message .= "<h2 style='color: #28a745;'>" . $siteName . " - 交易成功！</h2>";
        $message .= "<p style='font-size: 16px; color: #333;'>用户支付成功，已成功续费授权！</p>";
        $message .= "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
        $message .= "<tr><th style='background: #28a745; color: white; text-align: left; width: 150px;'>项目</th><th style='background: #28a745; color: white; text-align: left;'>信息</th></tr>";
        $message .= "<tr><td>用户姓名</td><td>" . htmlspecialchars($userName) . "</td></tr>";
        $message .= "<tr><td>手机号</td><td>" . htmlspecialchars($userPhone) . "</td></tr>";
        $message .= "<tr><td>授权域名</td><td>" . htmlspecialchars($userDomain) . "</td></tr>";
        $message .= "<tr><td>授权ID</td><td>" . htmlspecialchars($authId) . "</td></tr>";
        $message .= "<tr><td>套餐名称</td><td>" . htmlspecialchars($planName) . "</td></tr>";
        $message .= "<tr><td>授权时长</td><td>" . htmlspecialchars($authDuration) . " 天</td></tr>";
        $message .= "<tr><td>订单金额</td><td style='color: #d63384; font-weight: 600;'>¥" . number_format($amount, 2) . "</td></tr>";
        $message .= "<tr><td>订单号</td><td>" . htmlspecialchars($orderNo) . "</td></tr>";
        if ($tradeNo) {
            $message .= "<tr><td>支付宝交易号</td><td>" . htmlspecialchars($tradeNo) . "</td></tr>";
        }
        $message .= "<tr><td>用户设备IP</td><td>" . htmlspecialchars($clientIP) . "</td></tr>";
        $message .= "<tr><td>服务器IP</td><td>" . htmlspecialchars($serverIP) . "</td></tr>";
        if ($authIPv4) {
            $message .= "<tr><td>授权IPv4</td><td>" . htmlspecialchars($authIPv4) . "</td></tr>";
        }
        if ($authIPv6) {
            $message .= "<tr><td>授权IPv6</td><td>" . htmlspecialchars($authIPv6) . "</td></tr>";
        }
        if ($authPublicIP) {
            $message .= "<tr><td>授权公网IP</td><td>" . htmlspecialchars($authPublicIP) . "</td></tr>";
        }
        $message .= "<tr><td>交易时间</td><td>" . date('Y-m-d H:i:s') . "</td></tr>";
        $message .= "</table>";
        $message .= "<p style='margin-top: 20px; color: #28a745; font-size: 14px; font-weight: 600;'>✓ 已成功续费授权！</p>";
        $message .= "</body></html>";
    } elseif ($type === 'fail') {
        $subject = $siteName . ' - 交易失败提醒';
        $message = "<html><body>";
        $message .= "<h2 style='color: #dc3545;'>" . $siteName . " - 交易失败</h2>";
        $message .= "<p style='font-size: 16px; color: #333;'>用户支付失败或未完成支付！</p>";
        $message .= "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
        $message .= "<tr><th style='background: #dc3545; color: white; text-align: left; width: 150px;'>项目</th><th style='background: #dc3545; color: white; text-align: left;'>信息</th></tr>";
        $message .= "<tr><td>用户姓名</td><td>" . htmlspecialchars($userName) . "</td></tr>";
        $message .= "<tr><td>手机号</td><td>" . htmlspecialchars($userPhone) . "</td></tr>";
        $message .= "<tr><td>授权域名</td><td>" . htmlspecialchars($userDomain) . "</td></tr>";
        $message .= "<tr><td>授权ID</td><td>" . htmlspecialchars($authId) . "</td></tr>";
        $message .= "<tr><td>套餐名称</td><td>" . htmlspecialchars($planName) . "</td></tr>";
        $message .= "<tr><td>授权时长</td><td>" . htmlspecialchars($authDuration) . " 天</td></tr>";
        $message .= "<tr><td>订单金额</td><td>¥" . number_format($amount, 2) . "</td></tr>";
        $message .= "<tr><td>订单号</td><td>" . htmlspecialchars($orderNo) . "</td></tr>";
        if ($tradeNo) {
            $message .= "<tr><td>支付宝交易号</td><td>" . htmlspecialchars($tradeNo) . "</td></tr>";
        }
        if ($errorMsg) {
            $message .= "<tr><td>失败原因</td><td style='color: #dc3545; font-weight: 600;'>" . htmlspecialchars($errorMsg) . "</td></tr>";
        }
        $message .= "<tr><td>用户设备IP</td><td>" . htmlspecialchars($clientIP) . "</td></tr>";
        $message .= "<tr><td>服务器IP</td><td>" . htmlspecialchars($serverIP) . "</td></tr>";
        if ($authIPv4) {
            $message .= "<tr><td>授权IPv4</td><td>" . htmlspecialchars($authIPv4) . "</td></tr>";
        }
        if ($authIPv6) {
            $message .= "<tr><td>授权IPv6</td><td>" . htmlspecialchars($authIPv6) . "</td></tr>";
        }
        if ($authPublicIP) {
            $message .= "<tr><td>授权公网IP</td><td>" . htmlspecialchars($authPublicIP) . "</td></tr>";
        }
        $message .= "<tr><td>时间</td><td>" . date('Y-m-d H:i:s') . "</td></tr>";
        $message .= "</table>";
        $message .= "<p style='margin-top: 20px; color: #dc3545; font-size: 14px;'>可能原因：用户未扫码支付、扫码支付失败、网络超时或其他原因</p>";
        $message .= "</body></html>";
    }
    
    if (empty($subject) || empty($message)) {
        return ['success' => false, 'message' => '邮件内容错误'];
    }
    
    try {
        file_put_contents(__DIR__ . '/email_error_log.txt', date('Y-m-d H:i:s') . " - 开始发送邮件，SMTP: $smtpServer:$smtpPort\n", FILE_APPEND);
        
        $socket = fsockopen('ssl://' . $smtpServer, $smtpPort, $errno, $errstr, 30);
        if (!$socket) {
            file_put_contents(__DIR__ . '/email_error_log.txt', date('Y-m-d H:i:s') . " - 无法连接到SMTP服务器: $errstr ($errno)\n", FILE_APPEND);
            return ['success' => false, 'message' => "无法连接到SMTP服务器: $errstr ($errno)"];
        }
        
        file_put_contents(__DIR__ . '/email_error_log.txt', date('Y-m-d H:i:s') . " - 已连接到SMTP服务器\n", FILE_APPEND);
        
        stream_set_timeout($socket, 30);
        
        $response = getSmtpResponse($socket);
        if (empty($response) || substr($response, 0, 3) != '220') {
            fclose($socket);
            file_put_contents(__DIR__ . '/email_error_log.txt', date('Y-m-d H:i:s') . " - SMTP服务器初始响应失败: $response\n", FILE_APPEND);
            return ['success' => false, 'message' => "SMTP服务器初始响应失败: $response"];
        }
        
        fwrite($socket, "EHLO localhost\r\n");
        $response = getSmtpResponse($socket);
        if (empty($response) || substr($response, 0, 3) != '250') {
            fclose($socket);
            return ['success' => false, 'message' => "EHLO命令失败: $response"];
        }
        
        fwrite($socket, "AUTH LOGIN\r\n");
        $response = getSmtpResponse($socket);
        if (empty($response) || substr($response, 0, 3) != '334') {
            fclose($socket);
            return ['success' => false, 'message' => "AUTH LOGIN命令失败: $response"];
        }
        
        fwrite($socket, base64_encode($smtpUsername) . "\r\n");
        $response = getSmtpResponse($socket);
        if (empty($response) || substr($response, 0, 3) != '334') {
            fclose($socket);
            return ['success' => false, 'message' => "用户名发送失败: $response"];
        }
        
        fwrite($socket, base64_encode($smtpPassword) . "\r\n");
        $response = getSmtpResponse($socket);
        if (empty($response) || substr($response, 0, 3) != '235') {
            fclose($socket);
            return ['success' => false, 'message' => "SMTP认证失败: $response"];
        }
        
        fwrite($socket, "MAIL FROM: <$fromEmail>\r\n");
        $response = getSmtpResponse($socket);
        if (empty($response) || substr($response, 0, 3) != '250') {
            fclose($socket);
            return ['success' => false, 'message' => "MAIL FROM命令失败: $response"];
        }
        
        foreach ($adminEmails as $adminEmail) {
            fwrite($socket, "RCPT TO: <$adminEmail>\r\n");
            $response = getSmtpResponse($socket);
            if (empty($response) || substr($response, 0, 3) != '250') {
                fclose($socket);
                return ['success' => false, 'message' => "RCPT TO命令失败: $response"];
            }
        }
        
        fwrite($socket, "DATA\r\n");
        $response = getSmtpResponse($socket);
        if (empty($response) || substr($response, 0, 3) != '354') {
            fclose($socket);
            return ['success' => false, 'message' => "DATA命令失败: $response"];
        }
        
        $toHeader = implode(', ', array_map(function($email) { return "<$email>"; }, $adminEmails));
        
        $mailContent = "From: $fromName <$fromEmail>\r\n";
        $mailContent .= "To: $toHeader\r\n";
        $mailContent .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $mailContent .= "MIME-Version: 1.0\r\n";
        $mailContent .= "Content-Type: text/html; charset=utf-8\r\n";
        $mailContent .= "Reply-To: $fromEmail\r\n\r\n";
        $mailContent .= $message . "\r\n.\r\n";
        
        fwrite($socket, $mailContent);
        fflush($socket);
        
        $response = getSmtpResponse($socket);
        if (empty($response) || substr($response, 0, 3) != '250') {
            fclose($socket);
            return ['success' => false, 'message' => "邮件内容发送失败: $response"];
        }
        
        fwrite($socket, "QUIT\r\n");
        getSmtpResponse($socket);
        fclose($socket);
        
        file_put_contents(__DIR__ . '/email_error_log.txt', date('Y-m-d H:i:s') . " - 邮件发送成功！\n", FILE_APPEND);
        
        return ['success' => true];
    } catch (Exception $e) {
        if (isset($socket) && is_resource($socket)) {
            fclose($socket);
        }
        file_put_contents(__DIR__ . '/email_error_log.txt', date('Y-m-d H:i:s') . " - 发送邮件异常: " . $e->getMessage() . "\n", FILE_APPEND);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getSmtpResponse($socket)
{
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
