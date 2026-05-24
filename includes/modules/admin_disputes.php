<h3>申诉仲裁</h3>
<table id="disputesTable" lay-filter="disputesTable"></table>
<script>
window.initAdmindisputesModule = function() {
    if (document.getElementById('disputesTable').hasAttribute('lay-render')) return;
    layui.use('table', function(){
        var table = layui.table;
        table.render({
            elem: '#disputesTable',
            url: 'api/admin.php?action=list_disputes',
            parseData: function(res){
                return {
                    code: (res.code !== undefined) ? res.code : (res.success ? 0 : 1),
                    count: res.count || res.total || (res.data ? res.data.length : 0),
                    data: res.data || []
                };
            },
            cols: [[
                {field:'order_no', title:'订单号', minWidth:100},
                {field:'filed_name', title:'申诉人', minWidth:80},
                {field:'reason', title:'原因', minWidth:120, templet: function(d){return d.reason||'-';}},
                {title:'操作', width:170, templet: function(d){
                    var btn = '<div class="btn-text-group">';
                    btn += '<a class="layui-btn layui-btn-xs" onclick="resolveDispute('+d.id+',\'refund\')">退款</a> ';
                    btn += '<a class="layui-btn layui-btn-xs layui-btn-warm" onclick="resolveDispute('+d.id+',\'pay_booster\')">结算</a> ';
                    if(isSuperAdmin) btn += '<a class="layui-btn layui-btn-xs layui-btn-danger" onclick="confirmDelete(\'api/admin.php\', {action:\'delete_dispute\', dispute_id:'+d.id+'}, \'disputesTable\')">删除</a>';
                    btn += '</div>';
                    return btn;
                }}
            ]]
        });

        window.resolveDispute = function(id, resolution){
            fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'resolve_dispute', dispute_id:id, resolution:resolution})})
            .then(r=>r.json()).then(res=>{
                if(res.code===0 || res.success){ Toast.success(res.msg||res.message); table.reload('disputesTable'); }
                else Toast.error(res.msg||res.message);
            });
        };
    });
};
</script>