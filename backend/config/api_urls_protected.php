<?php
// ============================================================================
// 【灵码修复】配置文件保护系统（完整版）
// 修复日期: 2026-05-31
// ============================================================================

// 立即检测自身完整性
$selfFile = __FILE__;
$selfHashFile = __DIR__ . '/api_urls_protected_hash.dat';

if (!file_exists($selfHashFile)) {
    $errorPage = '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>系统保护已激活</title><style>body{font-family:Microsoft YaHei;background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}.error-container{text-align:center;padding:60px;background:rgba(231,76,60,0.1);border-radius:30px;border:2px solid rgba(231,76,60,0.4);max-width:600px;box-shadow:0 20px 60px rgba(0,0,0,0.5);}.error-icon{font-size:80px;margin-bottom:20px;}.error-title{color:#e74c3c;font-size:28px;margin-bottom:15px;}.error-message{color:rgba(255,255,255,0.8);line-height:1.8;font-size:16px;}.layer-info{background:rgba(0,0,0,0.3);padding:10px 20px;border-radius:8px;margin-top:20px;font-family:monospace;font-size:14px;color:#f39c12;}</style></head><body><div class="error-container"><div class="error-icon">🛡️</div><h1 class="error-title">系统保护已激活</h1><p class="error-message">检测到配置文件异常，系统已停止运行以保护数据安全。</p><p class="error-message">请联系系统管理员进行修复。</p><div class="layer-info">[LAYER_000] 保护系统哈希文件缺失</div></div></body></html>';
    echo $errorPage;
    exit(1);
}

$expectedSelfHash = trim(file_get_contents($selfHashFile));
$actualSelfHash = hash_file('sha256', $selfFile);

if ($actualSelfHash !== $expectedSelfHash) {
    $errorPage = '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>系统保护已激活</title><style>body{font-family:Microsoft YaHei;background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}.error-container{text-align:center;padding:60px;background:rgba(231,76,60,0.1);border-radius:30px;border:2px solid rgba(231,76,60,0.4);max-width:600px;box-shadow:0 20px 60px rgba(0,0,0,0.5);}.error-icon{font-size:80px;margin-bottom:20px;}.error-title{color:#e74c3c;font-size:28px;margin-bottom:15px;}.error-message{color:rgba(255,255,255,0.8);line-height:1.8;font-size:16px;}.layer-info{background:rgba(0,0,0,0.3);padding:10px 20px;border-radius:8px;margin-top:20px;font-family:monospace;font-size:14px;color:#f39c12;}</style></head><body><div class="error-container"><div class="error-icon">🛡️</div><h1 class="error-title">系统保护已激活</h1><p class="error-message">检测到配置文件异常，系统已停止运行以保护数据安全。</p><p class="error-message">请联系系统管理员进行修复。</p><div class="layer-info">[LAYER_000] 保护系统文件已被修改</div></div></body></html>';
    echo $errorPage;
    exit(1);
}

function protection_alert($layer, $message) {
    $logPath = __DIR__ . '/../logs/protection_alert.log';
    $logDir = dirname($logPath);
    if (!file_exists($logDir)) { mkdir($logDir, 0755, true); }
    $logEntry = date('Y-m-d H:i:s') . " [ALERT] [LAYER_$layer] " . $message . "\n";
    file_put_contents($logPath, $logEntry, FILE_APPEND);
}

function protection_fatal_error($layer, $message) {
    protection_alert($layer, $message);
    if (!headers_sent()) { http_response_code(503); header('Content-Type: text/html; charset=utf-8'); }
    
    $errorPage = '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>系统保护已激活</title><style>body{font-family:Microsoft YaHei;background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}.error-container{text-align:center;padding:60px;background:rgba(231,76,60,0.1);border-radius:30px;border:2px solid rgba(231,76,60,0.4);max-width:600px;box-shadow:0 20px 60px rgba(0,0,0,0.5);}.error-icon{font-size:80px;margin-bottom:20px;}.error-title{color:#e74c3c;font-size:28px;margin-bottom:15px;}.error-message{color:rgba(255,255,255,0.8);line-height:1.8;font-size:16px;}.layer-info{background:rgba(0,0,0,0.3);padding:10px 20px;border-radius:8px;margin-top:20px;font-family:monospace;font-size:14px;color:#f39c12;}</style></head><body><div class="error-container"><div class="error-icon">🛡️</div><h1 class="error-title">系统保护已激活</h1><p class="error-message">检测到配置文件异常，系统已停止运行以保护数据安全。</p><p class="error-message">请联系系统管理员进行修复。</p><div class="layer-info">[LAYER_$layer]</div></div></body></html>';
    echo $errorPage;
    exit(1);
}

function layer1_check_file_exists($filePath) { if (!file_exists($filePath)) { protection_fatal_error('001', '配置文件不存在: ' . $filePath); } return true; }
function layer2_check_file_size($filePath, $minSize = 100) { $fileSize = filesize($filePath); if ($fileSize < $minSize) { protection_fatal_error('002', '配置文件过小: ' . $filePath . ' (' . $fileSize . ' bytes)'); } return true; }
function layer3_check_file_permissions($filePath) { if (!preg_match('/\.php$/', $filePath) && !preg_match('/\.dat$/', $filePath)) { protection_fatal_error('003', '配置文件类型错误: ' . $filePath); } return true; }
function layer5_check_integrity($configFile, $hashFile) { if (!file_exists($hashFile)) { protection_fatal_error('005', '哈希校验文件缺失: ' . $hashFile); } $expectedHash = trim(file_get_contents($hashFile)); $actualHash = hash_file('sha256', $configFile); if ($actualHash !== $expectedHash) { protection_fatal_error('005', '配置文件完整性校验失败'); } return true; }
function layer6_check_string_integrity($content, $checkStrings) { foreach ($checkStrings as $str) { if (strpos($content, $str) === false) { protection_fatal_error('006', '配置文件缺少必要内容: ' . $str); } } return true; }
function layer7_decrypt_config($encryptedData, $key) { $data = base64_decode($encryptedData); if ($data === false) { protection_fatal_error('007', 'Base64解码失败'); } $ivLength = openssl_cipher_iv_length('AES-256-CBC'); $iv = substr($data, 0, $ivLength); $encrypted = substr($data, $ivLength); $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv); if ($decrypted === false) { protection_fatal_error('007', '配置文件解密失败'); } return $decrypted; }
function layer8_validate_structure($data) { if (!is_array($data)) { protection_fatal_error('008', '配置数据不是有效数组'); } $requiredKeys = array('local_url', 'cloud_url', 'version'); foreach ($requiredKeys as $key) { if (!isset($data[$key])) { protection_fatal_error('008', '配置数据缺少必要键: ' . $key); } } return true; }
function layer9_validate_url($url) { if (!filter_var($url, FILTER_VALIDATE_URL)) { protection_fatal_error('009', '无效的URL格式: ' . $url); } return true; }
function layer10_validate_domain($url) { $allowedDomains = array('localhost', 'shouquan.mmgcyy.com'); $parsed = parse_url($url); $host = $parsed['host'] ?? ''; $isAllowed = false; foreach ($allowedDomains as $domain) { if ($host === $domain || strpos($host, $domain) !== false) { $isAllowed = true; break; } } if (!$isAllowed) { protection_fatal_error('010', '域名不在白名单中: ' . $host); } return true; }

$keyFile = __DIR__ . '/api_key.php';
$configFile = __DIR__ . '/api_urls_encrypted.php';
$hashFile = __DIR__ . '/api_urls_hash.dat';

layer1_check_file_exists($keyFile); layer2_check_file_size($keyFile, 50); layer3_check_file_permissions($keyFile);
layer1_check_file_exists($configFile); layer2_check_file_size($configFile); layer3_check_file_permissions($configFile);
layer1_check_file_exists($hashFile); layer2_check_file_size($hashFile, 64);

require_once $keyFile;
require_once $configFile;

layer5_check_integrity($configFile, $hashFile);

$content = file_get_contents($configFile);
$checkStrings = array('encryptedConfig', 'configVersion');
layer6_check_string_integrity($content, $checkStrings);

$decryptedData = layer7_decrypt_config($GLOBALS['encryptedConfig'], LICENSE_ENCRYPT_KEY);

$config = json_decode($decryptedData, true);
layer8_validate_structure($config);

layer9_validate_url($config['local_url']); layer9_validate_url($config['cloud_url']);
layer10_validate_domain($config['local_url']); layer10_validate_domain($config['cloud_url']);

define('LICENSE_API_URL_LOCAL', $config['local_url']);
define('LICENSE_API_URL_CLOUD', $config['cloud_url']);

function getLicenseApiUrl() {
    $currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost:88';
    $isLocal = strpos($currentHost, 'localhost') !== false || strpos($currentHost, '127.0.0.1') !== false || strpos($currentHost, '192.168.') !== false || strpos($currentHost, '10.') !== false;
    return $isLocal ? LICENSE_API_URL_LOCAL : LICENSE_API_URL_CLOUD;
}

if (!defined('LICENSE_API_URL')) { define('LICENSE_API_URL', getLicenseApiUrl()); }
?>