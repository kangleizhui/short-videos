<?php
/**
 * 异步合成任务状态查询
 * AI 轮询此端点检查合成是否完成
 * 
 * 用法：
 *   GET /api/check_job.php?key=XXX&job_id=xxx
 * 
 * 返回：
 *   200 = 合成完成，data.url 即合成视频链接
 *   202 = 处理中，请稍后轮询
 *   404 = 任务不存在
 *   500 = 合成失败
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$key = $_REQUEST['key'] ?? '';
$job_id = $_REQUEST['job_id'] ?? '';

if (empty($key) || empty($job_id)) {
    http_response_code(400);
    echo json_encode(['code' => 400, 'msg' => '缺少参数: key, job_id']);
    exit;
}

// 验证密钥
$db_path = __DIR__ . '/../data/keys.db';
$db = new SQLite3($db_path);
$db->exec('PRAGMA journal_mode=WAL');
$stmt = $db->prepare("SELECT id, is_active FROM api_keys WHERE key_string = ?");
$stmt->bindValue(1, $key, SQLITE3_TEXT);
$result = $stmt->execute();
$key_data = $result->fetchArray(SQLITE3_ASSOC);
$db->close();

if (!$key_data || !$key_data['is_active']) {
    http_response_code(403);
    echo json_encode(['code' => 403, 'msg' => '密钥无效']);
    exit;
}

// 读取结果文件
$result_file = "/tmp/synth_jobs/{$job_id}.result";
$status_file = "/tmp/synth_jobs/{$job_id}.status";

if (!file_exists($result_file)) {
    http_response_code(404);
    echo json_encode(['code' => 404, 'msg' => '任务不存在或已过期']);
    exit;
}

$result = json_decode(file_get_contents($result_file), true);

if ($result['code'] === 202) {
    // 处理中
    $status = file_exists($status_file) ? file_get_contents($status_file) : 'unknown';
    http_response_code(202);
    echo json_encode(['code' => 202, 'msg' => '处理中', 'status' => $status]);
    exit;
}

// 完成或失败
http_response_code($result['code'] === 200 ? 200 : 500);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
