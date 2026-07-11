<?php
// 用户管理后端处理脚本
require_once __DIR__ . '/../includes/config.php';
checkAdminLogin();

$db = getAdminDB();
$action = $_POST['action'] ?? 'edit';

/**
 * 自动同步用户到授权系统（直接调用授权系统API）
 */
function syncToAuthSystem($userId) {
    global $db;
    
    try {
        // 获取用户信息
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => '用户不存在'];
        }
        
        // 从配置获取授权ID和小程序名称
        $authId = getConfig('auth_id', 0);
        $appName = getConfig('app_name', '医院预约挂号');
        
        // 如果配置中没有，则尝试从授权系统获取
        if (!$authId) {
            $authId = 0;
            $authCheckUrl = 'http://shouquan.mmgcyy.com/license_system/api/get_auth_id.php?domain=' 
                          . urlencode($_SERVER['HTTP_HOST'] ?? 'test.mmgcyy.com') 
                          . '&ip=' . urlencode($_SERVER['REMOTE_ADDR'] ?? '');
            $ch = curl_init($authCheckUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $authCheckResponse = curl_exec($ch);
            curl_close($ch);
            
            if ($authCheckResponse) {
                $authCheckResult = json_decode($authCheckResponse, true);
                if (is_array($authCheckResult) && isset($authCheckResult['success']) && $authCheckResult['success'] && isset($authCheckResult['data']['auth_id'])) {
                    $authId = intval($authCheckResult['data']['auth_id']);
                    $appName = $authCheckResult['data']['app_name'] ?? $appName;
                }
            }
        }
        
        if (!$authId) {
            return ['success' => false, 'message' => '未配置授权ID，请先在系统设置中配置'];
        }
        
        // 构建同步数据（直接调用授权系统的 sync_wechat_user.php）
        $syncData = [
            'auth_id' => $authId,
            'app_name' => $appName,
            'openid' => 'admin_' . $user['id'] . '_' . ($user['openid'] ?? 'user_' . $user['id']),
            'nickname' => $user['nickname'] ?? '微信用户',
            'avatar_url' => $user['avatar'] ?? '',
            'phone' => $user['phone'] ?? '',
            'real_name' => $user['real_name'] ?? '',
            'gender' => 0,
            'province' => '',
            'city' => ''
        ];
        
        // 发送同步请求到授权系统
        $syncUrl = 'http://shouquan.mmgcyy.com/license_system/api/sync_wechat_user.php';
        $ch = curl_init($syncUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($syncData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['code']) && $result['code'] === 200) {
                return ['success' => true, 'message' => '同步成功'];
            }
        }
        
        return ['success' => false, 'message' => '同步失败: ' . substr($response, 0, 200)];
    } catch (Exception $e) {
        return ['success' => false, 'message' => '同步异常: ' . $e->getMessage()];
    }
}

if ($action === 'delete') {
    // 删除用户
    $userId = $_POST['id'] ?? 0;
    
    if (empty($userId)) {
        echo json_encode(['code' => 400, 'message' => '用户ID不能为空', 'data' => null]);
        exit;
    }
    
    try {
        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $result = $stmt->execute([$userId]);
        
        if ($result) {
            echo json_encode(['code' => 200, 'message' => '删除成功', 'data' => null]);
        } else {
            echo json_encode(['code' => 500, 'message' => '删除失败', 'data' => null]);
        }
    } catch (Exception $e) {
        echo json_encode(['code' => 500, 'message' => '删除失败: ' . $e->getMessage(), 'data' => null]);
    }
} else {
    // 编辑用户
    $userId = $_POST['id'] ?? 0;
    $nickname = $_POST['nickname'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $realName = $_POST['real_name'] ?? '';
    $idCard = $_POST['id_card'] ?? '';
    $avatar = $_POST['avatar'] ?? '';
    
    if (empty($userId)) {
        echo json_encode(['code' => 400, 'message' => '用户ID不能为空', 'data' => null]);
        exit;
    }
    
    if (empty($nickname)) {
        echo json_encode(['code' => 400, 'message' => '昵称不能为空', 'data' => null]);
        exit;
    }
    
    try {
        $updateData = [
            'nickname' => $nickname,
            'phone' => $phone,
            'real_name' => $realName,
            'id_card' => $idCard,
            'avatar' => $avatar
        ];
        
        $fields = [];
        $params = [];
        
        foreach ($updateData as $key => $value) {
            $fields[] = "$key = ?";
            $params[] = $value;
        }
        
        $params[] = $userId;
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $db->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            // 自动同步到授权系统（异步执行，不影响主流程）
            syncToAuthSystem($userId);
            
            echo json_encode(['code' => 200, 'message' => '保存成功', 'data' => null]);
        } else {
            echo json_encode(['code' => 500, 'message' => '保存失败', 'data' => null]);
        }
    } catch (Exception $e) {
        echo json_encode(['code' => 500, 'message' => '保存失败: ' . $e->getMessage(), 'data' => null]);
    }
}
?>