/**
 * 堆叠式 Toast 提示组件
 */
(function() {
    const style = document.createElement('style');
    style.textContent = `
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 10px; pointer-events: none; }
        .toast-item { background: white; border-left: 5px solid #5FB878; padding: 12px 20px; border-radius: 14px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); min-width: 240px; display: flex; align-items: center; justify-content: space-between; animation: toastSlideIn 0.3s ease forwards; pointer-events: auto; font-family: system-ui, -apple-system, sans-serif; font-size: 14px; color: #1e1e2a; }
        .toast-item.toast-error { border-left-color: #FF5722; }
        .toast-item.toast-warning { border-left-color: #FFB800; }
        .toast-item.toast-removing { animation: toastSlideOut 0.3s ease forwards; }
        .toast-close { cursor: pointer; margin-left: 12px; opacity: 0.6; font-weight: bold; font-size: 16px; line-height: 1; transition: opacity 0.2s; }
        .toast-close:hover { opacity: 1; }
        @keyframes toastSlideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes toastSlideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(120%); opacity: 0; } }
    `;
    document.head.appendChild(style);
    const container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast-item toast-${type}`;
        toast.innerHTML = `<span>${message}</span><span class="toast-close">✕</span>`;
        toast.querySelector('.toast-close').addEventListener('click', () => removeToast(toast));
        container.appendChild(toast);
        const timer = setTimeout(() => removeToast(toast), 3000);
        toast.addEventListener('mouseenter', () => clearTimeout(timer));
        toast.addEventListener('mouseleave', () => { const newTimer = setTimeout(() => removeToast(toast), 3000); toast.dataset.timer = newTimer; });
        toast.dataset.timer = timer;
    }
    function removeToast(toast) {
        if (toast.classList.contains('toast-removing')) return;
        toast.classList.add('toast-removing');
        toast.addEventListener('animationend', () => { if (toast.parentNode) toast.parentNode.removeChild(toast); });
    }
    window.Toast = {
        success(msg) { showToast(msg, 'success'); },
        error(msg)   { showToast(msg, 'error'); },
        warning(msg) { showToast(msg, 'warning'); }
    };
})();

// 退出登录
window.logout = function() {
    fetch('api/auth.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'logout' }) })
    .then(r => r.json())
    .then(() => { window.location.href = 'login.php'; })
    .catch(() => { window.location.href = 'login.php'; });
};

// 格式化金额
function formatMoney(amount) { return '¥' + parseFloat(amount).toFixed(2); }

// 格式化时间（兼容 UTC 字符串）
function formatTime(datetime) {
    if (!datetime) return '-';
    if (typeof datetime === 'string' && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(datetime)) {
        datetime = datetime.replace(' ', 'T') + 'Z';
    }
    var d = new Date(datetime);
    if (isNaN(d.getTime())) return '-';
    var year = d.getFullYear(), month = String(d.getMonth()+1).padStart(2,'0'), day = String(d.getDate()).padStart(2,'0');
    var hours = String(d.getHours()).padStart(2,'0'), minutes = String(d.getMinutes()).padStart(2,'0'), seconds = String(d.getSeconds()).padStart(2,'0');
    return year + '-' + month + '-' + day + ' ' + hours + ':' + minutes + ':' + seconds;
}

// 游戏单位格式化
function formatGameUnit(amount, unit = 'K') {
    if (unit === 'K' && amount >= 1000) return (amount/1000).toFixed(1)+'M';
    else if (unit === 'M' && amount >= 1000) return (amount/1000).toFixed(1)+'B';
    return amount + unit;
}

// ========== WebSocket 实时表格更新 ==========
var ws = null, reconnectTimer = null;

function liveReload() {
    if (reconnectTimer) clearTimeout(reconnectTimer);
    if (ws && ws.readyState === WebSocket.OPEN) return;
    if (ws && ws.readyState !== WebSocket.CLOSED) ws.close();

    var protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    var wsUrl = protocol + '//' + window.location.host + '/wss/';
    ws = new WebSocket(wsUrl);
    window.ws = ws;

    var statusEl = document.getElementById('wsStatus');
    if (statusEl) {
        statusEl.innerText = '连接中...';
        statusEl.style.background = '#FFB800';
        statusEl.style.color = '#fff';
    }

    ws.onopen = function() {
        if (statusEl) { statusEl.innerText = '已连接'; statusEl.style.background = '#5FB878'; statusEl.style.color = '#fff'; }
    };

   // main.js 中的 WebSocket 监听部分
// main.js 中原来的 ws.onmessage
ws.onmessage = function(e) {
    try {
        var msg = JSON.parse(e.data);
        if (msg.type !== 'reload') return;

        // 1. 表格刷新逻辑：仅当页面存在该 ID 的表格且实例已初始化时执行
        if (msg.table) {
            var targetId = msg.table + 'Table';
            var tableEl = document.getElementById(targetId);
            
            if (tableEl && window.layui && layui.table) {
                // 增加安全判断：只有实例存在时才 reload，彻底消除错误提示
                if (layui.table.getOptions(targetId)) {
                    layui.table.reload(targetId);
                }
            }
        }

        // 2. 仪表盘卡片数据刷新逻辑
        // 只要收到 reload，就尝试调用这个函数。
        // 如果当前页面没有定义此函数（例如在其他页面），typeof 判断会返回 'undefined'，从而安全跳过，绝不报错。
        if (typeof fetchDashboardStats === 'function') {
            fetchDashboardStats();
        }
    } catch (err) {
        console.error('WS 消息处理错误:', err);
    }
};
    ws.onclose = function() {
        if (statusEl) { statusEl.innerText = '未连接'; statusEl.style.background = 'rgba(255,255,255,0.2)'; statusEl.style.color = '#fff'; }
        reconnectTimer = setTimeout(liveReload, 5000);
    };

    ws.onerror = function() { ws.close(); };
}

if (document.readyState === 'complete') liveReload();
else window.addEventListener('load', liveReload);

// Layui 初始化
layui.use(['layer', 'util', 'form', 'table'], function() {
    window.layer = layui.layer;
    window.layuiUtil = layui.util;
    window.layuiForm = layui.form;
    window.layuiTable = layui.table;
});