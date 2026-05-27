# 游戏代练平台 (Boost Platform)

一个基于 PHP 的在线游戏代练交易平台，支持雇主下单、代练接单、管理员后台管理，并通过 WebSocket 实现实时订单状态更新。

## 功能特性

- **多角色系统**：普通用户（雇主）、代练（打手）、管理员，各自拥有独立面板。
- **订单生命周期管理**：发布订单 → 代练接单 → 提交完成 → 雇主验收 → 结算。
- **邀请码注册**：代练可通过管理员或现有代练生成的邀请码注册。
- **实时通知**：基于 Workerman + Redis 的 WebSocket 推送，订单状态变更、余额变动等即时刷新。
- **后台管理**：管理员可管理用户、代练、订单、邀请码、代练类型、系统设置等。
- **资金流水**：记录充值、提现、佣金等变动。
- **响应式界面**：使用 Bootstrap 风格，适配 PC 与移动端。

## 环境要求

- PHP >= 7.2
- MySQL >= 5.7
- Redis >= 5.0
- Composer
- 可选：Nginx / Apache（Web 服务器）

## 快速开始

### 1. 克隆仓库

```bash
git clone https://github.com/mlsqe/df.git
cd df
```

### 2. 安装依赖

```bash
composer install
```

### 3. 配置数据库

在 `api/config.php` 和 `includes/config.php` 中设置数据库连接信息：

```php
// api/config.php
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = 'your_password';
$db_name = 'df_boost';

// Redis 连接
$redis_host = '127.0.0.1';
$redis_port = 6379;
$redis_auth = ''; // 如有密码请填写
```

> 请确保两个配置文件中的数据库参数保持一致。

### 4. 创建数据库表

根据代码中的 SQL 查询语句创建必要的表。推荐在 MySQL 中执行以下基础建表语句（可后续根据实际查询补充）：

```sql
CREATE DATABASE IF NOT EXISTS df_boost DEFAULT CHARSET utf8mb4;

USE df_boost;

-- 用户表（包含普通用户、代练、管理员）
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','booster','admin') NOT NULL DEFAULT 'user',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1正常 0冻结',
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `phone` varchar(20) DEFAULT NULL,
  `wechat` varchar(50) DEFAULT NULL,
  `qq` varchar(20) DEFAULT NULL,
  `invite_code` varchar(32) DEFAULT NULL COMMENT '代练注册用邀请码',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 订单表
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `booster_id` int(11) DEFAULT NULL,
  `game_type` varchar(50) NOT NULL COMMENT '代练类型',
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `screenshot` varchar(255) DEFAULT NULL COMMENT '完成截图',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 邀请码表
CREATE TABLE `invites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL,
  `created_by` int(11) NOT NULL COMMENT '创建者用户ID',
  `used_by` int(11) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0未使用 1已使用',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Token 表（用于 API 认证）
CREATE TABLE `user_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 管理员设置表（存储系统配置）
CREATE TABLE `settings` (
  `key` varchar(50) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

> 由于仓库未附带 SQL 文件，以上表结构基于代码中出现的字段推断。如有遗漏，请在调试过程中根据实际 SQL 报错补充字段。

### 5. 配置 Web 服务器

将项目目录设置为 Web 根目录，确保 `index.php` 可访问。

**Nginx 配置示例：**

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/df;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass   127.0.0.1:9000;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include        fastcgi_params;
    }

    # WebSocket 代理（用于 wss 连接）
    location /wss/ {
        proxy_pass http://127.0.0.1:2345;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_read_timeout 86400s;
    }
}
```

若使用 HTTPS，请将 `proxy_pass` 保持为 `http://127.0.0.1:2345`，并配置 SSL 证书，前端会自动使用 `wss://`。

### 6. 启动 WebSocket 服务

```bash
php server.php start -d
```

该服务监听 `0.0.0.0:2345`，用于处理实时推送。  
停止服务：`php server.php stop`  
重启服务：`php server.php restart -d`

### 7. 创建管理员账户

目前没有提供 web 端管理员注册功能，需要手动在数据库中插入一条管理员记录。

1. 使用 `password_hash()` 生成密码哈希，例如：
   ```php
   php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"
   ```
2. 在 MySQL 中执行：
   ```sql
   INSERT INTO users (username, password, role, status) 
   VALUES ('admin', '上一步生成的哈希', 'admin', 1);
   ```

之后即可使用该账户登录后台。

## 角色与权限

| 角色 | 功能 |
|------|------|
| **普通用户** | 发布代练订单、查看订单历史、验收订单、管理个人资料与余额 |
| **代练** | 查看可接订单、接单、提交完成截图、查看进行中/历史订单、生成邀请码 |
| **管理员** | 用户管理、代练审核、订单管理（强制结算/撤销）、邀请码管理、代练类型管理、系统设置 |

## 实时通信机制

- 使用 **Workerman** 创建 WebSocket 服务。
- 客户端连接后通过 token 认证，服务端建立 `user_id → connection` 映射。
- 业务操作完成后，后端通过 Redis 发布消息到 `realtime_channel`。
- WebSocket 服务订阅该频道，根据消息中的 `target_user_ids` 精准推送给特定用户。
- 前端收到消息后，通过 AJAX 局部刷新对应 UI 区域（如订单列表、仪表盘、余额）。

## 目录结构

```
.
├── api/                     # API 业务处理
│   ├── admin.php
│   ├── auth.php
│   ├── config.php
│   ├── functions.php
│   ├── order.php
│   ├── user.php
│   └── worker.php
├── includes/                # 网站模块与公共代码
│   ├── modules/             # 各功能页面
│   │   ├── admin_*.php      # 管理后台模块
│   │   ├── booster_*.php    # 代练模块
│   │   ├── history_orders.php
│   │   ├── my_orders.php
│   │   ├── my_profile.php
│   │   └── new_order.php
│   ├── auth.php
│   ├── config.php
│   ├── footer.php
│   ├── functions.php
│   ├── header.php
│   └── redis.php
├── css/
│   └── style.css
├── js/
│   └── main.js
├── index.php                # 入口路由
├── server.php               # WebSocket 服务
├── composer.json
├── AUTH
└── LICENSE
```

## 常见问题

**Q: 注册后无法登录？**  
A: 检查账号状态是否被冻结（`users.status = 0`），管理员可在后台解冻。

**Q: 代练注册时提示邀请码无效？**  
A: 确保邀请码未被使用且未过期（根据系统设置中的邀请码周期判断）。

**Q: 实时通知不工作？**  
A: 
1. 确认 `server.php` 已成功启动（`php server.php status`）。
2. 检查 Redis 服务是否运行，`api/config.php` 中的 Redis 配置是否正确。
3. 浏览器控制台是否有 WebSocket 连接错误？若站点使用 HTTPS，需配置 Nginx 反向代理提供 `wss://` 连接。
4. 检查 `js/main.js` 中的 WebSocket 地址是否正确指向代理路径。

**Q: 如何修改站点名称？**  
A: 在 `includes/config.php` 中修改 `SITE_NAME` 常量。

## 开源协议

本项目使用 [Apache License 2.0](LICENSE) 开源。
