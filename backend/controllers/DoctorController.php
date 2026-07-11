<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/Doctor.php';

class DoctorController extends ApiController {
    private $doctorModel;
    
    public function __construct() {
        $this->doctorModel = new Doctor();
    }
    
    // 获取医生列表
    public function getDoctors() {
        $params = $this->getParams();
        $hospitalId = $params['hospital_id'] ?? '';
        $departmentId = $params['department_id'] ?? '';
        
        if (empty($hospitalId) || empty($departmentId)) {
            // 无参数时获取所有医生
            $doctors = $this->doctorModel->getAllDoctors();
        } else {
            // 有参数时按医院和科室筛选
            $doctors = $this->doctorModel->getDoctorsByHospitalAndDepartment($hospitalId, $departmentId);
        }
        
        $this->success($doctors, '获取医生列表成功');
    }
    
    // 获取医生详情
    public function getDoctorDetail() {
        $params = $this->getParams();
        $doctorId = $params['doctor_id'] ?? '';
        
        if (empty($doctorId)) {
            $this->error('缺少doctor_id参数');
        }
        
        $doctor = $this->doctorModel->getDoctorById($doctorId);
        if ($doctor) {
            $this->success($doctor, '获取医生详情成功');
        } else {
            $this->error('医生不存在');
        }
    }
    
    // 获取医生排班
    public function getDoctorSchedules() {
        $params = $this->getParams();
        $doctorId = $params['doctor_id'] ?? '';
        $date = $params['date'] ?? date('Y-m-d');
        
        if (empty($doctorId)) {
            $this->error('缺少doctor_id参数');
        }
        
        $schedules = $this->doctorModel->getDoctorSchedules($doctorId, $date);
        $this->success($schedules, '获取医生排班成功');
    }
    
    // 获取医生所有排班
    public function getAllDoctorSchedules() {
        $params = $this->getParams();
        $doctorId = $params['doctor_id'] ?? '';
        
        if (empty($doctorId)) {
            $this->error('缺少doctor_id参数');
        }
        
        $schedules = $this->doctorModel->getAllDoctorSchedules($doctorId);
        $this->success($schedules, '获取医生所有排班成功');
    }
}