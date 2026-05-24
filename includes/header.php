<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
$currentUser = getCurrentUser();

// 根据身份设置导航栏背景色
$navbarColor = '#1E9FFF';
if ($currentUser) {
    if (in_array($currentUser['role'], ['admin', 'super_admin'])) {
        $navbarColor = '#FF5722';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM boosters WHERE user_id=? AND status='active'");
        $stmt->execute([$currentUser['id']]);
        if ($stmt->fetch()) {
            $navbarColor = '#5FB878';  // 打手绿色
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= SITE_NAME ?> - 代练平台</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/layui@2.8.0/dist/css/layui.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .layui-header { background-color: <?= $navbarColor ?> !important; }
        .topbar { display: flex; align-items: center; justify-content: space-between; height: 60px; padding: 0 20px; }
        .topbar .layui-nav { background: transparent !important; }
        .topbar .layui-nav .layui-nav-item { line-height: 60px; }
        .topbar .layui-nav .layui-nav-item a {
            color: #fff !important;
            transition: all 0.2s;
            padding: 0 15px;
            border-radius: 4px;
        }
        .topbar .layui-nav .layui-nav-item a:hover {
            background-color: rgba(255, 255, 255, 0.15) !important;
            color: #fff !important;
        }
        #wsStatus {
            font-size: 12px;
            color: #fff;
            background: rgba(255,255,255,0.2);
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 8px;
            transition: 0.3s;
            display: inline-block;
        }
    </style>
</head>
<body>
<div class="layui-header topbar">
    <a href="index.php" style="font-size:1.3rem; color:#fff; text-decoration:none; white-space:nowrap;">
        🔺 <?= SITE_NAME ?><span style="background:#fff; color:<?= $navbarColor ?>; padding:2px 8px; border-radius:3px; margin-left:8px;">代练</span>
        <span id="wsStatus">未连接</span>
    </a>
    <!-- 移动端汉堡按钮 -->
    <span class="nav-toggle" onclick="toggleMenu()">☰</span>
    <ul class="layui-nav" id="mainNav">
        <li class="layui-nav-item"><a href="index.php">首页</a></li>
        <?php if ($currentUser): ?>
            <?php if (isAdmin()): ?>
                <li class="layui-nav-item"><a href="admin.php">管理后台</a></li>
            <?php endif; ?>
            <?php
                $db = getDB();
                $stmt = $db->prepare("SELECT id FROM boosters WHERE user_id=? AND status='active'");
                $stmt->execute([$currentUser['id']]);
                $isBooster = $stmt->fetch() ? true : false;
            ?>
            <?php if ($isBooster): ?>
                <li class="layui-nav-item"><a href="booster.php">打手面板</a></li>
            <?php endif; ?>
            <li class="layui-nav-item">
                <a href="javascript:;">👤 <?= sanitize($currentUser['username']) ?> | 💰 ¥<?= number_format($currentUser['balance'], 2) ?></a>
            </li>
            <li class="layui-nav-item"><a href="javascript:window.logout();">退出</a></li>
        <?php else: ?>
            <li class="layui-nav-item"><a href="login.php" class="layui-btn layui-btn-sm layui-btn-warm">登录</a></li>
        <?php endif; ?>
    </ul>
</div>

<!-- 白色内容容器 -->
<div class="main-content" style="background:#fff; min-height:calc(100vh - 60px); padding:20px;">

<script>
// 移动端菜单切换
function toggleMenu() {
    var nav = document.getElementById('mainNav');
    if (nav) {
        nav.classList.toggle('show');
    }
}
// 点击菜单项后自动收起（移动端）
document.querySelectorAll('#mainNav a').forEach(function(link) {
    link.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
            document.getElementById('mainNav').classList.remove('show');
        }
    });
});
</script>