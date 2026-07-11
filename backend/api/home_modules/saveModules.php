<?php
// API配置
require_once __DIR__ . '/../config.php';

// 保存首页功能模块配置
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 数据库配置
require_once __DIR__ . '/../db_config.php';

// 获取POST数据
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['modules'])) {
    echo json_encode([
        'code' => 400,
        'message' => '参数错误：缺少modules数据'
    ]);
    exit;
}

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
    }
    
    $modules = $input['modules'];
    
    // 开始事务
    $pdo->beginTransaction();
    
    // 清空现有数据
    $pdo->exec("DELETE FROM home_modules");
    
    // 重置自增ID
    $pdo->exec("ALTER TABLE home_modules AUTO_INCREMENT = 1");
    
    // 插入新数据
    $stmt = $pdo->prepare("INSERT INTO home_modules (name, title, subtitle, icon, link_type, link_url, sort_order, is_show) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($modules as $index => $module) {
        $stmt->execute([
            $module['name'] ?? '',
            $module['title'] ?? '',
            $module['subtitle'] ?? '',
            $module['icon'] ?? '',
            $module['link_type'] ?? 'page',
            $module['link_url'] ?? '',
            $index,
            isset($module['is_show']) ? $module['is_show'] : 1
        ]);
    }
    
    // 提交事务
    $pdo->commit();
    
    echo json_encode([
        'code' => 200,
        'message' => '保存成功'
    ]);
    
} catch (PDOException $e) {
    // 回滚事务
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'code' => 500,
        'message' => '数据库错误：' . $e->getMessage()
    ]);
}
?>