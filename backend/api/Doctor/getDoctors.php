<?php
// 获取医生列表的API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // 允许跨域访问

try {
    require_once __DIR__ . '/../../utils/Database.php';
    
    // 获取数据库连接
    $db = Database::getInstance()->getConn();
    
    // 检查doctors表是否存在
    $stmt = $db->query("SHOW TABLES LIKE 'doctors'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        // 创建doctors表
        $createTableSql = "
        CREATE TABLE IF NOT EXISTS doctors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            title VARCHAR(255) NOT NULL,
            department VARCHAR(255) NOT NULL,
            specialty VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            avatar VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $db->exec($createTableSql);
        
        // 插入一些测试数据
        $insertTestDataSql = "
        INSERT INTO doctors (name, title, department, specialty, description) VALUES
        ('张医生', '主任医师', '妇产科', '妇科肿瘤', '从事妇产科临床工作20余年，擅长妇科肿瘤的诊断与治疗'),
        ('李医生', '副主任医师', '妇产科', '产前诊断', '专注于产前诊断和高危妊娠管理，经验丰富'),
        ('王医生', '主治医师', '妇产科', '不孕不育', '擅长不孕不育的诊断与治疗，帮助众多家庭实现生育愿望');
        ";
        
        $db->exec($insertTestDataSql);
    }
    
    // 获取筛选参数
    $department_id = isset($_GET['department_id']) ? $_GET['department_id'] : null;
    $department_name = isset($_GET['department_name']) ? $_GET['department_name'] : null;
    $keyword = isset($_GET['keyword']) ? $_GET['keyword'] : null;
    $disease_id = isset($_GET['disease_id']) ? $_GET['disease_id'] : null;
    
    // 构建查询
    if ($keyword) {
        // 使用关键词搜索
        $stmt = $db->prepare('SELECT * FROM doctors WHERE name LIKE ? OR department LIKE ? OR title LIKE ? OR specialty LIKE ? ORDER BY id DESC');
        $searchTerm = '%' . $keyword . '%';
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $doctors = $stmt->fetchAll();
    } elseif ($disease_id && $department_id) {
        // 使用病种ID和科室ID筛选
        try {
            $stmt = $db->prepare('SELECT d.* FROM doctors d JOIN doctor_departments dd ON d.id = dd.doctor_id JOIN doctor_diseases ds ON d.id = ds.doctor_id WHERE dd.department_id = ? AND ds.disease_id = ? ORDER BY d.id DESC');
            $stmt->execute([$department_id, $disease_id]);
            $doctors = $stmt->fetchAll();
            // 如果没有匹配的医生，返回所有医生
            if (empty($doctors)) {
                $stmt = $db->query('SELECT * FROM doctors ORDER BY id DESC');
                $doctors = $stmt->fetchAll();
            }
        } catch (Exception $e) {
            // 如果表不存在或查询失败，返回所有医生
            $stmt = $db->query('SELECT * FROM doctors ORDER BY id DESC');
            $doctors = $stmt->fetchAll();
        }
    } elseif ($disease_id) {
        // 使用病种ID筛选
        try {
            $stmt = $db->prepare('SELECT d.* FROM doctors d JOIN doctor_diseases ds ON d.id = ds.doctor_id WHERE ds.disease_id = ? ORDER BY d.id DESC');
            $stmt->execute([$disease_id]);
            $doctors = $stmt->fetchAll();
            // 如果没有匹配的医生，返回所有医生
            if (empty($doctors)) {
                $stmt = $db->query('SELECT * FROM doctors ORDER BY id DESC');
                $doctors = $stmt->fetchAll();
            }
        } catch (Exception $e) {
            // 如果表不存在或查询失败，返回所有医生
            $stmt = $db->query('SELECT * FROM doctors ORDER BY id DESC');
            $doctors = $stmt->fetchAll();
        }
    } elseif ($department_id) {
        // 使用科室ID筛选
        try {
            // 检查doctor_departments表是否存在
            $stmt = $db->query("SHOW TABLES LIKE 'doctor_departments'");
            $tableExists = $stmt->rowCount() > 0;
            
            if ($tableExists) {
                $stmt = $db->prepare('SELECT d.* FROM doctors d JOIN doctor_departments dd ON d.id = dd.doctor_id WHERE dd.department_id = ? ORDER BY d.id DESC');
                $stmt->execute([$department_id]);
                $doctors = $stmt->fetchAll();
            } else {
                // 如果表不存在，根据doctors表中的department字段进行筛选
                // 先获取科室名称
                $stmt = $db->prepare('SELECT name FROM departments WHERE id = ?');
                $stmt->execute([$department_id]);
                $department = $stmt->fetch();
                
                if ($department) {
                    $departmentName = $department['name'];
                    $stmt = $db->prepare('SELECT * FROM doctors WHERE department = ? ORDER BY id DESC');
                    $stmt->execute([$departmentName]);
                    $doctors = $stmt->fetchAll();
                } else {
                    $doctors = [];
                }
            }
        } catch (Exception $e) {
            // 如果查询失败，返回空结果
            $doctors = [];
        }
    } elseif ($department_name) {
        // 使用科室名称筛选
        $stmt = $db->prepare('SELECT * FROM doctors WHERE department = ? ORDER BY id DESC');
        $stmt->execute([$department_name]);
        $doctors = $stmt->fetchAll();
        // 如果没有匹配的医生，返回所有医生
        if (empty($doctors)) {
            $stmt = $db->query('SELECT * FROM doctors ORDER BY id DESC');
            $doctors = $stmt->fetchAll();
        }
    } else {
        // 获取所有医生
        $stmt = $db->query('SELECT * FROM doctors ORDER BY id DESC');
        $doctors = $stmt->fetchAll();
    }
    
    // 为没有头像的医生添加默认头像，并处理头像路径
    foreach ($doctors as &$doctor) {
        if (!isset($doctor['avatar']) || empty($doctor['avatar'])) {
            $doctor['avatar'] = 'https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=doctor%20portrait%20professional%20in%20white%20coat&image_size=square';
        } else if (strpos($doctor['avatar'], 'http://') === false && strpos($doctor['avatar'], 'https://') === false) {
            // 检查头像文件是否存在于 admin/uploads 目录
            $avatarFile = __DIR__ . '/../../admin/uploads/' . basename($doctor['avatar']);
            if (!file_exists($avatarFile)) {
                // 如果文件不存在，检查根目录的 uploads 目录
                $avatarFile = __DIR__ . '/../../uploads/' . basename($doctor['avatar']);
                if (!file_exists($avatarFile)) {
                    // 如果文件不存在，使用默认头像
                    $doctor['avatar'] = 'https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=doctor%20portrait%20professional%20in%20white%20coat&image_size=square';
                } else {
                    // 如果文件存在于根目录 uploads，使用相对路径
                    $doctor['avatar'] = 'uploads/' . basename($doctor['avatar']);
                }
            } else {
                // 如果文件存在于 admin/uploads，使用相对路径
                $doctor['avatar'] = 'admin/uploads/' . basename($doctor['avatar']);
            }
        }
    }
    
    echo json_encode([
        'code' => 200,
        'message' => '获取医生列表成功',
        'data' => $doctors
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'message' => '获取医生列表失败: ' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
?>