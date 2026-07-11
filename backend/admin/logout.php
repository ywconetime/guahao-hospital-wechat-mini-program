<?php
// 管理后台退出页面
session_start();

// 记录退出时间
$logoutTime = date('Y-m-d H:i:s');
$loginTime = $_SESSION['login_time'] ?? '未知';
$adminUsername = $_SESSION['admin_username'] ?? '未知';

// 销毁会话
session_destroy();

// 获取当前服务器配置
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

// 跳转到登录页面（使用绝对路径）
$loginUrl = $protocol . '://' . $host . $basePath . '/login.php';
header('Location: ' . $loginUrl);
exit;
?>