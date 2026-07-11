<?php
// 医生管理处理文件
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
checkAdminLogin();

// 导入日志记录函数
require_once __DIR__ . '/functions.php';

$db = getAdminDB();
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add':
        addDoctor($db);
        break;
    case 'edit':
        editDoctor($db);
        break;
    case 'delete':
        deleteDoctor($db);
        break;
    case 'get':
        getDoctor($db);
        break;
    case 'getDepartments':
        getDepartments($db);
        break;
    case 'getDiseases':
        getDiseases($db);
        break;
    case 'bind':
        bindDoctor($db);
        break;
    // 排班管理功能
    case 'getSchedules':
        getSchedules($db);
        break;
    case 'getSchedule':
        getSchedule($db);
        break;
    case 'saveSchedule':
        saveSchedule($db);
        break;
    case 'deleteSchedule':
        deleteSchedule($db);
        break;
    case 'batchDeleteSchedules':
        batchDeleteSchedules($db);
        break;
    case 'updateScheduleMode':
        updateScheduleMode($db);
        break;
    case 'getScheduleSettings':
        getScheduleSettings($db);
        break;
    case 'saveScheduleSettings':
        saveScheduleSettings($db);
        break;
    case 'generateAutoSchedules':
        generateAutoSchedules($db);
        break;
    case 'toggleScheduleSuspend':
        toggleScheduleSuspend($db);
        break;
    default:
        echo json_encode(['success' => false, 'message' => '无效的操作']);
        break;
}

function addDoctor($db) {
    try {
        $name = $_POST['name'] ?? '';
        $title = $_POST['title'] ?? '';
        $department = $_POST['department'] ?? '';
        $specialty = $_POST['specialty'] ?? '';
        $description = $_POST['description'] ?? '';
        $avatar = '';
        
        if (empty($name) || empty($title) || empty($department)) {
            throw new Exception('姓名、职称和科室不能为空');
        }
        
        // 处理头像上传
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $avatar = handleAvatarUpload();
        }
        
        // 检查doctors表结构
        $stmt = $db->query('DESCRIBE doctors');
        $columns = [];
        while ($row = $stmt->fetch()) {
            $columns[] = $row['Field'];
        }
        
        if (!in_array('department', $columns)) {
            throw new Exception('数据库表中不存在department列');
        }
        
        // 执行插入操作
        $stmt = $db->prepare('INSERT INTO doctors (name, title, department, specialty, description, avatar, hospital_id, department_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $result = $stmt->execute([$name, $title, $department, $specialty, $description, $avatar, 1, 1]);
        
        if ($result) {
            // 记录添加医生操作并发送邮件通知
            logAdminAction('添加医生', [
                '医生姓名' => $name,
                '职称' => $title,
                '科室' => $department,
                '专长' => $specialty
            ]);
            echo json_encode(['success' => true, 'message' => '添加成功']);
        } else {
            echo json_encode(['success' => false, 'message' => '添加失败']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function editDoctor($db) {
    try {
        $id = $_POST['id'] ?? '';
        $name = $_POST['name'] ?? '';
        $title = $_POST['title'] ?? '';
        $department = $_POST['department'] ?? '';
        $specialty = $_POST['specialty'] ?? '';
        $description = $_POST['description'] ?? '';
        
        if (empty($id) || empty($name) || empty($title) || empty($department)) {
            throw new Exception('ID、姓名、职称和科室不能为空');
        }
        
        // 获取当前头像
        $stmt = $db->prepare('SELECT avatar FROM doctors WHERE id = ?');
        $stmt->execute([$id]);
        $currentAvatar = $stmt->fetchColumn();
        
        // 处理头像上传
        $avatar = $currentAvatar;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $avatar = handleAvatarUpload();
            // 删除旧头像
            if ($currentAvatar && file_exists(__DIR__ . '/../uploads/' . $currentAvatar)) {
                unlink(__DIR__ . '/../uploads/' . $currentAvatar);
            }
        }
        
        // 检查doctors表结构
        $stmt = $db->query('DESCRIBE doctors');
        $columns = [];
        while ($row = $stmt->fetch()) {
            $columns[] = $row['Field'];
        }
        
        if (!in_array('department', $columns)) {
            throw new Exception('数据库表中不存在department列');
        }
        
        // 执行更新操作
        $stmt = $db->prepare('UPDATE doctors SET name = ?, title = ?, department = ?, specialty = ?, description = ?, avatar = ?, hospital_id = ?, department_id = ? WHERE id = ?');
        $result = $stmt->execute([$name, $title, $department, $specialty, $description, $avatar, 1, 1, $id]);
        
        if ($result && $stmt->rowCount() > 0) {
            // 记录编辑医生操作并发送邮件通知
            logAdminAction('编辑医生', [
                '医生ID' => $id,
                '医生姓名' => $name,
                '职称' => $title,
                '科室' => $department,
                '专长' => $specialty
            ]);
            echo json_encode(['success' => true, 'message' => '编辑成功']);
        } else {
            echo json_encode(['success' => false, 'message' => '编辑失败：未找到对应的医生或数据未变化']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteDoctor($db) {
    try {
        $id = $_POST['id'] ?? '';
        
        if (empty($id)) {
            throw new Exception('ID不能为空');
        }
        
        // 获取医生信息，用于邮件通知
        $stmt = $db->prepare('SELECT name, title, department FROM doctors WHERE id = ?');
        $stmt->execute([$id]);
        $doctor = $stmt->fetch();
        
        // 删除医生
        $stmt = $db->prepare('DELETE FROM doctors WHERE id = ?');
        $stmt->execute([$id]);
        
        // 记录删除医生操作并发送邮件通知
        logAdminAction('删除医生', [
            '医生ID' => $id,
            '医生姓名' => $doctor['name'] ?? '未知',
            '职称' => $doctor['title'] ?? '未知',
            '科室' => $doctor['department'] ?? '未知'
        ]);
        
        echo json_encode(['success' => true, 'message' => '删除成功']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getDoctor($db) {
    try {
        $id = $_POST['id'] ?? '';
        
        if (empty($id)) {
            throw new Exception('ID不能为空');
        }
        
        $stmt = $db->prepare('SELECT * FROM doctors WHERE id = ?');
        $stmt->execute([$id]);
        $doctor = $stmt->fetch();
        
        if ($doctor) {
            echo json_encode(['success' => true, 'data' => $doctor]);
        } else {
            echo json_encode(['success' => false, 'message' => '未找到医生']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getDepartments($db) {
    try {
        $doctorId = $_POST['doctor_id'] ?? '';
        
        if (empty($doctorId)) {
            throw new Exception('医生ID不能为空');
        }
        
        // 获取所有科室
        $stmt = $db->query('SELECT id, name FROM departments ORDER BY name');
        $departments = $stmt->fetchAll();
        
        // 获取医生已绑定的科室
        $stmt = $db->prepare('SELECT department_id FROM doctor_departments WHERE doctor_id = ?');
        $stmt->execute([$doctorId]);
        $boundDepartments = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 标记已绑定的科室
        foreach ($departments as &$dept) {
            $dept['checked'] = in_array($dept['id'], $boundDepartments);
        }
        
        echo json_encode(['success' => true, 'data' => $departments]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getDiseases($db) {
    try {
        $doctorId = $_POST['doctor_id'] ?? '';
        
        if (empty($doctorId)) {
            throw new Exception('医生ID不能为空');
        }
        
        // 获取所有病种
        $stmt = $db->query('SELECT id, name FROM diseases ORDER BY name');
        $diseases = $stmt->fetchAll();
        
        // 获取医生已绑定的病种
        $stmt = $db->prepare('SELECT disease_id FROM doctor_diseases WHERE doctor_id = ?');
        $stmt->execute([$doctorId]);
        $boundDiseases = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 标记已绑定的病种
        foreach ($diseases as &$disease) {
            $disease['checked'] = in_array($disease['id'], $boundDiseases);
        }
        
        echo json_encode(['success' => true, 'data' => $diseases]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function bindDoctor($db) {
    try {
        $doctorId = $_POST['doctor_id'] ?? '';
        $departments = $_POST['departments'] ?? [];
        $diseases = $_POST['diseases'] ?? [];
        
        if (empty($doctorId)) {
            throw new Exception('医生ID不能为空');
        }
        
        // 开始事务
        $db->beginTransaction();
        
        // 删除现有的科室绑定
        $stmt = $db->prepare('DELETE FROM doctor_departments WHERE doctor_id = ?');
        $stmt->execute([$doctorId]);
        
        // 删除现有的病种绑定
        $stmt = $db->prepare('DELETE FROM doctor_diseases WHERE doctor_id = ?');
        $stmt->execute([$doctorId]);
        
        // 添加新的科室绑定
        if (!empty($departments)) {
            $stmt = $db->prepare('INSERT INTO doctor_departments (doctor_id, department_id) VALUES (?, ?)');
            foreach ($departments as $deptId) {
                $stmt->execute([$doctorId, $deptId]);
            }
        }
        
        // 添加新的病种绑定
        if (!empty($diseases)) {
            $stmt = $db->prepare('INSERT INTO doctor_diseases (doctor_id, disease_id) VALUES (?, ?)');
            foreach ($diseases as $diseaseId) {
                $stmt->execute([$doctorId, $diseaseId]);
            }
        }
        
        // 提交事务
        $db->commit();
        
        echo json_encode(['success' => true, 'message' => '绑定成功']);
    } catch (Exception $e) {
        // 回滚事务
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleAvatarUpload() {
    $file = $_FILES['avatar'];
    
    // 检查文件类型
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('只支持JPG、PNG、GIF格式的图片');
    }
    
    // 检查文件大小（1M限制）
    if ($file['size'] > 1 * 1024 * 1024) {
        throw new Exception('图片大小不能超过1M');
    }
    
    // 生成唯一文件名
    $fileName = uniqid('avatar_') . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    
    // 确保上传目录存在
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // 移动文件到上传目录
    $filePath = $uploadDir . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('文件上传失败');
    }
    
    // 返回相对路径，用于存储到数据库
    return '../uploads/' . $fileName;
}

// ========== 排班管理功能 ==========

function getSchedules($db) {
    try {
        $doctorId = $_POST['doctor_id'] ?? '';
        $startDate = $_POST['start_date'] ?? date('Y-m-01');
        $endDate = $_POST['end_date'] ?? date('Y-m-t');
        
        if (empty($doctorId)) {
            throw new Exception('医生ID不能为空');
        }
        
        $stmt = $db->prepare('SELECT * FROM schedules WHERE doctor_id = ? AND date BETWEEN ? AND ? ORDER BY date ASC, time_slot ASC');
        $stmt->execute([$doctorId, $startDate, $endDate]);
        $schedules = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $schedules]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getSchedule($db) {
    try {
        $scheduleId = $_POST['id'] ?? '';
        
        if (empty($scheduleId)) {
            throw new Exception('排班ID不能为空');
        }
        
        $stmt = $db->prepare('SELECT * FROM schedules WHERE id = ?');
        $stmt->execute([$scheduleId]);
        $schedule = $stmt->fetch();
        
        if ($schedule) {
            echo json_encode(['success' => true, 'data' => $schedule]);
        } else {
            echo json_encode(['success' => false, 'message' => '未找到排班']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function saveSchedule($db) {
    try {
        $doctorId = $_POST['doctor_id'] ?? '';
        $date = $_POST['date'] ?? '';
        $timeSlot = $_POST['time_slot'] ?? '';
        $totalQuantity = $_POST['total_quantity'] ?? 20;
        $remainingQuantity = $_POST['remaining_quantity'] ?? $totalQuantity;
        $scheduleId = $_POST['id'] ?? '';
        
        if (empty($doctorId) || empty($date) || empty($timeSlot)) {
            throw new Exception('医生ID、日期和时段不能为空');
        }
        
        // 处理时间格式，确保是 HH:MM:SS 格式
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        
        if (empty($startTime)) {
            $startTime = $timeSlot === '上午' ? '08:00:00' : '14:00:00';
        } elseif (strlen($startTime) == 5) {
            $startTime .= ':00';
        }
        
        if (empty($endTime)) {
            $endTime = $timeSlot === '上午' ? '12:00:00' : '18:00:00';
        } elseif (strlen($endTime) == 5) {
            $endTime .= ':00';
        }
        
        if (!empty($scheduleId)) {
            // 更新现有排班
            $stmt = $db->prepare('UPDATE schedules SET date = ?, time_slot = ?, total_quantity = ?, remaining_quantity = ?, start_time = ?, end_time = ? WHERE id = ?');
            $result = $stmt->execute([$date, $timeSlot, $totalQuantity, $remainingQuantity, $startTime, $endTime, $scheduleId]);
        } else {
            // 添加新排班
            $stmt = $db->prepare('INSERT INTO schedules (doctor_id, date, time_slot, total_quantity, remaining_quantity, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $result = $stmt->execute([$doctorId, $date, $timeSlot, $totalQuantity, $remainingQuantity, $startTime, $endTime]);
        }
        
        if ($result) {
            logAdminAction('保存排班', [
                '医生ID' => $doctorId,
                '日期' => $date,
                '时段' => $timeSlot,
                '号源' => $totalQuantity
            ]);
            echo json_encode(['success' => true, 'message' => '保存成功']);
        } else {
            echo json_encode(['success' => false, 'message' => '保存失败']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteSchedule($db) {
    try {
        $scheduleId = $_POST['id'] ?? '';
        
        if (empty($scheduleId)) {
            throw new Exception('排班ID不能为空');
        }
        
        $stmt = $db->prepare('DELETE FROM schedules WHERE id = ?');
        $stmt->execute([$scheduleId]);
        
        logAdminAction('删除排班', [
            '排班ID' => $scheduleId
        ]);
        echo json_encode(['success' => true, 'message' => '删除成功']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function batchDeleteSchedules($db) {
    try {
        $ids = $_POST['ids'] ?? '';
        
        if (empty($ids)) {
            throw new Exception('请选择要删除的排班');
        }
        
        $idArray = explode(',', $ids);
        $placeholders = implode(',', array_fill(0, count($idArray), '?'));
        
        $stmt = $db->prepare("DELETE FROM schedules WHERE id IN ($placeholders)");
        $stmt->execute($idArray);
        
        logAdminAction('批量删除排班', [
            '排班ID列表' => $ids
        ]);
        echo json_encode(['success' => true, 'message' => '批量删除成功']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function updateScheduleMode($db) {
    try {
        $doctorId = $_POST['doctor_id'] ?? '';
        $scheduleMode = $_POST['schedule_mode'] ?? 'auto';
        
        if (empty($doctorId)) {
            throw new Exception('医生ID不能为空');
        }
        
        $stmt = $db->prepare('UPDATE doctors SET schedule_mode = ? WHERE id = ?');
        $result = $stmt->execute([$scheduleMode, $doctorId]);
        
        if ($result) {
            logAdminAction('切换排班模式', [
                '医生ID' => $doctorId,
                '模式' => $scheduleMode
            ]);
            echo json_encode(['success' => true, 'message' => '切换成功']);
        } else {
            echo json_encode(['success' => false, 'message' => '切换失败']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getScheduleSettings($db) {
    try {
        $doctorId = $_POST['doctor_id'] ?? '';
        
        if (empty($doctorId)) {
            throw new Exception('医生ID不能为空');
        }
        
        // 获取医生基本信息
        $stmt = $db->prepare('SELECT schedule_mode, default_morning_slots, default_afternoon_slots FROM doctors WHERE id = ?');
        $stmt->execute([$doctorId]);
        $doctor = $stmt->fetch();
        
        // 获取排班设置
        $stmt = $db->prepare('SELECT work_days FROM schedule_settings WHERE doctor_id = ?');
        $stmt->execute([$doctorId]);
        $settings = $stmt->fetch();
        
        $result = [
            'schedule_mode' => $doctor['schedule_mode'] ?? 'auto',
            'default_morning_slots' => $doctor['default_morning_slots'] ?? 20,
            'default_afternoon_slots' => $doctor['default_afternoon_slots'] ?? 15,
            'work_days' => $settings['work_days'] ?? '1,2,3,4,5'
        ];
        
        echo json_encode(['success' => true, 'data' => $result]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function saveScheduleSettings($db) {
    try {
        $doctorId = $_POST['doctor_id'] ?? '';
        $workDays = $_POST['work_days'] ?? '1,2,3,4,5';
        $defaultMorningSlots = $_POST['default_morning_slots'] ?? 20;
        $defaultAfternoonSlots = $_POST['default_afternoon_slots'] ?? 15;
        
        if (empty($doctorId)) {
            throw new Exception('医生ID不能为空');
        }
        
        // 更新医生默认号源
        $stmt = $db->prepare('UPDATE doctors SET default_morning_slots = ?, default_afternoon_slots = ? WHERE id = ?');
        $stmt->execute([$defaultMorningSlots, $defaultAfternoonSlots, $doctorId]);
        
        // 更新或插入排班设置
        $stmt = $db->prepare('SELECT id FROM schedule_settings WHERE doctor_id = ?');
        $stmt->execute([$doctorId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $stmt = $db->prepare('UPDATE schedule_settings SET work_days = ? WHERE doctor_id = ?');
            $stmt->execute([$workDays, $doctorId]);
        } else {
            $stmt = $db->prepare('INSERT INTO schedule_settings (doctor_id, work_days) VALUES (?, ?)');
            $stmt->execute([$doctorId, $workDays]);
        }
        
        logAdminAction('保存排班设置', [
            '医生ID' => $doctorId,
            '工作日' => $workDays,
            '上午号源' => $defaultMorningSlots,
            '下午号源' => $defaultAfternoonSlots
        ]);
        echo json_encode(['success' => true, 'message' => '保存成功']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function generateAutoSchedules($db) {
    try {
        $doctorId = $_POST['doctor_id'] ?? '';
        $startDate = $_POST['start_date'] ?? date('Y-m-01');
        $endDate = $_POST['end_date'] ?? date('Y-m-t');
        
        if (empty($doctorId)) {
            throw new Exception('医生ID不能为空');
        }
        
        // 获取医生信息
        $stmt = $db->prepare('SELECT default_morning_slots, default_afternoon_slots FROM doctors WHERE id = ?');
        $stmt->execute([$doctorId]);
        $doctor = $stmt->fetch();
        
        // 获取排班设置
        $stmt = $db->prepare('SELECT work_days FROM schedule_settings WHERE doctor_id = ?');
        $stmt->execute([$doctorId]);
        $settings = $stmt->fetch();
        
        $workDays = $settings['work_days'] ?? '1,2,3,4,5';
        $workDayArray = explode(',', $workDays);
        
        $morningSlots = $doctor['default_morning_slots'] ?? 20;
        $afternoonSlots = $doctor['default_afternoon_slots'] ?? 15;
        
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $count = 0;
        
        while ($start <= $end) {
            $currentDate = $start->format('Y-m-d');
            $dayOfWeek = $start->format('N'); // 1-7，周一到周日
            
            if (in_array($dayOfWeek, $workDayArray)) {
                // 上午
                try {
                    $stmt = $db->prepare('INSERT IGNORE INTO schedules (doctor_id, date, time_slot, total_quantity, remaining_quantity, start_time, end_time) VALUES (?, ?, "上午", ?, ?, "08:00:00", "12:00:00")');
                    $stmt->execute([$doctorId, $currentDate, $morningSlots, $morningSlots]);
                    $count++;
                } catch (Exception $e) {
                    // 忽略重复的话，继续
                }
                
                // 下午
                try {
                    $stmt = $db->prepare('INSERT IGNORE INTO schedules (doctor_id, date, time_slot, total_quantity, remaining_quantity, start_time, end_time) VALUES (?, ?, "下午", ?, ?, "14:00:00", "18:00:00")');
                    $stmt->execute([$doctorId, $currentDate, $afternoonSlots, $afternoonSlots]);
                    $count++;
                } catch (Exception $e) {
                    // 忽略重复
                }
            }
            
            $start->modify('+1 day');
        }
        
        logAdminAction('自动生成排班', [
            '医生ID' => $doctorId,
            '开始日期' => $startDate,
            '结束日期' => $endDate,
            '生成数量' => $count
        ]);
        
        echo json_encode(['success' => true, 'message' => "成功生成 {$count} 条排班记录", 'data' => ['count' => $count]]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// 切换排班停诊状态
function toggleScheduleSuspend($db) {
    try {
        // 检查并添加 is_suspended 字段
        $stmt = $db->prepare("SHOW COLUMNS FROM schedules LIKE 'is_suspended'");
        $stmt->execute();
        $columnExists = $stmt->rowCount() > 0;
        
        if (!$columnExists) {
            $db->exec("ALTER TABLE schedules ADD COLUMN is_suspended TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否停诊: 0正常, 1停诊' AFTER end_time");
        }
        
        $scheduleId = $_POST['schedule_id'] ?? '';
        
        if (empty($scheduleId)) {
            throw new Exception('排班ID不能为空');
        }
        
        // 获取当前停诊状态
        $stmt = $db->prepare('SELECT is_suspended FROM schedules WHERE id = ?');
        $stmt->execute([$scheduleId]);
        $schedule = $stmt->fetch();
        
        if (!$schedule) {
            throw new Exception('排班不存在');
        }
        
        // 切换状态
        $newStatus = $schedule['is_suspended'] ? 0 : 1;
        
        $stmt = $db->prepare('UPDATE schedules SET is_suspended = ? WHERE id = ?');
        $result = $stmt->execute([$newStatus, $scheduleId]);
        
        if ($result) {
            logAdminAction('切换停诊状态', [
                '排班ID' => $scheduleId,
                '新状态' => $newStatus ? '停诊' : '正常'
            ]);
            echo json_encode(['success' => true, 'message' => '切换成功', 'data' => ['is_suspended' => $newStatus]]);
        } else {
            echo json_encode(['success' => false, 'message' => '切换失败']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>