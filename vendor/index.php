<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) { header('Location: login.php'); exit; }

$user = getCurrentUser();
$role = $user['role'] ?? 'user';

// 判断是否为打手
$isBooster = false;
if ($role === 'user' || $role === 'booster') {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM boosters WHERE user_id=? AND status='active'");
    $stmt->execute([$user['id']]);
    if ($stmt->fetch()) $isBooster = true;
}

$isAdmin = in_array($role, ['admin', 'super_admin']);
$isSuperAdmin = ($role === 'super_admin');

// 读取模块权限配置（仅用于其他模块，发布订单已硬编码，不受影响）
$db = getDB();
$permRow = $db->query("SELECT cfg_value FROM system_config WHERE cfg_key='module_permissions'")->fetch();
$modulePermissions = $permRow ? json_decode($permRow['cfg_value'], true) : [];
$visibleModules = $modulePermissions[$role] ?? [];

// 导航栏颜色
$navbarColor = '#1E9FFF';
if ($isAdmin) $navbarColor = '#FF5722';
elseif ($isBooster) $navbarColor = '#5FB878';

// 辅助函数
function checkModuleVisible($moduleId, $visibleModules, $isSuperAdmin, $role) {
    if ($isSuperAdmin) return true;
    if (in_array($role, ['admin'])) return in_array($moduleId, $visibleModules ?? []);
    return in_array($moduleId, $visibleModules ?? []);
}
function includeModulePanel($moduleId, $visibleModules, $isSuperAdmin, $role) {
    if (!checkModuleVisible($moduleId, $visibleModules, $isSuperAdmin, $role)) return;
    $moduleFile = __DIR__ . '/includes/modules/' . $moduleId . '.php';
    if (file_exists($moduleFile)) {
        $active = ($moduleId == 'my_profile') ? ' active' : '';
        echo '<div class="module-panel'.$active.'" id="panel_'.$moduleId.'">';
        include $moduleFile;
        echo '</div>';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= SITE_NAME ?> - 工作台</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/layui@2.8.0/dist/css/layui.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <style>
        .layui-header { background-color: <?= $navbarColor ?> !important; }
        .layui-header .layui-logo { color: #fff; font-size: 1.3rem; padding: 0 20px; }
        .layui-side { background: #2a2f3f; }
        .layui-side .layui-nav { background: transparent; }
        .layui-side .layui-nav .layui-nav-item a { color: #c0c4cc; }
        .layui-side .layui-nav .layui-nav-item.layui-this a { background: #1E9FFF; color: #fff; }
        .layui-body { padding: 16px; }
        .module-panel { display: none; }
        .module-panel.active { display: block; }
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
<script>var isSuperAdmin = <?= $isSuperAdmin ? 'true' : 'false' ?>;</script>
<div class="layui-layout layui-layout-admin">
<!-- 顶部导航 --> <div class="layui-header topbar"> <br> <a href="index.php" style="font-size:1.3rem; color:#fff; text-decoration:none; white-space:nowrap;"> 🔺 <?= SITE_NAME ?><span style="background:#fff; color:<?= $navbarColor ?>; padding:2px 8px; border-radius:3px; margin-left:8px;">代练</span> <span id="wsStatus" style="font-size:12px; color:#fff; background:rgba(255,255,255,0.2); padding:2px 6px; border-radius:4px; margin-left:8px; transition:0.3s;">未连接</span> </a> </div>

    <!-- 侧边栏菜单 -->
    <div class="layui-side layui-side-menu">
        <div class="layui-side-scroll">
            <ul class="layui-nav layui-nav-tree" id="workMenu">
                <!-- 个人中心组 -->
                <li class="layui-nav-item layui-nav-itemed">
                    <a href="javascript:;">🏠 个人中心</a>
                    <dl class="layui-nav-child">
                        <?php if (checkModuleVisible('my_profile', $visibleModules, $isSuperAdmin, $role)): ?>
                        <dd data-id="my_profile" class="layui-this"><a href="javascript:void(0);" onclick="switchModule('my_profile')">个人主页</a></dd>
                        <?php endif; ?>
                        <?php if (checkModuleVisible('my_orders', $visibleModules, $isSuperAdmin, $role)): ?>
                        <dd data-id="my_orders"><a href="javascript:void(0);" onclick="switchModule('my_orders')">我的订单</a></dd>
                        <?php endif; ?>
                        <?php if (checkModuleVisible('history_orders', $visibleModules, $isSuperAdmin, $role)): ?>
                        <dd data-id="history_orders"><a href="javascript:void(0);" onclick="switchModule('history_orders')">历史订单</a></dd>
                        <?php endif; ?>
                    </dl>
                </li>

                <!-- 代练业务组（硬编码发布订单，所有角色可见） -->
                <li class="layui-nav-item layui-nav-itemed">
                    <a href="javascript:;">⚡ 代练业务</a>
                    <dl class="layui-nav-child">
                        <dd data-id="new_order"><a href="javascript:void(0);" onclick="switchModule('new_order')">发布订单</a></dd>
                        <?php if ($isBooster && checkModuleVisible('booster_orders', $visibleModules, $isSuperAdmin, $role)): ?>
                        <dd data-id="booster_orders"><a href="javascript:void(0);" onclick="switchModule('booster_orders')">可接订单</a></dd>
                        <?php endif; ?>
                        <?php if ($isBooster && checkModuleVisible('booster_progress', $visibleModules, $isSuperAdmin, $role)): ?>
                        <dd data-id="booster_progress"><a href="javascript:void(0);" onclick="switchModule('booster_progress')">我的代练</a></dd>
                        <?php endif; ?>
                        <?php if ($isBooster && checkModuleVisible('booster_invites', $visibleModules, $isSuperAdmin, $role)): ?>
                        <dd data-id="booster_invites"><a href="javascript:void(0);" onclick="switchModule('booster_invites')">邀请码</a></dd>
                        <?php endif; ?>
                    </dl>
                </li>

                <!-- 管理功能组（仅管理员） -->
                <?php if ($isAdmin): ?>
                <li class="layui-nav-item layui-nav-itemed">
                    <a href="javascript:;">⚙️ 管理功能</a>
                    <dl class="layui-nav-child">
                        <?php if (checkModuleVisible('admin_orders', $visibleModules, $isSuperAdmin, $role)): ?>
                        <dd data-id="admin_orders"><a href="javascript:void(0);" onclick="switchModule('admin_orders')">订单管理</a></dd>
                        <?php endif; ?>
                        <?php if (checkModuleVisible('admin_boosters', $visibleModules, $isSuperAdmin, $role)): ?>
                        <dd data-id="admin_boosters"><a href="javascript:void(0);" onclick="switchModule('admin_boosters')">打手审核</a></dd>
                        <?php endif; ?>
                        <?php if (checkModuleVisible('admin_disputes', $visibleModules, $isSuperAdmin, $role)): ?>
                        <dd data-id="admin_disputes"><a href="javascript:void(0);" onclick="switchModule('admin_disputes')">申诉仲裁</a></dd>
                        <?php endif; ?>
                        <?php if (checkModuleVisible('admin_types', $visibleModules, $isSuperAdmin, $role)): ?>
                        <dd data-id="admin_types"><a href="javascript:void(0);" onclick="switchModule('admin_types')">代练类型</a></dd>
                        <?php endif; ?>
                        <?php if (checkModuleVisible('admin_invites', $visibleModules, $isSuperAdmin, $role)): ?>
                        <dd data-id="admin_invites"><a href="javascript:void(0);" onclick="switchModule('admin_invites')">邀请码管理</a></dd>
                        <?php endif; ?>
                        <?php if (checkModuleVisible('admin_levels', $visibleModules, $isSuperAdmin, $role)): ?>
                        <dd data-id="admin_levels"><a href="javascript:void(0);" onclick="switchModule('admin_levels')">打手等级</a></dd>
                        <?php endif; ?>
                        <?php if (checkModuleVisible('admin_settings', $visibleModules, $isSuperAdmin, $role)): ?>
                        <dd data-id="admin_settings"><a href="javascript:void(0);" onclick="switchModule('admin_settings')">系统设置</a></dd>
                        <?php endif; ?>
                        <?php if (checkModuleVisible('admin_users', $visibleModules, $isSuperAdmin, $role)): ?>
                        <dd data-id="admin_users"><a href="javascript:void(0);" onclick="switchModule('admin_users')">用户管理</a></dd>
                        <?php endif; ?>
                        <?php if ($isSuperAdmin && checkModuleVisible('admin_admins', $visibleModules, $isSuperAdmin, $role)): ?>
                        <dd data-id="admin_admins"><a href="javascript:void(0);" onclick="switchModule('admin_admins')">管理员</a></dd>
                        <?php endif; ?>
                    </dl>
                </li>
                <?php endif; ?>

                <!-- 退出登录 -->
                <li class="layui-nav-item">
                    <a href="javascript:window.logout();" style="color:#FF5722 !important;">
                        <i class="layui-icon layui-icon-logout"></i> 退出登录
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- 右侧内容区 -->
    <div class="layui-body">
        <?php
        includeModulePanel('my_profile', $visibleModules, $isSuperAdmin, $role);
        includeModulePanel('my_orders', $visibleModules, $isSuperAdmin, $role);
        includeModulePanel('history_orders', $visibleModules, $isSuperAdmin, $role);
        // 发布订单面板无条件加载
        ?>
        <div class="module-panel" id="panel_new_order">
            <?php include __DIR__ . '/includes/modules/new_order.php'; ?>
        </div>
        <?php
        if ($isBooster) {
            includeModulePanel('booster_orders', $visibleModules, $isSuperAdmin, $role);
            includeModulePanel('booster_progress', $visibleModules, $isSuperAdmin, $role);
            includeModulePanel('booster_invites', $visibleModules, $isSuperAdmin, $role);
        }
        if ($isAdmin) {
            includeModulePanel('admin_orders', $visibleModules, $isSuperAdmin, $role);
            includeModulePanel('admin_boosters', $visibleModules, $isSuperAdmin, $role);
            includeModulePanel('admin_disputes', $visibleModules, $isSuperAdmin, $role);
            includeModulePanel('admin_types', $visibleModules, $isSuperAdmin, $role);
            includeModulePanel('admin_invites', $visibleModules, $isSuperAdmin, $role);
            includeModulePanel('admin_levels', $visibleModules, $isSuperAdmin, $role);
            includeModulePanel('admin_settings', $visibleModules, $isSuperAdmin, $role);
            includeModulePanel('admin_users', $visibleModules, $isSuperAdmin, $role);
            if ($isSuperAdmin) {
                includeModulePanel('admin_admins', $visibleModules, $isSuperAdmin, $role);
            }
        }
        ?>
    </div>

    <div class="layui-footer" style="text-align:center;">© <?= date('Y') ?> <?= SITE_NAME ?> | 安全交易·担保赔付</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/layui@2.8.0/dist/layui.js"></script>
<script src="js/main.js"></script>
<script>
var _moduleInited = {};

function switchModule(moduleId) {
    if (!moduleId) return;
    var panelId = 'panel_' + moduleId;
    $('.module-panel').removeClass('active');
    $('#' + panelId).addClass('active');

    $('#workMenu .layui-this').removeClass('layui-this');
    $('#workMenu dd[data-id="' + moduleId + '"]').addClass('layui-this');
    $('#workMenu dd[data-id="' + moduleId + '"]').parents('li.layui-nav-item').addClass('layui-nav-itemed');

    if (!_moduleInited[moduleId]) {
        var funcName = 'init' + moduleId.replace(/_/g, '').replace(/^(.)/, function(m, p){ return p.toUpperCase(); }) + 'Module';
        if (typeof window[funcName] === 'function') {
            window[funcName]();
            _moduleInited[moduleId] = true;
        }
    }
}

$(function(){
    switchModule('my_profile');
});
</script>
</body>
</html>