<h3>身份模块管理</h3>
<button class="layui-btn layui-btn-sm" id="addRoleBtn"><i class="layui-icon layui-icon-add-1"></i> 新增身份</button>
<table id="rolesTable" lay-filter="rolesTable"></table>

<script type="text/html" id="roleOperateTpl">
    <button class="layui-btn layui-btn-xs layui-btn-primary" lay-event="operate">
        操作 <i class="layui-icon layui-icon-down"></i>
    </button>
</script>

<script>
window.initAdminrolesModule = function() {
    if (document.getElementById('rolesTable').hasAttribute('lay-render')) return;

    // 核心字典：动态翻译映射表
    var moduleDict = {
        'dashboard': '📊 仪表板',
        'my_profile': '👤 个人主页',
        'my_orders': '📦 我的订单',
        'history_orders': '📜 历史订单',
        'new_order': '➕ 发布订单',
        'booster_invites': '🎟️ 接单邀请码',
        'booster_orders': '⚡ 可接订单',
        'booster_progress': '🎮 我的代练',
        'admin_orders': '🛒 订单管理',
        'admin_boosters': '🔍 打手审核',
        'admin_disputes': '⚖️ 申诉仲裁',
        'admin_types': '🏷️ 代练类型',
        'admin_invites': '🎫 邀请码管理',
        'admin_levels': '📈 打手等级',
        'admin_settings': '⚙️ 系统设置',
        'admin_users': '👥 用户管理',
        'admin_admins': '👑 管理员管理',
        'admin_roles': '🔐 身份模块'
    };

    layui.use(['table', 'layer', 'form', 'dropdown'], function(){
        var table = layui.table, layer = layui.layer, form = layui.form, dropdown = layui.dropdown;

        // 从系统配置中获取所有角色及其模块权限
        function loadRoles() {
            return fetch('api/admin.php?action=get_config').then(r => r.json()).then(res => {
                if (res.code === 0 && res.data && res.data.module_permissions) {
                    var perms = JSON.parse(res.data.module_permissions);
                    var roles = [];
                    for (var role in perms) {
                        var chineseModules = perms[role].map(function(mod) {
                            return moduleDict[mod] || mod;
                        });
                        roles.push({
                            role: role,
                            modules: chineseModules.join(' | '),
                            _modules: perms[role]
                        });
                    }
                    return roles;
                } else {
                    return [];
                }
            });
        }

        table.render({
            elem: '#rolesTable',
            data: [],
            loading: true,
            parseData: function(res){ return { code:0, data: res }; },
            cols: [[
                {field:'role', title:'身份标识', width:150},
                {field:'modules', title:'拥有可见模块', minWidth:300},
                {title:'操作', width:100, templet: '#roleOperateTpl'}
            ]],
            page: false
        });

        loadRoles().then(function(roles) {
            table.reload('rolesTable', { data: roles });
        });

        // 新增身份
        $('#addRoleBtn').click(function(){
            layer.open({
                type: 1,
                title: '新增身份',
                area: '500px',
                content: '<div style="padding:20px;" class="layui-form" id="newRoleForm">' +
                    '<div class="layui-form-item"><label class="layui-form-label">身份标识</label><div class="layui-input-block"><input type="text" id="newRoleId" class="layui-input" placeholder="英文标识，如 vip"></div></div>' +
                    '<div class="layui-form-item"><label class="layui-form-label">可见模块</label><div class="layui-input-block" id="newRoleModules" style="max-height:300px;overflow-y:auto;"></div></div>' +
                    '<button class="layui-btn layui-btn-fluid" id="saveNewRoleBtn">保存</button>' +
                '</div>',
                success: function(layero, index){
                    form.render();
                    loadAllModules().then(function(allModules) {
                        var html = '';
                        allModules.forEach(function(mod) {
                            var showName = moduleDict[mod] || mod;
                            html += '<input type="checkbox" name="newModule" lay-skin="primary" title="' + showName + '" value="' + mod + '" lay-filter="newModule">';
                        });
                        $('#newRoleModules').html(html);
                        form.render('checkbox');
                    });
                    $('#saveNewRoleBtn').click(function(){
                        var roleId = $('#newRoleId').val().trim();
                        if (!roleId) { layer.msg('请输入身份标识', {icon:2}); return; }
                        var checked = [];
                        $('input[name="newModule"]:checked').each(function() {
                            checked.push($(this).val());
                        });
                        fetch('api/admin.php?action=get_config').then(r=>r.json()).then(res => {
                            if (res.code !== 0) { layer.msg('读取配置失败', {icon:2}); return; }
                            var config = res.data || {};
                            var perms = config.module_permissions ? JSON.parse(config.module_permissions) : {};
                            if (perms[roleId]) { layer.msg('该身份已存在', {icon:2}); return; }
                            perms[roleId] = checked;
                            config.module_permissions = JSON.stringify(perms);
                            saveConfig(config).then(function() {
                                layer.close(index);
                                loadRoles().then(function(roles) {
                                    table.reload('rolesTable', { data: roles });
                                });
                            });
                        });
                    });
                }
            });
        });

        // 工具条事件（下拉菜单）
        table.on('tool(rolesTable)', function(obj){
            var data = obj.data;
            if (obj.event === 'operate') {
                var menuItems = [];
                menuItems.push({title: '✏️ 编辑模块', id: 'edit'});
                if (data.role !== 'super_admin') {
                    menuItems.push({title: '🗑️ 删除身份', id: 'del'});
                }

                dropdown.render({
                    elem: this,
                    id: 'role-drop-' + data.role,
                    data: menuItems,
                    show: true,
                    align: 'right',
                    click: function(menuData) {
                        if (menuData.id === 'edit') {
                            layer.open({
                                type: 1,
                                title: '编辑模块权限 - ' + data.role,
                                area: '500px',
                                content: '<div style="padding:20px;" class="layui-form" id="editRoleForm">' +
                                    '<div class="layui-form-item"><label class="layui-form-label">身份标识</label><div class="layui-input-block"><input type="text" value="' + data.role + '" disabled class="layui-input"></div></div>' +
                                    '<div class="layui-form-item"><label class="layui-form-label">可见模块</label><div class="layui-input-block" id="editRoleModules" style="max-height:300px;overflow-y:auto;"></div></div>' +
                                    '<button class="layui-btn layui-btn-fluid" id="saveEditRoleBtn">保存修改</button>' +
                                '</div>',
                                success: function(layero, index){
                                    form.render();
                                    loadAllModules().then(function(allModules) {
                                        var currentModules = data._modules || [];
                                        var html = '';
                                        allModules.forEach(function(mod) {
                                            var checked = currentModules.indexOf(mod) !== -1 ? ' checked' : '';
                                            var showName = moduleDict[mod] || mod;
                                            // 身份模块自身强制选中且禁用
                                            var disabled = (mod === 'admin_roles') ? ' disabled' : '';
                                            if (mod === 'admin_roles' && checked === '') {
                                                checked = ' checked'; // 确保至少选中
                                            }
                                            html += '<input type="checkbox" name="editModule" lay-skin="primary" title="' + showName + '" value="' + mod + '"' + checked + disabled + ' lay-filter="editModule">';
                                        });
                                        $('#editRoleModules').html(html);
                                        form.render('checkbox');
                                    });
                                    $('#saveEditRoleBtn').click(function(){
                                        var checked = [];
                                        $('input[name="editModule"]:checked').each(function() {
                                            checked.push($(this).val());
                                        });
                                        // 强制包含 admin_roles
                                        if (checked.indexOf('admin_roles') === -1) {
                                            checked.push('admin_roles');
                                        }
                                        fetch('api/admin.php?action=get_config').then(r=>r.json()).then(res => {
                                            if (res.code !== 0) { layer.msg('读取配置失败', {icon:2}); return; }
                                            var config = res.data || {};
                                            var perms = config.module_permissions ? JSON.parse(config.module_permissions) : {};
                                            perms[data.role] = checked;
                                            config.module_permissions = JSON.stringify(perms);
                                            saveConfig(config).then(function() {
                                                layer.close(index);
                                                loadRoles().then(function(roles) {
                                                    table.reload('rolesTable', { data: roles });
                                                });
                                            });
                                        });
                                    });
                                }
                            });
                        } else if (menuData.id === 'del') {
                            // 查询该身份的用户数量（可选，此处略）
                            layer.confirm('确定删除身份 <b style="color:#FF5722;">' + data.role + '</b> 吗？此操作不可恢复！', {icon:3, title:'危险操作'}, function(index){
                                fetch('api/admin.php?action=get_config').then(r=>r.json()).then(res => {
                                    if (res.code !== 0) { layer.msg('读取配置失败', {icon:2}); return; }
                                    var config = res.data || {};
                                    var perms = config.module_permissions ? JSON.parse(config.module_permissions) : {};
                                    delete perms[data.role];
                                    config.module_permissions = JSON.stringify(perms);
                                    saveConfig(config).then(function() {
                                        layer.close(index);
                                        loadRoles().then(function(roles) {
                                            table.reload('rolesTable', { data: roles });
                                        });
                                    });
                                });
                                layer.close(index);
                            });
                        }
                        dropdown.close('role-drop-' + data.role);
                    }
                });
            }
        });

        function saveConfig(configObj) {
            return fetch('api/admin.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'save_config', config: configObj})
            }).then(r => r.json()).then(res => {
                if (res.code === 0) {
                    layer.msg('权限配置保存成功！', {icon:1});
                } else {
                    layer.msg(res.msg, {icon:2});
                }
            });
        }

        function loadAllModules() {
            var all = [
                'dashboard', 'my_profile', 'my_orders', 'history_orders', 'new_order',
                'booster_invites', 'booster_orders', 'booster_progress',
                'admin_orders', 'admin_boosters', 'admin_disputes', 'admin_types',
                'admin_invites', 'admin_levels', 'admin_settings', 'admin_admins', 'admin_users',
                'admin_roles'
            ];
            return Promise.resolve(all);
        }
    });
};
</script>