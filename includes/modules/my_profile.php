<?php
$currentUser = getCurrentUser();
$db = getDB();

// 查询打手状态
$boosterInfo = null;
$stmt = $db->prepare("SELECT * FROM boosters WHERE user_id = ?");
$stmt->execute([$currentUser['id']]);
$boosterInfo = $stmt->fetch();

// 统计数据
$stats = $db->prepare("SELECT COUNT(*) AS total_orders, SUM(amount) AS total_spent FROM orders WHERE user_id = ?");
$stats->execute([$currentUser['id']]);
$stats = $stats->fetch();

// 押金金额
$depositAmount = $db->query("SELECT cfg_value FROM system_config WHERE cfg_key='deposit_amount'")->fetchColumn() ?: DEPOSIT_AMOUNT;

// 确定身份标签
$identityLabel = '玩家';
$identityBadge = 'layui-bg-blue';
if ($currentUser['role'] === 'admin') {
    $identityLabel = '管理员';
    $identityBadge = 'layui-bg-red';
} elseif ($currentUser['role'] === 'super_admin') {
    $identityLabel = '超级管理员';
    $identityBadge = 'layui-bg-red';
} elseif ($boosterInfo) {
    if ($boosterInfo['status'] === 'active') {
        $identityLabel = '正式打手';
        $identityBadge = 'layui-bg-green';
    } elseif ($boosterInfo['status'] === 'pending') {
        $identityLabel = '预备打手（待审核）';
        $identityBadge = 'layui-bg-orange';
    } elseif ($boosterInfo['status'] === 'banned') {
        $identityLabel = '已拒绝';
        $identityBadge = 'layui-bg-black';
    }
}
?>
<h3>个人主页</h3>
<div class="layui-row layui-col-space20">
    <div class="layui-col-md4">
        <div class="layui-card">
            <div class="layui-card-header">头像</div>
            <div class="layui-card-body" style="text-align:center;">
                <img id="avatarImg" src="<?= $currentUser['avatar'] ?: 'data:image/svg+xml;charset=UTF-8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120"><rect width="120" height="120" fill="#e2e8f0"/><text x="60" y="68" font-size="42" fill="#94a3b8" text-anchor="middle" font-family="Arial">?</text></svg>') ?>" style="width:120px;height:120px;border-radius:50%;">
                <div style="margin-top:10px;">
                    <button type="button" class="layui-btn layui-btn-sm" id="uploadAvatarBtn">上传头像</button>
                    <?php if (!empty($currentUser['avatar'])): ?>
                    <button type="button" class="layui-btn layui-btn-sm layui-btn-primary" id="resetAvatarBtn">恢复默认</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- 身份与权限卡片 -->
        <div class="layui-card">
            <div class="layui-card-header">身份与权限</div>
            <div class="layui-card-body">
                <p>当前身份：<span class="layui-badge <?= $identityBadge ?>"><?= $identityLabel ?></span></p>

                <!-- 预备打手 -->
                <?php if ($boosterInfo && $boosterInfo['status'] === 'pending'): ?>
                <div class="layui-alert layui-alert-warning" style="margin-top:10px;">
                    <i class="layui-icon layui-icon-tips"></i> 您的打手申请正在审核中。
                </div>
                <?php if ($boosterInfo['register_mode'] === 'deposit'): ?>
                <div style="margin-top:10px;">
                    <button class="layui-btn layui-btn-sm layui-btn-warm" id="payDepositBtn">缴纳押金 ¥<?= $depositAmount ?></button>
                    <button class="layui-btn layui-btn-sm layui-btn-normal" id="applyInviteBtn">填写邀请码</button>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <!-- 被拒绝打手 -->
                <?php if ($boosterInfo && $boosterInfo['status'] === 'banned'): ?>
                <div class="layui-alert layui-alert-danger" style="margin-top:10px;">
                    <i class="layui-icon layui-icon-close-fill"></i> 您的打手申请已被管理员拒绝。
                </div>
                <button class="layui-btn layui-btn-sm layui-btn-warm" id="reapplyBoosterBtn">重新申请</button>
                <?php endif; ?>

                <!-- 正式打手 -->
                <?php if ($boosterInfo && $boosterInfo['status'] === 'active'): ?>
                <p style="color:#5FB878;">✅ 可接单、可生成邀请码</p>
                <?php endif; ?>

                <!-- 普通玩家 -->
                <?php if (!$boosterInfo): ?>
                <p style="color:#999;">暂无特殊权限</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="layui-col-md8">
        <div class="layui-card">
            <div class="layui-card-header">基本信息</div>
            <div class="layui-card-body">
                <form class="layui-form" id="profileForm" lay-filter="profileForm">
                    <div class="layui-form-item">
                        <label class="layui-form-label">用户名</label>
                        <div class="layui-input-block">
                            <input type="text" value="<?= sanitize($currentUser['username']) ?>" readonly class="layui-input">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">昵称</label>
                        <div class="layui-input-block">
                            <input type="text" name="real_name" id="realName" value="<?= sanitize($currentUser['real_name']) ?>" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">手机号</label>
                        <div class="layui-input-block">
                            <input type="text" name="phone" id="phone" value="<?= sanitize($currentUser['phone']) ?>" class="layui-input">
                        </div>
                    </div>
                    <button type="button" class="layui-btn" id="saveProfileBtn">保存信息</button>
                </form>
            </div>
        </div>
        <div class="layui-card">
            <div class="layui-card-header">修改密码</div>
            <div class="layui-card-body">
                <form class="layui-form" id="passwordForm" lay-filter="passwordForm">
                    <div class="layui-form-item">
                        <label class="layui-form-label">旧密码</label>
                        <div class="layui-input-block"><input type="password" name="old_password" id="oldPassword" class="layui-input"></div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">新密码</label>
                        <div class="layui-input-block"><input type="password" name="new_password" id="newPassword" class="layui-input"></div>
                    </div>
                    <button type="button" class="layui-btn" id="changePasswordBtn">修改密码</button>
                </form>
            </div>
        </div>
        <div class="layui-card">
            <div class="layui-card-header">我的数据</div>
            <div class="layui-card-body">
                <p>余额：¥<?= number_format($currentUser['balance'], 2) ?></p>
                <p>总订单数：<?= $stats['total_orders'] ?></p>
                <p>总消费：¥<?= number_format($stats['total_spent'] ?: 0, 2) ?></p>
            </div>
        </div>
    </div>
</div>

<script>
// 上传头像
document.getElementById('uploadAvatarBtn').addEventListener('click', function() {
    var input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = function() {
        var file = this.files[0];
        if (!file) return;
        if (file.size > 10 * 1024 * 1024) {
            Toast.error('图片大小不能超过 10MB');
            return;
        }

        var formData = new FormData();
        formData.append('source', file);
        formData.append('upload_token', Date.now() + '' + Math.random());

        fetch('api/proxy.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status_code === 200 && data.image && data.image.url) {
                var url = data.image.url;
                fetch('api/profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_avatar', avatar: url })
                })
                .then(r => r.json())
                .then(res => {
                    if (res.code === 0) {
                        document.getElementById('avatarImg').src = url;
                        Toast.success('头像更新成功');
                        var resetBtn = document.getElementById('resetAvatarBtn');
                        if (resetBtn) resetBtn.style.display = 'inline-block';
                        setTimeout(function(){ location.reload(); }, 1000);
                    } else {
                        Toast.error(res.msg);
                    }
                });
            } else {
                Toast.error(data.status_txt || '上传失败');
            }
        })
        .catch(function() { Toast.error('网络错误，上传失败'); });
    };
    input.click();
});

// 恢复默认头像
document.getElementById('resetAvatarBtn')?.addEventListener('click', function() {
    fetch('api/profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'reset_avatar' })
    })
    .then(r => r.json())
    .then(res => {
        if (res.code === 0) {
            var defaultSrc = 'data:image/svg+xml;charset=UTF-8,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120"><rect width="120" height="120" fill="#e2e8f0"/><text x="60" y="68" font-size="42" fill="#94a3b8" text-anchor="middle" font-family="Arial">?</text></svg>') ?>';
            document.getElementById('avatarImg').src = defaultSrc;
            var resetBtn = document.getElementById('resetAvatarBtn');
            if (resetBtn) resetBtn.style.display = 'none';
            Toast.success(res.msg);
            setTimeout(function(){ location.reload(); }, 1000);
        } else {
            Toast.error(res.msg);
        }
    });
});

// 保存基本信息
document.getElementById('saveProfileBtn').addEventListener('click', function() {
    var realName = document.getElementById('realName').value.trim();
    var phone = document.getElementById('phone').value.trim();
    if (!realName && !phone) {
        Toast.warning('昵称和手机号不能都为空');
        return;
    }
    fetch('api/profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_profile', real_name: realName, phone: phone })
    })
    .then(r => r.json())
    .then(res => {
        if (res.code === 0) {
            Toast.success(res.msg);
            setTimeout(function(){ location.reload(); }, 1000);
        } else {
            Toast.error(res.msg);
        }
    })
    .catch(function() { Toast.error('网络错误'); });
});

// 修改密码
document.getElementById('changePasswordBtn').addEventListener('click', function() {
    var oldPwd = document.getElementById('oldPassword').value;
    var newPwd = document.getElementById('newPassword').value;
    if (!oldPwd || !newPwd) {
        Toast.warning('旧密码和新密码不能为空');
        return;
    }
    fetch('api/profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'change_password', old_password: oldPwd, new_password: newPwd })
    })
    .then(r => r.json())
    .then(res => {
        if (res.code === 0) {
            Toast.success(res.msg);
            setTimeout(function(){ location.reload(); }, 1000);
        } else {
            Toast.error(res.msg);
        }
    })
    .catch(function() { Toast.error('网络错误'); });
});

// 预备打手：缴纳押金（仅押金模式显示）
document.getElementById('payDepositBtn')?.addEventListener('click', function() {
    layer.confirm('确认缴纳 ¥<?= $depositAmount ?> 保证金吗？', function(index) {
        fetch('api/profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'pay_deposit' })
        })
        .then(r => r.json())
        .then(res => {
            if (res.code === 0) {
                Toast.success(res.msg);
                setTimeout(() => location.reload(), 1000);
            } else {
                Toast.error(res.msg);
            }
        });
        layer.close(index);
    });
});

// 预备打手：填写邀请码（仅押金模式显示）
document.getElementById('applyInviteBtn')?.addEventListener('click', function() {
    layer.prompt({ title: '请输入打手邀请码' }, function(code, index) {
        fetch('api/profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'apply_invite', invite_code: code })
        })
        .then(r => r.json())
        .then(res => {
            if (res.code === 0) {
                Toast.success(res.msg);
                setTimeout(() => location.reload(), 1000);
            } else {
                Toast.error(res.msg);
            }
        });
        layer.close(index);
    });
});

// 被拒绝打手：重新申请
document.getElementById('reapplyBoosterBtn')?.addEventListener('click', function() {
    layer.confirm('确定重新申请成为打手吗？', function(index) {
        fetch('api/profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reapply_booster' })
        })
        .then(r => r.json())
        .then(res => {
            if (res.code === 0) {
                Toast.success(res.msg);
                setTimeout(() => location.reload(), 1000);
            } else {
                Toast.error(res.msg);
            }
        });
        layer.close(index);
    });
});
</script>