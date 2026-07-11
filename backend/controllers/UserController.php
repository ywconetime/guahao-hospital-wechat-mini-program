<?php
require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/User.php';

class UserController extends ApiController {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new User();
    }
    
    // 用户登录/注册
    public function login() {
        try {
            $params = $this->getParams();
            $code = $params['code'] ?? '';
            $userInfo = $params['userInfo'] ?? null;
            $phone = $params['phone'] ?? '';
            
            if (empty($code)) {
                $this->error('登录凭证不能为空');
            }
            
            // 微信小程序AppID和AppSecret
            $appId = 'your_app_id'; // 请替换为你的微信小程序AppID
            $appSecret = 'your_app_secret'; // 请替换为你的微信小程序AppSecret
            
            // 由于AppID和AppSecret是默认值，这里使用模拟的openid进行测试
            // 实际使用时，请替换为正确的AppID和AppSecret
            if ($appId === 'your_app_id' || $appSecret === 'your_app_secret') {
                // 模拟openid，使用code作为唯一标识
                $openid = 'mock_openid_' . md5($code);
                $nickname = $userInfo ? ($userInfo['nickName'] ?? '测试用户') : '测试用户';
                $avatar = $userInfo ? ($userInfo['avatarUrl'] ?? 'https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=default%20user%20avatar&image_size=square') : 'https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=default%20user%20avatar&image_size=square';
            } else {
                // 实际获取openid的代码（当AppID和AppSecret正确时使用）
                $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appId}&secret={$appSecret}&js_code={$code}&grant_type=authorization_code";
                
                // 尝试使用file_get_contents获取响应
                $response = file_get_contents($url);
                
                // 如果file_get_contents失败，尝试使用curl
                if ($response === false) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $response = curl_exec($ch);
                    curl_close($ch);
                }
                
                if ($response === false) {
                    $this->error('无法连接到微信服务器，请检查网络连接');
                }
                
                $result = json_decode($response, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->error('解析微信响应失败: ' . json_last_error_msg());
                }
                
                if (isset($result['errcode'])) {
                    // 检查是否是AppID或AppSecret错误
                    if ($result['errcode'] == 40013 || $result['errcode'] == 40164) {
                        $this->error('AppID或AppSecret错误，请在UserController.php文件中替换为正确的值');
                    } else {
                        $this->error('获取openid失败: ' . $result['errmsg']);
                    }
                }
                
                if (!isset($result['openid'])) {
                    $this->error('获取openid失败，响应数据: ' . json_encode($result));
                }
                
                $openid = $result['openid'];
                $nickname = $userInfo ? ($userInfo['nickName'] ?? '') : '';
                $avatar = $userInfo ? ($userInfo['avatarUrl'] ?? '') : '';
            }
            
            // 确保openid不为空
            if (empty($openid)) {
                $this->error('获取openid失败');
            }
            
            // 查找用户
            $user = $this->userModel->getUserByOpenid($openid);
            
            if ($user) {
                // 用户已存在，更新信息
                $updateData = [
                    'nickname' => $nickname,
                    'avatar' => $avatar
                ];
                if (!empty($phone)) {
                    $updateData['phone'] = $phone;
                }
                $this->userModel->updateUser($user['id'], $updateData);
                // 重新获取用户信息
                $user = $this->userModel->getUserByOpenid($openid);
                $this->success(['token' => 'mock_token_' . md5($openid), 'userInfo' => $user], '登录成功');
            } else {
                // 创建新用户
                $userData = [
                    'openid' => $openid,
                    'nickname' => $nickname,
                    'avatar' => $avatar
                ];
                if (!empty($phone)) {
                    $userData['phone'] = $phone;
                }
                $userId = $this->userModel->createUser($userData);
                $newUser = $this->userModel->getUserById($userId);
                $this->success(['token' => 'mock_token_' . md5($openid), 'userInfo' => $newUser], '注册成功');
            }
        } catch (Exception $e) {
            $this->error('登录失败: ' . $e->getMessage());
        }
    }
    
    // 更新用户信息
    public function updateInfo() {
        $params = $this->getParams();
        $userId = $this->getUserId();
        
        $data = [];
        if (isset($params['phone'])) {
            $data['phone'] = $params['phone'];
        }
        if (isset($params['id_card'])) {
            $data['id_card'] = $params['id_card'];
        }
        if (isset($params['real_name'])) {
            $data['real_name'] = $params['real_name'];
        }
        
        if (empty($data)) {
            $this->error('没有需要更新的信息');
        }
        
        $result = $this->userModel->updateUser($userId, $data);
        if ($result) {
            $user = $this->userModel->getUserById($userId);
            $this->success($user, '信息更新成功');
        } else {
            $this->error('信息更新失败');
        }
    }
    
    // 获取用户信息
    public function getUserInfo() {
        $userId = $this->getUserId();
        $user = $this->userModel->getUserById($userId);
        if ($user) {
            $this->success($user, '获取用户信息成功');
        } else {
            $this->error('用户不存在');
        }
    }
    
    // 绑定手机号
    public function bindPhone() {
        try {
            $params = $this->getParams();
            $encryptedData = $params['encryptedData'] ?? '';
            $iv = $params['iv'] ?? '';
            $code = $params['code'] ?? '';
            
            if (empty($encryptedData) || empty($iv)) {
                $this->error('参数不能为空');
            }
            
            // 微信小程序AppID和AppSecret
            $appId = 'your_app_id'; // 请替换为你的微信小程序AppID
            $appSecret = 'your_app_secret'; // 请替换为你的微信小程序AppSecret
            
            // 由于AppID和AppSecret是默认值，这里使用模拟的手机号
            // 实际使用时，请替换为正确的AppID和AppSecret并使用微信提供的解密方法
            if ($appId === 'your_app_id' || $appSecret === 'your_app_secret') {
                // 模拟手机号 - 使用固定的测试手机号
                $phone = '13800138000';
                
                // 生成模拟的openid
                $openid = 'mock_openid_' . md5($code ?: 'default');
                
                // 查找用户
                $user = $this->userModel->getUserByOpenid($openid);
                if (!$user) {
                    // 如果用户不存在，创建一个新用户
                    $userData = [
                        'openid' => $openid,
                        'nickname' => '微信用户',
                        'avatar' => 'https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=default%20user%20avatar&image_size=square',
                        'phone' => $phone,
                        'real_name' => '测试用户'
                    ];
                    $userId = $this->userModel->createUser($userData);
                } else {
                    $userId = $user['id'];
                    // 更新用户手机号和真实姓名
                    $updateData = ['phone' => $phone, 'real_name' => '测试用户'];
                    $this->userModel->updateUser($userId, $updateData);
                }
                
                // 获取更新后的用户信息
                $user = $this->userModel->getUserById($userId);
                
                $this->success(['userInfo' => $user], '手机号绑定成功（模拟）');
            } else {
                // 实际解密手机号的代码（当AppID和AppSecret正确时使用）
                // 这里需要使用微信提供的解密方法
                // 由于需要session_key，这里暂时不实现
                $this->error('请在UserController.php文件中替换为正确的AppID和AppSecret');
            }
        } catch (Exception $e) {
            $this->error('绑定手机号失败: ' . $e->getMessage());
        }
    }
}