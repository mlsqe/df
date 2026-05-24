<?php
require_once __DIR__ . '/config.php';

class RedisHelper {
    private static $instance = null;
    private $redis;

    private function __construct() {
        $this->redis = new Redis();
        $this->redis->connect(REDIS_HOST, REDIS_PORT, REDIS_TIMEOUT);
        if (REDIS_PASSWORD) $this->redis->auth(REDIS_PASSWORD);
        $this->redis->select(REDIS_DB);
    }

    public static function getInstance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function getRedis() { return $this->redis; }

    public function cacheGet($key) {
        $val = $this->redis->get($key);
        return $val ? json_decode($val, true) : null;
    }

    public function cacheSet($key, $data, $ttl = 3600) {
        $this->redis->setex($key, $ttl, json_encode($data));
    }

    public function rateLimit($key, $max, $window) {
        $current = $this->redis->incr($key);
        if ($current == 1) $this->redis->expire($key, $window);
        return $current <= $max;
    }
}