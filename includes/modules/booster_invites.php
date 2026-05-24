<h3>我的邀请码</h3>
<div class="layui-alert" style="margin-bottom:10px;">
    <i class="layui-icon layui-icon-tips"></i> 您生成的邀请码用于他人注册成为打手，受周期限制。点击邀请码可复制。
</div>
<button class="layui-btn layui-btn-sm" id="createInviteBtn">生成邀请码</button>
<span id="inviteLimitInfo" style="margin-left:10px; color:#999;"></span>
<div id="boosterInviteList" style="margin-top:10px;"></div>
<script>
window.initBoosterinvitesModule = function() {
    loadInvites();
    loadLimitInfo();

    document.getElementById('createInviteBtn').addEventListener('click', function(){
        layui.use(['layer','form'], function(){
            var layer = layui.layer, form = layui.form;
            layer.open({
                type: 1,
                title: '生成邀请码',
                area: '400px',
                content: `<div style="padding:20px;" class="layui-form">
                    <div class="layui-form-item">
                        <label class="layui-form-label">数量</label>
                        <div class="layui-input-block">
                            <input type="number" id="inviteCount" value="1" min="1" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-form-item">
                        <label class="layui-form-label">有效期(天)</label>
                        <div class="layui-input-block">
                            <input type="number" id="inviteExpire" value="30" min="1" class="layui-input">
                            <div class="layui-form-mid layui-word-aux">最小1天，默认30天</div>
                        </div>
                    </div>
                    <button class="layui-btn layui-btn-fluid" onclick="generateBoosterInvites()">生成</button>
                </div>`
            });
            form.render();
        });
    });

    window.generateBoosterInvites = function(){
        var count = parseInt(document.getElementById('inviteCount').value) || 1;
        var expireDays = parseInt(document.getElementById('inviteExpire').value) || 30;
        fetch('api/booster.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'create_invite', count: count, expire_days: expireDays})})
        .then(r => r.json())
        .then(res => {
            if(res.success){
                layer.closeAll();
                Toast.success('生成成功：' + res.codes.join(', '));
                loadInvites();
                loadLimitInfo();
            } else {
                Toast.error(res.message);
            }
        });
    };

    function loadInvites(){
        fetch('api/booster.php?action=my_invites').then(r=>r.json()).then(res=>{
            var html = '';
            if(res.success && res.data){
                html = '<table class="layui-table"><thead><tr><th>邀请码</th><th>状态</th><th>有效期</th></tr></thead><tbody>';
                res.data.forEach(item=>{
                    html += `<tr><td><a href="javascript:;" class="copy-code" data-code="${item.code}" style="color:#1E9FFF;">${item.code}</a></td><td>${item.status}</td><td>${item.expire_at||'永久'}</td></tr>`;
                });
                html += '</tbody></table>';
                // 绑定复制事件
                setTimeout(function(){
                    document.querySelectorAll('.copy-code').forEach(function(el){
                        el.addEventListener('click', function(){
                            var code = this.getAttribute('data-code');
                            navigator.clipboard.writeText(code).then(function(){
                                Toast.success('已复制：' + code);
                            }).catch(function(){
                                // 降级方案
                                var input = document.createElement('input');
                                input.value = code;
                                document.body.appendChild(input);
                                input.select();
                                document.execCommand('copy');
                                document.body.removeChild(input);
                                Toast.success('已复制：' + code);
                            });
                        });
                    });
                }, 200);
            }
            document.getElementById('boosterInviteList').innerHTML = html;
        });
    }

    function loadLimitInfo(){
        fetch('api/booster.php?action=invite_limit_info').then(r=>r.json()).then(res=>{
            if(res.success && res.data){
                var info = res.data;
                var text = `本周期剩余可创建：${info.remain} 个（共 ${info.limit} 个，周期 ${info.periodDays} 天）`;
                document.getElementById('inviteLimitInfo').innerText = text;
            }
        });
    }
};
</script>