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
    $db = new SQLite3($db_path);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('PRAGMA foreign_keys=ON');
    return $db;
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
$stmt = $db->prepare("SELECT * FROM api_keys WHERE key_string = ?");
$stmt->bindValue(1, $key, SQLITE3_TEXT);
$result = $stmt->execute();
$key_data = $result->fetchArray(SQLITE3_ASSOC);

if (!$key_data) jsonExit(403, '密钥无效');
if (!$key_data['is_active']) jsonExit(403, '密钥已被禁用');

// 检查过期
if ($key_data['expires_at']) {
    if (strtotime($key_data['expires_at']) < time()) {
        jsonExit(403, '密钥已过期');
    }
}

// 检查次数（type=count 且 total_count > 0 才检查，0=无限）
if ($key_data['type'] === 'count' && $key_data['total_count'] > 0) {
    if ($key_data['used_count'] >= $key_data['total_count']) {
        jsonExit(403, '密钥次数已用完');
    }
}


// 每日上限检查（daily_limit > 0 的密钥）
if ($key_data['daily_limit'] > 0) {
    $today = date('Y-m-d');
    
    // 查今天用了多少次
    $stmt = $db->prepare("SELECT used_count FROM key_daily_usage WHERE key_id = ? AND date = ?");
    $stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
    $stmt->bindValue(2, $today, SQLITE3_TEXT);
    $r = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    if ($r && $r['used_count'] >= $key_data['daily_limit']) {
        jsonExit(429, '今日调用次数已达上限（' . $key_data['daily_limit'] . '次），明天再试');
    }
}

// 测试密钥的按IP每日限制（防止单用户刷完所有次数）
if ($key_data['is_test']) {
    $today = date('Y-m-d');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $MAX_DAILY_PER_IP = 10;
    
    // 查今天这个IP用了多少次
    $stmt = $db->prepare("SELECT used_count FROM test_ip_usage WHERE key_id = ? AND ip_address = ? AND date = ?");
    $stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
    $stmt->bindValue(2, $ip, SQLITE3_TEXT);
    $stmt->bindValue(3, $today, SQLITE3_TEXT);
    $r = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    
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
    // 调用本地解析器
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
    
    // 降级到外部聚合器
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
    // 未知平台，降级到外部聚合器
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

// 平台标签
$platform_label = $matched_api ? basename(dirname($matched_api)) : 'external';

// 记录调用日志（无论成功失败，用于调试）
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$platform = $platform_label;
$today = date('Y-m-d');
$status = ($http_code == 200 && isset($parsed['code']) && $parsed['code'] == 200) ? 'success' : 'failed';

$stmt = $db->prepare("INSERT INTO usage_log (key_id, url, platform, ip, status) VALUES (?, ?, ?, ?, ?)");
$stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
$stmt->bindValue(2, $url, SQLITE3_TEXT);
$stmt->bindValue(3, $platform, SQLITE3_TEXT);
$stmt->bindValue(4, $ip, SQLITE3_TEXT);
$stmt->bindValue(5, $status, SQLITE3_TEXT);
$stmt->execute();

// ❗ 检查解析结果 — 只有成功才消耗次数
if ($http_code != 200 || !$parsed || !isset($parsed['code'])) {
    jsonExit(500, '解析服务异常');
}

// ✅ 解析成功 → 更新使用次数
if ($status === 'success') {
    // 更新密钥使用次数
    $stmt = $db->prepare("UPDATE api_keys SET used_count = used_count + 1 WHERE id = ?");
    $stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
    $stmt->execute();

    // 记录每日用量（仅日限密钥）
    if ($key_data['daily_limit'] > 0) {
        $stmt = $db->prepare("INSERT INTO key_daily_usage (key_id, date, used_count) VALUES (?, ?, 1) ON CONFLICT(key_id, date) DO UPDATE SET used_count = used_count + 1");
        $stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
        $stmt->bindValue(2, $today, SQLITE3_TEXT);
        $stmt->execute();
    }

    // 测试密钥记录按IP用量
    if ($key_data['is_test']) {
        $stmt = $db->prepare("INSERT INTO test_ip_usage (key_id, ip_address, date, used_count) VALUES (?, ?, ?, 1) ON CONFLICT(key_id, ip_address, date) DO UPDATE SET used_count = used_count + 1");
        $stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
        $stmt->bindValue(2, $ip, SQLITE3_TEXT);
        $stmt->bindValue(3, $today, SQLITE3_TEXT);
        $stmt->execute();
    }
}

// 附加密钥使用信息
$parsed['key_info'] = [
    'type' => $key_data['type'],
    'used' => $key_data['used_count'] + 1,
    'total' => $key_data['total_count'],
    'expires_at' => $key_data['expires_at'],
    'is_test' => (bool)$key_data['is_test'],
];

// ==========================================
// 自动合成动图(Live Photo)
// 检测到 type=live 且有 live_photo 数据时，
// 服务端自动用 FFmpeg 合成交替式视频
// AI 直接拿 synthesized_url 下载发给用户
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
        $synth_params = http_build_query([
            'key' => $key,
            'video_url' => $video_url,
            'image_url' => $image_url,
            'music_url' => $music_url,
        ]);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'http://localhost:8080/api/synthesize.php?' . $synth_params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $synth_resp = curl_exec($ch);
        $synth_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($synth_http == 200) {
            $synth_data = json_decode($synth_resp, true);
            if (isset($synth_data['data']['url'])) {
                $parsed['data']['synthesized_url'] = $synth_data['data']['url'];
                $parsed['data']['synthesized_duration'] = $synth_data['data']['duration'] ?? 0;
            }
        }
    }
}

echo json_encode($parsed, JSON_UNESCAPED_UNICODE);
$db->close();
