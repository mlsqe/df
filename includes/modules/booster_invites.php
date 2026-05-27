<h3>我的邀请码</h3>
<table id="boosterInvitesTable" lay-filter="boosterInvitesTable"></table>

<script type="text/html" id="inviteStatusTpl">
    {{# if(d.status === 'unused'){ }}
        <span class="layui-badge layui-bg-green">未使用</span>
    {{# } else if(d.status === 'used'){ }}
        <span class="layui-badge layui-bg-blue">已使用</span>
    {{# } else { }}
        <span class="layui-badge layui-bg-black">已过期</span>
    {{# } }}
</script>

<script>
window.initBoosterinvitesModule = function() {
    if (document.getElementById('boosterInvitesTable').hasAttribute('lay-render')) return;
    layui.use(['table', 'layer'], function(){
        var table = layui.table, layer = layui.layer;

        table.render({
            elem: '#boosterInvitesTable',
            url: 'api/booster.php?action=my_invites',
            parseData: function(res){
                return {
                    code: (res.code !== undefined) ? res.code : (res.success ? 0 : 1),
                    count: res.count || res.total || (res.data ? res.data.length : 0),
                    data: res.data || []
                };
            },
            cols: [[
                {field:'code', title:'邀请码', minWidth:120},
                {field:'status', title:'状态', minWidth:80, templet: '#inviteStatusTpl'},
                {field:'used_user', title:'使用者', minWidth:100, templet: function(d){return d.used_user||'-';}},
                {field:'created_at', title:'生成时间', minWidth:180, templet: '<div>{{layui.util.toDateString(d.created_at, "yyyy-MM-dd HH:mm:ss")}}</div>'},
                {field:'expire_at', title:'有效期', minWidth:180, templet: function(d){return d.expire_at ? layui.util.toDateString(d.expire_at, "yyyy-MM-dd HH:mm:ss") : '永久';}}
            ]],
            page: true
        });
    });
};
</script>