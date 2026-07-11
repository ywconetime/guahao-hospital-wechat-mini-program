<?php
// config/api_key.dev.php - 开发版（无保护）
// 用途：开发、维护、修改代码时使用
// 特点：无保护机制，方便修改测试

// 开发版简单密钥（仅用于开发）
define('LICENSE_ENCRYPT_KEY', 'L1c3ns3_S3cur1ty_K3y_2026_D3V#@!');

// 完整性校验密钥
define('LICENSE_HASH_KEY', 'H4sh_V3r1f1c4t10n_K3y_2026_D3V#@!');

// 加密算法
define('LICENSE_ENCRYPT_ALGORITHM', 'AES-256-CBC');

// 初始化向量长度
define('LICENSE_IV_LENGTH', openssl_cipher_iv_length(LICENSE_ENCRYPT_ALGORITHM));

// 保护层级（开发版设为0）
define('LICENSE_PROTECTION_LEVEL', 0);

// 报警邮箱
define('LICENSE_ALERT_EMAIL', '14821043@qq.com');

// 报警日志路径
define('LICENSE_ALERT_LOG', __DIR__ . '/../logs/protection_alert.log');

// 是否启用保护机制（开发版设为false）
define('LICENSE_PROTECTION_ENABLED', false);
?>