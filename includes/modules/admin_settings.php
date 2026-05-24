<h3>系统设置</h3>
<form class="layui-form" lay-filter="settingsForm">
    <!-- 玩家注册 -->
    <fieldset class="layui-elem-field">
        <legend>玩家注册</legend>
        <div class="layui-form-item">
            <label class="layui-form-label">开放注册</label>
            <div class="layui-input-block">
                <input type="checkbox" name="register_open" lay-skin="switch" lay-text="ON|OFF">
            </div>
        </div>
    </fieldset>

    <!-- 打手注册与邀请码 -->
    <fieldset class="layui-elem-field">
        <legend>打手注册与邀请码</legend>
        <div class="layui-form-item">
            <label class="layui-form-label">注册方式</label>
            <div class="layui-input-block">
                <select name="booster_register_mode">
                    <option value="free">自由注册</option>
                    <option value="invite">邀请码注册</option>
                    <option value="deposit">押金注册</option>
                    <option value="invite_or_deposit">押金或邀请码（用户自选）</option>
                </select>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">打手押金</label>
            <div class="layui-input-block">
                <input type="number" name="deposit_amount" class="layui-input">
                <div class="layui-form-mid layui-word-aux">元</div>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">邀请码周期</label>
            <div class="layui-input-block">
                <input type="number" name="booster_invite_period_days" class="layui-input" style="width:100px;display:inline-block;">
                <span style="margin-left:10px;">天</span>
                <div class="layui-form-mid layui-word-aux">仅限制打手生成邀请码，管理员不受限</div>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">每周期数量</label>
            <div class="layui-input-block">
                <input type="number" name="booster_invite_limit" class="layui-input" style="width:100px;display:inline-block;">
                <span style="margin-left:10px;">个</span>
            </div>
        </div>
    </fieldset>

    <div style="margin-top:20px;">
        <button type="button" class="layui-btn" lay-submit lay-filter="saveSettings">保存设置</button>
    </div>
</form>

<script>
window.initAdminsettingsModule = function() {
    layui.use(['form','element'], function(){
        var form = layui.form;

        fetch('api/admin.php?action=get_config').then(r=>r.json()).then(res=>{
            if((res.code===0 || res.success) && (res.data || res.config)){
                var cfg = res.data || res.config;
                form.val('settingsForm', {
                    register_open: cfg.register_open == '1',
                    booster_register_mode: cfg.booster_register_mode || 'invite',
                    deposit_amount: cfg.deposit_amount || '<?= DEPOSIT_AMOUNT ?>',
                    booster_invite_period_days: cfg.booster_invite_period_days || '30',
                    booster_invite_limit: cfg.booster_invite_limit || '5'
                });
            }
        });

        form.on('submit(saveSettings)', function(data){
            var config = {
                register_open: data.field.register_open ? '1' : '0',
                booster_register_mode: data.field.booster_register_mode,
                deposit_amount: data.field.deposit_amount,
                booster_invite_period_days: data.field.booster_invite_period_days,
                booster_invite_limit: data.field.booster_invite_limit
            };
            fetch('api/admin.php', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({action:'save_config', config: config})
            }).then(r=>r.json()).then(res=>{
                layer.msg(res.msg||res.message, {icon:(res.code===0||res.success)?1:2});
            });
            return false;
        });
    });
};
</script>