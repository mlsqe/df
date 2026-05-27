<?php
require __DIR__ . '/includes/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Redis\Client as AsyncRedis;

// 测试阶段先注释掉日志重定向，让动静直接吐在屏幕上
// Worker::$stdoutFile = __DIR__ . '/ws_server.log';

$wsWorker = new Worker("websocket://0.0.0.0:" . WS_PORT);
$wsWorker->count = 1;
$wsWorker->name = 'DeltaWS';

// 【优化】删除了手动维护的 $connections 数组，直接用系统自带的

$wsWorker->onConnect = function(TcpConnection $connection) {
    // 连接时无需手动记录，Workerman 会自动放入 $worker->connections 中
};

$wsWorker->onClose = function(TcpConnection $connection) {
    // 断开时也无需手动 unset
};

// 启动 Redis 订阅进程
$wsWorker->onWorkerStart = function() use ($wsWorker) {
    
    // 【核心修复】重新构建标准的 Redis 连接配置（密码必须严格拼在 URL 中）
    $redis_password = (defined('REDIS_PASSWORD') && REDIS_PASSWORD) ? ':' . REDIS_PASSWORD . '@' : '';
    $redis_url = "redis://{$redis_password}" . REDIS_HOST . ':' . REDIS_PORT;
    
    $redis = new AsyncRedis($redis_url);

    // 异步订阅
    $redis->subscribe(WS_REDIS_CHANNEL, function($channel, $msg) use ($wsWorker) {
        
        // 💡 调试利器：只要 Redis 发布了消息，服务器终端就会疯狂打印这一行
        echo "【Redis 收到消息】频道: {$channel} | 内容: {$msg} \n";
        
        // 【核心修复】使用 Workerman 自带的 connections 数组进行全员广播
        foreach ($wsWorker->connections as $conn) {
            $conn->send($msg);
        }
    });
};

Worker::runAll();