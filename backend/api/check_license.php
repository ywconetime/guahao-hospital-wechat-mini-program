<?php
/**
 * API授权检查 - 调用独立授权系统 API
 */

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

// Layer 0: 检查保护开关状态文件
$switchFile = __DIR__ . '/../config/protection_switch.php';
if (file_exists($switchFile)) {
    require_once $switchFile;
    if (isset($PROTECTION_DISABLED) && $PROTECTION_DISABLED) {
        goto _SKIP_PROTECTION;
    }
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

// Layer 2-9: 检查所有关键文件（包括文件存在性和内容完整性）
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

$hashesDir = __DIR__ . '/../config/hashes';
$layerNum = 2;
foreach ($protectedFiles as $file) {
    $filePath = $rootDir . $ds . $file;
    
    // 检查文件是否存在
    if (!file_exists($filePath)) {
        _protection_show_error(str_pad($layerNum, 3, '0', STR_PAD_LEFT), '关键文件缺失: ' . $file);
    }
    
    // ✅ 新增：检查文件内容完整性（哈希校验）
    $normalizedFilePath = str_replace('\\', '/', $file);
    $hashFileName = md5($normalizedFilePath) . '.hash';
    $hashFilePath = $hashesDir . '/' . $hashFileName;
    
    if (!file_exists($hashFilePath)) {
        _protection_show_error(str_pad($layerNum, 3, '0', STR_PAD_LEFT), '哈希校验文件缺失: ' . $hashFileName);
    }
    
    $expectedHash = trim(file_get_contents($hashFilePath));
    $actualHash = hash_file('sha256', $filePath);
    if ($actualHash !== $expectedHash) {
        _protection_show_error(str_pad($layerNum, 3, '0', STR_PAD_LEFT), '文件已被修改: ' . $file);
    }
    
    $layerNum++;
    if ($layerNum > 9) break;
}

_SKIP_PROTECTION:

// 所有检查通过

// 使用统一的API地址配置（支持本地/云端自动切换）
$protectedFile = __DIR__ . '/../config/api_urls_protected.php';
if (!file_exists($protectedFile)) {
    // 文件不存在，返回 JSON 错误
    echo json_encode(['success' => false, 'error' => '系统保护已激活', 'code' => 'LAYER_000']);
    exit(1);
}
require_once $protectedFile;

class SimpleLicenseChecker
{
    public static function check()
    {
        // ✅ 获取小程序后台服务器自己的公网IP（不是访客的IP）
        $serverIP = self::getServerPublicIP();
        $domain = $_SERVER['HTTP_HOST'] ?? 'unknown';
        
        // =============================================
        // 本地环境自动跳过授权（不依赖任何配置）
        // =============================================
        if (self::isLocalDomain($domain)) {
            error_log("[API授权调试] 检测到本地环境（{$domain}）→ 自动跳过授权");
            return true;
        }
        
        $result = self::verifyLicense($serverIP, $domain);
        return $result['success'];
    }
    
    // 检测是否为本地环境
    private static function isLocalDomain($domain)
    {
        $domain = strtolower($domain);
        
        // 严格本地域名列表（含端口号）
        if (strpos($domain, 'localhost') !== false || 
            strpos($domain, '127.0.0.1') !== false || 
            strpos($domain, '::1') !== false) {
            return true;
        }
        
        // 检查是否是局域网IP（192.168.x.x, 10.x.x.x, 172.16-31.x.x）
        if (preg_match('/^(192\.168|10\.)/', $domain)) {
            return true;
        }
        
        if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $domain)) {
            return true;
        }
        
        return false;
    }

    public static function showError($message)
    {
        http_response_code(403);
        echo json_encode(['code' => 403, 'message' => $message]);
        exit;
    }

    private static function verifyLicense($ip, $domain)
    {
        $url = LICENSE_API_URL . '/free_verify.php';
        
        $postData = [
            'action' => 'verify',
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
        
        return ['success' => false, 'message' => $result['message'] ?? '验证失败'];
    }

    private static function getClientIP()
    {
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
    private static function getServerPublicIP()
    {
        static $cachedIp = null;
        
        if ($cachedIp !== null) {
            return $cachedIp;
        }
        
        $ip = null;
        $cacheFile = dirname(__DIR__) . '/config/server_ip_cache.txt';
        
        // 方法1：从缓存文件读取（避免频繁调用外部服务，最快）
        if (!$ip && file_exists($cacheFile)) {
            $cacheTime = filemtime($cacheFile);
            $cacheAge = time() - $cacheTime;
            if ($cacheAge < 604800) {
                $cachedValue = trim(file_get_contents($cacheFile));
                if (filter_var($cachedValue, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !self::isPrivateIP($cachedValue)) {
                    $ip = $cachedValue;
                }
            }
        }
        
        // 方法2：使用外部服务获取（最准确，超时1秒，失败就跳过）
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
                        if (filter_var($result, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !self::isPrivateIP($result)) {
                            $ip = $result;
                            @file_put_contents($cacheFile, $ip);
                            break;
                        }
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }
        
        // 方法3：fallback - SERVER_ADDR（服务器绑定的IP）
        if (!$ip) {
            $serverAddr = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
            if (filter_var($serverAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !self::isPrivateIP($serverAddr)) {
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
                        if (!self::isPrivateIP($headerValue)) {
                            $ip = $headerValue;
                            break;
                        }
                    }
                }
            }
        }
        
        // 方法5：最后的fallback
        if (!$ip) {
            $ip = self::getClientIP();
        }
        
        $cachedIp = $ip;
        return $ip;
    }

    /**
     * 检查是否为私有IP
     */
    private static function isPrivateIP($ip)
    {
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
}

?>