/**
 * 堆叠式 Toast 提示组件
 * 使用方式：
 *   Toast.success('操作成功');
 *   Toast.error('发生错误');
 *   Toast.warning('警告信息');
 */
(function() {
    const style = document.createElement('style');
    style.textContent = `
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }
        .toast-item {
            background: white;
            border-left: 5px solid #5FB878;
            padding: 12px 20px;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            min-width: 240px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: toastSlideIn 0.3s ease forwards;
            pointer-events: auto;
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 14px;
            color: #1e1e2a;
        }
        .toast-item.toast-error {
            border-left-color: #FF5722;
        }
        .toast-item.toast-warning {
            border-left-color: #FFB800;
        }
        .toast-item.toast-removing {
            animation: toastSlideOut 0.3s ease forwards;
        }
        .toast-close {
            cursor: pointer;
            margin-left: 12px;
            opacity: 0.6;
            font-weight: bold;
            font-size: 16px;
            line-height: 1;
            transition: opacity 0.2s;
        }
        .toast-close:hover { opacity: 1; }
        @keyframes toastSlideIn {
            from { transform: translateX(120%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes toastSlideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(120%); opacity: 0; }
        }
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
        toast.addEventListener('mouseleave', () => {
            const newTimer = setTimeout(() => removeToast(toast), 3000);
            toast.dataset.timer = newTimer;
        });
        toast.dataset.timer = timer;
    }

    function removeToast(toast) {
        if (toast.classList.contains('toast-removing')) return;
        toast.classList.add('toast-removing');
        toast.addEventListener('animationend', () => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        });
    }

    window.Toast = {
        success(msg) { showToast(msg, 'success'); },
        error(msg)   { showToast(msg, 'error'); },
        warning(msg) { showToast(msg, 'warning'); }
    };
})();

// 退出登录
window.logout = function() {
    fetch('api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'logout' })
    })
    .then(r => r.json())
    .then(() => { window.location.href = 'login.php'; })
    .catch(() => { window.location.href = 'login.php'; });
};

// 格式化金额
function formatMoney(amount) {
    return '¥' + parseFloat(amount).toFixed(2);
}

// 格式化时间
function formatTime(datetime) {
    if (!datetime) return '-';
    return layui.util.toDateString(datetime, 'yyyy-MM-dd HH:mm:ss');
}

// 订单状态中文映射
function getStatusText(status, payment_status = 'paid') {
    if (payment_status === 'unpaid') return '待付款';
    const map = {
        'pending': '待接单',
        'accepted': '已接单',
        'in_progress': '代练中',
        'completed': '已完成',
        'cancelled': '已取消',
        'disputed': '争议中'
    };
    return map[status] || status;
}

// 订单状态样式类
function getStatusClass(status, payment_status = 'paid') {
    if (payment_status === 'unpaid') return 'layui-bg-orange';
    const map = {
        'pending': 'layui-bg-cyan',
        'accepted': 'layui-bg-blue',
        'in_progress': 'layui-bg-green',
        'completed': 'layui-bg-gray',
        'disputed': 'layui-bg-red',
        'cancelled': 'layui-bg-black'
    };
    return map[status] || 'layui-bg-cyan';
}

// 游戏单位格式化 (K/M/B)
function formatGameUnit(amount, unit = 'K') {
    if (unit === 'K' && amount >= 1000) {
        return (amount / 1000).toFixed(1) + 'M';
    } else if (unit === 'M' && amount >= 1000) {
        return (amount / 1000).toFixed(1) + 'B';
    }
    return amount + unit;
}

// ========== WebSocket 实时表格更新 ==========
var ws = null;
function liveReload() {
    if (typeof WebSocket === 'undefined') return;
    if (ws && ws.readyState === WebSocket.OPEN) return;

    var protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    var wsUrl = protocol + '//' + window.location.host + '/wss/';
    ws = new WebSocket(wsUrl);

    ws.onopen = function() {
        var statusEl = document.getElementById('wsStatus');
        if (statusEl) {
            statusEl.innerText = '已连接';
            statusEl.style.background = '#5FB878';
            statusEl.style.color = '#fff';
        }
    };

    ws.onmessage = function(e) {
        try {
            var msg = JSON.parse(e.data);
            if (msg.type === 'reload' && msg.table) {
                // 个人主页刷新：直接刷新整个页面
                if (msg.table === 'profile') {
                    window.location.reload();
                    return;
                }

                // 表格映射（后端表名 -> 前端表格ID）
                var tableIdPrefix = msg.table;
                var tableId = tableIdPrefix + 'Table';
                if (document.getElementById(tableId) && layui.table) {
                    layui.table.reload(tableId);
                }
            }
        } catch (err) {
            console.log('WS message parse error:', err);
        }
    };

    ws.onclose = function() {
        var statusEl = document.getElementById('wsStatus');
        if (statusEl) {
            statusEl.innerText = '未连接';
            statusEl.style.background = 'rgba(255,255,255,0.2)';
            statusEl.style.color = '#fff';
        }
        // 断线重连
        setTimeout(liveReload, 3000);
    };
}

// 页面加载时启动
if (document.readyState === 'complete') {
    liveReload();
} else {
    window.addEventListener('load', liveReload);
}

// Layui 初始化
layui.use(['layer', 'util', 'form', 'table'], function() {
    window.layer = layui.layer;
    window.layuiUtil = layui.util;
    window.layuiForm = layui.form;
    window.layuiTable = layui.table;
});