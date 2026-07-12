<?php
/**
 * 身份码信息查询
 * GET /api/query_identity.php?code=你的身份码
 * 返回当前身份码的套餐信息、剩余次数、到期时间等
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../pay/identity.php';

$code = strtoupper(trim($_GET['code'] ?? ''));
if (empty($code)) {
    http_response_code(400);
    echo json_encode(['code' => 400, 'msg' => '缺少参数: code (身份码)'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db_path = __DIR__ . '/../data/keys.db';
$db = new SQLite3($db_path);
$db->exec('PRAGMA journal_mode=WAL');

$info = getIdentityInfo($db, $code);
$db->close();

if (!$info) {
    http_response_code(404);
    echo json_encode(['code' => 404, 'msg' => '身份码不存在'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'code' => 200,
    'data' => $info,
], JSON_UNESCAPED_UNICODE);
