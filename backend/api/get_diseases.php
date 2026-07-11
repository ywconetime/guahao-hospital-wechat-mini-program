<?php
// 获取病种列表的API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // 允许跨域访问

require_once __DIR__ . '/../utils/Database.php';

try {
    // 获取数据库连接
    $db = Database::getInstance()->getConn();
    
    // 获取参数
    $only_recommended = isset($_GET['only_recommended']) && $_GET['only_recommended'] == 'true';
    $department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : null;
    $keyword = isset($_GET['keyword']) ? $_GET['keyword'] : null;
    
    // 构建查询
    if ($keyword) {
        // 使用关键词搜索病种
        $sql = "SELECT * FROM diseases WHERE name LIKE ? ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $searchTerm = '%' . $keyword . '%';
        $stmt->execute([$searchTerm]);
    } elseif ($only_recommended && $department_id) {
        // 获取指定科室的优先推荐病种
        $sql = "SELECT * FROM diseases WHERE department_id = ? AND is_recommended = 1 ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$department_id]);
    } elseif ($only_recommended) {
        // 获取优先推荐的病种
        $sql = "SELECT * FROM diseases WHERE is_recommended = 1 ORDER BY created_at DESC";
        $stmt = $db->query($sql);
    } elseif ($department_id) {
        // 获取指定科室的病种
        $sql = "SELECT * FROM diseases WHERE department_id = ? ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$department_id]);
    } else {
        // 获取所有病种
        $sql = "SELECT * FROM diseases ORDER BY created_at DESC";
        $stmt = $db->query($sql);
    }
    
    // 检查是否有错误
    if ($stmt->errorCode() !== '00000') {
        $errorInfo = $stmt->errorInfo();
        throw new Exception('查询错误: ' . $errorInfo[2]);
    }
    
    // 检查查询是否成功
    if (!$stmt) {
        throw new Exception('查询病种失败');
    }
    
    $diseases = $stmt->fetchAll();
    
    // 处理icon字段，返回相对路径，让小程序端根据自己的baseUrl构建完整URL
    foreach ($diseases as &$disease) {
        if (!empty($disease['icon'])) {
            // 如果icon已经是完整的URL，保持不变
            if (preg_match('/^https?:\/\//', $disease['icon'])) {
                // 如果是完整URL，转换为相对路径
                $disease['icon'] = preg_replace('/^https?:\/\/[^\/]+\//', '', $disease['icon']);
            } else {
                // 确保路径包含 admin/ 前缀
                if (substr($disease['icon'], 0, 6) !== 'admin/') {
                    $disease['icon'] = 'admin/' . $disease['icon'];
                }
            }
        }
    }
    
    // 检查department_id是否存在
    if ($department_id) {
        echo json_encode([
            'code' => 200,
            'message' => '获取病种列表成功',
            'data' => $diseases,
            'debug' => [
                'department_id' => $department_id,
                'only_recommended' => $only_recommended,
                'query' => $only_recommended && $department_id ? $sql : "Other query",
                'total_diseases' => count($diseases)
            ]
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    echo json_encode([
        'code' => 200,
        'message' => '获取病种列表成功',
        'data' => $diseases
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '获取病种列表失败: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>