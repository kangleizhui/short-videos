<?php
/**
 * 身份码 API — 供 AI/用户获取身份码
 * 
 * 用法:
 *   GET /pay/identity.php               → 自动生成新身份码
 *   GET /pay/identity.php?code=XXXXX    → 使用用户自定义码（需5位、未占用）
 * 
 * 返回:
 *   {"code":200, "data":{"identity_code":"XXXXX", "is_new":true}}
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$db_path = __DIR__ . '/../data/keys.db';

function getDB() {
    global $db_path;
    for ($i = 0; $i < 3; $i++) {
        try {
            $db = new SQLite3($db_path);
            $db->exec('PRAGMA journal_mode=WAL');
            $db->exec('PRAGMA busy_timeout=5000');
            return $db;
        } catch (Exception $e) {
            if ($i >= 2) throw $e;
            usleep(100000 * ($i + 1));
        }
    }
}

function jsonExit($code, $msg, $data = []) {
    http_response_code($code);
    echo json_encode(['code' => $code, 'msg' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// 创建 identity 权益（免费每日10次）
function createFreeBenefit($db, $identity_id) {
    $stmt = $db->prepare("INSERT INTO identity_benefits (identity_id, type, total_count, used_count, daily_limit, note, created_at) VALUES (?, 'free_daily', 0, 0, 10, '免费每日体验', datetime('now', '+8 hours'))");
    $stmt->bindValue(1, $identity_id, SQLITE3_INTEGER);
    $stmt->execute();
}

$db = getDB();

// 确保表存在
$db->exec("CREATE TABLE IF NOT EXISTS identities (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT UNIQUE NOT NULL, is_active INTEGER DEFAULT 1, created_at TEXT, created_ip TEXT, fingerprint TEXT)");
$db->exec("CREATE TABLE IF NOT EXISTS identity_benefits (id INTEGER PRIMARY KEY AUTOINCREMENT, identity_id INTEGER NOT NULL REFERENCES identities(id), type TEXT NOT NULL, total_count INTEGER DEFAULT 0, used_count INTEGER DEFAULT 0, expires_at TEXT, daily_limit INTEGER DEFAULT 0, frozen_remain INTEGER DEFAULT 0, note TEXT, created_at TEXT)");

$code = isset($_GET['code']) ? trim($_GET['code']) : '';

if ($code) {
    // ===== 用户自定义码 =====
    $code = strtoupper($code);
    
    // 验证格式：5位字母数字
    if (!preg_match('/^[A-Z0-9]{5}$/', $code)) {
        jsonExit(400, '身份码格式错误，需5位大写字母+数字');
    }
    
    // 查重复
    $stmt = $db->prepare('SELECT id FROM identities WHERE code = ?');
    $stmt->bindValue(1, $code, SQLITE3_TEXT);
    if ($stmt->execute()->fetchArray()) {
        jsonExit(409, '该身份码已被占用');
    }
    
    // 创建
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $db->prepare("INSERT INTO identities (code, is_active, created_at, created_ip) VALUES (?, 1, datetime('now', '+8 hours'), ?)");
    $stmt->bindValue(1, $code, SQLITE3_TEXT);
    $stmt->bindValue(2, $ip, SQLITE3_TEXT);
    $stmt->execute();
    $identity_id = $db->lastInsertRowID();
    
    createFreeBenefit($db, $identity_id);
    
    jsonExit(200, '身份码已创建', [
        'identity_code' => $code,
        'is_new' => true,
    ]);
} else {
    // ===== 自动生成 =====
    // 查指纹已有码
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $fingerprint = hash('sha256', $ip . '|' . $ua);
    
    $stmt = $db->prepare('SELECT code FROM identities WHERE fingerprint = ? AND is_active = 1 ORDER BY id DESC LIMIT 1');
    $stmt->bindValue(1, $fingerprint, SQLITE3_TEXT);
    $existing = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if ($existing) {
        jsonExit(200, '返回已有身份码', [
            'identity_code' => $existing['code'],
            'is_new' => false,
            'note' => '已有身份码，直接复用',
        ]);
    }
    
    // 生成新码
    $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    for ($attempt = 0; $attempt < 200; $attempt++) {
        $c = '';
        for ($j = 0; $j < 5; $j++) {
            $c .= $chars[random_int(0, strlen($chars) - 1)];
        }
        try {
            $stmt = $db->prepare("INSERT INTO identities (code, is_active, fingerprint, created_at, created_ip) VALUES (?, 1, ?, datetime('now', '+8 hours'), ?)");
            $stmt->bindValue(1, $c, SQLITE3_TEXT);
            $stmt->bindValue(2, $fingerprint, SQLITE3_TEXT);
            $stmt->bindValue(3, $ip, SQLITE3_TEXT);
            $stmt->execute();
            $identity_id = $db->lastInsertRowID();
            createFreeBenefit($db, $identity_id);
            jsonExit(200, '新身份码已生成', [
                'identity_code' => $c,
                'is_new' => true,
            ]);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'UNIQUE') === false) throw $e;
        }
    }
    
    jsonExit(500, '无法生成唯一身份码');
}
