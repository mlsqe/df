<h3>可接订单</h3>
<table id="boosterOrdersTable" lay-filter="boosterOrdersTable"></table>

<script type="text/html" id="boosterOrderOperateTpl">
    <button class="layui-btn layui-btn-xs layui-btn-success" lay-event="accept">接单</button>
</script>

<script>
window.initBoosterordersModule = function() {
    if (document.getElementById('boosterOrdersTable').hasAttribute('lay-render')) return;
    layui.use(['table', 'layer'], function(){
        var table = layui.table, layer = layui.layer;

        table.render({
            elem: '#boosterOrdersTable',
            url: 'api/order.php?action=list&role=booster',
            parseData: function(res){
                return {
                    code: res.success ? 0 : 1,
                    count: res.orders ? res.orders.length : 0,
                    data: res.orders || []
                };
            },
            cols: [[
                {field:'order_no', title:'订单号', minWidth:250},
                {field:'user_name', title:'用户', minWidth:100},
                {field:'amount', title:'金额', minWidth:100, templet: '<span>¥{{d.amount}}</span>'},
                {field:'status', title:'状态', minWidth:100, templet: '<span class="layui-badge layui-bg-cyan">待接单</span>'},
                {field:'created_at', title:'发布时间', minWidth:180, templet: '<div>{{layui.util.toDateString(d.created_at, "yyyy-MM-dd HH:mm:ss")}}</div>'},
                {title:'操作', width:90, templet: '#boosterOrderOperateTpl'}
            ]],
            page: true
        });

        table.on('tool(boosterOrdersTable)', function(obj){
            if(obj.event === 'accept'){
                var data = obj.data;
                layer.confirm('确定接单吗？', function(index){
                    fetch('api/order.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({action:'accept', order_id: data.id})
                    })
                    .then(r => r.json())
                    .then(res => {
                        if(res.success){
                            layer.msg(res.message, {icon:1});
                            table.reload('boosterOrdersTable');
                        } else {
                            layer.msg(res.message, {icon:2});
                        }
                    });
                    layer.close(index);
                });
            }
        });
    });
};
</script>