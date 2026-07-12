<?php
/**
 * 创建支付订单 + 生成 QR 码
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/alipay.php';

// 读取 POST JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonExit(400, '请求数据格式错误');

$plan_id = $input['id'] ?? '';
$plan_name = $input['name'] ?? '';
$plan_type = $input['type'] ?? '';
$count = intval($input['count'] ?? 0);
$days = intval($input['days'] ?? 0);
$daily_limit = intval($input['daily_limit'] ?? 0);
$price = floatval($input['price'] ?? 0);
$identity_code = strtoupper(trim($input['identity_code'] ?? ''));

if (!$plan_id || !$plan_name || !$plan_type || $price <= 0) {
    jsonExit(400, '套餐参数不完整');
}
if (empty($identity_code)) {
    jsonExit(400, '缺少身份码');
}

$db = getDB();

// 验证身份码是否存在
$st = $db->prepare("SELECT id FROM identities WHERE code = ? AND is_active = 1");
$st->bindValue(1, $identity_code, SQLITE3_TEXT);
$ident = $st->execute()->fetchArray(SQLITE3_ASSOC);
if (!$ident) {
    jsonExit(400, '身份码无效或已被禁用');
}
$alipay = new Alipay();

// 生成订单号
$date = date('Ymd');
$rand = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
$out_trade_no = "SV{$date}{$rand}";

try {
    // 调用支付宝创建二维码
    $result = $alipay->precreate(
        $out_trade_no,
        $price,
        "短视频解析API - {$plan_name}",
        "{$plan_name} 密钥"
    );
    
    $qr_code = $result['qr_code'];
    
    // 保存订单到数据库（含身份码）
    $stmt = $db->prepare("INSERT INTO orders (order_id, plan_type, plan_name, amount, total_count, expires_days, key_type, daily_limit, note, status, identity_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bindValue(1, $out_trade_no, SQLITE3_TEXT);
    $stmt->bindValue(2, $plan_id, SQLITE3_TEXT);
    $stmt->bindValue(3, $plan_name, SQLITE3_TEXT);
    $stmt->bindValue(4, $price, SQLITE3_FLOAT);
    $stmt->bindValue(5, $count, SQLITE3_INTEGER);
    $stmt->bindValue(6, $days, SQLITE3_INTEGER);
    $stmt->bindValue(7, $plan_type, SQLITE3_TEXT);
    $stmt->bindValue(8, $daily_limit, SQLITE3_INTEGER);
    $stmt->bindValue(9, "{$plan_name} 订单", SQLITE3_TEXT);
    $stmt->bindValue(10, 'pending', SQLITE3_TEXT);
    $stmt->bindValue(11, $identity_code, SQLITE3_TEXT);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'order_id' => $out_trade_no,
        'qr_code' => $qr_code,
        'amount' => $price,
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    jsonExit(500, $e->getMessage());
}
