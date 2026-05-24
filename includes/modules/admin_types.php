<h3>代练类型</h3>
<button class="layui-btn layui-btn-sm" id="addTypeBtn">新增类型</button>
<table id="gameTypesTable" lay-filter="gameTypesTable"></table>
<script>
window.initAdmintypesModule = function() {
    if (document.getElementById('gameTypesTable').hasAttribute('lay-render')) return;
    layui.use(['table','form'], function(){
        var table = layui.table, form = layui.form;
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
                {field:'name', title:'名称', minWidth:80},
                {field:'unit_label', title:'单位', minWidth:60},
                {field:'price_per_unit', title:'单价', minWidth:70},
                {field:'status', title:'状态', templet: '<span class="layui-badge layui-bg-{{d.status=="active"?"green":"gray"}}">{{d.status}}</span>'},
                {title:'操作', width:140, templet: function(d){
                    var btn = '<div class="btn-text-group">';
                    btn += '<a class="layui-btn layui-btn-xs layui-btn-normal" onclick="editType('+d.id+')">编辑</a> ';
                    if(isSuperAdmin) btn += '<a class="layui-btn layui-btn-xs layui-btn-danger" onclick="confirmDelete(\'api/admin.php\', {action:\'delete_game_type\', id:'+d.id+'}, \'gameTypesTable\')">删除</a>';
                    btn += '</div>';
                    return btn;
                }}
            ]]
        });

        $('#addTypeBtn').click(function(){ editType(0); });

        window.editType = function(id){
            fetch('api/admin.php?action=list_game_types').then(r=>r.json()).then(res=>{
                if(res.code!==0 && !res.success) return;
                var data = id ? (res.data.find(item=>item.id==id) || {}) : {};
                layer.open({
                    type:1, title: id?'编辑类型':'新增类型', area:'400px',
                    content: `<div style="padding:20px;" class="layui-form">
                        <div class="layui-form-item"><label>名称</label><input type="text" id="type_name" value="${data.name||''}" class="layui-input"></div>
                        <div class="layui-form-item"><label>单位</label><select id="type_unit"><option value="K" ${data.unit_label=='K'?'selected':''}>K</option><option value="M" ${data.unit_label=='M'?'selected':''}>M</option><option value="B" ${data.unit_label=='B'?'selected':''}>B</option></select></div>
                        <div class="layui-form-item"><label>单价</label><input type="number" id="type_price" value="${data.price_per_unit||''}" step="0.01" class="layui-input"></div>
                        <div class="layui-form-item"><label>状态</label><select id="type_status"><option value="active" ${data.status=='active'?'selected':''}>启用</option><option value="disabled" ${data.status=='disabled'?'selected':''}>禁用</option></select></div>
                        <button class="layui-btn layui-btn-fluid" onclick="saveType(${id})">保存</button>
                    </div>`
                });
                form.render();
            });
        };

        window.saveType = function(id){
            var data = {action:'save_game_type', id, name:$('#type_name').val(), unit_label:$('#type_unit').val(), price_per_unit:$('#type_price').val(), status:$('#type_status').val()};
            fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)})
            .then(r=>r.json()).then(res=>{
                if(res.code===0 || res.success){ Toast.success(res.msg||res.message); layer.closeAll(); table.reload('gameTypesTable'); }
                else Toast.error(res.msg||res.message);
            });
        };
    });
};
</script>