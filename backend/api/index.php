<?php
// 授权检查（放在最前面）
require_once __DIR__ . '/check_license.php';
if (!SimpleLicenseChecker::check()) {
    SimpleLicenseChecker::showUnauthorizedResponse();
}

// 引入必要的文件
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../controllers/HospitalController.php';
require_once __DIR__ . '/../controllers/DoctorController.php';
require_once __DIR__ . '/../controllers/AppointmentController.php';
require_once __DIR__ . '/../controllers/NoticeController.php';
require_once __DIR__ . '/../controllers/SettingsController.php';
require_once __DIR__ . '/../controllers/SearchController.php';

// 获取请求路径
$path = $_SERVER['REQUEST_URI'];
// 去除查询参数
$path = parse_url($path, PHP_URL_PATH);
// 去除开头的斜杠
$path = ltrim($path, '/');
// 分割路径
$parts = explode('/', $path);

// 调试信息
// echo json_encode([
//     'path' => $path,
//     'parts' => $parts
// ]);
// exit;

// 找到api所在的索引
$apiIndex = array_search('api', $parts);

// 获取控制器和方法
$controller = isset($parts[$apiIndex + 1]) ? trim($parts[$apiIndex + 1]) : '';
$method = isset($parts[$apiIndex + 2]) ? trim($parts[$apiIndex + 2]) : '';

// 处理API根路径
if (empty($controller)) {
    http_response_code(200);
    echo json_encode([
        'code' => 200,
        'message' => 'API服务正常',
        'data' => [
            'version' => '1.0.0',
            'endpoints' => [
                '/api/Doctor/getDoctors' => '获取医生列表',
                '/api/Settings/getSettings' => '获取系统设置',
                '/api/User/getUsers' => '获取用户列表',
                '/api/Appointment/getAppointments' => '获取预约列表'
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 处理控制器名称
$controller = ucfirst($controller) . 'Controller';

// 检查控制器是否存在
if (!class_exists($controller)) {
    http_response_code(404);
    echo json_encode(['code' => 404, 'message' => '控制器不存在', 'data' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

// 检查方法是否存在
if (!method_exists($controller, $method)) {
    http_response_code(404);
    echo json_encode(['code' => 404, 'message' => '方法不存在', 'data' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

// 实例化控制器并调用方法
$controllerInstance = new $controller();
$controllerInstance->$method();