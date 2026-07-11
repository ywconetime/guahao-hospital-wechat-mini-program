<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/Hospital.php';

class HospitalController extends ApiController {
    private $hospitalModel;
    
    public function __construct() {
        $this->hospitalModel = new Hospital();
    }
    
    // 获取医院列表
    public function getHospitals() {
        $hospitals = $this->hospitalModel->getAllHospitals();
        $this->success($hospitals, '获取医院列表成功');
    }
    
    // 获取医院详情
    public function getHospitalDetail() {
        $params = $this->getParams();
        $hospitalId = $params['hospital_id'] ?? '';
        
        if (empty($hospitalId)) {
            $this->error('缺少hospital_id参数');
        }
        
        $hospital = $this->hospitalModel->getHospitalById($hospitalId);
        if ($hospital) {
            $this->success($hospital, '获取医院详情成功');
        } else {
            $this->error('医院不存在');
        }
    }
    
    // 获取医院科室列表
    public function getDepartments() {
        $params = $this->getParams();
        $hospitalId = $params['hospital_id'] ?? '';
        
        if (empty($hospitalId)) {
            $this->error('缺少hospital_id参数');
        }
        
        $departments = $this->hospitalModel->getDepartmentsByHospitalId($hospitalId);
        $this->success($departments, '获取科室列表成功');
    }
}