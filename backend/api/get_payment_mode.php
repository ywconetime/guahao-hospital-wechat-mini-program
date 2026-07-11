<?php
/**
 * 获取支付模式API
 */

require_once __DIR__ . '/../license_system/config.php';

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = get_db_connection();

    // 获取支付模式
    $stmt = $pdo->prepare("SELECT config_value FROM license_config WHERE config_key = 'payment_mode'");
    $stmt->execute();
    $result = $stmt->fetch();
    $mode = $result ? $result['config_value'] : 'alipay';

    // 获取套餐列表
    $stmt = $pdo->query("SELECT * FROM license_subscription_plans WHERE status = 1 ORDER BY sort_order ASC, id DESC");
    $plans = $stmt->fetchAll();

    echo json_encode([
        'code' => 200,
        'data' => [
            'payment_mode' => $mode,
            'plans' => $plans
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '系统错误: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
