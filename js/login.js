<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
if (isLoggedIn()) { header('Location: index.php'); exit; }

$db = getDB();
$cfg = $db->query("SELECT cfg_key, cfg_value FROM system_config")->fetchAll(PDO::FETCH_KEY_PAIR);
$regOpen = $cfg['register_open'] ?? '1';
$boosterMode = $cfg['booster_register_mode'] ?? 'invite';
$depositAmount = $cfg['deposit_amount'] ?? DEPOSIT_AMOUNT;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= SITE_NAME ?> - 登录注册</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/layui@2.8.0/dist/css/layui.css">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
<div class="login-wrap">
    <div class="login-header">
        <div class="logo">🔺 <?= SITE_NAME ?></div>
        <div class="subtitle">游戏代练交易平台</div>
    </div>
    <div class="login-body">
        <div class="layui-tab" lay-filter="authTab">
            <ul class="layui-tab-title">
                <li lay-id="login" class="layui-this">登录</li>
                <?php if ($regOpen == '1'): ?>
                <li lay-id="register">注册</li>
                <?php endif; ?>
            </ul>
            <div class="layui-tab-content">
                <!-- 登录面板 -->
                <div class="layui-tab-item layui-show">
                    <form class="layui-form" id="loginForm" onsubmit="return false;">
                        <div class="layui-form-item">
                            <div class="layui-input-wrap">
                                <div class="layui-input-prefix"><i class="layui-icon layui-icon-username"></i></div>
                                <input type="text" name="username" lay-verify="required" placeholder="请输入用户名" autocomplete="off" class="layui-input">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <div class="layui-input-wrap">
                                <div class="layui-input-prefix"><i class="layui-icon layui-icon-password"></i></div>
                                <input type="password" name="password" lay-verify="required" placeholder="请输入密码" autocomplete="off" class="layui-input">
                            </div>
                        </div>
                        <button type="button" class="layui-btn layui-btn-fluid" lay-submit lay-filter="doLogin">登 录</button>
                    </form>
                </div>

                <!-- 注册面板 -->
                <?php if ($regOpen == '1'): ?>
                <div class="layui-tab-item">
                    <form class="layui-form" id="registerForm" onsubmit="return false;">
                        <div class="layui-form-item">
                            <label class="layui-form-label" style="width:70px;">注册身份</label>
                            <div class="layui-input-block" style="margin-left:80px;">
                                <select name="role" id="regRoleSelect" lay-filter="regRoleSelect">
                                    <option value="user">玩家</option>
                                    <option value="booster">打手</option>
                                </select>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <div class="layui-input-wrap">
                                <div class="layui-input-prefix"><i class="layui-icon layui-icon-username"></i></div>
                                <input type="text" name="username" lay-verify="required" placeholder="请输入用户名" autocomplete="off" class="layui-input">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <div class="layui-input-wrap">
                                <div class="layui-input-prefix"><i class="layui-icon layui-icon-password"></i></div>
                                <input type="password" name="password" lay-verify="required|pass" placeholder="密码（至少4位）" autocomplete="off" class="layui-input">
                            </div>
                        </div>

                        <!-- 打手专属区域 -->
                        <div id="boosterSection" style="display:none;">
                            <div class="reg-tip" style="background:#F0FFF4; padding:8px; border-radius:8px; text-align:center; margin-bottom:10px;">
                                📞 以下信息用于接单联系，请如实填写
                            </div>
                            <div class="layui-form-item">
                                <div class="layui-input-wrap">
                                    <div class="layui-input-prefix"><i class="layui-icon layui-icon-cellphone"></i></div>
                                    <input type="text" name="phone" placeholder="手机号" class="layui-input">
                                </div>
                            </div>
                            <div class="layui-form-item">
                                <div class="layui-input-wrap">
                                    <div class="layui-input-prefix"><i class="layui-icon layui-icon-login-wechat"></i></div>
                                    <input type="text" name="wechat" placeholder="微信号" class="layui-input">
                                </div>
                            </div>
                            <div class="layui-form-item">
                                <div class="layui-input-wrap">
                                    <div class="layui-input-prefix"><i class="layui-icon layui-icon-login-qq"></i></div>
                                    <input type="text" name="qq" placeholder="QQ号" class="layui-input">
                                </div>
                            </div>
                            <!-- 动态内容区（邀请码/押金提示等） -->
                            <div id="boosterModeContent"></div>
                        </div>

                        <button type="button" class="layui-btn layui-btn-fluid" lay-submit lay-filter="doRegister">注 册</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($regOpen == '0'): ?>
        <div class="alert-warning">管理员已关闭注册，仅支持登录</div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/layui@2.8.0/dist/layui.js"></script>
<script>
    // 由 PHP 注入的全局配置
    var boosterMode = <?= json_encode($boosterMode) ?>;
    var depositAmount = <?= (int)$depositAmount ?>;
    var regOpen = <?= (int)$regOpen ?>;

    layui.use(['form', 'element', 'layer'], function() {
        var form = layui.form, element = layui.element, layer = layui.layer;

        form.verify({
            pass: function(value) { if (value.length < 4) return '密码至少4位'; }
        });

        function renderBoosterModeContent() {
            var container = document.getElementById('boosterModeContent');
            if (!container) return;
            var html = '';
            if (boosterMode === 'invite') {
                html = '<div class="layui-form-item"><div class="layui-input-wrap"><div class="layui-input-prefix"><i class="layui-icon layui-icon-vercode"></i></div><input type="text" name="invite_code" placeholder="请输入打手邀请码" class="layui-input"></div></div>';
            } else if (boosterMode === 'deposit') {
                html = '<div class="reg-tip" style="background:#FFF8F0; padding:10px; border-radius:8px; text-align:center;">⚠️ 注册打手需缴纳保证金 <strong style="color:#FF5722;">¥'+depositAmount+'</strong></div>';
            } else if (boosterMode === 'invite_or_deposit') {
                // 注意：必须添加 lay-filter="user_select_mode"，否则切换无效
                html = 
                    '<div class="layui-form-item" style="margin-bottom:5px;">' +
                        '<label class="layui-form-label" style="width:80px;">方式</label>' +
                        '<div class="layui-input-block" style="margin-left:90px;">' +
                            '<input type="radio" name="user_select_mode" value="invite" title="邀请码" lay-filter="user_select_mode" checked>' +
                            '<input type="radio" name="user_select_mode" value="deposit" title="押金" lay-filter="user_select_mode">' +
                        '</div>' +
                    '</div>' +
                    '<div id="modeContent">' +
                        '<div class="layui-form-item"><div class="layui-input-wrap"><div class="layui-input-prefix"><i class="layui-icon layui-icon-vercode"></i></div><input type="text" name="invite_code" placeholder="请输入打手邀请码" class="layui-input"></div></div>' +
                    '</div>';
            } else {
                html = '<div class="reg-tip" style="background:#F0FFF4; padding:10px; border-radius:8px; text-align:center;">✅ 自由注册成功后将进入待审核状态</div>';
            }
            container.innerHTML = html;
            // 强制重新渲染 radio，确保 lay-filter 生效
            if (boosterMode === 'invite_or_deposit') {
                form.render('radio');
            }
        }

        // 监听 radio 切换（invite/deposit）
        form.on('radio(user_select_mode)', function(data) {
            var modeContent = document.getElementById('modeContent');
            if (!modeContent) return;
            if (data.value === 'deposit') {
                modeContent.innerHTML = '<div class="reg-tip" style="background:#FFF8F0; padding:10px; border-radius:8px; text-align:center;">⚠️ 注册打手需缴纳保证金 <strong style="color:#FF5722;">¥'+depositAmount+'</strong></div>';
            } else {
                modeContent.innerHTML = '<div class="layui-form-item"><div class="layui-input-wrap"><div class="layui-input-prefix"><i class="layui-icon layui-icon-vercode"></i></div><input type="text" name="invite_code" placeholder="请输入打手邀请码" class="layui-input"></div></div>';
            }
        });

        // 身份切换
        form.on('select(regRoleSelect)', function(data) {
            var section = document.getElementById('boosterSection');
            if (section) {
                section.style.display = data.value === 'booster' ? 'block' : 'none';
            }
            if (data.value === 'booster') {
                renderBoosterModeContent();
            }
        });

        // 登录
        form.on('submit(doLogin)', function(data) {
            var u = data.field.username.trim(), p = data.field.password.trim();
            if (!u || !p) { layer.msg('请填写用户名和密码', {icon:2}); return false; }
            fetch('api/auth.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'login', username: u, password: p})
            })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res.success) {
                    layer.msg('登录成功', {icon:1, time:1000}, function(){ location.href = 'index.php'; });
                } else {
                    layer.msg(res.message || '登录失败', {icon:2});
                }
            })
            .catch(function(){ layer.msg('网络错误', {icon:2}); });
            return false;
        });

        // 注册
        form.on('submit(doRegister)', function(data) {
            var fields = data.field;
            var username = fields.username.trim(), password = fields.password.trim(), role = fields.role;
            var phone = (fields.phone || '').trim(), wechat = (fields.wechat || '').trim(), qq = (fields.qq || '').trim();
            var inviteCode = fields.invite_code ? fields.invite_code.trim() : '';
            var userSelectMode = fields.user_select_mode || '';

            if (!username || !password) { layer.msg('请填写用户名和密码', {icon:2}); return false; }
            if (role === 'booster') {
                if (!phone && !wechat && !qq) { layer.msg('请至少填写一个联系方式', {icon:2}); return false; }
                if (boosterMode === 'invite' && !inviteCode) { layer.msg('请填写打手邀请码', {icon:2}); return false; }
                if (boosterMode === 'invite_or_deposit') {
                    if (!userSelectMode) { layer.msg('请选择邀请码或押金', {icon:2}); return false; }
                    if (userSelectMode === 'invite' && !inviteCode) { layer.msg('请填写打手邀请码', {icon:2}); return false; }
                }
            }

            fetch('api/auth.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'register',
                    username: username, password: password, role: role,
                    phone: phone, wechat: wechat, qq: qq,
                    invite_code: inviteCode,
                    booster_mode: boosterMode,
                    user_select_mode: userSelectMode
                })
            })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res.success) {
                    layer.msg(res.message || '注册成功', {icon:1, time:2000}, function(){
                        element.tabChange('authTab', 'login');
                        form.render();
                        document.getElementById('registerForm').reset();
                        var section = document.getElementById('boosterSection');
                        if (section) section.style.display = 'none';
                        var mc = document.getElementById('boosterModeContent');
                        if (mc) mc.innerHTML = '';
                    });
                } else {
                    layer.msg(res.message || '注册失败', {icon:2});
                }
            })
            .catch(function(){ layer.msg('网络错误', {icon:2}); });
            return false;
        });

        if (regOpen === 0) {
            layer.msg('管理员已关闭注册，仅支持登录', {icon:0, time:2000});
        }
    });
</script>
</body>
</html>