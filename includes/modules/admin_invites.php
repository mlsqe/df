<h3>邀请码管理</h3>
<div class="layui-form" style="margin-bottom:10px;">
    <div class="layui-inline"><input type="text" id="inviteCode" placeholder="邀请码" class="layui-input" style="width:150px;"></div>
    <div class="layui-inline"><select id="inviteType"><option value="">全部类型</option><option value="admin">管理员</option><option value="booster">打手</option></select></div>
    <div class="layui-inline"><select id="inviteStatus"><option value="">全部状态</option><option value="unused">未使用</option><option value="used">已使用</option><option value="expired">已过期</option></select></div>
    <button class="layui-btn layui-btn-sm" id="searchInviteBtn"><i class="layui-icon layui-icon-search"></i> 搜索</button>
    <button class="layui-btn layui-btn-sm layui-btn-normal" id="createInviteBtn">生成邀请码</button>
</div>
<table id="invitesTable" lay-filter="invitesTable"></table>

<script type="text/html" id="inviteOperateTpl">
    <button class="layui-btn layui-btn-xs layui-btn-primary" lay-event="operate">
        操作 <i class="layui-icon layui-icon-down"></i>
    </button>
</script>

<script>
window.initAdmininvitesModule = function() {
    if (document.getElementById('invitesTable').hasAttribute('lay-render')) return;
    layui.use(['table', 'layer', 'form', 'dropdown'], function(){
        var table = layui.table, layer = layui.layer, form = layui.form, dropdown = layui.dropdown;

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
                {field:'code', title:'邀请码', minWidth:120},
                {field:'type', title:'类型', minWidth:80, templet: function(d){return d.type==='admin'?'管理员':'打手';}},
                {field:'created_user', title:'创建人', minWidth:100},
                {field:'used_user', title:'使用人', minWidth:100, templet: function(d){return d.used_user||'-';}},
                {field:'status', title:'状态', minWidth:80, templet: function(d){
                    if(d.status==='unused') return '<span class="layui-badge layui-bg-green">未使用</span>';
                    if(d.status==='used') return '<span class="layui-badge layui-bg-blue">已使用</span>';
                    return '<span class="layui-badge layui-bg-black">已过期</span>';
                }},
                {field:'expire_at', title:'有效期', minWidth:180, templet: function(d){return d.expire_at ? layui.util.toDateString(d.expire_at, 'yyyy-MM-dd HH:mm:ss') : '永久';}},
                {title:'操作', width:100, templet: '#inviteOperateTpl'}
            ]],
            page: true
        });

        $('#searchInviteBtn').click(function(){
            table.reload('invitesTable', {where:{keyword:$('#inviteCode').val(), type:$('#inviteType').val(), status:$('#inviteStatus').val()}, page:{curr:1}});
        });

        $('#createInviteBtn').click(function(){
            layer.open({
                type:1, title:'生成邀请码', area:'400px',
                content:'<div style="padding:20px;" class="layui-form"><div class="layui-form-item"><label>类型</label><select id="newInviteType"><option value="admin">管理员</option><option value="booster">打手</option></select></div><div class="layui-form-item"><label>数量</label><input type="number" id="newInviteCount" value="1" class="layui-input"></div><div class="layui-form-item"><label>有效期(天)</label><input type="number" id="newInviteExpire" value="0" class="layui-input" placeholder="0 永久"></div><button class="layui-btn layui-btn-fluid" id="genInviteBtn">生成</button></div>',
                success: function(layero, index){
                    form.render();
                    $('#genInviteBtn').click(function(){
                        var type = $('#newInviteType').val(), count = $('#newInviteCount').val(), expire = $('#newInviteExpire').val();
                        fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'create_invite', type:type, count:count, expire_days:expire})})
                        .then(r=>r.json()).then(res=>{ layer.msg(res.msg, {icon:res.code===0?1:2}); if(res.code===0){ layer.close(index); table.reload('invitesTable'); } });
                    });
                }
            });
        });

        table.on('tool(invitesTable)', function(obj){
            var data = obj.data;
            if(obj.event === 'operate'){
                var menuItems = [];
                if(isSuperAdmin && data.status !== 'used') menuItems.push({title:'🗑️ 删除', id:'del'});
                if(menuItems.length === 0) menuItems.push({title:'无可用操作', id:'none', disabled:true});
                dropdown.render({
                    elem: this,
                    id: 'invite-drop-' + data.id,
                    data: menuItems,
                    show: true,
                    align: 'right',
                    click: function(menuData){
                        if(menuData.id === 'del'){
                            layer.confirm('确定删除该邀请码吗？', {icon:3, title:'警告'}, function(index){
                                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'delete_invite', id:data.id})})
                                .then(r=>r.json()).then(res=>{ layer.msg(res.msg, {icon:res.code===0?1:2}); if(res.code===0) table.reload('invitesTable'); });
                                layer.close(index);
                            });
                        }
                        dropdown.close('invite-drop-' + data.id);
                    }
                });
            }
        });
    });
};
</script>