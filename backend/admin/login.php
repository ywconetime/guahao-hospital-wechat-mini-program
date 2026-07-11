<?php

// ============================================================================
// 保护机制错误显示函数（必须放在最前面）
// ============================================================================
if (!function_exists('_protection_show_error')) {
    function _protection_show_error($layer, $message) {
    if (php_sapi_name() === 'cli' || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => '系统保护已激活', 'code' => 'LAYER_' . $layer, 'message' => $message]);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>系统保护已激活</title><style>body{font-family:Microsoft YaHei;background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}.error-container{text-align:center;padding:60px;background:rgba(231,76,60,0.1);border-radius:30px;border:2px solid rgba(231,76,60,0.4);max-width:600px;box-shadow:0 20px 60px rgba(0,0,0,0.5);}.error-icon{font-size:80px;margin-bottom:20px;}.error-title{color:#e74c3c;font-size:28px;margin-bottom:15px;}.error-message{color:rgba(255,255,255,0.8);line-height:1.8;font-size:16px;}.layer-info{background:rgba(0,0,0,0.3);padding:10px 20px;border-radius:8px;margin-top:20px;font-family:monospace;font-size:14px;color:#f39c12;}</style></head><body><div class="error-container"><div class="error-icon">🛡️</div><h1 class="error-title">系统保护已激活</h1><p class="error-message">检测到配置文件异常，系统已停止运行以保护数据安全。</p><p class="error-message">请联系系统管理员进行修复。</p><div class="layer-info">[LAYER_' . $layer . '] ' . htmlspecialchars($message) . '</div></div></body></html>';
    }
    exit(1);
    }
}

// ============================================================================
// 10层保护机制
// ============================================================================

// 保护开关
$PROTECTION_ENABLED = true;
if (!$PROTECTION_ENABLED) goto _SKIP_PROTECTION;

// Layer 0: 检查保护开关状态文件 - 优先检查开发模式
$switchFile = dirname(__DIR__) . '/config/protection_switch.php';
$isDevMode = false;

// 直接读取文件内容检查开发模式标志
if (file_exists($switchFile)) {
    $content = file_get_contents($switchFile);
    if (strpos($content, '$PROTECTION_DISABLED = true;') !== false) {
        $isDevMode = true;
    }
}

// 开发模式下直接跳过所有保护检查
if ($isDevMode) {
    goto _SKIP_PROTECTION;
}

// Layer 1: 检查本文件完整性
$selfFile = __FILE__;
// 计算相对于根目录的相对路径，用于查找哈希文件
$rootDir = dirname(__DIR__);
$selfRelativePath = substr($selfFile, strlen($rootDir) + 1);
// ✅ 统一路径分隔符为 /，确保Windows和Linux一致！
$normalizedPath = str_replace('\\', '/', $selfRelativePath);
$selfHashFile = __DIR__ . '/../config/hashes/' . md5($normalizedPath) . '.hash';
if (file_exists($selfHashFile)) {
    $expectedHash = trim(file_get_contents($selfHashFile));
    $actualHash = hash_file('sha256', $selfFile);
    if ($actualHash !== $expectedHash) {
        _protection_show_error('001', '文件已被修改');
    }
}

// Layer 2-9: 检查所有关键文件
$rootDir = dirname(__DIR__);
$ds = DIRECTORY_SEPARATOR;
$protectedFiles = array(
    'api' . $ds . 'check_license.php',
    'api' . $ds . 'check_auth_status.php',
    'api' . $ds . 'create_appointment.php',
    'api' . $ds . 'send_auth_email.php',
    'api' . $ds . 'sync_wechat_to_auth.php',
    'admin' . $ds . 'check_license.php',
    'admin' . $ds . 'authorize.php',
    'admin' . $ds . 'login.php',
    'admin' . $ds . 'includes' . $ds . 'functions.php'
);

$layerNum = 2;
foreach ($protectedFiles as $file) {
    $filePath = $rootDir . $ds . $file;
    if (!file_exists($filePath)) {
        _protection_show_error(str_pad($layerNum, 3, '0', STR_PAD_LEFT), '关键文件缺失: ' . $file);
    }
    $layerNum++;
    if ($layerNum > 9) break;
}

_SKIP_PROTECTION:

// 所有检查通过


// 管理后台登录页面
// 设置浏览器不缓存
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// 先启动 session，以便授权检查中设置的延迟时间能正确保存
session_start();

// 授权检查
require_once __DIR__ . '/check_license.php';
AdminLicenseChecker::check();

// 获取授权ID
$authId = AdminLicenseChecker::getAuthId();

// 获取剩余延迟时间
$remainingDelay = AdminLicenseChecker::getRemainingDelay();

require_once __DIR__ . '/includes/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // 从数据库验证
    $db = getAdminDB();
    if ($db !== null) {
        $stmt = $db->prepare('SELECT * FROM admin_users WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if ($admin && md5($password) === $admin['password']) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['login_time'] = date('Y-m-d H:i:s');
            
            // 记录登录操作并发送邮件通知
            require_once __DIR__ . '/includes/functions.php';
            logAdminAction('管理员登录', [
                '管理员账号' => $admin['username'],
                '登录时间' => $_SESSION['login_time']
            ]);
            
            header('Location: index.php');
            exit;
        } else {
            $error = '用户名或密码错误';
        }
    } else {
        $error = '数据库连接失败';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台登录</title>
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="assets/css/font-awesome.min.css">
    <style>
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
        
        /* 二维码放大弹窗 */
        .qrcode-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            padding: 20px;
        }
        
        .qrcode-modal-overlay.show {
            display: flex;
        }
        
        .qrcode-modal {
            position: relative;
            max-width: 90%;
            max-height: 90%;
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }
        
        .qrcode-modal-close {
            position: absolute;
            top: -15px;
            right: -15px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ff4757;
            color: white;
            border: none;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            z-index: 1;
        }
        
        .qrcode-modal-close:hover {
            background: #ff6b81;
            transform: scale(1.1);
        }
        
        .qrcode-modal-img {
            max-width: 100%;
            max-height: 70vh;
            width: auto;
            height: auto;
            display: block;
            border-radius: 8px;
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
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <i class="fa fa-shield"></i>
            </div>
            <h2 class="login-title">管理后台</h2>
            <p class="login-subtitle">请登录以访问管理系统</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fa fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="loginForm">
            <input type="hidden" name="fingerprint" id="fingerprintField" value="">
            <div class="form-group">
                <label for="username" class="form-label">用户名</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="请输入用户名" required>
            </div>
            <div class="form-group">
                <label for="password" class="form-label">密码</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="请输入密码" required>
            </div>
            <button type="submit" class="login-btn">登录</button>
        </form>
        
        <div class="footer">
            版权© 2026医院预约挂号<br>
            医疗预约挂号微信小程序后台管理系统
        </div>
    </div>
    
    <!-- 漂浮微信群二维码 -->
    <div class="floating-qrcode" id="floatingQrcode" style="display: none;">
        <div class="qrcode-header">
            <span>📢 扫码加入微信群</span>
            <button class="qrcode-close" onclick="toggleQrcode()">×</button>
        </div>
        <div class="qrcode-content">
            <img id="qrcodeImage" src="" alt="微信群二维码" class="qrcode-img" onclick="enlargeQrcode()" style="cursor: zoom-in;">
            <p class="qrcode-tip">扫码加入微信群获取更多资讯</p>
            <p class="qrcode-expire" id="qrcodeExpire"></p>
        </div>
        <div class="qrcode-toggle" onclick="toggleQrcode()">
            <span>👆 点击展开</span>
        </div>
    </div>
    
    <!-- 二维码放大弹窗 -->
    <div class="qrcode-modal-overlay" id="qrcodeModalOverlay" onclick="closeQrcodeModal()">
        <div class="qrcode-modal" onclick="event.stopPropagation()">
            <button class="qrcode-modal-close" onclick="closeQrcodeModal()">×</button>
            <img id="enlargedQrcode" src="" alt="微信群二维码" class="qrcode-modal-img">
        </div>
    </div>
    
    <!-- 通知弹窗 -->
    <div class="notification-modal-overlay" id="notificationModal">
        <div class="notification-modal">
            <div class="notification-modal-header">
                <span class="notification-modal-title" id="notificationTitle"></span>
                <button class="notification-modal-close" onclick="closeNotificationModal()">&times;</button>
            </div>
            <div class="notification-modal-body">
                <div class="notification-content" id="notificationContent"></div>
                <div class="notification-attachments" id="notificationAttachments" style="display: none;">
                    <div class="attachments-title">📎 附件</div>
                    <div id="attachmentList"></div>
                </div>
                <div class="notification-download" id="notificationDownload" style="display: none;">
                    <button class="download-button" onclick="openDownloadUrl()">📥 立即下载</button>
                </div>
            </div>
            <div class="notification-modal-footer">
                <button class="notification-close-btn" onclick="closeNotificationModal()">我知道了</button>
            </div>
        </div>
    </div>
    
    
    
    <script>
        // ========== 立即执行：生成并保存指纹（必须在页面加载时立即执行） ==========
        (function() {
            console.log('%c[授权调试] ========== 页面加载开始 ==========', 'color: blue; font-weight: bold');
            
            // 检查是否已有指纹Cookie
            const existingFingerprint = getCookie('browser_fingerprint');
            console.log('[授权调试] 现有Cookie指纹:', existingFingerprint || '空');
            
            if (!existingFingerprint) {
                // 生成新指纹
                const fingerprint = generateQuickFingerprint();
                // 保存到Cookie（有效期365天）
                setCookie('browser_fingerprint', fingerprint, 365);
                console.log('[授权调试] 生成新指纹:', fingerprint);
                console.log('[授权调试] Cookie已设置');
            } else {
                console.log('[授权调试] 使用已有指纹');
            }
            
            // 验证Cookie是否正确设置
            const cookieAfterSet = getCookie('browser_fingerprint');
            console.log('[授权调试] 设置后的Cookie指纹:', cookieAfterSet || '空');
            
            // 显示所有Cookie
            console.log('[授权调试] 所有Cookie:', document.cookie);
            console.log('%c[授权调试] ========== 指纹设置完成 ==========', 'color: green; font-weight: bold');
            
            // ========== 发送指纹到授权系统 ==========
            sendFingerprintToServer();
        })();
        
        // 发送指纹到授权系统
        function sendFingerprintToServer() {
            const fingerprint = getCookie('browser_fingerprint');
            if (!fingerprint) {
                console.log('[授权调试] 指纹为空，跳过发送');
                return;
            }
            
            console.log('[授权调试] 发送指纹到授权系统:', fingerprint);
            
            fetch('/api/submit_fingerprint.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    fingerprint: fingerprint,
                    domain: window.location.hostname,
                    timestamp: Date.now()
                })
            }).then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('[授权调试] ✅ 指纹注册成功，首次访问时间:', data.first_access_time);
                    console.log('[授权调试] 数据来源:', data.source || 'unknown');
                    // 保存首次访问时间到Cookie
                    setCookie('auth_first_access_time', data.first_access_time, 365);
                } else {
                    console.log('[授权调试] ❌ 指纹注册失败:', data.message);
                }
            }).catch(error => {
                console.error('[授权调试] ❌ 发送指纹失败:', error);
            });
        }
        
        // 快速生成指纹（简化版，用于页面加载时快速生成）
        function generateQuickFingerprint() {
            const data = {
                screen: `${screen.width}x${screen.height}x${screen.colorDepth}`,
                dpr: window.devicePixelRatio || 1,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                language: navigator.language,
                platform: navigator.platform,
                userAgent: navigator.userAgent,
                hardwareConcurrency: navigator.hardwareConcurrency || 0,
                deviceMemory: navigator.deviceMemory || 0,
                touchPoints: navigator.maxTouchPoints || 0,
                cookieEnabled: navigator.cookieEnabled,
                onLine: navigator.onLine,
                vendor: navigator.vendor || ''
            };
            
            return hashCode(JSON.stringify(data));
        }
        
        function hashCode(str) {
            let hash = 0;
            for (let i = 0; i < str.length; i++) {
                const char = str.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash;
            }
            return Math.abs(hash).toString(16);
        }
        
        // 通知数据
        let notificationData = null;
        // 从 PHP 获取的授权ID
        const authId = <?php echo json_encode($authId); ?>;
        // 从 PHP 获取的剩余延迟时间
        const remainingDelay = <?php echo json_encode($remainingDelay); ?>;
        
        // 获取 API 基础路径（根据环境自动切换）
        function getApiBasePath() {
            const host = window.location.host;
            // 检查是否是本地/局域网环境
            const isLocal = host.includes('localhost') || 
                           host.includes('127.0.0.1') || 
                           host.startsWith('192.168.') || 
                           host.startsWith('10.');
            
            if (isLocal) {
                return 'http://localhost:88/license_system/api/';
            } else {
                return 'http://shouquan.mmgcyy.com/license_system/api/';
            }
        }
        
        // 获取 cookie
        function getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
            return '';
        }
        
        // 设置 cookie
        function setCookie(name, value, days = 365) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            const expires = `expires=${date.toUTCString()}`;
            document.cookie = `${name}=${value}; ${expires}; path=/`;
        }
        
        // 获取通知状态
        function getNotificationStatus(notificationId) {
            const cookieName = `notification_${notificationId}_status`;
            const cookieValue = getCookie(cookieName);
            if (cookieValue) {
                try {
                    return JSON.parse(cookieValue);
                } catch (e) {
                    return null;
                }
            }
            return null;
        }
        
        // 设置通知首次显示时间
        function setNotificationFirstShown(notificationId) {
            const cookieName = `notification_${notificationId}_status`;
            const status = getNotificationStatus(notificationId) || {};
            status.first_show_time = Math.floor(Date.now() / 1000);
            status.dismissed = false;
            setCookie(cookieName, JSON.stringify(status));
        }
        
        // 设置通知已关闭
        function setNotificationDismissed(notificationId) {
            const cookieName = `notification_${notificationId}_status`;
            const status = getNotificationStatus(notificationId) || {};
            status.last_show_time = Math.floor(Date.now() / 1000);
            status.dismiss_time = Math.floor(Date.now() / 1000);
            status.dismissed = true;
            setCookie(cookieName, JSON.stringify(status));
        }
        
        // 检查通知（先检查全局通知，再检查用户级别通知）
        function checkNotification() {
            const basePath = getApiBasePath();
            console.log('管理后台通知 - API 路径:', basePath + 'get_notification.php');
            
            // 先检查全局通知（优先级更高）
            checkGlobalNotificationAdmin(basePath);
        }
        
        // 检查管理后台全局通知
        function checkGlobalNotificationAdmin(basePath) {
            fetch(basePath + 'get_notification.php?source=admin')
                .then(response => response.json())
                .then(data => {
                    console.log('获取全局通知数据:', data);
                    // 只有当 show 为 true 时才显示全局通知
                    if (data.show && data.notification) {
                        const notification = data.notification;
                        showAdminNotification(notification, data.attachments || []);
                    } else {
                        console.log('全局通知已关闭或没有通知数据');
                        // 全局通知关闭，检查用户级别通知
                        checkUserSpecificNotificationAdmin(basePath);
                    }
                })
                .catch(error => {
                    console.error('获取全局通知失败:', error);
                    // 获取失败，检查用户级别通知
                    checkUserSpecificNotificationAdmin(basePath);
                });
        }
        
        // 检查管理后台用户级别通知
        function checkUserSpecificNotificationAdmin(basePath) {
            if (!authId || authId <= 0) {
                console.log('没有 auth_id，跳过用户级别通知检查');
                return;
            }
            
            console.log('检查用户级别通知，authId:', authId);
            
            fetch(basePath + 'get_user_notification.php?auth_id=' + authId + '&source=admin')
                .then(response => response.json())
                .then(data => {
                    console.log('获取用户级别通知数据:', data);
                    if (data.show && data.notification) {
                        showAdminNotification(data.notification, data.attachments || []);
                    } else {
                        console.log('用户级别通知未开启或没有数据');
                    }
                })
                .catch(error => {
                    console.error('获取用户级别通知失败:', error);
                });
        }
        
        // 显示管理后台通知
        function showAdminNotification(notification, attachments) {
            const hasAutoMode = notification.auto_mode || false;
            const firstDelay = notification.first_delay || 0;
            const intervalDelay = notification.interval_delay || 3600;
            const notificationId = notification.id;
            
            // 获取 cookie 中的状态
            const status = getNotificationStatus(notificationId);
            const now = Math.floor(Date.now() / 1000);
            
            console.log('通知数据:', notification);
            console.log('通知状态:', status);
            console.log('首次延迟:', firstDelay, '秒');
            
            // 检查时间控制
            let shouldShow = true;
            let delay = 0;
            
            if (hasAutoMode) {
                if (status) {
                    if (status.dismissed) {
                        // 已关闭，检查间隔时间
                        if (status.dismiss_time && (now - status.dismiss_time < intervalDelay)) {
                            shouldShow = false;
                            delay = intervalDelay - (now - status.dismiss_time);
                            console.log('管理后台通知：已关闭，等待间隔时间:', delay, '秒');
                        }
                    } else {
                        // 未关闭，检查首次延迟
                        if (status.first_show_time && (now - status.first_show_time < firstDelay)) {
                            shouldShow = false;
                            delay = firstDelay - (now - status.first_show_time);
                            console.log('管理后台通知：等待延迟时间:', delay, '秒');
                        }
                    }
                } else {
                    // 首次访问，检查首次延迟
                    if (firstDelay > 0) {
                        shouldShow = false;
                        delay = firstDelay;
                        console.log('管理后台通知：首次访问，等待延迟时间:', delay, '秒');
                    }
                }
            }
            
            if (shouldShow) {
                notificationData = notification;
                // 设置首次显示时间（用于时间控制）
                if (hasAutoMode && !status) {
                    setNotificationFirstShown(notificationId);
                }
                console.log('现在显示通知');
                showNotificationModal(notification, attachments);
                markNotificationShown(notificationId);
            } else if (delay > 0) {
                // 如果有延迟，先设置首次显示时间（如果没有设置过了就不再设置）
                if (hasAutoMode && !status) {
                    setNotificationFirstShown(notificationId);
                }
                // 延迟后直接显示通知，而不是再次检查
                console.log('设置延迟', delay, '秒后显示');
                setTimeout(() => {
                    notificationData = notification;
                    showNotificationModal(notification, attachments);
                    markNotificationShown(notificationId);
                }, delay * 1000);
            }
        }
        
        // 重置通知状态（用于调试）
        function resetNotificationStatus() {
            const cookies = document.cookie.split(';');
            cookies.forEach(cookie => {
                const name = cookie.trim().split('=')[0];
                if (name.startsWith('notification_')) {
                    document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
                }
            });
            console.log('通知状态已重置');
        }
        
        // 显示通知弹窗
        function showNotificationModal(notification, attachments) {
            document.getElementById('notificationTitle').textContent = notification.title;
            document.getElementById('notificationContent').innerHTML = notification.content;
            
            // 处理附件
            const attachmentSection = document.getElementById('notificationAttachments');
            const attachmentList = document.getElementById('attachmentList');
            if (attachments && attachments.length > 0) {
                attachmentSection.style.display = 'block';
                attachmentList.innerHTML = attachments.map(attachment => {
                    return `
                        <a href="${attachment.file_url}" class="attachment-item" target="_blank">
                            <span class="attachment-icon">📄</span>
                            <span class="attachment-name">${attachment.file_name}</span>
                        </a>
                    `;
                }).join('');
            } else {
                attachmentSection.style.display = 'none';
            }
            
            // 处理下载链接
            const downloadSection = document.getElementById('notificationDownload');
            if (notification.download_url) {
                downloadSection.style.display = 'block';
            } else {
                downloadSection.style.display = 'none';
            }
            
            document.getElementById('notificationModal').classList.add('show');
        }
        
        // 关闭通知弹窗
        function closeNotificationModal() {
            document.getElementById('notificationModal').classList.remove('show');
            if (notificationData) {
                dismissNotification(notificationData.id);
                // 设置 cookie 记录关闭状态
                if (notificationData.auto_mode) {
                    setNotificationDismissed(notificationData.id);
                }
            }
        }
        
        // 标记通知已显示
        function markNotificationShown(notificationId) {
            const basePath = getApiBasePath();
            fetch(basePath + 'mark_notification_shown.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ notification_id: notificationId })
            });
        }
        
        // 标记通知已关闭
        function dismissNotification(notificationId) {
            const basePath = getApiBasePath();
            fetch(basePath + 'close_notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ notification_id: notificationId })
            });
        }
        
        // 打开下载链接
        function openDownloadUrl() {
            if (notificationData && notificationData.download_url) {
                window.open(notificationData.download_url, '_blank');
            }
        }
        
        // 页面加载完成后执行初始化
        document.addEventListener('DOMContentLoaded', async function() {
            console.log('=== 授权延迟测试 ===');
            if (remainingDelay > 0) {
                console.log(`延迟时间剩余 ${remainingDelay} 秒，将自动跳转`);
            } else {
                console.log('无需延迟，直接加载');
            }
            
            // 检查通知
            setTimeout(checkNotification, 500);
            
            // 延迟时间到了之后自动跳转
            if (remainingDelay > 0 && !window.authDelayTimerSet) {
                window.authDelayTimerSet = true;
                setTimeout(function() {
                    console.log('延迟时间到，跳转到授权页');
                    window.location.href = window.location.pathname + '?force_auth=1';
                }, remainingDelay * 1000);
            }
            
            // 处理指纹 - 三重存储：Cookie + localStorage + 表单字段
            let fingerprint = getFingerprint();
            if (!fingerprint) {
                console.log('生成新指纹...');
                fingerprint = generateFingerprint();
                saveFingerprint(fingerprint);
            } else {
                console.log('使用已有指纹:', fingerprint);
            }
            
            // 将指纹设置到登录表单的隐藏字段
            const fingerprintField = document.getElementById('fingerprintField');
            if (fingerprintField) {
                fingerprintField.value = fingerprint;
                console.log('指纹已设置到表单');
            }
            
            // 表单提交前确保指纹已设置
            const loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    const fpField = document.getElementById('fingerprintField');
                    if (!fpField.value) {
                        let fp = getFingerprint();
                        if (!fp) {
                            fp = generateFingerprint();
                            saveFingerprint(fp);
                            console.log('表单提交时生成新指纹:', fp);
                        }
                        fpField.value = fp;
                    }
                });
            }
            
            // 同时向服务器注册指纹（确保服务器端有记录）
            await registerFingerprintToServer(fingerprint);
            
            // 加载微信群二维码
            loadGroupQrcode();
        });
        
        // 点击遮罩层关闭弹窗
        document.getElementById('notificationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeNotificationModal();
            }
        });
        
        // ==================== 浏览器指纹生成（30+项特征） ====================
        function generateFingerprint() {
            const fingerprintData = {
                // ========== 屏幕信息 ==========
                screen: `${screen.width}x${screen.height}x${screen.colorDepth}`,
                dpr: window.devicePixelRatio || 1,
                orientation: screen.orientation?.type || screen.orientation || 'unknown',
                availWidth: screen.availWidth || 0,
                availHeight: screen.availHeight || 0,
                
                // ========== 浏览器信息 ==========
                browser: getBrowserInfo(),
                userAgent: navigator.userAgent,
                vendor: navigator.vendor || '',
                product: navigator.product || '',
                appVersion: navigator.appVersion || '',
                appName: navigator.appName || '',
                appCodeName: navigator.appCodeName || '',
                
                // ========== 系统信息 ==========
                platform: navigator.platform,
                os: getOSInfo(),
                hardwareConcurrency: navigator.hardwareConcurrency || 0,
                deviceMemory: navigator.deviceMemory || 0,
                
                // ========== 语言与时区 ==========
                language: navigator.language,
                languages: navigator.languages?.join(',') || '',
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                timezoneOffset: -(new Date().getTimezoneOffset()),
                
                // ========== 设备能力 ==========
                touchPoints: navigator.maxTouchPoints || 0,
                isTouch: 'ontouchstart' in window,
                hardwareAcceleration: isHardwareAccelerated(),
                maxTouchPoints: navigator.maxTouchPoints || 0,
                
                // ========== Canvas指纹 ==========
                canvas: generateCanvasFingerprint(),
                
                // ========== WebGL指纹 ==========
                webgl: generateWebGLFingerprint(),
                webglExtensions: getWebGLExtensions(),
                
                // ========== 字体检测 ==========
                fonts: detectFonts(),
                
                // ========== Audio指纹 ==========
                audio: generateAudioFingerprint(),
                
                // ========== 插件信息 ==========
                plugins: getPluginInfo(),
                
                // ========== 存储能力 ==========
                storage: {
                    localStorage: typeof localStorage !== 'undefined',
                    sessionStorage: typeof sessionStorage !== 'undefined',
                    indexedDB: !!window.indexedDB,
                    webStorage: !!window.localStorage && !!window.sessionStorage
                },
                
                // ========== 网络信息 ==========
                connection: getConnectionInfo(),
                
                // ========== 其他特征 ==========
                doNotTrack: navigator.doNotTrack || '',
                cookieEnabled: navigator.cookieEnabled,
                onLine: navigator.onLine,
                webdriver: navigator.webdriver || false,
                hardware: getHardwareInfo()
            };
            
            return hashCode(JSON.stringify(fingerprintData));
        }
        
        function hashCode(str) {
            let hash = 0;
            for (let i = 0; i < str.length; i++) {
                const char = str.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash;
            }
            return Math.abs(hash).toString(16);
        }
        
        function generateCanvasFingerprint() {
            try {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                canvas.width = 200;
                canvas.height = 50;
                ctx.textBaseline = 'top';
                ctx.font = "14px 'Arial'";
                ctx.fillStyle = '#f60';
                ctx.fillRect(125, 1, 62, 20);
                ctx.fillStyle = '#069';
                ctx.fillText('Powered by FingerprintJS', 2, 15);
                ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
                ctx.fillText('FingerprintJS', 4, 17);
                return canvas.toDataURL().slice(-50);
            } catch (e) {
                return 'canvas_error';
            }
        }
        
        function generateWebGLFingerprint() {
            try {
                const canvas = document.createElement('canvas');
                const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
                if (!gl) return 'webgl_not_supported';
                
                const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                if (debugInfo) {
                    return gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) + '|' + 
                           gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL);
                }
                return gl.getParameter(gl.RENDERER) + '|' + gl.getParameter(gl.VENDOR);
            } catch (e) {
                return 'webgl_error';
            }
        }
        
        function detectFonts() {
            const baseFonts = ['monospace', 'sans-serif', 'serif'];
            const testString = 'mmmmmmmmmmlli';
            const testSize = '72px';
            
            const spans = [];
            const defaultWidths = {};
            
            baseFonts.forEach(font => {
                const span = document.createElement('span');
                span.style.position = 'absolute';
                span.style.left = '-9999px';
                span.style.fontSize = testSize;
                span.style.fontStyle = 'normal';
                span.style.fontWeight = 'normal';
                span.style.letterSpacing = 'normal';
                span.style.lineHeight = 'normal';
                span.style.textTransform = 'none';
                span.innerHTML = testString;
                span.style.fontFamily = font;
                document.body.appendChild(span);
                defaultWidths[font] = span.offsetWidth;
                spans.push(span);
            });
            
            const detectedFonts = [];
            const testFonts = ['Arial', 'Arial Black', 'Comic Sans MS', 'Courier New', 'Georgia', 
                              'Impact', 'Times New Roman', 'Trebuchet MS', 'Verdana', 'Microsoft YaHei',
                              'SimSun', 'SimHei', 'Microsoft JhengHei'];
            
            testFonts.forEach(font => {
                const width = defaultWidths['sans-serif'];
                spans.forEach(span => {
                    span.style.fontFamily = `'${font}', sans-serif`;
                    if (span.offsetWidth !== width) {
                        if (!detectedFonts.includes(font)) {
                            detectedFonts.push(font);
                        }
                    }
                });
            });
            
            spans.forEach(span => span.remove());
            return detectedFonts.join(',');
        }
        
        function generateAudioFingerprint() {
            try {
                const audioContext = window.AudioContext || window.webkitAudioContext;
                if (!audioContext) return 'audio_not_supported';
                
                const oscillator = audioContext.createOscillator();
                const analyser = audioContext.createAnalyser();
                const gainNode = audioContext.createGain();
                const scriptProcessor = audioContext.createScriptProcessor(4096, 1, 1);
                
                oscillator.type = 'triangle';
                oscillator.frequency.value = 10000;
                
                gainNode.gain.value = 0;
                oscillator.connect(analyser);
                analyser.connect(scriptProcessor);
                scriptProcessor.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator.start(0);
                
                const fingerprint = scriptProcessor.toString();
                oscillator.stop();
                
                return hashCode(fingerprint);
            } catch (e) {
                return 'audio_error';
            }
        }
        
        // 获取指纹（优先级：Cookie > localStorage）
        function getFingerprint() {
            // 1. 优先从Cookie获取
            const cookieValue = document.cookie
                .split('; ')
                .find(row => row.startsWith('browser_fingerprint='));
            if (cookieValue) {
                const fp = cookieValue.split('=')[1];
                if (fp && fp.length > 10) {
                    return fp;
                }
            }
            
            // 2. 从localStorage获取
            const localStorageValue = localStorage.getItem('browser_fingerprint');
            if (localStorageValue && localStorageValue.length > 10) {
                return localStorageValue;
            }
            
            return null;
        }
        
        // 保存指纹（同时保存到Cookie和localStorage）
        function saveFingerprint(fingerprint) {
            // 保存到Cookie（有效期365天）
            document.cookie = `browser_fingerprint=${fingerprint}; expires=${new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toUTCString()}; path=/; SameSite=Lax`;
            
            // 保存到localStorage
            localStorage.setItem('browser_fingerprint', fingerprint);
        }
        
        // 生成指纹并发送到服务器（旧函数，保持兼容）
        async function registerFingerprint() {
            const fingerprint = generateFingerprint();
            console.log('浏览器指纹:', fingerprint);
            saveFingerprint(fingerprint);
            
            try {
                const response = await fetch('http://shouquan.mmgcyy.com/license_system/api/fingerprint_api.php?action=register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        fingerprint: fingerprint,
                        domain: window.location.host
                    })
                });
                
                const result = await response.json();
                console.log('指纹注册结果:', result);
                
                if (result.success && result.data) {
                    localStorage.setItem('browser_fingerprint', fingerprint);
                    return result.data;
                }
            } catch (error) {
                console.error('指纹注册失败:', error);
            }
            
            return null;
        }
        
        // 向服务器注册指纹（带指纹参数版本）
        async function registerFingerprintToServer(fingerprint) {
            if (!fingerprint) {
                console.warn('没有指纹可注册');
                return;
            }
            
            try {
                const response = await fetch('http://shouquan.mmgcyy.com/license_system/api/fingerprint_api.php?action=register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        fingerprint: fingerprint,
                        domain: window.location.host
                    })
                });
                
                const result = await response.json();
                console.log('指纹注册到服务器结果:', result);
                
                if (result.success && result.data) {
                    return result.data;
                }
            } catch (error) {
                console.error('指纹注册到服务器失败:', error);
            }
            
            return null;
        }
        
        // ========== 指纹辅助函数（30+项特征） ==========
        
        // 获取浏览器信息
        function getBrowserInfo() {
            const ua = navigator.userAgent;
            let browser = 'unknown';
            let version = 'unknown';
            
            if (ua.includes('Edge')) {
                browser = 'Edge';
                version = ua.match(/Edge\/([\d.]+)/)?.[1] || 'unknown';
            } else if (ua.includes('Chrome') && !ua.includes('Edg')) {
                browser = 'Chrome';
                version = ua.match(/Chrome\/([\d.]+)/)?.[1] || 'unknown';
            } else if (ua.includes('Safari') && !ua.includes('Chrome')) {
                browser = 'Safari';
                version = ua.match(/Version\/([\d.]+)/)?.[1] || 'unknown';
            } else if (ua.includes('Firefox')) {
                browser = 'Firefox';
                version = ua.match(/Firefox\/([\d.]+)/)?.[1] || 'unknown';
            } else if (ua.includes('Opera') || ua.includes('OPR')) {
                browser = 'Opera';
                version = ua.match(/OPR\/([\d.]+)/)?.[1] || ua.match(/Opera\/([\d.]+)/)?.[1] || 'unknown';
            } else if (ua.includes('MSIE') || ua.includes('Trident')) {
                browser = 'IE';
                version = ua.match(/MSIE ([\d.]+)/)?.[1] || '11';
            }
            
            return { name: browser, version: version };
        }
        
        // 获取操作系统信息
        function getOSInfo() {
            const ua = navigator.userAgent;
            let os = 'unknown';
            let osVersion = 'unknown';
            
            if (ua.includes('Windows')) {
                os = 'Windows';
                if (ua.includes('Windows NT 10.0')) osVersion = '10';
                else if (ua.includes('Windows NT 6.3')) osVersion = '8.1';
                else if (ua.includes('Windows NT 6.2')) osVersion = '8';
                else if (ua.includes('Windows NT 6.1')) osVersion = '7';
                else if (ua.includes('Windows NT 6.0')) osVersion = 'Vista';
                else if (ua.includes('Windows NT 5.1')) osVersion = 'XP';
            } else if (ua.includes('Mac OS X')) {
                os = 'Mac OS X';
                osVersion = ua.match(/Mac OS X ([\d._]+)/)?.[1]?.replace(/_/g, '.') || 'unknown';
            } else if (ua.includes('Linux') && !ua.includes('Android')) {
                os = 'Linux';
            } else if (ua.includes('Android')) {
                os = 'Android';
                osVersion = ua.match(/Android ([\d.]+)/)?.[1] || 'unknown';
            } else if (ua.includes('iPhone') || ua.includes('iPad') || ua.includes('iPod')) {
                os = 'iOS';
                osVersion = ua.match(/OS ([\d_]+) like Mac/)?.[1]?.replace(/_/g, '.') || 'unknown';
            }
            
            return { name: os, version: osVersion };
        }
        
        // 检测硬件加速
        function isHardwareAccelerated() {
            try {
                const canvas = document.createElement('canvas');
                const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
                if (!gl) return false;
                
                const extension = gl.getExtension('WEBGL_lose_context');
                return extension !== null;
            } catch (e) {
                return false;
            }
        }
        
        // 获取WebGL扩展信息
        function getWebGLExtensions() {
            try {
                const canvas = document.createElement('canvas');
                const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
                if (!gl) return [];
                
                const extensions = gl.getSupportedExtensions();
                return extensions || [];
            } catch (e) {
                return [];
            }
        }
        
        // 获取插件信息
        function getPluginInfo() {
            const plugins = [];
            try {
                if (navigator.plugins && navigator.plugins.length > 0) {
                    for (let i = 0; i < Math.min(navigator.plugins.length, 10); i++) {
                        plugins.push({
                            name: navigator.plugins[i].name,
                            description: navigator.plugins[i].description || '',
                            filename: navigator.plugins[i].filename || ''
                        });
                    }
                }
            } catch (e) {
                // 某些浏览器可能禁止访问plugins
            }
            return plugins;
        }
        
        // 获取网络连接信息
        function getConnectionInfo() {
            const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            if (!connection) return null;
            
            return {
                effectiveType: connection.effectiveType || 'unknown',
                downlink: connection.downlink || 0,
                rtt: connection.rtt || 0,
                saveData: connection.saveData || false,
                type: connection.type || 'unknown'
            };
        }
        
        // 获取硬件信息
        function getHardwareInfo() {
            return {
                hardwareConcurrency: navigator.hardwareConcurrency || 0,
                deviceMemory: navigator.deviceMemory || 0,
                maxTouchPoints: navigator.maxTouchPoints || 0
            };
        }
        
        // 加载微信群二维码
        function loadGroupQrcode() {
            // 防止重复加载
            if (window.qrcodeLoaded) {
                console.log('二维码已加载，跳过');
                return;
            }
            window.qrcodeLoaded = true;
            
            const basePath = getApiBasePath();
            fetch(basePath + 'customer_service.php?action=get_group_qrcode')
                .then(response => response.json())
                .then(data => {
                    console.log('获取微信群二维码:', data);
                    if (data.success && data.data && data.data.qrcode_url && data.data.enabled !== false) {
                        displayQrcode(data.data);
                    } else if (data.data && data.data.enabled === false) {
                        console.log('微信群二维码功能已关闭');
                    } else {
                        console.log('暂无可用的微信群二维码');
                    }
                })
                .catch(error => {
                    console.error('获取微信群二维码失败:', error);
                });
        }
        
        // 显示二维码
        function displayQrcode(qrcodeData) {
            const container = document.getElementById('floatingQrcode');
            const img = document.getElementById('qrcodeImage');
            const expireEl = document.getElementById('qrcodeExpire');
            
            img.src = qrcodeData.qrcode_url;
            img.onerror = function() {
                this.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 150 150"><rect fill="%23f0f0f0" width="150" height="150"/><text x="75" y="75" text-anchor="middle" dy=".3em" font-size="14" fill="%23999">二维码加载失败</text></svg>';
            };
            
            if (qrcodeData.expire_time) {
                const expireDate = new Date(qrcodeData.expire_time);
                const now = new Date();
                const diff = expireDate - now;
                
                if (diff > 0) {
                    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    expireEl.textContent = `有效期剩余: ${days}天${hours}小时`;
                } else {
                    expireEl.textContent = '⚠️ 二维码已过期';
                }
            } else {
                expireEl.style.display = 'none';
            }
            
            container.style.display = 'block';
            container.classList.add('minimized');
        }
        
        // 切换二维码显示/隐藏
        function toggleQrcode() {
            const container = document.getElementById('floatingQrcode');
            if (container.classList.contains('minimized')) {
                container.classList.remove('minimized');
            } else {
                container.classList.add('minimized');
            }
        }
        
        // 放大二维码
        function enlargeQrcode() {
            const img = document.getElementById('qrcodeImage');
            const modalImg = document.getElementById('enlargedQrcode');
            const modal = document.getElementById('qrcodeModalOverlay');
            
            if (img.src) {
                modalImg.src = img.src;
                modal.classList.add('show');
            }
        }
        
        // 关闭二维码放大弹窗
        function closeQrcodeModal() {
            const modal = document.getElementById('qrcodeModalOverlay');
            modal.classList.remove('show');
        }
        
        // ESC键关闭弹窗
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeQrcodeModal();
            }
        });
        
    </script>
</body>
</html>