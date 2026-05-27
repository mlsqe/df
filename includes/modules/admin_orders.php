<h3>订单管理</h3>
<div class="layui-form search-container" style="margin-bottom:10px;">
    <div class="layui-inline search-item"><input type="text" id="orderKeyword" placeholder="订单号/用户名" class="layui-input"></div>
    <div class="layui-inline search-item">
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
    <div class="layui-inline btn-group">
        <button class="layui-btn layui-btn-sm" id="searchOrderBtn"><i class="layui-icon layui-icon-search"></i> 搜索</button>
        <button class="layui-btn layui-btn-sm layui-btn-primary" id="resetOrderBtn">重置</button>
    </div>
</div>

<div style="width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch;">
    <table id="adminOrdersTable" lay-filter="adminOrdersTable"></table>
</div>

<script type="text/html" id="operateTpl">
    <button class="layui-btn layui-btn-xs layui-btn-primary" lay-event="operate">
        操作 <i class="layui-icon layui-icon-down"></i>
    </button>
</script>

<style>
/* 针对搜索栏的移动端响应式补丁 */
.search-container { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.search-item { margin: 0 !important; width: 200px; }
.search-item .layui-input, .search-item .layui-form-select { width: 100% !important; }
.btn-group { margin: 0 !important; display: inline-flex; gap: 5px; }

@media screen and (max-width: 576px) {
    .search-container { display: block; }
    .search-item { display: block !important; width: 100% !important; margin-bottom: 8px !important; }
    .btn-group { display: flex !important; width: 100% !important; }
    .btn-group button { flex: 1; }
    
    /* 强力纠正 Layer 弹窗里的表单布局，防止被标签名或辅组文字顶变形 */
    .layui-layer-content .layui-form-item label { display: block; float: none; text-align: left; width: auto !important; padding: 5px 0; font-weight: bold; }
    .layui-layer-content .layui-form-item .layui-input-block { margin-left: 0 !important; }
}

/* ================== 核心改进：精准屏蔽操作列的 grid-down，保留其他列 ================== */
/* 1. 仅当操作列（通过data-field="operate"锁定）触发超长时，隐藏对应的小三角 */
td[data-field="operate"] .layui-table-grid-down,
.layui-table-fixed-r td[data-field="operate"] .layui-table-grid-down {
    display: none !important;
}

/* 2. 让操作列的内容溢出时可见，绝不触发隐藏截断判定 */
td[data-field="operate"] .layui-table-cell,
.layui-table-fixed-r td[data-field="operate"] .layui-table-cell {
    overflow: visible !important;
}

/* 3. 移动端/点击后引发的表格行、固定列的 hover 变色效果默认透明 */
.layui-table-view .layui-table tbody tr:hover,
.layui-table-hover,
.layui-table-click,
.layui-table-view .layui-table-fixed tbody tr:hover,
.layui-table-fixed .layui-table-hover {
    background-color: transparent !important;
}

/* 4. 精准恢复 PC 端：仅在大于 768px 的大屏环境下，恢复非操作列行的 hover 浅灰反馈 */
@media screen and (min-width: 769px) {
    .layui-table-view .layui-table tbody tr:hover,
    .layui-table-hover,
    .layui-table-view .layui-table-fixed tbody tr:hover {
        background-color: #FAFAFA !important;
    }
}

/* 5. 清除移动端点击按钮时，部分内核浏览器自带的半透明蓝色闪烁方块 */
.layui-table-cell, 
.layui-table-cell button,
.layui-btn {
    -webkit-tap-highlight-color: rgba(0, 0, 0, 0) !important;
}
</style>

<script>
window.initAdminordersModule = function() {
    if (document.getElementById('adminOrdersTable').hasAttribute('lay-render')) return;
    layui.use(['table','layer','form','dropdown'], function(){
        var table = layui.table, layer = layui.layer, form = layui.form, dropdown = layui.dropdown;

        table.render({
            elem: '#adminOrdersTable',
            url: 'api/admin.php?action=list_orders',
            parseData: function(res){
                return {
                    code: (res.code !== undefined) ? res.code : (res.success ? 0 : 1),
                    count: res.count || res.total || (res.data ? res.data.length : 0),
                    data: res.data || []
                };
            },
            cols: [[
                {field:'order_no', title:'订单号', minWidth:220},
                {field:'user_name', title:'用户', minWidth:100},
                {field:'amount', title:'金额', minWidth:90, templet: '<span>¥{{d.amount}}</span>'},
                {field:'status', title:'状态', minWidth:100, templet: function(d){
                    return '<span class="layui-badge ' + getStatusClass(d.status, d.payment_status) + '">' + getStatusText(d.status, d.payment_status) + '</span>';
                }},
                {field:'booster_name', title:'打手', minWidth:100, templet: function(d){return d.booster_name||'-';}},
                {field:'created_at', title:'时间', minWidth:150, templet: function(d){ return formatTime(d.created_at); }},
                /* 【重要修改】为操作列显式加上 field: 'operate'，以便 CSS 精确靶向定位 */
                {field:'operate', title:'操作', width:80, fixed: window.innerWidth > 768 ? 'right' : false, templet: '#operateTpl'} 
            ]],
            page: true,
            text: { none: '暂无订单数据' }
        });

        // 搜索/重置
        $('#searchOrderBtn').click(function(){
            table.reload('adminOrdersTable', {where:{keyword:$('#orderKeyword').val(), status:$('#orderStatus').val()}, page:{curr:1}});
        });
        $('#resetOrderBtn').click(function(){
            $('#orderKeyword').val(''); $('#orderStatus').val('');
            table.reload('adminOrdersTable', {where:{}, page:{curr:1}});
        });

        // 工具条事件（下拉菜单）
        table.on('tool(adminOrdersTable)', function(obj){
            var data = obj.data;
            if(obj.event === 'operate'){
                var menuItems = [
                    {title:'📋 详情', id:'detail'},
                    {title:'✏️ 编辑', id:'edit'}
                ];
                if(data.payment_status === 'unpaid') menuItems.push({title:'💰 付款', id:'pay'});
                if(data.payment_status === 'paid' && data.status !== 'completed' && data.status !== 'cancelled') menuItems.push({title:'💸 退款', id:'refund'});
                if(data.status !== 'completed' && data.status !== 'cancelled') menuItems.push({title:'❌ 取消', id:'cancel'});
                if(isSuperAdmin) menuItems.push({title:'🗑️ 删除', id:'del'});

                dropdown.render({
                    elem: this,
                    id: 'order-drop-' + data.id,
                    data: menuItems,
                    show: true,
                    align: 'right',
                    click: function(menuData){
                        var action = menuData.id;
                        
                        // == 动态分配弹窗宽高的算法 ==
                        var getResponsiveArea = function(defaultWidth, defaultHeight) {
                            if (window.innerWidth < 768) {
                                return ['95%', '85%']; 
                            }
                            return [defaultWidth, defaultHeight];
                        };

                        if(action === 'detail'){
                            fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'order_detail', order_id:data.id})})
                            .then(r=>r.json()).then(res=>{
                                if((res.code===0) || res.success){
                                    var o = res.data || res.order;
                                    var html = `<div style="padding:15px; font-size: 13px; line-height: 1.6;">
                                        <p><b>订单号:</b> ${o.order_no}</p>
                                        <p><b>用户:</b> ${o.user_name}</p>
                                        <p><b>金额:</b> <span style="color:#FF5722;">¥${o.amount}</span></p>
                                        <p><b>状态:</b> ${o.status}</p>
                                        <p><b>打手:</b> ${o.booster_name||'无'}</p>
                                        <hr class="layui-border-dashed">
                                        <p style="margin-bottom:5px;"><b>进度跟踪:</b></p>
                                        <div style="max-height:150px; overflow-y:auto; background:#f8f8f8; padding:8px; border-radius:4px;">
                                            ${(o.progress||[]).map(p=>`<div style="margin-bottom:6px; border-bottom:1px solid #eee; padding-bottom:4px;">${p.description} <br><small style="color:#999;">${p.created_at}</small></div>`).join('')||'<div style="color:#999;">暂无进度数据</div>'}
                                        </div>
                                    </div>`;
                                    
                                    layer.open({
                                        type: 1, 
                                        title: '订单详情', 
                                        area: getResponsiveArea('600px', '400px'), 
                                        content: html,
                                        shadeClose: true 
                                    });
                                } else layer.msg(res.msg||res.message, {icon:2});
                            });
                        } else if(action === 'edit'){
                            fetch('api/admin.php?action=list_boosters').then(r=>r.json()).then(boosterRes=>{
                                var list = (boosterRes.data||[]);
                                var options = list.map(b=>`<option value="${b.id}" ${b.id==data.booster_id?'selected':''}>${b.real_name} (ID:${b.id})</option>`).join('');
                                
                                layer.open({
                                    type: 1, 
                                    title: '编辑订单 #' + data.order_no, 
                                    area: getResponsiveArea('500px', 'auto'), 
                                    content: `<div style="padding:15px;" class="layui-form">
                                        <div class="layui-form-item">
                                            <label class="layui-form-label" style="padding-left:0; width:60px; text-align:left;">金额</label>
                                            <div class="layui-input-block" style="margin-left:60px;">
                                                <input type="number" id="edit_amount" value="${data.amount}" step="0.01" class="layui-input">
                                            </div>
                                        </div>
                                        <div class="layui-form-item">
                                            <label class="layui-form-label" style="padding-left:0; width:60px; text-align:left;">状态</label>
                                            <div class="layui-input-block" style="margin-left:60px;">
                                                <select id="edit_status">
                                                    <option value="pending" ${data.status=='pending'?'selected':''}>待接单</option>
                                                    <option value="accepted" ${data.status=='accepted'?'selected':''}>已接单</option>
                                                    <option value="in_progress" ${data.status=='in_progress'?'selected':''}>代练中</option>
                                                    <option value="completed" ${data.status=='completed'?'selected':''}>已完成</option>
                                                    <option value="cancelled" ${data.status=='cancelled'?'selected':''}>已取消</option>
                                                    <option value="disputed" ${data.status=='disputed'?'selected':''}>争议中</option>
                                                </select>
                                                <div style="font-size:11px; color:#999; margin-top:4px;">注：付款状态请通过“付款”或“退款”操作改变</div>
                                            </div>
                                        </div>
                                        <div class="layui-form-item">
                                            <label class="layui-form-label" style="padding-left:0; width:60px; text-align:left;">打手</label>
                                            <div class="layui-input-block" style="margin-left:60px;">
                                                <select id="edit_booster_id"><option value="">不分配</option>${options}</select>
                                            </div>
                                        </div>
                                        <div style="margin-top:20px;">
                                            <button class="layui-btn layui-btn-fluid" onclick="saveOrderEdit(${data.id})">保存修改</button>
                                        </div>
                                    </div>`,
                                    success: function(layero, index){
                                        form.render(); 
                                    }
                                });
                            });
                        } else if(action === 'pay'){
                            layer.confirm('确定为此订单付款吗？', {title:'提示'}, function(i){
                                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'pay_order', order_id:data.id})})
                                .then(r=>r.json()).then(res=>{ layer.msg(res.msg||res.message,{icon:(res.code===0||res.success)?1:2}); if((res.code===0||res.success)) table.reload('adminOrdersTable'); });
                                layer.close(i);
                            });
                        } else if(action === 'refund'){
                            layer.confirm('确定退款并取消订单？', {title:'提示'}, function(i){
                                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'refund_order', order_id:data.id})})
                                .then(r=>r.json()).then(res=>{ layer.msg(res.msg||res.message,{icon:(res.code===0||res.success)?1:2}); if((res.code===0||res.success)) table.reload('adminOrdersTable'); });
                                layer.close(i);
                            });
                        } else if(action === 'cancel'){
                            layer.confirm('确定取消订单？', {title:'提示'}, function(i){
                                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'cancel_order', order_id:data.id})})
                                .then(r=>r.json()).then(res=>{ layer.msg(res.msg||res.message,{icon:(res.code===0||res.success)?1:2}); if((res.code===0||res.success)) table.reload('adminOrdersTable'); });
                                layer.close(i);
                            });
                        } else if(action === 'del'){
                            if(!isSuperAdmin){ layer.msg('仅超级管理员可操作', {icon:2}); return; }
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