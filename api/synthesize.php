<?php
/**
 * 动图(Live Photo)合成 API
 * 服务端用 FFmpeg 合成交替式动图视频
 * AI 不需要 FFmpeg 环境，直接拿合成好的视频链接发给用户
 * 
 * 用法：
 *   GET /api/synthesize.php?key=密钥&video_url=动图视频URL&image_url=尾帧静图URL&music_url=配乐URL
 */
header('Content-Type: application/json; charset=utf-8');
set_time_limit(180);

$key = $_REQUEST['key'] ?? '';
$video_url = $_REQUEST['video_url'] ?? '';
$image_url = $_REQUEST['image_url'] ?? '';
$music_url = $_REQUEST['music_url'] ?? '';

if (empty($key)) { http_response_code(400); die(json_encode(['code'=>400,'msg'=>'缺少参数: key'])); }
if (empty($video_url) || empty($image_url)) { http_response_code(400); die(json_encode(['code'=>400,'msg'=>'缺少参数: video_url 和 image_url 是必需的'])); }

// 验证密钥
$db_path = __DIR__ . '/../data/keys.db';
$db = new SQLite3($db_path);
$stmt = $db->prepare("SELECT * FROM api_keys WHERE key_string = ?");
$stmt->bindValue(1, $key, SQLITE3_TEXT);
$result = $stmt->execute();
$key_data = $result->fetchArray(SQLITE3_ASSOC);
if (!$key_data || !$key_data['is_active']) { http_response_code(403); die(json_encode(['code'=>403,'msg'=>'密钥无效'])); }
if ($key_data['expires_at'] && strtotime($key_data['expires_at']) < time()) { http_response_code(403); die(json_encode(['code'=>403,'msg'=>'密钥已过期'])); }
if ($key_data['type'] === 'count' && $key_data['total_count'] > 0 && $key_data['used_count'] >= $key_data['total_count']) { http_response_code(403); die(json_encode(['code'=>403,'msg'=>'密钥次数已用完'])); }
if ($key_data['is_test']) {
    $stmt = $db->prepare("SELECT used_count FROM test_ip_usage WHERE key_id = ? AND ip_address = ? AND date = ?");
    $stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
    $stmt->bindValue(2, $_SERVER['REMOTE_ADDR'] ?? 'unknown', SQLITE3_TEXT);
    $stmt->bindValue(3, date('Y-m-d'), SQLITE3_TEXT);
    $r = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if ($r && $r['used_count'] >= 10) { http_response_code(429); die(json_encode(['code'=>429,'msg'=>'测试密钥每日每IP限10次'])); }
}

$work = '/tmp/synth_' . uniqid();
@mkdir($work, 0755, true);
@mkdir('/tmp/synth_output', 0755, true);

// 下载素材
$fp = fopen("$work/live.mp4", 'w');
$ch = curl_init($video_url);
curl_setopt_array($ch, [CURLOPT_FILE=>$fp, CURLOPT_TIMEOUT=>30, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_SSL_VERIFYPEER=>false]);
curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); fclose($fp);
if ($code != 200) { cleanup($work); http_response_code(500); die(json_encode(['code'=>500,'msg'=>'下载动图视频失败'])); }

$fp = fopen("$work/tail.jpg", 'w');
$ch = curl_init($image_url);
curl_setopt_array($ch, [CURLOPT_FILE=>$fp, CURLOPT_TIMEOUT=>30, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_SSL_VERIFYPEER=>false]);
curl_exec($ch); curl_close($ch); fclose($fp);

$has_music = false;
if (!empty($music_url)) {
    $fp = fopen("$work/music.mp3", 'w');
    $ch = curl_init($music_url);
    curl_setopt_array($ch, [CURLOPT_FILE=>$fp, CURLOPT_TIMEOUT=>30, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_SSL_VERIFYPEER=>false]);
    curl_exec($ch); curl_close($ch); fclose($fp);
    $has_music = filesize("$work/music.mp3") > 0;
}

// 获取动图原始时长
$dur = floatval(trim(shell_exec("ffprobe -v error -show_entries format=duration -of csv=p=0 " . escapeshellarg("$work/live.mp4"))));
if ($dur <= 0) $dur = 3.0;
$D = number_format($dur, 2, '.', '');

// 生成30fps循环素材
shell_exec("ffmpeg -y -stream_loop 6 -i " . escapeshellarg("$work/live.mp4") . " -t 20 -r 30 -vf \"scale=1080:-2\" -c:v libx264 -preset veryfast -crf 28 -c:a aac -b:a 96k " . escapeshellarg("$work/motion.mp4") . " 2>/dev/null");
shell_exec("ffmpeg -y -loop 1 -i " . escapeshellarg("$work/tail.jpg") . " -t 20 -r 30 -vf \"scale=1080:-2\" -c:v libx264 -preset veryfast -crf 28 -an " . escapeshellarg("$work/still.mp4") . " 2>/dev/null");

// 切割3个cycle（⚠️ 全部 ss=0，绝不能从不同时间点取！）
// 反例：seg_m0取0~3s、seg_m1取6~9s → 每段开头画面不同 → 动图静图切换时画面突变 = "闪"
// 正解：全部从第0秒取同一段源 → 画面连续顺滑
for ($i = 0; $i < 3; $i++) {
    shell_exec("ffmpeg -y -i " . escapeshellarg("$work/motion.mp4") . " -ss 0 -t $D -c:v libx264 -preset ultrafast -crf 28 -g 1 -an -fflags +genpts " . escapeshellarg("$work/m$i.mp4") . " 2>/dev/null");
    shell_exec("ffmpeg -y -i " . escapeshellarg("$work/still.mp4") . " -ss 0 -t $D -c:v libx264 -preset ultrafast -crf 28 -g 1 -an -fflags +genpts " . escapeshellarg("$work/s$i.mp4") . " 2>/dev/null");
}

// concat
$list = '';
for ($i = 0; $i < 3; $i++) {
    $list .= "file " . escapeshellarg("$work/m$i.mp4") . "\n";
    $list .= "file " . escapeshellarg("$work/s$i.mp4") . "\n";
}
file_put_contents("$work/list.txt", $list);
shell_exec("ffmpeg -y -f concat -safe 0 -i " . escapeshellarg("$work/list.txt") . " -fflags +genpts -r 30 -c:v libx264 -preset veryfast -crf 28 " . escapeshellarg("$work/video.mp4") . " 2>/dev/null");

// 获取视频时长
$vd = floatval(trim(shell_exec("ffprobe -v error -show_entries format=duration -of csv=p=0 " . escapeshellarg("$work/video.mp4"))));
if ($vd <= 0) $vd = floatval($D) * 6;
$td = number_format($vd, 2, '.', '');

// 加音频
$audio_cmd = "ffmpeg -y -i " . escapeshellarg("$work/video.mp4") . " -stream_loop 6 -i " . escapeshellarg("$work/live.mp4");
if ($has_music) $audio_cmd .= " -stream_loop -1 -i " . escapeshellarg("$work/music.mp3");
$audio_cmd .= " -filter_complex \"[1:a]volume=1.7,atrim=0:{$td}[orig]";
if ($has_music) $audio_cmd .= ";[2:a]volume=1.0,atrim=0:{$td}[bgm];[orig][bgm]amix=inputs=2:duration=first:weights=1.7 1.0[aout]\" -map \"[aout]\"";
else $audio_cmd .= "\" -map \"[orig]\"";
$audio_cmd .= " -map 0:v -c:v copy -c:a aac -b:a 96k " . escapeshellarg("$work/final.mp4") . " 2>/dev/null";
shell_exec($audio_cmd);

if (!file_exists("$work/final.mp4")) {
    cleanup($work);
    http_response_code(500);
    die(json_encode(['code'=>500,'msg'=>'合成失败']));
}

// 复制到web可访问
$id = uniqid('synth_');
@copy("$work/final.mp4", "/tmp/synth_output/$id.mp4");
@copy("$work/final.mp4", "/var/www/short-videos/synth/$id.mp4");
$final_dur = floatval(trim(shell_exec("ffprobe -v error -show_entries format=duration -of csv=p=0 " . escapeshellarg("$work/final.mp4"))));
cleanup($work);

// 记录用量
$stmt = $db->prepare("UPDATE api_keys SET used_count = used_count + 1 WHERE id = ?");
$stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
$stmt->execute();
$db->close();

echo json_encode([
    'code' => 200,
    'msg' => '合成成功',
    'data' => [
        'url' => 'http://101.32.98.240:8080/synth/' . $id . '.mp4',
        'duration' => $final_dur,
        'segment_duration' => floatval($D),
        'total_segments' => 6,
    ]
], JSON_UNESCAPED_UNICODE);

function cleanup($dir) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = "$dir/$f";
        is_file($p) && unlink($p);
    }
    @rmdir($dir);
}
