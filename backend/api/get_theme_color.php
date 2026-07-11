<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// 使用Database类连接数据库
try {
    require_once __DIR__ . '/../utils/Database.php';
    
    // 获取数据库连接
    $db = Database::getInstance()->getConn();

    // 查询主题颜色设置
    $sql = "SELECT `value` FROM `settings` WHERE `key_name` = 'primary_color'";
    $stmt = $db->query($sql);
    $row = $stmt->fetch();

    if ($row) {
        $themeColor = $row['value'];
    } else {
        // 默认颜色
        $themeColor = "#007AFF";
    }

    // 计算浅色和深色版本
    function hexToRgb($hex) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        return array($r, $g, $b);
    }

    function rgbToHex($r, $g, $b) {
        return "#" . str_pad(dechex($r), 2, "0", STR_PAD_LEFT) . str_pad(dechex($g), 2, "0", STR_PAD_LEFT) . str_pad(dechex($b), 2, "0", STR_PAD_LEFT);
    }

    function lightenColor($hex, $amount) {
        list($r, $g, $b) = hexToRgb($hex);
        $r = min(255, $r + $amount);
        $g = min(255, $g + $amount);
        $b = min(255, $b + $amount);
        return rgbToHex($r, $g, $b);
    }

    function darkenColor($hex, $amount) {
        list($r, $g, $b) = hexToRgb($hex);
        $r = max(0, $r - $amount);
        $g = max(0, $g - $amount);
        $b = max(0, $b - $amount);
        return rgbToHex($r, $g, $b);
    }

    $primaryLight = lightenColor($themeColor, 220);
    $primaryDark = darkenColor($themeColor, 50);

    // 返回主题颜色数据
    $response = array(
        "code" => 200,
        "message" => "获取主题颜色成功",
        "data" => array(
            "primaryColor" => $themeColor,
            "primaryLight" => $primaryLight,
            "primaryDark" => $primaryDark
        )
    );

    echo json_encode($response);
} catch (Exception $e) {
    // 返回错误信息
    $response = array(
        "code" => 500,
        "message" => $e->getMessage(),
        "data" => array(
            "primaryColor" => "#007AFF",
            "primaryLight" => "#e6f7ff",
            "primaryDark" => "#0056b3"
        )
    );

    echo json_encode($response);
}
?>