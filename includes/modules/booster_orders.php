<h3>可接订单</h3>
<table id="openOrdersTable" lay-filter="openOrdersTable"></table>
<script>
window.initBoosterordersModule = function() {
    if (document.getElementById('openOrdersTable').hasAttribute('lay-render')) return;
    layui.use('table', function(){
        var table = layui.table;
        table.render({
            elem: '#openOrdersTable',
            url: 'api/order.php?action=list&role=booster',
            parseData: function(res){
                var data = res.data || res.orders || [];
                var count = res.count || res.total || data.length;
                return {
                    code: res.success || res.code === 0 ? 0 : 1,
                    msg: res.message || '',
                    count: count,
                    data: data
                };
            },
            cols: [[
                {field:'order_no', title:'订单号', minWidth:120},
                {field:'game_type', title:'类型'},
                {field:'amount', title:'金额', templet: '<span>¥{{d.amount}}</span>'},
                {field:'user_name', title:'用户'},
                {field:'created_at', title:'时间', templet: '<div>{{layui.util.toDateString(d.created_at, "yyyy-MM-dd HH:mm:ss")}}</div>'},
                {title:'操作', width:100, templet: function(d){
                    return '<a class="layui-btn layui-btn-xs" lay-event="accept">抢单</a>';
                }}
            ]],
            page: true
        });

        table.on('tool(openOrdersTable)', function(obj){
            var data = obj.data;
            if(obj.event === 'accept'){
                layer.confirm('确定接单？', function(i){
                    fetch('api/order.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'accept', order_id:data.id})})
                    .then(r=>r.json()).then(res=>{
                        if(res.success){ Toast.success('接单成功'); table.reload('openOrdersTable'); }
                        else Toast.error(res.message);
                    });
                    layer.close(i);
                });
            }
        });
    });
};
</script>