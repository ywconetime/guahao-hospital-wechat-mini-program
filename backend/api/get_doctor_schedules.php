<?php
// 获取医生排班API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../utils/Database.php';

try {
    // 获取数据库连接
    $db = Database::getInstance()->getConn();
    
    // 获取参数
    $doctorId = $_GET['doctor_id'] ?? $_POST['doctor_id'] ?? '';
    
    if (empty($doctorId)) {
        echo json_encode([
            'success' => false,
            'message' => '医生ID不能为空',
            'data' => null
        ]);
        exit;
    }
    
    // 获取医生的排班模式
    $stmt = $db->prepare('SELECT schedule_mode FROM doctors WHERE id = ?');
    $stmt->execute([$doctorId]);
    $doctor = $stmt->fetch();
    
    $scheduleMode = $doctor['schedule_mode'] ?? 'auto';
    
    if ($scheduleMode === 'auto') {
        // 自动模式：生成随机的排班数据
        $result = generateRandomSchedules();
    } else {
        // 手动模式：从数据库读取排班数据
            $result = getManualSchedules($db, $doctorId);
        }
        
        echo json_encode([
            'success' => true,
            'message' => '获取成功',
            'data' => $result
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'data' => null
        ]);
    }

// 生成随机的排班数据
function generateRandomSchedules() {
    $schedules = [];
    $today = new DateTime();
    
    for ($i = 1; $i <= 7; $i++) {
        $date = clone $today;
        $date->add(new DateInterval("P{$i}D"));
        $dateStr = $date->format('Y-m-d');
        
        $timeSlots = [];
        
        // 上午：随机0-30个号源
        $morningCount = rand(0, 30);
        $timeSlots[] = [
            'time' => '上午',
            'count' => $morningCount,
            'total' => 30,
            'startTime' => '08:00:00',
            'endTime' => '12:00:00',
            'isSuspended' => false
        ];
        
        // 下午：随机0-20个号源
        $afternoonCount = rand(0, 20);
        $timeSlots[] = [
            'time' => '下午',
            'count' => $afternoonCount,
            'total' => 20,
            'startTime' => '14:00:00',
            'endTime' => '18:00:00',
            'isSuspended' => false
        ];
        
        $schedules[] = [
            'date' => $dateStr,
            'timeSlots' => $timeSlots
        ];
    }
    
    return $schedules;
}

// 从数据库获取手动设置的排班数据
function getManualSchedules($db, $doctorId) {
    // 获取未来30天的排班
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime('+30 days'));
    
    $stmt = $db->prepare('SELECT * FROM schedules WHERE doctor_id = ? AND date BETWEEN ? AND ? ORDER BY date ASC, time_slot ASC');
    $stmt->execute([$doctorId, $startDate, $endDate]);
    $schedules = $stmt->fetchAll();
    
    // 整理数据格式 - 按日期分组
    $grouped = [];
    foreach ($schedules as $schedule) {
        $date = $schedule['date'];
        if (!isset($grouped[$date])) {
            $grouped[$date] = [
                'date' => $date,
                'timeSlots' => []
            ];
        }
        
        $grouped[$date]['timeSlots'][] = [
            'time' => $schedule['time_slot'],
            'count' => $schedule['remaining_quantity'],
            'total' => $schedule['total_quantity'],
            'startTime' => $schedule['start_time'],
            'endTime' => $schedule['end_time'],
            'isSuspended' => $schedule['is_suspended'] == 1
        ];
    }
    
    // 转换为数组格式
    $result = array_values($grouped);
    
    return $result;
}
?>