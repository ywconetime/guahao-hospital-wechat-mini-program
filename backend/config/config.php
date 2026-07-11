<?php
// 数据库配置
$config = [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'username' => 'xiaochengxu',
        'password' => 'xiaochengxu',
        'database' => 'xiaochengxu',
        'charset' => 'utf8mb4'
    ],
    'jwt' => [
        'secret' => 'your_secret_key',
        'expire' => 86400 // 24小时
    ],
    'api' => [
        'version' => 'v1'
    ],
    'wechat' => [
        'appid' => '',
        'appsecret' => ''
    ]
];

return $config;