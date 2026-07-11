<?php
/**
 * 微信小程序工具类
 * 用于处理微信小程序的各种操作，包括解密手机号等
 */
class Wechat {
    
    /**
     * 解密微信小程序加密数据
     * @param string $encryptedData 加密数据
     * @param string $iv 初始向量
     * @param string $sessionKey 会话密钥
     * @return array|bool 解密后的数据或false
     */
    public static function decryptData($encryptedData, $iv, $sessionKey) {
        if (strlen($sessionKey) != 24) {
            return false;
        }
        
        if (strlen($iv) != 24) {
            return false;
        }
        
        $encryptedData = base64_decode($encryptedData);
        $iv = base64_decode($iv);
        $sessionKey = base64_decode($sessionKey);
        
        $result = openssl_decrypt($encryptedData, 'AES-128-CBC', $sessionKey, OPENSSL_RAW_DATA, $iv);
        
        if ($result === false) {
            return false;
        }
        
        $data = json_decode($result, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        return $data;
    }
    
    /**
     * 获取微信小程序session_key和openid
     * @param string $appId 小程序AppID
     * @param string $appSecret 小程序AppSecret
     * @param string $code 登录凭证
     * @return array|bool 包含openid和session_key的数组或false
     */
    public static function getSessionKey($appId, $appSecret, $code) {
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appId}&secret={$appSecret}&js_code={$code}&grant_type=authorization_code";
        
        $response = file_get_contents($url);
        
        if ($response === false) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            curl_close($ch);
        }
        
        if ($response === false) {
            return false;
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        if (isset($result['errcode'])) {
            return false;
        }
        
        if (!isset($result['openid']) || !isset($result['session_key'])) {
            return false;
        }
        
        return $result;
    }
    
    /**
     * 获取手机号
     * @param string $appId 小程序AppID
     * @param string $appSecret 小程序AppSecret  
     * @param string $code 登录凭证
     * @param string $encryptedData 加密的手机号数据
     * @param string $iv 初始向量
     * @return string|bool 手机号或false
     */
    public static function getPhoneNumber($appId, $appSecret, $code, $encryptedData, $iv) {
        // 获取session_key和openid
        $sessionData = self::getSessionKey($appId, $appSecret, $code);
        
        if (!$sessionData) {
            return false;
        }
        
        $sessionKey = $sessionData['session_key'];
        
        // 解密数据
        $decryptedData = self::decryptData($encryptedData, $iv, $sessionKey);
        
        if (!$decryptedData) {
            return false;
        }
        
        if (!isset($decryptedData['phoneNumber'])) {
            return false;
        }
        
        return $decryptedData['phoneNumber'];
    }
}
?>