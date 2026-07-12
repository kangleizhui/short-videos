<?php
/**
 * 查询订单状态 - 支持订单号/支付宝交易号/身份码
 * GET /pay/query.php?order_id=xxx
 * GET /pay/query.php?alipay_trade_no=xxx
 * GET /pay/query.php?identity_code=xxx
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../admin/config.php';

$order_id = $_GET['order_id'] ?? '';
$alipay_trade_no = $_GET['alipay_trade_no'] ?? '';
$identity_code = $_GET['identity_code'] ?? '';

if (!$order_id && !$alipay_trade_no && !$identity_code) {
    echo json_encode(['success' => false, 'error' => '请输入订单号、支付宝交易号或身份码']);
    exit;
}

$db = getDB();
$result = [];

// ===== 按身份码查询 =====
if ($identity_code) {
    $code = strtoupper(trim($identity_code));

    // 查询身份码信息
    $stmt = $db->prepare("SELECT * FROM identities WHERE code = ?");
    $stmt->bindValue(1, $code, SQLITE3_TEXT);
    $ident = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$ident) {
        echo json_encode(['success' => false, 'error' => '身份码不存在']);
        exit;
    }

    // 查询关联的所有权益
    $benefits = [];
    $total_usage = 0;
    $stmt2 = $db->prepare("SELECT * FROM identity_benefits WHERE identity_id = ? ORDER BY id");
    $stmt2->bindValue(1, $ident['id'], SQLITE3_INTEGER);
    $r2 = $stmt2->execute();
    while ($row = $r2->fetchArray(SQLITE3_ASSOC)) {
        $b = [
            'type' => $row['type'],
            'total_count' => (int)$row['total_count'],
            'used_count' => (int)$row['used_count'],
            'remaining' => max(0, (int)$row['total_count'] - (int)$row['used_count']),
            'daily_limit' => (int)$row['daily_limit'],
            'expires_at' => $row['expires_at'],
            'note' => $row['note'],
        ];
        $total_usage += (int)$row['used_count'];
        // 统计今日用量
        if ($row['daily_limit'] > 0) {
            $today = date('Y-m-d');
            $st3 = $db->prepare("SELECT used_count FROM daily_usage WHERE identity_id = ? AND date = ? AND benefit_id = ?");
            $st3->bindValue(1, $ident['id'], SQLITE3_INTEGER);
            $st3->bindValue(2, $today, SQLITE3_TEXT);
            $st3->bindValue(3, $row['id'], SQLITE3_INTEGER);
            $r3 = $st3->execute()->fetchArray(SQLITE3_ASSOC);
            $b['today_used'] = $r3 ? (int)$r3['used_count'] : 0;
            $b['today_remaining'] = max(0, $row['daily_limit'] - $b['today_used']);
        }
        $benefits[] = $b;
    }

    // 查询关联的订单
    $orders = [];
    $stmt4 = $db->prepare("SELECT * FROM orders WHERE identity_code = ? ORDER BY created_at DESC");
    $stmt4->bindValue(1, $code, SQLITE3_TEXT);
    $r4 = $stmt4->execute();
    while ($row = $r4->fetchArray(SQLITE3_ASSOC)) {
        $orders[] = [
            'order_id' => $row['order_id'],
            'plan_name' => $row['plan_name'],
            'amount' => $row['amount'],
            'status' => $row['status'],
            'paid_at' => $row['paid_at'],
            'created_at' => $row['created_at'],
        ];
    }

    $result = [
        'success' => true,
        'type' => 'identity',
        'identity_code' => $code,
        'is_active' => (bool)$ident['is_active'],
        'created_at' => $ident['created_at'],
        'created_ip' => $ident['created_ip'],
        'last_used_at' => $ident['last_used_at'],
        'last_ip' => $ident['last_ip'],
        'total_usage' => $total_usage,
        'benefits' => $benefits,
        'orders' => $orders,
    ];

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== 按订单号或支付宝交易号查询 =====
$search = $order_id ?: $alipay_trade_no;

$stmt = $db->prepare("SELECT o.* FROM orders o WHERE o.order_id = ? OR o.alipay_trade_no = ?");
$stmt->bindValue(1, $search, SQLITE3_TEXT);
$stmt->bindValue(2, $search, SQLITE3_TEXT);
$order = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

if (!$order) {
    echo json_encode(['success' => false, 'error' => '订单不存在']);
    exit;
}

// 查询关联的身份码信息
$identity_info = null;
if ($order['identity_code']) {
    $st5 = $db->prepare("SELECT * FROM identities WHERE code = ?");
    $st5->bindValue(1, $order['identity_code'], SQLITE3_TEXT);
    $ident2 = $st5->execute()->fetchArray(SQLITE3_ASSOC);
    if ($ident2) {
        // 查询该身份码的权益
        $benefits2 = [];
        $st6 = $db->prepare("SELECT * FROM identity_benefits WHERE identity_id = ? ORDER BY id");
        $st6->bindValue(1, $ident2['id'], SQLITE3_INTEGER);
        $r6 = $st6->execute();
        $total_usage = 0;
        while ($row = $r6->fetchArray(SQLITE3_ASSOC)) {
            $b = [
                'type' => $row['type'],
                'total_count' => (int)$row['total_count'],
                'used_count' => (int)$row['used_count'],
                'remaining' => max(0, (int)$row['total_count'] - (int)$row['used_count']),
                'daily_limit' => (int)$row['daily_limit'],
                'expires_at' => $row['expires_at'],
                'note' => $row['note'],
            ];
            $total_usage += (int)$row['used_count'];
            // 统计今日用量
            if ($row['daily_limit'] > 0) {
                $today = date('Y-m-d');
                $st7 = $db->prepare("SELECT used_count FROM daily_usage WHERE identity_id = ? AND date = ? AND benefit_id = ?");
                $st7->bindValue(1, $ident2['id'], SQLITE3_INTEGER);
                $st7->bindValue(2, $today, SQLITE3_TEXT);
                $st7->bindValue(3, $row['id'], SQLITE3_INTEGER);
                $r7 = $st7->execute()->fetchArray(SQLITE3_ASSOC);
                $b['today_used'] = $r7 ? (int)$r7['used_count'] : 0;
                $b['today_remaining'] = max(0, $row['daily_limit'] - $b['today_used']);
            }
            $benefits2[] = $b;
        }
        $identity_info = [
            'code' => $ident2['code'],
            'is_active' => (bool)$ident2['is_active'],
            'benefits' => $benefits2,
            'total_usage' => $total_usage,
            'last_used_at' => $ident2['last_used_at'],
        ];
    }
}

$result = [
    'success' => true,
    'type' => 'order',
    'order_id' => $order['order_id'],
    'plan_name' => $order['plan_name'],
    'plan_type' => $order['plan_type'],
    'amount' => $order['amount'],
    'status' => $order['status'],
    'total_count' => (int)$order['total_count'],
    'expires_days' => (int)$order['expires_days'],
    'daily_limit' => (int)$order['daily_limit'],
    'identity_code' => $order['identity_code'],
    'alipay_trade_no' => $order['alipay_trade_no'] ?: null,
    'created_at' => $order['created_at'],
    'paid_at' => $order['paid_at'],
    'identity_info' => $identity_info,
];

echo json_encode($result, JSON_UNESCAPED_UNICODE);
