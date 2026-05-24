<h3>发布代练订单</h3>
<div class="layui-form">
    <?php $types = getGameTypeOptions(); ?>
    <div class="layui-form-item">
        <label class="layui-form-label">类型</label>
        <div class="layui-input-block">
            <select id="game_type">
                <?php foreach($types as $t): ?>
                <option value="<?=$t['id']?>" data-unit="<?=$t['unit_label']?>" data-price="<?=$t['price_per_unit']?>">
                    <?=$t['name']?> (¥<?=$t['price_per_unit']?>/<?=$t['unit_label']?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="layui-form-item">
        <label class="layui-form-label">数量</label>
        <div class="layui-input-block">
            <input type="number" id="quantity" class="layui-input" oninput="calcPrice()">
            <span id="unit_display" style="color:#999;"></span>
        </div>
    </div>
    <div class="layui-form-item">
        <label class="layui-form-label">总价</label>
        <div class="layui-input-block">
            <input type="text" id="total_price" class="layui-input" readonly>
        </div>
    </div>
    <div class="layui-form-item">
        <label class="layui-form-label">描述</label>
        <div class="layui-input-block">
            <textarea id="target_desc" class="layui-textarea"></textarea>
        </div>
    </div>
    <button class="layui-btn" onclick="submitOrder()">发布</button>
</div>
<script>
function calcPrice(){
    var sel = document.getElementById('game_type');
    var opt = sel.options[sel.selectedIndex];
    var price = parseFloat(opt.getAttribute('data-price'));
    var qty = parseInt(document.getElementById('quantity').value) || 0;
    var unit = opt.getAttribute('data-unit');
    document.getElementById('unit_display').innerText = qty>0 ? formatGameUnit(qty, unit) : '';
    document.getElementById('total_price').value = (price*qty).toFixed(2);
}
function submitOrder(){
    var sel = document.getElementById('game_type');
    var opt = sel.options[sel.selectedIndex];
    var qty = parseInt(document.getElementById('quantity').value) || 0;
    if(qty<=0){ Toast.warning('请输入数量'); return; }
    fetch('api/order.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'create', game_type_id: opt.value, quantity: qty, target_description: document.getElementById('target_desc').value})
    }).then(r=>r.json()).then(res=>{
        if(res.success){
            Toast.success('订单创建成功，即将跳转到我的订单');
            if(typeof switchModule === 'function') switchModule('my_orders');
            document.getElementById('quantity').value='';
            document.getElementById('target_desc').value='';
        } else Toast.error(res.message);
    });
}
</script>