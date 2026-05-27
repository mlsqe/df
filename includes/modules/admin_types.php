<h3>代练类型</h3>
<button class="layui-btn layui-btn-sm" id="addTypeBtn"><i class="layui-icon layui-icon-add-1"></i> 新增类型</button>
<table id="gameTypesTable" lay-filter="gameTypesTable"></table>

<script type="text/html" id="typeOperateTpl">
    <button class="layui-btn layui-btn-xs layui-btn-primary" lay-event="operate">
        操作 <i class="layui-icon layui-icon-down"></i>
    </button>
</script>

<script>
window.initAdmintypesModule = function() {
    if (document.getElementById('gameTypesTable').hasAttribute('lay-render')) return;
    layui.use(['table', 'layer', 'form', 'dropdown'], function(){
        var table = layui.table, layer = layui.layer, form = layui.form, dropdown = layui.dropdown;

        table.render({
            elem: '#gameTypesTable',
            url: 'api/admin.php?action=list_game_types',
            parseData: function(res){
                return {
                    code: (res.code !== undefined) ? res.code : (res.success ? 0 : 1),
                    count: res.count || res.total || (res.data ? res.data.length : 0),
                    data: res.data || []
                };
            },
            cols: [[
                {field:'name', title:'名称', minWidth:120},
                {field:'unit_label', title:'单位', minWidth:80},
                {field:'price_per_unit', title:'单价', minWidth:100, templet: '<span>¥{{d.price_per_unit}}</span>'},
                {field:'status', title:'状态', minWidth:80, templet: function(d){
                    return d.status === 'active'
                        ? '<span class="layui-badge layui-bg-green">启用</span>'
                        : '<span class="layui-badge layui-bg-gray">禁用</span>';
                }},
                {title:'操作', width:100, templet: '#typeOperateTpl'}
            ]],
            page: false
        });

        // 新增类型
        $('#addTypeBtn').click(function(){
            openTypeForm(0);
        });

        table.on('tool(gameTypesTable)', function(obj){
            var data = obj.data;
            if(obj.event === 'operate'){
                var menuItems = [{title:'✏️ 编辑', id:'edit'}];
                if(isSuperAdmin) menuItems.push({title:'🗑️ 删除', id:'del'});
                dropdown.render({
                    elem: this,
                    id: 'type-drop-' + data.id,
                    data: menuItems,
                    show: true,
                    align: 'right',
                    click: function(menuData){
                        if(menuData.id === 'edit') openTypeForm(data.id);
                        else if(menuData.id === 'del'){
                            if(!isSuperAdmin){ layer.msg('仅超级管理员可操作', {icon:2}); return; }
                            layer.confirm('确定删除该类型吗？', {icon:3, title:'警告'}, function(index){
                                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'delete_game_type', id:data.id})})
                                .then(r=>r.json()).then(res=>{
                                    layer.msg(res.msg||res.message, {icon:(res.code===0||res.success)?1:2});
                                    if(res.code===0||res.success) table.reload('gameTypesTable');
                                });
                                layer.close(index);
                            });
                        }
                        dropdown.close('type-drop-' + data.id);
                    }
                });
            }
        });

        // 打开编辑/新增弹窗
        function openTypeForm(id){
            var title = id ? '编辑类型' : '新增类型';
            fetch('api/admin.php?action=list_game_types').then(r=>r.json()).then(res=>{
                var data = {};
                if(id && res.data){
                    var found = res.data.find(function(item){ return item.id == id; });
                    if(found) data = found;
                }
                layer.open({
                    type: 1,
                    title: title,
                    area: '400px',
                    content: '<div style="padding:20px;" class="layui-form">' +
                        '<div class="layui-form-item"><label>名称</label><input type="text" id="type_name" value="'+(data.name||'')+'" class="layui-input"></div>' +
                        '<div class="layui-form-item"><label>单位</label><select id="type_unit">' +
                            '<option value="K" '+(data.unit_label=='K'?'selected':'')+'>K</option>' +
                            '<option value="M" '+(data.unit_label=='M'?'selected':'')+'>M</option>' +
                            '<option value="B" '+(data.unit_label=='B'?'selected':'')+'>B</option></select></div>' +
                        '<div class="layui-form-item"><label>单价</label><input type="number" id="type_price" value="'+(data.price_per_unit||'')+'" step="0.01" class="layui-input"></div>' +
                        '<div class="layui-form-item"><label>状态</label><select id="type_status"><option value="active" '+(data.status=='active'?'selected':'')+'>启用</option><option value="disabled" '+(data.status=='disabled'?'selected':'')+'>禁用</option></select></div>' +
                        '<button class="layui-btn layui-btn-fluid" id="saveTypeBtn">保存</button></div>',
                    success: function(layero, index){
                        form.render();
                        $('#saveTypeBtn').click(function(){
                            var name = $('#type_name').val(), unit = $('#type_unit').val(), price = parseFloat($('#type_price').val()), status = $('#type_status').val();
                            if(!name || isNaN(price) || price<=0){ layer.msg('请填写完整且正确的信息', {icon:2}); return; }
                            var sendData = {action:'save_game_type', name:name, unit_label:unit, price_per_unit:price, status:status};
                            if(id) sendData.id = id;
                            fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(sendData)})
                            .then(r=>r.json()).then(res=>{
                                if(res.code===0 || res.success){
                                    layer.msg(res.msg||res.message, {icon:1});
                                    layer.close(index);
                                    table.reload('gameTypesTable');
                                } else layer.msg(res.msg||res.message, {icon:2});
                            });
                        });
                    }
                });
            });
        }
    });
};
</script>