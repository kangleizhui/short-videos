<?php
/**
 * 密钥信息查询 API
 * 
 * 用法:
 *   GET /api/key_info.php?key=XXXX-XXXX-XXXX-XXXX-XXXX
 *   
 * 返回密钥类型、剩余次数、到期时间等信息，不消耗解析次数。
 * AI 可随时调用此接口查密钥状态，无需附带视频链接。
 * 可用于任何密钥（测试/计次/包月/包天/永久），包括未来的。
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
            $db->exec('PRAGMA foreign_keys=ON');
            $db->exec('PRAGMA busy_timeout=5000');
            return $db;
        } catch (Exception $e) {
            if ($i >= 2) throw $e;
            usleep(100000 * ($i + 1));
        }
    }
}

$key = isset($_GET['key']) ? trim($_GET['key']) : '';

if (!$key) {
    echo json_encode(['code' => 400, 'msg' => '缺少 key 参数'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 清洗密钥中的空格/换行
$key = preg_replace('/[\s\r\n]+/', '', $key);

try {
    $db = getDB();
    
    $stmt = $db->prepare('SELECT id, key_string, type, total_count, used_count, expires_at, is_active, is_test, daily_limit FROM api_keys WHERE key_string = ?');
    $stmt->bindValue(1, $key, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    if (!$row) {
        echo json_encode(['code' => 404, 'msg' => '密钥不存在'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if (!$row['is_active']) {
        echo json_encode([
            'code' => 403,
            'msg' => '密钥已停用',
            'data' => [
                'key' => $row['key_string'],
                'type' => $row['type'],
                'is_test' => (bool)$row['is_test'],
                'is_active' => false,
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 计算剩余次数（count类型，total_count=0表示无限制）
    $remaining = null;
    if ($row['type'] === 'count' && $row['total_count'] > 0) {
        $remaining = max(0, $row['total_count'] - $row['used_count']);
    }
    
    // 计算每日剩余
    $daily_remaining = null;
    if ($row['daily_limit'] > 0) {
        // 全局每日限额
        $today = date('Y-m-d');
        $st = $db->prepare("SELECT SUM(used_count) as used FROM daily_usage WHERE key_id = ? AND date = ?");
        $st->bindValue(1, $row['id'], SQLITE3_INTEGER);
        $st->bindValue(2, $today, SQLITE3_TEXT);
        $r = $st->execute()->fetchArray(SQLITE3_ASSOC);
        $daily_used = $r ? (int)$r['used'] : 0;
        $daily_remaining = max(0, $row['daily_limit'] - $daily_used);
    } elseif ($row['is_test']) {
        // 测试密钥：按调用者IP查今日剩余
        $today = date('Y-m-d');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $MAX = 10;
        $st = $db->prepare("SELECT used_count FROM test_ip_usage WHERE key_id = ? AND ip_address = ? AND date = ?");
        $st->bindValue(1, $row['id'], SQLITE3_INTEGER);
        $st->bindValue(2, $ip, SQLITE3_TEXT);
        $st->bindValue(3, $today, SQLITE3_TEXT);
        $r = $st->execute()->fetchArray(SQLITE3_ASSOC);
        $used = $r ? (int)$r['used_count'] : 0;
        $daily_remaining = max(0, $MAX - $used);
    }
    
    echo json_encode([
        'code' => 200,
        'data' => [
            'key' => $row['key_string'],
            'type' => $row['type'],
            'is_test' => (bool)$row['is_test'],
            'is_active' => true,
            'total_count' => (int)$row['total_count'],
            'used_count' => (int)$row['used_count'],
            'remaining' => $remaining,
            'daily_remaining' => $daily_remaining,
            'expires_at' => $row['expires_at'],
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['code' => 500, 'msg' => '服务器内部错误'], JSON_UNESCAPED_UNICODE);
}
