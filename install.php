<?php
$installed = file_exists(__DIR__ . '/includes/config.php');
$step = $_POST['step'] ?? ($_GET['step'] ?? 1);
$error = '';
$success = '';
$log = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step == 2) {
    $reset_db = isset($_POST['reset_db']) && $_POST['reset_db'] == '1';
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_port = $_POST['db_port'] ?? '3306';
    $db_user = $_POST['db_user'] ?? 'dfmls666';
    $db_pass = $_POST['db_pass'] ?? 'a.z123123';
    $db_name = $_POST['db_name'] ?? 'dfmls666';
    $site_name = $_POST['site_name'] ?? '俊俊电竞';
    $admin_user = $_POST['admin_user'] ?? 'admin';
    $admin_pass = $_POST['admin_pass'] ?? 'a.z123123';
    $redis_host = $_POST['redis_host'] ?? '127.0.0.1';
    $redis_port = $_POST['redis_port'] ?? '6379';
    $redis_pass = $_POST['redis_pass'] ?? '';

    if (empty($db_name) || empty($admin_pass)) {
        $error = '数据库名和管理员密码不能为空';
    } else {
        try {
            $dsn = "mysql:host=$db_host;port=$db_port;charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$db_name`");

            if ($reset_db) {
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                $log[] = "🔍 发现 " . count($tables) . " 张旧表";
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                // 按外键依赖顺序删除表，避免约束冲突
                $dropOrder = [
                    'disputes','transactions','progress','orders','boosters','invite_codes',
                    'recharge_log','payment_config','game_types','booster_levels','system_config','users'
                ];
                foreach ($dropOrder as $table) {
                    if (in_array($table, $tables)) {
                        $pdo->exec("DROP TABLE IF EXISTS `$table`");
                        $log[] = "🗑️ 删除表: $table";
                    }
                }
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                $log[] = "✅ 数据库已重置";
            }

            $log[] = "📦 开始创建数据表...";

            // 用户表（role 为 VARCHAR，支持动态身份）
            $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) UNIQUE NOT NULL,
                `password_hash` VARCHAR(255) NOT NULL,
                `real_name` VARCHAR(50) DEFAULT '',
                `id_card` VARCHAR(18) DEFAULT '',
                `phone` VARCHAR(20) DEFAULT '',
                `qq` VARCHAR(20) DEFAULT '',
                `wechat` VARCHAR(30) DEFAULT '',
                `avatar` VARCHAR(255) DEFAULT '',
                `balance` DECIMAL(10,2) DEFAULT 0,
                `role` VARCHAR(50) DEFAULT 'user',
                `status` ENUM('active','banned','pending') DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `qq` VARCHAR(20) DEFAULT '' AFTER `phone`"); } catch (PDOException $e) {}
            try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `wechat` VARCHAR(30) DEFAULT '' AFTER `qq`"); } catch (PDOException $e) {}
            try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `avatar` VARCHAR(255) DEFAULT '' AFTER `wechat`"); } catch (PDOException $e) {}
            try { $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` VARCHAR(50) DEFAULT 'user'"); } catch (PDOException $e) {}

            $pdo->exec("CREATE TABLE IF NOT EXISTS `booster_levels` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(50) NOT NULL,
                `min_orders` INT DEFAULT 0,
                `min_rating` DECIMAL(2,1) DEFAULT 5.0,
                `deposit_required` DECIMAL(10,2) DEFAULT 0,
                `description` TEXT,
                `sort_order` INT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `boosters` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT,
                `real_name` VARCHAR(50),
                `phone` VARCHAR(20),
                `wechat` VARCHAR(30) DEFAULT '',
                `qq` VARCHAR(20) DEFAULT '',
                `deposit_paid` DECIMAL(10,2) DEFAULT 0,
                `rating` DECIMAL(2,1) DEFAULT 5.0,
                `total_orders` INT DEFAULT 0,
                `status` ENUM('pending','active','banned') DEFAULT 'pending',
                `register_mode` VARCHAR(20) DEFAULT NULL,
                `refuse_reason` VARCHAR(255) DEFAULT '',
                `level_id` INT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
                FOREIGN KEY (`level_id`) REFERENCES `booster_levels`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `orders` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `order_no` VARCHAR(30) UNIQUE NOT NULL,
                `user_id` INT,
                `booster_id` INT DEFAULT NULL,
                `game_type_id` INT DEFAULT NULL,
                `game_type` VARCHAR(50) DEFAULT '哈弗币',
                `quantity` INT DEFAULT 0,
                `amount` DECIMAL(10,2) NOT NULL,
                `target_description` TEXT,
                `status` ENUM('pending','accepted','in_progress','completed','cancelled','disputed') DEFAULT 'pending',
                `payment_status` ENUM('unpaid','paid') DEFAULT 'unpaid',
                `screenshot_url` VARCHAR(255) DEFAULT '',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `accepted_at` TIMESTAMP NULL,
                `completed_at` TIMESTAMP NULL,
                FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
                FOREIGN KEY (`booster_id`) REFERENCES `boosters`(`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `progress` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `order_id` INT,
                `booster_id` INT,
                `description` TEXT,
                `screenshot_url` VARCHAR(255) DEFAULT '',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `transactions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT,
                `order_id` INT DEFAULT NULL,
                `type` VARCHAR(30),
                `amount` DECIMAL(10,2),
                `balance_after` DECIMAL(10,2),
                `description` VARCHAR(255),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `disputes` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `order_id` INT,
                `filed_by` INT,
                `reason` TEXT,
                `status` ENUM('open','resolved') DEFAULT 'open',
                `resolution` VARCHAR(50) DEFAULT '',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `resolved_at` TIMESTAMP NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `game_types` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(50) NOT NULL,
                `unit_label` VARCHAR(20) DEFAULT 'K',
                `min_unit` INT DEFAULT 10,
                `max_unit` INT DEFAULT 9999,
                `price_per_unit` DECIMAL(10,2) NOT NULL,
                `status` ENUM('active','disabled') DEFAULT 'active',
                `sort_order` INT DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `payment_config` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `pay_type` VARCHAR(20) DEFAULT 'epay',
                `api_url` VARCHAR(255),
                `merchant_id` VARCHAR(100),
                `secret_key` VARCHAR(255),
                `status` ENUM('enabled','disabled') DEFAULT 'enabled'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `recharge_log` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT,
                `order_no` VARCHAR(50),
                `amount` DECIMAL(10,2),
                `pay_type` VARCHAR(20),
                `channel_code` VARCHAR(20) DEFAULT '',
                `status` ENUM('pending','success','failed') DEFAULT 'pending',
                `notify_data` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `paid_at` TIMESTAMP NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            try { $pdo->exec("ALTER TABLE `recharge_log` ADD COLUMN `channel_code` VARCHAR(20) DEFAULT '' AFTER `pay_type`"); } catch (PDOException $e) {}
            try { $pdo->exec("ALTER TABLE `recharge_log` ADD COLUMN `notify_data` TEXT AFTER `status`"); } catch (PDOException $e) {}

            $pdo->exec("CREATE TABLE IF NOT EXISTS `system_config` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `cfg_key` VARCHAR(50) UNIQUE NOT NULL,
                `cfg_value` TEXT,
                `description` VARCHAR(255)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `invite_codes` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `code` VARCHAR(20) UNIQUE NOT NULL,
                `type` ENUM('admin','booster') DEFAULT 'admin',
                `created_by` INT,
                `used_by` INT DEFAULT NULL,
                `status` ENUM('unused','used','expired') DEFAULT 'unused',
                `expire_at` DATETIME NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `used_at` TIMESTAMP NULL,
                FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
                FOREIGN KEY (`used_by`) REFERENCES `users`(`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("INSERT IGNORE INTO `booster_levels` (`name`, `min_orders`, `min_rating`, `deposit_required`, `sort_order`) VALUES ('初级打手',0,5.0,200,1),('中级打手',50,4.8,500,2),('高级打手',200,4.9,1000,3),('王牌打手',500,4.95,2000,4)");
            $pdo->exec("INSERT IGNORE INTO `game_types` (`name`, `unit_label`, `price_per_unit`) VALUES ('跑刀','K',0.05),('跑车','M',50.00)");
            // 默认模块权限配置（包含所有身份）
            $pdo->exec("INSERT IGNORE INTO `system_config` (`cfg_key`, `cfg_value`, `description`) VALUES 
                ('register_open','1','是否开放注册'),
                ('booster_register_mode','invite','打手注册方式 free/invite/deposit/invite_or_deposit'),
                ('deposit_amount','200','打手保证金金额'),
                ('booster_invite_limit','5','打手每周期可创建邀请码数量'),
                ('booster_invite_period_days','30','打手邀请码创建周期（天）'),
                ('module_permissions','{\"user\":[\"dashboard\",\"my_profile\",\"my_orders\",\"new_order\",\"history_orders\"],\"booster\":[\"dashboard\",\"my_profile\",\"my_orders\",\"new_order\",\"booster_orders\",\"booster_progress\",\"booster_invites\",\"history_orders\"],\"pending_booster\":[\"dashboard\",\"my_profile\",\"my_orders\",\"history_orders\",\"new_order\",\"booster_invites\",\"booster_orders\",\"booster_progress\"],\"admin\":[\"dashboard\",\"my_profile\",\"admin_orders\",\"admin_boosters\",\"admin_disputes\",\"admin_types\",\"admin_invites\",\"admin_levels\",\"admin_settings\",\"admin_users\",\"history_orders\"],\"super_admin\":[\"dashboard\",\"my_profile\",\"my_orders\",\"new_order\",\"booster_orders\",\"booster_progress\",\"booster_invites\",\"admin_orders\",\"admin_boosters\",\"admin_disputes\",\"admin_types\",\"admin_invites\",\"admin_levels\",\"admin_settings\",\"admin_admins\",\"admin_users\",\"admin_roles\",\"history_orders\"]}','各角色可见模块'),
                ('ws_enabled','1','是否开启实时推送'),
                ('ws_port','8282','WebSocket 服务端口')");

            $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$admin_user]);
            if ($stmt->fetch()) {
                $pdo->prepare("UPDATE users SET password_hash = ?, role = 'super_admin' WHERE username = ?")->execute([$hash, $admin_user]);
            } else {
                $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'super_admin')")->execute([$admin_user, $hash]);
            }

            $configContent = "<?php
define('DB_HOST', '" . addslashes($db_host) . "');
define('DB_PORT', '" . addslashes($db_port) . "');
define('DB_NAME', '" . addslashes($db_name) . "');
define('DB_USER', '" . addslashes($db_user) . "');
define('DB_PASS', '" . addslashes($db_pass) . "');
define('DB_CHARSET', 'utf8mb4');
define('REDIS_HOST', '" . addslashes($redis_host) . "');
define('REDIS_PORT', " . intval($redis_port) . ");
define('REDIS_PASSWORD', '" . addslashes($redis_pass) . "');
define('REDIS_DB', 0);
define('REDIS_TIMEOUT', 2.5);
define('REDIS_ENABLED', false);
define('SITE_NAME', '" . addslashes($site_name) . "');
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
    static \$pdo = null;
    if (\$pdo !== null) return \$pdo;
    try {
        \$dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        return \$pdo;
    } catch (PDOException \$e) {
        die('数据库连接失败: ' . \$e->getMessage());
    }
}
";
            if (!is_dir(__DIR__ . '/includes')) mkdir(__DIR__ . '/includes', 0755, true);
            file_put_contents(__DIR__ . '/includes/config.php', $configContent);
            $success = "安装完成！";
        } catch (PDOException $e) {
            $error = '数据库操作失败：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>俊俊电竞 - 安装向导</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/layui@2.8.0/dist/css/layui.css">
    <style>
        body { background: #f2f3f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        .install-card { width: 100%; max-width: 650px; }
        .layui-card-header { font-size: 1.2rem; font-weight: bold; background: #1E9FFF; color: white; }
        .install-log { background: #2d2d2d; color: #a6e22e; padding: 15px; border-radius: 6px; font-family: 'Courier New', monospace; font-size: 0.85rem; max-height: 200px; overflow-y: auto; margin-top: 15px; }
        .reset-warning { background: #FF5722; color: white; padding: 12px; border-radius: 6px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .reset-warning i { font-size: 1.5rem; }
    </style>
</head>
<body>
<div class="layui-card install-card">
    <div class="layui-card-header"><?= $installed ? '🔄 重新安装/重置数据库' : '🔺 俊俊电竞 - 安装向导' ?></div>
    <div class="layui-card-body">
        <?php if (!empty($error)): ?>
            <div class="layui-bg-red" style="padding:10px;margin-bottom:15px;border-radius:4px;">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="layui-bg-green" style="padding:15px;margin-bottom:15px;border-radius:4px;text-align:center;">
                <h3 style="margin:0;">🎉 <?= htmlspecialchars($success) ?></h3>
                <?php if (!empty($admin_user)): ?>
                    <p style="margin:10px 0 0 0;">管理员：<strong><?= htmlspecialchars($admin_user) ?></strong> / <strong><?= htmlspecialchars($admin_pass) ?></strong></p>
                <?php endif; ?>
                <p style="color:#FF5722;margin-top:8px;">⚠️ 请立即删除 install.php 文件！</p>
                <a href="login.php" class="layui-btn layui-btn-normal" style="margin-top:10px;">前往登录</a>
            </div>
        <?php endif; ?>
        <?php if (!empty($log)): ?>
            <div class="install-log"><?php foreach ($log as $line): ?><div><?= htmlspecialchars($line) ?></div><?php endforeach; ?></div>
        <?php endif; ?>
        <?php if (empty($success)): ?>
        <form method="post" class="layui-form" id="installForm">
            <input type="hidden" name="step" value="2">
            <fieldset class="layui-elem-field"><legend>数据库设置</legend>
                <div class="layui-form-item"><label class="layui-form-label">主机</label><div class="layui-input-block"><input type="text" name="db_host" value="localhost" class="layui-input"></div></div>
                <div class="layui-form-item"><label class="layui-form-label">端口</label><div class="layui-input-block"><input type="text" name="db_port" value="3306" class="layui-input"></div></div>
                <div class="layui-form-item"><label class="layui-form-label">用户名</label><div class="layui-input-block"><input type="text" name="db_user" value="dfmls666" class="layui-input"></div></div>
                <div class="layui-form-item"><label class="layui-form-label">密码</label><div class="layui-input-block"><input type="password" name="db_pass" value="a.z123123" class="layui-input"></div></div>
                <div class="layui-form-item"><label class="layui-form-label">数据库名</label><div class="layui-input-block"><input type="text" name="db_name" value="dfmls666" required class="layui-input"></div></div>
            </fieldset>
            <fieldset class="layui-elem-field"><legend>Redis 设置（可选）</legend>
                <div class="layui-form-item"><label class="layui-form-label">主机</label><div class="layui-input-block"><input type="text" name="redis_host" value="127.0.0.1" class="layui-input"></div></div>
                <div class="layui-form-item"><label class="layui-form-label">端口</label><div class="layui-input-block"><input type="text" name="redis_port" value="6379" class="layui-input"></div></div>
                <div class="layui-form-item"><label class="layui-form-label">密码</label><div class="layui-input-block"><input type="password" name="redis_pass" class="layui-input"></div></div>
            </fieldset>
            <fieldset class="layui-elem-field"><legend>站点与管理员</legend>
                <div class="layui-form-item"><label class="layui-form-label">站点名称</label><div class="layui-input-block"><input type="text" name="site_name" value="俊俊电竞" class="layui-input"></div></div>
                <div class="layui-form-item"><label class="layui-form-label">管理员账号</label><div class="layui-input-block"><input type="text" name="admin_user" value="admin" required class="layui-input"></div></div>
                <div class="layui-form-item"><label class="layui-form-label">管理员密码</label><div class="layui-input-block"><input type="password" name="admin_pass" value="a.z123123" required class="layui-input"></div></div>
            </fieldset>
            <fieldset class="layui-elem-field"><legend>安装选项</legend>
                <div class="layui-form-item" style="margin-top:10px;"><label class="layui-form-label">重置数据库</label><div class="layui-input-block"><input type="checkbox" name="reset_db" value="1" title="安装前清空所有数据表" lay-skin="primary" id="resetCheckbox"></div></div>
            </fieldset>
            <div style="text-align:center;margin-top:20px;"><button type="button" class="layui-btn layui-btn-normal layui-btn-fluid" onclick="confirmInstall()"><i class="layui-icon layui-icon-ok-circle"></i> 开始安装</button></div>
        </form>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/layui@2.8.0/dist/layui.js"></script>
<script>
layui.use(['layer'], function() {
    var layer = layui.layer;
    window.confirmInstall = function() {
        var resetChecked = document.getElementById('resetCheckbox').checked;
        if (resetChecked) {
            layer.confirm('<div style="color:red;font-size:16px;">⚠️ 警告：此操作将清空所有数据！</div><ul style="margin:10px 0;padding-left:20px;"><li>删除所有用户账户</li><li>删除所有订单记录</li><li>删除所有打手信息</li><li>删除所有交易流水</li></ul><strong style="color:#FF5722;">此操作不可逆！确定继续？</strong>', {icon:0,title:'重置数据库确认',btn:['确定重置','取消'],area:'450px',shadeClose:false}, function(index){ layer.close(index); document.getElementById('installForm').submit(); });
        } else {
            document.getElementById('installForm').submit();
        }
    };
});
</script>
</body>
</html>