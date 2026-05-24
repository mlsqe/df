<h3>打手审核</h3>
<table id="boostersTable" lay-filter="boostersTable"></table>
<script>
window.initAdminboostersModule = function() {
    if (document.getElementById('boostersTable').hasAttribute('lay-render')) return;
    layui.use('table', function(){
        var table = layui.table;
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
                {field:'username', title:'用户名', minWidth:80},
                {field:'real_name', title:'姓名', minWidth:80, templet: function(d){return d.real_name||'-';}},
                {field:'phone', title:'手机', minWidth:80, templet: function(d){return d.phone||'-';}},
                {field:'wechat', title:'微信', templet: function(d){return d.wechat||'-';}},
                {field:'qq', title:'QQ', templet: function(d){return d.qq||'-';}},
                {field:'register_mode', title:'注册方式', minWidth:80, templet: function(d){
                    if (d.register_mode === 'free') return '<span class="layui-badge layui-bg-green">自由注册</span>';
                    if (d.register_mode === 'invite') return '<span class="layui-badge layui-bg-blue">邀请码</span>';
                    if (d.register_mode === 'deposit') return '<span class="layui-badge layui-bg-orange">押金</span>';
                    if (d.register_mode === 'invite_or_deposit') return '<span class="layui-badge layui-bg-cyan">邀请码/押金</span>';
                    return '<span class="layui-badge">未知</span>';
                }},
                {field:'deposit_status', title:'保证金', templet: function(d){return d.deposit_status||'未缴纳';}},
                {field:'used_invite_code', title:'使用邀请码', templet: function(d){return d.used_invite_code||'-';}},
                {field:'status', title:'状态', templet: '<span class="layui-badge layui-bg-{{d.status=="active"?"green":"orange"}}">{{d.status}}</span>'},
                {title:'操作', width:150, templet: function(d){
                    var btn = '<div class="btn-text-group">';
                    if(d.status === 'pending'){
                        btn += '<a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="approve">通过</a> ';
                        btn += '<a class="layui-btn layui-btn-xs layui-btn-danger" lay-event="reject">拒绝</a> ';
                    }
                    if(isSuperAdmin) btn += '<a class="layui-btn layui-btn-xs layui-btn-danger" onclick="confirmDelete(\'api/admin.php\', {action:\'delete_booster\', booster_id:'+d.id+'}, \'boostersTable\')">删除</a>';
                    btn += '</div>';
                    return btn;
                }}
            ]]
        });

        // 工具条事件
        table.on('tool(boostersTable)', function(obj){
            var data = obj.data;
            if(obj.event === 'approve'){
                layer.confirm('确定通过该打手审核吗？', function(i){
                    fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'approve_booster', booster_id: data.id})})
                    .then(r=>r.json()).then(res=>{
                        if(res.code===0 || res.success){ Toast.success(res.msg||res.message); table.reload('boostersTable'); }
                        else Toast.error(res.msg||res.message);
                    });
                    layer.close(i);
                });
            } else if(obj.event === 'reject'){
                layer.prompt({title: '请输入拒绝原因', formType: 2}, function(text, index){
                    fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'reject_booster', booster_id: data.id, reason: text})})
                    .then(r=>r.json()).then(res=>{
                        if(res.code===0 || res.success){ Toast.success(res.msg||res.message); table.reload('boostersTable'); }
                        else Toast.error(res.msg||res.message);
                    });
                    layer.close(index);
                });
            }
        });

        window.confirmDelete = window.confirmDelete || function(url, data, reloadElem){
            if(!isSuperAdmin){ Toast.warning('仅超级管理员可操作'); return; }
            layer.confirm('确定删除吗？此操作不可恢复！', {icon:3, title:'警告'}, function(index){
                fetch(url, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)})
                .then(r=>r.json()).then(res=>{
                    if(res.code===0 || res.success){ Toast.success(res.msg||res.message); table.reload(reloadElem); }
                    else Toast.error(res.msg||res.message);
                });
                layer.close(index);
            });
        };
    });
};
</script>