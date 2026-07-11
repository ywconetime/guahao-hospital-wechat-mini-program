<?php
// JWT工具类
class JWT {
    /**
     * 编码JWT
     * @param array $payload JWT载荷
     * @param string $key 密钥
     * @param string $alg 算法
     * @return string JWT字符串
     */
    public static function encode($payload, $key, $alg = 'HS256') {
        // 生成头部
        $header = json_encode(['typ' => 'JWT', 'alg' => $alg]);
        // 生成载荷
        $payload = json_encode($payload);
        // 编码头部和载荷
        $header = self::base64UrlEncode($header);
        $payload = self::base64UrlEncode($payload);
        // 生成签名
        $signature = hash_hmac('sha256', "$header.$payload", $key, true);
        $signature = self::base64UrlEncode($signature);
        // 组合JWT
        return "$header.$payload.$signature";
    }
    
    /**
     * 解码JWT
     * @param string $token JWT字符串
     * @param string $key 密钥
     * @param array $allowedAlgs 允许的算法
     * @return object 解码后的载荷
     */
    public static function decode($token, $key, $allowedAlgs = ['HS256']) {
        // 分割JWT
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Invalid token');
        }
        list($header, $payload, $signature) = $parts;
        // 解码头部和载荷
        $header = json_decode(self::base64UrlDecode($header));
        $payload = json_decode(self::base64UrlDecode($payload));
        // 验证算法
        if (!in_array($header->alg, $allowedAlgs)) {
            throw new Exception('Invalid algorithm');
        }
        // 验证签名
        $expectedSignature = hash_hmac('sha256', $parts[0] . '.' . $parts[1], $key, true);
        $expectedSignature = self::base64UrlEncode($expectedSignature);
        if ($signature !== $expectedSignature) {
            throw new Exception('Invalid signature');
        }
        return $payload;
    }
    
    /**
     * Base64 URL编码
     * @param string $data 待编码的数据
     * @return string 编码后的字符串
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL解码
     * @param string $data 待解码的数据
     * @return string 解码后的字符串
     */
    private static function base64UrlDecode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}
?>