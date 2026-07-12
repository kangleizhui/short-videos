<?php
/**
 * 创建身份码（无需视频链接）
 * AI 首次使用专用接口，生成一个带免费每日权益的5位身份码
 * 
 * GET /api/create_identity.php
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$db_path = __DIR__ . '/../data/keys.db';

$db = new SQLite3($db_path);
$db->exec('PRAGMA journal_mode=WAL');
$db->exec('PRAGMA busy_timeout=5000');

// 确保表存在
$db->exec("CREATE TABLE IF NOT EXISTS identities (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT UNIQUE NOT NULL, is_active INTEGER DEFAULT 1, created_at TEXT, created_ip TEXT, last_ip TEXT, last_used_at TEXT)");
$db->exec("CREATE TABLE IF NOT EXISTS identity_benefits (id INTEGER PRIMARY KEY AUTOINCREMENT, identity_id INTEGER NOT NULL REFERENCES identities(id), type TEXT NOT NULL, total_count INTEGER DEFAULT 0, used_count INTEGER DEFAULT 0, expires_at TEXT, daily_limit INTEGER DEFAULT 0, frozen_remain INTEGER DEFAULT 0, note TEXT, created_at TEXT)");
$db->exec("CREATE TABLE IF NOT EXISTS daily_usage (identity_id INTEGER NOT NULL, date TEXT NOT NULL, used_count INTEGER DEFAULT 0, PRIMARY KEY (identity_id, date))");

// ===== 指纹去重：同 IP + 同 UA 不再生成新码 =====
$ip = '';
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $ip = trim($ips[0]);
} elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
    $ip = trim($_SERVER['HTTP_X_REAL_IP']);
} else {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
}
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$fingerprint = hash('sha256', $ip . '|' . $ua);

if ($fingerprint && $ip) {
    $existing = $db->prepare("SELECT code FROM identities WHERE fingerprint = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
    $existing->bindValue(1, $fingerprint, SQLITE3_TEXT);
    $row = $existing->execute()->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        echo json_encode([
            'code' => 200,
            'msg' => '返回已有身份码',
            'data' => [
                'identity_code' => $row['code'],
                'daily_limit' => 10,
                'note' => '已有身份码，直接复用',
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 生成5位身份码
$chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
$code = '';
for ($attempt = 0; $attempt < 200; $attempt++) {
    $c = '';
    for ($j = 0; $j < 5; $j++) {
        $c .= $chars[random_int(0, strlen($chars) - 1)];
    }
    try {
        $stmt = $db->prepare("INSERT INTO identities (code, is_active, created_at, created_ip, fingerprint) VALUES (?, 1, datetime('now'), ?, ?)");
        $stmt->bindValue(1, $c, SQLITE3_TEXT);
        
        $ip = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = trim($_SERVER['HTTP_X_REAL_IP']);
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        $stmt->bindValue(2, $ip ?: 'unknown', SQLITE3_TEXT);
        $stmt->bindValue(3, $fingerprint ?: null, SQLITE3_TEXT);
        $stmt->execute();
        
        $identity_id = $db->lastInsertRowID();
        
        // 添加免费每日权益
        $stmt2 = $db->prepare("INSERT INTO identity_benefits (identity_id, type, total_count, used_count, daily_limit, note, created_at) VALUES (?, 'free_daily', 0, 0, 10, '免费每日体验', datetime('now'))");
        $stmt2->bindValue(1, $identity_id, SQLITE3_INTEGER);
        $stmt2->execute();
        
        // 更新 last_ip
        $stmt3 = $db->prepare("UPDATE identities SET last_ip = ?, last_used_at = datetime('now') WHERE id = ?");
        $stmt3->bindValue(1, $ip ?: 'unknown', SQLITE3_TEXT);
        $stmt3->bindValue(2, $identity_id, SQLITE3_INTEGER);
        $stmt3->execute();
        
        $code = $c;
        break;
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'UNIQUE') === false) throw $e;
    }
}

if (empty($code)) {
    http_response_code(500);
    echo json_encode(['code' => 500, 'msg' => '无法生成唯一身份码'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'code' => 200,
    'msg' => '身份码创建成功',
    'data' => [
        'identity_code' => $code,
        'daily_limit' => 10,
        'note' => '免费每日体验，每天10次',
    ]
], JSON_UNESCAPED_UNICODE);
