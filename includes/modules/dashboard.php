<?php
// includes/modules/dashboard.php

$currentUser = getCurrentUser();
$user_id = $currentUser['id'];
$role = $currentUser['role'] ?? 'user';
$db = getDB();

$user = $db->prepare("SELECT username, balance FROM users WHERE id=?");
$user->execute([$user_id]);
$user = $user->fetch();

// ========== 根据角色查询统计数据 ==========
if ($role == 'user') {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM orders WHERE user_id=? AND status NOT IN ('cancelled','disputed') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute([$user_id]);
    $totalSpent = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id=? AND status IN ('pending','accepted','in_progress')");
    $stmt->execute([$user_id]);
    $pendingOrders = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM orders WHERE user_id=? GROUP BY status");
    $stmt->execute([$user_id]);
    $statusData = $stmt->fetchAll();
} elseif ($role == 'booster') {
    $booster = $db->prepare("SELECT b.*, bl.name as level_name FROM boosters b LEFT JOIN booster_levels bl ON b.level_id=bl.id WHERE b.user_id=?");
    $booster->execute([$user_id]);
    $booster = $booster->fetch();
    $boosterId = $booster ? $booster['id'] : 0;

    $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE booster_id=? AND status='completed' AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute([$boosterId]);
    $totalCompleted = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE booster_id=? AND status IN ('accepted','in_progress')");
    $stmt->execute([$boosterId]);
    $activeOrders = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM orders WHERE booster_id=? GROUP BY status");
    $stmt->execute([$boosterId]);
    $statusData = $stmt->fetchAll();
} elseif ($role == 'admin' || $role == 'super_admin') {
    $totalUsers = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
    $totalBoosters = $db->query("SELECT COUNT(*) FROM users WHERE role IN ('booster','pending_booster')")->fetchColumn();
    $totalOrders = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $totalRevenue = $db->query("SELECT COALESCE(SUM(amount),0) FROM orders WHERE status='completed'")->fetchColumn();
    $pendingBoosters = $db->query("SELECT COUNT(*) FROM boosters WHERE status='pending'")->fetchColumn();
    $latestOrders = $db->query("SELECT o.order_no, o.amount, o.status, o.payment_status, o.created_at, u.username FROM orders o LEFT JOIN users u ON o.user_id=u.id ORDER BY o.id DESC LIMIT 8")->fetchAll();
}

$statusClasses = [
    'pending'     => 'orange',
    'accepted'    => 'blue',
    'in_progress' => 'cyan',
    'completed'   => 'green',
    'cancelled'   => 'gray',
    'disputed'    => 'red'
];
$statusTexts = [
    'pending'     => '待接单',
    'accepted'    => '已接单',
    'in_progress' => '代练中',
    'completed'   => '已完成',
    'cancelled'   => '已取消',
    'disputed'    => '争议中'
];
?>
<style>
    @media screen and (max-width: 768px) {
        .welcome-block { text-align: center; }
        .welcome-block span { float: none !important; display: block; margin-top: 5px; font-size: 0.9em; }
        .layui-card-body h2 { font-size: 1.8rem !important; }
    }
    .table-responsive-wrapper {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .table-responsive-wrapper table {
        white-space: nowrap;
    }
</style>

<div class="layui-fluid" style="padding: 0;">
    <div class="layui-row">
        <div class="layui-col-xs12">
            <div class="layui-card">
                <div class="layui-card-body welcome-block" style="font-size:1.1em; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:#fff; border-radius:8px; padding: 15px;">
                    欢迎回来，<?php echo htmlspecialchars($user['username'] ?? '未知用户'); ?>！
                    <?php if($role != 'admin' && $role != 'super_admin'): ?>
                        <span style="float:right;">余额：¥<?php echo number_format($user['balance'] ?? 0, 2); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <hr class="layui-bg-gray">

    <?php if ($role == 'user'): ?>
    <!-- 用户仪表盘 -->
    <div class="layui-row layui-col-space15">
        <div class="layui-col-xs12 layui-col-sm6 layui-col-md4">
            <div class="layui-card">
                <div class="layui-card-header"><i class="layui-icon layui-icon-flag"></i> 待处理订单</div>
                <div class="layui-card-body" style="text-align:center; font-size:2.5em; font-weight: bold; color:#FF5722; padding: 20px 0;"><?php echo $pendingOrders; ?></div>
            </div>
        </div>
        <div class="layui-col-xs12 layui-col-sm6 layui-col-md4">
            <div class="layui-card">
                <div class="layui-card-header"><i class="layui-icon layui-icon-rmb"></i> 近30天消费</div>
                <div class="layui-card-body" style="text-align:center; font-size:2.5em; font-weight: bold; color:#16b777; padding: 20px 0;">¥<?php echo number_format($totalSpent,2); ?></div>
            </div>
        </div>
        <div class="layui-col-xs12 layui-col-md4">
            <div class="layui-card">
                <div class="layui-card-header"><i class="layui-icon layui-icon-chart"></i> 订单状态</div>
                <div class="layui-card-body" style="height: 220px; position: relative;">
                    <canvas id="userStatusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script>
    (function(){
        var ctx = document.getElementById('userStatusChart');
        if(!ctx) return;
        new Chart(ctx, {
            type: 'polarArea',
            data: {
                labels: [<?php echo implode(',', array_map(function($r){ return "'".$r['status']."'"; }, $statusData)); ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_column($statusData, 'cnt')); ?>],
                    backgroundColor: ['#FF6384','#36A2EB','#FFCE56','#4BC0C0','#9966FF','#FF9F40']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    })();
    </script>

    <?php elseif ($role == 'booster'): ?>
    <!-- 打手仪表盘 -->
    <div class="layui-row layui-col-space15">
        <div class="layui-col-xs12 layui-col-sm6">
            <div class="layui-card">
                <div class="layui-card-header"><i class="layui-icon layui-icon-flag"></i> 当前任务</div>
                <div class="layui-card-body" style="text-align:center; padding: 20px 0;">
                    <span style="font-size:2.5em; font-weight: bold; color:#1E9FFF;"><?php echo $activeOrders; ?></span>
                    <p style="color:#999; margin-top: 5px;">个代练进行中</p>
                </div>
            </div>
        </div>
        <div class="layui-col-xs12 layui-col-sm6">
            <div class="layui-card">
                <div class="layui-card-header"><i class="layui-icon layui-icon-ok-circle"></i> 本月完成</div>
                <div class="layui-card-body" style="text-align:center; padding: 20px 0;">
                    <span style="font-size:2.5em; font-weight: bold; color:#5FB878;"><?php echo $totalCompleted; ?></span>
                    <p style="color:#999; margin-top: 5px;">单</p>
                </div>
            </div>
        </div>
    </div>
    <div class="layui-row layui-col-space15" style="margin-top:5px;">
        <div class="layui-col-xs12">
            <div class="layui-card">
                <div class="layui-card-header"><i class="layui-icon layui-icon-chart"></i> 订单状态分布</div>
                <div class="layui-card-body" style="height: 240px; position: relative;">
                    <canvas id="boosterStatusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script>
    (function(){
        var ctx = document.getElementById('boosterStatusChart');
        if(!ctx) return;
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [<?php echo implode(',', array_map(function($r){ return "'".$r['status']."'"; }, $statusData)); ?>],
                datasets: [{
                    label: '数量',
                    data: [<?php echo implode(',', array_column($statusData, 'cnt')); ?>],
                    backgroundColor: '#1E9FFF'
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
    })();
    </script>

    <?php else: ?>
    <!-- 管理员仪表盘 -->
    <div class="layui-row layui-col-space10" id="dashboardCards">
        <div class="layui-col-xs6 layui-col-sm4 layui-col-md2">
            <div class="layui-card">
                <div class="layui-card-body" style="text-align:center; padding: 15px 10px;">
                    <i class="layui-icon layui-icon-user" style="font-size:1.8em; color:#1E9FFF;"></i>
                    <p style="color:#666; font-size:0.85rem; margin: 3px 0;">玩家总数</p>
                    <h2 class="dashboard-card-value" data-key="total_users" style="font-size: 1.5rem; font-weight: bold;"><?php echo $totalUsers; ?></h2>
                </div>
            </div>
        </div>
        <div class="layui-col-xs6 layui-col-sm4 layui-col-md2">
            <div class="layui-card">
                <div class="layui-card-body" style="text-align:center; padding: 15px 10px;">
                    <i class="layui-icon layui-icon-user" style="font-size:1.8em; color:#5FB878;"></i>
                    <p style="color:#666; font-size:0.85rem; margin: 3px 0;">打手总数</p>
                    <h2 class="dashboard-card-value" data-key="total_boosters" style="font-size: 1.5rem; font-weight: bold;"><?php echo $totalBoosters; ?></h2>
                </div>
            </div>
        </div>
        <div class="layui-col-xs6 layui-col-sm4 layui-col-md2">
            <div class="layui-card">
                <div class="layui-card-body" style="text-align:center; padding: 15px 10px;">
                    <i class="layui-icon layui-icon-flag" style="font-size:1.8em; color:#FF5722;"></i>
                    <p style="color:#666; font-size:0.85rem; margin: 3px 0;">订单总数</p>
                    <h2 class="dashboard-card-value" data-key="total_orders" style="font-size: 1.5rem; font-weight: bold;"><?php echo $totalOrders; ?></h2>
                </div>
            </div>
        </div>
        <div class="layui-col-xs6 layui-col-sm4 layui-col-md2">
            <div class="layui-card">
                <div class="layui-card-body" style="text-align:center; padding: 15px 10px;">
                    <i class="layui-icon layui-icon-rmb" style="font-size:1.8em; color:#16b777;"></i>
                    <p style="color:#666; font-size:0.85rem; margin: 3px 0;">平台收入</p>
                    <h2 class="dashboard-card-value" data-key="total_revenue" style="font-size: 1.4rem; font-weight: bold; white-space: nowrap;">¥<?php echo number_format($totalRevenue,0); ?></h2>
                </div>
            </div>
        </div>
        <div class="layui-col-xs6 layui-col-sm4 layui-col-md2">
            <div class="layui-card">
                <div class="layui-card-body" style="text-align:center; padding: 15px 10px;">
                    <i class="layui-icon layui-icon-about" style="font-size:1.8em; color:#FFB800;"></i>
                    <p style="color:#666; font-size:0.85rem; margin: 3px 0;">待审打手</p>
                    <h2 class="dashboard-card-value" data-key="pending_boosters" style="font-size: 1.5rem; font-weight: bold;"><?php echo $pendingBoosters; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="layui-row layui-col-space15" style="margin-top:5px;">
        <div class="layui-col-xs12">
            <div class="layui-card">
                <div class="layui-card-header"><i class="layui-icon layui-icon-list"></i> 最近订单</div>
                <div class="layui-card-body" style="padding: 10px;">
                    <div class="table-responsive-wrapper">
                        <table class="layui-table" lay-even style="margin: 0;">
                            <thead><tr><th>订单号</th><th>用户</th><th>金额</th><th>状态</th><th>时间</th></tr></thead>
                            <tbody>
                                <?php if (empty($latestOrders)): ?>
                                <tr><td colspan="5" style="text-align:center; color:#999;">暂无订单</td></tr>
                                <?php else: foreach ($latestOrders as $o): 
                                    $statusKey = $o['status'] ?? 'pending';
                                    $class = $statusClasses[$statusKey] ?? 'gray';
                                    $text  = $statusTexts[$statusKey] ?? '未知状态';
                                    if (($o['payment_status'] ?? '') === 'unpaid' && $statusKey !== 'cancelled') {
                                        $class = 'orange'; $text .= ' (待支付)';
                                    }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($o['order_no']); ?></td>
                                    <td><?php echo htmlspecialchars($o['username'] ?? '已注销用户'); ?></td>
                                    <td>¥<?php echo number_format($o['amount'],2); ?></td>
                                    <td><span class="layui-badge layui-bg-<?php echo $class; ?>"><?php echo $text; ?></span></td>
                                    <td><?php echo date('m-d H:i', strtotime($o['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
// ========== 管理员仪表盘实时更新（仅更新卡片，不触及 layui.table）==========
window.onDashboardReload = function(msg) {
    // 只负责刷新统计卡片
    if (typeof fetchDashboardStats === 'function') {
        fetchDashboardStats();
    }
    // 不再尝试 reload 任何表格，避免 “table instance not found” 错误
};

function fetchDashboardStats() {
    fetch('api/admin.php?action=dashboard_stats')
        .then(function(response) {
            if (!response.ok) throw new Error('网络请求失败');
            return response.json();
        })
        .then(function(res) {
            if (res.code === 0 && res.data) {
                var dataMap = res.data;
                var elements = document.querySelectorAll('.dashboard-card-value');
                elements.forEach(function(el) {
                    var key = el.getAttribute('data-key');
                    if (key && dataMap[key] !== undefined) {
                        if (key === 'total_revenue') {
                            el.innerHTML = '¥' + Number(dataMap[key]).toLocaleString('zh-CN', {maximumFractionDigits:0});
                        } else {
                            el.innerHTML = dataMap[key];
                        }
                    }
                });
                console.log('仪表盘卡片已更新');
            } else {
                console.error('仪表盘数据获取失败', res.msg || '');
            }
        })
        .catch(function(err) {
            console.error('fetchDashboardStats 出错:', err);
        });
}
</script>
    <?php endif; ?>
</div>