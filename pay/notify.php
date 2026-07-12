<?php
/**
 * 支付宝异步通知处理
 * 支付成功后自动将权益添加到身份码
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

$identity_code = $order['identity_code'];
if (empty($identity_code)) {
    file_put_contents(__DIR__ . '/../data/alipay_notify.log', 
        date('Y-m-d H:i:s') . " 订单无关联身份码: {$out_trade_no}\n", FILE_APPEND);
    echo 'failure';
    exit;
}

// 查询身份码
$st = $db->prepare("SELECT id FROM identities WHERE code = ? AND is_active = 1");
$st->bindValue(1, $identity_code, SQLITE3_TEXT);
$ident = $st->execute()->fetchArray(SQLITE3_ASSOC);
if (!$ident) {
    file_put_contents(__DIR__ . '/../data/alipay_notify.log', 
        date('Y-m-d H:i:s') . " 身份码无效: {$identity_code}\n", FILE_APPEND);
    echo 'failure';
    exit;
}
$identity_id = (int)$ident['id'];

$plan_type = $order['key_type'];
$total_count = intval($order['total_count']);
$expires_days = intval($order['expires_days']);
$daily_limit = intval($order['daily_limit']);

// 计算过期时间和总次数
$expires_at = null;
$insert_total = $total_count;

if ($plan_type === 'monthly' || $plan_type === 'daily') {
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_days} days"));
}
if (in_array($plan_type, ['lifetime', 'lifetime_daily', 'monthly', 'daily'])) {
    $insert_total = 0; // 不限量
}

$daily_limit_val = 0;
if ($plan_type === 'lifetime_daily') {
    $daily_limit_val = $daily_limit > 0 ? $daily_limit : 150;
}
if ($plan_type === 'daily') {
    $daily_limit_val = $daily_limit > 0 ? $daily_limit : 0;
}

$note = "用户购买-{$order['plan_name']}";
$note .= $buyer_email ? " ({$buyer_email})" : '';

// 将权益插入 identity_benefits
$stmt = $db->prepare("INSERT INTO identity_benefits (identity_id, type, total_count, used_count, expires_at, daily_limit, note, created_at) VALUES (?, ?, ?, 0, ?, ?, ?, datetime('now', '+8 hours'))");
$stmt->bindValue(1, $identity_id, SQLITE3_INTEGER);
$stmt->bindValue(2, $plan_type, SQLITE3_TEXT);
$stmt->bindValue(3, $insert_total, SQLITE3_INTEGER);
$stmt->bindValue(4, $expires_at, SQLITE3_TEXT);
$stmt->bindValue(5, $daily_limit_val, SQLITE3_INTEGER);
$stmt->bindValue(6, $note, SQLITE3_TEXT);
$stmt->execute();
$benefit_id = $db->lastInsertRowID();

// 更新订单状态
$stmt = $db->prepare("UPDATE orders SET status = 'paid', alipay_trade_no = ?, buyer_email = ?, paid_at = datetime('now', '+8 hours') WHERE order_id = ?");
$stmt->bindValue(1, $trade_no, SQLITE3_TEXT);
$stmt->bindValue(2, $buyer_email, SQLITE3_TEXT);
$stmt->bindValue(3, $out_trade_no, SQLITE3_TEXT);
$stmt->execute();

file_put_contents(__DIR__ . '/../data/alipay_notify.log', 
    date('Y-m-d H:i:s') . " 支付成功: {$out_trade_no} -> 身份码: {$identity_code} (权益ID: {$benefit_id})\n", FILE_APPEND);

$db->close();

echo 'success';
