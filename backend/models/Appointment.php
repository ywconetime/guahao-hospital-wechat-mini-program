<?php
class Appointment {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // 创建新预约
    public function createAppointment($data) {
        // 开始事务
        $conn = $this->db->getConn();
        $conn->beginTransaction();
        
        try {
            // 检查排班是否存在
            $scheduleSql = "SELECT * FROM schedules WHERE id = ?";
            $schedule = $this->db->fetchOne($scheduleSql, [$data['schedule_id']]);
            
            if (!$schedule) {
                // 如果排班不存在，创建一个默认排班
                $insertScheduleSql = "INSERT INTO schedules (doctor_id, date, time_slot, total_quantity, remaining_quantity, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $this->db->execute($insertScheduleSql, [
                    $data['doctor_id'],
                    date('Y-m-d'),
                    $data['time_slot'] ?? '上午',
                    100,
                    99,
                    '08:00:00',
                    '18:00:00'
                ]);
                $data['schedule_id'] = $this->db->lastInsertId();
            }
            
            // 创建预约
            $appointmentSql = "INSERT INTO appointments (user_id, doctor_id, schedule_id, hospital_id, department_id, appointment_time) VALUES (?, ?, ?, ?, ?, ?)";
            $appointmentParams = [
                $data['user_id'],
                $data['doctor_id'],
                $data['schedule_id'],
                $data['hospital_id'],
                $data['department_id'],
                $data['appointment_time']
            ];
            $this->db->execute($appointmentSql, $appointmentParams);
            $appointmentId = $this->db->lastInsertId();
            
            // 更新排班剩余号数
            $updateScheduleSql = "UPDATE schedules SET remaining_quantity = remaining_quantity - 1 WHERE id = ?";
            $this->db->execute($updateScheduleSql, [$data['schedule_id']]);
            
            // 提交事务
            $conn->commit();
            return $appointmentId;
        } catch (Exception $e) {
            // 回滚事务
            $conn->rollback();
            throw $e;
        }
    }
    
    // 获取用户的预约列表
    public function getUserAppointments($userId) {
        $sql = "SELECT a.*, d.name as doctor_name, d.title, h.name as hospital_name, de.name as department_name, u.real_name as patient_name, u.gender as patient_gender, u.age as patient_age, u.phone as patient_phone 
                FROM appointments a 
                JOIN doctors d ON a.doctor_id = d.id 
                JOIN hospitals h ON a.hospital_id = h.id 
                JOIN departments de ON a.department_id = de.id 
                JOIN users u ON a.user_id = u.id 
                WHERE a.user_id = ? 
                ORDER BY a.created_at DESC";
        return $this->db->fetchAll($sql, [$userId]);
    }
    
    // 根据ID获取预约详情
    public function getAppointmentById($appointmentId) {
        $sql = "SELECT a.*, d.name as doctor_name, d.title, h.name as hospital_name, de.name as department_name, u.real_name as patient_name, u.gender as patient_gender, u.age as patient_age, u.phone as patient_phone 
                FROM appointments a 
                JOIN doctors d ON a.doctor_id = d.id 
                JOIN hospitals h ON a.hospital_id = h.id 
                JOIN departments de ON a.department_id = de.id 
                JOIN users u ON a.user_id = u.id 
                WHERE a.id = ?";
        return $this->db->fetchOne($sql, [$appointmentId]);
    }
    
    // 取消预约
    public function cancelAppointment($appointmentId) {
        // 开始事务
        $conn = $this->db->getConn();
        $conn->beginTransaction();
        
        try {
            // 获取预约信息
            $appointmentSql = "SELECT * FROM appointments WHERE id = ?";
            $appointment = $this->db->fetchOne($appointmentSql, [$appointmentId]);
            
            if (!$appointment) {
                throw new Exception('预约不存在');
            }
            
            if ($appointment['status'] == 'cancelled') {
                throw new Exception('预约已取消');
            }
            
            // 更新预约状态
            $updateAppointmentSql = "UPDATE appointments SET status = 'cancelled' WHERE id = ?";
            $this->db->execute($updateAppointmentSql, [$appointmentId]);
            
            // 更新排班剩余号数
            $updateScheduleSql = "UPDATE schedules SET remaining_quantity = remaining_quantity + 1 WHERE id = ?";
            $this->db->execute($updateScheduleSql, [$appointment['schedule_id']]);
            
            // 提交事务
            $conn->commit();
            return true;
        } catch (Exception $e) {
            // 回滚事务
            $conn->rollback();
            throw $e;
        }
    }
    
    // 更新预约状态
    public function updateAppointmentStatus($appointmentId, $status) {
        $sql = "UPDATE appointments SET status = ? WHERE id = ?";
        return $this->db->execute($sql, [$status, $appointmentId]) > 0;
    }
    
    // 删除预约
    public function deleteAppointment($appointmentId) {
        // 开始事务
        $conn = $this->db->getConn();
        $conn->beginTransaction();
        
        try {
            // 获取预约信息
            $appointmentSql = "SELECT * FROM appointments WHERE id = ?";
            $appointment = $this->db->fetchOne($appointmentSql, [$appointmentId]);
            
            if (!$appointment) {
                throw new Exception('预约不存在');
            }
            
            // 更新排班剩余号数
            $updateScheduleSql = "UPDATE schedules SET remaining_quantity = remaining_quantity + 1 WHERE id = ?";
            $this->db->execute($updateScheduleSql, [$appointment['schedule_id']]);
            
            // 删除预约
            $deleteAppointmentSql = "DELETE FROM appointments WHERE id = ?";
            $this->db->execute($deleteAppointmentSql, [$appointmentId]);
            
            // 提交事务
            $conn->commit();
            return true;
        } catch (Exception $e) {
            // 回滚事务
            $conn->rollback();
            throw $e;
        }
    }
    
    // 更新预约
    public function updateAppointment($appointmentId, $data) {
        // 开始事务
        $conn = $this->db->getConn();
        $conn->beginTransaction();
        
        try {
            // 检查预约是否存在
            $appointmentSql = "SELECT * FROM appointments WHERE id = ?";
            $appointment = $this->db->fetchOne($appointmentSql, [$appointmentId]);
            
            if (!$appointment) {
                throw new Exception('预约不存在');
            }
            
            // 构建更新语句
            $updateFields = [];
            $updateParams = [];
            
            if (isset($data['doctor_id'])) {
                $updateFields[] = 'doctor_id = ?';
                $updateParams[] = $data['doctor_id'];
            }
            if (isset($data['appointment_time'])) {
                $updateFields[] = 'appointment_time = ?';
                $updateParams[] = $data['appointment_time'];
            }
            if (isset($data['status'])) {
                $updateFields[] = 'status = ?';
                $updateParams[] = $data['status'];
            }
            
            if (empty($updateFields)) {
                throw new Exception('没有需要更新的字段');
            }
            
            // 添加预约ID到参数列表
            $updateParams[] = $appointmentId;
            
            // 执行更新
            $updateSql = "UPDATE appointments SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $this->db->execute($updateSql, $updateParams);
            
            // 提交事务
            $conn->commit();
            return true;
        } catch (Exception $e) {
            // 回滚事务
            $conn->rollback();
            throw $e;
        }
    }
}