<h3>管理员</h3>
<button class="layui-btn layui-btn-sm" id="addAdminBtn">添加管理员</button>
<table id="adminsTable" lay-filter="adminsTable"></table>
<script>
window.initAdminadminsModule = function() {
    if (document.getElementById('adminsTable').hasAttribute('lay-render')) return;
    layui.use('table', function(){
        var table = layui.table;
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
                {field:'username', title:'用户名', minWidth:80},
                {field:'role', title:'角色', minWidth:80},
                {field:'status', title:'状态', minWidth:80},
                {title:'操作', width:80, templet: function(d){
                    if(isSuperAdmin && d.role!=='super_admin') return '<div class="btn-text-group"><a class="layui-btn layui-btn-xs layui-btn-danger" onclick="deleteAdmin('+d.id+')">删除</a></div>';
                    return '';
                }}
            ]]
        });

        $('#addAdminBtn').click(function(){
            layer.open({
                type:1, title:'添加管理员', area:'300px',
                content: `<div style="padding:20px;"><div class="layui-form-item"><input type="text" id="new_admin_user" placeholder="用户名" class="layui-input"></div><div class="layui-form-item"><input type="password" id="new_admin_pass" placeholder="密码" class="layui-input"></div><button class="layui-btn layui-btn-fluid" onclick="addAdmin()">添加</button></div>`
            });
        });

        window.addAdmin = function(){
            var u = $('#new_admin_user').val(), p = $('#new_admin_pass').val();
            if(!u || !p) return layer.msg('请填写完整', {icon:2});
            fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'add_admin', username:u, password:p})})
            .then(r=>r.json()).then(res=>{
                if(res.code===0 || res.success){ Toast.success(res.msg||res.message); layer.closeAll(); table.reload('adminsTable'); }
                else Toast.error(res.msg||res.message);
            });
        };

        window.deleteAdmin = function(id){
            if(!isSuperAdmin){ Toast.warning('仅超级管理员可操作'); return; }
            layer.confirm('确定删除？', function(i){
                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'delete_admin', user_id:id})})
                .then(r=>r.json()).then(res=>{
                    if(res.code===0 || res.success){ Toast.success(res.msg||res.message); table.reload('adminsTable'); }
                    else Toast.error(res.msg||res.message);
                });
                layer.close(i);
            });
        };
    });
};
</script>