<?php
// 获取科室列表的API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // 允许跨域访问

try {
    require_once __DIR__ . '/../utils/Database.php';
    
    // 获取数据库连接
    $db = Database::getInstance()->getConn();
    
    // 确保is_recommended列存在
    try {
        $stmt = $db->query("SHOW COLUMNS FROM departments LIKE 'is_recommended'");
        $columnExists = $stmt->rowCount() > 0;
        
        if (!$columnExists) {
            $db->exec("ALTER TABLE departments ADD COLUMN is_recommended TINYINT(1) DEFAULT 0");
        }
    } catch (PDOException $e) {
        // 忽略错误，继续执行
    }
    
    // 获取参数
    $only_recommended = isset($_GET['only_recommended']) && $_GET['only_recommended'] == 'true';
    
    // 获取科室列表，按优先推荐和创建时间排序
    try {
        if ($only_recommended) {
            $stmt = $db->query("SELECT * FROM departments WHERE is_recommended = 1 ORDER BY created_at DESC");
        } else {
            $stmt = $db->query("SELECT * FROM departments ORDER BY is_recommended DESC, created_at DESC");
        }
        $departments = $stmt->fetchAll();
    } catch (PDOException $e) {
        // 如果is_recommended列不存在，使用默认排序
        $stmt = $db->query("SELECT * FROM departments ORDER BY created_at DESC");
        $departments = $stmt->fetchAll();
    }
    
    echo json_encode([
        'code' => 200,
        'message' => '获取科室列表成功',
        'data' => $departments
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '获取科室列表失败: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>