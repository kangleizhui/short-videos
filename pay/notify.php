<?php
/**
 * 支付宝异步通知处理
 * 支付成功后自动生成 API 密钥
 */
require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/alipay.php';

// 记录原始通知
file_put_contents(__DIR__ . '/../data/alipay_notify.log', 
    date('Y-m-d H:i:s') . " " . json_encode($_POST) . "\n", FILE_APPEND);

$alipay = new Alipay();

// 验证签名
$verify = $alipay->verifyNotify($_POST);
if (!$verify) {
    file_put_contents(__DIR__ . '/../data/alipay_notify.log', 
        date('Y-m-d H:i:s') . " 签名验证失败\n", FILE_APPEND);
    echo 'failure';
    exit;
}

$out_trade_no = $_POST['out_trade_no'] ?? '';
$trade_no = $_POST['trade_no'] ?? '';
$trade_status = $_POST['trade_status'] ?? '';
$buyer_email = $_POST['buyer_email'] ?? '';

if ($trade_status !== 'TRADE_SUCCESS' && $trade_status !== 'TRADE_FINISHED') {
    // 非成功状态，直接返回 success
    echo 'success';
    exit;
}

$db = getDB();

// 查询订单
$stmt = $db->prepare("SELECT * FROM orders WHERE order_id = ?");
$stmt->bindValue(1, $out_trade_no, SQLITE3_TEXT);
$order = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$order) {
    file_put_contents(__DIR__ . '/../data/alipay_notify.log', 
        date('Y-m-d H:i:s') . " 订单不存在: {$out_trade_no}\n", FILE_APPEND);
    echo 'failure';
    exit;
}

// 防止重复处理
if ($order['status'] === 'paid') {
    echo 'success';
    exit;
}

// 生成 API 密钥
$key = strtoupper(bin2hex(random_bytes(12)));
$key = substr($key, 0, 4) . '-' . substr($key, 4, 4) . '-' . substr($key, 8, 4) . '-' . substr($key, 12, 4) . '-' . substr($key, 16);

$plan_type = $order['key_type'];
$total_count = intval($order['total_count']);
$expires_days = intval($order['expires_days']);

$expires_at = null;
$total_count_for_lifetime = $total_count;
if ($plan_type === 'monthly' || $plan_type === 'daily') {
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_days} days"));
}
// 永久套餐：无过期时间，次数设为0（表示无限）
if ($plan_type === 'lifetime') {
    $expires_at = null;
    $total_count_for_lifetime = 0;
}
// 终身畅享套餐：无过期，次数无限，但每日限次数
if ($plan_type === 'lifetime_daily') {
    $expires_at = null;
    $total_count_for_lifetime = 0;
}

$note = "用户购买-{$order['plan_name']}";
$note .= $buyer_email ? " ({$buyer_email})" : '';

// 插入密钥
$stmt = $db->prepare("INSERT INTO api_keys (key_string, type, total_count, used_count, expires_at, is_active, note, daily_limit, created_at) VALUES (?, ?, ?, 0, ?, 1, ?, ?, datetime('now', '+8 hours'))");
$stmt->bindValue(1, $key, SQLITE3_TEXT);
$stmt->bindValue(2, $plan_type, SQLITE3_TEXT);
$stmt->bindValue(3, $total_count_for_lifetime, SQLITE3_INTEGER);
$stmt->bindValue(4, $expires_at, SQLITE3_TEXT);
$stmt->bindValue(5, $note, SQLITE3_TEXT);
// 终身畅享套餐设置每日上限
$daily_limit = $plan_type === 'lifetime_daily' ? 150 : 0;
$stmt->bindValue(6, $daily_limit, SQLITE3_INTEGER);
$stmt->execute();
$key_id = $db->lastInsertRowID();

// 更新订单状态
$stmt = $db->prepare("UPDATE orders SET status = 'paid', alipay_trade_no = ?, buyer_email = ?, key_id = ?, paid_at = datetime('now', '+8 hours') WHERE order_id = ?");
$stmt->bindValue(1, $trade_no, SQLITE3_TEXT);
$stmt->bindValue(2, $buyer_email, SQLITE3_TEXT);
$stmt->bindValue(3, $key_id, SQLITE3_INTEGER);
$stmt->bindValue(4, $out_trade_no, SQLITE3_TEXT);
$stmt->execute();

file_put_contents(__DIR__ . '/../data/alipay_notify.log', 
    date('Y-m-d H:i:s') . " 支付成功: {$out_trade_no} -> 密钥: {$key}\n", FILE_APPEND);

$db->close();

echo 'success';
