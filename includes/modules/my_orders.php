<h3>我的订单</h3>
<div class="layui-row">
    <div class="layui-col-xs12">
        <div class="table-responsive">
            <table id="userOrderTable" lay-filter="userOrderTable"></table>
        </div>
    </div>
</div>
<script type="text/html" id="userStatusTpl">
    {{# if(d.payment_status === 'unpaid'){ }}<span class="layui-badge layui-bg-orange">待付款</span>
    {{# } else if(d.status === 'pending'){ }}<span class="layui-badge layui-bg-cyan">待接单</span>
    {{# } else if(d.status === 'accepted'){ }}<span class="layui-badge layui-bg-blue">已接单</span>
    {{# } else if(d.status === 'in_progress'){ }}<span class="layui-badge layui-bg-green">代练中</span>
    {{# } else if(d.status === 'completed'){ }}<span class="layui-badge">已完成</span>
    {{# } else if(d.status === 'cancelled'){ }}<span class="layui-badge layui-bg-black">已取消</span>
    {{# } else if(d.status === 'disputed'){ }}<span class="layui-badge layui-bg-red">争议中</span>{{# } }}
</script>
<script>
window.initMyordersModule = function() {
    if (document.getElementById('userOrderTable').hasAttribute('lay-render')) return;
    layui.use('table', function(){
        var table = layui.table;
        table.render({
            elem: '#userOrderTable',
            url: 'api/order.php?action=list&role=user',
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
                {field:'order_no', title:'订单号', width:160},
                {field:'game_type', title:'类型'},
                {field:'amount', title:'金额', templet: '<span>¥{{d.amount}}</span>'},
                {field:'status', title:'状态', templet: '#userStatusTpl'},
                {fixed:'right', title:'操作', templet: function(d){
                    var btn = '';
                    if(d.payment_status === 'unpaid') {
                        btn += '<a class="layui-btn layui-btn-xs layui-btn-warm" lay-event="pay">付款</a> ';
                    } else if(d.status === 'in_progress') {
                        btn += '<a class="layui-btn layui-btn-xs" lay-event="progress">进度</a> ';
                        btn += '<a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="complete">验收</a> ';
                    }
                    if(['accepted','in_progress'].includes(d.status)) {
                        btn += '<a class="layui-btn layui-btn-xs layui-btn-danger" lay-event="dispute">申诉</a>';
                    }
                    return btn;
                }}
            ]],
            page: true
        });

        table.on('tool(userOrderTable)', function(obj){
            var data = obj.data;
            if(obj.event === 'pay'){
                layer.confirm('确认付款？', function(i){
                    fetch('api/order.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'pay',order_id:data.id})})
                    .then(r=>r.json()).then(res=>{
                        if(res.success){ Toast.success(res.message); table.reload('userOrderTable'); }
                        else Toast.error(res.message);
                    });
                    layer.close(i);
                });
            } else if(obj.event === 'progress'){
                fetch('api/order.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'get_progress',order_id:data.id})})
                .then(r=>r.json()).then(res=>{
                    var html = '';
                    if(res.success && res.progress){
                        res.progress.forEach(p=>{ html += `<div>${p.description} (${p.created_at})</div>`; });
                    }
                    layer.open({type:1, title:'进度', area:'400px', content:'<div style="padding:20px;">'+html+'</div>'});
                });
            } else if(obj.event === 'complete'){
                layer.confirm('确认验收？', function(i){
                    fetch('api/order.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'complete',order_id:data.id})})
                    .then(r=>r.json()).then(res=>{
                        if(res.success){ Toast.success(res.message); table.reload('userOrderTable'); }
                        else Toast.error(res.message);
                    });
                    layer.close(i);
                });
            } else if(obj.event === 'dispute'){
                layer.prompt({title:'申诉原因'}, function(value, index){
                    fetch('api/order.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'dispute',order_id:data.id,reason:value})})
                    .then(r=>r.json()).then(res=>{
                        if(res.success){ Toast.success(res.message); table.reload('userOrderTable'); }
                        else Toast.error(res.message);
                    });
                    layer.close(index);
                });
            }
        });
    });
};
</script>