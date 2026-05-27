<h3>我的代练</h3>
<table id="boosterProgressTable" lay-filter="boosterProgressTable"></table>

<script type="text/html" id="progressOperateTpl">
    <div class="layui-btn-group">
        <button class="layui-btn layui-btn-xs layui-btn-normal" lay-event="upload">上传进度</button>
        <button class="layui-btn layui-btn-xs layui-btn-success" lay-event="complete">结单</button>
        <button class="layui-btn layui-btn-xs layui-btn-danger" lay-event="cancel">取消接单</button>
    </div>
</script>

<script>
window.initBoosterprogressModule = function() {
    if (document.getElementById('boosterProgressTable').hasAttribute('lay-render')) return;
    layui.use(['table', 'layer', 'form'], function(){
        var table = layui.table, layer = layui.layer, form = layui.form;

        table.render({
            elem: '#boosterProgressTable',
            url: 'api/order.php?action=my_booster_orders',
            parseData: function(res){
                return {
                    code: res.success ? 0 : 1,
                    count: res.orders ? res.orders.length : 0,
                    data: res.orders || []
                };
            },
            cols: [[
                {field:'order_no', title:'订单号', minWidth:250},
                {field:'game_type', title:'游戏类型', minWidth:100},
                {field:'user_name', title:'用户', minWidth:100},
                {field:'amount', title:'金额', minWidth:100, templet: '<span>¥{{d.amount}}</span>'},
                {field:'status', title:'状态', minWidth:100, templet: function(d){
                    if(d.status === 'accepted') return '<span class="layui-badge layui-bg-blue">已接单</span>';
                    if(d.status === 'in_progress') return '<span class="layui-badge layui-bg-green">代练中</span>';
                    return '<span class="layui-badge">'+d.status+'</span>';
                }},
                {field:'created_at', title:'接单时间', minWidth:180, templet: '<div>{{layui.util.toDateString(d.accepted_at, "yyyy-MM-dd HH:mm:ss")}}</div>'},
                {title:'操作', width:220, templet: '#progressOperateTpl'}
            ]],
            page: false
        });

        table.on('tool(boosterProgressTable)', function(obj){
            var data = obj.data;
            if(obj.event === 'upload'){
                layer.open({
                    type: 1,
                    title: '上传进度 - ' + data.order_no,
                    area: '400px',
                    content: '<div style="padding:20px;" class="layui-form">' +
                        '<div class="layui-form-item"><label>描述</label><textarea id="progressDesc" class="layui-textarea"></textarea></div>' +
                        '<div class="layui-form-item"><label>截图</label><input type="file" id="progressScreenshot"></div>' +
                        '<button class="layui-btn layui-btn-fluid" id="submitProgressBtn">提交</button>' +
                    '</div>',
                    success: function(layero, index){
                        form.render();
                        $('#submitProgressBtn').click(function(){
                            var desc = $('#progressDesc').val().trim();
                            var file = document.getElementById('progressScreenshot').files[0];
                            var formData = new FormData();
                            formData.append('action', 'upload_progress');
                            formData.append('order_id', data.id);
                            formData.append('description', desc);
                            if(file) formData.append('screenshot', file);

                            fetch('api/order.php', {method:'POST', body:formData})
                            .then(r => r.json())
                            .then(res => {
                                if(res.success){
                                    layer.msg('进度已更新', {icon:1});
                                    layer.close(index);
                                    table.reload('boosterProgressTable');
                                } else {
                                    layer.msg(res.message || '上传失败', {icon:2});
                                }
                            });
                        });
                    }
                });
            } else if(obj.event === 'complete'){
                layer.confirm('确认订单已完成？将结算代练费。', function(index){
                    fetch('api/order.php', {
                        method:'POST',
                        headers:{'Content-Type':'application/json'},
                        body:JSON.stringify({action:'complete', order_id: data.id})
                    })
                    .then(r => r.json())
                    .then(res => {
                        if(res.success){
                            layer.msg(res.message || '已结算', {icon:1});
                            table.reload('boosterProgressTable');
                        } else {
                            layer.msg(res.message || '操作失败', {icon:2});
                        }
                    });
                    layer.close(index);
                });
            } else if(obj.event === 'cancel'){
                layer.confirm('确定取消接单吗？订单将退回待接单状态。', function(index){
                    fetch('api/order.php', {
                        method:'POST',
                        headers:{'Content-Type':'application/json'},
                        body:JSON.stringify({action:'cancel_accept', order_id: data.id})
                    })
                    .then(r => r.json())
                    .then(res => {
                        if(res.success){
                            layer.msg(res.message || '已取消接单', {icon:1});
                            table.reload('boosterProgressTable');
                        } else {
                            layer.msg(res.message || '操作失败', {icon:2});
                        }
                    });
                    layer.close(index);
                });
            }
        });
    });
};
</script>