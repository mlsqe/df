<h3>打手审核</h3>
<table id="boostersTable" lay-filter="boostersTable"></table>

<script type="text/html" id="boosterStatusTpl">
    {{# if(d.status === 'active'){ }}<span class="layui-badge layui-bg-green">已通过</span>{{# } }}
    {{# if(d.status === 'pending'){ }}<span class="layui-badge layui-bg-orange">待审核</span>{{# } }}
    {{# if(d.status === 'banned'){ }}<span class="layui-badge layui-bg-black">已拒绝</span>{{# } }}
    {{# if(d.status === 'other'){ }}<span class="layui-badge">{{d.status}}</span>{{# } }}
</script>

<script type="text/html" id="boosterRegisterModeTpl">
    {{# if(d.register_mode === 'free'){ }}<span class="layui-badge layui-bg-green">自由注册</span>{{# } }}
    {{# if(d.register_mode === 'invite'){ }}<span class="layui-badge layui-bg-blue">邀请码</span>{{# } }}
    {{# if(d.register_mode === 'deposit'){ }}<span class="layui-badge layui-bg-orange">押金</span>{{# } }}
    {{# if(d.register_mode === 'invite_or_deposit'){ }}<span class="layui-badge layui-bg-cyan">邀请码/押金</span>{{# } }}
    {{# if(d.register_mode === 'other'){ }}<span class="layui-badge">未知</span>{{# } }}
</script>

<script type="text/html" id="boosterOperateTpl">
    <button class="layui-btn layui-btn-xs layui-btn-primary" lay-event="operate">
        操作 <i class="layui-icon layui-icon-down"></i>
    </button>
</script>

<script>
window.initAdminboostersModule = function() {
    if (document.getElementById('boostersTable').hasAttribute('lay-render')) return;
    layui.use(['table', 'layer', 'dropdown'], function(){
        var table = layui.table, layer = layui.layer, dropdown = layui.dropdown;

        table.render({
            elem: '#boostersTable',
            url: 'api/admin.php?action=list_boosters',
            parseData: function(res){
                return {
                    code: (res.code !== undefined) ? res.code : (res.success ? 0 : 1),
                    count: res.count || res.total || (res.data ? res.data.length : 0),
                    data: res.data || []
                };
            },
            cols: [[
                {field:'username', title:'用户名', minWidth:100},
                {field:'real_name', title:'姓名', minWidth:130, templet: function(d){return d.real_name||'-';}},
                {field:'phone', title:'手机', minWidth:120, templet: function(d){return d.phone||'-';}},
                {field:'wechat', title:'微信', minWidth:150, templet: function(d){return d.wechat||'-';}},
                {field:'qq', title:'QQ', minWidth:150, templet: function(d){return d.qq||'-';}},
                {field:'register_mode', title:'注册方式', minWidth:100, templet: '#boosterRegisterModeTpl'},
                {field:'deposit_status', title:'保证金', minWidth:100, templet: function(d){return d.deposit_status||'未缴纳';}},
                {field:'used_invite_code', title:'使用邀请码', minWidth:120, templet: function(d){return d.used_invite_code||'-';}},
                {field:'status', title:'状态', minWidth:100, templet: '#boosterStatusTpl'},
                {title:'操作', width:100, templet: '#boosterOperateTpl'}
            ]],
            page: true
        });

        table.on('tool(boostersTable)', function(obj){
            var data = obj.data;
            if(obj.event === 'operate'){
                var menuItems = [];
                if(data.status === 'pending'){
                    menuItems.push({title:'✅ 通过', id:'approve'});
                    menuItems.push({title:'❌ 拒绝', id:'reject'});
                }
                if(isSuperAdmin){
                    menuItems.push({title:'🗑️ 删除', id:'del'});
                }
                if(menuItems.length === 0){
                    menuItems.push({title:'无可用操作', id:'none', disabled: true});
                }
                dropdown.render({
                    elem: this,
                    id: 'booster-drop-' + data.id,
                    data: menuItems,
                    show: true,
                    align: 'right',
                    click: function(menuData){
                        var action = menuData.id;
                        if(action === 'approve'){
                            layer.confirm('确定通过该打手审核吗？', function(index){
                                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'approve_booster', booster_id: data.id})})
                                .then(r=>r.json()).then(res=>{
                                    layer.msg(res.msg||res.message, {icon:(res.code===0||res.success)?1:2});
                                    if(res.code===0||res.success) table.reload('boostersTable');
                                });
                                layer.close(index);
                            });
                        } else if(action === 'reject'){
                            layer.prompt({title:'请输入拒绝原因', formType: 2}, function(text, index){
                                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'reject_booster', booster_id: data.id, reason: text})})
                                .then(r=>r.json()).then(res=>{
                                    layer.msg(res.msg||res.message, {icon:(res.code===0||res.success)?1:2});
                                    if(res.code===0||res.success) table.reload('boostersTable');
                                });
                                layer.close(index);
                            });
                        } else if(action === 'del'){
                            if(!isSuperAdmin){ layer.msg('仅超级管理员可操作', {icon:2}); return; }
                            layer.confirm('确定删除该打手吗？此操作不可恢复！', {icon:3, title:'警告'}, function(index){
                                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'delete_booster', booster_id: data.id})})
                                .then(r=>r.json()).then(res=>{
                                    layer.msg(res.msg||res.message, {icon:(res.code===0||res.success)?1:2});
                                    if(res.code===0||res.success) table.reload('boostersTable');
                                });
                                layer.close(index);
                            });
                        }
                        dropdown.close('booster-drop-' + data.id);
                    }
                });
            }
        });
    });
};
</script>