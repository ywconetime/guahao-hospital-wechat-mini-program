<?php
// API配置
require_once __DIR__ . '/../config.php';

// 上传首页模块图标
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 检查是否有文件上传
if (!isset($_FILES['icon'])) {
    echo json_encode([
        'code' => 400,
        'message' => '上传失败：未找到文件'
    ]);
    exit;
}

if ($_FILES['icon']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => '文件超过了php.ini中的上传大小限制',
        UPLOAD_ERR_FORM_SIZE => '文件超过了表单中的上传大小限制',
        UPLOAD_ERR_PARTIAL => '文件只上传了一部分',
        UPLOAD_ERR_NO_FILE => '没有文件被上传',
        UPLOAD_ERR_NO_TMP_DIR => '缺少临时文件夹',
        UPLOAD_ERR_CANT_WRITE => '无法写入文件到磁盘',
        UPLOAD_ERR_EXTENSION => '文件上传被PHP扩展中断'
    ];
    $errorMessage = $errorMessages[$_FILES['icon']['error']] ?? '未知错误';
    echo json_encode([
        'code' => 400,
        'message' => '上传失败：' . $errorMessage
    ]);
    exit;
}

$file = $_FILES['icon'];

// 检查文件类型
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode([
        'code' => 400,
        'message' => '只支持JPG、PNG、GIF、WEBP格式的图片'
    ]);
    exit;
}

// 检查文件大小（2M限制）
if ($file['size'] > 2 * 1024 * 1024) {
    echo json_encode([
        'code' => 400,
        'message' => '图片大小不能超过2M'
    ]);
    exit;
}

// 生成唯一文件名
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = 'module_icon_' . uniqid() . '.' . $extension;

// 确保上传目录存在
$uploadDir = __DIR__ . '/../../uploads/modules/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode([
            'code' => 500,
            'message' => '创建上传目录失败'
        ]);
        exit;
    }
}

// 检查目录是否可写
if (!is_writable($uploadDir)) {
    echo json_encode([
        'code' => 500,
        'message' => '上传目录不可写'
    ]);
    exit;
}

// 移动文件到上传目录
$filePath = $uploadDir . $fileName;
if (move_uploaded_file($file['tmp_name'], $filePath)) {
    // 返回相对路径，包含models前缀
    $relativePath = '/models/uploads/modules/' . $fileName;
    
    echo json_encode([
        'code' => 200,
        'message' => '上传成功',
        'data' => [
            'url' => $relativePath
        ]
    ]);
} else {
    echo json_encode([
        'code' => 500,
        'message' => '文件保存失败，可能是权限问题'
    ]);
}
?>