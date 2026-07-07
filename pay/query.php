<?php
/**
 * 查询订单状态
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../admin/config.php';

$order_id = $_GET['order_id'] ?? '';
if (!$order_id) {
    echo json_encode(['success' => false, 'error' => '缺少 order_id']);
    exit;
}

$db = getDB();

$stmt = $db->prepare("SELECT o.*, k.key_string FROM orders o LEFT JOIN api_keys k ON o.key_id = k.id WHERE o.order_id = ? OR o.alipay_trade_no = ?");
$stmt->bindValue(1, $order_id, SQLITE3_TEXT);
$stmt->bindValue(2, $order_id, SQLITE3_TEXT);
$order = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$order) {
    echo json_encode(['success' => false, 'error' => '订单不存在']);
    exit;
}

$result = [
    'success' => true,
    'status' => $order['status'],
    'plan_name' => $order['plan_name'],
    'amount' => $order['amount'],
    'api_key' => $order['status'] === 'paid' ? $order['key_string'] : null,
    'alipay_trade_no' => $order['alipay_trade_no'] ?: null,
];

echo json_encode($result, JSON_UNESCAPED_UNICODE);
