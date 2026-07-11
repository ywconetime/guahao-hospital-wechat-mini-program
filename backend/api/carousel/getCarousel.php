<?php
// 获取轮播图片API
require_once __DIR__ . '/../../admin/includes/config.php';

$db = getAdminDB();

if ($db !== null) {
    try {
        // 获取启用的轮播图片，按排序顺序
        $stmt = $db->query('SELECT id, image_url, title, link FROM carousel WHERE status = "active" ORDER BY sort_order ASC');
        $carousel = $stmt->fetchAll();
        
        // 构建响应
        $response = [
            'code' => 200,
            'message' => '获取轮播图片成功',
            'data' => $carousel
        ];
    } catch (PDOException $e) {
        $response = [
            'code' => 500,
            'message' => '数据库查询失败: ' . $e->getMessage(),
            'data' => []
        ];
    }
} else {
    $response = [
        'code' => 500,
        'message' => '数据库连接失败',
        'data' => []
    ];
}

// 输出响应
header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>