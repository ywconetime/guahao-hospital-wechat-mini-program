<?php
// 获取首页功能模块配置
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 数据库配置
require_once __DIR__ . '/../db_config.php';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 检查表是否存在
    $stmt = $pdo->query("SHOW TABLES LIKE 'home_modules'");
    if ($stmt->rowCount() == 0) {
        // 表不存在，创建表
        $pdo->exec("CREATE TABLE IF NOT EXISTS home_modules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL COMMENT '模块名称',
            title VARCHAR(100) NOT NULL COMMENT '模块标题',
            subtitle VARCHAR(200) COMMENT '模块副标题/描述',
            icon VARCHAR(500) COMMENT '图标URL',
            link_type VARCHAR(50) COMMENT '链接类型：page/tabbar/web',
            link_url VARCHAR(500) COMMENT '链接地址',
            sort_order INT DEFAULT 0 COMMENT '排序顺序',
            is_show TINYINT DEFAULT 1 COMMENT '是否显示：0隐藏 1显示',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='首页功能模块表'");
        
        // 插入默认数据
        $defaultModules = [
            ['name' => 'appointment', 'title' => '预约挂号', 'subtitle' => '线上预约，快速便捷', 'icon' => '', 'link_type' => 'tabbar', 'link_url' => '/pages/appointment/appointment', 'sort_order' => 1],
            ['name' => 'my_appointment', 'title' => '我的预约', 'subtitle' => '查看个人预约', 'icon' => '', 'link_type' => 'page', 'link_url' => '/pages/appointmentList/appointmentList', 'sort_order' => 2],
            ['name' => 'expert_team', 'title' => '专家团队', 'subtitle' => '按医生预约挂号', 'icon' => '', 'link_type' => 'tabbar', 'link_url' => '/pages/doctor/doctor', 'sort_order' => 3]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO home_modules (name, title, subtitle, icon, link_type, link_url, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($defaultModules as $module) {
            $stmt->execute([$module['name'], $module['title'], $module['subtitle'], $module['icon'], $module['link_type'], $module['link_url'], $module['sort_order']]);
        }
    }
    
    // 获取所有显示的模块
    $stmt = $pdo->query("SELECT * FROM home_modules WHERE is_show = 1 ORDER BY sort_order ASC");
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'code' => 200,
        'message' => '获取成功',
        'data' => $modules
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'code' => 500,
        'message' => '数据库错误：' . $e->getMessage(),
        'data' => []
    ]);
}
?>