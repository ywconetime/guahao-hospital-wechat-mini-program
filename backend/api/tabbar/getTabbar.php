<?php
// 获取底部菜单配置
header('Access-Control-Allow-Origin: *'); // 允许跨域访问

// 直接加载数据库配置
require_once __DIR__ . '/../../config/config.php';

// 数据库连接
$db = null;
try {
    $db = new PDO(
        "mysql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['database']};charset={$config['db']['charset']}",
        $config['db']['username'],
        $config['db']['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    error_log('数据库连接失败: ' . $e->getMessage());
}
$tabbarConfig = array(
    'color' => '#999',
    'selectedColor' => '#007AFF',
    'backgroundColor' => '#fff',
    'borderStyle' => 'black',
    'list' => array()
);

if ($db !== null) {
    $stmt = $db->query('SELECT * FROM tabbar_icons ORDER BY sort_order ASC');
    while ($row = $stmt->fetch()) {
        $iconPath = $row['icon_path'];
        $selectedIconPath = $row['selected_icon_path'];
        
        if ($iconPath && strpos($iconPath, 'miniprogram/') !== false) {
            $iconPath = str_replace('miniprogram/', '', $iconPath);
        } elseif ($iconPath && strpos($iconPath, 'xuaochengxu/') !== false) {
            $iconPath = str_replace('xuaochengxu/', '', $iconPath);
        } elseif (!$iconPath) {
            $iconPath = 'images/home.png';
        }
        
        if ($selectedIconPath && strpos($selectedIconPath, 'miniprogram/') !== false) {
            $selectedIconPath = str_replace('miniprogram/', '', $selectedIconPath);
        } elseif ($selectedIconPath && strpos($selectedIconPath, 'xuaochengxu/') !== false) {
            $selectedIconPath = str_replace('xuaochengxu/', '', $selectedIconPath);
        } elseif (!$selectedIconPath) {
            $selectedIconPath = 'images/home-active.png';
        }
        
        $item = array(
            'pagePath' => $row['page_path'],
            'text' => $row['menu_text'],
            'iconPath' => '/' . $iconPath,
            'selectedIconPath' => '/' . $selectedIconPath
        );
        $tabbarConfig['list'][] = $item;
    }
}

header('Content-Type: application/json');
echo json_encode($tabbarConfig);
?>