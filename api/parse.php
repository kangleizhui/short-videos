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
$key = trim($_REQUEST['key'] ?? '');
$url = trim($_REQUEST['url'] ?? '');
$raw_url = trim($_REQUEST['url'] ?? '');

if (empty($key)) jsonExit(400, '缺少参数: key (API密钥)');
if (empty($url)) jsonExit(400, '缺少参数: url (视频链接)');

// 验证密钥
$db = getDB();
$stmt = $db->prepare('SELECT * FROM api_keys WHERE key_string = ?');
$stmt->bindValue(1, $key, SQLITE3_TEXT);
$result = dbExecRetry($db, $stmt);
$key_data = $result->fetchArray(SQLITE3_ASSOC);

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
        jsonExit(403, '密钥次数已用完');
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
    $parser_url = 'http://localhost:8080' . $matched_api . '?url=' . urlencode($url);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $parser_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $parsed_try = json_decode($response, true);
    if ($http_code != 200 || !$parsed_try || !isset($parsed_try['code']) || $parsed_try['code'] != 200) {
        $aggregator_url = 'http://localhost:8080/short_videos/sv1.php?url=' . urlencode($url);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $aggregator_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }
} else {
    $aggregator_url = 'http://localhost:8080/short_videos/sv1.php?url=' . urlencode($url);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $aggregator_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
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

// 更新次数
if ($status === 'success') {
    $stmt = $db->prepare('UPDATE api_keys SET used_count = used_count + 1 WHERE id = ?');
    $stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
    dbExecRetry($db, $stmt);

    if ($key_data['daily_limit'] > 0) {
        $stmt = $db->prepare('INSERT INTO key_daily_usage (key_id, date, used_count) VALUES (?, ?, 1) ON CONFLICT(key_id, date) DO UPDATE SET used_count = used_count + 1');
        $stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
        $stmt->bindValue(2, $today, SQLITE3_TEXT);
        dbExecRetry($db, $stmt);
    }

    if ($key_data['is_test']) {
        $stmt = $db->prepare('INSERT INTO test_ip_usage (key_id, ip_address, date, used_count) VALUES (?, ?, ?, 1) ON CONFLICT(key_id, ip_address, date) DO UPDATE SET used_count = used_count + 1');
        $stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
        $stmt->bindValue(2, $ip, SQLITE3_TEXT);
        $stmt->bindValue(3, $today, SQLITE3_TEXT);
        dbExecRetry($db, $stmt);
    }
}

$parsed['key_info'] = [
    'type' => $key_data['type'],
    'used' => $key_data['used_count'] + 1,
    'total' => $key_data['total_count'],
    'expires_at' => $key_data['expires_at'],
    'is_test' => (bool)$key_data['is_test'],
];

// ==========================================
// ⚡ 异步合成动图(Live Photo) — 带重复请求保护
// ==========================================
$do_synthesize = isset($parsed['data']['type']) 
    && $parsed['data']['type'] === 'live'
    && isset($parsed['data']['live_photo']) 
    && count($parsed['data']['live_photo']) > 0;

if ($do_synthesize) {
    $lp = $parsed['data']['live_photo'][0];
    $video_url = $lp['video'] ?? '';
    $image_url = $lp['image'] ?? '';
    $music_url = $parsed['data']['music']['url'] ?? '';
    
    if ($video_url && $image_url) {
        @mkdir('/tmp/synth_jobs', 0755, true);
        @mkdir('/tmp/synth_locks', 0755, true);
        
        // 用 video_url+image_url 的 hash 做去重锁
        $lock_key = md5($video_url . $image_url);
        $lock_dir = "/tmp/synth_locks/{$lock_key}";
        $lock_info = "/tmp/synth_locks/{$lock_key}.info";
        $job_id = '';
        $spawn_new = false;
        
        if (is_dir($lock_dir)) {
            $existing_job = @file_get_contents($lock_info);
            $lock_time = filemtime($lock_dir);
            $age = time() - $lock_time;
            
            if ($existing_job && $age < 300) {
                $job_id = trim($existing_job);
                $result_file = "/tmp/synth_jobs/{$job_id}.result";
                if (file_exists($result_file)) {
                    $res = json_decode(file_get_contents($result_file), true);
                    if ($res && $res['code'] === 202) {
                        // 还在处理中，复用 job_id
                        $spawn_new = false;
                    } elseif ($res && $res['code'] === 200) {
                        // 已完成，可以重新 spawn
                        $spawn_new = true;
                    } else {
                        $spawn_new = true;
                    }
                } else {
                    $spawn_new = true;
                }
            } else {
                // 锁过期
                @rmdir($lock_dir);
                @unlink($lock_info);
                $spawn_new = true;
            }
        } else {
            $spawn_new = true;
        }
        
        if ($spawn_new) {
            $job_id = uniqid('', true);
            // 原子锁：mkdir 失败说明别的请求刚创建了锁
            if (@mkdir($lock_dir, 0755)) {
                file_put_contents($lock_info, $job_id);
                
                $esc_key = escapeshellarg($key);
                $esc_mus = escapeshellarg($music_url);
                $script = __DIR__ . '/async_job_handler.php';
                
                // 启动后台进程，不阻塞
                                // 写入所有 live_photo 段到 JSON 文件（支持多段合成）
                file_put_contents("/tmp/synth_jobs/{$job_id}.photos.json", json_encode($parsed['data']['live_photo'], JSON_UNESCAPED_UNICODE));
                
                exec("php {$script} {$job_id} {$esc_key} {$esc_mus} > /dev/null 2>&1 &");
            } else {
                // 另一个请求刚创建了锁，读它的 job_id
                $existing_job = @file_get_contents($lock_info);
                if ($existing_job) $job_id = trim($existing_job);
            }
        }
        
        $parsed['data']['synthesized_url'] = null;
        $parsed['data']['synthesize_job_id'] = $job_id;
        $parsed['data']['synthesize_status'] = 'processing';
    }
}

echo json_encode($parsed, JSON_UNESCAPED_UNICODE);
$db->close();
