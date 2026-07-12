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
<title>短视频解析 - 购买套餐</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Nunito','Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; background:#0e0b0d; color:#f0f0f0; }
/* ========== Navbar ========== */
.navbar{position:fixed;top:0;left:0;right:0;z-index:100;background:#0e0b0d;border-bottom:1px solid rgba(255,255,255,0.06);}
.navbar .container{display:flex;align-items:center;justify-content:space-between;height:60px;max-width:1100px;margin:0 auto;padding:0 24px;}
.navbar-brand{font-size:15px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#fff;text-decoration:none;}
.navbar-links{display:flex;align-items:center;gap:2px;}
.navbar-links a{padding:8px 16px;border-radius:6px;font-size:13px;font-weight:500;color:rgba(255,255,255,0.5);text-decoration:none;transition:all .2s;letter-spacing:0.5px;}
.navbar-links a:hover{color:#fff;background:rgba(255,255,255,0.04);}
.navbar-cta{padding:8px 20px!important;border-radius:100px!important;background:rgba(255,255,255,0.08)!important;color:#fff!important;border:1px solid rgba(255,255,255,0.1)!important;}
.navbar-cta:hover{background:rgba(255,255,255,0.12)!important;}
.menu-toggle{display:none;background:none;border:none;color:#fff;font-size:22px;cursor:pointer;padding:8px;letter-spacing:2px;}
@media(max-width:640px){
  .navbar-links{display:none;flex-direction:column;position:absolute;top:60px;left:0;right:0;padding:12px;gap:2px;background:#0e0b0d;border-bottom:1px solid rgba(255,255,255,0.06);}
  .navbar-links.open{display:flex;}
  .menu-toggle{display:block;}
}
.container { max-width:1000px; margin:0 auto; padding:80px 20px 40px; }
h1 { text-align:center; font-size:2em; font-weight:600; color:#f0f0f0; margin-bottom:8px; }
.subtitle { text-align:center; color:rgba(255,255,255,0.4); margin-bottom:40px; font-size:0.9em; }
/* ===== 套餐卡片 ===== */
.plans { display:grid; grid-template-columns:repeat(auto-fit, minmax(280px,1fr)); gap:20px; margin-bottom:30px; }
.plan { background:rgba(255,255,255,0.02); -webkit-backdrop-filter:blur(20px); backdrop-filter:blur(20px); border:1px solid rgba(255,255,255,0.04); border-radius:16px; padding:24px; cursor:pointer; transition:all 0.2s; position:relative; }
.plan:hover { border-color:rgba(255,255,255,0.2); transform:translateY(-2px); }
.plan.selected { border-color:rgba(255,255,255,0.3); box-shadow:0 0 30px rgba(255,255,255,0.06); }
.plan .name { font-size:1.3em; font-weight:600; color:rgba(255,255,255,0.7); margin-bottom:4px; }
.plan .price { font-size:2em; font-weight:700; color:#f0f0f0; margin:8px 0; }
.plan .price small { font-size:0.4em; color:rgba(255,255,255,0.35); font-weight:400; }
.plan .desc { color:rgba(255,255,255,0.35); font-size:0.85em; }
.plan .tag { display:inline-block; padding:2px 10px; border-radius:6px; font-size:0.75em; margin-bottom:8px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.06); color:rgba(255,255,255,0.6); }
.plan .badge-popular { position:absolute; top:-10px; right:20px; background:rgba(255,255,255,0.1); -webkit-backdrop-filter:blur(16px); backdrop-filter:blur(16px); color:rgba(255,255,255,0.8); border:1px solid rgba(255,255,255,0.12); padding:4px 12px; border-radius:20px; font-size:0.75em; font-weight:600; }
#paySection { display:none; text-align:center; margin-top:20px; }
.qr-wrap { display:inline-flex; flex-direction:column; align-items:center; background:#fff; padding:12px; border-radius:16px; }
.qr-wrap img { width:200px; height:200px; border-radius:8px; }
.status-text { margin-top:12px; font-size:1em; color:rgba(255,255,255,0.4); }
.status-text.paid { color:rgba(255,255,255,0.7); font-size:1.2em; font-weight:600; }
.key-display { background:rgba(255,255,255,0.02); -webkit-backdrop-filter:blur(20px); backdrop-filter:blur(20px); border:1px solid rgba(255,255,255,0.15); border-radius:12px; padding:16px; margin-top:16px; font-family:monospace; font-size:1.1em; color:rgba(255,255,255,0.7); letter-spacing:2px; }
.loading { display:inline-block; width:24px; height:24px; border:3px solid rgba(255,255,255,0.08); border-top-color:rgba(255,255,255,0.4); border-radius:50%; animation:spin 1s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
@keyframes fadeInOut { 0%{opacity:0;transform:translateY(-10px)} 15%{opacity:1;transform:translateY(0)} 85%{opacity:1} 100%{opacity:0;transform:translateY(-10px)} }
#errorMsg { color:rgba(255,255,255,0.5); text-align:center; margin-top:12px; }
.btn { padding:14px 40px; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.12); border-radius:100px; color:#f0f0f0; font-size:1.1em; cursor:pointer; display:none; margin-top:16px; transition:all 0.2s; }
.btn:hover { background:rgba(255,255,255,0.15); }
.btn-buy { padding:16px 60px; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.12); border-radius:100px; color:#f0f0f0; font-size:1.2em; font-weight:600; cursor:pointer; transition:all 0.2s; }
.btn-buy:hover { background:rgba(255,255,255,0.15); transform:translateY(-2px); }
.btn-buy:disabled { opacity:0.3; cursor:not-allowed; transform:none; }
/* ===== 身份码管理 ===== */
.identity-section { background:rgba(255,255,255,0.02); -webkit-backdrop-filter:blur(20px); backdrop-filter:blur(20px); border:1px solid rgba(255,255,255,0.04); border-radius:16px; padding:24px; margin-bottom:30px; }
.identity-section h3 { color:rgba(255,255,255,0.6); font-size:1em; margin-bottom:16px; }
.identity-row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.identity-row input { flex:1; min-width:150px; padding:12px 16px; border-radius:10px; border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.04); color:#f0f0f0; font-size:0.95em; outline:none; letter-spacing:2px; transition:border-color 0.2s; }
.identity-row input:focus { border-color:rgba(255,255,255,0.3); }
.identity-row input:disabled { opacity:0.4; }
.identity-row .btn-sm { padding:10px 20px; border:1px solid rgba(255,255,255,0.12); border-radius:10px; cursor:pointer; font-size:0.9em; font-weight:600; white-space:nowrap; transition:all 0.2s; }
.identity-row .btn-confirm { background:rgba(255,255,255,0.1); color:#f0f0f0; }
.identity-row .btn-confirm:hover { background:rgba(255,255,255,0.15); }
.identity-row .btn-gen { background:rgba(255,255,255,0.1); color:#f0f0f0; }
.identity-row .btn-gen:hover { background:rgba(255,255,255,0.15); }
.identity-badge { display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.06); border-radius:10px; padding:8px 16px; font-family:monospace; font-size:1.1em; letter-spacing:2px; color:rgba(255,255,255,0.7); }
.identity-status { margin-top:12px; font-size:0.85em; color:rgba(255,255,255,0.35); }
.identity-status .ok { color:rgba(255,255,255,0.5); }
.identity-status .warn { color:rgba(255,255,255,0.5); }
.identity-code-display { text-align:center; padding:16px; background:rgba(255,255,255,0.04); border-radius:10px; border:1px solid rgba(255,255,255,0.06); }
.footer { text-align:center; color:rgba(255,255,255,0.25); margin-top:40px; font-size:0.85em; }
.footer a { color:rgba(255,255,255,0.5); text-decoration:none; }
.footer a:hover { color:rgba(255,255,255,0.7); }
/* 查询订单区 */
.query-section { margin-top:40px; border-top:1px solid rgba(255,255,255,0.06); padding-top:30px; }
.query-section h3 { text-align:center; color:rgba(255,255,255,0.4); font-size:0.95em; margin-bottom:16px; font-weight:400; }
.query-box { display:flex; justify-content:center; gap:10px; max-width:500px; margin:0 auto; flex-wrap:wrap; }
.query-box input { flex:1; min-width:180px; padding:12px 16px; border-radius:10px; border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.04); color:#f0f0f0; font-size:0.95em; outline:none; transition:border-color 0.2s; }
.query-box input:focus { border-color:rgba(255,255,255,0.3); }
.query-box select { padding:12px 14px; border-radius:10px; border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.04); -webkit-backdrop-filter:blur(20px); backdrop-filter:blur(20px); color:rgba(255,255,255,0.7); font-size:0.95em; outline:none; cursor:pointer; flex-shrink:0; transition:border-color 0.2s; }
.query-box select:focus { border-color:rgba(255,255,255,0.3); }
.query-box select option { background:#1a1a1a; color:#f0f0f0; }
.query-box button { padding:12px 24px; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.12); border-radius:10px; color:#f0f0f0; font-size:0.95em; cursor:pointer; white-space:nowrap; transition:all 0.2s; }
.query-box button:hover { background:rgba(255,255,255,0.15); }
#queryResult { text-align:center; margin-top:16px; color:rgba(255,255,255,0.4); font-size:0.9em; }
#queryResult .ok { color:rgba(255,255,255,0.5); }
#queryResult .fail { color:rgba(255,255,255,0.5); }
/* 详细查询结果 */
.query-detail { text-align:left; background:rgba(255,255,255,0.02); -webkit-backdrop-filter:blur(20px); backdrop-filter:blur(20px); border:1px solid rgba(255,255,255,0.04); border-radius:16px; padding:20px; margin-top:16px; max-width:650px; margin-left:auto; margin-right:auto; }
.query-detail .qr-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid rgba(255,255,255,0.06); font-size:0.9em; }
.query-detail .qr-row:last-child { border-bottom:none; }
.query-detail .qr-label { color:rgba(255,255,255,0.4); }
.query-detail .qr-value { color:rgba(255,255,255,0.7); text-align:right; word-break:break-all; max-width:60%; }
.query-detail .qr-value .code { color:rgba(255,255,255,0.7); font-family:monospace; letter-spacing:1px; }
.query-detail .qr-value .paid { color:rgba(255,255,255,0.6); font-weight:600; }
.query-detail .qr-value .pending { color:rgba(255,255,255,0.55); }
.query-detail .qr-value .closed { color:rgba(255,255,255,0.45); }
.query-detail .qr-value .green { color:rgba(255,255,255,0.6); }
.query-detail .qr-value .yellow { color:rgba(255,255,255,0.55); }
.query-detail .qr-section-title { color:rgba(255,255,255,0.5); font-size:0.85em; font-weight:600; margin:12px 0 6px; padding-top:8px; border-top:1px solid rgba(255,255,255,0.06); }
.query-detail .benefit-item { background:rgba(255,255,255,0.04); border-radius:8px; padding:10px 14px; margin-bottom:6px; }
.query-detail .benefit-item .b-row { display:flex; justify-content:space-between; font-size:0.85em; padding:2px 0; }
.query-detail .benefit-item .b-label { color:rgba(255,255,255,0.4); }
.query-detail .benefit-item .b-value { color:rgba(255,255,255,0.65); }
.query-detail .order-item { background:rgba(255,255,255,0.04); border-radius:8px; padding:10px 14px; margin-bottom:4px; display:flex; justify-content:space-between; font-size:0.85em; }
.query-detail .order-item .o-left { color:rgba(255,255,255,0.4); }
.query-detail .order-item .o-right { color:rgba(255,255,255,0.7); text-align:right; }
</style>
<!-- 极验行为验证 4.0 -->
<script src="https://static.geetest.com/v4/gt4.js"></script>
</head>
<body>
<nav class="navbar">
    <div class="container">
        <a href="/landing/" class="navbar-brand">ShortParse</a>
        <div class="navbar-links" id="navLinks">
            <a href="/landing/">功能</a>
            <a href="/landing/#pricing">价格</a>
            <a href="/">解析工具</a>
            <a href="/admin/" target="_blank">管理</a>
            <a href="/pay/" class="navbar-cta" target="_blank">开始使用</a>
        </div>
        <button class="menu-toggle" onclick="document.getElementById('navLinks').classList.toggle('open')">☰</button>
    </div>
</nav>
<div class="container">

<h1>🎬 短视频解析</h1>
<p class="subtitle">支持抖音 / 快手 / 小红书 / 皮皮虾四大主流平台 · 选购套餐获取更多使用次数</p>

<!-- ===== 身份码管理 ===== -->
<div class="identity-section" id="identitySection">
    <h3>🔑 身份码</h3>
    <div class="identity-row">
        <input type="text" id="identityCode" placeholder="输入身份码（5位）" maxlength="5" style="flex:0.4;min-width:140px;">
        <button class="btn-sm btn-confirm" onclick="confirmIdentity()">确认</button>
        <span style="color:rgba(255,255,255,0.3);">或</span>
        <button class="btn-sm btn-gen" onclick="generateIdentity()">🆕 生成新身份码</button>
        <a href="https://spqsy.kcucu.com/" style="display:inline-flex;align-items:center;gap:4px;color:rgba(255,255,255,0.4);text-decoration:none;font-size:0.9em;padding:10px 20px;border:1px solid rgba(255,255,255,0.08);border-radius:100px;transition:all 0.2s;margin-left:auto;">返回解析页 →</a>
    </div>
    <div id="geetestOverlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);z-index:99998;" onclick="document.getElementById('geetestOverlay').style.display='none';document.getElementById('geetestWrap').style.display='none';"></div>
    <div id="geetestWrap" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:99999;background:transparent;"></div>
    <div class="identity-status" id="identityStatus">请填写或生成身份码后购买套餐</div>
    <div id="identityInfo" style="display:none;margin-top:10px;"></div>
</div>

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

<div style="text-align:center;margin-bottom:30px;display:flex;justify-content:center;gap:12px;align-items:center;flex-wrap:wrap;">
    <button class="btn-buy" onclick="createOrder()">💳 立即购买</button>
</div>
<div style="text-align:center;font-size:0.8em;color:rgba(255,255,255,0.3);margin-top:-20px;margin-bottom:30px;" id="buyHint">💡 请先在上方填写或生成身份码，再选择套餐购买</div>

<div id="paySection">
    <div class="qr-wrap" id="qrWrap">
        <div id="qrPlaceholder" style="width:200px;height:200px;display:flex;align-items:center;justify-content:center;flex-direction:column;">
            <div class="loading"></div>
            <div style="color:#999;margin-top:8px;font-size:0.85em;">生成二维码中...</div>
        </div>
        <img id="qrImg" style="display:none;width:200px;height:200px;" alt="支付宝扫码支付">
    </div>
    <div class="status-text" id="statusText">等待付款...</div>
    <div id="resultDisplay" style="display:none;">
        <div class="key-display" id="resultCode"></div>
        <div style="color:rgba(255,255,255,0.4);margin-top:12px;font-size:0.85em;" id="resultHint"></div>
    </div>
    <div id="errorMsg"></div>
    <button class="btn" id="newOrderBtn" onclick="resetPage()">重新购买</button>
</div>
<!-- ===== 查询订单区 ===== -->
<div class="query-section">
    <h3>查询订单 / 身份码信息</h3>
    <div class="query-box">
        <select id="queryType">
            <option value="auto">🤖 自动识别</option>
            <option value="order_id">📋 订单号</option>
            <option value="alipay">💰 支付宝交易号</option>
            <option value="identity_code">🔑 身份码</option>
        </select>
        <input type="text" id="queryOrderId" placeholder="输入订单号 / 支付宝交易号 / 身份码" onkeydown="if(event.key==='Enter')queryOrder()">
        <button onclick="queryOrder()">查 询</button>
    </div>
    <div id="queryResult"></div>
</div>

<div class="footer">
    <p>遇到问题？联系管理员 · QQ：2556208918 · 微信：xlz51920xlz</p>
</div>
</div>

<script>
let currentOrderId = null;
let pollTimer = null;
let selectedPlan = null;
let currentIdentityCode = null;
let geetestCaptcha = null; // 极验验证码实例

function selectPlan(el) {
    document.querySelectorAll('.plan').forEach(p => p.classList.remove('selected'));
    el.classList.add('selected');
    selectedPlan = JSON.parse(el.dataset.plan);
    document.getElementById('paySection').style.display = 'none';
    document.getElementById('errorMsg').textContent = '';
}

// ===== 生成新身份码（带极验验证） =====
async function generateIdentity() {
    // 先检查 localStorage，已有身份码就先验证是否还存在
    const saved = localStorage.getItem('pay_identity_code');
    if (saved) {
        document.getElementById('identityCode').value = saved;
        // 调 API 验证服务端还有没有这个码
        try {
            const res = await fetch('query.php?identity_code=' + encodeURIComponent(saved));
            const data = await res.json();
            if (data.success) {
                // 码还存在 → 提示每人仅一个
                document.getElementById('identityStatus').innerHTML = '<span class="warn">⚠️ 每人仅能拥有一个身份码</span>';
                return;
            }
        } catch(e) {}
        // 码不存在或查询失败 → 清 localStorage，允许生成新的
        localStorage.removeItem('pay_identity_code');
        localStorage.removeItem('identity_code');
        document.getElementById('identityCode').value = '';
        document.getElementById('identityStatus').innerHTML = '<span class="warn">旧身份码已失效，可生成新的</span>';
    }

    if (!geetestCaptcha) {
        showError('验证码未加载，请刷新页面重试');
        return;
    }

    // 显示极验验证码（居中弹窗+遮罩）
    document.getElementById('geetestOverlay').style.display = 'block';
    document.getElementById('geetestWrap').style.display = 'block';
    geetestCaptcha.reset();
}

// ===== 确认身份码 =====
async function confirmIdentity() {
    const input = document.getElementById('identityCode').value.trim().toUpperCase();
    const statusEl = document.getElementById('identityStatus');
    const infoEl = document.getElementById('identityInfo');

    if (!input || input.length < 4) {
        statusEl.innerHTML = '<span class="warn">⚠️ 请输入有效的身份码</span>';
        return;
    }

    statusEl.innerHTML = '验证中...';

    try {
        const res = await fetch('query.php?identity_code=' + encodeURIComponent(input));
        const data = await res.json();

        if (!data.success) {
            statusEl.innerHTML = '<span class="warn">❌ ' + (data.error || '身份码不存在') + '</span>';
            infoEl.style.display = 'none';
            currentIdentityCode = null;
            // 码失效了，清 localStorage 以免卡死
            localStorage.removeItem('pay_identity_code');
            localStorage.removeItem('identity_code');
            enableBuyBtn(false);
            return;
        }

        currentIdentityCode = input;
        localStorage.setItem('pay_identity_code', input);
        localStorage.setItem('identity_code', input); // 同步到主页面

        // 完整展示身份码信息（复用查询页的渲染）
        var fullHtml = renderIdentityDetail(data);
        // 移除最后的 </div> 闭合，因为我们外面包了一层
        infoEl.innerHTML = fullHtml;
        infoEl.style.display = 'block';
        statusEl.innerHTML = '<span class="ok">✅ 身份码已确认，可选购套餐续费</span>';
        enableBuyBtn(true);
    } catch (e) {
        statusEl.innerHTML = '<span class="warn">❌ 查询失败: ' + e.message + '</span>';
    }
}

function enableBuyBtn(enabled) {
    var btn = document.querySelector('.btn-buy');
    if (!btn) return; // 安全保护：按钮可能还未渲染
    btn.disabled = !enabled;
    btn.style.opacity = enabled ? '1' : '0.4';
}

function showError(msg) {
    document.getElementById('identityStatus').innerHTML = '<span class="warn">❌ ' + msg + '</span>';
}

function showToast(msg) {
    var t = document.createElement('div');
    t.style.cssText = 'position:fixed;top:20px;right:20px;padding:12px 24px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.12);color:#f0f0f0;border-radius:10px;z-index:999;font-size:0.9em;-webkit-backdrop-filter:blur(20px);backdrop-filter:blur(20px);animation:fadeInOut 2s ease forwards;';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function(){t.remove();}, 2500);
}

// 极验验证成功后的回调
function onGeetestSuccess() {
    if (!geetestCaptcha) return;
    const result = geetestCaptcha.getValidate();

    const btn = document.querySelector('.btn-gen');
    btn.textContent = '身份码生成中...';
    btn.disabled = true;

    // 先验证极验，再创建身份码
    fetch('geetest_verify.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams(result),
    })
    .then(r => r.json())
    .then(verifyData => {
        if (!verifyData.success || !verifyData.geetest_pass) {
            showError(verifyData.error || '验证未通过');
            btn.textContent = '🆕 生成新身份码';
            btn.disabled = false;
            return;
        }
        return fetch('create_identity.php');
    })
    .then(r => r && r.json())
    .then(data => {
        if (!data) return;
        if (!data.success) {
            showError(data.error || '生成失败');
            return;
        }
        document.getElementById('identityCode').value = data.identity_code;
        localStorage.setItem('pay_identity_code', data.identity_code);
        localStorage.setItem('identity_code', data.identity_code); // 同步到主页面
        return confirmIdentity().then(() => {
            showToast(data.is_existing
                ? '✅ 已绑定身份码: ' + data.identity_code
                : '✅ 新身份码已生成: ' + data.identity_code);
        });
    })
    .catch(e => {
        showError('网络错误: ' + e.message);
    })
    .finally(() => {
        btn.textContent = '🆕 生成新身份码';
        btn.disabled = false;
    });
}

async function createOrder() {
    if (!selectedPlan) {
        document.getElementById('errorMsg').textContent = '请先选择一个套餐';
        document.getElementById('paySection').style.display = 'block';
        document.getElementById('qrWrap').style.display = 'none';
        return;
    }
    if (!currentIdentityCode) {
        document.getElementById('identityStatus').innerHTML = '<span class="warn">⚠️ 请先填写或生成身份码</span>';
        document.getElementById('paySection').style.display = 'none';
        return;
    }
    
    document.getElementById('paySection').style.display = 'block';
    document.getElementById('qrWrap').style.display = '';
    document.getElementById('qrImg').style.display = 'none';
    document.getElementById('qrPlaceholder').style.display = 'flex';
    document.getElementById('statusText').textContent = '生成订单中...';
    document.getElementById('statusText').className = 'status-text';
    document.getElementById('resultDisplay').style.display = 'none';
    document.getElementById('errorMsg').textContent = '';
    document.getElementById('newOrderBtn').style.display = 'none';
    
    try {
        const orderData = Object.assign({}, selectedPlan, {identity_code: currentIdentityCode});
        const res = await fetch('create.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(orderData)
        });
        const data = await res.json();
        
        if (!data.success) {
            document.getElementById('errorMsg').textContent = '创建订单失败: ' + data.error;
            return;
        }
        
        currentOrderId = data.order_id;
        document.getElementById('qrPlaceholder').style.display = 'none';
        document.getElementById('qrImg').style.display = 'block';
        document.getElementById('qrImg').src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=10&data=' + encodeURIComponent(data.qr_code);
        document.getElementById('statusText').textContent = '请打开支付宝扫码支付';
        
        // 开始轮询支付状态
        startPolling(data.order_id);
    } catch (e) {
        document.getElementById('errorMsg').textContent = '网络错误: ' + e.message;
    }
}

function startPolling(orderId) {
    let attempts = 0;
    const maxAttempts = 120;
    
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
                document.getElementById('resultCode').textContent = currentIdentityCode;
                document.getElementById('resultHint').textContent = '权益已添加到身份码 ' + currentIdentityCode + '，在解析页面输入即可使用';
                document.getElementById('resultDisplay').style.display = 'block';
                document.getElementById('newOrderBtn').style.display = 'inline-block';
                // 刷新身份码信息
                await confirmIdentity();
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
    const input = document.getElementById('queryOrderId').value.trim();
    const queryType = document.getElementById('queryType').value;
    const resultEl = document.getElementById('queryResult');

    if (!input) {
        resultEl.innerHTML = '<span class="fail">请输入查询内容</span>';
        return;
    }

    resultEl.innerHTML = '查询中...';

    let param = '', value = '';
    if (queryType === 'auto') {
        if (/^[A-Z0-9]{5}$/i.test(input)) {
            param = 'identity_code';
        } else if (/^SV/i.test(input)) {
            param = 'order_id';
        } else if (/^\d{10,}$/.test(input.replace(/-/g, ''))) {
            param = 'alipay_trade_no';
        } else {
            param = 'order_id';
        }
        value = encodeURIComponent(input);
    } else if (queryType === 'identity_code') {
        param = 'identity_code';
        value = encodeURIComponent(input);
    } else if (queryType === 'alipay') {
        param = 'alipay_trade_no';
        value = encodeURIComponent(input);
    } else {
        param = 'order_id';
        value = encodeURIComponent(input);
    }

    try {
        const res = await fetch('query.php?' + param + '=' + value);
        const data = await res.json();

        if (!data.success) {
            resultEl.innerHTML = '<span class="fail">❌ ' + (data.error || '查询失败') + '</span>';
            return;
        }

        if (data.type === 'identity') {
            resultEl.innerHTML = renderIdentityDetail(data);
        } else {
            resultEl.innerHTML = renderOrderDetail(data);
        }
    } catch (e) {
        resultEl.innerHTML = '<span class="fail">查询失败: ' + e.message + '</span>';
    }
}

function renderOrderDetail(data) {
    var paid = data.status === 'paid';
    var statusHtml = paid ? '<span class="paid">✅ 已支付</span>' :
        (data.status === 'pending' ? '<span class="pending">⏳ 等待支付</span>' :
        '<span class="closed">❌ 已关闭</span>');

    var html = '<div class="query-detail">';
    html += '<div class="qr-row"><span class="qr-label">订单号</span><span class="qr-value">' + data.order_id + '</span></div>';
    if (data.alipay_trade_no) {
        html += '<div class="qr-row"><span class="qr-label">支付宝交易号</span><span class="qr-value">' + data.alipay_trade_no + '</span></div>';
    }
    html += '<div class="qr-row"><span class="qr-label">套餐</span><span class="qr-value">' + data.plan_name + '</span></div>';
    html += '<div class="qr-row"><span class="qr-label">金额</span><span class="qr-value">¥' + data.amount.toFixed(2) + '</span></div>';
    html += '<div class="qr-row"><span class="qr-label">状态</span><span class="qr-value">' + statusHtml + '</span></div>';

    if (data.total_count > 0) {
        html += '<div class="qr-row"><span class="qr-label">总次数</span><span class="qr-value">' + data.total_count + ' 次</span></div>';
    }
    if (data.expires_days > 0) {
        html += '<div class="qr-row"><span class="qr-label">有效期</span><span class="qr-value">' + data.expires_days + ' 天</span></div>';
    }
    if (data.daily_limit > 0) {
        html += '<div class="qr-row"><span class="qr-label">每日限额</span><span class="qr-value">' + data.daily_limit + ' 次/天</span></div>';
    }
    html += '<div class="qr-row"><span class="qr-label">创建时间</span><span class="qr-value">' + data.created_at + '</span></div>';
    if (data.paid_at) {
        html += '<div class="qr-row"><span class="qr-label">支付时间</span><span class="qr-value">' + data.paid_at + '</span></div>';
    }

    // 身份码信息
    if (data.identity_code) {
        html += '<div class="qr-section-title">🔑 关联身份码</div>';
        html += '<div class="qr-row"><span class="qr-label">身份码</span><span class="qr-value"><span class="code">' + data.identity_code + '</span></span></div>';
        if (data.identity_info) {
            var ii = data.identity_info;
            html += '<div class="qr-row"><span class="qr-label">状态</span><span class="qr-value">' + (ii.is_active ? '<span class="green">启用</span>' : '<span class="closed">禁用</span>') + '</span></div>';
            // 总调用
            if (ii.total_usage !== undefined) {
                html += '<div class="qr-row"><span class="qr-label">总调用</span><span class="qr-value" style="color:rgba(255,255,255,0.6);font-weight:600;">' + ii.total_usage + ' 次</span></div>';
            }
            if (ii.benefits && ii.benefits.length > 0) {
                ii.benefits.forEach(function(b) {
                    var bName = b.type === 'free_daily' ? '免费每日' :
                        (b.type === 'count' ? '按次' :
                        (b.type === 'monthly' ? '包月' :
                        (b.type === 'daily' ? '包天' : '永久')));
                    html += '<div class="benefit-item">';
                    html += '<div class="b-row"><span class="b-label">权益类型</span><span class="b-value">' + bName + '</span></div>';
                    // 按次：显示 已用/总量
                    if (b.total_count > 0) {
                        html += '<div class="b-row"><span class="b-label">次数</span><span class="b-value">' + b.used_count + ' / ' + b.total_count + '（剩余 ' + b.remaining + '）</span></div>';
                    }
                    // 包月/不限量：显示已使用次数
                    if (b.total_count === 0 && b.used_count > 0) {
                        html += '<div class="b-row"><span class="b-label">已使用</span><span class="b-value">' + b.used_count + ' 次</span></div>';
                    }
                    // 今日已用（有限额的）
                    if (b.daily_limit > 0) {
                        html += '<div class="b-row"><span class="b-label">今日已用</span><span class="b-value">' + (b.today_used || 0) + ' / ' + b.daily_limit + '（剩余 ' + (b.today_remaining || b.daily_limit) + '）</span></div>';
                    }
                    // 包月也尝试查今日用量（通过 daily_usage 表直接查）
                    if (b.type === 'monthly' && b.daily_limit === 0) {
                        // 包月虽然没有 daily_limit，但有 total_usage 可以看
                    }
                    if (b.expires_at) {
                        html += '<div class="b-row"><span class="b-label">到期</span><span class="b-value">' + b.expires_at + '</span></div>';
                    }
                    if (b.note) {
                        html += '<div class="b-row"><span class="b-label">备注</span><span class="b-value">' + b.note + '</span></div>';
                    }
                    html += '</div>';
                });
            }
            if (ii.last_used_at) {
                html += '<div class="qr-row"><span class="qr-label">最后使用</span><span class="qr-value">' + ii.last_used_at + '</span></div>';
            }
        }
    }

    html += '</div>';
    return html;
}

function renderIdentityDetail(data) {
    var html = '<div class="query-detail">';
    html += '<div class="qr-section-title">🔑 身份码信息</div>';
    html += '<div class="qr-row"><span class="qr-label">身份码</span><span class="qr-value"><span class="code">' + data.identity_code + '</span></span></div>';
    html += '<div class="qr-row"><span class="qr-label">状态</span><span class="qr-value">' + (data.is_active ? '<span class="green">启用</span>' : '<span class="closed">禁用</span>') + '</span></div>';
    // 总调用
    if (data.total_usage !== undefined) {
        html += '<div class="qr-row"><span class="qr-label">总调用</span><span class="qr-value" style="color:rgba(255,255,255,0.6);font-weight:600;">' + data.total_usage + ' 次</span></div>';
    }
    html += '<div class="qr-row"><span class="qr-label">创建时间</span><span class="qr-value">' + data.created_at + '</span></div>';
    if (data.last_used_at) {
        html += '<div class="qr-row"><span class="qr-label">最后使用</span><span class="qr-value">' + data.last_used_at + '</span></div>';
    }

    // 权益列表
    if (data.benefits && data.benefits.length > 0) {
        html += '<div class="qr-section-title">📦 权益详情</div>';
        data.benefits.forEach(function(b) {
            var bName = b.type === 'free_daily' ? '免费每日' :
                (b.type === 'count' ? '按次' :
                (b.type === 'monthly' ? '包月' :
                (b.type === 'daily' ? '包天' :
                (b.type === 'lifetime' ? '永久' : '永久（每日限次）'))));
            html += '<div class="benefit-item">';
            html += '<div class="b-row"><span class="b-label">类型</span><span class="b-value">' + bName + '</span></div>';
            // 按次：已用/总量
            if (b.total_count > 0) {
                html += '<div class="b-row"><span class="b-label">次数</span><span class="b-value">已用 ' + b.used_count + ' / 共 ' + b.total_count + '（剩余 <strong style="color:rgba(255,255,255,0.6);">' + b.remaining + '</strong>）</span></div>';
            }
            // 包月/不限量：显示已使用
            if (b.total_count === 0 && b.used_count > 0) {
                html += '<div class="b-row"><span class="b-label">已使用</span><span class="b-value"><strong style="color:rgba(255,255,255,0.55);">' + b.used_count + '</strong> 次</span></div>';
            }
            // 每日限额
            if (b.daily_limit > 0) {
                var tu = b.today_used || 0;
                var tr = b.today_remaining !== undefined ? b.today_remaining : b.daily_limit;
                html += '<div class="b-row"><span class="b-label">今日已用</span><span class="b-value">' + tu + ' / ' + b.daily_limit + '（剩余 <strong style="color:rgba(255,255,255,0.55);">' + tr + '</strong>）</span></div>';
            }
            if (b.expires_at) {
                html += '<div class="b-row"><span class="b-label">到期时间</span><span class="b-value">' + b.expires_at + '</span></div>';
            }
            if (b.note) {
                html += '<div class="b-row"><span class="b-label">备注</span><span class="b-value">' + b.note + '</span></div>';
            }
            html += '</div>';
        });
    }

    // 订单记录
    if (data.orders && data.orders.length > 0) {
        html += '<div class="qr-section-title">📋 购买记录</div>';
        data.orders.forEach(function(o) {
            var os = o.status === 'paid' ? '<span class="green">已支付</span>' :
                (o.status === 'pending' ? '<span class="yellow">待支付</span>' : '<span class="closed">已关闭</span>');
            html += '<div class="order-item">';
            html += '<div class="o-left">' + o.plan_name + '<br><small style="color:rgba(255,255,255,0.3);">' + o.created_at + '</small></div>';
            html += '<div class="o-right">¥' + o.amount.toFixed(2) + ' ' + os + '</div>';
            html += '</div>';
        });
    }

    html += '</div>';
    return html;
}

// 页面加载
document.addEventListener('DOMContentLoaded', () => {
    selectedPlan = JSON.parse(document.querySelector('.plan.selected').dataset.plan);
    // 恢复上次保存的身份码
    const saved = localStorage.getItem('pay_identity_code');
    if (saved) {
        document.getElementById('identityCode').value = saved;
        confirmIdentity();
    }

    // 初始化极验行为验证
    function initGeetest() {
        if (typeof initGeetest4 !== 'function') {
            // CDN可能还没加载完，等500ms再试
            setTimeout(initGeetest, 500);
            return;
        }
        initGeetest4({
            captchaId: '8ae32ba04bfc50cd4f76bcab250e3fc3',
            product: 'float',
        }, function (captcha) {
            geetestCaptcha = captcha;
            captcha.appendTo('#geetestWrap');
            captcha.onSuccess(function () {
                document.getElementById('geetestWrap').style.display = 'none';
                onGeetestSuccess();
            });
        });
    }
    initGeetest();
});

// 回车键触发
document.getElementById('identityCode').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') confirmIdentity();
});

// 双击套餐直接跳转到支付
document.querySelectorAll('.plan').forEach(p => {
    p.addEventListener('dblclick', function() {
        selectPlan(this);
        createOrder();
    });
});
</script>
</body>
</html>
