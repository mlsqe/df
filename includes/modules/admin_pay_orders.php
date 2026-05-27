<?php
// includes/modules/admin_pay_orders.php
if (!defined('IN_APP')) exit;
$role = get_user_role();
if ($role !== 'admin' && $role !== 'super_admin') {
    echo '权限不足'; return;
}
$db = getDB();

// 处理手动更新状态
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    $order_id = intval($_POST['order_id']);
    // 获取当前订单
    $order = $db->prepare("SELECT * FROM recharge_log WHERE id=?");
    $order->execute([$order_id]);
    $row = $order->fetch();
    if ($row && $row['status'] == 'pending') {
        // 更新订单为成功
        $db->prepare("UPDATE recharge_log SET status='success', paid_at=NOW() WHERE id=?")->execute([$order_id]);
        // 增加用户余额（注意不要重复增加）
        $db->prepare("UPDATE users SET balance = balance + ? WHERE id=?")->execute([$row['amount'], $row['user_id']]);
        // 写入交易记录
        $db->prepare("INSERT INTO transactions (user_id, type, amount, balance_after, description) VALUES (?, 'recharge', ?, (SELECT balance FROM users WHERE id=?), '管理员手动入账')")->execute([$row['user_id'], $row['amount'], $row['user_id']]);
    }
    echo '<script>location.href="?module=admin_pay_orders"</script>';
}

$where = "1=1";
$params = [];
if (!empty($_GET['user'])) {
    $where .= " AND r.user_id = ?";
    $params[] = intval($_GET['user']);
}
if (!empty($_GET['status']) && $_GET['status'] != 'all') {
    $where .= " AND r.status = ?";
    $params[] = $_GET['status'];
}
$limit = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page-1)*$limit;
$count = $db->prepare("SELECT COUNT(*) FROM recharge_log r WHERE $where");
$count->execute($params);
$total = $count->fetchColumn();
$orders = $db->prepare("SELECT r.*, u.username FROM recharge_log r LEFT JOIN users u ON r.user_id=u.id WHERE $where ORDER BY r.id DESC LIMIT $offset,$limit");
$orders->execute($params);
$orders = $orders->fetchAll();
?>
<div class="layui-card">
    <div class="layui-card-header">支付订单管理</div>
    <div class="layui-card-body">
        <form class="layui-form layui-row layui-col-space10" method="get">
            <input type="hidden" name="module" value="admin_pay_orders">
            <div class="layui-col-md3">
                <input type="text" name="user" placeholder="用户ID" value="<?= htmlspecialchars($_GET['user'] ?? '') ?>" class="layui-input">
            </div>
            <div class="layui-col-md3">
                <select name="status">
                    <option value="all">全部状态</option>
                    <option value="pending" <?= ($_GET['status']??'')=='pending'?'selected':'' ?>>待支付</option>
                    <option value="success" <?= ($_GET['status']??'')=='success'?'selected':'' ?>>已支付</option>
                    <option value="failed" <?= ($_GET['status']??'')=='failed'?'selected':'' ?>>失败</option>
                </select>
            </div>
            <div class="layui-col-md3">
                <button type="submit" class="layui-btn layui-btn-sm">搜索</button>
            </div>
        </form>
        <table class="layui-table">
            <thead>
                <tr>
                    <th>ID</th><th>用户</th><th>订单号</th><th>金额</th>
                    <th>渠道</th><th>状态</th><th>时间</th><th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td><?= $o['id'] ?></td>
                    <td><?= htmlspecialchars($o['username']) ?> (ID:<?= $o['user_id'] ?>)</td>
                    <td><?= htmlspecialchars($o['order_no']) ?></td>
                    <td>¥<?= $o['amount'] ?></td>
                    <td><?= $o['channel_code'] ?: $o['pay_type'] ?></td>
                    <td><?php if($o['status']=='pending') echo '<span class="layui-badge layui-bg-orange">待付</span>';
                        elseif($o['status']=='success') echo '<span class="layui-badge layui-bg-green">已付</span>';
                        else echo '<span class="layui-badge">失败</span>'; ?></td>
                    <td><?= $o['created_at'] ?></td>
                    <td>
                        <?php if($o['status']=='pending'): ?>
                        <button class="layui-btn layui-btn-xs layui-btn-normal" onclick="markPaid(<?= $o['id'] ?>)">手动入账</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        // 简单分页
        $pages = ceil($total/$limit);
        if($pages>1){
            echo '<div class="layui-box layui-laypage">';
            for($i=1;$i<=$pages;$i++){
                $url = "?module=admin_pay_orders&page=$i".(isset($_GET['user'])?'&user='.intval($_GET['user']):'').(isset($_GET['status'])?'&status='.urlencode($_GET['status']):'');
                echo '<a href="'.$url.'" class="layui-laypage-'.($i==$page?'curr':'normal').'">'.$i.'</a>';
            }
            echo '</div>';
        }
        ?>
    </div>
</div>
<script>
function markPaid(id) {
    layer.confirm('确认手动标记为已支付并给用户加余额？', {icon:3}, function(idx){
        $.post('?module=admin_pay_orders', {mark_paid:1, order_id:id}, function(){
            layer.close(idx);
            location.reload();
        });
    });
}
</script>