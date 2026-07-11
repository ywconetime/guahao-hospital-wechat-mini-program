<?php
// 获取医生排班信息的API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // 允许跨域访问

require_once __DIR__ . '/../../utils/Database.php';

try {
    // 获取数据库连接
    $db = Database::getInstance()->getConn();
    
    // 检查schedules表是否存在
    $stmt = $db->query("SHOW TABLES LIKE 'schedules'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        // 创建schedules表
        $createTableSql = "
        CREATE TABLE IF NOT EXISTS schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doctor_id INT NOT NULL,
            date DATE NOT NULL,
            time_slot VARCHAR(10) NOT NULL,
            total_quantity INT NOT NULL DEFAULT 20,
            remaining_quantity INT NOT NULL DEFAULT 20,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (doctor_id) REFERENCES doctors(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $db->exec($createTableSql);
    }
    
    // 获取请求参数
    $doctorId = isset($_GET['doctor_id']) ? $_GET['doctor_id'] : null;
    $date = isset($_GET['date']) ? $_GET['date'] : null;
    
    if (!$doctorId || !$date) {
        throw new Exception('缺少必要参数');
    }
    
    // 检查医生是否存在
    $stmt = $db->prepare('SELECT * FROM doctors WHERE id = ?');
    $stmt->execute([$doctorId]);
    $doctor = $stmt->fetch();
    
    if (!$doctor) {
        throw new Exception('医生不存在');
    }
    
    // 查询排班信息
    $stmt = $db->prepare('SELECT * FROM schedules WHERE doctor_id = ? AND date = ?');
    $stmt->execute([$doctorId, $date]);
    $schedules = $stmt->fetchAll();
    
    if (empty($schedules)) {
        // 如果没有排班信息，生成默认排班
        $defaultSchedules = [
            [
                'id' => 1,
                'doctor_id' => $doctorId,
                'date' => $date,
                'time_slot' => '上午',
                'total_quantity' => 20,
                'remaining_quantity' => 20
            ],
            [
                'id' => 2,
                'doctor_id' => $doctorId,
                'date' => $date,
                'time_slot' => '下午',
                'total_quantity' => 20,
                'remaining_quantity' => 20
            ]
        ];
        
        // 插入默认排班数据
        foreach ($defaultSchedules as $schedule) {
            $stmt = $db->prepare('INSERT INTO schedules (doctor_id, date, time_slot, total_quantity, remaining_quantity) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$schedule['doctor_id'], $schedule['date'], $schedule['time_slot'], $schedule['total_quantity'], $schedule['remaining_quantity']]);
        }
        
        $schedules = $defaultSchedules;
    }
    
    echo json_encode([
        'code' => 200,
        'message' => '获取排班信息成功',
        'data' => $schedules
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '获取排班信息失败: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>