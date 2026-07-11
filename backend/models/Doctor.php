<?php
class Doctor {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // 根据医院和科室获取医生列表
    public function getDoctorsByHospitalAndDepartment($hospitalId, $departmentId) {
        $sql = "SELECT * FROM doctors WHERE hospital_id = ? AND department_id = ?";
        return $this->db->fetchAll($sql, [$hospitalId, $departmentId]);
    }
    
    // 根据ID获取医生详情
    public function getDoctorById($doctorId) {
        $sql = "SELECT * FROM doctors WHERE id = ?";
        return $this->db->fetchOne($sql, [$doctorId]);
    }
    
    // 获取医生的排班信息
    public function getDoctorSchedules($doctorId, $date) {
        $sql = "SELECT * FROM schedules WHERE doctor_id = ? AND date = ?";
        return $this->db->fetchAll($sql, [$doctorId, $date]);
    }
    
    // 获取医生的所有排班信息
    public function getAllDoctorSchedules($doctorId) {
        $sql = "SELECT * FROM schedules WHERE doctor_id = ? ORDER BY date ASC";
        return $this->db->fetchAll($sql, [$doctorId]);
    }
    
    // 获取所有医生
    public function getAllDoctors() {
        $sql = "SELECT * FROM doctors ORDER BY id DESC";
        return $this->db->fetchAll($sql);
    }
    
    // 搜索医生
    public function searchDoctors($keyword) {
        $sql = "SELECT * FROM doctors WHERE name LIKE ? OR title LIKE ? OR department LIKE ? OR specialty LIKE ? OR description LIKE ?";
        $params = ["%$keyword%", "%$keyword%", "%$keyword%", "%$keyword%", "%$keyword%"];
        return $this->db->fetchAll($sql, $params);
    }
}