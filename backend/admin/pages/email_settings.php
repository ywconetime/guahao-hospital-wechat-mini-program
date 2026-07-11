<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = '邮箱设置';
$activePage = 'email_settings';

$db = getAdminDB();

// 默认值
$authEmail = '';
$authAppid = '';
$authAppsecret = '';
$businessDomain = '';
$useBusinessDomain = 0;

// 从数据库读取配置（key-value 方式）
if ($db) {
    try {
        $stmt = $db->query("SELECT key_name, value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key_name']] = $row['value'];
        }
        
        $authEmail = $settings['auth_email'] ?? '';
        $authAppid = $settings['auth_appid'] ?? '';
        $authAppsecret = $settings['auth_appsecret'] ?? '';
        $businessDomain = $settings['business_domain'] ?? '';
        $useBusinessDomain = $settings['use_business_domain'] ?? 0;
    } catch (Exception $e) {
        // 忽略错误
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 保存邮箱设置
    if (isset($_POST['auth_email'])) {
        $email = trim($_POST['auth_email']);
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error_message'] = '请输入有效的邮箱地址';
        } else {
            if ($db) {
                // 使用 key-value 方式保存
                $stmt = $db->prepare("INSERT INTO settings (key_name, value) VALUES ('auth_email', ?) ON DUPLICATE KEY UPDATE value = ?");
                $stmt->execute([$email, $email]);
            }
            $_SESSION['success_message'] = '邮箱设置保存成功！';
            $authEmail = $email;
        }
    }
    
    // 保存小程序配置
    if (isset($_POST['appid']) || isset($_POST['appsecret'])) {
        $appid = trim($_POST['appid'] ?? '');
        $appsecret = trim($_POST['appsecret'] ?? '');
        
        $savedToDB = false;
        $syncedToFile = false;
        $errorMsg = '';
        
        if ($db) {
            // 1. 使用 key-value 方式保存到数据库
            try {
                $stmt = $db->prepare("INSERT INTO settings (key_name, value) VALUES ('auth_appid', ?) ON DUPLICATE KEY UPDATE value = ?");
                $stmt->execute([$appid, $appid]);
                
                $stmt = $db->prepare("INSERT INTO settings (key_name, value) VALUES ('auth_appsecret', ?) ON DUPLICATE KEY UPDATE value = ?");
                $stmt->execute([$appsecret, $appsecret]);
                $savedToDB = true;
            } catch (Exception $e) {
                $errorMsg = '数据库保存失败: ' . $e->getMessage();
            }
            
            // 2. 尝试同步到小程序前端配置文件 project.config.json
            $searchPaths = [
                __DIR__ . '/../../xuaochengxu/project.config.json',
                __DIR__ . '/../../xiaochengxu/project.config.json',
                dirname(dirname(__DIR__)) . '/xuaochengxu/project.config.json',
                dirname(dirname(__DIR__)) . '/xiaochengxu/project.config.json',
            ];
            
            $projectConfigPath = '';
            foreach ($searchPaths as $path) {
                if (file_exists($path)) {
                    $projectConfigPath = $path;
                    break;
                }
            }
            
            if ($projectConfigPath) {
                try {
                    $content = file_get_contents($projectConfigPath);
                    if ($content !== false) {
                        $config = json_decode($content, true);
                        if ($config && is_array($config)) {
                            $config['appid'] = $appid;
                            $jsonOutput = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                            $writeResult = file_put_contents($projectConfigPath, $jsonOutput);
                            if ($writeResult !== false) {
                                $syncedToFile = true;
                            }
                        }
                    }
                } catch (Exception $e) {
                    $errorMsg .= ' | 文件同步失败: ' . $e->getMessage();
                }
            }
        }
        
        // 3. 设置提示消息
        if ($savedToDB && $syncedToFile) {
            $_SESSION['success_message'] = '✅ 小程序配置保存成功！已同步到前端配置文件 (project.config.json)。';
        } elseif ($savedToDB) {
            $_SESSION['success_message'] = '✅ 小程序配置保存成功！小程序启动时会通过API自动获取最新AppID。';
        } else {
            $_SESSION['error_message'] = '❌ 配置保存失败：' . $errorMsg;
        }
    }
    
    // 保存业务域名配置
    if (isset($_POST['business_domain']) || isset($_POST['use_business_domain'])) {
        $business_domain = trim($_POST['business_domain'] ?? '');
        $use_business_domain = isset($_POST['use_business_domain']) ? 1 : 0;
        
        if ($db) {
            // 使用 key-value 方式保存
            $stmt = $db->prepare("INSERT INTO settings (key_name, value) VALUES ('business_domain', ?) ON DUPLICATE KEY UPDATE value = ?");
            $stmt->execute([$business_domain, $business_domain]);
            
            $stmt = $db->prepare("INSERT INTO settings (key_name, value) VALUES ('use_business_domain', ?) ON DUPLICATE KEY UPDATE value = ?");
            $stmt->execute([$use_business_domain, $use_business_domain]);
        }
        
        if ($use_business_domain) {
            $_SESSION['success_message'] = '业务域名配置保存成功！';
        } else {
            $_SESSION['success_message'] = '业务域名配置保存成功！';
        }
    }
    
    header('Location: email_settings.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

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
                    <div class="card-body">
                        <form method="POST" action="email_settings.php">
                            <div class="mb-3">
                                <label for="auth_email" class="form-label">接收预约提醒的邮箱</label>
                                <input type="email" class="form-control" id="auth_email" name="auth_email" 
                                       value="<?php echo htmlspecialchars($authEmail); ?>"
                                       placeholder="请输入您的邮箱地址">
                                <small class="form-text text-muted">
                                    设置后，您小程序的患者提交预约挂号信息时，系统会自动发送邮件通知到此邮箱。
                                </small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">保存设置</button>
                            <?php if (!empty($authEmail)): ?>
                            <button type="button" class="btn btn-secondary ms-2" onclick="clearEmail()">清空邮箱</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="POST" action="email_settings.php">
                            <h6 class="mb-3">小程序配置</h6>
                            <div class="alert alert-info alert-sm">
                                <strong>💡 提示：</strong>保存此配置后，AppID将自动同步到小程序前端配置文件 <code>xuaochengxu/project.config.json</code>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="appid" class="form-label">AppID(小程序ID)</label>
                                        <input type="text" class="form-control" id="appid" name="appid" 
                                               value="<?php echo htmlspecialchars($authAppid); ?>"
                                               placeholder="请输入微信小程序AppID">
                                        <small class="form-text text-muted">
                                            在微信公众平台获取小程序的AppID
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="appsecret" class="form-label">AppSecret(小程序密钥)</label>
                                        <input type="password" class="form-control" id="appsecret" name="appsecret" 
                                               value="<?php echo htmlspecialchars($authAppsecret); ?>"
                                               placeholder="请输入微信小程序AppSecret">
                                        <small class="form-text text-muted">
                                            在微信公众平台获取小程序的AppSecret
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">保存小程序配置</button>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <form method="POST" action="email_settings.php">
                            <h6 class="mb-3">业务域名配置</h6>
                            <div class="mb-3">
                                <label for="business_domain" class="form-label">业务域名</label>
                                <input type="text" class="form-control" id="business_domain" name="business_domain" 
                                       value="<?php echo htmlspecialchars($businessDomain); ?>"
                                       placeholder="请输入业务域名（如：https://www.example.com）">
                                <small class="form-text text-muted">
                                    需在微信公众平台验证通过的业务域名。
                                </small>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="use_business_domain" name="use_business_domain" 
                                           value="1" <?php echo $useBusinessDomain ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="use_business_domain">
                                        启用业务域名切换
                                    </label>
                                </div>
                                <small class="form-text text-muted">
                                    开启后，小程序首页将直接跳转到业务域名网站，隐藏原小程序内容。
                                </small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">保存业务域名配置</button>
                        </form>
                    </div>
                </div>

                <script>
                    function clearEmail() {
                        if (confirm('确定要清空邮箱设置吗？清空后将不再接收预约邮件提醒。')) {
                            document.getElementById('auth_email').value = '';
                            document.querySelector('form').submit();
                        }
                    }
                </script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
