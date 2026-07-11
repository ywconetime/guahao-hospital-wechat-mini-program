<?php
// API配置文件
require_once __DIR__ . '/../admin/includes/config.php';

// 检查API访问权限
function checkApiAuth() {
    // 首先检查域名访问权限
    checkDomainAccess();
    
    // 从请求头获取token
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($token)) {
        // 尝试从GET参数获取
        $token = $_GET['token'] ?? '';
    }
    
    // 移除Bearer前缀（如果有）
    $token = str_replace('Bearer ', '', $token);
    
    $payload = validateApiToken($token);
    if (!$payload) {
        http_response_code(401);
        echo json_encode([
            'code' => 401,
            'message' => '未授权访问'
        ]);
        exit;
    }
    
    return $payload;
}

// 不需要验证的API路径
$publicApiPaths = [
    '/api/Doctor/getDoctors.php',
    '/api/Doctor/getDoctorDetail.php',
    '/api/Doctor/getDoctorSchedules.php',
    '/api/Doctor/getDoctorDiseases.php',
    '/api/appointment/getUserAppointments.php',
    '/api/appointment/cancelAppointment.php',
    '/api/appointment/getAppointmentDetail.php',
    '/api/carousel/getCarousel.php',
    '/api/home_modules/getModules.php',
    '/api/notification/getNotifications.php',
    '/api/patient/getPatient.php',
    '/api/patient/getPatients.php',
    '/api/patient/addPatient.php',
    '/api/patient/createPatient.php',
    '/api/patient/updatePatient.php',
    '/api/patient/deletePatient.php',
    '/api/tabbar/getTabbar.php',
    '/api/get_appointments.php',
    '/api/get_departments.php',
    '/api/get_disease_doctors.php',
    '/api/get_diseases.php',
    '/api/get_diseases_simple.php',
    '/api/get_doctors.php',
    '/api/get_doctor_schedules.php',
    '/api/get_settings.php',
    '/api/get_theme_color.php',
    '/api/get_users.php',
    '/api/User/login.php',
    '/api/User/bindPhone.php',
    '/api/User/getUserInfo.php'
];

// 检查当前API是否需要验证
function needApiAuth() {
    global $publicApiPaths;
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    
    // 检查是否在公共API列表中
    foreach ($publicApiPaths as $path) {
        if (strpos($requestUri, $path) !== false) {
            return false;
        }
    }
    
    return true;
}

// 自动检查域名访问权限
checkDomainAccess();

// 自动检查API访问权限
if (needApiAuth()) {
    checkApiAuth();
}
?>