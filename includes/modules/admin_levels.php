<h3>打手等级</h3>
<button class="layui-btn layui-btn-sm" id="addLevelBtn">新增等级</button>
<table id="levelsTable" lay-filter="levelsTable"></table>
<script>
window.initAdminlevelsModule = function() {
    if (document.getElementById('levelsTable').hasAttribute('lay-render')) return;
    layui.use(['table','form'], function(){
        var table = layui.table, form = layui.form;
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
                {field:'name', title:'等级名称', minWidth:80},
                {field:'min_orders', title:'最少订单', minWidth:80},
                {field:'min_rating', title:'最低评分', minWidth:80},
                {field:'deposit_required', title:'保证金', minWidth:80},
                {field:'description', title:'描述', minWidth:100, templet: function(d){return d.description||'-';}},
                {title:'操作', width:140, templet: function(d){
                    var btn = '<div class="btn-text-group">';
                    btn += '<a class="layui-btn layui-btn-xs layui-btn-normal" onclick="editLevel('+d.id+')">编辑</a> ';
                    if(isSuperAdmin) btn += '<a class="layui-btn layui-btn-xs layui-btn-danger" onclick="deleteLevel('+d.id+')">删除</a>';
                    btn += '</div>';
                    return btn;
                }}
            ]]
        });

        $('#addLevelBtn').click(function(){ editLevel(0); });

        window.editLevel = function(id){
            fetch('api/admin.php?action=list_booster_levels').then(r=>r.json()).then(res=>{
                if(res.code!==0 && !res.success) return;
                var data = id ? (res.data.find(item=>item.id==id) || {}) : {};
                layer.open({
                    type:1, title: id?'编辑等级':'新增等级', area:'450px',
                    content: `<div style="padding:20px;" class="layui-form">
                        <div class="layui-form-item"><label>名称</label><input type="text" id="level_name" value="${data.name||''}" class="layui-input"></div>
                        <div class="layui-form-item"><label>最少订单数</label><input type="number" id="level_min_orders" value="${data.min_orders||0}" class="layui-input"></div>
                        <div class="layui-form-item"><label>最低评分</label><input type="number" id="level_min_rating" value="${data.min_rating||5.0}" step="0.1" class="layui-input"></div>
                        <div class="layui-form-item"><label>保证金</label><input type="number" id="level_deposit" value="${data.deposit_required||0}" step="0.01" class="layui-input"></div>
                        <div class="layui-form-item"><label>描述</label><textarea id="level_desc" class="layui-textarea">${data.description||''}</textarea></div>
                        <button class="layui-btn layui-btn-fluid" onclick="saveLevel(${id})">保存</button>
                    </div>`
                });
                form.render();
            });
        };

        window.saveLevel = function(id){
            var data = {action:'save_booster_level', id, name:$('#level_name').val(), min_orders:$('#level_min_orders').val(), min_rating:$('#level_min_rating').val(), deposit_required:$('#level_deposit').val(), description:$('#level_desc').val()};
            fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)})
            .then(r=>r.json()).then(res=>{
                if(res.code===0 || res.success){ Toast.success(res.msg||res.message); layer.closeAll(); table.reload('levelsTable'); }
                else Toast.error(res.msg||res.message);
            });
        };

        window.deleteLevel = function(id){
            if(!isSuperAdmin){ Toast.warning('仅超级管理员可操作'); return; }
            layer.confirm('确定删除该等级？', function(i){
                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'delete_booster_level', level_id:id})})
                .then(r=>r.json()).then(res=>{
                    if(res.code===0 || res.success){ Toast.success(res.msg||res.message); table.reload('levelsTable'); }
                    else Toast.error(res.msg||res.message);
                });
                layer.close(i);
            });
        };
    });
};
</script>