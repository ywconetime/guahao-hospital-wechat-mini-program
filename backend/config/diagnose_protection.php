<?php
/**
 * 配置文件保护系统诊断脚本
 */

echo "<h1>配置文件保护系统诊断</h1>";
echo "<pre>";

// 检查密钥文件
echo "=== 1. 密钥文件检查 ===\n";
$keyFile = __DIR__ . '/api_key.php';
if (file_exists($keyFile)) {
    echo "✅ 密钥文件存在\n";
    require_once $keyFile;
    echo "   加密密钥长度: " . strlen(LICENSE_ENCRYPT_KEY) . " 字符\n";
    echo "   保护机制状态: " . (LICENSE_PROTECTION_ENABLED ? '已启用' : '已禁用') . "\n";
} else {
    echo "❌ 密钥文件不存在\n";
}

// 检查加密配置文件
echo "\n=== 2. 加密配置文件检查 ===\n";
$configFile = __DIR__ . '/api_urls_encrypted.php';
if (file_exists($configFile)) {
    echo "✅ 加密配置文件存在\n";
    require_once $configFile;
    echo "   加密数据长度: " . strlen($encryptedConfig) . " 字符\n";
    echo "   配置版本: " . $configVersion . "\n";
} else {
    echo "❌ 加密配置文件不存在\n";
}

// 检查哈希文件
echo "\n=== 3. 哈希校验文件检查 ===\n";
$hashFile = __DIR__ . '/api_urls_hash.dat';
if (file_exists($hashFile)) {
    echo "✅ 哈希文件存在\n";
    $hash = trim(file_get_contents($hashFile));
    echo "   存储的哈希值: " . $hash . "\n";
    
    // 计算实际哈希
    $actualHash = hash_file('sha256', $configFile);
    echo "   实际计算哈希: " . $actualHash . "\n";
    echo "   哈希校验: " . ($hash === $actualHash ? '✅ 匹配' : '❌ 不匹配') . "\n";
} else {
    echo "❌ 哈希文件不存在\n";
}

// 测试解密
echo "\n=== 4. 解密测试 ===\n";
$hasEncryptedConfig = isset($encryptedConfig);
$hasKey = defined('LICENSE_ENCRYPT_KEY');
if ($hasEncryptedConfig && $hasKey) {
    function license_decrypt($encryptedData, $key) {
        $data = base64_decode($encryptedData);
        if ($data === false) {
            return ['success' => false, 'error' => 'Base64解码失败'];
        }
        $ivLength = openssl_cipher_iv_length('AES-256-CBC');
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            return ['success' => false, 'error' => 'AES解密失败'];
        }
        return ['success' => true, 'data' => $decrypted];
    }
    
    $result = license_decrypt($encryptedConfig, LICENSE_ENCRYPT_KEY);
    if ($result['success']) {
        echo "✅ 解密成功\n";
        echo "   解密内容: " . $result['data'] . "\n";
        
        // 解析JSON
        $config = json_decode($result['data'], true);
        if ($config) {
            echo "\n=== 5. JSON解析结果 ===\n";
            print_r($config);
        } else {
            echo "❌ JSON解析失败\n";
        }
    } else {
        echo "❌ 解密失败: " . $result['error'] . "\n";
        echo "\n可能原因:\n";
        echo "  1. 加密密钥不正确\n";
        echo "  2. 加密数据被篡改\n";
        echo "  3. 加密算法不匹配\n";
    }
}

// 检查OpenSSL扩展
echo "\n=== 6. OpenSSL扩展检查 ===\n";
echo "OpenSSL扩展: " . (extension_loaded('openssl') ? '✅ 已加载' : '❌ 未加载') . "\n";

echo "</pre>";
?>