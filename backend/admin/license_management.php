<?php
/**
 * 授权管理页面
 * 管理员可以查看和管理授权记录
 */

require_once __DIR__ . '/includes/config.php';

// 检查登录状态
checkAdminLogin();

// 获取授权列表
function getAuthorizationList() {
    $db = getAdminDB();
    if (!$db) return [];
    
    try {
        $stmt = $db->query("
            SELECT * FROM license_authorization 
            ORDER BY created_at DESC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('获取授权列表失败: ' . $e->getMessage());
        return [];
    }
}

// 删除授权记录
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $db = getAdminDB();
    if ($db) {
        try {
            $stmt = $db->prepare("DELETE FROM license_authorization WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            header('Location: license_management.php?deleted=1');
            exit;
        } catch (PDOException $e) {
            $error = '删除失败: ' . $e->getMessage();
        }
    }
}

// 更新授权状态
if (isset($_POST['action']) && $_POST['action'] == 'update') {
    $db = getAdminDB();
    if ($db) {
        try {
            $status = $_POST['status'] ?? 1;
            $expireTime = $_POST['expire_time'] ?? date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $stmt = $db->prepare("
                UPDATE license_authorization 
                SET status = ?, expire_time = ? 
                WHERE id = ?
            ");
            $stmt->execute([$status, $expireTime, $_POST['id']]);
            header('Location: license_management.php?updated=1');
            exit;
        } catch (PDOException $e) {
            $error = '更新失败: ' . $e->getMessage();
        }
    }
}

$authorizationList = getAuthorizationList();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>授权管理 - 管理后台</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fa; }
        
        /* 顶部导航 */
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 18px; font-weight: 500; }
        .header .user-info { display: flex; align-items: center; gap: 20px; }
        .header .logout { color: white; text-decoration: none; padding: 6px 16px; background: rgba(255,255,255,0.2); border-radius: 6px; }
        
        /* 主体内容 */
        .container { padding: 20px; }
        .card { background: white; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); padding: 20px; margin-bottom: 20px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
        .card-header h2 { font-size: 16px; color: #333; }
        
        /* 表格 */
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .table th { background: #f8f9fa; font-weight: 600; color: #666; font-size: 13px; }
        .table td { font-size: 13px; color: #333; }
        .table tr:hover { background: #f8f9fa; }
        
        /* 状态标签 */
        .status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .status.active { background: #d4edda; color: #155724; }
        .status.inactive { background: #f8d7da; color: #721c24; }
        .status.expired { background: #fff3cd; color: #856404; }
        
        /* 操作按钮 */
        .btn { padding: 6px 14px; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; transition: opacity 0.3s; }
        .btn-edit { background: #667eea; color: white; }
        .btn-delete { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.8; }
        
        /* 弹窗 */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal { background: white; border-radius: 12px; width: 90%; max-width: 500px; padding: 24px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { font-size: 16px; color: #333; }
        .modal-close { font-size: 24px; cursor: pointer; color: #999; }
        
        /* 表单 */
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 13px; color: #333; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #667eea; }
        .form-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        
        /* 消息提示 */
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="header">
        <h1>授权管理系统</h1>
        <div class="user-info">
            <span>欢迎, admin</span>
            <a href="/admin/logout.php" class="logout">退出登录</a>
        </div>
    </div>
    
    <div class="container">
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">更新成功</div>
        <?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">删除成功</div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2>授权列表</h2>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>姓名</th>
                        <th>手机号</th>
                        <th>微信号</th>
                        <th>IPv4地址</th>
                        <th>域名</th>
                        <th>状态</th>
                        <th>到期时间</th>
                        <th>创建时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($authorizationList as $item): ?>
                    <tr>
                        <td><?php echo $item['id']; ?></td>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo htmlspecialchars($item['phone']); ?></td>
                        <td><?php echo htmlspecialchars($item['wechat'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($item['ipv4']); ?></td>
                        <td><?php echo htmlspecialchars($item['domain']); ?></td>
                        <td>
                            <?php 
                            $statusClass = 'inactive';
                            $statusText = '未授权';
                            if ($item['status'] == 1) {
                                if (strtotime($