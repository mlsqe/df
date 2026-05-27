<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) { header('Location: login.php'); exit; }

$user = getCurrentUser();
$role = $user['role'] ?? 'user';

// 判断打手身份（仅根据 role 字段）
$isBooster = ($role === 'booster');
$isPendingBooster = ($role === 'pending_booster');
$isAdmin = in_array($role, ['admin', 'super_admin']);
$isSuperAdmin = ($role === 'super_admin');

// 从数据库读取模块权限配置（所有角色均依赖数据库，包括超级管理员）
$db = getDB();
$permRow = $db->query("SELECT cfg_value FROM system_config WHERE cfg_key='module_permissions'")->fetch();
$modulePermissions = $permRow ? json_decode($permRow['cfg_value'], true) : [];
$visibleModules = $modulePermissions[$role] ?? [];

// 确保仪表盘始终可见
if (!in_array('dashboard', $visibleModules)) $visibleModules[] = 'dashboard';

// 导航栏颜色
$navbarColor = '#1E9FFF';
if ($isAdmin) $navbarColor = '#FF5722';
elseif ($isBooster || $isPendingBooster) $navbarColor = '#5FB878';

// 侧边栏模块名称字典
$moduleDict = [
    'dashboard'       => '📊 仪表板',
    'my_profile'      => '👤 个人主页',
    'my_orders'       => '📦 我的订单',
    'history_orders'  => '📜 历史订单',
    'new_order'       => '➕ 发布订单',
    'booster_invites' => '🎟️ 接单邀请码',
    'booster_orders'  => '⚡ 可接订单',
    'booster_progress'=> '🎮 我的代练',
    'admin_orders'    => '🛒 订单管理',
    'admin_boosters'  => '🔍 打手审核',
    'admin_disputes'  => '⚖️ 申诉仲裁',
    'admin_types'     => '🏷️ 代练类型',
    'admin_invites'   => '🎫 邀请码管理',
    'admin_levels'    => '📈 打手等级',
    'admin_settings'  => '⚙️ 系统设置',
    'admin_users'     => '👥 用户管理',
    'admin_admins'    => '👑 管理员管理',
    'admin_roles'     => '🔐 身份模块',
];

// 辅助函数
function checkModuleVisible($moduleId, $visibleModules) {
    if ($moduleId === 'dashboard') return true;
    return in_array($moduleId, $visibleModules);
}
function includeModulePanel($moduleId, $visibleModules, $customContent = null) {
    if (!checkModuleVisible($moduleId, $visibleModules)) return;
    $moduleFile = __DIR__ . '/includes/modules/' . $moduleId . '.php';
    if (file_exists($moduleFile)) {
        $active = ($moduleId == 'dashboard') ? ' active' : '';
        echo '<div class="module-panel'.$active.'" id="panel_'.$moduleId.'">';
        if ($customContent !== null) {
            echo $customContent;
        } else {
            ob_start();
            include $moduleFile;
            $content = ob_get_clean();
            echo $content;
        }
        echo '</div>';
    } else {
        echo '<div class="module-panel" id="panel_'.$moduleId.'"><div class="layui-alert layui-alert-danger">模块文件缺失：'.htmlspecialchars($moduleFile).'</div></div>';
    }
}

// 预备打手审核提示
$pendingContent = '<div style="text-align:center; padding:60px 20px; color:#999;">';
$pendingContent .= '<i class="layui-icon layui-icon-about" style="font-size:60px;"></i>';
$pendingContent .= '<p style="margin-top:20px; font-size:16px;">您的打手申请正在审核中，请耐心等待。</p>';
$pendingContent .= '</div>';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title><?= SITE_NAME ?> - 工作台</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/layui@2.8.0/dist/css/layui.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <style>
        .layui-header { background-color: <?= $navbarColor ?> !important; display: flex; align-items: center; justify-content: space-between; padding: 0 15px; }
        .layui-header a.logo-link { font-size: 1.2rem; color: #fff; text-decoration: none; white-space: nowrap; display: flex; align-items: center; }
        .layui-side { background: #2a2f3f; transition: all .3s; }
        .layui-side .layui-nav { background: transparent; }
        .layui-side .layui-nav .layui-nav-item a { color: #c0c4cc; }
        .layui-side .layui-nav .layui-nav-item.layui-this a { background: #1E9FFF; color: #fff; }

        /* 关键修复：底部留出版权栏高度 */
        .layui-body { left: 220px; right: 0; top: 60px; bottom: 44px; /* 44px 是 footer 高度 */ padding: 15px; overflow-y: auto; transition: all .3s; }
        .layui-footer { position: fixed; bottom: 0; width: 100%; height: 44px; line-height: 44px; text-align: center; background: #f2f3f5; z-index: 1000; }

        .module-panel { display: none; }
        .module-panel.active { display: block; }
        #wsStatus { font-size: 11px; color: #fff; background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 4px; margin-left: 8px; display: inline-block; }
        .mobile-toggle-btn { display: none; color: #fff; font-size: 20px; cursor: pointer; margin-right: 15px; }

        /* 移动端适配 */
        @media screen and (max-width: 991px) {
            .layui-side { transform: translate3d(-200px, 0, 0); width: 200px !important; z-index: 1001; }
            .layui-body { left: 0 !important; }
            .layui-footer { left: 0 !important; }
            .mobile-toggle-btn { display: inline-block; }
            .layui-side-opened .layui-side { transform: translate3d(0, 0, 0); }
            .layui-side-opened .site-mobile-shade { content: ''; position: fixed; top: 0; bottom: 0; left: 0; right: 0; background-color: rgba(0,0,0,.5); z-index: 1000; }
            .layui-header a.logo-link { font-size: 1.05rem; }
            .layui-body { padding: 10px; }
        }
    </style>
    <script>
    function getStatusText(status, payment_status) {
        if (status === 'cancelled') return '已取消';
        if (status === 'disputed') return '争议中';
        if (status === 'completed') return '已完成';
        if (payment_status === 'unpaid') return '待付款';
        const map = { 'pending': '待接单', 'accepted': '已接单', 'in_progress': '代练中' };
        return map[status] || status;
    }
    function getStatusClass(status, payment_status) {
        if (status === 'cancelled') return 'layui-bg-black';
        if (status === 'disputed') return 'layui-bg-red';
        if (status === 'completed') return 'layui-bg-gray';
        if (payment_status === 'unpaid') return 'layui-bg-orange';
        const map = { 'pending': 'layui-bg-cyan', 'accepted': 'layui-bg-blue', 'in_progress': 'layui-bg-green' };
        return map[status] || 'layui-bg-cyan';
    }
    function formatTime(datetime) {
        if (!datetime) return '-';
        if (typeof datetime === 'string' && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(datetime)) datetime = datetime.replace(' ', 'T') + 'Z';
        var d = new Date(datetime);
        if (isNaN(d.getTime())) return '-';
        var y = d.getFullYear(), m = String(d.getMonth()+1).padStart(2,'0'), day = String(d.getDate()).padStart(2,'0');
        var h = String(d.getHours()).padStart(2,'0'), min = String(d.getMinutes()).padStart(2,'0'), s = String(d.getSeconds()).padStart(2,'0');
        return y + '-' + m + '-' + day + ' ' + h + ':' + min + ':' + s;
    }
    var _moduleInited = {};
    function switchModule(moduleId) {
        if (!moduleId) return;
        var panel = document.getElementById('panel_' + moduleId);
        if (!panel) return;
        $('.module-panel').removeClass('active');
        $(panel).addClass('active');
        $('#workMenu .layui-this').removeClass('layui-this');
        $('#workMenu dd[data-id="' + moduleId + '"]').addClass('layui-this');
        $('#workMenu dd[data-id="' + moduleId + '"]').parents('li.layui-nav-item').addClass('layui-nav-itemed');
        $('body').removeClass('layui-side-opened');
        if (!_moduleInited[moduleId]) {
            var funcName = 'init' + moduleId.replace(/_/g, '').replace(/^(.)/, function(m, p){ return p.toUpperCase(); }) + 'Module';
            if (typeof window[funcName] === 'function') { window[funcName](); _moduleInited[moduleId] = true; }
        }
    }
    window.logout = window.logout || function() {
        fetch('api/auth.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'logout'})})
        .then(r => r.json()).then(() => { window.location.href = 'login.php'; })
        .catch(() => { window.location.href = 'login.php'; });
    };
    </script>
</head>
<body>
<script>var isSuperAdmin = <?= $isSuperAdmin ? 'true' : 'false' ?>;</script>
<script>var currentUserId = <?= $user['id'] ?>;</script>

<div class="layui-layout layui-layout-admin">
    <div class="layui-header topbar">
        <div style="display: flex; align-items: center;">
            <i class="layui-icon layui-icon-spread-left mobile-toggle-btn" id="mobileToggle"></i>
            <a href="index.php" class="logo-link">
                🔺 <?= SITE_NAME ?>
                <span style="background:#fff; color:<?= $navbarColor ?>; padding:1px 6px; border-radius:3px; margin-left:6px; font-size: 0.8rem; font-weight: bold;">代练</span> 
                <span id="wsStatus">未连接</span>
            </a>
        </div>
    </div>

    <div class="site-mobile-shade" id="mobileShade"></div>

    <div class="layui-side layui-side-menu">
        <div class="layui-side-scroll">
            <ul class="layui-nav layui-nav-tree" id="workMenu">
                <li class="layui-nav-item layui-nav-itemed">
                    <a href="javascript:;">🏠 个人中心</a>
                    <dl class="layui-nav-child">
                        <dd data-id="dashboard" class="layui-this"><a href="javascript:void(0);" onclick="switchModule('dashboard')"><?= $moduleDict['dashboard'] ?></a></dd>
                        <?php if (checkModuleVisible('my_profile', $visibleModules)): ?><dd data-id="my_profile"><a href="javascript:void(0);" onclick="switchModule('my_profile')"><?= $moduleDict['my_profile'] ?></a></dd><?php endif; ?>
                        <?php if (checkModuleVisible('my_orders', $visibleModules)): ?><dd data-id="my_orders"><a href="javascript:void(0);" onclick="switchModule('my_orders')"><?= $moduleDict['my_orders'] ?></a></dd><?php endif; ?>
                        <?php if (checkModuleVisible('history_orders', $visibleModules)): ?><dd data-id="history_orders"><a href="javascript:void(0);" onclick="switchModule('history_orders')"><?= $moduleDict['history_orders'] ?></a></dd><?php endif; ?>
                        <?php if (checkModuleVisible('booster_invites', $visibleModules)): ?><dd data-id="booster_invites"><a href="javascript:void(0);" onclick="switchModule('booster_invites')"><?= $moduleDict['booster_invites'] ?></a></dd><?php endif; ?>
                    </dl>
                </li>
                <li class="layui-nav-item layui-nav-itemed">
                    <a href="javascript:;">⚡ 代练业务</a>
                    <dl class="layui-nav-child">
                        <?php if (checkModuleVisible('new_order', $visibleModules)): ?><dd data-id="new_order"><a href="javascript:void(0);" onclick="switchModule('new_order')"><?= $moduleDict['new_order'] ?></a></dd><?php endif; ?>
                        <?php if (checkModuleVisible('booster_orders', $visibleModules)): ?><dd data-id="booster_orders"><a href="javascript:void(0);" onclick="switchModule('booster_orders')"><?= $moduleDict['booster_orders'] ?></a></dd><?php endif; ?>
                        <?php if (checkModuleVisible('booster_progress', $visibleModules)): ?><dd data-id="booster_progress"><a href="javascript:void(0);" onclick="switchModule('booster_progress')"><?= $moduleDict['booster_progress'] ?></a></dd><?php endif; ?>
                    </dl>
                </li>
                <?php if ($isAdmin): ?>
                <li class="layui-nav-item layui-nav-itemed">
                    <a href="javascript:;">⚙️ 管理功能</a>
                    <dl class="layui-nav-child">
                        <?php if (checkModuleVisible('admin_orders', $visibleModules)): ?><dd data-id="admin_orders"><a href="javascript:void(0);" onclick="switchModule('admin_orders')"><?= $moduleDict['admin_orders'] ?></a></dd><?php endif; ?>
                        <?php if (checkModuleVisible('admin_boosters', $visibleModules)): ?><dd data-id="admin_boosters"><a href="javascript:void(0);" onclick="switchModule('admin_boosters')"><?= $moduleDict['admin_boosters'] ?></a></dd><?php endif; ?>
                        <?php if (checkModuleVisible('admin_disputes', $visibleModules)): ?><dd data-id="admin_disputes"><a href="javascript:void(0);" onclick="switchModule('admin_disputes')"><?= $moduleDict['admin_disputes'] ?></a></dd><?php endif; ?>
                        <?php if (checkModuleVisible('admin_types', $visibleModules)): ?><dd data-id="admin_types"><a href="javascript:void(0);" onclick="switchModule('admin_types')"><?= $moduleDict['admin_types'] ?></a></dd><?php endif; ?>
                        <?php if (checkModuleVisible('admin_invites', $visibleModules)): ?><dd data-id="admin_invites"><a href="javascript:void(0);" onclick="switchModule('admin_invites')"><?= $moduleDict['admin_invites'] ?></a></dd><?php endif; ?>
                        <?php if (checkModuleVisible('admin_levels', $visibleModules)): ?><dd data-id="admin_levels"><a href="javascript:void(0);" onclick="switchModule('admin_levels')"><?= $moduleDict['admin_levels'] ?></a></dd><?php endif; ?>
                        <?php if (checkModuleVisible('admin_settings', $visibleModules)): ?><dd data-id="admin_settings"><a href="javascript:void(0);" onclick="switchModule('admin_settings')"><?= $moduleDict['admin_settings'] ?></a></dd><?php endif; ?>
                        <?php if (checkModuleVisible('admin_users', $visibleModules)): ?><dd data-id="admin_users"><a href="javascript:void(0);" onclick="switchModule('admin_users')"><?= $moduleDict['admin_users'] ?></a></dd><?php endif; ?>
                        <?php if (checkModuleVisible('admin_admins', $visibleModules)): ?><dd data-id="admin_admins"><a href="javascript:void(0);" onclick="switchModule('admin_admins')"><?= $moduleDict['admin_admins'] ?></a></dd><?php endif; ?>
                        <?php if (checkModuleVisible('admin_roles', $visibleModules)): ?><dd data-id="admin_roles"><a href="javascript:void(0);" onclick="switchModule('admin_roles')"><?= $moduleDict['admin_roles'] ?></a></dd><?php endif; ?>
                    </dl>
                </li>
                <?php endif; ?>
                <li class="layui-nav-item"><a href="javascript:window.logout();" style="color:#FF5722 !important;"><i class="layui-icon layui-icon-logout"></i> 退出登录</a></li>
            </ul>
        </div>
    </div>

    <div class="layui-body">
        <?php
        includeModulePanel('dashboard', $visibleModules);
        includeModulePanel('my_profile', $visibleModules);
        includeModulePanel('my_orders', $visibleModules);
        includeModulePanel('history_orders', $visibleModules);
        includeModulePanel('new_order', $visibleModules);
        includeModulePanel('booster_orders', $visibleModules, $isPendingBooster ? $pendingContent : null);
        includeModulePanel('booster_progress', $visibleModules, $isPendingBooster ? $pendingContent : null);
        includeModulePanel('booster_invites', $visibleModules, $isPendingBooster ? $pendingContent : null);
        includeModulePanel('admin_orders', $visibleModules);
        includeModulePanel('admin_boosters', $visibleModules);
        includeModulePanel('admin_disputes', $visibleModules);
        includeModulePanel('admin_types', $visibleModules);
        includeModulePanel('admin_invites', $visibleModules);
        includeModulePanel('admin_levels', $visibleModules);
        includeModulePanel('admin_settings', $visibleModules);
        includeModulePanel('admin_users', $visibleModules);
        includeModulePanel('admin_admins', $visibleModules);
        includeModulePanel('admin_roles', $visibleModules);
        ?>
    </div>

    <div class="layui-footer">© <?= date('Y') ?> <?= SITE_NAME ?> | 安全交易·担保赔付</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/layui@2.8.0/dist/layui.js"></script>
<script src="js/main.js"></script>
<script>
$(function(){ 
    switchModule('dashboard'); 
    $('#mobileToggle').on('click', function() {
        $('body').toggleClass('layui-side-opened');
    });
    $('#mobileShade').on('click', function() {
        $('body').removeClass('layui-side-opened');
    });
});
</script>
</body>
</html>