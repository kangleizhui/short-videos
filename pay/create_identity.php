<?php
/**
 * 生成新身份码（带设备指纹记录）
 * - 记录 IP 和 User-Agent 指纹
 * - 同设备重复调用返回已有码
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../admin/config.php';

$db = getDB();

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$fingerprint = hash('sha256', $ip . '|' . $ua);

// 查指纹是否已有码
$st = $db->prepare('SELECT code FROM identities WHERE fingerprint = ? AND is_active = 1 ORDER BY id DESC LIMIT 1');
$st->bindValue(1, $fingerprint, SQLITE3_TEXT);
$existing = $st->execute()->fetchArray(SQLITE3_ASSOC);
if ($existing) {
    echo json_encode([
        'success' => true,
        'identity_code' => $existing['code'],
        'is_existing' => true,
    ], JSON_UNESCAPED_UNICODE);
    $db->close();
    exit;
}


// 生成新码
$chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
$code = '';
for ($attempt = 0; $attempt < 200; $attempt++) {
    $c = '';
    for ($j = 0; $j < 5; $j++) $c .= $chars[random_int(0, strlen($chars) - 1)];
    try {
        $db->exec("INSERT INTO identities (code, is_active, fingerprint, created_at, created_ip) VALUES ('$c', 1, '$fingerprint', datetime('now', '+8 hours'), '$ip')");
        $code = $c;
        break;
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'UNIQUE') === false) throw $e;
    }
}

if (empty($code)) {
    echo json_encode(['success' => false, 'error' => '无法生成身份码'], JSON_UNESCAPED_UNICODE);
    $db->close();
    exit;
}

// 赠送免费每日体验
$iid = $db->lastInsertRowID();
$db->exec("INSERT INTO identity_benefits (identity_id, type, total_count, used_count, daily_limit, note, created_at) VALUES ($iid, 'free_daily', 0, 0, 10, '免费每日体验', datetime('now', '+8 hours'))");

echo json_encode([
    'success' => true,
    'identity_code' => $code,
    'is_existing' => false,
], JSON_UNESCAPED_UNICODE);

$db->close();
