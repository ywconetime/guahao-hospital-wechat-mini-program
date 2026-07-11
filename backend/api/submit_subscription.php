<?php
/**
 * 提交订阅预约API - 通过授权系统API处理
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// 先引入配置文件，获取 LICENSE_API_URL
$configFile = __DIR__ . '/../config/api_urls.php';
if (!file_exists($configFile)) {
    $configFile = __DIR__ . '/../config/api_urls_protected.php';
}
if (file_exists($configFile)) {
    require_once $configFile;
}

// 如果还没有定义 LICENSE_API_URL，使用后备方案
if (!defined('LICENSE_API_URL') || empty(LICENSE_API_URL)) {
    $currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost:88';
    $isLocal = strpos($currentHost, 'localhost') !== false || 
               strpos($currentHost, '127.0.0.1') !== false ||
               strpos($currentHost, '192.168.') !== false ||
               strpos($currentHost, '10.') !== false;
    define('LICENSE_API_URL', $isLocal ? 'http://localhost:88/license_system/api' : 'https://shouquan.4wc.cn/license_system/api');
}

// 读取POST数据
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$authId = intval($data['auth_id'] ?? 0);
$planId = intval($data['plan_id'] ?? 0);
$userName = trim($data['user_name'] ?? '');
$userPhone = trim($data['user_phone'] ?? '');
$userWechat = trim($data['user_wechat'] ?? '');
$userEmail = trim($data['user_email'] ?? '');

// auth_id 是可选的（订阅页面可能还没有授权）
// 必须的参数：plan_id, user_name, user_phone
if (!$planId || empty($userName) || empty($userPhone)) {
    echo json_encode([
        'code' => 400,
        'message' => '缺少必要参数：套餐ID、姓名、手机号为必填项'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 邮箱是选填的，只有填写了才验证格式
if (!empty($userEmail) && !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'code' => 400,
        'message' => '邮箱格式不正确'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 调用授权系统API
$apiUrl = LICENSE_API_URL . '/submit_subscription_order.php';

$postData = [
    'auth_id' => $authId,
    'plan_id' => $planId,
    'user_name' => $userName,
    'user_phone' => $userPhone,
    'user_wechat' => $userWechat,
    'user_email' => $userEmail
];

$jsonData = json_encode($postData);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'LicenseSystem/1.0');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    error_log("[订阅API] curl调用失败: " . $error);
    echo json_encode([
        'code' => 500,
        'message' => '网络连接失败: ' . $error,
        'debug_info' => ['api_url' => $apiUrl, 'post_data' => $postData]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($httpCode != 200) {
    error_log("[订阅API] HTTP错误: " . $httpCode . ", 响应: " . $response);
    echo json_encode([
        'code' => 500,
        'message' => '服务响应错误: HTTP ' . $httpCode,
        'debug_info' => ['api_url' => $apiUrl, 'response' => substr($response, 0, 500)]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = json_decode($response, true);
if ($result) {
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} else {
    error_log("[订阅API] 响应格式错误 - 原始响应: " . substr($response, 0, 500));
    echo json_encode([
        'code' => 500,
        'message' => '服务响应格式错误',
        'debug_info' => ['api_url' => $apiUrl, 'response' => substr($response, 0, 500)]
    ], JSON_UNESCAPED_UNICODE);
}
?>
