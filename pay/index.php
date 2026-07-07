<?php
/**
 * 支付套餐选购页
 */
require_once __DIR__ . '/config.php';

$plans = [
    ['id' => 'count_10', 'name' => '🎯 体验套餐', 'type' => 'count', 'count' => 10, 'days' => 0, 'price' => 0.50, 'desc' => '10次体验，仅需5毛'],
    ['id' => 'count_50', 'name' => '按次 50次', 'type' => 'count', 'count' => 50, 'days' => 0, 'price' => 9.90, 'desc' => '适合偶尔使用'],
    ['id' => 'count_200', 'name' => '按次 200次', 'type' => 'count', 'count' => 200, 'days' => 0, 'price' => 29.90, 'desc' => '适合高频使用'],
    ['id' => 'count_1000', 'name' => '按次 1000次', 'type' => 'count', 'count' => 1000, 'days' => 0, 'price' => 99.90, 'desc' => '适合批量使用'],
    ['id' => 'monthly', 'name' => '包月套餐', 'type' => 'monthly', 'count' => 0, 'days' => 30, 'price' => 19.90, 'desc' => '30天无限次'],
    ['id' => 'quarterly', 'name' => '包季套餐', 'type' => 'monthly', 'count' => 0, 'days' => 90, 'price' => 49.90, 'desc' => '90天无限次'],
    ['id' => 'daily_7', 'name' => '包周套餐', 'type' => 'daily', 'count' => 0, 'days' => 7, 'price' => 5.90, 'desc' => '7天无限次'],
    ['id' => 'lifetime', 'name' => '💎 永久套餐', 'type' => 'lifetime', 'count' => 0, 'days' => 0, 'price' => 199.00, 'desc' => '永久无限次使用'],
    ['id' => 'lifetime_daily', 'name' => '🌟 终身畅享套餐', 'type' => 'lifetime_daily', 'count' => 0, 'days' => 0, 'price' => 150.00, 'daily_limit' => 150, 'desc' => '终身有效，每日限150次'],
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>短视频解析 API - 购买套餐</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; background:#0f0f0f; color:#e0e0e0; }
.container { max-width:1000px; margin:0 auto; padding:40px 20px; }
h1 { text-align:center; font-size:2em; background:linear-gradient(135deg,#667eea,#764ba2); -webkit-background-clip:text; -webkit-text-fill-color:transparent; margin-bottom:8px; }
.subtitle { text-align:center; color:#888; margin-bottom:40px; }
.plans { display:grid; grid-template-columns:repeat(auto-fit, minmax(280px,1fr)); gap:20px; margin-bottom:30px; }
.plan { background:#1a1a2e; border:1px solid #2a2a4e; border-radius:12px; padding:24px; cursor:pointer; transition:all 0.2s; position:relative; }
.plan:hover { border-color:#a78bfa; transform:translateY(-2px); }
.plan.selected { border-color:#a78bfa; box-shadow:0 0 20px rgba(167,139,250,0.2); }
.plan .name { font-size:1.3em; font-weight:600; color:#a78bfa; margin-bottom:4px; }
.plan .price { font-size:2em; font-weight:700; color:#fff; margin:8px 0; }
.plan .price small { font-size:0.4em; color:#888; font-weight:400; }
.plan .desc { color:#888; font-size:0.85em; }
.plan .tag { display:inline-block; padding:2px 8px; border-radius:4px; font-size:0.75em; margin-bottom:8px; }
.tag-count { background:#2563eb; color:#fff; }
.tag-monthly { background:#7c3aed; color:#fff; }
.tag-daily { background:#0891b2; color:#fff; }
.tag-lifetime { background:#dc2626; color:#fff; }
.plan .badge-popular { position:absolute; top:-10px; right:20px; background:#f59e0b; color:#000; padding:4px 12px; border-radius:20px; font-size:0.75em; font-weight:600; }
#paySection { display:none; text-align:center; margin-top:20px; }
.qr-wrap { display:inline-block; background:#fff; padding:20px; border-radius:12px; }
.qr-wrap img { width:250px; height:250px; }
.qr-wrap .hint { color:#888; font-size:0.85em; margin-top:12px; }
.status-text { margin-top:12px; font-size:1em; color:#666; }
.status-text.paid { color:#34d399; font-size:1.2em; font-weight:600; }
.key-display { background:#1a1a2e; border:1px solid #a78bfa; border-radius:8px; padding:16px; margin-top:16px; font-family:monospace; font-size:1.1em; color:#67e8f9; letter-spacing:2px; }
.loading { display:inline-block; width:24px; height:24px; border:3px solid #2a2a4e; border-top-color:#a78bfa; border-radius:50%; animation:spin 1s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
#errorMsg { color:#f87171; text-align:center; margin-top:12px; }
.btn { padding:14px 40px; background:#a78bfa; border:none; border-radius:8px; color:#fff; font-size:1.1em; cursor:pointer; display:none; margin-top:16px; }
.btn:hover { background:#b99bff; }
.btn-buy { padding:16px 60px; background:linear-gradient(135deg,#667eea,#764ba2); border:none; border-radius:12px; color:#fff; font-size:1.2em; font-weight:600; cursor:pointer; transition:all 0.2s; box-shadow:0 4px 15px rgba(102,126,234,0.3); }
.btn-buy:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(102,126,234,0.5); }
.footer { text-align:center; color:#666; margin-top:40px; font-size:0.85em; }
.footer a { color:#667eea; text-decoration:none; }
/* 查询订单区 */
.query-section { margin-top:40px; border-top:1px solid #2a2a4e; padding-top:30px; }
.query-section h3 { text-align:center; color:#888; font-size:0.95em; margin-bottom:16px; font-weight:400; }
.query-box { display:flex; justify-content:center; gap:10px; max-width:500px; margin:0 auto; }
.query-box input { flex:1; padding:12px 16px; border-radius:8px; border:1px solid #2a2a4e; background:#1a1a2e; color:#e0e0e0; font-size:0.95em; outline:none; }
.query-box input:focus { border-color:#a78bfa; }
.query-box button { padding:12px 24px; background:#a78bfa; border:none; border-radius:8px; color:#fff; font-size:0.95em; cursor:pointer; white-space:nowrap; }
.query-box button:hover { background:#b99bff; }
#queryResult { text-align:center; margin-top:16px; color:#888; font-size:0.9em; }
#queryResult .ok { color:#34d399; }
#queryResult .fail { color:#f87171; }
</style>
</head>
<body>
<div class="container">
<h1>🎬 短视频解析 API</h1>
<p class="subtitle">支持抖音 / 快手 / B站 / 小红书等主流平台 · 支付宝当面付</p>

<div class="plans" id="planList">
    <?php foreach ($plans as $i => $p): ?>
    <div class="plan <?= $i === 0 ? 'selected' : '' ?>" data-plan='<?= json_encode($p, JSON_UNESCAPED_UNICODE) ?>' onclick="selectPlan(this)">
        <?php if ($p['id'] === 'count_200'): ?><div class="badge-popular">🔥 推荐</div><?php endif; ?>
        <div class="tag <?= $p['type'] === 'lifetime_daily' ? 'tag-lifetime' : 'tag-' . $p['type'] ?>"><?= $p['type'] === 'count' ? '按次' : ($p['type'] === 'monthly' ? '包月' : ($p['type'] === 'daily' ? '包天' : ($p['type'] === 'lifetime_daily' ? '每日限' . $p['daily_limit'] . '次' : '永久'))) ?></div>
        <div class="name"><?= $p['name'] ?></div>
        <div class="price">¥<?= number_format($p['price'], 2) ?> <small><?= $p['type'] === 'count' ? '/ 共' . $p['count'] . '次' : ($p['type'] === 'lifetime' ? '/ 永久' : ($p['type'] === 'lifetime_daily' ? '/ 每日限' . $p['daily_limit'] . '次' : '/ ' . $p['days'] . '天')) ?></small></div>
        <div class="desc"><?= $p['desc'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div style="text-align:center;margin-bottom:30px;">
    <button class="btn-buy" onclick="createOrder()">💳 立即购买</button>
</div>

<div id="paySection">
    <div class="qr-wrap" id="qrWrap">
        <div id="qrPlaceholder" style="width:200px;height:200px;display:flex;align-items:center;justify-content:center;flex-direction:column;">
            <div class="loading"></div>
            <div style="color:#888;margin-top:8px;font-size:0.85em;">生成二维码中...</div>
        </div>
        <img id="qrImg" style="display:none;width:200px;height:200px;" alt="支付宝扫码支付">
        <div class="hint">请打开支付宝扫码支付</div>
    </div>
    <div class="status-text" id="statusText">等待付款...</div>
    <div id="keyDisplay" style="display:none;">
        <div style="color:#888;margin-bottom:8px;">你的 API 密钥：</div>
        <div class="key-display" id="apiKey"></div>
        <div style="color:#666;margin-top:12px;font-size:0.85em;">
            调用方式：<code style="background:#252540;padding:2px 8px;border-radius:4px;color:#fbbf24;">GET /api/parse.php?key=你的密钥&url=视频链接</code>
        </div>
    </div>
    <div id="errorMsg"></div>
    <button class="btn" id="newOrderBtn" onclick="resetPage()">重新购买</button>
</div>

<div class="query-section">
    <h3>已有订单号？查询支付状态</h3>
    <div class="query-box">
        <input type="text" id="queryOrderId" placeholder="输入订单号" onkeydown="if(event.key==='Enter')queryOrder()">
        <button onclick="queryOrder()">查 询</button>
    </div>
    <div id="queryResult"></div>
</div>

<div class="footer">
    <p>遇到问题？联系管理员 · 支付成功后密钥自动生效</p>
    <p style="margin-top:8px;">测试密钥：<code style="background:#252540;padding:2px 8px;border-radius:4px;">TEST1-ABCD-EFGH-IJKL-MNOP</code>（每IP每日限10次）</p>
</div>
</div>

<script>
let currentOrderId = null;
let pollTimer = null;
let selectedPlan = null;

function selectPlan(el) {
    document.querySelectorAll('.plan').forEach(p => p.classList.remove('selected'));
    el.classList.add('selected');
    selectedPlan = JSON.parse(el.dataset.plan);
    document.getElementById('paySection').style.display = 'none';
    document.getElementById('errorMsg').textContent = '';
}

async function createOrder() {
    if (!selectedPlan) {
        document.getElementById('errorMsg').textContent = '请先选择一个套餐';
        return;
    }
    
    document.getElementById('paySection').style.display = 'block';
    document.getElementById('qrImg').style.display = 'none';
    document.getElementById('qrPlaceholder').style.display = 'flex';
    document.getElementById('statusText').textContent = '生成订单中...';
    document.getElementById('statusText').className = 'status-text';
    document.getElementById('keyDisplay').style.display = 'none';
    document.getElementById('errorMsg').textContent = '';
    document.getElementById('newOrderBtn').style.display = 'none';
    
    try {
        const res = await fetch('create.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(selectedPlan)
        });
        const data = await res.json();
        
        if (!data.success) {
            document.getElementById('errorMsg').textContent = '创建订单失败: ' + data.error;
            return;
        }
        
        currentOrderId = data.order_id;
        document.getElementById('qrPlaceholder').style.display = 'none';
        document.getElementById('qrImg').style.display = 'block';
        document.getElementById('qrImg').src = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&margin=10&data=' + encodeURIComponent(data.qr_code);
        document.getElementById('statusText').textContent = '请打开支付宝扫码支付';
        
        // 开始轮询支付状态
        startPolling(data.order_id);
    } catch (e) {
        document.getElementById('errorMsg').textContent = '网络错误: ' + e.message;
    }
}

function startPolling(orderId) {
    let attempts = 0;
    const maxAttempts = 120; // 等待10分钟
    
    if (pollTimer) clearInterval(pollTimer);
    
    pollTimer = setInterval(async () => {
        attempts++;
        try {
            const res = await fetch('query.php?order_id=' + orderId);
            const data = await res.json();
            
            if (data.status === 'paid') {
                clearInterval(pollTimer);
                document.getElementById('statusText').textContent = '✅ 支付成功！';
                document.getElementById('statusText').className = 'status-text paid';
                document.getElementById('apiKey').textContent = data.api_key;
                document.getElementById('keyDisplay').style.display = 'block';
                document.getElementById('newOrderBtn').style.display = 'inline-block';
                return;
            }
            
            if (data.status === 'closed') {
                clearInterval(pollTimer);
                document.getElementById('statusText').textContent = '❌ 订单已关闭';
                document.getElementById('statusText').className = 'status-text';
                document.getElementById('newOrderBtn').style.display = 'inline-block';
                return;
            }
            
            if (attempts % 10 === 0) {
                document.getElementById('statusText').textContent = '等待支付中... (' + (attempts * 5) + 's)';
            }
            
            if (attempts >= maxAttempts) {
                clearInterval(pollTimer);
                document.getElementById('statusText').textContent = '⏰ 二维码已过期，请重新生成';
                document.getElementById('newOrderBtn').style.display = 'inline-block';
            }
        } catch (e) {
            // 忽略轮询错误
        }
    }, 5000);
}

function resetPage() {
    if (pollTimer) clearInterval(pollTimer);
    currentOrderId = null;
    document.getElementById('paySection').style.display = 'none';
    document.getElementById('errorMsg').textContent = '';
    selectedPlan = null;
    document.querySelectorAll('.plan').forEach(p => p.classList.remove('selected'));
    document.querySelector('.plan').classList.add('selected');
}

async function queryOrder() {
    const orderId = document.getElementById('queryOrderId').value.trim();
    const resultEl = document.getElementById('queryResult');
    
    if (!orderId) {
        resultEl.innerHTML = '<span class="fail">请输入订单号</span>';
        return;
    }
    
    resultEl.innerHTML = '查询中...';
    
    try {
        const res = await fetch('query.php?order_id=' + encodeURIComponent(orderId));
        const data = await res.json();
        
        if (data.status === 'paid') {
            resultEl.innerHTML = '<span class="ok">✅ 已支付</span> &nbsp; API密钥：<code style="background:#252540;padding:2px 8px;border-radius:4px;color:#fbbf24;">' + data.api_key + '</code>';
        } else if (data.status === 'pending') {
            resultEl.innerHTML = '<span class="fail">⏳ 等待支付中...</span>';
        } else if (data.status === 'closed') {
            resultEl.innerHTML = '<span class="fail">❌ 订单已关闭</span>';
        } else {
            resultEl.innerHTML = '<span class="fail">❌ ' + (data.error || '订单不存在') + '</span>';
        }
    } catch (e) {
        resultEl.innerHTML = '<span class="fail">查询失败: ' + e.message + '</span>';
    }
}

// 选择第一个套餐后自动生成二维码
document.addEventListener('DOMContentLoaded', () => {
    selectedPlan = JSON.parse(document.querySelector('.plan.selected').dataset.plan);
});

// 点击套餐直接跳转到支付
document.querySelectorAll('.plan').forEach(p => {
    p.addEventListener('dblclick', function() {
        selectPlan(this);
        createOrder();
    });
});
</script>
</body>
</html>
