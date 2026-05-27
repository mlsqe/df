<?php
// includes/modules/admin_payment.php
if (!defined('IN_APP')) exit;
$user_id = get_user_id();
$role = get_user_role();
if ($role !== 'admin' && $role !== 'super_admin') {
    echo '权限不足'; return;
}
$db = getDB();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $channel_code = trim($_POST['channel_code'] ?? '');
        $channel_name = trim($_POST['channel_name'] ?? '');
        $pay_type = $_POST['pay_type'] ?? 'epay';
        $api_url = trim($_POST['api_url'] ?? '');
        $merchant_id = trim($_POST['merchant_id'] ?? '');
        $secret_key = trim($_POST['secret_key'] ?? '');
        $status = $_POST['status'] ?? 'enabled';
        $sort_order = intval($_POST['sort_order'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE payment_channels SET channel_code=?, channel_name=?, pay_type=?, api_url=?, merchant_id=?, secret_key=?, status=?, sort_order=? WHERE id=?");
            $stmt->execute([$channel_code, $channel_name, $pay_type, $api_url, $merchant_id, $secret_key, $status, $sort_order, $id]);
        } else {
            $stmt = $db->prepare("INSERT INTO payment_channels (channel_code, channel_name, pay_type, api_url, merchant_id, secret_key, status, sort_order) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$channel_code, $channel_name, $pay_type, $api_url, $merchant_id, $secret_key, $status, $sort_order]);
        }
        echo '<script>location.href="?module=admin_payment"</script>';
    } elseif ($action === 'delete' && isset($_POST['id'])) {
        $stmt = $db->prepare("DELETE FROM payment_channels WHERE id=?");
        $stmt->execute([intval($_POST['id'])]);
        echo '<script>location.href="?module=admin_payment"</script>';
    }
}

$channels = $db->query("SELECT * FROM payment_channels ORDER BY sort_order ASC")->fetchAll();
?>
<div class="layui-card">
    <div class="layui-card-header">支付渠道管理</div>
    <div class="layui-card-body">
        <table class="layui-table" lay-even>
            <thead>
                <tr>
                    <th>ID</th><th>渠道代码</th><th>显示名称</th><th>接口类型</th><th>商户ID</th>
                    <th>状态</th><th>排序</th><th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($channels as $ch): ?>
                <tr>
                    <td><?= $ch['id'] ?></td>
                    <td><?= htmlspecialchars($ch['channel_code']) ?></td>
                    <td><?= htmlspecialchars($ch['channel_name']) ?></td>
                    <td><?= $ch['pay_type'] ?></td>
                    <td><?= htmlspecialchars($ch['merchant_id']) ?></td>
                    <td><?= $ch['status'] == 'enabled' ? '<span class="layui-badge layui-bg-green">启用</span>' : '<span class="layui-badge">禁用</span>' ?></td>
                    <td><?= $ch['sort_order'] ?></td>
                    <td>
                        <button class="layui-btn layui-btn-xs layui-btn-normal" onclick="editChannel(<?= htmlspecialchars(json_encode($ch)) ?>)">编辑</button>
                        <button class="layui-btn layui-btn-xs layui-btn-danger" onclick="deleteChannel(<?= $ch['id'] ?>)">删除</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button class="layui-btn layui-btn-sm" onclick="editChannel()">添加新渠道</button>
    </div>
</div>

<!-- 编辑/添加表单弹窗 -->
<div id="channelFormLayer" style="display:none; padding:20px;">
    <form class="layui-form" id="channelForm">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="channelId">
        <div class="layui-form-item">
            <label class="layui-form-label">渠道代码</label>
            <div class="layui-input-block"><input type="text" name="channel_code" required class="layui-input"></div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">显示名称</label>
            <div class="layui-input-block"><input type="text" name="channel_name" required class="layui-input"></div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">接口类型</label>
            <div class="layui-input-block"><input type="text" name="pay_type" value="epay" class="layui-input"></div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">API地址</label>
            <div class="layui-input-block"><input type="text" name="api_url" class="layui-input"></div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">商户ID</label>
            <div class="layui-input-block"><input type="text" name="merchant_id" class="layui-input"></div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">密钥</label>
            <div class="layui-input-block"><input type="text" name="secret_key" class="layui-input"></div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">状态</label>
            <div class="layui-input-block">
                <input type="radio" name="status" value="enabled" title="启用" checked>
                <input type="radio" name="status" value="disabled" title="禁用">
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">排序</label>
            <div class="layui-input-block"><input type="number" name="sort_order" value="0" class="layui-input"></div>
        </div>
        <div class="layui-form-item" style="text-align:center;">
            <button type="submit" class="layui-btn">保存</button>
        </div>
    </form>
</div>

<script>
function editChannel(data) {
    if (data) {
        $('#channelId').val(data.id);
        $('input[name="channel_code"]').val(data.channel_code);
        $('input[name="channel_name"]').val(data.channel_name);
        $('input[name="pay_type"]').val(data.pay_type);
        $('input[name="api_url"]').val(data.api_url);
        $('input[name="merchant_id"]').val(data.merchant_id);
        $('input[name="secret_key"]').val(data.secret_key);
        $('input[name="status"][value="'+data.status+'"]').prop('checked', true);
        $('input[name="sort_order"]').val(data.sort_order);
    } else {
        $('#channelForm')[0].reset();
        $('#channelId').val('');
    }
    layer.open({
        type: 1,
        title: '支付渠道编辑',
        area: ['500px', '480px'],
        content: $('#channelFormLayer')
    });
}
function deleteChannel(id) {
    layer.confirm('确定删除？', {icon: 3}, function(index){
        $.post('?module=admin_payment', {action:'delete', id:id}, function(){ location.reload(); });
        layer.close(index);
    });
}
</script>