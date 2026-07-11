<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/Appointment.php';
require_once __DIR__ . '/../models/User.php';

class AppointmentController extends ApiController {
    private $appointmentModel;
    
    public function __construct() {
        $this->appointmentModel = new Appointment();
    }
    
    // 创建预约
    public function createAppointment() {
        $params = $this->getParams();
        $userId = $this->getUserId();
        
        $doctorId = $params['doctor_id'] ?? '';
        $scheduleId = $params['schedule_id'] ?? '';
        $hospitalId = $params['hospital_id'] ?? '';
        $departmentId = $params['department_id'] ?? '';
        $appointmentTime = $params['appointment_time'] ?? '';
        
        if (empty($doctorId) || empty($scheduleId) || empty($hospitalId) || empty($departmentId) || empty($appointmentTime)) {
            $this->error('缺少必要参数');
        }
        
        try {
            // 尝试更新用户信息（捕获可能的数据库列不存在错误）
            try {
                $userModel = new User();
                $userData = [
                    'real_name' => $params['patient_name'] ?? '',
                    'phone' => $params['patient_phone'] ?? '',
                    'gender' => $params['patient_gender'] ?? '',
                    'age' => $params['patient_age'] ?? ''
                ];
                // 只更新确定存在的字段
                $userModel->updateUser($userId, $userData);
            } catch (Exception $userError) {
                // 忽略用户信息更新错误，继续创建预约
                error_log('用户信息更新失败: ' . $userError->getMessage());
            }
            
            // 创建预约
            $data = [
                'user_id' => $userId,
                'doctor_id' => $doctorId,
                'schedule_id' => $scheduleId,
                'hospital_id' => $hospitalId,
                'department_id' => $departmentId,
                'appointment_time' => $appointmentTime
            ];
            
            $appointmentId = $this->appointmentModel->createAppointment($data);
            $appointment = $this->appointmentModel->getAppointmentById($appointmentId);
            $this->success($appointment, '预约成功');
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }
    
    // 获取用户预约列表
    public function getUserAppointments() {
        $userId = $this->getUserId();
        $appointments = $this->appointmentModel->getUserAppointments($userId);
        $this->success($appointments, '获取预约列表成功');
    }
    
    // 获取预约详情
    public function getAppointmentDetail() {
        $params = $this->getParams();
        $appointmentId = $params['appointment_id'] ?? '';
        
        if (empty($appointmentId)) {
            $this->error('缺少appointment_id参数');
        }
        
        $appointment = $this->appointmentModel->getAppointmentById($appointmentId);
        if ($appointment) {
            $this->success($appointment, '获取预约详情成功');
        } else {
            $this->error('预约不存在');
        }
    }
    
    // 取消预约
    public function cancelAppointment() {
        $params = $this->getParams();
        $appointmentId = $params['appointment_id'] ?? '';
        
        if (empty($appointmentId)) {
            $this->error('缺少appointment_id参数');
        }
        
        try {
            $result = $this->appointmentModel->cancelAppointment($appointmentId);
            if ($result) {
                $this->success(null, '预约取消成功');
            } else {
                $this->error('预约取消失败');
            }
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }
    
    // 更新预约状态
    public function updateAppointmentStatus() {
        $params = $this->getParams();
        $appointmentId = $params['appointment_id'] ?? '';
        $status = $params['status'] ?? '';
        
        if (empty($appointmentId) || empty($status)) {
            $this->error('缺少必要参数');
        }
        
        $result = $this->appointmentModel->updateAppointmentStatus($appointmentId, $status);
        if ($result) {
            $this->success(null, '预约状态更新成功');
        } else {
            $this->error('预约状态更新失败');
        }
    }
    
    // 删除预约
    public function deleteAppointment() {
        $params = $this->getParams();
        $appointmentId = $params['appointment_id'] ?? '';
        
        if (empty($appointmentId)) {
            $this->error('缺少appointment_id参数');
        }
        
        try {
            $result = $this->appointmentModel->deleteAppointment($appointmentId);
            if ($result) {
                $this->success(null, '预约删除成功');
            } else {
                $this->error('预约删除失败');
            }
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }
    
    // 更新预约
    public function updateAppointment() {
        $params = $this->getParams();
        $appointmentId = $params['id'] ?? '';
        
        if (empty($appointmentId)) {
            $this->error('缺少预约ID参数');
        }
        
        try {
            $result = $this->appointmentModel->updateAppointment($appointmentId, $params);
            if ($result) {
                $this->success(null, '预约更新成功');
            } else {
                $this->error('预约更新失败');
            }
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }
}