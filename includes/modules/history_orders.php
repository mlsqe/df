<?php
$currentUser = getCurrentUser();
$db = getDB();
$boosterStmt = $db->prepare("SELECT id FROM boosters WHERE user_id = ? AND status = 'active'");
$boosterStmt->execute([$currentUser['id']]);
$isBooster = $boosterStmt->fetch() ? true : false;
?>
<h3>历史订单</h3>

<?php if ($isBooster): ?>
<!-- 打手：显示代练记录和下单记录 -->
<div class="layui-row layui-col-space20">
    <div class="layui-col-md6">
        <div class="layui-card">
            <div class="layui-card-header">代练记录</div>
            <div class="layui-card-body">
                <div class="layui-form" style="margin-bottom:10px;">
                    <div class="layui-inline">
                        <select id="boosterHistStatusFilter">
                            <option value="completed">已完成</option>
                            <option value="cancelled">已取消</option>
                            <option value="">全部</option>
                        </select>
                    </div>
                    <button class="layui-btn layui-btn-sm" id="searchBoosterHistBtn"><i class="layui-icon layui-icon-search"></i> 搜索</button>
                </div>
                <table id="boosterHistoryTable" lay-filter="boosterHistoryTable"></table>
            </div>
        </div>
    </div>
    <div class="layui-col-md6">
        <div class="layui-card">
            <div class="layui-card-header">下单记录</div>
            <div class="layui-card-body">
                <div class="layui-form" style="margin-bottom:10px;">
                    <div class="layui-inline">
                        <select id="userHistStatusFilter">
                            <option value="completed">已完成</option>
                            <option value="cancelled">已取消</option>
                            <option value="">全部</option>
                        </select>
                    </div>
                    <button class="layui-btn layui-btn-sm" id="searchUserHistBtn"><i class="layui-icon layui-icon-search"></i> 搜索</button>
                </div>
                <table id="userHistoryTable" lay-filter="userHistoryTable"></table>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- 普通用户/管理员：只显示下单记录 -->
<div class="layui-card">
    <div class="layui-card-header">下单记录</div>
    <div class="layui-card-body">
        <div class="layui-form" style="margin-bottom:10px;">
            <div class="layui-inline">
                <select id="userHistStatusFilter">
                    <option value="completed">已完成</option>
                    <option value="cancelled">已取消</option>
                    <option value="">全部</option>
                </select>
            </div>
            <button class="layui-btn layui-btn-sm" id="searchUserHistBtn"><i class="layui-icon layui-icon-search"></i> 搜索</button>
        </div>
        <table id="userHistoryTable" lay-filter="userHistoryTable"></table>
    </div>
</div>
<?php endif; ?>

<script type="text/html" id="histStatusTpl">
    {{# if(d.status === 'completed'){ }}
        <span class="layui-badge layui-bg-green">已完成</span>
    {{# } else if(d.status === 'cancelled'){ }}
        <span class="layui-badge layui-bg-black">已取消</span>
    {{# } }}
</script>

<script>
window.initHistoryordersModule = function() {
    if (document.getElementById('userHistoryTable')?.hasAttribute('lay-render')) return;

    layui.use(['table', 'layer'], function(){
        var table = layui.table, layer = layui.layer;

        // 下单记录表格
        if (document.getElementById('userHistoryTable')) {
            table.render({
                elem: '#userHistoryTable',
                url: 'api/order.php?action=list_history&type=user',
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
                    {field:'order_no', title:'订单号', minWidth:160},
                    {field:'game_type', title:'类型'},
                    {field:'amount', title:'金额', templet: '<span>¥{{d.amount}}</span>'},
                    {field:'status', title:'状态', templet: '#histStatusTpl'},
                    {field:'booster_name', title:'打手', templet: function(d){return d.booster_name||'-';}},
                    {field:'completed_at', title:'完成/取消时间', templet: '<div>{{layui.util.toDateString(d.completed_at||d.created_at, "yyyy-MM-dd HH:mm:ss")}}</div>'}
                ]],
                page: true
            });

            $('#searchUserHistBtn').click(function(){
                table.reload('userHistoryTable', {
                    where: { status: $('#userHistStatusFilter').val() },
                    page: { curr: 1 }
                });
            });
        }

        // 代练记录表格（仅打手）
        <?php if ($isBooster): ?>
        if (document.getElementById('boosterHistoryTable')) {
            table.render({
                elem: '#boosterHistoryTable',
                url: 'api/order.php?action=list_history&type=booster',
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
                    {field:'order_no', title:'订单号', minWidth:160},
                    {field:'game_type', title:'类型'},
                    {field:'amount', title:'金额', templet: '<span>¥{{d.amount}}</span>'},
                    {field:'status', title:'状态', templet: '#histStatusTpl'},
                    {field:'user_name', title:'用户', templet: function(d){return d.user_name||'-';}},
                    {field:'completed_at', title:'完成/取消时间', templet: '<div>{{layui.util.toDateString(d.completed_at||d.created_at, "yyyy-MM-dd HH:mm:ss")}}</div>'}
                ]],
                page: true
            });

            $('#searchBoosterHistBtn').click(function(){
                table.reload('boosterHistoryTable', {
                    where: { status: $('#boosterHistStatusFilter').val() },
                    page: { curr: 1 }
                });
            });
        }
        <?php endif; ?>
    });
};
</script>