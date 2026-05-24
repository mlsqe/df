<?php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'dfmls666');
define('DB_USER', 'dfmls666');
define('DB_PASS', 'a.z123123');
define('DB_CHARSET', 'utf8mb4');
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_PASSWORD', '');
define('REDIS_DB', 0);
define('REDIS_TIMEOUT', 2.5);
define('REDIS_ENABLED', false);
define('SITE_NAME', '俊俊电竞');
define('COMMISSION_RATE', 0.10);
define('DEPOSIT_AMOUNT', 200);
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('PAYMENT_API_URL', 'https://pay.example.com/');
define('PAYMENT_MERCHANT_ID', '');
define('PAYMENT_SECRET_KEY', '');
define('WS_ENABLED', true);
define('WS_PORT', 8282);
define('WS_REDIS_CHANNEL', 'delta_ws_channel');
function getDB() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        return $pdo;
    } catch (PDOException $e) {
        die('数据库连接失败: ' . $e->getMessage());
    }
}
