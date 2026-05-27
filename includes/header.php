<?php
// includes/header.php - 页面公共头部 & 侧边栏
if (!defined('IN_APP')) exit;

$user_id = get_user_id();
$role = get_user_role();
$db = getDB();

// 获取系统设置（站点名称、模块权限）
$cfg = $db->query("SELECT cfg_key, cfg_value FROM system_config WHERE cfg_key IN ('site_name','module_permissions')")->fetchAll(PDO::FETCH_KEY_PAIR);
$site_name = $cfg['site_name'] ?? '俊俊电竞';
$module_perms = json_decode($cfg['module_permissions'] ?? '{}', true);
$allowed_modules = $module_perms[$role] ?? [];

// 当前激活的模块（用于高亮菜单）
$current_module = $_GET['module'] ?? 'dashboard'; // 默认仪表板
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_name) ?> - 控制台</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/layui@2.8.0/dist/css/layui.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
</head>
<body class="layui-layout-body">
<div class="layui-layout layui-layout-admin">
    <!-- 顶部栏 -->
    <div class="layui-header">
        <div class="layui-logo layui-hide-xs"><?= htmlspecialchars($site_name) ?></div>
        <ul class="layui-nav layui-layout-right">
            <li class="layui-nav-item">
                <a href="javascript:;"><img src="<?= get_avatar($user_id) ?>" class="layui-nav-img"><?= htmlspecialchars(get_username($user_id)) ?></a>
                <dl class="layui-nav-child">
                    <dd><a href="?module=dashboard">仪表板</a></dd>
                    <dd><a href="api/auth.php?action=logout">退出</a></dd>
                </dl>
            </li>
        </ul>
    </div>

    <!-- 侧边栏 -->
    <div class="layui-side layui-side-menu">
        <div class="layui-side-scroll">
            <ul class="layui-nav layui-nav-tree layui-inline" lay-filter="side-menu">
                <!--
                ================================
                菜单生成循环（关键修改区）
                ================================
                -->
                <?php foreach ($allowed_modules as $mod):
                    // === 修改点 1：跳过 my_profile，不显示在菜单中 ===
                    if ($mod === 'my_profile') continue;

                    // 菜单名称映射（根据模块自定义中文名）
                    $menu_name = match($mod) {
                        'dashboard'         => '仪表板',
                        'new_order'         => '新建订单',
                        'my_orders'         => '我的订单',
                        'history_orders'    => '历史订单',
                        'booster_orders'    => '打手订单',
                        'booster_progress'  => '订单进度',
                        'booster_invites'   => '邀请码',
                        'admin_orders'      => '订单管理',
                        'admin_boosters'    => '打手审核',
                        'admin_disputes'    => '争议处理',
                        'admin_types'       => '游戏类型',
                        'admin_invites'     => '邀请码管理',
                        'admin_levels'      => '打手等级',
                        'admin_settings'    => '系统设置',
                        'admin_admins'      => '管理员',
                        'admin_users'       => '用户管理',
                        'admin_payment'     => '支付渠道',  // 新增
                        'admin_pay_orders'  => '支付订单',  // 新增
                        default => $mod
                    };
                    $active = ($current_module === $mod) ? 'layui-this' : '';
                ?>
                <li class="layui-nav-item <?= $active ?>">
                    <a href="?module=<?= $mod ?>"><?= $menu_name ?></a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- 主内容区 -->
    <div class="layui-body">
        <div class="layui-main" style="padding:15px;">
            <?php
            // 加载对应模块文件
            $module_file = __DIR__ . '/modules/' . $current_module . '.php';
            if (in_array($current_module, $allowed_modules) && file_exists($module_file)) {
                include $module_file;
            } elseif ($current_module === 'my_profile') {
                // 兼容旧链接，直接重定向到仪表板
                header('Location: ?module=dashboard');
                exit;
            } else {
                echo '<div class="layui-card"><div class="layui-card-body">模块不存在或无权限</div></div>';
            }
            ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/layui@2.8.0/dist/layui.js"></script>
<script>
layui.use(['element', 'layer'], function() {
    var element = layui.element;
});
</script>
</body>
</html>