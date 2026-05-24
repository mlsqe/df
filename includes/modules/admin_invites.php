<h3>管理员邀请码</h3>
<div class="layui-alert" style="margin-bottom:10px;">
    <i class="layui-icon layui-icon-tips"></i> 此页面生成的邀请码均为<b>打手注册邀请码</b>，可用于注册成为打手，无周期限制。
</div>
<button class="layui-btn layui-btn-sm" id="createInviteBtn">生成邀请码</button>
<table id="invitesTable" lay-filter="invitesTable"></table>
<script>
window.initAdmininvitesModule = function() {
    if (document.getElementById('invitesTable').hasAttribute('lay-render')) return;
    layui.use(['table','layer','form'], function(){
        var table = layui.table, layer = layui.layer, form = layui.form;

        table.render({
            elem: '#invitesTable',
            url: 'api/admin.php?action=list_invites',
            parseData: function(res){
                return {
                    code: (res.code !== undefined) ? res.code : (res.success ? 0 : 1),
                    count: res.count || res.total || (res.data ? res.data.length : 0),
                    data: res.data || []
                };
            },
            cols: [[
                {field:'code', title:'邀请码', minWidth:80, templet: function(d){
                    return '<a href="javascript:;" class="copy-code" data-code="' + d.code + '" style="color:#1E9FFF;">' + d.code + '</a>';
                }},
                {field:'type', title:'类型', minWidth:60},
                {field:'created_user', title:'创建者', minWidth:80, templet: function(d){return d.created_user||'-';}},
                {field:'used_user', title:'使用者', minWidth:80, templet: function(d){return d.used_user||'-';}},
                {field:'status', title:'状态', minWidth:80, templet: function(d){
                    if(d.expire_at && d.expire_at < new Date().toISOString()) return '<span class="layui-badge layui-bg-gray">过期</span>';
                    return d.status==='unused'?'<span class="layui-badge layui-bg-green">未使用</span>':'<span class="layui-badge">已使用</span>';
                }},
                {field:'expire_at', title:'有效期', minWidth:80, templet: function(d){return d.expire_at||'永久';}},
                {title:'操作', width:80, templet: function(d){
                    if(isSuperAdmin) return '<div class="btn-text-group"><a class="layui-btn layui-btn-xs layui-btn-danger" onclick="confirmDelete(\'api/admin.php\', {action:\'delete_invite\', id:'+d.id+'}, \'invitesTable\')">删除</a></div>';
                    return '';
                }}
            ]],
            page: true,
            done: function() {
                // 为所有邀请码绑定点击复制事件
                $('.copy-code').on('click', function() {
                    var code = $(this).data('code');
                    navigator.clipboard.writeText(code).then(function() {
                        Toast.success('已复制：' + code);
                    }).catch(function() {
                        // 降级方案：使用旧方法
                        var input = document.createElement('input');
                        input.value = code;
                        document.body.appendChild(input);
                        input.select();
                        document.execCommand('copy');
                        document.body.removeChild(input);
                        Toast.success('已复制：' + code);
                    });
                });
            }
        });

        $('#createInviteBtn').click(function(){
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
                            <input type="number" id="inviteExpire" value="0" min="0" class="layui-input">
                            <div class="layui-form-mid layui-word-aux">0 表示永久有效</div>
                        </div>
                    </div>
                    <button class="layui-btn layui-btn-fluid" onclick="generateInvites()">生成</button>
                </div>`
            });
            form.render();
        });

        window.generateInvites = function(){
            var count = parseInt($('#inviteCount').val()) || 1;
            var expireDays = parseInt($('#inviteExpire').val()) || 0;
            fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'create_invite', count: count, expire_days: expireDays})})
            .then(r => r.json())
            .then(res => {
                if(res.code===0 || res.success){
                    layer.closeAll();
                    layer.msg('生成成功：' + (res.data||res.codes).join(', '), {icon:1});
                    table.reload('invitesTable');
                } else {
                    layer.msg(res.msg||res.message, {icon:2});
                }
            });
        };

        window.confirmDelete = window.confirmDelete || function(url, data, reloadElem){
            if(!isSuperAdmin){ Toast.warning('仅超级管理员可操作'); return; }
            layer.confirm('确定删除吗？此操作不可恢复！', {icon:3, title:'警告'}, function(index){
                fetch(url, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)})
                .then(r=>r.json()).then(res=>{
                    if(res.code===0 || res.success){ Toast.success(res.msg||res.message); table.reload(reloadElem); }
                    else Toast.error(res.msg||res.message);
                });
                layer.close(index);
            });
        };
    });
};
</script>