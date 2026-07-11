<?php
class Hospital {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // 获取所有医院
    public function getAllHospitals() {
        $sql = "SELECT * FROM hospitals";
        return $this->db->fetchAll($sql);
    }
    
    // 根据ID获取医院详情
    public function getHospitalById($hospitalId) {
        $sql = "SELECT * FROM hospitals WHERE id = ?";
        return $this->db->fetchOne($sql, [$hospitalId]);
    }
    
    // 获取医院的科室列表
    public function getDepartmentsByHospitalId($hospitalId) {
        $sql = "SELECT * FROM departments WHERE hospital_id = ?";
        return $this->db->fetchAll($sql, [$hospitalId]);
    }
}