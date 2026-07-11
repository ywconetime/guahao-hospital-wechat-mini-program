<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// 读取授权系统API配置
require_once '../config/api_urls_protected.php';

$input = json_decode(file_get_contents('php://input'), true);
$fingerprint = $input['fingerprint'] ?? '';
$domain = $input['domain'] ?? $_SERVER['HTTP_HOST'];
$timestamp = $input['timestamp'] ?? time();

if (empty($fingerprint)) {
    echo json_encode(['success' => false, 'message' => '指纹为空']);
    exit();
}

// 优先尝试连接授权系统
$firstAccessTime = null;
$apiUrl = API_URL_LICENSE . '/api/register_fingerprint.php';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'fingerprint' => $fingerprint,
    'domain' => $domain,
    'ip' => $_SERVER['REMOTE_ADDR'],
    'timestamp' => $timestamp
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200 && $response) {
    $data = json_decode($response, true);
    if ($data && $data['success']) {
        echo $response;
        exit();
    }
}

// 如果授权系统不可用，使用本地数据库作为备用
try {
    require_once '../license_system/api/fingerprint_api.php';
    
    $result = registerFingerprint($fingerprint, $domain, $_SERVER['REMOTE_ADDR']);
    echo json_encode([
        'success' => true,
        'first_access_time' => $result['first_access_time'] ?? time(),
        'message' => '使用本地数据库',
        'source' => 'local'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '授权系统和本地数据库都不可用: ' . $e->getMessage()
    ]);
}
?>