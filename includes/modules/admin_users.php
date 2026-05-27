<h3>用户管理</h3>
<div class="layui-form" style="margin-bottom:10px;">
    <div class="layui-inline"><input type="text" id="userKeyword" placeholder="用户名/手机号" class="layui-input" style="width:160px;"></div>
    <div class="layui-inline">
        <select id="userStatus">
            <option value="">全部状态</option>
            <option value="active">正常</option>
            <option value="banned">禁用</option>
            <option value="pending">待审核</option>
        </select>
    </div>
    <div class="layui-inline">
        <select id="userRole">
            <option value="">全部身份</option>
            <!-- 动态加载身份列表 -->
        </select>
    </div>
    <button class="layui-btn layui-btn-sm" id="searchUserBtn"><i class="layui-icon layui-icon-search"></i> 搜索</button>
    <button class="layui-btn layui-btn-sm" id="addUserBtn"><i class="layui-icon layui-icon-add-1"></i> 添加用户</button>
</div>
<table id="usersTable" lay-filter="usersTable"></table>

<script type="text/html" id="userRoleTpl">
    {{# var roleText = d.role; }}
    {{# if(d.role === 'super_admin'){ roleText = '超级管理员'; } }}
    {{# if(d.role === 'admin'){ roleText = '管理员'; } }}
    {{# if(d.role === 'booster'){ roleText = '打手'; } }}
    {{# if(d.role === 'pending_booster'){ roleText = '预备打手'; } }}
    {{# if(d.role === 'user'){ roleText = '玩家'; } }}
    <span class="layui-badge layui-bg-{{ d.role === 'super_admin' ? 'red' : (d.role === 'admin' ? 'blue' : (d.role === 'booster' ? 'green' : (d.role === 'pending_booster' ? 'orange' : 'cyan'))) }}">{{ roleText }}</span>
</script>

<script type="text/html" id="userOperateTpl">
    <button class="layui-btn layui-btn-xs layui-btn-primary" lay-event="operate">操作 <i class="layui-icon layui-icon-down"></i></button>
</script>

<script>
// 英文身份到中文的映射表（用于动态加载下拉框和详情弹窗）
var roleToChinese = {
    'user': '玩家',
    'booster': '打手',
    'pending_booster': '预备打手',
    'admin': '管理员',
    'super_admin': '超级管理员'
};

window.initAdminusersModule = function() {
    if (document.getElementById('usersTable').hasAttribute('lay-render')) return;
    layui.use(['table', 'layer', 'form', 'dropdown'], function(){
        var table = layui.table, layer = layui.layer, form = layui.form, dropdown = layui.dropdown;

        // 动态加载身份选项到筛选下拉框
        function loadRoleOptions() {
            fetch('api/admin.php?action=get_config').then(r => r.json()).then(res => {
                if (res.code === 0 && res.data && res.data.module_permissions) {
                    var perms = JSON.parse(res.data.module_permissions);
                    var html = '<option value="">全部身份</option>';
                    for (var role in perms) {
                        var chineseName = roleToChinese[role] || role;
                        html += '<option value="' + role + '">' + chineseName + '</option>';
                    }
                    $('#userRole').html(html);
                    form.render('select');
                }
            });
        }
        loadRoleOptions();

        table.render({
            elem: '#usersTable',
            url: 'api/admin.php?action=list_users',
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
                {field:'role', title:'角色', minWidth:100, templet: '#userRoleTpl'},
                {field:'status', title:'状态', minWidth:100, templet: function(d){
                    if (d.status === 'active') return '<span class="layui-badge layui-bg-green">正常</span>';
                    if (d.status === 'banned') return '<span class="layui-badge layui-bg-black">禁用</span>';
                    if (d.status === 'pending') return '<span class="layui-badge layui-bg-orange">待审核</span>';
                    return '<span class="layui-badge">' + (d.status || '未知') + '</span>';
                }},
                {field:'order_count', title:'下单数', minWidth:80},
                {field:'boost_count', title:'打单数', minWidth:80, templet: function(d){
                    return (d.role === 'booster' || d.role === 'super_admin' || d.role === 'admin') ? (d.boost_count || 0) : '-';
                }},
                {field:'balance', title:'余额', minWidth:100, templet: '<span>¥{{d.balance}}</span>'},
                {field:'created_at', title:'注册时间', minWidth:180, templet: '<div>{{layui.util.toDateString(d.created_at, "yyyy-MM-dd HH:mm:ss")}}</div>'},
                {title:'操作', width:100, templet: '#userOperateTpl'}
            ]],
            page: true
        });

        // 搜索
        $('#searchUserBtn').click(function(){
            table.reload('usersTable', {where:{keyword:$('#userKeyword').val(), status:$('#userStatus').val(), role:$('#userRole').val()}, page:{curr:1}});
        });

        // 添加用户
        $('#addUserBtn').click(function(){
            fetch('api/admin.php?action=get_config').then(r => r.json()).then(res => {
                if (res.code !== 0 || !res.data || !res.data.module_permissions) {
                    layer.msg('无法获取身份列表', {icon:2});
                    return;
                }
                var perms = JSON.parse(res.data.module_permissions);
                var options = '';
                for (var role in perms) {
                    var chineseName = roleToChinese[role] || role;
                    options += '<option value="' + role + '">' + chineseName + '</option>';
                }

                var unique = Date.now();
                var contentHtml = 
                    '<div style="padding:20px;" id="addUserForm' + unique + '">' +
                    '<div class="layui-form-item"><label class="layui-form-label">用户名</label><div class="layui-input-block"><input type="text" id="newUsername' + unique + '" class="layui-input"></div></div>' +
                    '<div class="layui-form-item"><label class="layui-form-label">密码</label><div class="layui-input-block"><input type="password" id="newPassword' + unique + '" class="layui-input"></div></div>' +
                    '<div class="layui-form-item"><label class="layui-form-label">身份</label><div class="layui-input-block"><select id="newRole' + unique + '" lay-search>' + options + '</select></div></div>' +
                    '<button class="layui-btn layui-btn-fluid" id="saveNewUserBtn' + unique + '">保存</button>' +
                    '</div>';

                layer.open({
                    type: 1,
                    title: '添加用户',
                    area: '400px',
                    content: contentHtml,
                    success: function(layero, index){
                        form.render('select');
                        
                        $('#saveNewUserBtn' + unique).click(function(){
                            var username = document.getElementById('newUsername' + unique).value.trim();
                            var password = document.getElementById('newPassword' + unique).value;
                            var role = document.getElementById('newRole' + unique).value;
                            
                            if (!username) { layer.msg('请输入用户名', {icon:2}); return; }
                            if (!password) { layer.msg('请输入密码', {icon:2}); return; }
                            if (password.length < 4) { layer.msg('密码至少4位', {icon:2}); return; }
                            
                            fetch('api/admin.php', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/json'},
                                body: JSON.stringify({action:'add_user', username:username, password:password, role:role})
                            })
                            .then(r => r.json())
                            .then(res => {
                                if (res.code === 0 || res.success) {
                                    layer.msg(res.msg || '添加成功', {icon:1});
                                    layer.close(index);
                                    table.reload('usersTable');
                                } else {
                                    layer.msg(res.msg || res.message || '添加失败', {icon:2});
                                }
                            });
                        });
                    }
                });
            });
        });

        // 工具条事件（下拉菜单）
        table.on('tool(usersTable)', function(obj){
            var data = obj.data;
            if(obj.event === 'operate'){
                var menuItems = [];
                menuItems.push({title:'详情', id:'detail'});
                menuItems.push({title:'编辑信息', id:'edit'});
                menuItems.push({title:'余额修改', id:'balance'});
                if(data.role !== 'super_admin'){
                    if(data.status === 'active'){
                        // 不能封禁自己
                        if(data.id != currentUserId) menuItems.push({title:'封禁', id:'ban'});
                    } else {
                        menuItems.push({title:'解禁', id:'activate'});
                    }
                }
                if(isSuperAdmin && data.role !== 'super_admin') menuItems.push({title:'删除', id:'del'});

                dropdown.render({
                    elem: this,
                    id: 'user-drop-' + data.id,
                    data: menuItems,
                    show: true,
                    align: 'right',
                    click: function(menuData){
                        var action = menuData.id;
                        if(action === 'detail'){
                            fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'user_detail', user_id: data.id})})
                            .then(r=>r.json()).then(res=>{
                                if(res.code===0 || res.success){
                                    var u = res.data || res.user;
                                    var html = '<div style="padding:10px;"><p>用户名: '+u.username+'</p><p>姓名: '+(u.real_name||'-')+'</p><p>手机: '+(u.phone||'-')+'</p><p>微信: '+(u.wechat||'-')+'</p><p>角色: '+(roleToChinese[u.role]||u.role)+'</p><p>状态: '+u.status+'</p><p>余额: ¥'+u.balance+'</p><p>下单数: '+(u.order_count||0)+'</p><p>打单数: '+(u.boost_count||0)+'</p><p>注册时间: '+u.created_at+'</p><hr>打手信息：'+(u.booster ? '<p>等级: '+(u.booster.level_name||'无')+'</p><p>保证金: ¥'+u.booster.deposit_paid+'</p><p>评分: '+u.booster.rating+'</p><p>完成订单: '+u.booster.total_orders+'</p>' : '非打手')+'</div>';
                                    layer.open({type:1, title:'用户详情', area:'500px', content: html});
                                } else layer.msg(res.msg||res.message, {icon:2});
                            });
                        } else if(action === 'edit'){
                            layer.open({
                                type: 1,
                                title: '编辑用户信息',
                                area: '400px',
                                content: '<div style="padding:20px;" class="layui-form">' +
                                    '<div class="layui-form-item"><label>姓名</label><input type="text" id="edit_real_name" value="' + (data.real_name||'') + '" class="layui-input"></div>' +
                                    '<div class="layui-form-item"><label>手机号</label><input type="text" id="edit_phone" value="' + (data.phone||'') + '" class="layui-input"></div>' +
                                    '<div class="layui-form-item"><label>微信号</label><input type="text" id="edit_wechat" value="' + (data.wechat||'') + '" class="layui-input"></div>' +
                                    '<button class="layui-btn layui-btn-fluid" onclick="saveUserEdit(' + data.id + ')">保存</button></div>',
                                success: function(){ form.render(); }
                            });
                        } else if(action === 'balance'){
                            layer.open({
                                type: 1,
                                title: '余额修改 - ' + data.username,
                                area: '400px',
                                content: '<div style="padding:20px;" class="layui-form">' +
                                    '<div class="layui-form-item"><label>金额</label><input type="number" id="balanceAmount" step="0.01" class="layui-input" placeholder="正数充值，负数扣除"></div>' +
                                    '<div class="layui-form-item"><label>备注</label><input type="text" id="balanceRemark" class="layui-input" placeholder="可选"></div>' +
                                    '<button class="layui-btn layui-btn-fluid" onclick="adjustBalance(' + data.id + ')">确认修改</button></div>',
                                success: function(){ form.render(); }
                            });
                        } else if(action === 'ban'){
                            layer.confirm('确定封禁该用户吗？', function(i){
                                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'toggle_user_status', user_id: data.id, status:'banned'})})
                                .then(r=>r.json()).then(res=>{ layer.msg(res.msg||res.message,{icon:(res.code===0||res.success)?1:2}); if(res.code===0||res.success) table.reload('usersTable'); });
                                layer.close(i);
                            });
                        } else if(action === 'activate'){
                            layer.confirm('确定解禁该用户吗？', function(i){
                                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'toggle_user_status', user_id: data.id, status:'active'})})
                                .then(r=>r.json()).then(res=>{ layer.msg(res.msg||res.message,{icon:(res.code===0||res.success)?1:2}); if(res.code===0||res.success) table.reload('usersTable'); });
                                layer.close(i);
                            });
                        } else if(action === 'del'){
                            if(!isSuperAdmin){ Toast.warning('仅超级管理员可操作'); return; }
                            layer.confirm('确定删除该用户吗？此操作不可恢复！', {icon:3, title:'警告'}, function(i){
                                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'delete_user', user_id: data.id})})
                                .then(r=>r.json()).then(res=>{ layer.msg(res.msg||res.message,{icon:(res.code===0||res.success)?1:2}); if(res.code===0||res.success) table.reload('usersTable'); });
                                layer.close(i);
                            });
                        }
                        dropdown.close('user-drop-' + data.id);
                    }
                });
            }
        });

        // 全局函数
        window.saveUserEdit = function(userId){
            var realName = $('#edit_real_name').val().trim(), phone = $('#edit_phone').val().trim(), wechat = $('#edit_wechat').val().trim();
            if (!realName && !phone && !wechat) { Toast.warning('姓名、手机号和微信号不能都为空'); return; }
            fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'update_user', user_id: userId, real_name: realName, phone: phone, wechat: wechat})})
            .then(r => r.json()).then(res => { if(res.code===0||res.success){ layer.msg(res.msg||res.message,{icon:1}); layer.closeAll(); table.reload('usersTable'); } else layer.msg(res.msg||res.message,{icon:2}); });
        };
        window.adjustBalance = function(userId){
            var amount = parseFloat($('#balanceAmount').val());
            if (isNaN(amount) || amount === 0) { Toast.warning('请输入有效金额'); return; }
            fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'balance_adjust', user_id: userId, amount: amount, remark: $('#balanceRemark').val().trim()})})
            .then(r => r.json()).then(res => { if(res.code===0||res.success){ layer.msg(res.msg||res.message,{icon:1}); layer.closeAll(); table.reload('usersTable'); } else layer.msg(res.msg||res.message,{icon:2}); });
        };
    });
};
</script>