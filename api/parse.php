<?php
/**
 * 短视频解析 API - 密钥验证版
 * 
 * 用法:
 *   GET /api/parse.php?key=XXXX-XXXX-XXXX-XXXX-XXXX&url=https://v.douyin.com/xxx/
 *   
 * 按次密钥: 每次调用消耗1次
 * 包月/包天密钥: 在有效期内无限调用
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$db_path = __DIR__ . '/../data/keys.db';

function getDB() {
    global $db_path;
    // 最多重试3次解决 "database is locked"
    for ($i = 0; $i < 3; $i++) {
        try {
            $db = new SQLite3($db_path);
            $db->exec('PRAGMA journal_mode=WAL');
            $db->exec('PRAGMA foreign_keys=ON');
            $db->exec('PRAGMA busy_timeout=5000');  // 忙时等5秒再放弃
            return $db;
        } catch (Exception $e) {
            if ($i >= 2) throw $e;
            usleep(100000 * ($i + 1)); // 100ms, 200ms, 300ms
        }
    }
}

function dbExecRetry($db, $stmt) {
    for ($i = 0; $i < 3; $i++) {
        try {
            return $stmt->execute();
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'locked') === false || $i >= 2) throw $e;
            usleep(100000 * ($i + 1));
        }
    }
}

function jsonExit($code, $msg, $data = []) {
    http_response_code($code);
    echo json_encode(['code' => $code, 'msg' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取参数
$key = trim($_REQUEST['key'] ?? $_REQUEST['code'] ?? '');
$key = preg_replace('/[\s
\n]+/', '', $key); // 清除空格/换行等干扰字符
$key = strtoupper($key); // 统一大小写
$url = trim($_REQUEST['url'] ?? '');
$raw_url = trim($_REQUEST['url'] ?? '');

if (empty($url)) jsonExit(400, '缺少参数: url (视频链接)');

// 自动创建身份码（不传key/code时）
$db = getDB();
$is_identity_mode = false;

if (empty($key)) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $fingerprint = hash('sha256', $ip . '|' . $ua);
    
    // 查指纹已有码
    $st = $db->prepare('SELECT code FROM identities WHERE fingerprint = ? AND is_active = 1 ORDER BY id DESC LIMIT 1');
    $st->bindValue(1, $fingerprint, SQLITE3_TEXT);
    $existing = $st->execute()->fetchArray(SQLITE3_ASSOC);
    if ($existing) {
        $key = $existing['code'];
    } else {
        // 生成新码
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $c = ''; for ($j = 0; $j < 5; $j++) $c .= $chars[random_int(0, strlen($chars) - 1)];
            try {
                $db->exec("INSERT INTO identities (code, is_active, fingerprint, created_at, created_ip) VALUES ('$c', 1, '$fingerprint', datetime('now'), '$ip')");
                $key = $c;
                break;
            } catch (Exception $e) { if (strpos($e->getMessage(),'UNIQUE')===false) throw $e; }
        }
        if ($key) {
            $iid = $db->lastInsertRowID();
            $db->exec("INSERT INTO identity_benefits (identity_id, type, total_count, used_count, daily_limit, note, created_at) VALUES ($iid, 'free_daily', 0, 0, 10, '免费每日体验', datetime('now'))");
        }
    }
    if (empty($key)) jsonExit(400, '无法创建身份码');
}

// 创建去重表（60秒内同一密钥+同一链接只计一次）
$db->exec('CREATE TABLE IF NOT EXISTS request_dedup (dedup_key TEXT PRIMARY KEY, counted_at INTEGER)');

$stmt = $db->prepare('SELECT * FROM api_keys WHERE key_string = ?');
$stmt->bindValue(1, $key, SQLITE3_TEXT);
$result = dbExecRetry($db, $stmt);
$key_data = $result->fetchArray(SQLITE3_ASSOC);

// 兜底：不在 api_keys 则尝试 identities
if (!$key_data) {
    $stmt2 = $db->prepare('SELECT * FROM identities WHERE code = ? AND is_active = 1');
    $stmt2->bindValue(1, $key, SQLITE3_TEXT);
    $ident = $stmt2->execute()->fetchArray(SQLITE3_ASSOC);
    if ($ident) {
        // 加载该身份下的所有权益
        $all_benefits = [];
        $benefits_result = $db->query('SELECT * FROM identity_benefits WHERE identity_id = ' . (int)$ident['id'] . ' ORDER BY id');
        while ($b = $benefits_result->fetchArray(SQLITE3_ASSOC)) {
            $all_benefits[] = $b;
        }

        if (!empty($all_benefits)) {
            // ──────────────────────────────────────────────
            // 按优先级选择消费来源：
            //   包月/包天/永久(不限量) → 免费每日 → 按量
            // ──────────────────────────────────────────────
            $target_benefit = null;  // 本次消费要扣费的权益

            // TIER 1: 不限量套餐（monthly/daily/lifetime/lifetime_daily）
            foreach ($all_benefits as $b) {
                if (in_array($b['type'], ['monthly', 'daily', 'lifetime', 'lifetime_daily'])) {
                    if (empty($b['expires_at']) || strtotime($b['expires_at']) > time()) {
                        $target_benefit = $b;
                        $target_benefit['_priority'] = 'unlimited';
                        break;
                    }
                }
            }

            // TIER 2: 免费每日（每日限额未用完）
            if (!$target_benefit) {
                foreach ($all_benefits as $b) {
                    if ($b['type'] === 'free_daily') {
                        $today = date('Y-m-d');
                        // 注：key_daily_usage.key_id = identities.id（与 deduct 段一致）
                        $st_d = $db->prepare('SELECT used_count FROM key_daily_usage WHERE key_id = ? AND date = ?');
                        $st_d->bindValue(1, $ident['id'], SQLITE3_INTEGER);
                        $r_d = $st_d->execute()->fetchArray(SQLITE3_ASSOC);
                        $daily_used = $r_d ? (int)$r_d['used_count'] : 0;
                        if ($daily_used < $b['daily_limit']) {
                            $target_benefit = $b;
                            $target_benefit['_priority'] = 'free_daily';
                            break;
                        }
                    }
                }
            }

            // TIER 3: 按量（剩余次数 > 0）
            if (!$target_benefit) {
                foreach ($all_benefits as $b) {
                    if ($b['type'] === 'count') {
                        if ($b['total_count'] <= 0 || $b['used_count'] < $b['total_count']) {
                            $target_benefit = $b;
                            $target_benefit['_priority'] = 'count';
                            break;
                        }
                    }
                }
            }

            // TIER 4: 全部用完→给具体错误（不浪费解析资源）
            if (!$target_benefit) {
                $exhausted_free_daily = null;
                $exhausted_count = null;
                foreach ($all_benefits as $b) {
                    if ($b['type'] === 'free_daily' && !$exhausted_free_daily) $exhausted_free_daily = $b;
                    if ($b['type'] === 'count' && !$exhausted_count) $exhausted_count = $b;
                }
                if ($exhausted_free_daily) {
                    jsonExit(429, '今日免费次数已用完（' . $exhausted_free_daily['daily_limit'] . '次/天），请购买套餐获取更多次数');
                } elseif ($exhausted_count) {
                    jsonExit(429, '按量次数已用完，请购买套餐');
                } else {
                    jsonExit(403, '该身份码无可用权益');
                }
            }

            if ($target_benefit) {
                $priority = $target_benefit['_priority'];
                $type_label = $priority === 'unlimited' ? 'monthly' : ($priority === 'free_daily' ? 'daily' : 'count');

                $key_data = [
                    'id' => $ident['id'],
                    'key_string' => $ident['code'],
                    'type' => $type_label,
                    'total_count' => $target_benefit['total_count'] > 0 ? $target_benefit['total_count'] : 999999,
                    'used_count' => $target_benefit['used_count'],
                    'daily_limit' => $target_benefit['daily_limit'],
                    'is_active' => 1,
                    'is_test' => 0,
                    'expires_at' => $target_benefit['expires_at'],
                    'note' => $target_benefit['note'],
                    '_target_benefit_id' => (int)$target_benefit['id'],
                    '_priority' => $priority,
                ];
                $is_identity_mode = true;
            }
        }
    }
}

if (!$key_data) jsonExit(403, '密钥无效');
if (!$key_data['is_active']) jsonExit(403, '密钥已被禁用');

// 检查过期
if ($key_data['expires_at']) {
    if (strtotime($key_data['expires_at']) < time()) {
        jsonExit(403, '密钥已过期');
    }
}

// 检查次数
if ($key_data['type'] === 'count' && $key_data['total_count'] > 0) {
    if ($key_data['used_count'] >= $key_data['total_count']) {
        // 自动停用已用完的密钥
        $stmt = $db->prepare("UPDATE api_keys SET is_active = 0 WHERE id = ?");
        $stmt->bindValue(1, $key_data["id"], SQLITE3_INTEGER);
        dbExecRetry($db, $stmt);
        jsonExit(403, "密钥次数已用完");
    }
}

// 每日上限检查
if ($key_data['daily_limit'] > 0) {
    $today = date('Y-m-d');
    $stmt = $db->prepare('SELECT used_count FROM key_daily_usage WHERE key_id = ? AND date = ?');
    $stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
    $stmt->bindValue(2, $today, SQLITE3_TEXT);
    $r = dbExecRetry($db, $stmt)->fetchArray(SQLITE3_ASSOC);
    if ($r && $r['used_count'] >= $key_data['daily_limit']) {
        jsonExit(429, '今日调用次数已达上限（' . $key_data['daily_limit'] . '次），明天再试');
    }
}

// 测试密钥的按IP每日限制
if ($key_data['is_test']) {
    $today = date('Y-m-d');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $MAX_DAILY_PER_IP = 10;
    $stmt = $db->prepare('SELECT used_count FROM test_ip_usage WHERE key_id = ? AND ip_address = ? AND date = ?');
    $stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
    $stmt->bindValue(2, $ip, SQLITE3_TEXT);
    $stmt->bindValue(3, $today, SQLITE3_TEXT);
    $r = dbExecRetry($db, $stmt)->fetchArray(SQLITE3_ASSOC);
    if ($r && $r['used_count'] >= $MAX_DAILY_PER_IP) {
        jsonExit(429, '测试密钥每日每IP限' . $MAX_DAILY_PER_IP . '次，已达上限，请购买正式套餐获取更多次数');
    }
}

set_time_limit(120);

// 调用解析引擎
$platforms = [
    'douyin' => ['keywords' => ['douyin'], 'api' => '/api/douyin/douyin.php'],
    'kuaishou' => ['keywords' => ['kuaishou'], 'api' => '/api/kuaishou/ksjx.php'],
    'bilibili' => ['keywords' => ['bilibili'], 'api' => '/api/bilibili/bilibili.php'],
    'xiaohongshu' => ['keywords' => ['xhs', 'xiaohongshu', 'xhslink'], 'api' => '/api/xiaohongshu/xhsjx.php'],
    'pipigx' => ['keywords' => ['pipigx', 'ippzone'], 'api' => '/api/pipigx.php'],
    'ppxia' => ['keywords' => ['pipix'], 'api' => '/api/ppxia.php'],
    'weibo' => ['keywords' => ['weibo'], 'api' => '/api/weibo.php'],
    'toutiao' => ['keywords' => ['toutiao'], 'api' => '/api/toutiao.php'],
];

// 检测平台
$matched_api = null;
$lowerUrl = strtolower($url);
foreach ($platforms as $name => $cfg) {
    foreach ($cfg['keywords'] as $kw) {
        if (strpos($lowerUrl, $kw) !== false) {
            $matched_api = $cfg['api'];
            break 2;
        }
    }
}

if ($matched_api) {
    $parser_url = 'https://localhost' . $matched_api . '?url=' . urlencode($url);
    $ch = curl_init();
    curl_setopt_array($ch, [
    CURLOPT_URL => $parser_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $parsed_try = json_decode($response, true);
    if ($http_code != 200 || !$parsed_try || !isset($parsed_try['code']) || $parsed_try['code'] != 200) {
    $aggregator_url = 'https://localhost/short_videos/sv2.php?url=' . urlencode($url);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $aggregator_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }
} else {
    $aggregator_url = 'https://localhost/short_videos/sv2.php?url=' . urlencode($url);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $aggregator_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
}

$parsed = json_decode($response, true);
$platform_label = $matched_api ? basename(dirname($matched_api)) : 'external';

// 记录日志
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$platform = $platform_label;
$today = date('Y-m-d');
$status = ($http_code == 200 && isset($parsed['code']) && $parsed['code'] == 200) ? 'success' : 'failed';

$stmt = $db->prepare('INSERT INTO usage_log (key_id, url, platform, ip, status) VALUES (?, ?, ?, ?, ?)');
$stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
$stmt->bindValue(2, $url, SQLITE3_TEXT);
$stmt->bindValue(3, $platform, SQLITE3_TEXT);
$stmt->bindValue(4, $ip, SQLITE3_TEXT);
$stmt->bindValue(5, $status, SQLITE3_TEXT);
dbExecRetry($db, $stmt);

if ($http_code != 200 || !$parsed || !isset($parsed['code'])) {
    jsonExit(500, '解析服务异常');
}

// 更新次数（60秒去重：同一密钥+同一链接只计一次）
$dedup_counted = false;
if ($status === 'success') {
    // 60秒内是否已计费
    $dedup_key = md5($key_data['id'] . '|' . $url);
    $st = $db->prepare('SELECT counted_at FROM request_dedup WHERE dedup_key = ?');
    $st->bindValue(1, $dedup_key, SQLITE3_TEXT);
    $dr = dbExecRetry($db, $st)->fetchArray(SQLITE3_ASSOC);
    if ($dr && (time() - $dr['counted_at']) < 60) {
        $dedup_counted = true;  // 已计过，跳过
    } else {
        // 首次计费或距上次已过60秒
        if ($is_identity_mode) {
            // 所有套餐都累加 used_count，不限量套餐也记录调用次数
            $priority = $key_data['_priority'] ?? '';
            $benefit_id = $key_data['_target_benefit_id'] ?? 0;
            if ($benefit_id > 0) {
                $stmt = $db->prepare('UPDATE identity_benefits SET used_count = used_count + 1 WHERE id = ?');
                $stmt->bindValue(1, $benefit_id, SQLITE3_INTEGER);
                dbExecRetry($db, $stmt);
            }
        } else {
            $stmt = $db->prepare('UPDATE api_keys SET used_count = used_count + 1 WHERE id = ?');
            $stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
            dbExecRetry($db, $stmt);
        }
    }

    if (!$dedup_counted) {
        if ($key_data['daily_limit'] > 0) {
            $stmt = $db->prepare('INSERT INTO key_daily_usage (key_id, date, used_count) VALUES (?, ?, 1) ON CONFLICT(key_id, date) DO UPDATE SET used_count = used_count + 1');
            $stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
            $stmt->bindValue(2, $today, SQLITE3_TEXT);
            dbExecRetry($db, $stmt);
        }

        // 同步写入 dashboard 读的 daily_usage（identity 模式必写，不限量套餐也能显示调用量）
        if ($is_identity_mode) {
            $stmt2 = $db->prepare('INSERT INTO daily_usage (identity_id, benefit_id, date, used_count) VALUES (?, ?, ?, 1) ON CONFLICT(identity_id, benefit_id, date) DO UPDATE SET used_count = used_count + 1');
            $stmt2->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
            $stmt2->bindValue(2, $benefit_id, SQLITE3_INTEGER);
            $stmt2->bindValue(3, $today, SQLITE3_TEXT);
            dbExecRetry($db, $stmt2);
        }

        if ($key_data['is_test']) {
            $stmt = $db->prepare('INSERT INTO test_ip_usage (key_id, ip_address, date, used_count) VALUES (?, ?, ?, 1) ON CONFLICT(key_id, ip_address, date) DO UPDATE SET used_count = used_count + 1');
            $stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
            $stmt->bindValue(2, $ip, SQLITE3_TEXT);
            $stmt->bindValue(3, $today, SQLITE3_TEXT);
            dbExecRetry($db, $stmt);
        }

        // 记录本次计费时间
        $st2 = $db->prepare('INSERT OR REPLACE INTO request_dedup (dedup_key, counted_at) VALUES (?, ?)');
        $st2->bindValue(1, $dedup_key, SQLITE3_TEXT);
        $st2->bindValue(2, time(), SQLITE3_INTEGER);
        dbExecRetry($db, $st2);
    }
}

$remaining = null;
$daily_remaining = null;
if ($key_data['is_test']) {
    // 测试密钥：返回当前IP今日剩余次数
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $today = date('Y-m-d');
    $st = $db->prepare('SELECT used_count FROM test_ip_usage WHERE key_id = ? AND ip_address = ? AND date = ?');
    $st->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
    $st->bindValue(2, $ip, SQLITE3_TEXT);
    $st->bindValue(3, $today, SQLITE3_TEXT);
    $r = $st->execute()->fetchArray(SQLITE3_ASSOC);
    $daily_used = $r ? (int)$r['used_count'] : 0;
    $daily_remaining = max(0, 10 - $daily_used);
} elseif ($key_data['type'] === 'count' && $key_data['total_count'] > 0) {
    // 计次密钥：剩余次数
    $remaining = max(0, $key_data['total_count'] - $key_data['used_count']);
} elseif ($key_data['daily_limit'] > 0) {
    // 每日限额密钥：今日剩余
    $today = date('Y-m-d');
    $st = $db->prepare('SELECT used_count FROM key_daily_usage WHERE key_id = ? AND date = ?');
    $st->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
    $st->bindValue(2, $today, SQLITE3_TEXT);
    $r = $st->execute()->fetchArray(SQLITE3_ASSOC);
    $daily_used = $r ? (int)$r['used_count'] : 0;
    $daily_remaining = max(0, $key_data['daily_limit'] - $daily_used);
}

$parsed['key_info'] = [
    'type' => $key_data['type'],
    'is_test' => (bool)$key_data['is_test'],
    'remaining' => $remaining,
    'daily_remaining' => $daily_remaining,
    'expires_at' => $key_data['expires_at'],
];

// 身份码模式：额外返回 identity_info
if ($is_identity_mode) {
    $parsed['identity_info'] = [
        'code' => $key,
        'is_new' => false,
        'plans' => [$key_data['type'] === 'daily' ? '免费每日' : ($key_data['type'] === 'monthly' ? '包月' : '按次')],
        'remaining' => $remaining,
        'daily_remaining' => $daily_remaining,
        'expires_at' => $key_data['expires_at'],
    ];
}

// ==========================================
// 🔁 封面/头像/图片代理重写
// ==========================================
$CF_PROXY_BASE = 'https://svproxy.kcucu.com/?mediatype=image';

// 图片（如果还没被合成逻辑处理过）
if (isset($parsed['data']['images']) && is_array($parsed['data']['images'])) {
    foreach ($parsed['data']['images'] as $k => $v) {
        if (!empty($v) && strpos($v, 'svproxy.kcucu.com') === false) {
            $parsed['data']['images'][$k] = $CF_PROXY_BASE . '&proxyurl=' . base64_encode($v);
        }
    }
}

if (!empty($parsed['data']['cover'])) {
    $parsed['data']['cover'] = $CF_PROXY_BASE . '&proxyurl=' . base64_encode($parsed['data']['cover']);
}
if (!empty($parsed['data']['author']['avatar'])) {
    $parsed['data']['author']['avatar'] = $CF_PROXY_BASE . '&proxyurl=' . base64_encode($parsed['data']['author']['avatar']);
}

echo json_encode($parsed, JSON_UNESCAPED_UNICODE);
$db->close();