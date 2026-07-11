<?php
// 设置控制器
class SettingsController {
    // 获取系统设置
    public function getSettings() {
        require_once __DIR__ . '/../utils/Database.php';
        
        header('Content-Type: application/json');
        
        try {
            // 获取数据库连接
            $db = Database::getInstance()->getConn();
            
            // 获取系统设置
            $stmt = $db->query('SELECT key_name, value FROM settings');
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['key_name']] = $row['value'];
            }
            
            echo json_encode([
                'code' => 200,
                'message' => '获取设置成功',
                'data' => $settings
            ], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode([
                'code' => 500,
                'message' => '获取设置失败: ' . $e->getMessage(),
                'data' => null
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
?>