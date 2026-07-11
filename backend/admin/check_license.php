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

$hashesDir = __DIR__ . '/../config/hashes';
$layerNum = 2;
foreach ($protectedFiles as $file) {
    $filePath = $rootDir . $ds . $file;
    
    if (!file_exists($filePath)) {
        _protection_show_error(str_pad($layerNum, 3, '0', STR_PAD_LEFT), '关键文件缺失: ' . $file);
    }
    
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


/**
 * 管理后台授权检查 - 调用独立授权系统 API
 * 新授权流程：登录前弹出授权框，输入姓名、手机号、微信号即可免费授权使用一个月
 */

// 引入路径辅助文件
require_once __DIR__ . '/license_path_helper.php';

// 使用统一的API地址配置（支持本地/云端自动切换）
$protectedFile = __DIR__ . '/../config/api_urls_protected.php';
if (!file_exists($protectedFile)) {
    // 文件不存在，显示保护页面
    $errorPage = '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>系统保护已激活</title><style>body{font-family:Microsoft YaHei;background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}.error-container{text-align:center;padding:60px;background:rgba(231,76,60,0.1);border-radius:30px;border:2px solid rgba(231,76,60,0.4);max-width:600px;box-shadow:0 20px 60px rgba(0,0,0,0.5);}.error-icon{font-size:80px;margin-bottom:20px;}.error-title{color:#e74c3c;font-size:28px;margin-bottom:15px;}.error-message{color:rgba(255,255,255,0.8);line-height:1.8;font-size:16px;}.layer-info{background:rgba(0,0,0,0.3);padding:10px 20px;border-radius:8px;margin-top:20px;font-family:monospace;font-size:14px;color:#f39c12;}</style></head><body><div class="error-container"><div class="error-icon">🛡️</div><h1 class="error-title">系统保护已激活</h1><p class="error-message">检测到配置文件异常，系统已停止运行以保护数据安全。</p><p class="error-message">请联系系统管理员进行修复。</p><div class="layer-info">[LAYER_000] 保护系统文件缺失</div></div></body></html>';
    echo $errorPage;
    exit(1);
}
require_once $protectedFile;

class AdminLicenseChecker
{
    private static $FREE_TRIAL_DAYS = 30;
    private static $authData = null; // 保存授权数据
    private static $remainingDelay = 0; // 保存剩余延迟时间
    
    public static function check()
    {
        // 确保 session 已启动
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        // 先确保 domain 绝对不为空 - 在最前面就处理！
        $domain = 'localhost';  // 默认值
        if (!empty($_SERVER['HTTP_HOST'])) {
            $domain = $_SERVER['HTTP_HOST'];
        } elseif (!empty($_SERVER['SERVER_NAME'])) {
            $domain = $_SERVER['SERVER_NAME'];
        }
        
        // 如果是请求显示授权内容（AJAX调用）
        if (isset($_GET['action']) && $_GET['action'] == 'show_auth') {
            self::showAuthContent();
            exit;
        }
        
        // 获取客户端IP和域名
        $clientIP = self::getClientIP();
        
        // 获取服务器公网IP（用于授权绑定）
        $serverIP = self::getServerPublicIP();
        
        // 检查是否是本地环境（包括localhost、127.0.0.1、局域网IP）
        $isLocal = self::isLocalDomain($domain);
        
        // 调试信息
        error_log("[授权调试] domain: {$domain}, clientIP: {$clientIP}, serverIP: {$serverIP}, isLocal: {$isLocal}");
        
        // =============================================
        // 第一步：本地环境自动跳过授权（不依赖任何配置）
        // =============================================
        // 只要是本地环境（localhost/127.0.0.1/局域网IP），直接跳过授权检查
        // 这确保本地开发和测试时不会被授权系统干扰
        if ($isLocal) {
            error_log("[授权调试] 检测到本地环境（{$domain}）→ 自动跳过授权");
            // 设置本地测试环境的默认授权数据，确保用户级别通知功能正常
            self::$authData = [
                'id' => 1,
                'phone' => '13800138000',
                'status' => 1,
                'free_trial_count' => 0
            ];
            return true;
        }
        
        // =============================================
        // 第二步：先检查授权状态（区分新用户和已有记录用户）
        // =============================================
        
        // 调用授权系统 API 验证授权（同时会自动绑定域名）
        // 使用服务器公网IP进行授权绑定
        $result = self::verifyLicense($serverIP, $domain);
        
        // 调试日志：显示 API 返回结果
        error_log("[授权调试] verifyLicense 返回结果: " . json_encode($result));
        
        // 保存授权数据供后续使用
        if (isset($result['data'])) {
            self::$authData = $result['data'];
        }
        
        // 检查是否已有授权记录（包括过期、次数用完、被禁止）
        // 判断标准：data 不为 null 说明有授权记录
        $hasAuthRecord = isset($result['data']) && $result['data'] !== null;
        
        // 如果有授权记录且被禁止（code=403 且有数据），直接显示授权页
        if ($hasAuthRecord && isset($result['code']) && $result['code'] == 403) {
            // 被禁止授权，立即显示
            self::showUnauthorizedPage($result['message'], false, false);
            exit;
        }
        
        // 如果没有授权记录（新用户），需要检查延迟时间
        if (!$hasAuthRecord) {
            // =============================================
            // 延迟时间检查（仅针对新用户）
            // =============================================
            
            // 如果是从延迟页面跳转过来的（force_auth=1），跳过延迟检查
            $forceAuth = isset($_GET['force_auth']) && $_GET['force_auth'] == 1;
            
            // 非强制授权时检查延迟
            if (!$forceAuth) {
                $delaySeconds = self::getFirstAccessDelaySeconds();
                
                error_log("[授权调试] 新用户延迟检查: delaySeconds: {$delaySeconds}");
                
                // 如果设置了延迟时间 > 0
                if ($delaySeconds > 0) {
                    error_log("[授权调试] 新用户延迟时间设置为 {$delaySeconds} 秒");
                    
                    // 获取数据库连接
                    $pdo = null;
                    try {
                        if (loadLicenseSystem()) {
                            $pdo = get_db_connection();
                        }
                    } catch (Exception $e) {
                        error_log("[授权调试] 获取数据库连接失败: " . $e->getMessage());
                    }
                    
                    // 使用浏览器指纹 + 数据库双重验证（防止多域名绕过）
                    // 优先级：POST参数 > GET参数 > Cookie
                    $postFingerprint = $_POST['fingerprint'] ?? '';
                    $getFingerprint = $_GET['fingerprint'] ?? '';
                    $cookieFingerprint = $_COOKIE['browser_fingerprint'] ?? '';
                    $fingerprint = $postFingerprint ?: $getFingerprint ?: $cookieFingerprint ?: '';
                    $delayStartTime = null;
                    
                    // 详细调试日志
                    error_log("[授权调试] =======================================");
                    error_log("[授权调试] 请求时间: " . date('Y-m-d H:i:s'));
                    error_log("[授权调试] 域名: " . $domain);
                    error_log("[授权调试] POST指纹: '" . ($postFingerprint ? $postFingerprint : '空') . "'");
                    error_log("[授权调试] GET指纹: '" . ($getFingerprint ? $getFingerprint : '空') . "'");
                    error_log("[授权调试] Cookie指纹: '" . ($cookieFingerprint ? $cookieFingerprint : '空') . "'");
                    error_log("[授权调试] 最终指纹: '" . ($fingerprint ? $fingerprint : '空') . "'");
                    
                    // 如果没有指纹，强制设置一个服务器端生成的指纹Cookie并刷新页面
                    if (empty($fingerprint)) {
                        $serverFingerprint = self::generateServerFingerprint();
                        error_log("[授权调试] ⚠️ 没有指纹，生成服务器端指纹: " . $serverFingerprint);
                        
                        // 设置Cookie（有效期365天，不使用Secure标志以支持HTTP环境）
                        setcookie('browser_fingerprint', $serverFingerprint, time() + 365 * 24 * 60 * 60, '/');
                        
                        // 立即刷新页面，让Cookie生效
                        echo '<!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset="UTF-8">
                            <script>
                                // 确保Cookie已设置后再刷新
                                document.cookie = "browser_fingerprint=' . $serverFingerprint . '; expires=' . date('D, d-M-Y H:i:s T', time() + 365 * 24 * 60 * 60) . '; path=/";
                                setTimeout(function() {
                                    window.location.href = window.location.href;
                                }, 100);
                            </script>
                        </head>
                        <body>
                            正在初始化...
                        </body>
                        </html>';
                        exit();
                    }
                    
                    // 确保域名不为空
                    if (empty($domain)) {
                        $domain = $_SERVER['HTTP_HOST'] ?? 'unknown';
                    }
                    
                    if ($pdo) {
                        $delayStartTime = self::getDelayStartTimeByFingerprint($pdo, $domain, $fingerprint);
                        error_log("[授权调试] 从数据库获取的延迟开始时间: " . ($delayStartTime ? date('Y-m-d H:i:s', $delayStartTime) : 'null'));
                    } else {
                        error_log("[授权调试] 数据库连接失败，无法使用指纹验证");
                    }
                    
                    // 如果数据库中没有记录，说明是首次访问
                    if ($delayStartTime === null) {
                        $delayStartTime = time();
                        
                        // 确保域名不为空
                        if (empty($domain)) {
                            $domain = $_SERVER['HTTP_HOST'] ?? 'unknown';
                        }
                        
                        self::createFirstAccessRecord($domain, $delayStartTime, $fingerprint);
                        error_log("[授权调试] ⚠️ 新用户首次访问，开始延迟期，开始时间: " . date('Y-m-d H:i:s', $delayStartTime));
                    } else {
                        $elapsed = time() - $delayStartTime;
                        error_log("[授权调试] ✅ 找到已有记录，延迟开始时间: " . date('Y-m-d H:i:s', $delayStartTime) . "，已过去: {$elapsed}秒");
                    }
                    
                    // 同时保存到 Session 和 Cookie（用于 JavaScript 获取剩余时间）
                    $_SESSION['auth_delay_start_time'] = $delayStartTime;
                    setcookie('auth_delay_start_time', $delayStartTime, time() + 31 * 24 * 60 * 60, '/');
                    $_SESSION['browser_fingerprint'] = $fingerprint;
                    
                    // 计算已经过去的时间
                    $elapsedSeconds = time() - $delayStartTime;
                    error_log("[授权调试] 已过去时间: {$elapsedSeconds} 秒");
                    
                    // 如果还在延迟期内，显示登录页
                    if ($elapsedSeconds < $delaySeconds) {
                        $remainingDelay = $delaySeconds - $elapsedSeconds;
                        self::$remainingDelay = $remainingDelay;
                        error_log("[授权调试] 新用户还在延迟期内，剩余时间: " . $remainingDelay . " 秒");
                        
                        // 不再使用 Refresh 头，改用 JavaScript 倒计时跳转
                        // 延迟时间在 login.php 中通过 JavaScript 处理
                        
                        return true;
                    }
                    
                    // 延迟期已过，继续检查授权
                    error_log("[授权调试] 新用户延迟期已过，继续检查授权");
                } else {
                    error_log("[授权调试] 新用户延迟时间为0或未设置，继续检查授权");
                }
            } else {
                error_log("[授权调试] force_auth=1 → 跳过延迟检查，直接验证授权");
            }
        } else {
            error_log("[授权调试] 已有授权记录，跳过延迟检查，立即显示授权页");
        }
        
        // 检查是否是禁用状态（用户禁用或域名禁用）
        $isDisabled = false;
        $disableMessage = '';
        
        // 检查用户是否被禁用
        if (isset($result['data']['status']) && $result['data']['status'] == 0) {
            $isDisabled = true;
            $disableMessage = '您的授权已被管理员禁用，请联系管理员';
        }
        if (isset($result['message']) && strpos($result['message'], '已被管理员禁用') !== false) {
            $isDisabled = true;
            $disableMessage = '您的授权已被管理员禁用，请联系管理员';
        }
        
        // 检查域名是否被禁用
        if (isset($result['message']) && strpos($result['message'], '当前域名未被授权') !== false) {
            $isDisabled = true;
            $disableMessage = $result['message'];
        }
        
        // 检查服务器IP是否被禁用
        if (isset($result['message']) && strpos($result['message'], '当前服务器IP未被授权') !== false) {
            $isDisabled = true;
            $disableMessage = $result['message'];
        }
        
        // 检查客户端IP是否被禁用
        if (isset($result['message']) && strpos($result['message'], '当前IP未被授权') !== false) {
            $isDisabled = true;
            $disableMessage = $result['message'];
        }
        
        // 如果被禁用了，不管什么情况都显示授权页
        if ($isDisabled) {
            self::showUnauthorizedPage($disableMessage, false, false);
            exit;
        }
        
        // 检查授权是否成功（API 返回 code 而不是 success）
        if (isset($result['code']) && $result['code'] == 200) {
            // 已经授权 → 正常使用
            return true;
        }
        
        // =============================================
        // 第四步：显示授权页面
        // =============================================
        
        // 获取授权记录信息（用于判断免费试用次数）
        $authRecord = null;
        if (isset($result['data']) && isset($result['data']['id'])) {
            $authRecord = $result['data'];
        }
        
        // 授权验证失败（已过期，code=401）
        if ($result['code'] == 401) {
            // 如果返回了授权记录，保存起来供页面使用
            if ($authRecord) {
                self::$authData = $authRecord;
            }
            // 显示过期授权页
            self::showUnauthorizedPage($result['message'], true, false);
            exit;
        }
        // 授权验证失败（授权次数用完，code=402）
        elseif ($result['code'] == 402) {
            // 如果返回了授权记录，保存起来供页面使用
            if ($authRecord) {
                self::$authData = $authRecord;
            }
            // 显示授权次数用完弹窗
            self::showUnauthorizedPage($result['message'], false, true);
            exit;
        }
        // 授权验证失败（未授权，code=403）
        else {
            // 如果返回了授权记录，保存起来供页面使用
            if ($authRecord) {
                self::$authData = $authRecord;
            }
            // 显示授权页
            self::showUnauthorizedPage($result['message'], false, false);
            exit;
        }
        
        return true;
    }
    
    private static function isLocalDomain($domain)
    {
        // 如果域名为空，使用客户端IP来判断
        if (empty($domain)) {
            $clientIP = self::getClientIP();
            return self::isLocalIP($clientIP);
        }
        
        // 严格本地域名列表
        $localDomains = ['localhost', '127.0.0.1', '::1', 'localhost:88', 'localhost:80', '127.0.0.1:88', '365xiang', '365xiang:88'];
        $domainWithoutPort = explode(':', $domain)[0];
        
        // 检查是否是严格的本地环境
        $isStrictLocal = in_array($domain, $localDomains) || in_array($domainWithoutPort, $localDomains);
        
        if ($isStrictLocal) {
            return true;
        }
        
        // 检查是否是局域网IP（192.168.x.x, 10.x.x.x, 172.16-31.x.x）
        if (preg_match('/^(192\.168|10\.)/', $domainWithoutPort)) {
            return true;
        }
        
        // 检查是否是172.16-31.x.x网段
        if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $domainWithoutPort)) {
            return true;
        }
        
        return false;
    }
    
    private static function isLocalIP($ip)
    {
        // 检查是否是本地IP
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }
        
        // 检查是否是局域网IP
        if (preg_match('/^(192\.168|10\.)/', $ip)) {
            return true;
        }
        
        // 检查是否是172.16-31.x.x网段
        if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip)) {
            return true;
        }
        
        return false;
    }
    
    private static function isLocalAccessAllowed()
    {
        // 方法1：直接检查本地配置文件（最可靠）
        $storageFile = dirname(__DIR__) . '/license_system/data/local_access_setting.json';
        
        if (file_exists($storageFile)) {
            $content = file_get_contents($storageFile);
            $data = json_decode($content, true);
            if (is_array($data) && isset($data['allow_local_access'])) {
                $result = $data['allow_local_access'] == 1;
                error_log("[本地免授权] 从配置文件读取: allow_local_access = " . ($result ? '1' : '0'));
                return $result;
            }
        }
        
        // 方法2：检查数据库
        try {
            // 兼容本地和云服务器的路径
            $licenseConfigPath = __DIR__ . '/../license_system/config.php';
            if (!file_exists($licenseConfigPath)) {
                // 如果 license_system 不在上级目录，尝试其他路径
                $licenseConfigPath = __DIR__ . '/license_system/config.php';
            }
            if (!file_exists($licenseConfigPath)) {
                // 如果还是找不到，使用绝对路径
                $licenseConfigPath = dirname(__DIR__) . '/license_system/config.php';
            }
            
            if (!file_exists($licenseConfigPath)) {
                error_log("[授权调试] 找不到 license_system/config.php 文件");
                return true; // 找不到配置文件时默认允许
            }
            
            require_once $licenseConfigPath;
            
            $functionsPath = dirname($licenseConfigPath) . '/includes/functions.php';
            if (file_exists($functionsPath)) {
                require_once $functionsPath;
            }
            
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SELECT setting_value FROM license_settings WHERE setting_key = 'allow_local_access'");
            $stmt->execute();
            $value = $stmt->fetchColumn();
            $result = $value == 1;
            error_log("[本地免授权] 从数据库读取: allow_local_access = " . ($result ? '1' : '0'));
            return $result;
        } catch (Exception $e) {
            error_log("[本地免授权] 数据库读取失败: " . $e->getMessage());
        }
        
        // 默认关闭本地免授权（保持与数据库设置一致）
        error_log("[本地免授权] 默认关闭（找不到配置时要求授权）");
        return false;
    }
    
    private static function getFirstAccessDelaySeconds()
    {
        // 首次访问延迟功能已废弃，直接返回0秒（立即授权）
        // 现在所有用户都会自动获得30天免费授权
        error_log("[授权调试] 首次访问延迟功能已废弃，立即授权");
        return 0;
    }
    
    /**
     * 将延迟值转换为秒
     */
    private static function convertToSeconds($value, $unit)
    {
        switch ($unit) {
            case 'seconds': return intval($value);
            case 'minutes': return intval($value) * 60;
            case 'hours': return intval($value) * 60 * 60;
            case 'days': default: return intval($value) * 60 * 60 * 24;
        }
    }
    
    /**
     * 通过浏览器指纹获取延迟开始时间（核心防绕过方法）
     */
    private static function getDelayStartTimeByFingerprint($pdo, $domain, $fingerprint)
    {
        // 如果没有指纹，回退到原来的域名记录方式
        if (empty($fingerprint)) {
            error_log("[授权调试] 无浏览器指纹，使用域名记录方式");
            return self::getFirstAccessTime($domain);
        }
        
        // ========== 优先从授权系统获取首次访问时间 ==========
        $licenseSystemTime = self::getFirstAccessTimeFromLicenseSystem($fingerprint);
        if ($licenseSystemTime !== null) {
            error_log("[授权调试] ✅ 使用授权系统的首次访问时间");
            return $licenseSystemTime;
        }
        
        error_log("[授权调试] 授权系统不可用，使用本地数据库");
        
        try {
            // 查询该指纹的所有记录
            $stmt = $pdo->prepare("
                SELECT first_access_time, domain 
                FROM license_browser_fingerprints 
                WHERE fingerprint = ?
                ORDER BY first_access_time ASC
                LIMIT 10
            ");
            $stmt->execute([$fingerprint]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($records)) {
                // 找到最早的访问记录（无论哪个域名）
                $earliestTime = min(array_column($records, 'first_access_time'));
                $registeredDomains = array_column($records, 'domain');
                
                error_log("[授权调试] 指纹已存在，最早访问: " . date('Y-m-d H:i:s', $earliestTime));
                error_log("[授权调试] 该指纹已注册域名: " . implode(', ', $registeredDomains));
                
                // 如果当前域名不在已注册列表中，说明用户在尝试绕过
                if (!in_array($domain, $registeredDomains)) {
                    error_log("[授权调试] ⚠️ 检测到可疑行为！用户尝试在新域名使用同一指纹");
                    // 新域名也使用相同的延迟开始时间，防止绕过
                    // 这里可以选择拒绝访问或使用最早的时间
                }
                
                return $earliestTime;
            }
            
            // 指纹不存在，检查同一IP是否有其他指纹记录
            $clientIP = self::getClientIP();
            $stmt = $pdo->prepare("
                SELECT fingerprint, first_access_time 
                FROM license_browser_fingerprints 
                WHERE ip_address = ? AND fingerprint != ?
                ORDER BY first_access_time ASC
                LIMIT 5
            ");
            $stmt->execute([$clientIP, $fingerprint]);
            $sameIPRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($sameIPRecords)) {
                error_log("[授权调试] 检测到同一IP有其他指纹记录，可能在尝试绕过授权");
                // 返回最早的时间，防止绕过
                $earliestTime = min(array_column($sameIPRecords, 'first_access_time'));
                return $earliestTime;
            }
            
            // 完全新的指纹和IP，记录首次访问
            return null;
            
        } catch (Exception $e) {
            error_log("[授权调试] 指纹验证失败: " . $e->getMessage());
            // 数据库失败时回退到域名记录方式
            return self::getFirstAccessTime($domain);
        }
    }
    
    private static function getFirstAccessTime($domain)
    {
        $storageFile = dirname(__DIR__) . '/data/first_access.json';
        if (!file_exists($storageFile)) {
            // 创建首次访问记录
            self::createFirstAccessRecord($domain, time(), '');
            return null;
        }
        
        $data = json_decode(file_get_contents($storageFile), true);
        if (isset($data[$domain])) {
            return $data[$domain];
        }
        
        // 新域名，记录首次访问时间
        self::createFirstAccessRecord($domain, time(), '');
        return null;
    }
    
    private static function createFirstAccessRecord($domain, $firstAccessTime = null, $fingerprint = '')
    {
        // 确保域名不为空 - 终极防护！
        if (empty($domain)) {
            // 尝试从多个地方获取域名
            if (!empty($_SERVER['HTTP_HOST'])) {
                $domain = $_SERVER['HTTP_HOST'];
            } elseif (!empty($_SERVER['SERVER_NAME'])) {
                $domain = $_SERVER['SERVER_NAME'];
            } else {
                // 都没有，使用默认值
                $domain = 'localhost';
            }
        }
        
        // 二次防护：确保即使是空字符串也能处理
        if (empty($domain) || $domain === '') {
            $domain = 'localhost';
        }
        
        $storageFile = dirname(__DIR__) . '/data/first_access.json';
        $storageDir = dirname($storageFile);
        
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
        
        $data = [];
        if (file_exists($storageFile)) {
            $data = json_decode(file_get_contents($storageFile), true) ?: [];
        }
        
        if ($firstAccessTime === null) {
            $firstAccessTime = time();
        }
        
        $data[$domain] = $firstAccessTime;
        file_put_contents($storageFile, json_encode($data));
        
        // 同时记录到数据库（如果提供了指纹）
        if (!empty($fingerprint)) {
            try {
                // 直接建立数据库连接（不依赖外部函数）
                $pdo = self::getPDOConnection();
                if ($pdo) {
                    $clientIP = self::getClientIP();
                    
                    // 确保域名不为空
                    if (empty($domain)) {
                        $domain = $_SERVER['HTTP_HOST'] ?? 'unknown';
                    }
                    
                    // 检查是否已存在该指纹
                    $stmt = $pdo->prepare("SELECT id FROM license_browser_fingerprints WHERE fingerprint = ?");
                    $stmt->execute([$fingerprint]);
                    if (!$stmt->fetch()) {
                        $stmt = $pdo->prepare("
                            INSERT INTO license_browser_fingerprints (fingerprint, ip_address, domain, first_access_time)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$fingerprint, $clientIP, $domain, $firstAccessTime]);
                        error_log("[授权调试] 新指纹已记录到数据库");
                    } else {
                        error_log("[授权调试] 指纹已存在于数据库中");
                    }
                } else {
                    error_log("[授权调试] 无法建立数据库连接，指纹未记录");
                }
            } catch (Exception $e) {
                error_log("[授权调试] 记录指纹到数据库失败: " . $e->getMessage());
            }
        }
    }
    
    private static function isWithinTrialPeriod($firstAccessTime)
    {
        $trialSeconds = self::$FREE_TRIAL_DAYS * 24 * 60 * 60;
        return (time() - $firstAccessTime) < $trialSeconds;
    }

    /**
     * 获取套餐列表
     */
    private static function getSubscriptionPlans()
    {
        // 默认套餐（与后台套餐管理一致的价格）
        $defaultPlans = [
            [
                'id' => 1,
                'name' => '月卡',
                'description' => '30天授权期限',
                'price' => 100.00,
                'duration_days' => 30,
                'is_popular' => 1,
                'status' => 1
            ],
            [
                'id' => 2,
                'name' => '季卡',
                'description' => '90天授权期限，更优惠',
                'price' => 250.00,
                'duration_days' => 90,
                'is_popular' => 0,
                'status' => 1
            ],
            [
                'id' => 3,
                'name' => '半年卡',
                'description' => '180天授权期限，更优惠',
                'price' => 500.00,
                'duration_days' => 180,
                'is_popular' => 0,
                'status' => 1
            ],
            [
                'id' => 4,
                'name' => '年卡',
                'description' => '365天授权期限，超值选择',
                'price' => 800.00,
                'duration_days' => 365,
                'is_popular' => 1,
                'status' => 1
            ],
            [
                'id' => 5,
                'name' => '终身版',
                'description' => '永久授权，一次付费终身使用',
                'price' => 999.00,
                'duration_days' => 9999,
                'is_popular' => 0,
                'status' => 0
            ]
        ];
        
        $licenseApiUrl = self::getLicenseApiUrlForPlans();
        error_log("[授权调试] getSubscriptionPlans - API URL: " . ($licenseApiUrl ?: '未定义'));
        
        if (!empty($licenseApiUrl)) {
            // 优先调用 get_plans.php（更完整的版本）
            $apiUrl = $licenseApiUrl . '/get_plans.php';
            error_log("[授权调试] getSubscriptionPlans - 完整API地址: " . $apiUrl);
            
            $result = self::callLicenseApi($apiUrl);
            if ($result !== null) {
                // 过滤掉 status=0 的套餐，并确保字段格式正确
                $validPlans = [];
                foreach ($result as $plan) {
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
                
                if (!empty($validPlans)) {
                    error_log("[授权调试] getSubscriptionPlans - API调用成功，获取到 " . count($validPlans) . " 个套餐");
                    return $validPlans;
                }
            }
            
            // 降级到 get_subscription_plans.php
            $apiUrl2 = $licenseApiUrl . '/get_subscription_plans.php';
            $result2 = self::callLicenseApi($apiUrl2);
            if ($result2 !== null) {
                $validPlans = [];
                foreach ($result2 as $plan) {
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
                
                if (!empty($validPlans)) {
                    error_log("[授权调试] getSubscriptionPlans - API调用成功(get_subscription_plans)，获取到 " . count($validPlans) . " 个套餐");
                    return $validPlans;
                }
            }
        }
        
        try {
            if (function_exists('loadLicenseSystem') && loadLicenseSystem()) {
                if (function_exists('get_db_connection')) {
                    $pdo = get_db_connection();
                    $stmt = $pdo->query("SELECT * FROM license_subscription_plans WHERE status = 1 ORDER BY sort_order ASC, id ASC");
                    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if (!empty($plans)) {
                        error_log("[授权调试] getSubscriptionPlans - 从本地数据库获取到 " . count($plans) . " 个套餐");
                        return $plans;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("[授权调试] getSubscriptionPlans - 本地数据库获取失败: " . $e->getMessage());
        }
        
        error_log("[授权调试] getSubscriptionPlans - 使用默认套餐");
        // 从默认套餐中也过滤掉 status=0 的
        $filteredDefaultPlans = [];
        foreach ($defaultPlans as $plan) {
            if ($plan['status'] == 1) {
                $filteredDefaultPlans[] = $plan;
            }
        }
        return $filteredDefaultPlans;
    }
    
    private static function getLicenseApiUrlForPlans()
    {
        if (defined('LICENSE_API_URL')) {
            return LICENSE_API_URL;
        }
        
        $currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost:88';
        $isLocal = strpos($currentHost, 'localhost') !== false || 
                   strpos($currentHost, '127.0.0.1') !== false ||
                   strpos($currentHost, '192.168.') !== false ||
                   strpos($currentHost, '10.') !== false;
        
        if ($isLocal) {
            return 'http://localhost:88/license_system/api';
        } else {
            return 'https://shouquan.4wc.cn/license_system/api';
        }
    }
    
    private static function callLicenseApi($apiUrl)
    {
        $methods = [
            'curl' => function($url) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'LicenseSystem/1.0');
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($error) {
                    error_log("[授权调试] API调用失败(curl): " . $error);
                    return null;
                }
                
                if ($httpCode != 200) {
                    error_log("[授权调试] API调用失败(HTTP): " . $httpCode);
                    return null;
                }
                
                return $response;
            },
            'file_get_contents' => function($url) {
                $options = [
                    'http' => [
                        'method' => 'GET',
                        'timeout' => 15,
                        'header' => 'User-Agent: LicenseSystem/1.0\r\nContent-Type: application/json\r\n',
                        'ignore_errors' => true
                    ]
                ];
                $context = stream_context_create($options);
                $response = @file_get_contents($url, false, $context);
                
                if ($response === false) {
                    error_log("[授权调试] API调用失败(file_get_contents)");
                    return null;
                }
                
                return $response;
            },
            'stream_socket' => function($url) {
                $parsed = parse_url($url);
                if (!$parsed || !isset($parsed['host'], $parsed['path'])) {
                    return null;
                }
                
                $port = isset($parsed['port']) ? $parsed['port'] : 80;
                $path = $parsed['path'] . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
                
                $socket = @stream_socket_client($parsed['host'] . ':' . $port, $errno, $errstr, 8);
                if (!$socket) {
                    error_log("[授权调试] API调用失败(stream_socket): " . $errstr);
                    return null;
                }
                
                fwrite($socket, "GET " . $path . " HTTP/1.1\r\n");
                fwrite($socket, "Host: " . $parsed['host'] . "\r\n");
                fwrite($socket, "User-Agent: LicenseSystem/1.0\r\n");
                fwrite($socket, "Connection: close\r\n\r\n");
                
                $response = '';
                while (!feof($socket)) {
                    $response .= fgets($socket, 4096);
                }
                fclose($socket);
                
                $parts = explode("\r\n\r\n", $response, 2);
                if (count($parts) != 2) {
                    return null;
                }
                
                return $parts[1];
            }
        ];
        
        foreach ($methods as $methodName => $method) {
            $response = $method($apiUrl);
            if ($response !== null && !empty($response)) {
                $result = json_decode($response, true);
                if ($result && isset($result['success']) && $result['success'] && isset($result['data']) && !empty($result['data'])) {
                    return $result['data'];
                }
            }
        }
        
        return null;
    }
    
    private static function getPaymentMode() {
        $defaultMode = 'alipay';
        
        $licenseApiUrl = self::getLicenseApiUrlForPlans();
        if (!empty($licenseApiUrl)) {
            $apiUrl = $licenseApiUrl . '/get_payment_mode.php';
            error_log("[授权调试] getPaymentMode - API URL: " . $apiUrl);
            
            $methods = [
                'curl' => function($url) {
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    $response = curl_exec($ch);
                    $error = curl_error($ch);
                    curl_close($ch);
                    return $error ? null : $response;
                },
                'file_get_contents' => function($url) {
                    $options = [
                        'http' => [
                            'method' => 'GET',
                            'timeout' => 10,
                            'header' => 'User-Agent: LicenseSystem/1.0\r\n'
                        ]
                    ];
                    $context = stream_context_create($options);
                    return @file_get_contents($url, false, $context);
                }
            ];
            
            foreach ($methods as $methodName => $method) {
                $response = $method($apiUrl);
                if ($response !== null && !empty($response)) {
                    $result = json_decode($response, true);
                    if ($result && isset($result['success']) && $result['success'] && isset($result['data']['payment_mode'])) {
                        error_log("[授权调试] getPaymentMode - API调用成功，支付模式: " . $result['data']['payment_mode']);
                        return $result['data']['payment_mode'];
                    }
                }
            }
        }
        
        // 降级方案：尝试从本地数据库获取
        try {
            if (function_exists('loadLicenseSystem') && loadLicenseSystem()) {
                if (function_exists('get_db_connection')) {
                    $pdo = get_db_connection();
                    $stmt = $pdo->prepare("SELECT config_value FROM license_config WHERE config_key = 'payment_mode'");
                    $stmt->execute();
                    $result = $stmt->fetch();
                    if ($result) {
                        error_log("[授权调试] getPaymentMode - 从本地数据库获取，支付模式: " . $result['config_value']);
                        return $result['config_value'];
                    }
                }
            }
        } catch (Exception $e) {
            error_log("[授权调试] getPaymentMode - 本地数据库获取失败: " . $e->getMessage());
        }
        
        error_log("[授权调试] getPaymentMode - 使用默认支付模式: " . $defaultMode);
        return $defaultMode;
    }
    
    private static function getPaymentUrl() {
        $licenseApiUrl = self::getLicenseApiUrlForPlans();
        if (!empty($licenseApiUrl)) {
            $parsedUrl = parse_url($licenseApiUrl);
            $scheme = $parsedUrl['scheme'] ?? 'http';
            $host = $parsedUrl['host'] ?? '';
            $path = $parsedUrl['path'] ?? '';
            
            $basePath = dirname($path);
            if ($basePath === '/' || $basePath === '.') {
                $basePath = '/license_system';
            }
            
            if (!empty($host)) {
                return $scheme . '://' . $host . $basePath . '/pay.php';
            } else {
                return $basePath . '/pay.php';
            }
        }
        
        return '/license_system/pay.php';
    }
    
    public static function showUnauthorizedPage($message = '', $isExpired = false, $isCountExhausted = false)
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // ✅ 获取小程序后台服务器自己的公网IP（不是访客的IP）
        $serverIP = self::getServerPublicIP();
        
        // 终极三级兜底方案：确保 domain 永远是字符串，绝对不为 null
        $domain = isset($_SERVER['HTTP_HOST']) ? trim($_SERVER['HTTP_HOST']) : '';
        if ($domain === '') {
            $domain = isset($_SERVER['SERVER_NAME']) ? trim($_SERVER['SERVER_NAME']) : '';
        }
        if ($domain === '') {
            $domain = 'unknown.host';
        }
        
        // 检查是否已经提交过授权（使用服务器IP）
        $hasSubmitted = self::checkSubmitted($serverIP, $domain);
        
        // 获取授权用户信息
        $authId = null;
        $authInfo = null;
        $freeTrialCount = 0;
        $maxFreeTrialCount = 2;
        
        if (isset(self::$authData) && self::$authData) {
            $authId = self::$authData['id'] ?? null;
            $authInfo = self::$authData;
            $freeTrialCount = intval(self::$authData['free_trial_count'] ?? 0);
        }
        
        // 如果没有授权ID，尝试通过API获取授权记录（使用服务器IP）
        if (!$authId) {
            try {
                $url = LICENSE_API_URL . '/get_auth.php?ip=' . urlencode($serverIP) . '&domain=' . urlencode($domain);
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode == 200) {
                    $result = json_decode($response, true);
                    if ($result && isset($result['success']) && $result['success'] && isset($result['auth'])) {
                        $authId = $result['auth']['id'] ?? null;
                        $authInfo = $result['auth'];
                        $freeTrialCount = intval($result['auth']['free_trial_count'] ?? 0);
                    }
                }
            } catch (Exception $e) {
                // 忽略错误
            }
        }
        
        // 获取客服设置 - 默认为false，只有API明确返回开启才显示
        $wechatServiceEnabled = false;
        $groupQrcodeEnabled = false;
        $chatroomEnabled = false;
        
        try {
            // 通过API获取微信客服状态
            $apiUrl = LICENSE_API_URL . '/customer_service.php?action=get_wechat_info';
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $response = curl_exec($ch);
            curl_close($ch);
            
            if ($response) {
                $data = json_decode($response, true);
                // 只有当API返回成功且enabled为true时才显示
                if ($data && isset($data['success']) && $data['success'] === true) {
                    if (isset($data['data']['enabled']) && $data['data']['enabled'] === true) {
                        $wechatServiceEnabled = true;
                    } elseif (!isset($data['data']['enabled'])) {
                        // 兼容旧版API，没有enabled字段时默认开启
                        $wechatServiceEnabled = true;
                    }
                }
            }
        } catch (Exception $e) {
            // 忽略错误，保持关闭状态
        }
        
        // 获取微信群二维码状态
        try {
            $apiUrl = LICENSE_API_URL . '/customer_service.php?action=get_group_qrcode';
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $response = curl_exec($ch);
            curl_close($ch);
            
            if ($response) {
                $data = json_decode($response, true);
                // 只有当API返回成功且enabled为true时才显示
                if ($data && isset($data['success']) && $data['success'] === true) {
                    if (isset($data['data']['enabled']) && $data['data']['enabled'] === true) {
                        $groupQrcodeEnabled = true;
                    } elseif (!isset($data['data']['enabled'])) {
                        // 兼容旧版API，没有enabled字段时默认开启
                        $groupQrcodeEnabled = true;
                    }
                }
            }
        } catch (Exception $e) {
            // 忽略错误，保持关闭状态
        }
        
        // 获取聊天室状态
        try {
            $apiUrl = LICENSE_API_URL . '/customer_service.php?action=get_chatroom_status';
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $response = curl_exec($ch);
            curl_close($ch);
            
            if ($response) {
                $data = json_decode($response, true);
                // 只有当API返回成功且enabled为true时才显示
                if ($data && isset($data['data']['enabled']) && $data['data']['enabled'] === true) {
                    $chatroomEnabled = true;
                }
            }
        } catch (Exception $e) {
            // 忽略错误，保持关闭状态
        }
        
        // 获取支付模式
        $paymentMode = self::getPaymentMode();
        
        error_log("[授权调试] 支付模式: $paymentMode");
        
        // 获取支付页面URL
        $paymentUrl = self::getPaymentUrl();
        
        error_log("[授权调试] 支付URL: $paymentUrl");
        
        // 获取套餐列表
        $plans = self::getSubscriptionPlans();
        
        // 判断是否需要显示购买页面
        // 逻辑：
        // - 如果是过期状态且 free_trial_count <= 1（还有剩余次数）→ 显示免费申请
        // - 其他情况（free_trial_count >= 2 或次数用完）→ 显示购买页面
        
        // 初始化为false
        $shouldShowPurchasePage = false;
        
        // 如果次数已经用完，直接显示购买页面
        if ($isCountExhausted) {
            $shouldShowPurchasePage = true;
        } 
        // 如果是过期状态
        else if ($isExpired) {
            // 免费试用次数 >= 最大次数（第二次及以后过期）→ 显示购买页面
            if ($freeTrialCount >= $maxFreeTrialCount) {
                $shouldShowPurchasePage = true;
            } 
            // 免费试用次数 = 1（第二次过期）→ 显示订阅套餐页（不再提供免费续期）
            else if ($freeTrialCount == 1) {
                $shouldShowPurchasePage = true;
            }
            // 免费试用次数 = 0（第一次过期，还有剩余次数）→ 显示免费申请
            else if ($freeTrialCount == 0) {
                $isExpired = false;
                $shouldShowPurchasePage = false;
            }
        }
        // 如果未过期但免费试用次数已经用完 → 显示购买页面
        else if ($freeTrialCount >= $maxFreeTrialCount) {
            $shouldShowPurchasePage = true;
        }
        
        ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统授权</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: white; padding: 40px; border-radius: 16px; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.2); max-width: 480px; width: 100%; }
        .icon { font-size: 64px; margin-bottom: 20px; }
        h1 { color: #333; margin-bottom: 15px; font-size: 22px; }
        .success-msg { background: #d4edda; color: #155724; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .error-msg { background: #f8d7da; color: #721c24; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .form-group { margin-bottom: 18px; text-align: left; }
        .form-group label { display: block; margin-bottom: 6px; color: #333; font-size: 14px; font-weight: 500; }
        .form-group input { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; transition: border-color 0.3s; }
        .form-group input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: 500; cursor: pointer; transition: opacity 0.3s; }
        .btn:hover { opacity: 0.9; }
        .btn:active { transform: scale(0.98); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .footer { margin-top: 20px; font-size: 12px; color: #999; }
        .countdown { display: inline-block; background: #f5f5f5; padding: 4px 12px; border-radius: 20px; font-size: 12px; color: #666; margin-top: 10px; }
        
        /* 高端大气上档次的弹窗遮罩 */
        .modal-overlay { 
            position: fixed; 
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0; 
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(10px);
            display: flex; 
            align-items: center; 
            justify-content: center; 
            z-index: 1000; 
            padding: 20px; 
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* 高端大气上档次的弹窗容器 */
        .modal { 
            background: linear-gradient(145deg, #ffffff 0%, #f8f9ff 100%); 
            padding: 0; 
            border-radius: 16px; 
            max-width: 850px; 
            width: 90%;
            text-align: center; 
            box-shadow: 
                0 0 60px rgba(102, 126, 234, 0.3),
                0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: visible;
            animation: modalSlideIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        @keyframes modalSlideIn {
            from { 
                opacity: 0; 
                transform: scale(0.8) translateY(-30px); 
            }
            to { 
                opacity: 1; 
                transform: scale(1) translateY(0); 
            }
        }
        
        /* 右上角关闭按钮 */
        .modal-close-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 36px;
            height: 36px;
            background: linear-gradient(145deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            z-index: 10;
        }
        
        .modal-close-btn:hover {
            transform: rotate(90deg) scale(1.1);
            box-shadow: 0 6px 25px rgba(102, 126, 234, 0.6);
        }
        
        /* 弹窗头部装饰 */
        .modal-header-decoration {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 14px 20px 12px;
            position: relative;
            overflow: hidden;
        }
        
        .modal-header-decoration::before {
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
        
        .modal .warning-icon { 
            font-size: 36px; 
            margin-bottom: 6px;
            display: inline-block;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .modal h2 { 
            color: white; 
            margin-bottom: 6px; 
            font-size: 20px; 
            font-weight: 700;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .modal p { 
            color: rgba(255,255,255,0.95); 
            margin-bottom: 0; 
            line-height: 1.6;
            font-size: 13px;
        }
        
        .modal-content-wrapper {
            padding: 10px 14px 14px;
        }
        
        /* 用户信息卡片 - 高端版 */
        .user-info-card { 
            background: linear-gradient(145deg, #f0f4ff 0%, #e8ecff 100%); 
            border-radius: 10px; 
            padding: 10px 14px; 
            margin-bottom: 12px; 
            text-align: left;
            border: 1px solid rgba(102, 126, 234, 0.15);
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.08);
        }
        
        .user-info-card h3 { 
            color: #1a1a2e; 
            font-size: 14px; 
            margin-bottom: 8px; 
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .user-info-grid { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 8px; 
        }
        
        .user-info-item { 
            background: white; 
            padding: 8px 10px; 
            border-radius: 8px; 
            border: 1px solid rgba(102, 126, 234, 0.1);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: all 0.3s;
        }
        
        .user-info-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.15);
        }
        
        .user-info-item label { 
            display: block; 
            font-size: 10px; 
            color: #7f8c8d; 
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .user-info-item .value { 
            font-size: 12px; 
            color: #2c3e50; 
            font-weight: 600;
        }
        
        .expire-danger { 
            color: #e74c3c;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a5a 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* 套餐列表 - 高端豪华版 */
        .plans-container { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 12px; 
            margin-bottom: 12px; 
        }
        
        .plan-card { 
            background: linear-gradient(145deg, #ffffff 0%, #fafbff 100%); 
            border: 2px solid rgba(102, 126, 234, 0.15); 
            border-radius: 12px; 
            padding: 12px 10px; 
            text-align: center; 
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55); 
            position: relative; 
            cursor: pointer;
        }
        
        .plan-card:hover { 
            transform: translateY(-5px) scale(1.01); 
            border-color: #667eea; 
            box-shadow: 
                0 10px 20px rgba(102, 126, 234, 0.25),
                0 0 20px rgba(102, 126, 234, 0.12);
        }
        
        .plan-card.popular { 
            border-color: #667eea; 
            border-width: 3px;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.22);
        }
        
        .plan-card.popular::before { 
            content: '⭐ 热门'; 
            position: absolute; 
            top: -8px; 
            left: 50%; 
            transform: translateX(-50%); 
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); 
            color: white; 
            padding: 3px 10px; 
            border-radius: 15px; 
            font-size: 10px; 
            font-weight: 700; 
            box-shadow: 0 3px 10px rgba(240, 147, 251, 0.35);
            letter-spacing: 0.3px;
        }
        
        .plan-name { 
            font-size: 16px; 
            font-weight: 800; 
            color: #1a1a2e; 
            margin-bottom: 5px; 
            letter-spacing: -0.5px;
        }
        
        .plan-desc { 
            color: #7f8c8d; 
            font-size: 11px; 
            margin-bottom: 8px; 
            min-height: 26px; 
            line-height: 1.4;
        }
        
        .plan-price { 
            font-size: 26px; 
            font-weight: 800; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 4px; 
        }
        
        .plan-price small { 
            font-size: 12px; 
            font-weight: 500; 
        }
        
        .plan-duration { 
            color: #95a5a6; 
            font-size: 11px; 
            margin-bottom: 10px; 
            font-weight: 500;
        }
        
        .plan-btn { 
            display: block; 
            width: 100%; 
            padding: 9px 14px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            border: none; 
            border-radius: 10px; 
            font-size: 13px; 
            font-weight: 700; 
            cursor: pointer; 
            text-decoration: none; 
            transition: all 0.3s; 
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            letter-spacing: 0.5px;
        }
        
        .plan-btn:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }

        /* 右侧漂浮客服组件 */
        .floating-customer-service {
            position: fixed;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .floating-btn {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .floating-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .floating-btn::after {
            content: attr(data-tooltip);
            position: absolute;
            right: 70px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }

        .floating-btn:hover::after {
            opacity: 1;
        }

        .wechat-btn {
            background: linear-gradient(135deg, #07c160 0%, #06ad56 100%);
        }

        .group-btn {
            background: linear-gradient(135deg, #1890ff 0%, #096dd9 100%);
        }

        .chatroom-btn {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a5a 100%);
        }

        /* 弹窗样式 */
        .cs-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .cs-modal-overlay.active {
            display: flex;
        }

        .cs-modal {
            background: white;
            border-radius: 16px;
            max-width: 420px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: csModalIn 0.3s ease-out;
        }

        @keyframes csModalIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        .cs-modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .cs-modal-title {
            color: white;
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }

        .cs-modal-close {
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

        .cs-modal-close:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }

        .cs-modal-body {
            padding: 20px;
        }

        /* 微信咨询弹窗 */
        .wechat-info {
            text-align: center;
        }

        .wechat-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #07c160 0%, #06ad56 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 50px;
        }

        .wechat-id {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .wechat-nickname {
            color: #666;
            margin-bottom: 20px;
        }

        .copy-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #07c160 0%, #06ad56 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .copy-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(7,193,96,0.4);
        }

        /* 群二维码弹窗 */
        .qrcode-container {
            text-align: center;
        }

        .qrcode-img {
            width: 280px;
            height: 280px;
            border-radius: 12px;
            border: 4px solid #f0f0f0;
            margin-bottom: 16px;
            object-fit: contain;
            background: #f9f9f9;
        }

        .qrcode-tip {
            color: #666;
            font-size: 14px;
        }

        /* 聊天室弹窗 */
        .chatroom-container {
            display: flex;
            flex-direction: column;
            height: 500px;
        }

        .chatroom-header-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }

        .online-count {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #666;
            font-size: 13px;
        }

        .online-dot {
            width: 8px;
            height: 8px;
            background: #07c160;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            background: #f5f5f5;
        }

        .chat-message {
            margin-bottom: 12px;
            animation: messageIn 0.3s ease-out;
        }

        @keyframes messageIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .chat-message-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }

        .chat-nickname {
            font-size: 13px;
            font-weight: 600;
            color: #667eea;
        }

        .chat-time {
            font-size: 11px;
            color: #999;
        }

        .chat-content {
            background: white;
            padding: 10px 14px;
            border-radius: 12px;
            border-top-left-radius: 4px;
            display: inline-block;
            max-width: 80%;
            word-break: break-word;
            font-size: 14px;
            color: #333;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }

        .chat-input-area {
            padding: 16px;
            background: white;
            border-top: 1px solid #eee;
        }

        .chat-input-row {
            display: flex;
            gap: 10px;
        }

        .chat-input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #eee;
            border-radius: 24px;
            font-size: 14px;
            outline: none;
            transition: all 0.3s;
        }

        .chat-input:focus {
            border-color: #667eea;
        }

        .chat-send-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .chat-send-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(102,126,234,0.4);
        }

        .emoji-picker {
            display: flex;
            gap: 6px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .emoji-btn {
            width: 32px;
            height: 32px;
            border: none;
            background: #f5f5f5;
            border-radius: 6px;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.2s;
        }

        .emoji-btn:hover {
            background: #e8e8e8;
            transform: scale(1.1);
        }

        /* 响应式 */
        @media (max-width: 768px) {
            .floating-customer-service {
                left: 10px;
            }
            .floating-btn {
                width: 48px;
                height: 48px;
                font-size: 24px;
            }
            .chatroom-container {
                height: 400px;
            }
        }

        /* 通知弹窗样式 */
        .notification-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.6);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }
        .notification-modal-overlay.show {
            display: flex;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .notification-modal {
            background: white;
            border-radius: 16px;
            max-width: 600px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .notification-modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notification-modal-title {
            font-size: 20px;
            font-weight: 600;
        }
        .notification-modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .notification-modal-body {
            padding: 24px;
        }
        .notification-content {
            font-size: 15px;
            line-height: 1.8;
            color: #333;
        }
        .notification-modal-footer {
            padding: 20px 24px;
            border-top: 1px solid #e9ecef;
            text-align: center;
        }
        .notification-close-btn {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php if ($shouldShowPurchasePage): ?>
    <div class="modal-overlay" id="countModal">
        <div class="modal">
            <!-- 右上角关闭按钮 -->
            <button class="modal-close-btn" onclick="closeModal()">×</button>
            
            <!-- 头部装饰区域 -->
            <div class="modal-header-decoration">
                <div class="warning-icon">📉</div>
                <h2>您的授权过期了</h2>
                <p>您的授权过期了，请选择套餐付费授权</p>
            </div>
            
            <!-- 内容区域 -->
            <div class="modal-content-wrapper">
                <?php if ($authId && $authInfo): ?>
                <!-- 用户信息卡片 -->
                <div class="user-info-card">
                    <h3>📋 当前授权信息</h3>
                    <div class="user-info-grid">
                        <div class="user-info-item">
                            <label>用户姓名</label>
                            <div class="value"><?php echo htmlspecialchars($authInfo['name'] ?? ''); ?></div>
                        </div>
                        <div class="user-info-item">
                            <label>手机号</label>
                            <div class="value"><?php echo htmlspecialchars($authInfo['phone'] ?? ''); ?></div>
                        </div>
                        <div class="user-info-item">
                            <label>授权域名</label>
                            <div class="value"><?php echo htmlspecialchars($domain); ?></div>
                        </div>
                        <div class="user-info-item">
                            <label>当前状态</label>
                            <div class="value expire-danger">您的授权已过期</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($plans)): ?>
                <!-- 套餐列表 -->
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
                        <a href="<?php echo htmlspecialchars($paymentUrl); ?>?auth_id=<?php echo $authId; ?>&plan_id=<?php echo $plan['id']; ?>" class="plan-btn">
                            立即购买
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="color: #666; margin-bottom: 20px;">暂无可购套餐，请联系管理员</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php elseif ($isExpired): ?>
    <div class="modal-overlay" id="expireModal">
        <div class="modal">
            <!-- 右上角关闭按钮 -->
            <button class="modal-close-btn" onclick="closeModal()">×</button>
            
            <!-- 头部装饰区域 -->
            <div class="modal-header-decoration">
                <div class="warning-icon">⚠️</div>
                <h2>授权到期提醒</h2>
                <p>您的免费授权试用已经到期了，请选择套餐续费</p>
            </div>
            
            <!-- 内容区域 -->
            <div class="modal-content-wrapper">
                <?php if ($authId && $authInfo): ?>
                <!-- 用户信息卡片 -->
                <div class="user-info-card">
                    <h3>📋 当前授权信息</h3>
                    <div class="user-info-grid">
                        <div class="user-info-item">
                            <label>用户姓名</label>
                            <div class="value"><?php echo htmlspecialchars($authInfo['name'] ?? ''); ?></div>
                        </div>
                        <div class="user-info-item">
                            <label>手机号</label>
                            <div class="value"><?php echo htmlspecialchars($authInfo['phone'] ?? ''); ?></div>
                        </div>
                        <div class="user-info-item">
                            <label>授权域名</label>
                            <div class="value"><?php echo htmlspecialchars($domain); ?></div>
                        </div>
                        <div class="user-info-item">
                            <label>到期时间</label>
                            <div class="value expire-danger">
                                <?php echo isset($authInfo['expire_time']) ? date('Y-m-d H:i', strtotime($authInfo['expire_time'])) : '已过期'; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($plans)): ?>
                <!-- 套餐列表 -->
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
                        <a href="<?php echo htmlspecialchars($paymentUrl); ?>?auth_id=<?php echo $authId; ?>&plan_id=<?php echo $plan['id']; ?>" class="plan-btn">
                            立即购买
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="color: #666; margin-bottom: 20px;">暂无可购套餐，请联系管理员</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="container">
        <div class="icon">🔑</div>
        <h1>系统授权</h1>
        
        <?php if (!empty($message)): ?>
            <div class="error-msg"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($hasSubmitted): ?>
            <div class="success-msg">您的设备已提交过授权，请使用原手机号登录</div>
        <?php endif; ?>
        
        <form id="licenseForm" method="post" action="/admin/authorize.php">
            <input type="hidden" name="ip" id="serverIP" value="<?php echo htmlspecialchars($serverIP); ?>">
            <input type="hidden" name="domain" value="<?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'); ?>">
            
            <script>
            // 页面加载时尝试获取真实 IP
            (function() {
                // 方法1：通过 WebRTC 获取本地 IP
                function getLocalIP() {
                    return new Promise(function(resolve) {
                        var ip = '127.0.0.1';
                        try {
                            var RTCPeerConnection = window.RTCPeerConnection || window.webkitRTCPeerConnection || window.mozRTCPeerConnection;
                            if (RTCPeerConnection) {
                                var pc = new RTCPeerConnection({iceServers: []});
                                pc.createDataChannel('');
                                pc.onicecandidate = function(e) {
                                    if (!e || !e.candidate || !e.candidate.candidate) {
                                        pc.close();
                                        resolve(ip);
                                        return;
                                    }
                                    var matches = /([0-9]{1,3}(\.[0-9]{1,3}){3})/.exec(e.candidate.candidate);
                                    if (matches && matches[1]) {
                                        ip = matches[1];
                                    }
                                    pc.close();
                                    resolve(ip);
                                };
                                pc.createOffer().then(function(offer) {
                                    return pc.setLocalDescription(offer);
                                }).catch(function() {
                                    resolve(ip);
                                });
                            } else {
                                resolve(ip);
                            }
                        } catch (e) {
                            resolve(ip);
                        }
                    });
                }
                
                // 获取 IP 并更新表单
                getLocalIP().then(function(ip) {
                    var ipInput = document.getElementById('clientIP');
                    if (ipInput && ip !== '127.0.0.1' && ip !== '::1') {
                        ipInput.value = ip;
                        console.log('获取到真实 IP:', ip);
                    }
                });
            })();
            </script>
            
            <div class="form-group">
                <label>姓名</label>
                <input type="text" name="name" placeholder="请输入您的姓名" required>
            </div>
            
            <div class="form-group">
                <label>手机号</label>
                <input type="tel" name="phone" placeholder="请输入您的手机号" required pattern="1[3-9]\d{9}">
            </div>
            
            <div class="form-group">
                <label>电子邮箱 <span style="color: red;">*</span></label>
                <input type="email" name="wechat" placeholder="请输入您的电子邮箱" required>
            </div>
            
            <button type="submit" class="btn">立即授权</button>
            
            <div class="countdown">点击提交信息后即可免费授权使用小程序后台</div>
            <div style="color: #ef4444; font-size: 0.875rem; text-align: center; margin-top: 10px;">若提交的资料虚构作假，将永久失去授权资格</div>
        </form>
        
        <div class="footer">新会老余原创开发</div>
    </div>

    <!-- 右侧漂浮客服组件 -->
    <?php if ($wechatServiceEnabled || $groupQrcodeEnabled || $chatroomEnabled): ?>
    <div class="floating-customer-service">
        <?php if ($wechatServiceEnabled): ?>
        <button class="floating-btn wechat-btn" data-tooltip="微信咨询" onclick="openWechatModal()">
            💬
        </button>
        <?php endif; ?>
        <?php if ($groupQrcodeEnabled): ?>
        <button class="floating-btn group-btn" data-tooltip="微信群" onclick="openGroupModal()">
            👥
        </button>
        <?php endif; ?>
        <?php if ($chatroomEnabled): ?>
        <button class="floating-btn chatroom-btn" data-tooltip="在线聊天室" onclick="openChatroomModal()">
            🏠
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 微信咨询弹窗 -->
    <div class="cs-modal-overlay" id="wechatModal">
        <div class="cs-modal">
            <div class="cs-modal-header">
                <h3 class="cs-modal-title">微信咨询</h3>
                <button class="cs-modal-close" onclick="closeModal('wechatModal')">×</button>
            </div>
            <div class="cs-modal-body">
                <div class="wechat-info">
                    <!-- 客服切换按钮 -->
                    <div id="wechatSwitch" style="display: none; flex-direction: row; justify-content: center; gap: 10px; margin-bottom: 15px;">
                        <button onclick="switchWechat(-1)" style="width: 36px; height: 36px; border-radius: 50%; border: none; background: #f0f0f0; cursor: pointer; font-size: 16px;">←</button>
                        <button onclick="switchWechat(1)" style="width: 36px; height: 36px; border-radius: 50%; border: none; background: #f0f0f0; cursor: pointer; font-size: 16px;">→</button>
                    </div>
                    
                    <div class="wechat-avatar">👤</div>
                    <div class="wechat-id" id="wechatIdDisplay">加载中...</div>
                    <div class="wechat-nickname" id="wechatNicknameDisplay">客服</div>
                    
                    <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 20px;">
                        <button class="copy-btn" onclick="openWechat()">
                            📱 打开微信加好友
                        </button>
                        
                        <button class="copy-btn" onclick="copyWechatOnly()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            📋 仅复制微信号
                        </button>
                    </div>
                    
                    <div style="margin-top: 20px; text-align: left; background: #f5f5f5; padding: 16px; border-radius: 8px;">
                        <p style="margin: 0 0 8px 0; color: #333; font-weight: 600; font-size: 14px;">💡 添加步骤：</p>
                        <ol style="margin: 0; padding-left: 20px; color: #666; font-size: 13px; line-height: 1.8;">
                            <li>点击上方按钮，微信号会自动复制</li>
                            <li>打开微信 APP</li>
                            <li>点击右上角「+」号</li>
                            <li>选择「添加朋友」</li>
                            <li>粘贴微信号搜索</li>
                            <li>点击「添加到通讯录」</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 微信群弹窗 -->
    <div class="cs-modal-overlay" id="groupModal">
        <div class="cs-modal">
            <div class="cs-modal-header">
                <h3 class="cs-modal-title">加入微信群</h3>
                <button class="cs-modal-close" onclick="closeModal('groupModal')">×</button>
            </div>
            <div class="cs-modal-body">
                <div class="qrcode-container">
                    <img class="qrcode-img" id="groupQrcode" src="" alt="微信群二维码" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 200%22><rect fill=%22%23f0f0f0%22 width=%22200%22 height=%22200%22/><text x=%22100%22 y=%22100%22 text-anchor=%22middle%22 fill=%22%23999%22 font-size=%2216%22>暂无二维码</text></svg>'">
                    <p class="qrcode-tip" id="qrcodeTip">扫描二维码加入微信群</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 聊天室弹窗 -->
    <div class="cs-modal-overlay" id="chatroomModal">
        <div class="cs-modal" style="max-width: 480px;">
            <div class="cs-modal-header">
                <h3 class="cs-modal-title">在线聊天室</h3>
                <button class="cs-modal-close" onclick="closeModal('chatroomModal')">×</button>
            </div>
            <div class="chatroom-container">
                <div class="chatroom-header-info">
                    <div class="online-count">
                        <span class="online-dot"></span>
                        <span id="onlineCount">0</span> 人在线
                    </div>
                </div>
                <div class="chat-messages" id="chatMessages">
                    <!-- 消息将在这里显示 -->
                </div>
                <div class="chat-input-area">
                    <div class="chat-input-row">
                        <input type="text" class="chat-input" id="chatInput" placeholder="输入消息..." onkeypress="if(event.key==='Enter')sendMessage()">
                        <button class="chat-send-btn" onclick="sendMessage()">➤</button>
                    </div>
                    <div class="emoji-picker" id="emojiPicker">
                        <button class="emoji-btn" onclick="insertEmoji('😀')">😀</button>
                        <button class="emoji-btn" onclick="insertEmoji('😂')">😂</button>
                        <button class="emoji-btn" onclick="insertEmoji('❤️')">❤️</button>
                        <button class="emoji-btn" onclick="insertEmoji('👍')">👍</button>
                        <button class="emoji-btn" onclick="insertEmoji('🎉')">🎉</button>
                        <button class="emoji-btn" onclick="insertEmoji('🔥')">🔥</button>
                        <button class="emoji-btn" onclick="insertEmoji('💪')">💪</button>
                        <button class="emoji-btn" onclick="insertEmoji('😊')">😊</button>
                        <button class="emoji-btn" onclick="insertEmoji('🙏')">🙏</button>
                        <button class="emoji-btn" onclick="insertEmoji('👏')">👏</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // 使用授权系统的完整API地址（自动适配HTTP/HTTPS协议）
        <?php 
        $parsedUrl = parse_url(LICENSE_API_URL);
        $host = $parsedUrl['host'] ?? '';
        $path = $parsedUrl['path'] ?? '';
        ?>
        
        // 构建完整的客服API地址
        const LICENSE_API_HOST = '<?php echo $host; ?>';
        const LICENSE_API_PATH = '<?php echo $path; ?>';
        const API_BASE = window.location.protocol + '//' + LICENSE_API_HOST + LICENSE_API_PATH + '/customer_service.php';
        
        console.log('=== 漂浮组件API配置 ===');
        console.log('LICENSE_API_URL:', '<?php echo LICENSE_API_URL; ?>');
        console.log('API_BASE:', API_BASE);
        
        let wechatId = '';
        let chatSessionId = localStorage.getItem('chatSessionId') || '';
        let chatNickname = localStorage.getItem('chatNickname') || '';
        let lastMessageId = 0;
        let heartbeatInterval = null;
        let refreshInterval = null;

        document.getElementById('licenseForm').addEventListener('submit', function(e) {
            const btn = this.querySelector('button');
            btn.disabled = true;
            btn.textContent = '提交中...';
        });
        
        function closeModal(id) {
            if (id) {
                document.getElementById(id).classList.remove('active');
                if (id === 'chatroomModal' && heartbeatInterval) {
                    clearInterval(heartbeatInterval);
                    clearInterval(refreshInterval);
                    heartbeatInterval = null;
                    refreshInterval = null;
                }
            } else {
                const expireModal = document.getElementById('expireModal');
                const countModal = document.getElementById('countModal');
                if (expireModal) expireModal.style.display = 'none';
                if (countModal) countModal.style.display = 'none';
            }
        }

        // 当前选中的客服索引
        let currentWechatIndex = 0;
        let wechatList = [];
        
        // 微信咨询
        async function openWechatModal() {
            console.log('=== 打开微信咨询弹窗 ===');
            console.log('API地址:', `${API_BASE}?action=get_wechat_info`);
            
            try {
                const response = await fetch(`${API_BASE}?action=get_wechat_info`);
                console.log('HTTP状态码:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP错误: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('API响应:', result);
                
                if (result.success && result.data && result.data.wechats && result.data.wechats.length > 0) {
                    wechatList = result.data.wechats;
                    currentWechatIndex = 0;
                    wechatId = wechatList[0].wechat_id;
                    document.getElementById('wechatIdDisplay').textContent = wechatId;
                    document.getElementById('wechatNicknameDisplay').textContent = wechatList[0].wechat_nickname || '客服';
                    
                    // 更新头像
                    if (wechatList[0].avatar) {
                        document.querySelector('.wechat-avatar').innerHTML = `<img src="${wechatList[0].avatar}" style="width: 100%; height: 100%; border-radius: 50%;">`;
                    } else {
                        document.querySelector('.wechat-avatar').textContent = '👤';
                    }
                    
                    // 显示客服切换按钮（如果有多个客服）
                    if (wechatList.length > 1) {
                        document.getElementById('wechatSwitch').style.display = 'block';
                    } else {
                        document.getElementById('wechatSwitch').style.display = 'none';
                    }
                    
                    console.log('✅ 成功获取客服信息');
                } else {
                    // API返回失败或数据为空，使用默认微信号
                    console.log('⚠️ API返回失败或数据为空，使用默认配置');
                    useDefaultWechat();
                }
            } catch (e) {
                // 请求失败，使用默认微信号
                console.error('❌ 获取客服信息失败:', e.message);
                useDefaultWechat();
            }
            document.getElementById('wechatModal').classList.add('active');
        }
        
        function useDefaultWechat() {
            wechatId = 'jmxhywc';
            document.getElementById('wechatIdDisplay').textContent = wechatId;
            document.getElementById('wechatNicknameDisplay').textContent = '客服小宇';
            document.querySelector('.wechat-avatar').textContent = '👤';
            document.getElementById('wechatSwitch').style.display = 'none';
            wechatList = [];
        }
        
        // 切换客服
        function switchWechat(direction) {
            console.log('=== 切换客服 ===');
            console.log('当前索引:', currentWechatIndex, '方向:', direction, '客服列表长度:', wechatList.length);
            
            if (wechatList.length < 2) {
                console.log('⚠️ 客服数量不足，无法切换');
                return;
            }
            
            currentWechatIndex += direction;
            if (currentWechatIndex < 0) {
                currentWechatIndex = wechatList.length - 1;
            }
            if (currentWechatIndex >= wechatList.length) {
                currentWechatIndex = 0;
            }
            
            const wechat = wechatList[currentWechatIndex];
            wechatId = wechat.wechat_id;
            document.getElementById('wechatIdDisplay').textContent = wechatId;
            document.getElementById('wechatNicknameDisplay').textContent = wechat.wechat_nickname || '客服';
            
            if (wechat.avatar) {
                document.querySelector('.wechat-avatar').innerHTML = `<img src="${wechat.avatar}" style="width: 100%; height: 100%; border-radius: 50%;">`;
            } else {
                document.querySelector('.wechat-avatar').textContent = '👤';
            }
            
            console.log('✅ 切换到客服:', wechat.wechat_nickname);
        }

        // 复制文本到剪贴板（兼容所有浏览器）
        function copyToClipboard(text) {
            return new Promise(function(resolve, reject) {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(resolve).catch(reject);
                } else {
                    // 降级方案：创建临时文本框
                    var textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.style.position = 'fixed';
                    textarea.style.left = '-9999px';
                    textarea.style.top = '-9999px';
                    textarea.style.width = '1px';
                    textarea.style.height = '1px';
                    document.body.appendChild(textarea);
                    textarea.select();
                    try {
                        var successful = document.execCommand('copy');
                        if (successful) {
                            document.body.removeChild(textarea);
                            resolve();
                        } else {
                            document.body.removeChild(textarea);
                            reject(new Error('复制失败'));
                        }
                    } catch (err) {
                        document.body.removeChild(textarea);
                        reject(err);
                    }
                }
            });
        }

        function openWechat() {
            // 检查微信号是否已加载
            if (!wechatId || wechatId === '') {
                alert('微信号正在加载中，请稍候...');
                return;
            }
            
            // 首先复制微信号
            copyToClipboard(wechatId).then(() => {
                // 判断设备类型
                const ua = navigator.userAgent;
                const isMobile = /Android|iPhone|iPad|iPod|webOS|BlackBerry|IEMobile|Opera Mini|Harmony/i.test(ua);
                const isWechat = /MicroMessenger/i.test(ua);
                const isIOS = /iPhone|iPad|iPod/i.test(ua);
                const isAndroid = /Android/i.test(ua);
                const isHarmony = /Harmony/i.test(ua);
                
                if (isWechat) {
                    // 已经在微信里
                    alert(`微信号【${wechatId}】已复制！\n\n请点击右上角「+」号 -> 添加朋友 -> 粘贴搜索即可`);
                } else if (isMobile) {
                    // 手机端
                    alert(`微信号【${wechatId}】已复制！\n\n正在尝试打开微信，请稍候...\n\n如果微信没有自动打开，请手动打开微信，点击「添加朋友」，粘贴搜索即可`);
                    
                    try {
                        // 创建隐藏的iframe用于尝试唤起
                        function tryOpenApp(url, timeout = 2000) {
                            return new Promise((resolve) => {
                                const iframe = document.createElement('iframe');
                                iframe.style.display = 'none';
                                iframe.src = url;
                                document.body.appendChild(iframe);
                                
                                const timer = setTimeout(() => {
                                    document.body.removeChild(iframe);
                                    resolve(false);
                                }, timeout);
                                
                                const blurHandler = () => {
                                    clearTimeout(timer);
                                    document.body.removeChild(iframe);
                                    resolve(true);
                                };
                                
                                window.addEventListener('blur', blurHandler, { once: true });
                                
                                setTimeout(() => {
                                    window.removeEventListener('blur', blurHandler);
                                }, timeout + 100);
                            });
                        }
                        
                        // 构建所有可能的唤起URL列表
                        const wechatUrls = [
                            // 微信标准协议 - 添加好友
                            'weixin://dl/addfriend?uin=' + encodeURIComponent(wechatId),
                            'weixin://dl/addfriend?to=' + encodeURIComponent(wechatId),
                            'weixin://dl/addfriend?id=' + encodeURIComponent(wechatId),
                            'weixin://dl/addfriend?username=' + encodeURIComponent(wechatId),
                            'weixin://addfriend/' + encodeURIComponent(wechatId),
                            
                            // 微信标准协议 - 打开聊天
                            'weixin://dl/chat?to=' + encodeURIComponent(wechatId),
                            'weixin://dl/chat?username=' + encodeURIComponent(wechatId),
                            'weixin://chat/' + encodeURIComponent(wechatId),
                            
                            // 微信标准协议 - 打开个人资料
                            'weixin://dl/profile?uin=' + encodeURIComponent(wechatId),
                            'weixin://dl/profile?id=' + encodeURIComponent(wechatId),
                            'weixin://dl/profile?username=' + encodeURIComponent(wechatId),
                            'weixin://profile/' + encodeURIComponent(wechatId),
                            
                            // 备用协议
                            'weixin://dl/friends',
                            'weixin://dl/contact',
                            'weixin://dl/message',
                            'weixin://dl/app',
                            'weixin://',
                            
                            // Android 深层链接
                            'intent://dl/addfriend?uin=' + encodeURIComponent(wechatId) + '#Intent;scheme=weixin;package=com.tencent.mm;end',
                            'intent://dl/profile?uin=' + encodeURIComponent(wechatId) + '#Intent;scheme=weixin;package=com.tencent.mm;end',
                            
                            // iOS Universal Links
                            'https://u.weixin.qq.com/cgi-bin/readtemplate?t=page/contact&uin=' + encodeURIComponent(wechatId),
                            'https://open.weixin.qq.com/qr/code?username=' + encodeURIComponent(wechatId),
                            
                            // 应用宝跳转（兜底方案）
                            'https://a.app.qq.com/o/simple.jsp?pkgname=com.tencent.mm&g_f=991653'
                        ];
                        
                        // 按优先级排序（iOS优先Universal Links，Android优先intent）
                        let prioritizedUrls = [];
                        if (isIOS) {
                            prioritizedUrls = wechatUrls.filter(u => u.startsWith('https://u.weixin') || u.startsWith('https://open.weixin'))
                                                    .concat(wechatUrls.filter(u => u.startsWith('weixin://')));
                        } else if (isAndroid || isHarmony) {
                            prioritizedUrls = wechatUrls.filter(u => u.startsWith('intent://'))
                                                    .concat(wechatUrls.filter(u => u.startsWith('weixin://')));
                        } else {
                            prioritizedUrls = wechatUrls;
                        }
                        
                        // 依次尝试唤起
                        let index = 0;
                        const tryNext = async () => {
                            if (index >= prioritizedUrls.length) return;
                            
                            const url = prioritizedUrls[index];
                            index++;
                            
                            console.log('Trying to open WeChat:', url);
                            
                            try {
                                if (url.startsWith('intent://')) {
                                    // Android intent 方案
                                    window.location.href = url;
                                } else if (url.startsWith('https://') && (isIOS || isHarmony)) {
                                    // iOS/Harmony Universal Links
                                    window.location.href = url;
                                } else {
                                    // 其他协议
                                    window.location.href = url;
                                }
                                
                                // 等待一段时间检查是否唤起成功
                                setTimeout(tryNext, 1500);
                            } catch (e) {
                                console.error('Failed to open:', url, e);
                                tryNext();
                            }
                        };
                        
                        // 立即开始尝试
                        tryNext();
                        
                    } catch (e) {
                        console.error('WeChat open error:', e);
                    }
                } else {
                    // PC端
                    alert(`微信号【${wechatId}】已复制到剪贴板！\n\n请打开微信 -> 点击「添加朋友」-> 粘贴搜索即可\n\n正在尝试自动打开微信...`);
                    
                    try {
                        // 尝试PC端微信唤起
                        let pcAttempts = [
                            'weixin://dl/addfriend',
                            'weixin://dl/addfriend?id=' + encodeURIComponent(wechatId),
                            'weixin://dl/profile?id=' + encodeURIComponent(wechatId),
                            'weixin://dl/profile',
                            'weixin://dl/chat',
                            'weixin://dl/friends',
                            'weixin://'
                        ];
                        
                        pcAttempts.forEach((url, index) => {
                            setTimeout(() => {
                                try {
                                    console.log('PC Trying:', url);
                                    window.location.href = url;
                                } catch (e) {}
                            }, 200 + index * 600);
                        });
                    } catch (e) {}
                }
            }).catch(() => {
                alert(`请手动复制微信号添加：${wechatId}`);
            });
        }

        function copyWechatOnly() {
            if (!wechatId || wechatId === '') {
                alert('微信号正在加载中，请稍候...');
                return;
            }
            
            copyToClipboard(wechatId).then(() => {
                alert(`微信号【${wechatId}】已成功复制到剪贴板！\n\n请去微信里添加好友`);
            }).catch(() => {
                alert(`请手动复制微信号：${wechatId}`);
            });
        }

        // 微信群
        async function openGroupModal() {
            try {
                const response = await fetch(`${API_BASE}?action=get_group_qrcode`);
                const result = await response.json();
                if (result.success) {
                    document.getElementById('groupQrcode').src = result.data.qrcode_url;
                    const expireTime = new Date(result.data.expire_time);
                    const now = new Date();
                    const daysLeft = Math.ceil((expireTime - now) / (1000 * 60 * 60 * 24));
                    document.getElementById('qrcodeTip').textContent = `二维码有效期还剩 ${daysLeft} 天`;
                } else {
                    document.getElementById('qrcodeTip').textContent = result.message || '暂无可用群二维码';
                }
            } catch (e) {
                document.getElementById('qrcodeTip').textContent = '加载二维码失败';
            }
            document.getElementById('groupModal').classList.add('active');
        }

        // 聊天室
        async function openChatroomModal() {
            document.getElementById('chatroomModal').classList.add('active');
            
            if (!chatNickname) {
                chatNickname = '游客' + Math.floor(Math.random() * 9000 + 1000);
                localStorage.setItem('chatNickname', chatNickname);
            }
            
            await loadMessages();
            await updateOnlineCount();
            
            heartbeatInterval = setInterval(updateOnlineCount, 10000);
            refreshInterval = setInterval(loadMessages, 3000);
        }

        async function loadMessages() {
            try {
                const response = await fetch(`${API_BASE}?action=get_chatroom_messages`);
                const result = await response.json();
                if (result.success) {
                    renderMessages(result.data);
                }
            } catch (e) {
                console.error(e);
            }
        }

        function renderMessages(messages) {
            const container = document.getElementById('chatMessages');
            container.innerHTML = '';
            messages.forEach(msg => {
                const time = new Date(msg.created_at).toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' });
                const div = document.createElement('div');
                div.className = 'chat-message';
                div.innerHTML = `
                    <div class="chat-message-header">
                        <span class="chat-nickname">${escapeHtml(msg.nickname)}</span>
                        <span class="chat-time">${time}</span>
                    </div>
                    <div class="chat-content">${escapeHtml(msg.content)}</div>
                `;
                container.appendChild(div);
            });
            container.scrollTop = container.scrollHeight;
        }

        async function sendMessage() {
            const input = document.getElementById('chatInput');
            const content = input.value.trim();
            if (!content) return;
            
            input.value = '';
            
            try {
                await fetch(`${API_BASE}?action=send_chatroom_message`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        nickname: chatNickname,
                        content: content,
                        message_type: 'text'
                    })
                });
                await loadMessages();
            } catch (e) {
                console.error(e);
            }
        }

        function insertEmoji(emoji) {
            const input = document.getElementById('chatInput');
            input.value += emoji;
            input.focus();
        }

        async function updateOnlineCount() {
            try {
                const response = await fetch(`${API_BASE}?action=heartbeat`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        session_id: chatSessionId,
                        nickname: chatNickname
                    })
                });
                const result = await response.json();
                if (result.success) {
                    document.getElementById('onlineCount').textContent = result.data.count;
                    if (!chatSessionId && result.data.session_id) {
                        chatSessionId = result.data.session_id;
                        localStorage.setItem('chatSessionId', chatSessionId);
                    }
                }
            } catch (e) {
                console.error(e);
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        document.querySelectorAll('.cs-modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    closeModal(overlay.id);
                }
            });
        });

        // ==================== 通知弹窗功能 ====================
        function checkNotification(reset = false) {
            console.log('=== 检查通知 ===');
            // 使用授权系统的完整API地址，并传递auth_id和phone
            let url = '<?php echo LICENSE_API_URL; ?>/get_notification.php?source=admin';
            <?php if ($authId): ?>
                url += '&auth_id=<?php echo $authId; ?>';
            <?php endif; ?>
            <?php if (!empty($authInfo['phone'])): ?>
                url += '&phone=<?php echo urlencode($authInfo['phone']); ?>';
            <?php endif; ?>
            if (reset) {
                url += '&reset=1';
            }
            
            console.log('通知API地址:', url);
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    console.log('通知数据:', data);
                    if (data.debug) {
                        console.log('调试信息:', data.debug);
                    }
                    
                    if (data.code === 200) {
                        if (data.show && data.notification) {
                            // 检查是否需要延迟显示
                            const delay = data.delay || 0;
                            if (delay > 0) {
                                console.log(`通知延迟显示，延迟时间: ${delay}秒`);
                                setTimeout(() => {
                                    showNotificationModal(data.notification, data.attachments || []);
                                }, delay * 1000);
                            } else {
                                showNotificationModal(data.notification, data.attachments || []);
                            }
                        } else {
                            console.log('不需要显示通知:', data.message || '没有通知');
                        }
                    } else {
                        console.error('通知API返回错误:', data.message);
                    }
                })
                .catch(error => {
                    console.error('获取通知失败:', error);
                });
        }

        function showNotificationModal(notification, attachments = []) {
            console.log('=== 显示通知弹窗 ===');
            console.log('通知内容:', notification);
            console.log('附件列表:', attachments);
            
            // 创建通知弹窗
            const modalOverlay = document.createElement('div');
            modalOverlay.className = 'notification-modal-overlay';
            modalOverlay.id = 'notificationModal';
            
            // 构建附件HTML
            let attachmentsHtml = '';
            if (attachments && attachments.length > 0) {
                attachmentsHtml = `
                    <div class="notification-attachments">
                        <h4>附件下载：</h4>
                        <ul>
                            ${attachments.map(attach => `
                                <li>
                                    <a href="${attach.file_url}" target="_blank" download>${attach.file_name}</a>
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                `;
            }
            
            // 构建下载按钮
            let downloadButton = '';
            if (notification.download_url) {
                downloadButton = `
                    <a href="${notification.download_url}" target="_blank" class="notification-download-btn">立即下载</a>
                `;
            }
            
            modalOverlay.innerHTML = `
                <div class="notification-modal">
                    <div class="notification-modal-header">
                        <span class="notification-modal-title">${notification.title}</span>
                        <button class="notification-modal-close" onclick="closeNotificationModal(${notification.id})">&times;</button>
                    </div>
                    <div class="notification-modal-body">
                        <div class="notification-content">${notification.content}</div>
                        ${attachmentsHtml}
                    </div>
                    <div class="notification-modal-footer">
                        ${downloadButton}
                        <button class="notification-close-btn" onclick="closeNotificationModal(${notification.id})">我知道了</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modalOverlay);
            modalOverlay.classList.add('show');
            
            // 添加点击遮罩关闭
            modalOverlay.addEventListener('click', (e) => {
                if (e.target === modalOverlay) {
                    closeNotificationModal();
                }
            });
        }

        function closeNotificationModal(notificationId = null) {
            console.log('=== 关闭通知弹窗 ===');
            console.log('通知ID:', notificationId);
            
            // 记录关闭状态到服务器
            if (notificationId) {
                let url = '<?php echo LICENSE_API_URL; ?>/close_notification.php';
                <?php if ($authId): ?>
                    url += '?auth_id=<?php echo $authId; ?>';
                <?php endif; ?>
                url += '&notification_id=' + notificationId;
                
                fetch(url, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    console.log('通知关闭状态已记录:', data);
                })
                .catch(error => {
                    console.error('记录通知关闭状态失败:', error);
                });
            }
            
            // 关闭弹窗
            const modal = document.getElementById('notificationModal');
            if (modal) {
                modal.classList.remove('show');
                setTimeout(() => {
                    modal.remove();
                }, 300);
            }
        }

        // 页面加载完成后检查通知
        document.addEventListener('DOMContentLoaded', checkNotification);
    </script>

    <style>
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
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        .subscription-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
    </style>

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
                        <input type="email" id="subUserWechat" placeholder="请输入您的电子邮箱" required>
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
            return ['success' => false, 'message' => '无法连接授权服务器', 'is_expired' => false];
        }
        
        $result = json_decode($response, true);
        if (!$result) {
            return ['success' => false, 'message' => '授权服务器响应错误', 'is_expired' => false];
        }
        
        if ($result['code'] == 200) {
            return ['success' => true, 'code' => 200, 'message' => $result['message'], 'data' => $result['data'], 'is_expired' => false];
        }
        
        if ($result['code'] == 401) {
            return ['success' => false, 'code' => 401, 'message' => $result['message'], 'data' => $result['data'], 'is_expired' => true];
        }
        
        // 其他错误码（403禁用、402次数用完等）也需要返回data字段
        // 注意：新用户时 data=null，不能转成 []
        return ['success' => false, 'code' => $result['code'] ?? 403, 'message' => $result['message'] ?? '验证失败', 'data' => $result['data'], 'is_expired' => false];
    }

    private static function showDelayedUnauthorizedPage($delaySeconds)
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        $domain = $_SERVER['HTTP_HOST'] ?? 'unknown';
        
        // 确保 domain 绝对不为空
        if (empty($domain)) {
            if (!empty($_SERVER['HTTP_HOST'])) {
                $domain = $_SERVER['HTTP_HOST'];
            } elseif (!empty($_SERVER['SERVER_NAME'])) {
                $domain = $_SERVER['SERVER_NAME'];
            } else {
                $domain = 'localhost';
            }
        }
        
        // 二次防护：确保即使是空字符串也能处理
        if (empty($domain) || $domain === '') {
            $domain = 'localhost';
        }
        
        ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统授权 - 延迟等待</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: white; padding: 40px; border-radius: 16px; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.2); max-width: 400px; width: 100%; }
        .icon { font-size: 64px; margin-bottom: 20px; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1); } }
        h1 { color: #333; margin-bottom: 15px; font-size: 22px; }
        .countdown { font-size: 36px; font-weight: 700; color: #667eea; margin: 20px 0; }
        .progress-bar { height: 6px; background: #e2e8f0; border-radius: 3px; margin-top: 20px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); border-radius: 3px; transition: width 1s linear; }
        .message { color: #666; font-size: 14px; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">⏳</div>
        <h1>正在验证授权...</h1>
        <div class="countdown" id="countdown"><?php echo $delaySeconds; ?></div>
        <div class="progress-bar">
            <div class="progress-fill" id="progress" style="width: 0%"></div>
        </div>
        <div class="message">系统正在验证您的访问权限，请稍候...</div>
    </div>

    <script>
        var remainingSeconds = <?php echo $delaySeconds; ?>;
        var totalSeconds = <?php echo $delaySeconds; ?>;
        var countdownElement = document.getElementById('countdown');
        var progressElement = document.getElementById('progress');
        
        var timer = setInterval(function() {
            remainingSeconds--;
            countdownElement.textContent = remainingSeconds;
            
            var progress = ((totalSeconds - remainingSeconds) / totalSeconds) * 100;
            progressElement.style.width = progress + '%';
            
            if (remainingSeconds <= 0) {
                clearInterval(timer);
                window.location.href = '/admin/login.php?force_auth=1';
            }
        }, 1000);
    </script>
</body>
</html>
        <?php
        exit;
    }
    
    private static function checkSubmitted($ip, $domain)
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        return $result && $result['code'] == 200;
    }

    private static function bindDomainIfNeeded($ip, $domain)
    {
        $url = LICENSE_API_URL . '/free_verify.php';
        
        $postData = [
            'action' => 'bind_domain',
            'ip' => $ip,
            'domain' => $domain
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        curl_exec($ch);
        curl_close($ch);
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
     * 获取服务器公网IP（用于授权绑定）
     * 使用文件缓存 + 短超时，避免阻塞页面加载
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
            // 7天内的缓存直接使用
            if ($cacheAge < 604800) {
                $cachedValue = trim(file_get_contents($cacheFile));
                if (filter_var($cachedValue, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !self::isPrivateIP($cachedValue)) {
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
                        if (filter_var($result, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !self::isPrivateIP($result)) {
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
        
        // 方法3：fallback - 使用SERVER_ADDR（服务器自己的IP）
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
        
        // 方法5：最后的fallback - 使用访客IP（确保系统不卡）
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
    
    /**
     * 获取授权数据
     */
    public static function getAuthData()
    {
        return self::$authData;
    }
    
    /**
     * 获取授权用户ID
     */
    public static function getAuthId()
    {
        return self::$authData['id'] ?? null;
    }
    
    /**
     * 获取剩余延迟时间（秒）
     */
    public static function getRemainingDelay()
    {
        return self::$remainingDelay;
    }
    
    /**
     * 生成服务器端指纹（用于首次访问没有指纹时）
     */
    private static function generateServerFingerprint()
    {
        // 基于服务器信息生成唯一指纹（使用服务器IP，不是访客IP）
        $serverInfo = [
            'ip' => self::getServerPublicIP(),
            'server_name' => $_SERVER['SERVER_NAME'] ?? '',
            'server_addr' => $_SERVER['SERVER_ADDR'] ?? '',
            'timezone' => date_default_timezone_get(),
            'timestamp' => time(),
            'random' => mt_rand(100000, 999999)
        ];
        
        return hash('sha256', json_encode($serverInfo));
    }
    
    /**
     * 从授权系统获取首次访问时间
     */
    private static function getFirstAccessTimeFromLicenseSystem($fingerprint)
    {
        if (empty($fingerprint)) {
            return null;
        }
        
        try {
            require_once '../config/api_urls_protected.php';
            
            if (!defined('API_URL_LICENSE')) {
                error_log("[授权调试] API_URL_LICENSE 未定义");
                return null;
            }
            
            $apiUrl = API_URL_LICENSE . '/api/register_fingerprint.php';
            error_log("[授权调试] 尝试连接授权系统: " . $apiUrl);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'fingerprint' => $fingerprint,
                'domain' => $_SERVER['HTTP_HOST'],
                'ip' => $_SERVER['REMOTE_ADDR']
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                error_log("[授权调试] 授权系统连接失败: " . $error);
                return null;
            }
            
            if ($httpCode != 200) {
                error_log("[授权调试] 授权系统HTTP错误: " . $httpCode);
                return null;
            }
            
            if (!$response) {
                error_log("[授权调试] 授权系统返回空");
                return null;
            }
            
            $data = json_decode($response, true);
            if (!$data || !$data['success'] || !isset($data['first_access_time'])) {
                error_log("[授权调试] 授权系统返回无效数据: " . $response);
                return null;
            }
            
            error_log("[授权调试] ✅ 从授权系统获取首次访问时间: " . date('Y-m-d H:i:s', $data['first_access_time']));
            return $data['first_access_time'];
            
        } catch (Exception $e) {
            error_log("[授权调试] 连接授权系统异常: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 获取PDO数据库连接（多方式尝试）
     */
    private static function getPDOConnection()
    {
        // 方式1：尝试使用外部函数
        if (function_exists('get_db_connection')) {
            try {
                $pdo = get_db_connection();
                if ($pdo instanceof PDO) {
                    return $pdo;
                }
            } catch (Exception $e) {
                error_log("[授权调试] get_db_connection() 失败: " . $e->getMessage());
            }
        }
        
        // 方式2：尝试使用硬编码连接（本地测试）
        try {
            $pdo = new PDO(
                "mysql:host=localhost;dbname=license;charset=utf8mb4",
                "license",
                "license",
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            return $pdo;
        } catch (Exception $e) {
            error_log("[授权调试] 硬编码连接失败: " . $e->getMessage());
        }
        
        // 方式3：尝试使用另一种常见配置
        try {
            $pdo = new PDO(
                "mysql:host=127.0.0.1;dbname=365xiang_cn;charset=utf8mb4",
                "root",
                "",
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            return $pdo;
        } catch (Exception $e) {
            error_log("[授权调试] 备用连接失败: " . $e->getMessage());
        }
        
        return null;
    }
}
?>