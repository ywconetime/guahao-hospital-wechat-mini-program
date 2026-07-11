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
 * 授权处理脚本
 * 调用授权系统 API 申请免费授权
 */

// 使用统一的API地址配置（支持本地/云端自动切换）
$protectedFile = __DIR__ . '/../config/api_urls_protected.php';
if (!file_exists($protectedFile)) {
    // 文件不存在，显示保护页面
    $errorPage = '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>系统保护已激活</title><style>body{font-family:Microsoft YaHei;background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}.error-container{text-align:center;padding:60px;background:rgba(231,76,60,0.1);border-radius:30px;border:2px solid rgba(231,76,60,0.4);max-width:600px;box-shadow:0 20px 60px rgba(0,0,0,0.5);}.error-icon{font-size:80px;margin-bottom:20px;}.error-title{color:#e74c3c;font-size:28px;margin-bottom:15px;}.error-message{color:rgba(255,255,255,0.8);line-height:1.8;font-size:16px;}.layer-info{background:rgba(0,0,0,0.3);padding:10px 20px;border-radius:8px;margin-top:20px;font-family:monospace;font-size:14px;color:#f39c12;}</style></head><body><div class="error-container"><div class="error-icon">🛡️</div><h1 class="error-title">系统保护已激活</h1><p class="error-message">检测到配置文件异常，系统已停止运行以保护数据安全。</p><p class="error-message">请联系系统管理员进行修复。</p><div class="layer-info">[LAYER_000] 保护系统文件缺失</div></div></body></html>';
    echo $errorPage;
    exit(1);
}
require_once $protectedFile;

// 调试日志
$domain = $_SERVER['HTTP_HOST'] ?? '';
error_log("[授权提交] 当前域名: {$domain}");
error_log("[授权提交] 授权API地址: " . LICENSE_API_URL);

class LicenseAuthorizer
{
    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showError('请求方法错误');
        }
        
        // 调试日志
        error_log("收到授权请求: " . print_r($_POST, true));
        
        // 获取表单数据
        $name = $_POST['name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $wechat = $_POST['wechat'] ?? '';
        // ✅ 获取小程序后台服务器自己的公网IP（不是访客的IP
        $ip = $_POST['ip'] ?? $this->getServerPublicIP();
        
        // 终极三级兜底方案：确保 domain 永远是字符串，绝对不为 null
        $domain = isset($_POST['domain']) ? trim($_POST['domain']) : '';
        if ($domain === '') {
            $domain = isset($_SERVER['HTTP_HOST']) ? trim($_SERVER['HTTP_HOST']) : '';
        }
        if ($domain === '') {
            $domain = isset($_SERVER['SERVER_NAME']) ? trim($_SERVER['SERVER_NAME']) : '';
        }
        if ($domain === '') {
            $domain = 'unknown.host';
        }
        
        // 验证数据
        if (empty($name)) {
            $this->showError('请输入姓名');
        }
        
        if (empty($phone) || !preg_match('/^1[3-9]\d{9}$/', $phone)) {
            $this->showError('请输入正确的手机号');
        }
        
        // 调用授权系统 API 申请授权
        $result = $this->applyLicense($name, $phone, $wechat, $ip, $domain);
        
        if ($result['success']) {
            // 授权成功，保存授权码到 session
            session_start();
            if (isset($result['data']['license_code'])) {
                $_SESSION['license_code'] = $result['data']['license_code'];
            }
            
            // 跳转到登录页面
            header('Location: /admin/login.php');
            exit;
        } else {
            // 处理授权过期(401)和次数用完(402)的情况，显示订阅套餐页面
            if (isset($result['code']) && ($result['code'] == 401 || $result['code'] == 402)) {
                // 尝试从返回数据中获取 auth_id，如果没有则通过 API 获取
                $authId = null;
                if (isset($result['data']) && is_array($result['data'])) {
                    $authId = $result['data']['id'] ?? $result['data']['auth_id'] ?? null;
                }
                
                // 如果没有，通过 IP 和域名获取授权记录
                if (!$authId) {
                    $authRecord = $this->getAuthByIpDomain($ip, $domain);
                    if ($authRecord && isset($authRecord['id'])) {
                        $authId = $authRecord['id'];
                    }
                }
                
                $this->showSubscriptionPage($result['message'], $result['code'] == 401, $result['code'] == 402, $authId);
            } else {
                $this->showError($result['message']);
            }
        }
    }

    private function applyLicense($name, $phone, $wechat, $ip, $domain) {
        $url = LICENSE_API_URL . '/free_verify.php';
        
        $postData = [
            'action' => 'apply',
            'name' => $name,
            'phone' => $phone,
            'wechat' => $wechat,
            'ip' => $ip,
            'domain' => $domain
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
        
        // 处理授权过期(401)和次数用完(402)的情况
        if ($result['code'] == 401 || $result['code'] == 402) {
            return [
                'success' => false, 
                'message' => $result['message'] ?? '授权失败',
                'code' => $result['code'],
                'data' => $result['data'] ?? null
            ];
        }
        
        return ['success' => false, 'message' => $result['message'] ?? '授权失败'];
    }

    private function getClientIP() {
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
     * ✅ 获取小程序后台服务器自己的公网IP
     * 优先顺序（关键：先从外部服务获取，避免获取访客IP）：
     * 1. 从缓存文件读取（7天内有效）→ 最快
     * 2. 调用外部服务获取（超时1秒）→ 最准确
     * 3. SERVER_ADDR（服务器绑定的IP）→ 如果不在反向代理后面
     * 4. HTTP代理头（仅当确定在反向代理后面时使用）
     * 5. fallback到访客IP（确保系统不卡顿）
     */
    private function getServerPublicIP() {
        static $cachedIp = null;
        
        if ($cachedIp !== null) {
            return $cachedIp;
        }
        
        $ip = null;
        
        // 方法1：从缓存文件读取（避免频繁调用外部服务，最快）
        $cacheFile = dirname(__DIR__) . '/config/server_ip_cache.txt';
        if (!$ip && file_exists($cacheFile)) {
            $cacheTime = filemtime($cacheFile);
            $cacheAge = time() - $cacheTime;
            // 7天内的缓存直接使用
            if ($cacheAge < 604800) {
                $cachedValue = trim(file_get_contents($cacheFile));
                if (filter_var($cachedValue, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !$this->isPrivateIP($cachedValue)) {
                    $ip = $cachedValue;
                }
            }
        }
        
        // 方法2：使用外部服务获取（最准确，只尝试第一个，超时1秒，失败就跳过）
        // ⚠️ 关键：这是获取服务器自己公网IP最可靠的方式
        if (!$ip) {
            $services = [
                'https://api.ipify.org',
                'https://ipv4.icanhazip.com',
                'https://v4.ident.me',
            ];
            
            foreach ($services as $service) {
                try {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $service);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $result = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode == 200 && !empty($result)) {
                        $result = trim($result);
                        if (filter_var($result, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !$this->isPrivateIP($result)) {
                            $ip = $result;
                            // 写入缓存
                            @file_put_contents($cacheFile, $ip);
                            break;
                        }
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }
        
        // 方法3：fallback - 使用SERVER_ADDR（服务器绑定的IP）
        if (!$ip) {
            $serverAddr = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
            if (filter_var($serverAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !$this->isPrivateIP($serverAddr)) {
                $ip = $serverAddr;
            }
        }
        
        // 方法4：HTTP代理头（最后尝试，因为可能包含访客IP）
        if (!$ip) {
            $proxyHeaders = [
                'HTTP_X_REAL_IP',
                'HTTP_CLIENT_IP',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_FORWARDED',
                'HTTP_X_CLUSTER_CLIENT_IP'
            ];
            
            foreach ($proxyHeaders as $header) {
                if (isset($_SERVER[$header])) {
                    $headerValue = $_SERVER[$header];
                    if (strpos($headerValue, ',') !== false) {
                        $headerValue = trim(explode(',', $headerValue)[0]);
                    }
                    if (filter_var($headerValue, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        if (!$this->isPrivateIP($headerValue)) {
                            $ip = $headerValue;
                            break;
                        }
                    }
                }
            }
        }
        
        // 方法5：最后的fallback - 使用访客IP（确保系统不卡）
        if (!$ip) {
            $ip = $this->getClientIP();
        }
        
        $cachedIp = $ip;
        return $ip;
    }

    /**
     * 检查是否为私有IP
     */
    private function isPrivateIP($ip) {
        if (strpos($ip, '10.') === 0) return true;
        if (strpos($ip, '172.16.') === 0) return true;
        if (strpos($ip, '172.17.') === 0) return true;
        if (strpos($ip, '172.18.') === 0) return true;
        if (strpos($ip, '172.19.') === 0) return true;
        if (strpos($ip, '172.20.') === 0) return true;
        if (strpos($ip, '172.21.') === 0) return true;
        if (strpos($ip, '172.22.') === 0) return true;
        if (strpos($ip, '172.23.') === 0) return true;
        if (strpos($ip, '172.24.') === 0) return true;
        if (strpos($ip, '172.25.') === 0) return true;
        if (strpos($ip, '172.26.') === 0) return true;
        if (strpos($ip, '172.27.') === 0) return true;
        if (strpos($ip, '172.28.') === 0) return true;
        if (strpos($ip, '172.29.') === 0) return true;
        if (strpos($ip, '172.30.') === 0) return true;
        if (strpos($ip, '172.31.') === 0) return true;
        if (strpos($ip, '192.168.') === 0) return true;
        if (strpos($ip, '127.') === 0) return true;
        if ($ip === '::1') return true;
        return false;
    }

    private function showSubscriptionPage($message, $isExpired = false, $isCountExhausted = false, $authId = null) {
        // 从授权系统API获取套餐数据
        $plans = $this->getPlansFromApi();
        
        // 获取支付模式
        $paymentMode = $this->getPaymentModeFromApi();
        
        $paymentUrl = '/license_system/pay.php';
        
        if (defined('LICENSE_API_URL')) {
            $parsedUrl = parse_url(LICENSE_API_URL);
            $host = $parsedUrl['host'] ?? '';
            $path = $parsedUrl['path'] ?? '';
            
            $basePath = dirname($path);
            if ($basePath === '/' || $basePath === '.') {
                $basePath = '/license_system';
            }
            
            $paymentUrl = 'http://' . $host . $basePath . '/pay.php';
        }
        
        ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>订阅授权套餐</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        
        .container { background: white; padding: 0; border-radius: 16px; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.2); max-width: 850px; width: 100%; overflow: hidden; }
        
        .header-decoration {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .header-decoration::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            0%, 100% { transform: translate(-30%, -30%); }
            50% { transform: translate(30%, 30%); }
        }
        
        .warning-icon { 
            font-size: 48px; 
            margin-bottom: 10px;
            display: inline-block;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .header-decoration h2 { 
            color: white; 
            margin-bottom: 8px; 
            font-size: 24px; 
            font-weight: 700;
        }
        
        .header-decoration p { 
            color: rgba(255,255,255,0.95); 
            margin-bottom: 0; 
            font-size: 14px;
        }
        
        .content-wrapper {
            padding: 20px;
        }
        
        .message-box {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .message-box p {
            color: #856404;
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .plans-container { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 15px; 
            margin-bottom: 20px; 
        }
        
        .plan-card { 
            background: linear-gradient(145deg, #ffffff 0%, #fafbff 100%); 
            border: 2px solid rgba(102, 126, 234, 0.15); 
            border-radius: 12px; 
            padding: 15px; 
            text-align: center; 
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55); 
            position: relative; 
            cursor: pointer;
        }
        
        .plan-card:hover { 
            transform: translateY(-5px) scale(1.01); 
            border-color: #667eea; 
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.25);
        }
        
        .plan-card.popular { 
            border-color: #667eea; 
            border-width: 3px;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.22);
        }
        
        .plan-card.popular::before { 
            content: '⭐ 热门'; 
            position: absolute; 
            top: -10px; 
            left: 50%; 
            transform: translateX(-50%); 
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); 
            color: white; 
            padding: 4px 12px; 
            border-radius: 15px; 
            font-size: 11px; 
            font-weight: 700; 
            box-shadow: 0 3px 10px rgba(240, 147, 251, 0.35);
        }
        
        .plan-name { 
            font-size: 18px; 
            font-weight: 800; 
            color: #1a1a2e; 
            margin-bottom: 6px; 
        }
        
        .plan-desc { 
            color: #7f8c8d; 
            font-size: 12px; 
            margin-bottom: 10px; 
            min-height: 30px; 
            line-height: 1.4;
        }
        
        .plan-price { 
            font-size: 28px; 
            font-weight: 800; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 5px; 
        }
        
        .plan-price small { 
            font-size: 14px; 
            font-weight: 500; 
        }
        
        .plan-duration { 
            color: #95a5a6; 
            font-size: 12px; 
            margin-bottom: 12px; 
            font-weight: 500;
        }
        
        .plan-btn { 
            display: block; 
            width: 100%; 
            padding: 11px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            border: none; 
            border-radius: 10px; 
            font-size: 14px; 
            font-weight: 700; 
            cursor: pointer; 
            text-decoration: none; 
            transition: all 0.3s; 
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .plan-btn:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }
        
        .footer-link {
            text-align: center;
            color: #666;
            font-size: 13px;
        }
        
        .footer-link a {
            color: #667eea;
            text-decoration: none;
        }
        
        .footer-link a:hover {
            text-decoration: underline;
        }
        
        /* 订阅预约弹窗 */
        .subscription-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            padding: 20px;
        }
        .subscription-overlay.show {
            display: flex;
        }
        .subscription-modal {
            background: white;
            border-radius: 16px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalIn 0.3s ease;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        .subscription-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .subscription-title {
            color: white;
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }
        .subscription-close {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        .subscription-close:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }
        .subscription-body {
            padding: 24px;
        }
        .subscription-plan-info {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .subscription-plan-name {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }
        .subscription-plan-price {
            font-size: 24px;
            font-weight: 800;
            color: #667eea;
            margin-bottom: 4px;
        }
        .subscription-plan-duration {
            color: #666;
            font-size: 14px;
        }
        .subscription-form-group {
            margin-bottom: 16px;
        }
        .subscription-form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }
        .subscription-form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .subscription-form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .subscription-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }
        .subscription-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102,126,234,0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-decoration">
            <div class="warning-icon"><?php echo $isCountExhausted ? '📉' : '⚠️'; ?></div>
            <h2><?php echo $isCountExhausted ? '您的授权过期了' : ($isExpired ? '您的授权过期了' : '订阅授权套餐'); ?></h2>
            <p><?php echo $isCountExhausted ? '您的授权过期了，请选择套餐付费授权' : ($isExpired ? '您的授权过期了，请选择套餐付费授权' : '请选择授权套餐'); ?></p>
        </div>
        
        <div class="content-wrapper">
            <div class="message-box">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
            
            <div class="plans-container">
                <?php foreach ($plans as $plan): ?>
                <div class="plan-card <?php echo $plan['is_popular'] ? 'popular' : ''; ?>">
                    <div class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></div>
                    <?php if ($plan['description']): ?>
                    <div class="plan-desc"><?php echo htmlspecialchars($plan['description']); ?></div>
                    <?php endif; ?>
                    <div class="plan-price">
                        <small>¥</small><?php echo number_format($plan['price'], 2); ?>
                    </div>
                    <div class="plan-duration">
                        <?php echo $plan['duration_days'] > 9999 ? '永久授权' : $plan['duration_days'] . '天有效期'; ?>
                    </div>
                    <?php if ($paymentMode == 'subscription'): ?>
                    <button type="button" class="plan-btn" onclick="openSubscriptionModal(<?php echo $plan['id']; ?>, '<?php echo htmlspecialchars($plan['name'], ENT_QUOTES); ?>', <?php echo $plan['price']; ?>, <?php echo $plan['duration_days']; ?>)">
                        订阅预约
                    </button>
                    <?php else: ?>
                    <a href="<?php echo htmlspecialchars($paymentUrl); ?>?auth_id=<?php echo $authId ? intval($authId) : 0; ?>&plan_id=<?php echo $plan['id']; ?>" class="plan-btn">
                        立即购买
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="footer-link">
                <a href="javascript:history.back()">← 返回上一页</a>
            </div>
        </div>
    </div>

    <!-- 订阅预约弹窗 -->
    <div class="subscription-overlay" id="subscriptionOverlay">
        <div class="subscription-modal">
            <div class="subscription-header">
                <h3 class="subscription-title">📋 订阅套餐预约</h3>
                <button class="subscription-close" onclick="closeSubscriptionModal()">×</button>
            </div>
            <div class="subscription-body">
                <div class="subscription-plan-info">
                    <div class="subscription-plan-name" id="subPlanName">-</div>
                    <div class="subscription-plan-price">¥<span id="subPlanPrice">0.00</span></div>
                    <div class="subscription-plan-duration">有效期：<span id="subPlanDuration">-</span></div>
                </div>
                <form id="subscriptionForm" onsubmit="submitSubscription(event)">
                    <div class="subscription-form-group">
                        <label>姓名 *</label>
                        <input type="text" id="subUserName" placeholder="请输入您的姓名" required value="<?php echo htmlspecialchars($authInfo['name'] ?? ''); ?>">
                    </div>
                    <div class="subscription-form-group">
                        <label>手机号 *</label>
                        <input type="tel" id="subUserPhone" placeholder="请输入您的手机号" required value="<?php echo htmlspecialchars($authInfo['phone'] ?? ''); ?>">
                    </div>
                    <div class="subscription-form-group">
                        <label>电子邮箱 <span style="color: red;">*</span></label>
                        <input type="email" id="subUserWechat" placeholder="请输入您的电子邮箱" required value="<?php echo htmlspecialchars($authInfo['wechat'] ?? ''); ?>">
                    </div>
                    <div class="subscription-form-group" style="display: none;">
                        <label>邮箱</label>
                        <input type="email" id="subUserEmail" placeholder="请输入您的邮箱（选填）">
                    </div>
                    <button type="submit" class="subscription-submit" id="subSubmitBtn">提交预约</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        var currentPlanId = 0;

        function openSubscriptionModal(planId, planName, price, durationDays) {
            currentPlanId = planId;
            document.getElementById('subPlanName').textContent = planName;
            document.getElementById('subPlanPrice').textContent = price.toFixed(2);
            document.getElementById('subPlanDuration').textContent = durationDays > 9999 ? '永久' : durationDays + '天';
            document.getElementById('subscriptionOverlay').classList.add('show');
        }

        function closeSubscriptionModal() {
            document.getElementById('subscriptionOverlay').classList.remove('show');
        }

        function submitSubscription(e) {
            e.preventDefault();
            
            var submitBtn = document.getElementById('subSubmitBtn');
            var formAuthId = <?php echo intval($authId ?? 0); ?>;
            
            if (!formAuthId || formAuthId <= 0) {
                alert('授权信息不存在，请先完成免费授权申请');
                return;
            }
            
            if (!currentPlanId || currentPlanId <= 0) {
                alert('请选择套餐');
                return;
            }
            
            var userName = document.getElementById('subUserName').value.trim();
            var userPhone = document.getElementById('subUserPhone').value.trim();
            
            if (!userName) {
                alert('请输入您的姓名');
                return;
            }
            
            if (!userPhone) {
                alert('请输入您的手机号');
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.textContent = '提交中...';

            var data = {
                auth_id: formAuthId,
                plan_id: currentPlanId,
                user_name: userName,
                user_phone: userPhone,
                user_wechat: document.getElementById('subUserWechat').value.trim(),
                user_email: document.getElementById('subUserEmail').value.trim()
            };

            fetch('/api/submit_subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function(result) {
                if (result.code === 200) {
                    alert('预约提交成功！请等待管理员联系您。');
                    closeSubscriptionModal();
                } else {
                    alert('预约失败：' + (result.message || '未知错误'));
                }
            })
            .catch(function(error) {
                console.error('订阅提交错误:', error);
                alert('提交失败：' + (error.message || '网络错误，请稍后重试'));
            })
            .finally(function() {
                submitBtn.disabled = false;
                submitBtn.textContent = '提交预约';
            });
        }

        // 点击遮罩关闭
        document.getElementById('subscriptionOverlay').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSubscriptionModal();
            }
        });
    </script>
</body>
</html>
        <?php
        exit;
    }
    
    /**
     * 通过 IP 和域名获取授权记录
     */
    private function getAuthByIpDomain($ip, $domain) {
        if (!defined('LICENSE_API_URL')) {
            return null;
        }
        
        $apiUrl = LICENSE_API_URL . '/get_auth.php?ip=' . urlencode($ip) . '&domain=' . urlencode($domain);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode != 200) {
            return null;
        }
        
        $result = json_decode($response, true);
        if (!$result || !isset($result['success']) || !$result['success'] || !isset($result['auth'])) {
            return null;
        }
        
        return $result['auth'];
    }

    /**
     * 从授权系统API获取套餐列表
     */
    private function getPlansFromApi() {
        // 默认套餐（与后台套餐管理一致的价格）
        $defaultPlans = [
            ['id' => 1, 'name' => '月卡', 'description' => '30天授权期限', 'price' => 100.00, 'duration_days' => 30, 'is_popular' => 1, 'status' => 1],
            ['id' => 2, 'name' => '季卡', 'description' => '90天授权期限，更优惠', 'price' => 250.00, 'duration_days' => 90, 'is_popular' => 0, 'status' => 1],
            ['id' => 3, 'name' => '半年卡', 'description' => '180天授权期限，更优惠', 'price' => 500.00, 'duration_days' => 180, 'is_popular' => 0, 'status' => 1],
            ['id' => 4, 'name' => '年卡', 'description' => '365天授权期限，超值选择', 'price' => 800.00, 'duration_days' => 365, 'is_popular' => 1, 'status' => 1],
            ['id' => 5, 'name' => '终身版', 'description' => '永久授权，一次付费终身使用', 'price' => 999.00, 'duration_days' => 9999, 'is_popular' => 0, 'status' => 0]
        ];
        
        if (!defined('LICENSE_API_URL')) {
            return $defaultPlans;
        }
        
        $apiUrl = LICENSE_API_URL . '/get_plans.php';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode != 200) {
            return $defaultPlans;
        }
        
        $result = json_decode($response, true);
        if (!$result || !isset($result['success']) || !$result['success']) {
            return $defaultPlans;
        }
        
        // 处理返回的数据，确保格式正确
        $plans = $result['data'] ?? [];
        if (empty($plans)) {
            return $defaultPlans;
        }
        
        // 确保每个套餐有必要的字段，并且只显示启用的套餐
        $validPlans = [];
        foreach ($plans as $plan) {
            // 检查是否启用
            if (isset($plan['status']) && $plan['status'] == 0) {
                continue;
            }
            
            $validPlans[] = [
                'id' => $plan['id'] ?? 0,
                'name' => $plan['name'] ?? '未知套餐',
                'description' => $plan['description'] ?? '',
                'price' => floatval($plan['price'] ?? 0),
                'duration_days' => intval($plan['duration_days'] ?? 30),
                'is_popular' => isset($plan['is_popular']) ? intval($plan['is_popular']) : 0
            ];
        }
        
        return empty($validPlans) ? $defaultPlans : $validPlans;
    }
    
    /**
     * 从授权系统API获取支付模式
     */
    private function getPaymentModeFromApi() {
        if (!defined('LICENSE_API_URL')) {
            return 'alipay';
        }
        
        $apiUrl = LICENSE_API_URL . '/get_payment_mode.php';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode != 200) {
            return 'alipay';
        }
        
        $result = json_decode($response, true);
        if (!$result || !isset($result['success']) || !$result['success']) {
            return 'alipay';
        }
        
        return $result['data']['payment_mode'] ?? 'alipay';
    }
    
    private function showError($message) {
        ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>授权失败</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: white; padding: 40px; border-radius: 16px; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.2); max-width: 480px; width: 100%; }
        .icon { font-size: 64px; margin-bottom: 20px; }
        h1 { color: #333; margin-bottom: 15px; font-size: 22px; }
        .error { color: #dc3545; font-size: 14px; margin-bottom: 20px; }
        .btn { padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">❌</div>
        <h1>授权失败</h1>
        <div class="error"><?php echo htmlspecialchars($message); ?></div>
        <button class="btn" onclick="history.back()">返回重试</button>
    </div>
</body>
</html>
        <?php
        exit;
    }
}

// 执行授权处理
$authorizer = new LicenseAuthorizer();
$authorizer->handleRequest();
?>