<h3>管理员管理</h3>
<button class="layui-btn layui-btn-sm" id="addAdminBtn"><i class="layui-icon layui-icon-add-1"></i> 添加管理员</button>
<table id="adminsTable" lay-filter="adminsTable"></table>

<script type="text/html" id="adminOperateTpl">
    <button class="layui-btn layui-btn-xs layui-btn-primary" lay-event="operate">
        操作 <i class="layui-icon layui-icon-down"></i>
    </button>
</script>

<script>
window.initAdminadminsModule = function() {
    if (document.getElementById('adminsTable').hasAttribute('lay-render')) return;
    layui.use(['table', 'layer', 'form', 'dropdown'], function(){
        var table = layui.table, layer = layui.layer, form = layui.form, dropdown = layui.dropdown;

        table.render({
            elem: '#adminsTable',
            url: 'api/admin.php?action=list_admins',
            parseData: function(res){
                return {
                    code: (res.code !== undefined) ? res.code : (res.success ? 0 : 1),
                    count: res.count || res.total || (res.data ? res.data.length : 0),
                    data: res.data || []
                };
            },
            cols: [[
                {field:'username', title:'用户名', minWidth:120},
                {field:'role', title:'角色', minWidth:100, templet: function(d){return d.role==='super_admin'?'超级管理员':'管理员';}},
                {field:'status', title:'状态', minWidth:80, templet: function(d){return d.status==='active'?'正常':'禁用';}},
                {title:'操作', width:100, templet: '#adminOperateTpl'}
            ]],
            page: false
        });

        $('#addAdminBtn').click(function(){
            layer.open({
                type:1, title:'添加管理员', area:'400px',
                content:'<div style="padding:20px;" class="layui-form"><div class="layui-form-item"><label>用户名</label><input type="text" id="adminUsername" class="layui-input"></div><div class="layui-form-item"><label>密码</label><input type="password" id="adminPassword" class="layui-input"></div><button class="layui-btn layui-btn-fluid" id="saveAdminBtn">添加</button></div>',
                success: function(layero, index){
                    form.render();
                    $('#saveAdminBtn').click(function(){
                        var username = $('#adminUsername').val(), password = $('#adminPassword').val();
                        if(!username || !password){ layer.msg('请填写完整', {icon:2}); return; }
                        fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'add_admin', username:username, password:password})})
                        .then(r=>r.json()).then(res=>{ layer.msg(res.msg, {icon:res.code===0?1:2}); if(res.code===0){ layer.close(index); table.reload('adminsTable'); } });
                    });
                }
            });
        });

        table.on('tool(adminsTable)', function(obj){
            var data = obj.data;
            if(obj.event === 'operate'){
                var menuItems = [];
                if(isSuperAdmin && data.role !== 'super_admin') menuItems.push({title:'🗑️ 删除', id:'del'});
                if(menuItems.length === 0) menuItems.push({title:'无可用操作', id:'none', disabled:true});
                dropdown.render({
                    elem: this,
                    id: 'admin-drop-' + data.id,
                    data: menuItems,
                    show: true,
                    align: 'right',
                    click: function(menuData){
                        if(menuData.id === 'del'){
                            layer.confirm('确定删除该管理员吗？', {icon:3, title:'警告'}, function(index){
                                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'delete_admin', user_id:data.id})})
                                .then(r=>r.json()).then(res=>{ layer.msg(res.msg, {icon:res.code===0?1:2}); if(res.code===0) table.reload('adminsTable'); });
                                layer.close(index);
                            });
                        }
                        dropdown.close('admin-drop-' + data.id);
                    }
                });
            }
        });
    });
};
</script>