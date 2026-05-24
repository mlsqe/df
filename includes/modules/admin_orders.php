<h3>订单管理</h3>
<div class="layui-form" style="margin-bottom:10px;">
    <div class="layui-inline"><input type="text" id="orderKeyword" placeholder="订单号/用户名" class="layui-input" style="width:200px;"></div>
    <div class="layui-inline">
        <select id="orderStatus">
            <option value="">全部状态</option>
            <option value="pending">待接单</option>
            <option value="accepted">已接单</option>
            <option value="in_progress">代练中</option>
            <option value="completed">已完成</option>
            <option value="cancelled">已取消</option>
            <option value="disputed">争议中</option>
        </select>
    </div>
    <button class="layui-btn layui-btn-sm" id="searchOrderBtn"><i class="layui-icon layui-icon-search"></i> 搜索</button>
    <button class="layui-btn layui-btn-sm layui-btn-primary" id="resetOrderBtn">重置</button>
</div>
<table id="adminOrdersTable" lay-filter="adminOrdersTable"></table>

<script type="text/html" id="adminStatusTpl">
    {{# if(d.payment_status === 'unpaid'){ }}
        <span class="layui-badge layui-bg-orange">待付款</span>
    {{# } else if(d.status === 'pending'){ }}
        <span class="layui-badge layui-bg-cyan">待接单</span>
    {{# } else if(d.status === 'accepted'){ }}
        <span class="layui-badge layui-bg-blue">已接单</span>
    {{# } else if(d.status === 'in_progress'){ }}
        <span class="layui-badge layui-bg-green">代练中</span>
    {{# } else if(d.status === 'completed'){ }}
        <span class="layui-badge">已完成</span>
    {{# } else if(d.status === 'cancelled'){ }}
        <span class="layui-badge layui-bg-black">已取消</span>
    {{# } else if(d.status === 'disputed'){ }}
        <span class="layui-badge layui-bg-red">争议中</span>
    {{# } }}
</script>

<script>
window.initAdminordersModule = function() {
    if (document.getElementById('adminOrdersTable').hasAttribute('lay-render')) return;
    layui.use(['table','layer','form','dropdown'], function(){
        var table = layui.table, layer = layui.layer, form = layui.form, dropdown = layui.dropdown;

        table.render({
            elem: '#adminOrdersTable',
            url: 'api/admin.php?action=list_orders',
            parseData: function(res){
                // 不再强行补默认值，完全信任后端数据
                return {
                    code: (res.code !== undefined) ? res.code : (res.success ? 0 : 1),
                    count: res.count || res.total || (res.data ? res.data.length : 0),
                    data: res.data || []
                };
            },
            cols: [[
                {field:'order_no', title:'订单号', minWidth:160},
                {field:'user_name', title:'用户', minWidth:100},
                {field:'amount', title:'金额', templet: '<span>¥{{d.amount}}</span>'},
                {field:'status', title:'状态', templet: '#adminStatusTpl'},
                {field:'booster_name', title:'打手', templet: function(d){return d.booster_name||'-';}},
                {field:'created_at', title:'时间', templet: '<div>{{layui.util.toDateString(d.created_at, "yyyy-MM-dd HH:mm:ss")}}</div>'},
                {title:'操作', width:100, templet: '#operateTpl'}
            ]],
            page: true
        });

        // 搜索/重置
        $('#searchOrderBtn').click(function(){
            table.reload('adminOrdersTable', {where:{keyword:$('#orderKeyword').val(), status:$('#orderStatus').val()}, page:{curr:1}});
        });
        $('#resetOrderBtn').click(function(){
            $('#orderKeyword').val(''); $('#orderStatus').val('');
            table.reload('adminOrdersTable', {where:{}, page:{curr:1}});
        });

        // 工具条事件
        table.on('tool(adminOrdersTable)', function(obj){
            var data = obj.data;
            if(obj.event === 'operate'){
                // 根据订单状态动态生成下拉菜单项
                var menuItems = [];
                menuItems.push({title:'📋 详情', id:'detail'});
                menuItems.push({title:'✏️ 编辑', id:'edit'});
                if(data.payment_status === 'unpaid'){
                    menuItems.push({title:'💰 付款', id:'pay'});
                }
                if(data.payment_status === 'paid' && data.status !== 'completed' && data.status !== 'cancelled'){
                    menuItems.push({title:'💸 退款', id:'refund'});
                }
                if(data.status !== 'completed' && data.status !== 'cancelled'){
                    menuItems.push({title:'❌ 取消', id:'cancel'});
                }
                if(isSuperAdmin){
                    menuItems.push({title:'🗑️ 删除', id:'del'});
                }

                // 渲染下拉菜单并立即显示
                dropdown.render({
                    elem: this,
                    id: 'order-drop-' + data.id,
                    data: menuItems,
                    show: true,
                    align: 'right',
                    click: function(menuData){
                        var action = menuData.id;
                        if(action === 'detail'){
                            fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'order_detail', order_id:data.id})})
                            .then(r=>r.json()).then(res=>{
                                if((res.code===0) || res.success){
                                    var o = res.data || res.order;
                                    var html = `<div style="padding:10px;"><p>订单号: ${o.order_no}</p><p>用户: ${o.user_name}</p><p>金额: ¥${o.amount}</p><p>状态: ${o.status}</p><p>打手: ${o.booster_name||'无'}</p><hr>进度: ${(o.progress||[]).map(p=>`<div>${p.description} (${p.created_at})</div>`).join('')||'无'}</div>`;
                                    layer.open({type:1, title:'订单详情', area:'600px', content: html});
                                } else layer.msg(res.msg||res.message, {icon:2});
                            });
                        } else if(action === 'edit'){
                            fetch('api/admin.php?action=list_boosters').then(r=>r.json()).then(boosterRes=>{
                                var list = (boosterRes.data||[]);
                                var options = list.map(b=>`<option value="${b.id}" ${b.id==data.booster_id?'selected':''}>${b.real_name} (ID:${b.id})</option>`).join('');
                                layer.open({
                                    type:1, title:'编辑订单 #'+data.order_no, area:'500px',
                                    content:`<div style="padding:20px;" class="layui-form">
                                        <div class="layui-form-item"><label>金额</label><input type="number" id="edit_amount" value="${data.amount}" step="0.01" class="layui-input"></div>
                                        <div class="layui-form-item"><label>状态</label>
                                            <select id="edit_status">
                                                <option value="pending" ${data.status=='pending'?'selected':''}>待接单</option>
                                                <option value="accepted" ${data.status=='accepted'?'selected':''}>已接单</option>
                                                <option value="in_progress" ${data.status=='in_progress'?'selected':''}>代练中</option>
                                                <option value="completed" ${data.status=='completed'?'selected':''}>已完成</option>
                                                <option value="cancelled" ${data.status=='cancelled'?'selected':''}>已取消</option>
                                                <option value="disputed" ${data.status=='disputed'?'selected':''}>争议中</option>
                                            </select>
                                            <div class="layui-form-mid layui-word-aux">注：付款状态请通过“付款”或“退款”操作改变</div>
                                        </div>
                                        <div class="layui-form-item"><label>打手</label><select id="edit_booster_id"><option value="">不分配</option>${options}</select></div>
                                        <button class="layui-btn layui-btn-fluid" onclick="saveOrderEdit(${data.id})">保存修改</button>
                                    </div>`
                                });
                                form.render();
                            });
                        } else if(action === 'pay'){
                            layer.confirm('确定为此订单付款吗？', function(i){
                                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'pay_order', order_id:data.id})})
                                .then(r=>r.json()).then(res=>{ layer.msg(res.msg||res.message,{icon:(res.code===0||res.success)?1:2}); if((res.code===0||res.success)) table.reload('adminOrdersTable'); });
                                layer.close(i);
                            });
                        } else if(action === 'refund'){
                            layer.confirm('确定退款并取消订单？', function(i){
                                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'refund_order', order_id:data.id})})
                                .then(r=>r.json()).then(res=>{ layer.msg(res.msg||res.message,{icon:(res.code===0||res.success)?1:2}); if((res.code===0||res.success)) table.reload('adminOrdersTable'); });
                                layer.close(i);
                            });
                        } else if(action === 'cancel'){
                            layer.confirm('确定取消订单？', function(i){
                                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'cancel_order', order_id:data.id})})
                                .then(r=>r.json()).then(res=>{ layer.msg(res.msg||res.message,{icon:(res.code===0||res.success)?1:2}); if((res.code===0||res.success)) table.reload('adminOrdersTable'); });
                                layer.close(i);
                            });
                        } else if(action === 'del'){
                            if(!isSuperAdmin){ Toast.warning('仅超级管理员可操作'); return; }
                            layer.confirm('确定删除订单？此操作不可恢复！', {icon:3, title:'警告'}, function(i){
                                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'delete_order', order_id:data.id})})
                                .then(r=>r.json()).then(res=>{ layer.msg(res.msg||res.message,{icon:(res.code===0||res.success)?1:2}); if((res.code===0||res.success)) table.reload('adminOrdersTable'); });
                                layer.close(i);
                            });
                        }
                        dropdown.close('order-drop-' + data.id);
                    }
                });
            }
        });

        // 保存编辑
        window.saveOrderEdit = function(orderId){
            var amount = $('#edit_amount').val(), status = $('#edit_status').val(), boosterId = $('#edit_booster_id').val();
            fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'update_order', order_id:orderId, amount, status, booster_id:boosterId})})
            .then(r=>r.json()).then(res=>{ layer.msg(res.msg||res.message,{icon:(res.code===0||res.success)?1:2}); if((res.code===0||res.success)){ layer.closeAll(); table.reload('adminOrdersTable'); } });
        };
    });
};
</script>

<!-- 行工具栏模板 -->
<script type="text/html" id="operateTpl">
    <button class="layui-btn layui-btn-xs layui-btn-primary" lay-event="operate">
        操作 <i class="layui-icon layui-icon-down"></i>
    </button>
</script>