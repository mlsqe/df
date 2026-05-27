<h3>打手等级</h3>
<button class="layui-btn layui-btn-sm" id="addLevelBtn"><i class="layui-icon layui-icon-add-1"></i> 新增等级</button>
<table id="levelsTable" lay-filter="levelsTable"></table>

<script type="text/html" id="levelOperateTpl">
    <button class="layui-btn layui-btn-xs layui-btn-primary" lay-event="operate">
        操作 <i class="layui-icon layui-icon-down"></i>
    </button>
</script>

<script>
window.initAdminlevelsModule = function() {
    if (document.getElementById('levelsTable').hasAttribute('lay-render')) return;
    layui.use(['table', 'layer', 'form', 'dropdown'], function(){
        var table = layui.table, layer = layui.layer, form = layui.form, dropdown = layui.dropdown;

        table.render({
            elem: '#levelsTable',
            url: 'api/admin.php?action=list_booster_levels',
            parseData: function(res){
                return {
                    code: (res.code !== undefined) ? res.code : (res.success ? 0 : 1),
                    count: res.count || res.total || (res.data ? res.data.length : 0),
                    data: res.data || []
                };
            },
            cols: [[
                {field:'name', title:'等级名称', minWidth:120},
                {field:'min_orders', title:'最低订单', minWidth:100},
                {field:'min_rating', title:'最低评分', minWidth:100},
                {field:'deposit_required', title:'保证金', minWidth:100, templet: '<span>¥{{d.deposit_required}}</span>'},
                {field:'sort_order', title:'排序', minWidth:80},
                {title:'操作', width:100, templet: '#levelOperateTpl'}
            ]],
            page: false
        });

        $('#addLevelBtn').click(function(){ openLevelForm(0); });

        table.on('tool(levelsTable)', function(obj){
            var data = obj.data;
            if(obj.event === 'operate'){
                var menuItems = [{title:'✏️ 编辑', id:'edit'}];
                if(isSuperAdmin) menuItems.push({title:'🗑️ 删除', id:'del'});
                dropdown.render({
                    elem: this,
                    id: 'level-drop-' + data.id,
                    data: menuItems,
                    show: true,
                    align: 'right',
                    click: function(menuData){
                        if(menuData.id === 'edit') openLevelForm(data.id);
                        else if(menuData.id === 'del'){
                            if(!isSuperAdmin){ layer.msg('仅超级管理员可操作', {icon:2}); return; }
                            layer.confirm('确定删除该等级吗？', {icon:3, title:'警告'}, function(index){
                                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'delete_booster_level', level_id:data.id})})
                                .then(r=>r.json()).then(res=>{ layer.msg(res.msg, {icon:res.code===0?1:2}); if(res.code===0) table.reload('levelsTable'); });
                                layer.close(index);
                            });
                        }
                        dropdown.close('level-drop-' + data.id);
                    }
                });
            }
        });

        function openLevelForm(id){
            var title = id ? '编辑等级' : '新增等级';
            fetch('api/admin.php?action=list_booster_levels').then(r=>r.json()).then(res=>{
                var data = {};
                if(id && res.data){
                    var found = res.data.find(function(item){ return item.id == id; });
                    if(found) data = found;
                }
                layer.open({
                    type:1, title:title, area:'400px',
                    content:'<div style="padding:20px;" class="layui-form">' +
                        '<div class="layui-form-item"><label>名称</label><input type="text" id="levelName" value="'+(data.name||'')+'" class="layui-input"></div>' +
                        '<div class="layui-form-item"><label>最低订单数</label><input type="number" id="levelMinOrders" value="'+(data.min_orders||'')+'" class="layui-input"></div>' +
                        '<div class="layui-form-item"><label>最低评分</label><input type="number" id="levelMinRating" step="0.1" value="'+(data.min_rating||'')+'" class="layui-input"></div>' +
                        '<div class="layui-form-item"><label>所需保证金</label><input type="number" id="levelDeposit" step="0.01" value="'+(data.deposit_required||'')+'" class="layui-input"></div>' +
                        '<div class="layui-form-item"><label>描述</label><input type="text" id="levelDesc" value="'+(data.description||'')+'" class="layui-input"></div>' +
                        '<button class="layui-btn layui-btn-fluid" id="saveLevelBtn">保存</button></div>',
                    success: function(layero, index){
                        form.render();
                        $('#saveLevelBtn').click(function(){
                            var name = $('#levelName').val(), minOrders = $('#levelMinOrders').val(), minRating = $('#levelMinRating').val(), deposit = $('#levelDeposit').val(), desc = $('#levelDesc').val();
                            if(!name){ layer.msg('请输入名称', {icon:2}); return; }
                            fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'save_booster_level', id:id?id:0, name:name, min_orders:minOrders, min_rating:minRating, deposit_required:deposit, description:desc})})
                            .then(r=>r.json()).then(res=>{ layer.msg(res.msg, {icon:res.code===0?1:2}); if(res.code===0){ layer.close(index); table.reload('levelsTable'); } });
                        });
                    }
                });
            });
        }
    });
};
</script>