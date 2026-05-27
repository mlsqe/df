<h3>申诉仲裁</h3>
<table id="disputesTable" lay-filter="disputesTable"></table>

<script type="text/html" id="disputeOperateTpl">
    <button class="layui-btn layui-btn-xs layui-btn-primary" lay-event="operate">
        操作 <i class="layui-icon layui-icon-down"></i>
    </button>
</script>

<script>
window.initAdmindisputesModule = function() {
    if (document.getElementById('disputesTable').hasAttribute('lay-render')) return;
    layui.use(['table', 'layer', 'dropdown'], function(){
        var table = layui.table, layer = layui.layer, dropdown = layui.dropdown;

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
                {field:'order_no', title:'订单号', minWidth:250},
                {field:'filed_name', title:'申诉人', minWidth:100},
                {field:'reason', title:'原因', minWidth:200, templet: function(d){return d.reason||'-';}},
                {field:'status', title:'状态', minWidth:100, templet: function(d){
                    return d.status === 'open'
                        ? '<span class="layui-badge layui-bg-orange">处理中</span>'
                        : '<span class="layui-badge layui-bg-green">已解决</span>';
                }},
                {field:'created_at', title:'申诉时间', minWidth:180, templet: '<div>{{layui.util.toDateString(d.created_at, "yyyy-MM-dd HH:mm:ss")}}</div>'},
                {title:'操作', width:100, templet: '#disputeOperateTpl'}
            ]],
            page: true
        });

        table.on('tool(disputesTable)', function(obj){
            var data = obj.data;
            if(obj.event === 'operate'){
                var menuItems = [
                    {title:'💸 退款给用户', id:'refund'},
                    {title:'💰 结算给打手', id:'settle'}
                ];
                if(isSuperAdmin){
                    menuItems.push({title:'🗑️ 删除', id:'del'});
                }
                dropdown.render({
                    elem: this,
                    id: 'dispute-drop-' + data.id,
                    data: menuItems,
                    show: true,
                    align: 'right',
                    click: function(menuData){
                        var action = menuData.id;
                        if(action === 'refund'){
                            layer.confirm('确定退款给用户并关闭订单吗？', function(index){
                                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'resolve_dispute', dispute_id:data.id, resolution:'refund'})})
                                .then(r=>r.json()).then(res=>{
                                    layer.msg(res.msg||res.message, {icon:(res.code===0||res.success)?1:2});
                                    if(res.code===0||res.success) table.reload('disputesTable');
                                });
                                layer.close(index);
                            });
                        } else if(action === 'settle'){
                            layer.confirm('确定结算给打手并完成订单吗？', function(index){
                                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'resolve_dispute', dispute_id:data.id, resolution:'pay_booster'})})
                                .then(r=>r.json()).then(res=>{
                                    layer.msg(res.msg||res.message, {icon:(res.code===0||res.success)?1:2});
                                    if(res.code===0||res.success) table.reload('disputesTable');
                                });
                                layer.close(index);
                            });
                        } else if(action === 'del'){
                            if(!isSuperAdmin){ layer.msg('仅超级管理员可操作', {icon:2}); return; }
                            layer.confirm('确定删除该申诉记录吗？', {icon:3, title:'警告'}, function(index){
                                fetch('api/admin.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'delete_dispute', dispute_id:data.id})})
                                .then(r=>r.json()).then(res=>{
                                    layer.msg(res.msg||res.message, {icon:(res.code===0||res.success)?1:2});
                                    if(res.code===0||res.success) table.reload('disputesTable');
                                });
                                layer.close(index);
                            });
                        }
                        dropdown.close('dispute-drop-' + data.id);
                    }
                });
            }
        });
    });
};
</script>