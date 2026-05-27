<h3>我的订单</h3>
<table id="userOrderTable" lay-filter="userOrderTable"></table>

<script type="text/html" id="userOrderOperateTpl">
    <button class="layui-btn layui-btn-xs layui-btn-primary" lay-event="operate">
        操作 <i class="layui-icon layui-icon-down"></i>
    </button>
</script>

<script>
// 后备定义：确保 formatTime 一定存在（即使 main.js 未加载）
if (typeof formatTime === 'undefined') {
    window.formatTime = function(datetime) {
        if (!datetime) return '-';
        if (typeof datetime === 'string' && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(datetime)) {
            datetime = datetime.replace(' ', 'T') + 'Z';
        }
        var d = new Date(datetime);
        if (isNaN(d.getTime())) return '-';
        var year = d.getFullYear(), month = String(d.getMonth()+1).padStart(2,'0'), day = String(d.getDate()).padStart(2,'0');
        var hours = String(d.getHours()).padStart(2,'0'), minutes = String(d.getMinutes()).padStart(2,'0'), seconds = String(d.getSeconds()).padStart(2,'0');
        return year + '-' + month + '-' + day + ' ' + hours + ':' + minutes + ':' + seconds;
    };
}

window.initMyordersModule = function() {
    if (document.getElementById('userOrderTable').hasAttribute('lay-render')) return;
    layui.use(['table', 'layer', 'dropdown'], function(){
        var table = layui.table, layer = layui.layer, dropdown = layui.dropdown;

        table.render({
            elem: '#userOrderTable',
            url: 'api/order.php?action=list_my_orders',
            parseData: function(res){
                return {
                    code: res.code === 0 ? 0 : 1,
                    count: res.count || 0,
                    data: res.data || []
                };
            },
            cols: [[
                {field:'order_no', title:'订单号', minWidth:250},
                {field:'game_type', title:'游戏类型', minWidth:100},
                {field:'amount', title:'金额', minWidth:100, templet: '<span>¥{{d.amount}}</span>'},
                // 状态列使用函数回调，避免模板报错
                {field:'status', title:'状态', minWidth:100, templet: function(d){
                    return '<span class="layui-badge ' + getStatusClass(d.status, d.payment_status) + '">' + getStatusText(d.status, d.payment_status) + '</span>';
                }},
                {field:'booster_name', title:'打手', minWidth:100, templet: function(d){ return d.booster_name || '-'; }},
                {field:'created_at', title:'下单时间', minWidth:180, templet: function(d){ return formatTime(d.created_at); }},
                {title:'操作', width:90, templet: '#userOrderOperateTpl'}
            ]],
            page: true
        });

        table.on('tool(userOrderTable)', function(obj){
            var data = obj.data;
            if(obj.event === 'operate'){
                var menuItems = [];
                if(data.payment_status === 'unpaid' && data.status !== 'cancelled'){
                    menuItems.push({title: '去付款', id: 'pay'});
                    menuItems.push({title: '取消订单', id: 'cancel'});
                } else if(data.payment_status === 'paid' && data.status === 'pending'){
                    menuItems.push({title: '取消订单', id: 'cancel'});
                }
                if(menuItems.length === 0){
                    menuItems.push({title: '无可用操作', id: 'none', disabled: true});
                }
                dropdown.render({
                    elem: this,
                    id: 'userOrder-' + data.id,
                    data: menuItems,
                    show: true,
                    align: 'right',
                    click: function(menuData){
                        var action = menuData.id;
                        if(action === 'cancel'){
                            layer.confirm('确定取消该订单吗？', function(index){
                                fetch('api/order.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'cancel_order', order_id: data.id})})
                                .then(r => r.json()).then(res => {
                                    if(res.code===0 || res.success){
                                        table.reload('userOrderTable');
                                        layer.msg(res.msg || '取消成功', {icon:1});
                                    } else {
                                        layer.msg(res.msg || '取消失败', {icon:2});
                                    }
                                });
                                layer.close(index);
                            });
                        } else if(action === 'pay'){
                            fetch('api/order.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'pay', order_id: data.id})})
                            .then(r => r.json()).then(res => {
                                if(res.code===0 || res.success){
                                    table.reload('userOrderTable');
                                    layer.msg(res.msg || '支付成功', {icon:1});
                                } else {
                                    layer.msg(res.msg || res.message || '支付失败', {icon:2});
                                }
                            });
                        }
                        dropdown.close('userOrder-' + data.id);
                    }
                });
            }
        });
    });
};
</script>