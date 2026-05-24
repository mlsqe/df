<h3>我的代练</h3>
<div id="myBoosterOrders"></div>
<script>
window.initBoosterprogressModule = function() {
    loadMyOrders();
    window.showUpload = function(orderId){
        layer.open({
            type:1, title:'上传进度', area:'400px',
            content: `<div style="padding:20px;">
                <input type="hidden" id="progress_order_id" value="${orderId}">
                <textarea id="progress_desc" class="layui-textarea" placeholder="描述进度"></textarea>
                <input type="file" id="progress_screenshot" accept="image/*" style="margin-top:10px;">
                <button class="layui-btn layui-btn-fluid" onclick="uploadProgress()" style="margin-top:10px;">提交</button>
            </div>`
        });
    };

    window.uploadProgress = function(){
        var fd = new FormData();
        fd.append('action', 'upload_progress');
        fd.append('order_id', $('#progress_order_id').val());
        fd.append('description', $('#progress_desc').val());
        var file = $('#progress_screenshot')[0].files[0];
        if(file) fd.append('screenshot', file);
        fetch('api/order.php', {method:'POST', body:fd})
        .then(r=>r.json()).then(res=>{
            if(res.success){ Toast.success(res.message); layer.closeAll(); loadMyOrders(); }
            else Toast.error(res.message);
        });
    };

    function loadMyOrders(){
        fetch('api/order.php?action=my_booster_orders').then(r=>r.json()).then(res=>{
            var html = '';
            if(res.success && res.orders){
                res.orders.forEach(o=>{
                    html += `<div style="background:#f6f6f6; padding:12px; border-radius:6px; margin-bottom:10px;">
                        <strong>${o.order_no}</strong> <span class="layui-badge layui-bg-blue">${getStatusText(o.status)}</span>
                        <div>用户：${o.user_name} | ¥${parseFloat(o.amount).toFixed(2)}</div>
                        <button class="layui-btn layui-btn-xs layui-btn-warm" onclick="showUpload(${o.id})">📤 上传进度</button>
                    </div>`;
                });
                if(res.orders.length===0) html = '<div style="text-align:center;color:#999;">暂无进行中的订单</div>';
            }
            document.getElementById('myBoosterOrders').innerHTML = html;
        });
    }
};
</script>