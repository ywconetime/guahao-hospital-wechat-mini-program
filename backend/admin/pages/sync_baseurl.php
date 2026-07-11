<?php
/**
 * ============================================================================
 * 【灵码修复】同步baseUrl到小程序前端
 * 修复日期: 2026-05-31
 * 修复工具: 通义灵码 (Tongyi Lingma)
 * 功能说明: 将后台域名同步到小程序前端app.js文件中的baseUrl字段
 * 数据流向: 后台设置页面 -> 此API -> xuaochengxu/app.js
 * 影响范围: admin/pages/sync_baseurl.php（独立新增文件）
 * 风险评估: 🟢低风险（独立功能，失败不影响其他功能）
 * ============================================================================
 */

header('Content-Type: application/json; charset=utf-8');

try {
    // 获取请求参数
    $baseUrl = isset($_POST['base_url']) ? trim($_POST['base_url']) : '';
    
    // 验证参数
    if (empty($baseUrl)) {
        echo json_encode([
            'code' => 400,
            'message' => '请输入后台域名'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 验证URL格式
    if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
        echo json_encode([
            'code' => 400,
            'message' => '请输入有效的URL地址（如：http://localhost:88）'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 构建小程序前端app.js文件路径（统一用 / 分隔符）
    $appJsPath = __DIR__ . '/../../xuaochengxu/app.js';
    $appJsPath = str_replace('\\', '/', $appJsPath);
    
    // 检查文件是否存在
    if (!file_exists($appJsPath)) {
        echo json_encode([
            'code' => 200,
            'message' => '💡 提示：此功能仅用于本地开发环境！' . "\n\n" .
                         '在云服务器上，小程序前端通过API自动获取配置。' . "\n\n" .
                         '你的baseUrl是：' . $baseUrl
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 读取app.js内容
    $content = file_get_contents($appJsPath);
    
    // 正则匹配并替换baseUrl
    // 匹配格式: baseUrl: 'xxx' 或 baseUrl: "xxx" 或 baseUrl: '' (空字符串)
    $pattern = "/baseUrl:\s*['\"]([^'\"]*)['\"]/";
    $replacement = "baseUrl: '$baseUrl'";
    
    $newContent = preg_replace($pattern, $replacement, $content);
    
    // 检查是否替换成功
    if ($newContent === $content) {
        echo json_encode([
            'code' => 500,
            'message' => '未能找到baseUrl字段进行替换，请检查app.js文件格式'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 写入修改后的内容
    $result = file_put_contents($appJsPath, $newContent);
    
    if ($result === false) {
        echo json_encode([
            'code' => 500,
            'message' => '写入文件失败，请检查文件权限'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 同步成功
    echo json_encode([
        'code' => 200,
        'message' => 'baseUrl已成功同步到小程序前端配置文件！' . "\n" .
                     '文件位置: xuaochengxu/app.js' . "\n" .
                     '新的baseUrl: ' . $baseUrl
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log('同步baseUrl失败: ' . $e->getMessage());
    echo json_encode([
        'code' => 500,
        'message' => '系统错误，请稍后重试: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>