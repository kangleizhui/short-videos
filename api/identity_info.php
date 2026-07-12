<?php
/**
 * 身份码信息查询 API
 * 
 * 用法:
 *   GET /api/identity_info.php?code=X7K3M
 *   GET /api/identity_info.php?key=X7K3M        ← 兼容旧写法
 * 
 * 返回身份码类型、权益列表、到期时间等信息，不消耗解析次数。
 * AI 可随时调用此接口查身份码状态。
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

$code = isset($_GET['code']) ? trim($_GET['code']) : (isset($_GET['key']) ? trim($_GET['key']) : '');

if (!$code) {
    echo json_encode(['code' => 400, 'msg' => '缺少 code 参数（身份码）'], JSON_UNESCAPED_UNICODE);
    exit;
}

$code = preg_replace('/[\s
\n]+/', '', $code);
$code = strtoupper($code);

/**
 * 获取客户端真实IP（兼容 IPv4/IPv6 + CDN 反代头）
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $first = trim($ips[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) return $first;
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = trim($_SERVER['HTTP_X_REAL_IP']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

try {
    $db = getDB();
    
    // 查找身份码
    $stmt = $db->prepare('SELECT * FROM identities WHERE code = ?');
    $stmt->bindValue(1, $code, SQLITE3_TEXT);
    $identity = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    if (!$identity) {
        echo json_encode(['code' => 404, 'msg' => '身份码不存在'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if (!$identity['is_active']) {
        echo json_encode([
            'code' => 403,
            'msg' => '身份码已停用',
            'data' => [
                'code' => $identity['code'],
                'is_active' => false,
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 记录最后查询 IP
    $client_ip = getClientIP();
    $upd = $db->prepare('UPDATE identities SET last_ip = ?, last_used_at = ? WHERE id = ?');
    $upd->bindValue(1, $client_ip, SQLITE3_TEXT);
    $upd->bindValue(2, date('Y-m-d H:i:s'), SQLITE3_TEXT);
    $upd->bindValue(3, $identity['id'], SQLITE3_INTEGER);
    $upd->execute();
    
    // 查询所有权益
    $benefits = $db->query('SELECT * FROM identity_benefits WHERE identity_id = ' . (int)$identity['id'] . ' ORDER BY id');
    $benefits_list = [];
    $today = date('Y-m-d');
    $daily_used = 0;
    
    // 查每日使用
    $stmt = $db->prepare('SELECT used_count FROM daily_usage WHERE identity_id = ? AND date = ?');
    $stmt->bindValue(1, $identity['id'], SQLITE3_INTEGER);
    $stmt->bindValue(2, $today, SQLITE3_TEXT);
    $r = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    $daily_used = $r ? (int)$r['used_count'] : 0;
    
    $remaining = null;
    $daily_remaining = null;
    $expires_at = null;
    $plans = [];
    $total_daily_limit = 0;
    
    while ($b = $benefits->fetchArray(SQLITE3_ASSOC)) {
        $is_expired = false;
        if ($b['expires_at'] && strtotime($b['expires_at']) < time()) {
            $is_expired = true;
        }
        
        $benefit_info = [
            'type' => $b['type'],
            'total_count' => (int)$b['total_count'],
            'used_count' => (int)$b['used_count'],
            'expires_at' => $b['expires_at'],
            'daily_limit' => (int)$b['daily_limit'],
            'is_expired' => $is_expired,
            'note' => $b['note'],
        ];
        
        if (!$is_expired) {
            switch ($b['type']) {
                case 'free_daily':
                    $free_left = max(0, $b['daily_limit'] - $daily_used);
                    $daily_remaining = $daily_remaining === null ? $free_left : min($daily_remaining, $free_left);
                    $total_daily_limit += $b['daily_limit'];
                    $plans[] = '免费每日';
                    break;
                case 'count':
                    if ($b['total_count'] > 0) {
                        $r = max(0, $b['total_count'] - $b['used_count']);
                        $remaining = $remaining === null ? $r : $remaining + $r;
                    }
                    $plans[] = '计次';
                    break;
                case 'monthly':
                    $plans[] = '包月';
                    $expires_at = $b['expires_at'];
                    break;
                case 'daily':
                    $plans[] = '包天';
                    $expires_at = $b['expires_at'];
                    break;
                case 'lifetime':
                    $plans[] = '永久';
                    break;
                case 'lifetime_daily':
                    $plans[] = '终身畅享';
                    $total_daily_limit += $b['daily_limit'];
                    if ($b['daily_limit'] > 0) {
                        $ld_left = max(0, $b['daily_limit'] - $daily_used);
                        $daily_remaining = $daily_remaining === null ? $ld_left : min($daily_remaining, $ld_left);
                    }
                    break;
            }
        }
        
        $benefits_list[] = $benefit_info;
    }
    
    if ($daily_remaining === null && $total_daily_limit > 0) {
        $daily_remaining = max(0, $total_daily_limit - $daily_used);
    }
    
    echo json_encode([
        'code' => 200,
        'data' => [
            'code' => $identity['code'],
            'is_active' => true,
            'plans' => $plans,
            'benefits' => $benefits_list,
            'remaining' => $remaining,
            'daily_remaining' => $daily_remaining,
            'daily_used_today' => $daily_used,
            'expires_at' => $expires_at,
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['code' => 500, 'msg' => '服务器内部错误'], JSON_UNESCAPED_UNICODE);
}
