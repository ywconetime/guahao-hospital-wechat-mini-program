<?php
// 管理后台头部模板
require_once __DIR__ . '/config.php';
checkAdminLogin();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo $activePage == 'index' ? 'assets/css/bootstrap.min.css' : '../assets/css/bootstrap.min.css'; ?>">
    <link rel="stylesheet" href="<?php echo $activePage == 'index' ? 'assets/css/font-awesome.min.css' : '../assets/css/font-awesome.min.css'; ?>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background-color: #f5f7fa;
            font-family: 'PingFang SC', 'Microsoft YaHei', sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: #333;
        }
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 220px;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            padding: 20px 0;
        }
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .sidebar-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            color: white;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        .sidebar-menu li {
            padding: 0 20px;
        }
        .sidebar-menu a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 12px 15px;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        .sidebar-menu a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.2);
            font-weight: 600;
        }
        .sidebar-menu i, .sidebar-menu svg.menu-icon {
            margin-right: 10px;
            width: 16px;
            height: 16px;
            text-align: center;
        }
        .main-content {
            margin-left: 220px;
            min-height: 100vh;
            background-color: #f5f7fa;
        }
        .top-bar {
            background-color: white;
            padding: 15px 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .top-bar h1 {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            color: #333;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-name {
            font-weight: 500;
            color: #333;
        }
        .logout-btn {
            color: #dc3545;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
            padding: 6px 12px;
            border-radius: 4px;
        }
        .logout-btn:hover {
            background-color: rgba(220, 53, 69, 0.1);
            color: #c82333;
        }
        .content-area {
            padding: 30px;
        }
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border: none;
            overflow: hidden;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
        }
        .card-title {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
            color: #333;
        }
        .card-body {
            padding: 20px;
        }
        .btn {
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 8px 16px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        .btn-secondary {
            background-color: #6c757d;
            border: none;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .btn-danger {
            background-color: #dc3545;
            border: none;
            color: white;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .btn-success {
            background-color: #28a745;
            border: none;
            color: white;
        }
        .btn-success:hover {
            background-color: #218838;
        }
        .form-control {
            border-radius: 6px;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 6px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #e9ecef;
        }
        .table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.02);
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.04);
        }
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        .pagination .page-item .page-link {
            border-radius: 6px;
            margin: 0 4px;
            padding: 6px 12px;
            font-size: 14px;
            color: #667eea;
            border: 1px solid #dee2e6;
        }
        .pagination .page-item.active .page-link {
            background-color: #667eea;
            border-color: #667eea;
            color: white;
        }
        .pagination .page-item .page-link:hover {
            color: #764ba2;
            border-color: #667eea;
        }
        .alert {
            border-radius: 6px;
            margin-bottom: 20px;
            padding: 12px 16px;
        }
        .modal-content {
            border-radius: 8px;
            overflow: hidden;
        }
        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 15px 20px;
        }
        .modal-title {
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }
        .modal-body {
            padding: 20px;
        }
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #e9ecef;
        }
        .badge {
            border-radius: 16px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-primary {
            background-color: #667eea;
        }
        .badge-success {
            background-color: #28a745;
        }
        .badge-danger {
            background-color: #dc3545;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #333;
        }
        .d-flex {
            display: flex;
        }
        .justify-content-between {
            justify-content: space-between;
        }
        .align-items-center {
            align-items: center;
        }
        .gap-2 {
            gap: 8px;
        }
        .gap-3 {
            gap: 12px;
        }
        .mb-3 {
            margin-bottom: 12px;
        }
        .mb-4 {
            margin-bottom: 16px;
        }
        .mt-4 {
            margin-top: 16px;
        }
        .text-center {
            text-align: center;
        }
        .text-muted {
            color: #6c757d;
        }
        .small {
            font-size: 12px;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 180px;
            }
            .main-content {
                margin-left: 180px;
            }
            .content-area {
                padding: 20px;
            }
        }
        @media (max-width: 576px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
                min-height: auto;
            }
            .main-content {
                margin-left: 0;
            }
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
                padding: 15px 20px;
            }
            .user-info {
                align-self: flex-end;
            }
        }
        
        /* 通知弹窗样式 */
        .notification-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.6);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            padding: 20px;
        }
        .notification-modal-overlay.show {
            display: flex;
        }
        .notification-modal {
            background-color: white;
            border-radius: 16px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: notificationModalShow 0.3s ease;
        }
        @keyframes notificationModalShow {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        .notification-modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notification-modal-title {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
        }
        .notification-modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            padding: 0;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s;
        }
        .notification-modal-close:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        .notification-modal-body {
            padding: 24px;
            max-height: 60vh;
            overflow-y: auto;
        }
        .notification-content {
            font-size: 15px;
            line-height: 1.8;
            color: #333;
            word-break: break-word;
        }
        .notification-content img {
            max-width: 100%;
            height: auto;
        }
        .notification-attachments {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        .attachments-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 12px;
        }
        .attachment-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .attachment-item:hover {
            background-color: #e9ecef;
        }
        .attachment-icon {
            font-size: 24px;
        }
        .attachment-name {
            font-size: 14px;
            color: #333;
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .notification-download {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        .download-button {
            display: inline-block;
            background: linear-gradient(135deg, #52c41a 0%, #73d13d 100%);
            color: white;
            padding: 12px 32px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 15px;
        }
        .download-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(82, 196, 26, 0.3);
        }
        .notification-modal-footer {
            padding: 20px 24px;
            border-top: 1px solid #e9ecef;
            text-align: center;
        }
        .notification-close-btn {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        .notification-close-btn:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h3 class="sidebar-title">管理后台</h3>
        </div>
        <ul class="sidebar-menu">
            <li><a href="<?php echo $activePage == 'index' ? 'index.php' : '../index.php'; ?>" class="<?php echo $activePage == 'index' ? 'active' : ''; ?>"><i class="fa fa-home"></i> 首页</a></li>
            <li><a href="<?php echo $activePage == 'index' ? 'pages/users.php' : 'users.php'; ?>" class="<?php echo $activePage == 'users' ? 'active' : ''; ?>"><i class="fa fa-users"></i> 用户管理</a></li>
            <li><a href="<?php echo $activePage == 'index' ? 'pages/doctors.php' : 'doctors.php'; ?>" class="<?php echo $activePage == 'doctors' ? 'active' : ''; ?>"><i class="fa fa-user-md"></i> 医生管理</a></li>
            <li><a href="<?php echo $activePage == 'index' ? 'pages/departments.php' : 'departments.php'; ?>" class="<?php echo $activePage == 'departments' ? 'active' : ''; ?>"><i class="fa fa-th-large"></i> 科室管理</a></li>
            <li><a href="<?php echo $activePage == 'index' ? 'pages/diseases.php' : 'diseases.php'; ?>" class="<?php echo $activePage == 'diseases' ? 'active' : ''; ?>"><i class="fa fa-medkit"></i> 病种管理</a></li>
            <li><a href="<?php echo $activePage == 'index' ? 'pages/appointments.php' : 'appointments.php'; ?>" class="<?php echo $activePage == 'appointments' ? 'active' : ''; ?>"><i class="fa fa-calendar"></i> 预约管理</a></li>
            <li><a href="<?php echo $activePage == 'index' ? 'pages/email_settings.php' : 'email_settings.php'; ?>" class="<?php echo $activePage == 'email_settings' ? 'active' : ''; ?>"><svg class="menu-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg> 邮箱设置</a></li>
            <li><a href="<?php echo $activePage == 'index' ? 'pages/settings.php' : 'settings.php'; ?>" class="<?php echo $activePage == 'settings' ? 'active' : ''; ?>"><i class="fa fa-cog"></i> 系统设置</a></li>
        </ul>
    </div>
    
    <!-- 主内容区 -->
    <div class="main-content">
        <div class="top-bar">
            <h1><?php echo $pageTitle; ?></h1>
            <div class="user-info">
                <span class="user-name">管理员</span>
                <?php
                // 动态生成退出链接的URL
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $adminPath = '/admin'; // 管理后台路径
                $logoutUrl = $protocol . '://' . $host . $adminPath . '/logout.php';
                ?>
                <a href="<?php echo $logoutUrl; ?>" class="logout-btn"><i class="fa fa-sign-out"></i> 退出</a>
            </div>
        </div>
        <div class="content-area">