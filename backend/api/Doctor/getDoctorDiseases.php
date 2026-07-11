<?php
// 获取医生绑定的病种
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // 允许跨域访问

try {
    require_once __DIR__ . '/../../utils/Database.php';
    
    // 获取数据库连接
    $db = Database::getInstance()->getConn();
    
    // 获取医生ID
    $doctorId = isset($_GET['doctor_id']) ? $_GET['doctor_id'] : null;
    
    if (!$doctorId) {
        echo json_encode([
            'code' => 400,
            'message' => '缺少医生ID参数',
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 查询医生绑定的病种
    $stmt = $db->prepare('SELECT d.* FROM diseases d JOIN doctor_diseases dd ON d.id = dd.disease_id WHERE dd.doctor_id = ? ORDER BY d.name ASC');
    $stmt->execute([$doctorId]);
    $diseases = $stmt->fetchAll();
    
    echo json_encode([
        'code' => 200,
        'message' => '获取医生绑定的病种成功',
        'data' => $diseases
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '获取医生绑定的病种失败: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>