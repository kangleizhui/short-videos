<?php
/**
 * 异步合成任务处理器（后台进程版）
 * 支持多个 live_photo 段依次合成
 * 
 * 用法：php async_job_handler.php <job_id> <key> <music_url>
 * live_photo 列表从 /tmp/synth_jobs/{job_id}.photos.json 读取
 */

set_time_limit(300);

$job_id = $argv[1] ?? '';
$key = $argv[2] ?? '';
$music_url = $argv[3] ?? '';

if (empty($job_id) || empty($key)) {
    file_put_contents("/tmp/synth_jobs/{$job_id}.result", json_encode(['code'=>400,'msg'=>'参数不足']));
    exit(1);
}

// 读取 live_photo 列表
$photos_file = "/tmp/synth_jobs/{$job_id}.photos.json";
if (!file_exists($photos_file)) {
    file_put_contents("/tmp/synth_jobs/{$job_id}.result", json_encode(['code'=>400,'msg'=>'缺少 live_photo 列表']));
    exit(1);
}
$live_photos = json_decode(file_get_contents($photos_file), true);
if (empty($live_photos)) {
    file_put_contents("/tmp/synth_jobs/{$job_id}.result", json_encode(['code'=>400,'msg'=>'live_photo 列表为空']));
    exit(1);
}
$total = count($live_photos);

// 验证密钥
$db_path = dirname(__DIR__) . '/data/keys.db';
$db = new SQLite3($db_path);
$db->exec('PRAGMA journal_mode=WAL');
$stmt = $db->prepare("SELECT * FROM api_keys WHERE key_string = ?");
$stmt->bindValue(1, $key, SQLITE3_TEXT);
$result = $stmt->execute();
$key_data = $result->fetchArray(SQLITE3_ASSOC);
if (!$key_data || !$key_data['is_active']) {
    file_put_contents("/tmp/synth_jobs/{$job_id}.result", json_encode(['code'=>403,'msg'=>'密钥无效']));
    exit(1);
}
$db->close();

$work = "/tmp/synth_{$job_id}";
@mkdir($work, 0755, true);
@mkdir('/tmp/synth_output', 0755, true);
@mkdir('/tmp/synth_jobs', 0755, true);

// 标记进行中
file_put_contents("/tmp/synth_jobs/{$job_id}.status", 'downloading');
file_put_contents("/tmp/synth_jobs/{$job_id}.result", json_encode([
    'code' => 202, 'msg' => 'processing', 'data' => null
]));

// 下载配乐
$has_music = false;
if (!empty($music_url)) {
    $fp = fopen("$work/music.mp3", 'w');
    $ch = curl_init($music_url);
    curl_setopt_array($ch, [CURLOPT_FILE=>$fp, CURLOPT_TIMEOUT=>30, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_SSL_VERIFYPEER=>false]);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    $has_music = filesize("$work/music.mp3") > 0;
}

// 下载所有 live_photo 素材
for ($pi = 0; $pi < $total; $pi++) {
    $video_url = $live_photos[$pi]['video'] ?? '';
    $image_url = $live_photos[$pi]['image'] ?? '';
    
    $fp = fopen("$work/live_{$pi}.mp4", 'w');
    $ch = curl_init($video_url);
    curl_setopt_array($ch, [CURLOPT_FILE=>$fp, CURLOPT_TIMEOUT=>30, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_SSL_VERIFYPEER=>false]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    if ($code != 200) {
        file_put_contents("/tmp/synth_jobs/{$job_id}.result", json_encode(['code'=>500,'msg'=>"下载动图{$pi}失败"]));
        cleanup($work); exit(1);
    }
    
    $fp = fopen("$work/tail_{$pi}.jpg", 'w');
    $ch = curl_init($image_url);
    curl_setopt_array($ch, [CURLOPT_FILE=>$fp, CURLOPT_TIMEOUT=>30, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_SSL_VERIFYPEER=>false]);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
}

file_put_contents("/tmp/synth_jobs/{$job_id}.status", 'synthesizing');

// 逐段合成
$list = '';
$total_dur = 0;

for ($pi = 0; $pi < $total; $pi++) {
    // 获取每段动图原始时长
    $dur = floatval(trim(shell_exec("ffprobe -v error -show_entries format=duration -of csv=p=0 " . escapeshellarg("$work/live_{$pi}.mp4"))));
    if ($dur <= 0) $dur = 3.0;
    $D = number_format($dur, 2, '.', '');
    
    // 生成循环素材（全部 ss=0）
    shell_exec("ffmpeg -y -stream_loop 6 -i " . escapeshellarg("$work/live_{$pi}.mp4") . " -t 20 -r 30 -vf \"scale=1080:-2\" -c:v libx264 -preset veryfast -crf 28 -c:a aac -b:a 96k " . escapeshellarg("$work/motion_{$pi}.mp4") . " 2>/dev/null");
    shell_exec("ffmpeg -y -loop 1 -i " . escapeshellarg("$work/tail_{$pi}.jpg") . " -t 20 -r 30 -vf \"scale=1080:-2\" -c:v libx264 -preset veryfast -crf 28 -an " . escapeshellarg("$work/still_{$pi}.mp4") . " 2>/dev/null");
    
    // 每个素材：1段动图 + 1段静图（⚠️ 全部 ss=0）
    shell_exec("ffmpeg -y -i " . escapeshellarg("$work/motion_{$pi}.mp4") . " -ss 0 -t $D -c:v libx264 -preset ultrafast -crf 28 -g 1 -an -fflags +genpts " . escapeshellarg("$work/m{$pi}.mp4") . " 2>/dev/null");
    shell_exec("ffmpeg -y -i " . escapeshellarg("$work/still_{$pi}.mp4") . " -ss 0 -t $D -c:v libx264 -preset ultrafast -crf 28 -g 1 -an -fflags +genpts " . escapeshellarg("$work/s{$pi}.mp4") . " 2>/dev/null");
    
    $list .= "file " . escapeshellarg("$work/m{$pi}.mp4") . "\n";
    $list .= "file " . escapeshellarg("$work/s{$pi}.mp4") . "\n";
    $total_dur += floatval($D) * 2;
}

file_put_contents("$work/list.txt", $list);

// concat 全片
shell_exec("ffmpeg -y -f concat -safe 0 -i " . escapeshellarg("$work/list.txt") . " -fflags +genpts -r 30 -c:v libx264 -preset veryfast -crf 28 " . escapeshellarg("$work/video.mp4") . " 2>/dev/null");

if (!file_exists("$work/video.mp4")) {
    file_put_contents("/tmp/synth_jobs/{$job_id}.result", json_encode(['code'=>500,'msg'=>'合并失败']));
    cleanup($work); exit(1);
}

// 加音频（用第一个视频的原声）
$vd = floatval(trim(shell_exec("ffprobe -v error -show_entries format=duration -of csv=p=0 " . escapeshellarg("$work/video.mp4"))));
if ($vd <= 0) $vd = $total_dur;
$td = number_format($vd, 2, '.', '');

if ($has_music) {
    $audio_cmd = "ffmpeg -y -i " . escapeshellarg("$work/video.mp4") . " -stream_loop 6 -i " . escapeshellarg("$work/live_0.mp4") . " -stream_loop -1 -i " . escapeshellarg("$work/music.mp3");
    $audio_cmd .= " -filter_complex \"[1:a]volume=1.7,atrim=0:{$td}[orig];[2:a]volume=1.0,atrim=0:{$td}[bgm];[orig][bgm]amix=inputs=2:duration=first:weights=1.7 1.0[aout]\"";
    $audio_cmd .= " -map \"[aout]\" -map 0:v -c:v copy -c:a aac -b:a 96k -movflags +faststart " . escapeshellarg("$work/final.mp4") . " 2>/dev/null";
} else {
    $audio_cmd = "ffmpeg -y -i " . escapeshellarg("$work/video.mp4") . " -stream_loop 6 -i " . escapeshellarg("$work/live_0.mp4");
    $audio_cmd .= " -filter_complex \"[1:a]volume=1.7,atrim=0:{$td}[orig]\"";
    $audio_cmd .= " -map \"[orig]\" -map 0:v -c:v copy -c:a aac -b:a 96k -movflags +faststart " . escapeshellarg("$work/final.mp4") . " 2>/dev/null";
}
shell_exec($audio_cmd);

if (!file_exists("$work/final.mp4")) {
    file_put_contents("/tmp/synth_jobs/{$job_id}.result", json_encode(['code'=>500,'msg'=>'音频合成失败']));
    cleanup($work); exit(1);
}

// 输出
$id = "synth_{$job_id}";
@copy("$work/final.mp4", "/tmp/synth_output/{$id}.mp4");
@copy("$work/final.mp4", dirname(__DIR__) . "/synth/{$id}.mp4");
$final_dur = floatval(trim(shell_exec("ffprobe -v error -show_entries format=duration -of csv=p=0 " . escapeshellarg("$work/final.mp4"))));
cleanup($work);

file_put_contents("/tmp/synth_jobs/{$job_id}.result", json_encode([
    'code' => 200,
    'msg' => '合成成功',
    'data' => [
        'url' => 'https://spqsy.kcucu.com/synth/' . $id . '.mp4',
        'duration' => $final_dur,
        'segments' => $total,
    ]
], JSON_UNESCAPED_UNICODE));

// 计次
$db = new SQLite3($db_path);
$db->exec('PRAGMA journal_mode=WAL');
$stmt = $db->prepare("UPDATE api_keys SET used_count = used_count + 1 WHERE id = ?");
$stmt->bindValue(1, $key_data['id'], SQLITE3_INTEGER);
$stmt->execute();
$db->close();

shell_exec("(sleep 600 && rm -f /tmp/synth_jobs/{$job_id}.*) &");

function cleanup($dir) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = "$dir/$f";
        is_file($p) && unlink($p);
    }
    @rmdir($dir);
}
