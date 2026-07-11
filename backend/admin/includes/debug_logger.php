<?php
/**
 * 授权系统调试日志工具
 * 用于记录授权系统相关的调试信息
 */

class LicenseDebugLogger {
    
    private static $logDir = null;
    private static $enabled = true;
    
    public static function init() {
        self::$logDir = dirname(__DIR__) . '/../logs';
        if (!file_exists(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
        
        $debugConfig = dirname(__DIR__) . '/../config/debug_config.php';
        if (file_exists($debugConfig)) {
            $config = include($debugConfig);
            self::$enabled = $config['debug_enabled'] ?? true;
        }
    }
    
    public static function log($category, $message, $data = null) {
        if (!self::$enabled) return;
        
        if (self::$logDir === null) {
            self::init();
        }
        
        $logFile = self::$logDir . '/license_debug_' . date('Ymd') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        
        $logEntry = "[$timestamp] [$category] $message";
        
        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $logEntry .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
            } else {
                $logEntry .= " | Data: " . $data;
            }
        }
        
        $logEntry .= "\n";
        
        error_log($logEntry, 3, $logFile);
    }
    
    public static function logAPIRequest($apiUrl, $method = 'GET', $params = null, $response = null) {
        if (!self::$enabled) return;
        
        self::log('API_REQUEST', "URL: $apiUrl, Method: $method", $params);
        
        if ($response !== null) {
            if (is_array($response) || is_object($response)) {
                $responseStr = json_encode($response, JSON_UNESCAPED_UNICODE);
            } else {
                $responseStr = (string)$response;
            }
            
            if (strlen($responseStr) > 2000) {
                $responseStr = substr($responseStr, 0, 2000) . '... [TRUNCATED]';
            }
            
            self::log('API_RESPONSE', "URL: $apiUrl", ['response' => $responseStr]);
        }
    }
    
    public static function logError($category, $message, $exception = null) {
        if (!self::$enabled) return;
        
        $logEntry = "[ERROR] $message";
        
        if ($exception instanceof Exception) {
            $logEntry .= " | Exception: " . $exception->getMessage() . 
                         " | File: " . $exception->getFile() . 
                         " | Line: " . $exception->getLine();
        }
        
        self::log($category, $logEntry);
    }
    
    public static function logSubscription($action, $data) {
        self::log('SUBSCRIPTION', "$action", $data);
    }
    
    public static function logAuthCheck($domain, $ip, $result) {
        self::log('AUTH_CHECK', "Domain: $domain, IP: $ip", $result);
    }
}

LicenseDebugLogger::init();
?>