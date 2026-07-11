<?php
// 获取医生详情的API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // 允许跨域访问

require_once __DIR__ . '/../../utils/Database.php';

try {
    // 获取数据库连接
    $db = Database::getInstance()->getConn();
    
    // 获取请求参数
    $doctorId = isset($_GET['doctor_id']) ? $_GET['doctor_id'] : null;
    
    if (!$doctorId) {
        throw new Exception('缺少必要参数');
    }
    
    // 查询医生信息
    $stmt = $db->prepare('SELECT * FROM doctors WHERE id = ?');
    $stmt->execute([$doctorId]);
    $doctor = $stmt->fetch();
    
    if (!$doctor) {
        throw new Exception('医生不存在');
    }
    
    // 处理头像路径
    if (!empty($doctor['avatar']) && strpos($doctor['avatar'], 'http://') === false && strpos($doctor['avatar'], 'https://') === false) {
        // 检查头像文件是否存在于 admin/uploads 目录
        $avatarFile = __DIR__ . '/../../admin/uploads/' . basename($doctor['avatar']);
        if (!file_exists($avatarFile)) {
            // 如果文件不存在，检查根目录的 uploads 目录
            $avatarFile = __DIR__ . '/../../uploads/' . basename($doctor['avatar']);
            if (!file_exists($avatarFile)) {
                // 如果文件不存在，使用默认头像
                $doctor['avatar'] = 'https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=doctor%20portrait%20professional%20in%20white%20coat&image_size=square';
            } else {
                // 如果文件存在于根目录 uploads，使用相对路径
                $doctor['avatar'] = 'uploads/' . basename($doctor['avatar']);
            }
        } else {
            // 如果文件存在于 admin/uploads，使用相对路径
            $doctor['avatar'] = 'admin/uploads/' . basename($doctor['avatar']);
        }
    }
    
    echo json_encode([
        'code' => 200,
        'message' => '获取医生详情成功',
        'data' => $doctor
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '获取医生详情失败: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>