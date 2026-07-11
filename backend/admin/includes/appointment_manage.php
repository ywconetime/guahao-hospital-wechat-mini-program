<?php
// 预约管理处理文件
require_once __DIR__ . '/config.php';

// 获取数据库连接
$db = getAdminDB();

// 检查数据库连接
if ($db === null) {
    echo json_encode(['code' => 500, 'message' => '数据库连接失败', 'data' => null]);
    exit;
}

// 获取操作类型
$action = $_POST['action'] ?? '';

// 处理不同的操作
switch ($action) {
    case 'get':
        // 获取预约详情
        $id = $_POST['id'] ?? '';
        if (empty($id)) {
            echo json_encode(['code' => 400, 'message' => '缺少预约ID参数', 'data' => null]);
            exit;
        }
        
        try {
            $sql = "SELECT a.*, d.name as doctor_name FROM appointments a JOIN doctors d ON a.doctor_id = d.id WHERE a.id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$id]);
            $appointment = $stmt->fetch();
            if ($appointment) {
                echo json_encode(['code' => 200, 'message' => '获取预约详情成功', 'data' => $appointment]);
            } else {
                echo json_encode(['code' => 404, 'message' => '预约不存在', 'data' => null]);
            }
        } catch (Exception $e) {
            echo json_encode(['code' => 500, 'message' => '获取预约详情失败: ' . $e->getMessage(), 'data' => null]);
        }
        break;
        
    case 'update':
        // 更新预约
        $id = $_POST['id'] ?? '';
        if (empty($id)) {
            echo json_encode(['code' => 400, 'message' => '缺少预约ID参数', 'data' => null]);
            exit;
        }
        
        try {
            // 先获取预约信息，包括用户ID
            $sql = "SELECT user_id, appointment_time, doctor_id FROM appointments WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$id]);
            $appointment = $stmt->fetch();
            
            if (!$appointment) {
                echo json_encode(['code' => 404, 'message' => '预约不存在', 'data' => null]);
                exit;
            }
            
            $userId = $appointment['user_id'];
            $appointmentTime = $appointment['appointment_time'];
            
            // 构建更新语句
            $updateFields = [];
            $updateParams = [];
            $statusChanged = false;
            $newStatus = '';
            
            if (isset($_POST['doctor_id'])) {
                $updateFields[] = 'doctor_id = ?';
                $updateParams[] = $_POST['doctor_id'];
            }
            if (isset($_POST['appointment_time'])) {
                $updateFields[] = 'appointment_time = ?';
                $updateParams[] = $_POST['appointment_time'];
                $appointmentTime = $_POST['appointment_time'];
            }
            if (isset($_POST['status'])) {
                $updateFields[] = 'status = ?';
                $updateParams[] = $_POST['status'];
                $statusChanged = true;
                $newStatus = $_POST['status'];
            }
            
            if (empty($updateFields)) {
                echo json_encode(['code' => 400, 'message' => '没有需要更新的字段', 'data' => null]);
                exit;
            }
            
            // 添加预约ID到参数列表
            $updateParams[] = $id;
            
            // 执行更新
            $updateSql = "UPDATE appointments SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $db->prepare($updateSql);
            $result = $stmt->execute($updateParams);
            
            if ($result) {
                // 如果状态发生变化，发送通知给用户
                if ($statusChanged) {
                    $title = '预约状态更新';
                    $statusText = '';
                    switch ($newStatus) {
                        case 'pending':
                            $statusText = '待确认到诊';
                            break;
                        case 'confirmed':
                            $statusText = '已确认';
                            break;
                        case 'cancelled':
                            $statusText = '已取消';
                            break;
                        case 'completed':
                            $statusText = '已完成';
                            break;
                        default:
                            $statusText = $newStatus;
                    }
                    $appointmentDate = date('Y-m-d', strtotime($appointmentTime));
                            $content = '您的预约状态已更新为：' . $statusText . '，预约时间：' . $appointmentDate;
                    
                    // 发送通知
                    $sql = "INSERT INTO notifications (user_id, title, content, type) VALUES (?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$userId, $title, $content, 'appointment']);
                }
                
                echo json_encode(['code' => 200, 'message' => '预约更新成功', 'data' => null]);
            } else {
                echo json_encode(['code' => 400, 'message' => '预约更新失败', 'data' => null]);
            }
        } catch (Exception $e) {
            echo json_encode(['code' => 500, 'message' => '预约更新失败: ' . $e->getMessage(), 'data' => null]);
        }
        break;
        
    case 'confirm':
        // 确认预约
        $id = $_POST['id'] ?? '';
        if (empty($id)) {
            echo json_encode(['code' => 400, 'message' => '缺少预约ID参数', 'data' => null]);
            exit;
        }
        
        try {
            // 先获取预约信息，包括用户ID
            $sql = "SELECT user_id, appointment_time FROM appointments WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$id]);
            $appointment = $stmt->fetch();
            
            if (!$appointment) {
                echo json_encode(['code' => 404, 'message' => '预约不存在', 'data' => null]);
                exit;
            }
            
            $userId = $appointment['user_id'];
            $appointmentTime = $appointment['appointment_time'];
            
            // 更新预约状态为已确认
            $sql = "UPDATE appointments SET status = 'confirmed' WHERE id = ?";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([$id]);
            
            if ($result) {
                // 发送通知给用户
                $title = '预约已确认';
                $appointmentDate = date('Y-m-d', strtotime($appointmentTime));
                $content = '您的预约已被确认，预约时间：' . $appointmentDate . '，请准时到达医院。';
                
                $sql = "INSERT INTO notifications (user_id, title, content, type) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$userId, $title, $content, 'appointment']);
                
                echo json_encode(['code' => 200, 'message' => '预约确认成功', 'data' => null]);
            } else {
                echo json_encode(['code' => 400, 'message' => '预约确认失败', 'data' => null]);
            }
        } catch (Exception $e) {
            echo json_encode(['code' => 500, 'message' => '预约确认失败: ' . $e->getMessage(), 'data' => null]);
        }
        break;
        
    case 'delete':
        // 删除预约
        $id = $_POST['id'] ?? '';
        if (empty($id)) {
            echo json_encode(['code' => 400, 'message' => '缺少预约ID参数', 'data' => null]);
            exit;
        }
        
        try {
            // 执行删除操作
            $sql = "DELETE FROM appointments WHERE id = ?";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([$id]);
            
            if ($result) {
                echo json_encode(['code' => 200, 'message' => '预约删除成功', 'data' => null]);
            } else {
                echo json_encode(['code' => 400, 'message' => '预约删除失败', 'data' => null]);
            }
        } catch (Exception $e) {
            echo json_encode(['code' => 500, 'message' => '预约删除失败: ' . $e->getMessage(), 'data' => null]);
        }
        break;
        
    case 'batchDelete':
        // 批量删除预约
        $ids = $_POST['ids'] ?? '';
        if (empty($ids)) {
            echo json_encode(['code' => 400, 'message' => '缺少预约ID参数', 'data' => null]);
            exit;
        }
        
        try {
            // 将ids字符串转换为数组
            $idArray = explode(',', $ids);
            
            // 执行批量删除操作
            $placeholders = str_repeat('?,', count($idArray) - 1) . '?';
            $sql = "DELETE FROM appointments WHERE id IN ($placeholders)";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute($idArray);
            
            if ($result) {
                echo json_encode(['code' => 200, 'message' => '批量删除成功', 'data' => null]);
            } else {
                echo json_encode(['code' => 400, 'message' => '批量删除失败', 'data' => null]);
            }
        } catch (Exception $e) {
            echo json_encode(['code' => 500, 'message' => '批量删除失败: ' . $e->getMessage(), 'data' => null]);
        }
        break;
        
    default:
        echo json_encode(['code' => 400, 'message' => '无效的操作类型', 'data' => null]);
        break;
}
?>