<?php
require_once __DIR__ . '/config.php';

function generateOrderNo() {
    return 'DF' . date('YmdHis') . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
}

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function isAdmin() {
    $user = getCurrentUser();
    return $user && in_array($user['role'], ['admin', 'super_admin']);
}

function isSuperAdmin() {
    $user = getCurrentUser();
    return $user && $user['role'] === 'super_admin';
}

function uploadScreenshot($file) {
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (!in_array(strtolower($ext), ['jpg','jpeg','png','gif','webp'])) return false;
    $filename = uniqid('ss_') . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
        return 'uploads/' . $filename;
    }
    return false;
}

function addTransaction($userId, $type, $amount, $orderId = null, $desc = '') {
    $db = getDB();
    $stmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    $balanceAfter = $user['balance'] + $amount;
    $stmt = $db->prepare("INSERT INTO transactions (user_id, order_id, type, amount, balance_after, description) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$userId, $orderId, $type, $amount, $balanceAfter, $desc]);
    $stmt = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
    $stmt->execute([$balanceAfter, $userId]);
}

function getOrderStatusText($status) {
    $map = ['pending'=>'待接单','accepted'=>'已接单','in_progress'=>'代练中','completed'=>'已完成','cancelled'=>'已取消','disputed'=>'争议中'];
    return $map[$status] ?? $status;
}

function formatGameUnit($amount, $unit = 'K') {
    if ($unit === 'K' && $amount >= 1000) {
        return number_format($amount / 1000, 1) . 'M';
    } elseif ($unit === 'M' && $amount >= 1000) {
        return number_format($amount / 1000, 1) . 'B';
    }
    return number_format($amount) . $unit;
}

function getGameTypeOptions() {
    $db = getDB();
    return $db->query("SELECT * FROM game_types WHERE status='active' ORDER BY sort_order")->fetchAll();
}

function getBoosterLevels() {
    $db = getDB();
    return $db->query("SELECT * FROM booster_levels ORDER BY sort_order")->fetchAll();
}
function publishTableReload($table) {
    // 创建一个日志文件，直接写在当前目录下
    $logFile = __DIR__ . '/ws_debug.log';
    $time = date('[Y-m-d H:i:s] ');
    
    file_put_contents($logFile, $time . "【触发动作】试图刷新表格: {$table}\n", FILE_APPEND);

    if (!defined('WS_ENABLED') || !WS_ENABLED) {
        file_put_contents($logFile, "  ❌【拦截】WS_ENABLED 常量未定义，或者值不为 true\n", FILE_APPEND);
        return;
    }
    
    if (!class_exists('Redis')) {
        file_put_contents($logFile, "  ❌【拦截】当前网页的 PHP 环境没有安装/启用 Redis 扩展（class Redis 不存在）\n", FILE_APPEND);
        return;
    }
    
    try {
        $redis = new Redis();
        $redis->connect(REDIS_HOST, REDIS_PORT);
        if (defined('REDIS_PASSWORD') && REDIS_PASSWORD) {
            $redis->auth(REDIS_PASSWORD);
        }
        
        $result = $redis->publish(WS_REDIS_CHANNEL, json_encode([
            'type' => 'reload',
            'table' => $table
        ]));
        
        file_put_contents($logFile, "  ✅【成功】消息已成功送入 Redis！订阅者接收数量: {$result}\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents($logFile, "  💥【异常】Redis 报错啦: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}