<?php
// 管理后台系统设置页面
// 设置浏览器缓存控制 - 禁用缓存以确保实时更新
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// 页面标题
$pageTitle = '系统设置';
// 当前活动页面
$activePage = 'settings';

$db = getAdminDB();

// 表是否可用的标记
$tabbarTableAvailable = false;
$carouselTableAvailable = false;

// 检查表是否可用，如果不可用则尝试创建
if ($db !== null) {
    // 检查并创建 tabbar_icons 表
    $result = $db->query("SHOW TABLES LIKE 'tabbar_icons'");
    if ($result && $result->rowCount() > 0) {
        $tabbarTableAvailable = true;
    } else {
        // 表不存在，尝试创建
        try {
            $db->exec("CREATE TABLE tabbar_icons (id int(11) NOT NULL AUTO_INCREMENT, menu_key varchar(50) NOT NULL, menu_text varchar(50) NOT NULL, icon_path varchar(255) DEFAULT NULL, selected_icon_path varchar(255) DEFAULT NULL, page_path varchar(255) DEFAULT NULL, sort_order int(11) NOT NULL DEFAULT '0', status tinyint(1) NOT NULL DEFAULT '1', created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY menu_key (menu_key), KEY sort_order (sort_order), KEY status (status)) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");
            $db->exec("INSERT INTO tabbar_icons (menu_key, menu_text, page_path, sort_order) VALUES ('home', '首页', '/pages/index/index', 0), ('appointment', '预约挂号', '/pages/appointment/appointment', 1), ('doctor', '专家团队', '/pages/doctor/doctor', 2), ('mine', '我的', '/pages/my/my', 3)");
            $tabbarTableAvailable = true;
        } catch (PDOException $e) {
            // 创建失败，记录错误
        }
    }
    
    // 检查并创建 carousel 表
    $result = $db->query("SHOW TABLES LIKE 'carousel'");
    if ($result && $result->rowCount() > 0) {
        $carouselTableAvailable = true;
    } else {
        // 表不存在，尝试创建
        try {
            $db->exec("CREATE TABLE carousel (id int(11) NOT NULL AUTO_INCREMENT, image_url varchar(255) NOT NULL, title varchar(100) NOT NULL, link varchar(255) DEFAULT NULL, sort_order int(11) NOT NULL DEFAULT '0', status enum('active','inactive') NOT NULL DEFAULT 'active', created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY sort_order (sort_order), KEY status (status)) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");
            $db->exec("INSERT INTO carousel (image_url, title, link, sort_order, status) VALUES ('https://neeko-copilot.bytedance.net/api/text_to_image?prompt=medical%20appointment%20banner%20with%20doctor%20illustration%20blue%20background&image_size=landscape_16_9', '自助预约挂号', '/pages/appointment/appointment', 0, 'active'), ('https://neeko-copilot.bytedance.net/api/text_to_image?prompt=health%20care%20service%20banner%20professional%20medical&image_size=landscape_16_9', '在线问诊服务', '/pages/doctor/doctor', 1, 'active'), ('https://neeko-copilot.bytedance.net/api/text_to_image?prompt=medical%20team%20expert%20doctors%20banner&image_size=landscape_16_9', '专家团队', '/pages/doctor/doctor', 2, 'active')");
            $carouselTableAvailable = true;
        } catch (PDOException $e) {
            // 创建失败，记录错误
        }
    }
}

// 获取系统设置
$settings = [];
if ($db !== null) {
    $stmt = $db->query('SELECT key_name, value FROM settings');
    while ($row = $stmt->fetch()) {
        $settings[$row['key_name']] = $row['value'];
    }
}

// 保存设置
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($db !== null) {
        // 处理底部菜单操作
        if (isset($_POST['tabbar_action']) && $tabbarTableAvailable) {
            switch ($_POST['tabbar_action']) {
                case 'update':
                    // 处理底部菜单更新
                    $id = $_POST['tabbar_id'];
                    $menuKey = $_POST['menu_key'];
                    $menuText = $_POST['menu_text'];
                    $pagePath = $_POST['page_path'];
                    $sortOrder = $_POST['sort_order'];
                    
                    // 处理默认图标上传
                    $iconPath = '';
                    if (isset($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK) {
                        // 验证文件大小（1M以内）
                        if ($_FILES['icon']['size'] > 1 * 1024 * 1024) {
                            $_SESSION['error_message'] = '图片大小不能超过1M';
                            header('Location: settings.php');
                            exit;
                        }
                        
                        // 验证文件类型
                        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                        $fileType = $_FILES['icon']['type'];
                        if (!in_array($fileType, $allowedTypes)) {
                            $_SESSION['error_message'] = '图片格式必须是jpg、png或gif';
                            header('Location: settings.php');
                            exit;
                        }
                        
                        // 验证图片尺寸比例（1:1）
                        list($width, $height) = getimagesize($_FILES['icon']['tmp_name']);
                        // 允许2%的误差
                        if (abs($width - $height) > $width * 0.02) {
                            $_SESSION['error_message'] = '图片尺寸比例必须是1:1';
                            header('Location: settings.php');
                            exit;
                        }
                        
                        $uploadDir = __DIR__ . '/../../xuaochengxu/images/';
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        $fileExtension = pathinfo($_FILES['icon']['name'], PATHINFO_EXTENSION);
                        $fileName = $menuKey . '.' . $fileExtension;
                        $filePath = $uploadDir . $fileName;
                        if (move_uploaded_file($_FILES['icon']['tmp_name'], $filePath)) {
                            $iconPath = '/xuaochengxu/images/' . $fileName;
                        }
                    }
                    
                    // 处理选中图标上传
                    $selectedIconPath = '';
                    if (isset($_FILES['selected_icon']) && $_FILES['selected_icon']['error'] === UPLOAD_ERR_OK) {
                        // 验证文件大小（1M以内）
                        if ($_FILES['selected_icon']['size'] > 1 * 1024 * 1024) {
                            $_SESSION['error_message'] = '图片大小不能超过1M';
                            header('Location: settings.php');
                            exit;
                        }
                        
                        // 验证文件类型
                        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                        $fileType = $_FILES['selected_icon']['type'];
                        if (!in_array($fileType, $allowedTypes)) {
                            $_SESSION['error_message'] = '图片格式必须是jpg、png或gif';
                            header('Location: settings.php');
                            exit;
                        }
                        
                        // 验证图片尺寸比例（1:1）
                        list($width, $height) = getimagesize($_FILES['selected_icon']['tmp_name']);
                        // 允许2%的误差
                        if (abs($width - $height) > $width * 0.02) {
                            $_SESSION['error_message'] = '图片尺寸比例必须是1:1';
                            header('Location: settings.php');
                            exit;
                        }
                        
                        $uploadDir = __DIR__ . '/../../xuaochengxu/images/';
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        $fileExtension = pathinfo($_FILES['selected_icon']['name'], PATHINFO_EXTENSION);
                        $fileName = $menuKey . '-active.' . $fileExtension;
                        $filePath = $uploadDir . $fileName;
                        if (move_uploaded_file($_FILES['selected_icon']['tmp_name'], $filePath)) {
                            $selectedIconPath = '/xuaochengxu/images/' . $fileName;
                        }
                    }
                    
                    // 更新数据库记录
                    if ($iconPath && $selectedIconPath) {
                        $stmt = $db->prepare('UPDATE tabbar_icons SET menu_text = ?, page_path = ?, icon_path = ?, selected_icon_path = ?, sort_order = ? WHERE id = ?');
                        $stmt->execute([$menuText, $pagePath, $iconPath, $selectedIconPath, $sortOrder, $id]);
                    } elseif ($iconPath) {
                        $stmt = $db->prepare('UPDATE tabbar_icons SET menu_text = ?, page_path = ?, icon_path = ?, sort_order = ? WHERE id = ?');
                        $stmt->execute([$menuText, $pagePath, $iconPath, $sortOrder, $id]);
                    } elseif ($selectedIconPath) {
                        $stmt = $db->prepare('UPDATE tabbar_icons SET menu_text = ?, page_path = ?, selected_icon_path = ?, sort_order = ? WHERE id = ?');
                        $stmt->execute([$menuText, $pagePath, $selectedIconPath, $sortOrder, $id]);
                    } else {
                        $stmt = $db->prepare('UPDATE tabbar_icons SET menu_text = ?, page_path = ?, sort_order = ? WHERE id = ?');
                        $stmt->execute([$menuText, $pagePath, $sortOrder, $id]);
                    }
                    
                    // 底部菜单操作完成，直接跳转，不处理其他设置
                    header('Location: settings.php');
                    exit;
            }
        }
        
        // 处理轮播图片操作
        if (isset($_POST['carousel_action']) && $db !== null) {
            switch ($_POST['carousel_action']) {
                case 'add':
                    // 处理轮播图片上传
                    if (isset($_FILES['carousel_image']) && $_FILES['carousel_image']['error'] === UPLOAD_ERR_OK) {
                        // 验证文件大小（1M以内）
                        if ($_FILES['carousel_image']['size'] > 1 * 1024 * 1024) {
                            $_SESSION['error_message'] = '图片大小不能超过1M';
                            header('Location: settings.php');
                            exit;
                        }
                        
                        // 验证文件类型
                        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                        $fileType = $_FILES['carousel_image']['type'];
                        if (!in_array($fileType, $allowedTypes)) {
                            $_SESSION['error_message'] = '图片格式必须是jpg、png或gif';
                            header('Location: settings.php');
                            exit;
                        }
                        
                        // 验证图片尺寸比例（16:9）
                        list($width, $height) = getimagesize($_FILES['carousel_image']['tmp_name']);
                        $aspectRatio = $width / $height;
                        $targetRatio = 16 / 9;
                        // 允许1%的误差
                        if (abs($aspectRatio - $targetRatio) > 0.01) {
                            $_SESSION['error_message'] = '图片尺寸比例必须是16:9';
                            header('Location: settings.php');
                            exit;
                        }
                        
                        $uploadDir = __DIR__ . '/../../uploads/carousel/';
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        $fileName = uniqid() . '_' . basename($_FILES['carousel_image']['name']);
                        $filePath = $uploadDir . $fileName;
                        if (move_uploaded_file($_FILES['carousel_image']['tmp_name'], $filePath)) {
                            $imageUrl = '/uploads/carousel/' . $fileName;
                            $title = $_POST['carousel_title'];
                            $link = $_POST['carousel_link'];
                            $sortOrder = $_POST['carousel_sort_order'];
                            $status = $_POST['carousel_status'];
                            
                            $stmt = $db->prepare('INSERT INTO carousel (image_url, title, link, sort_order, status) VALUES (?, ?, ?, ?, ?)');
                            $stmt->execute([$imageUrl, $title, $link, $sortOrder, $status]);
                        }
                    }
                    // 轮播图片操作完成，直接跳转，不处理其他设置
                    header('Location: settings.php');
                    exit;
                case 'update':
                    // 处理轮播图片更新
                    $id = $_POST['carousel_id'];
                    $title = $_POST['carousel_title'];
                    $link = $_POST['carousel_link'];
                    $sortOrder = $_POST['carousel_sort_order'];
                    $status = $_POST['carousel_status'];
                    
                    // 如果有新图片上传
                    if (isset($_FILES['carousel_image']) && $_FILES['carousel_image']['error'] === UPLOAD_ERR_OK) {
                        // 验证文件大小（1M以内）
                        if ($_FILES['carousel_image']['size'] > 1 * 1024 * 1024) {
                            $_SESSION['error_message'] = '图片大小不能超过1M';
                            header('Location: settings.php');
                            exit;
                        }
                        
                        // 验证文件类型
                        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                        $fileType = $_FILES['carousel_image']['type'];
                        if (!in_array($fileType, $allowedTypes)) {
                            $_SESSION['error_message'] = '图片格式必须是jpg、png或gif';
                            header('Location: settings.php');
                            exit;
                        }
                        
                        // 验证图片尺寸比例（16:9）- 放宽到5%误差
                        list($width, $height) = getimagesize($_FILES['carousel_image']['tmp_name']);
                        $aspectRatio = $width / $height;
                        $targetRatio = 16 / 9;
                        // 允许5%的误差
                        if (abs($aspectRatio - $targetRatio) > 0.05) {
                            $_SESSION['error_message'] = '图片尺寸比例必须是16:9（当前比例: ' . number_format($aspectRatio, 2) . '）';
                            header('Location: settings.php');
                            exit;
                        }
                        
                        $uploadDir = __DIR__ . '/../../uploads/carousel/';
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        $fileName = uniqid() . '_' . basename($_FILES['carousel_image']['name']);
                        $filePath = $uploadDir . $fileName;
                        
                        // 尝试上传图片
                        if (move_uploaded_file($_FILES['carousel_image']['tmp_name'], $filePath)) {
                            $imageUrl = '/uploads/carousel/' . $fileName;
                            
                            // 删除旧图片文件
                            $stmt = $db->prepare('SELECT image_url FROM carousel WHERE id = ?');
                            $stmt->execute([$id]);
                            $oldCarousel = $stmt->fetch();
                            if ($oldCarousel && $oldCarousel['image_url']) {
                                $oldFilePath = __DIR__ . '/../../' . $oldCarousel['image_url'];
                                if (file_exists($oldFilePath)) {
                                    unlink($oldFilePath);
                                }
                            }
                            
                            $stmt = $db->prepare('UPDATE carousel SET image_url = ?, title = ?, link = ?, sort_order = ?, status = ? WHERE id = ?');
                            $stmt->execute([$imageUrl, $title, $link, $sortOrder, $status, $id]);
                            
                            $_SESSION['success_message'] = '轮播图片更新成功';
                        } else {
                            $_SESSION['error_message'] = '图片上传失败，请检查服务器权限';
                        }
                    } else {
                        // 没有新图片，只更新其他信息
                        $stmt = $db->prepare('UPDATE carousel SET title = ?, link = ?, sort_order = ?, status = ? WHERE id = ?');
                        $stmt->execute([$title, $link, $sortOrder, $status, $id]);
                        
                        $_SESSION['success_message'] = '轮播信息更新成功';
                    }
                    // 轮播图片操作完成，直接跳转，不处理其他设置
                    header('Location: settings.php');
                    exit;
                case 'delete':
                    // 处理轮播图片删除
                    $id = $_POST['carousel_id'];
                    // 先获取图片路径，以便删除文件
                    $stmt = $db->prepare('SELECT image_url FROM carousel WHERE id = ?');
                    $stmt->execute([$id]);
                    $carousel = $stmt->fetch();
                    if ($carousel) {
                        $imagePath = __DIR__ . '/../../' . $carousel['image_url'];
                        if (file_exists($imagePath)) {
                            unlink($imagePath);
                        }
                        // 删除数据库记录
                        $stmt = $db->prepare('DELETE FROM carousel WHERE id = ?');
                        $stmt->execute([$id]);
                    }
                    // 轮播图片操作完成，直接跳转，不处理其他设置
                    header('Location: settings.php');
                    exit;
            }
        }
        
        // 处理分享封面图上传
        if (isset($_FILES['share_image']) && $_FILES['share_image']['error'] === UPLOAD_ERR_OK) {
            // 验证文件大小（1M以内）
            if ($_FILES['share_image']['size'] > 1 * 1024 * 1024) {
                $_SESSION['error_message'] = '图片大小不能超过1M';
                header('Location: settings.php');
                exit;
            }
            
            // 验证文件类型
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = $_FILES['share_image']['type'];
            if (!in_array($fileType, $allowedTypes)) {
                $_SESSION['error_message'] = '图片格式必须是jpg、png或gif';
                header('Location: settings.php');
                exit;
            }
            
            // 验证图片尺寸比例（1:1）
            list($width, $height) = getimagesize($_FILES['share_image']['tmp_name']);
            // 允许2%的误差
            if (abs($width - $height) > $width * 0.02) {
                $_SESSION['error_message'] = '图片尺寸比例必须是1:1';
                header('Location: settings.php');
                exit;
            }
            
            $uploadDir = __DIR__ . '/../../uploads/share/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = uniqid() . '_' . basename($_FILES['share_image']['name']);
            $filePath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['share_image']['tmp_name'], $filePath)) {
                $shareImageUrl = '/uploads/share/' . $fileName;
                $stmt = $db->prepare('INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?');
                $stmt->execute(['share_image', $shareImageUrl, $shareImageUrl]);
            }
        }
        
        // 处理基本设置
        // 先处理常规字段
        $changedFields = [];
        foreach ($_POST as $key => $value) {
            if (in_array($key, ['site_name', 'site_description', 'contact_phone', 'contact_email', 'address', 'copyright', 'share_title', 'share_description', 'primary_color', 'admin_domain'])) {
                // 检查是否有变更
                if (isset($settings[$key]) && $settings[$key] !== $value) {
                    $changedFields[$key] = [
                        'old' => $settings[$key],
                        'new' => $value
                    ];
                } elseif (!isset($settings[$key])) {
                    $changedFields[$key] = [
                        'old' => '',
                        'new' => $value
                    ];
                }
                
                $stmt = $db->prepare('INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?');
                $stmt->execute([$key, $value, $value]);
            }
        }
        
        // 如果修改了主色调，更新小程序的app.json文件
        if (isset($_POST['primary_color'])) {
            $primaryColor = $_POST['primary_color'];
            $appJsonPath = __DIR__ . '/../../miniprogram/app.json';
            if (file_exists($appJsonPath)) {
                $appJson = json_decode(file_get_contents($appJsonPath), true);
                if ($appJson) {
                    // 更新导航栏背景色
                    if (isset($appJson['window'])) {
                        $appJson['window']['navigationBarBackgroundColor'] = $primaryColor;
                    }
                    // 更新tabBar选中颜色
                    if (isset($appJson['tabBar'])) {
                        $appJson['tabBar']['selectedColor'] = $primaryColor;
                    }
                    // 写回文件
                    file_put_contents($appJsonPath, json_encode($appJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }
        }
        
        // 单独处理phone_enabled字段，确保即使未选中也会更新
        $phoneEnabled = isset($_POST['phone_enabled']) ? 1 : 0;
        $stmt = $db->prepare('INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?');
        $stmt->execute(['phone_enabled', $phoneEnabled, $phoneEnabled]);
        
        // 单独处理wechat_customer_service字段，确保即使未选中也会更新
        $wechatCustomerService = isset($_POST['wechat_customer_service']) ? 1 : 0;
        $stmt = $db->prepare('INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?');
        $stmt->execute(['wechat_customer_service', $wechatCustomerService, $wechatCustomerService]);
        
        // 单独处理login_required字段，授权登录控制
        $loginRequired = isset($_POST['login_required']) ? 1 : 0;
        $stmt = $db->prepare('INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?');
        $stmt->execute(['login_required', $loginRequired, $loginRequired]);
        
        // 单独处理patient_required字段，就诊人强制添加控制
        $patientRequired = isset($_POST['patient_required']) ? 1 : 0;
        $stmt = $db->prepare('INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?');
        $stmt->execute(['patient_required', $patientRequired, $patientRequired]);
        
        // 处理管理员密码修改
        if (isset($_POST['action']) && $_POST['action'] === 'update_admin') {
            $adminPassword = $_POST['admin_password'] ?? '';
            $adminPasswordConfirm = $_POST['admin_password_confirm'] ?? '';
            
            if (!empty($adminPassword)) {
                if ($adminPassword === $adminPasswordConfirm) {
                    // 获取当前密码（虽然是加密的，但我们可以在邮件中显示为星号）
                    $currentPassword = '******';
                    $stmt = $db->prepare('SELECT password FROM admin_users WHERE username = ?');
                    $stmt->execute(['admin']);
                    $admin = $stmt->fetch();
                    if ($admin) {
                        // 注意：这里存储的是加密后的密码，无法直接显示原始密码
                        // 但我们可以在邮件中显示为星号
                    }
                    
                    // 密码加密（使用md5，与登录验证保持一致）
                    $hashedPassword = md5($adminPassword);
                    // 更新管理员密码（使用admin_users表，与登录验证保持一致）
                    $stmt = $db->prepare('UPDATE admin_users SET password = ? WHERE username = ?');
                    $stmt->execute([$hashedPassword, 'admin']);
                    $_SESSION['success_message'] = '管理员密码修改成功';
                    
                    // 记录密码变更
                    $changedFields['admin_password'] = [
                        'old' => $currentPassword,
                        'new' => $adminPassword // 保存原始密码用于邮件发送
                    ];
                } else {
                    $_SESSION['error_message'] = '两次输入的密码不一致';
                }
            }
        }
        
        // 如果有变更，发送邮件通知
        if (!empty($changedFields)) {
            sendAdminChangeEmail($changedFields);
        }
        
        // 保存成功后刷新页面
        header('Location: settings.php');
        exit;
    }
}



// 包含头部模板
require_once __DIR__ . '/../includes/header.php';

// 页面特定样式
?>

                <!-- 错误消息显示 -->
                <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <?php echo $_SESSION['error_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <?php echo $_SESSION['success_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center bg-white border-bottom">
                        <h5 class="card-title mb-0">基本设置</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="settings.php" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="site_name" class="form-label">网站名称</label>
                                <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo isset($settings['site_name']) ? htmlspecialchars($settings['site_name']) : '沈阳附医北方医院'; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="site_description" class="form-label">网站描述</label>
                                <textarea class="form-control" id="site_description" name="site_description" rows="3"><?php echo isset($settings['site_description']) ? htmlspecialchars($settings['site_description']) : '沈阳附医北方医院是一家专业的妇科医院，提供优质的医疗服务。'; ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="contact_phone" class="form-label">联系电话</label>
                                <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="<?php echo isset($settings['contact_phone']) ? htmlspecialchars($settings['contact_phone']) : '024-12345678'; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="phone_enabled" class="form-label">拨打电话功能</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="phone_enabled" name="phone_enabled" <?php echo isset($settings['phone_enabled']) && $settings['phone_enabled'] == 1 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="phone_enabled">启用拨打电话功能</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="contact_email" class="form-label">联系邮箱</label>
                                <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?php echo isset($settings['contact_email']) ? htmlspecialchars($settings['contact_email']) : 'contact@example.com'; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="wechat_customer_service" class="form-label">微信客服功能</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="wechat_customer_service" name="wechat_customer_service" <?php echo isset($settings['wechat_customer_service']) && $settings['wechat_customer_service'] == 1 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="wechat_customer_service">启用微信客服功能</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">医院地址</label>
                                <input type="text" class="form-control" id="address" name="address" value="<?php echo isset($settings['address']) ? htmlspecialchars($settings['address']) : '沈阳市和平区某某路123号'; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="copyright" class="form-label">版权信息</label>
                                <input type="text" class="form-control" id="copyright" name="copyright" value="<?php echo isset($settings['copyright']) ? htmlspecialchars($settings['copyright']) : '© 2026 厦门元火妇科男科医院'; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="admin_domain" class="form-label">后台域名</label>
                                <input type="text" class="form-control" id="admin_domain" name="admin_domain" value="<?php echo isset($settings['admin_domain']) ? htmlspecialchars($settings['admin_domain']) : 'http://' . $_SERVER['HTTP_HOST'] . '/admin/'; ?>">
                                <small class="form-text text-muted">
                                    💡 请输入完整的您的域名（如：https://test.mmgcyy.com），不要带 /admin/
                                    <br>
                                    📱 本地开发：点击下方按钮可以自动同步到本地小程序开发目录
                                    <br>
                                    ☁️  云服务器：点击按钮会给出提示，请手动修改本地小程序 app.js
                                </small>
                                <button type="button" class="btn btn-success mt-2" onclick="syncBaseUrl()">
                                    🔄 立即同步到小程序前端
                                </button>
                            </div>
                            <div class="mb-3">
                                <label for="share_title" class="form-label">分享标题</label>
                                <input type="text" class="form-control" id="share_title" name="share_title" value="<?php echo isset($settings['share_title']) ? htmlspecialchars($settings['share_title']) : '厦门元火医疗自助挂号'; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="share_description" class="form-label">分享描述</label>
                                <textarea class="form-control" id="share_description" name="share_description" rows="3"><?php echo isset($settings['share_description']) ? htmlspecialchars($settings['share_description']) : '专业医疗服务，便捷预约挂号'; ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="share_image" class="form-label">分享封面图</label>
                                <input type="file" class="form-control" id="share_image" name="share_image">
                                <small class="form-text text-muted">要求：尺寸比例1:1，格式jpg、png、gif，大小不超过1M</small>
                                <?php if (isset($settings['share_image'])): ?>
                                <div class="mt-2">
                                    <img src="<?php echo $settings['share_image']; ?>" width="100" height="100" alt="分享封面图">
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label for="primary_color" class="form-label">小程序主色调</label>
                                <div class="d-flex align-items-center">
                                    <input type="color" class="form-control form-control-color w-25" id="primary_color" name="primary_color" value="<?php echo isset($settings['primary_color']) ? htmlspecialchars($settings['primary_color']) : '#007AFF'; ?>">
                                    <span class="ms-3" id="color-preview" style="width: 40px; height: 40px; border-radius: 50%; background-color: <?php echo isset($settings['primary_color']) ? htmlspecialchars($settings['primary_color']) : '#007AFF'; ?>"></span>
                                    <button type="button" class="btn btn-secondary ms-3" id="reset-color">重置默认色调</button>
                                </div>
                                <small class="form-text text-muted">选择小程序的主色调，将应用于导航栏和底部菜单选中状态</small>
                            </div>
                            <div class="mb-3">
                                <label for="login_required" class="form-label">授权登录控制</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="login_required" name="login_required" <?php echo isset($settings['login_required']) && $settings['login_required'] == 1 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="login_required">启用授权登录（关闭后用户可直接浏览小程序，无需授权）</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="patient_required" class="form-label">就诊人强制添加</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="patient_required" name="patient_required" <?php echo isset($settings['patient_required']) && $settings['patient_required'] == 1 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="patient_required">强制添加就诊人（关闭后用户可直接预约，无需添加就诊人）</label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">保存设置</button>
                        </form>
                    </div>
                </div>
                
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center bg-white border-bottom">
                        <h5 class="card-title mb-0">系统设置</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="settings.php">
                            <input type="hidden" name="action" value="update_admin">
                            <div class="mb-3">
                                <label for="adminUsername" class="form-label">管理员用户名</label>
                                <input type="text" class="form-control" id="adminUsername" name="admin_username" value="admin" disabled>
                            </div>
                            <div class="mb-3">
                                <label for="adminPassword" class="form-label">管理员密码</label>
                                <input type="password" class="form-control" id="adminPassword" name="admin_password" placeholder="请输入新密码">
                            </div>
                            <div class="mb-3">
                                <label for="adminPasswordConfirm" class="form-label">确认密码</label>
                                <input type="password" class="form-control" id="adminPasswordConfirm" name="admin_password_confirm" placeholder="请确认新密码">
                            </div>
                            <button type="submit" class="btn btn-primary">保存设置</button>
                        </form>
                    </div>
                </div>
                
                <!-- 底部菜单管理 -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center bg-white border-bottom">
                        <h5 class="card-title mb-0">底部菜单管理</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>菜单名称</th>
                                        <th>默认图标</th>
                                        <th>选中图标</th>
                                        <th>页面路径</th>
                                        <th>排序</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // 获取底部菜单列表
                                    if ($tabbarTableAvailable) {
                                        $stmt = $db->query('SELECT * FROM tabbar_icons ORDER BY sort_order ASC');
                                        while ($row = $stmt->fetch()) {
                                            echo '<tr>';
                                            echo '<td>' . $row['id'] . '</td>';
                                            echo '<td>' . htmlspecialchars($row['menu_text']) . '</td>';
                                            echo '<td><img src="' . ($row['icon_path'] ? '../../' . $row['icon_path'] : 'https://via.placeholder.com/40') . '" width="40" height="40" alt="默认图标"></td>';
                                            echo '<td><img src="' . ($row['selected_icon_path'] ? '../../' . $row['selected_icon_path'] : 'https://via.placeholder.com/40') . '" width="40" height="40" alt="选中图标"></td>';
                                            echo '<td>' . htmlspecialchars($row['page_path']) . '</td>';
                                            echo '<td>' . $row['sort_order'] . '</td>';
                                            echo '<td>';
                                            echo '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editTabbarModal" data-id="' . $row['id'] . '" data-menu-key="' . $row['menu_key'] . '" data-menu-text="' . htmlspecialchars($row['menu_text']) . '" data-icon-path="' . $row['icon_path'] . '" data-selected-icon-path="' . $row['selected_icon_path'] . '" data-page-path="' . $row['page_path'] . '" data-sort-order="' . $row['sort_order'] . '">编辑</button>';
                                            echo '</td>';
                                            echo '</tr>';
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- 轮播图片管理 -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center bg-white border-bottom">
                        <h5 class="card-title mb-0">轮播图片管理</h5>
                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addCarouselModal">
                            <i class="fa fa-plus me-1"></i> 添加轮播图片
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>图片</th>
                                        <th>标题</th>
                                        <th>链接</th>
                                        <th>排序</th>
                                        <th>状态</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // 获取轮播图片列表
                                    if ($carouselTableAvailable) {
                                        $stmt = $db->query('SELECT * FROM carousel ORDER BY sort_order ASC');
                                        while ($row = $stmt->fetch()) {
                                            echo '<tr>';
                                            echo '<td>' . $row['id'] . '</td>';
                                            echo '<td><img src="' . $row['image_url'] . '" width="100" height="50" alt="' . $row['title'] . '"></td>';
                                            echo '<td>' . htmlspecialchars($row['title']) . '</td>';
                                            echo '<td>' . htmlspecialchars($row['link']) . '</td>';
                                            echo '<td>' . $row['sort_order'] . '</td>';
                                            echo '<td>' . ($row['status'] === 'active' ? '<span class="badge bg-success">启用</span>' : '<span class="badge bg-secondary">禁用</span>') . '</td>';
                                            echo '<td>';
                                            echo '<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editCarouselModal" data-id="' . $row['id'] . '" data-title="' . htmlspecialchars($row['title']) . '" data-link="' . htmlspecialchars($row['link']) . '" data-sort-order="' . $row['sort_order'] . '" data-status="' . $row['status'] . '" data-image-url="' . $row['image_url'] . '">编辑</button>';
                                            echo '<button type="button" class="btn btn-danger btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#deleteCarouselModal" data-id="' . $row['id'] . '">删除</button>';
                                            echo '</td>';
                                            echo '</tr>';
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- 添加轮播图片模态框 -->
                <div class="modal fade" id="addCarouselModal" tabindex="-1" aria-labelledby="addCarouselModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addCarouselModalLabel">添加轮播图片</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="settings.php" enctype="multipart/form-data">
                                <div class="modal-body">
                                    <input type="hidden" name="carousel_action" value="add">
                                    <div class="mb-3">
                                        <label for="carousel_image" class="form-label">图片</label>
                                        <input type="file" class="form-control" id="carousel_image" name="carousel_image" required>
                                        <small class="form-text text-muted">要求：尺寸比例16:9，格式jpg、png、gif，大小不超过1M</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="carousel_title" class="form-label">标题</label>
                                        <input type="text" class="form-control" id="carousel_title" name="carousel_title" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="carousel_link" class="form-label">链接</label>
                                        <input type="text" class="form-control" id="carousel_link" name="carousel_link">
                                    </div>
                                    <div class="mb-3">
                                        <label for="carousel_sort_order" class="form-label">排序</label>
                                        <input type="number" class="form-control" id="carousel_sort_order" name="carousel_sort_order" value="0">
                                    </div>
                                    <div class="mb-3">
                                        <label for="carousel_status" class="form-label">状态</label>
                                        <select class="form-select" id="carousel_status" name="carousel_status">
                                            <option value="active">启用</option>
                                            <option value="inactive">禁用</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                    <button type="submit" class="btn btn-primary">保存</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- 编辑轮播图片模态框 -->
                <div class="modal fade" id="editCarouselModal" tabindex="-1" aria-labelledby="editCarouselModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editCarouselModalLabel">编辑轮播图片</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="settings.php" enctype="multipart/form-data">
                                <div class="modal-body">
                                    <input type="hidden" name="carousel_action" value="update">
                                    <input type="hidden" id="edit_carousel_id" name="carousel_id">
                                    <div class="mb-3">
                                        <label for="edit_carousel_image" class="form-label">图片</label>
                                        <input type="file" class="form-control" id="edit_carousel_image" name="carousel_image">
                                        <small class="form-text text-muted">要求：尺寸比例16:9，格式jpg、png、gif，大小不超过1M</small>
                                        <div id="current_image" class="mt-2"></div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_carousel_title" class="form-label">标题</label>
                                        <input type="text" class="form-control" id="edit_carousel_title" name="carousel_title" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_carousel_link" class="form-label">链接</label>
                                        <input type="text" class="form-control" id="edit_carousel_link" name="carousel_link">
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_carousel_sort_order" class="form-label">排序</label>
                                        <input type="number" class="form-control" id="edit_carousel_sort_order" name="carousel_sort_order">
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_carousel_status" class="form-label">状态</label>
                                        <select class="form-select" id="edit_carousel_status" name="carousel_status">
                                            <option value="active">启用</option>
                                            <option value="inactive">禁用</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                    <button type="submit" class="btn btn-primary">保存</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- 编辑底部菜单模态框 -->
                <div class="modal fade" id="editTabbarModal" tabindex="-1" aria-labelledby="editTabbarModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editTabbarModalLabel">编辑底部菜单</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="settings.php" enctype="multipart/form-data">
                                <div class="modal-body">
                                    <input type="hidden" name="tabbar_action" value="update">
                                    <input type="hidden" id="edit_tabbar_id" name="tabbar_id">
                                    <input type="hidden" id="edit_tabbar_menu_key" name="menu_key">
                                    
                                    <div class="mb-3">
                                        <label for="edit_tabbar_menu_text" class="form-label">菜单名称</label>
                                        <input type="text" class="form-control" id="edit_tabbar_menu_text" name="menu_text" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="edit_tabbar_page_path" class="form-label">页面路径</label>
                                        <input type="text" class="form-control" id="edit_tabbar_page_path" name="page_path" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="edit_tabbar_sort_order" class="form-label">排序</label>
                                        <input type="number" class="form-control" id="edit_tabbar_sort_order" name="sort_order" min="0">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="edit_tabbar_icon" class="form-label">默认图标</label>
                                        <div class="mb-2">
                                            <div id="edit_tabbar_icon_preview" style="width: 80px; height: 80px; border: 2px dashed #ccc; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 10px;">
                                                <span id="edit_tabbar_icon_placeholder" style="color: #999;">点击上传图标</span>
                                                <img id="edit_tabbar_icon_image" src="" alt="图标预览" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                                            </div>
                                            <input type="file" class="form-control" id="edit_tabbar_icon" name="icon" accept="image/jpeg,image/png,image/gif" style="display: none;">
                                            <p class="text-muted text-sm">支持JPG、PNG、GIF格式，尺寸1:1，大小不超过1M</p>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="edit_tabbar_selected_icon" class="form-label">选中图标</label>
                                        <div class="mb-2">
                                            <div id="edit_tabbar_selected_icon_preview" style="width: 80px; height: 80px; border: 2px dashed #ccc; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 10px;">
                                                <span id="edit_tabbar_selected_icon_placeholder" style="color: #999;">点击上传图标</span>
                                                <img id="edit_tabbar_selected_icon_image" src="" alt="图标预览" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                                            </div>
                                            <input type="file" class="form-control" id="edit_tabbar_selected_icon" name="selected_icon" accept="image/jpeg,image/png,image/gif" style="display: none;">
                                            <p class="text-muted text-sm">支持JPG、PNG、GIF格式，尺寸1:1，大小不超过1M</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                    <button type="submit" class="btn btn-primary">保存</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- 删除轮播图片模态框 -->
                <div class="modal fade" id="deleteCarouselModal" tabindex="-1" aria-labelledby="deleteCarouselModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteCarouselModalLabel">删除轮播图片</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="settings.php">
                                <div class="modal-body">
                                    <input type="hidden" name="carousel_action" value="delete">
                                    <input type="hidden" id="delete_carousel_id" name="carousel_id">
                                    <p>确定要删除此轮播图片吗？</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                    <button type="submit" class="btn btn-danger">删除</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
    
    <script>
        // 编辑轮播图片模态框
        const editCarouselModal = document.getElementById('editCarouselModal');
        if (editCarouselModal) {
            editCarouselModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const title = button.getAttribute('data-title');
                const link = button.getAttribute('data-link');
                const sortOrder = button.getAttribute('data-sort-order');
                const status = button.getAttribute('data-status');
                const imageUrl = button.getAttribute('data-image-url');
                
                document.getElementById('edit_carousel_id').value = id;
                document.getElementById('edit_carousel_title').value = title;
                document.getElementById('edit_carousel_link').value = link;
                document.getElementById('edit_carousel_sort_order').value = sortOrder;
                document.getElementById('edit_carousel_status').value = status;
                
                // 显示当前图片
                const currentImageDiv = document.getElementById('current_image');
                currentImageDiv.innerHTML = `<img src="${imageUrl}" width="100" height="50" alt="当前图片">`;
            });
        }
        
        // 删除轮播图片模态框
        const deleteCarouselModal = document.getElementById('deleteCarouselModal');
        if (deleteCarouselModal) {
            deleteCarouselModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                document.getElementById('delete_carousel_id').value = id;
            });
        }
        
        // 颜色选择器预览功能
        const primaryColorInput = document.getElementById('primary_color');
        const colorPreview = document.getElementById('color-preview');
        const resetColorBtn = document.getElementById('reset-color');
        if (primaryColorInput && colorPreview) {
            primaryColorInput.addEventListener('input', function() {
                colorPreview.style.backgroundColor = this.value;
            });
            
            // 重置默认色调功能
            if (resetColorBtn) {
                resetColorBtn.addEventListener('click', function() {
                    const defaultColor = '#007AFF';
                    primaryColorInput.value = defaultColor;
                    colorPreview.style.backgroundColor = defaultColor;
                });
            }
        }
        
        // 编辑底部菜单模态框
        const editTabbarModal = document.getElementById('editTabbarModal');
        if (editTabbarModal) {
            editTabbarModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const menuKey = button.getAttribute('data-menu-key');
                const menuText = button.getAttribute('data-menu-text');
                const iconPath = button.getAttribute('data-icon-path');
                const selectedIconPath = button.getAttribute('data-selected-icon-path');
                const pagePath = button.getAttribute('data-page-path');
                const sortOrder = button.getAttribute('data-sort-order');
                
                document.getElementById('edit_tabbar_id').value = id;
                document.getElementById('edit_tabbar_menu_key').value = menuKey;
                document.getElementById('edit_tabbar_menu_text').value = menuText;
                document.getElementById('edit_tabbar_page_path').value = pagePath;
                document.getElementById('edit_tabbar_sort_order').value = sortOrder;
                
                // 显示当前图标
                const iconPreview = document.getElementById('edit_tabbar_icon_preview');
                const iconPlaceholder = document.getElementById('edit_tabbar_icon_placeholder');
                const iconImage = document.getElementById('edit_tabbar_icon_image');
                
                if (iconPath) {
                    iconImage.src = '../../' + iconPath;
                    iconImage.style.display = 'block';
                    iconPlaceholder.style.display = 'none';
                } else {
                    iconImage.style.display = 'none';
                    iconPlaceholder.style.display = 'block';
                }
                
                // 显示当前选中图标
                const selectedIconPreview = document.getElementById('edit_tabbar_selected_icon_preview');
                const selectedIconPlaceholder = document.getElementById('edit_tabbar_selected_icon_placeholder');
                const selectedIconImage = document.getElementById('edit_tabbar_selected_icon_image');
                
                if (selectedIconPath) {
                    selectedIconImage.src = '../../' + selectedIconPath;
                    selectedIconImage.style.display = 'block';
                    selectedIconPlaceholder.style.display = 'none';
                } else {
                    selectedIconImage.style.display = 'none';
                    selectedIconPlaceholder.style.display = 'block';
                }
            });
        }
        
        // 图标上传预览
        document.addEventListener('DOMContentLoaded', function() {
            // 默认图标上传
            const iconPreview = document.getElementById('edit_tabbar_icon_preview');
            const iconInput = document.getElementById('edit_tabbar_icon');
            
            if (iconPreview && iconInput) {
                iconPreview.addEventListener('click', function() {
                    iconInput.click();
                });
                
                iconInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const iconImage = document.getElementById('edit_tabbar_icon_image');
                            const iconPlaceholder = document.getElementById('edit_tabbar_icon_placeholder');
                            iconImage.src = e.target.result;
                            iconImage.style.display = 'block';
                            iconPlaceholder.style.display = 'none';
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            // 选中图标上传
            const selectedIconPreview = document.getElementById('edit_tabbar_selected_icon_preview');
            const selectedIconInput = document.getElementById('edit_tabbar_selected_icon');
            
            if (selectedIconPreview && selectedIconInput) {
                selectedIconPreview.addEventListener('click', function() {
                    selectedIconInput.click();
                });
                
                selectedIconInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const selectedIconImage = document.getElementById('edit_tabbar_selected_icon_image');
                            const selectedIconPlaceholder = document.getElementById('edit_tabbar_selected_icon_placeholder');
                            selectedIconImage.src = e.target.result;
                            selectedIconImage.style.display = 'block';
                            selectedIconPlaceholder.style.display = 'none';
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
        });
        
        // 同步baseUrl到小程序前端
        function syncBaseUrl() {
            var baseUrl = document.getElementById('admin_domain').value;
            if (!baseUrl || baseUrl.trim() === '') {
                alert('请先输入后台域名');
                return;
            }
            
            if (confirm('确定要将 "' + baseUrl + '" 同步到小程序前端的baseUrl吗？')) {
                fetch('sync_baseurl.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'base_url=' + encodeURIComponent(baseUrl)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.code === 200) {
                        alert('✅ baseUrl同步成功！\n\n' + data.message);
                    } else {
                        alert('❌ 同步失败：\n\n' + data.message);
                    }
                })
                .catch(error => {
                    alert('❌ 同步出错：\n\n' + error);
                });
            }
        }
    </script>

<?php
// 包含底部模板
require_once __DIR__ . '/../includes/footer.php';
?>